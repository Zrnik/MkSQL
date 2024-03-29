<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Zrnik\MkSQL;

use PDO;
use PDOException;
use Throwable;
use Zrnik\MkSQL\Enum\DriverType;
use Zrnik\MkSQL\Exceptions\InvalidArgumentException;
use Zrnik\MkSQL\Exceptions\InvalidDriverException;
use Zrnik\MkSQL\Exceptions\TableCreationFailedException;
use Zrnik\MkSQL\Exceptions\TableDefinitionExists;
use Zrnik\MkSQL\Exceptions\UnexpectedCall;
use Zrnik\MkSQL\Queries\Makers\IQueryMaker;
use Zrnik\MkSQL\Queries\Query;
use Zrnik\MkSQL\Queries\Tables\TableDescription;
use Zrnik\MkSQL\Repository\BaseEntity;
use Zrnik\MkSQL\Tracy\Measure;
use Zrnik\MkSQL\Utilities\Installable;
use Zrnik\MkSQL\Utilities\TableOrder;
use function array_key_exists;
use function count;

/**
 * @package Zrnik\MkSQL
 */
class Updater
{
    private PDO $pdo;

    /**
     * @internal
     */
    public ?string $installable = null;
    public ?Throwable $lastError = null;

    /**
     * Updater constructor.
     * @param PDO $pdo
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;

        $options = [
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ];

        foreach ($options as $key => $value) {
            $this->pdo->setAttribute($key, $value);
        }
    }

    //region Tables

    /** @var array<string, Table> */
    private array $tables = [];

    /** @var string[] */
    private array $tablesInProgress = [];

    /**
     * @param string $tableName
     * @param bool $rewrite
     * @return Table
     * @throws TableDefinitionExists
     * @throws InvalidArgumentException
     */
    public function tableCreate(string $tableName, bool $rewrite = false): Table
    {
        $this->tablesInProgress[] = $tableName;
        $newTable = new Table($tableName);
        return $this->tableAdd($newTable, $rewrite);
    }

    /**
     * @param Table $table
     * @param bool $rewrite
     * @return Table
     * @throws TableDefinitionExists
     */
    public function tableAdd(Table $table, bool $rewrite = false): Table
    {
        if (!$rewrite && isset($this->tables[$table->getName()])) {
            throw new TableDefinitionExists("Table '" . $table->getName() . "' already defined!");
        }

        $this->tables[$table->getName()] = $table;
        $table->setParent($this);

        /** @var string|false $key */
        $key = array_search($table->getName(), $this->tablesInProgress, true);

        if ($key !== false) {
            unset($this->tablesInProgress[(string)$key]);
        }

        return $table;
    }

    /**
     * @return array<string, Table>
     */
    public function tableList(): array
    {
        return $this->tables;
    }

    /**
     * @param string $tableName
     * @return Table|null
     */
    public function tableGet(string $tableName): ?Table
    {
        return $this->tables[$tableName] ?? null;
    }

    /**
     * @param string $tableName
     * @return bool
     */
    public function tableRemove(string $tableName): bool
    {
        $exists = array_key_exists($tableName, $this->tables);

        if ($exists) {
            unset($this->tables[$tableName]);
        }

        /** @var string|false $key */
        $key = array_search($tableName, $this->tablesInProgress, true);

        if ($key !== false) {
            /**
             * @var string $key
             * @noinspection PhpRedundantVariableDocTypeInspection
             */
            unset($this->tablesInProgress[$key]); // @codeCoverageIgnore
        }


        return $exists;
    }
    //endregion

    /**
     * @throws InvalidArgumentException
     * @throws InvalidDriverException
     * @throws UnexpectedCall
     */
    public function install(): void
    {
        if ($this->installable !== null) {
            throw new UnexpectedCall(
                sprintf(
                    "Please, do not call '\$updater->install()' inside a '%s::install()' method, as its already handled by '%s'!",
                    $this->installable,
                    Installable::class
                )
            );
        }

        $timer_total = microtime(true);

        //region DriverCheck

        $driverName = DriverType::getName($this->getDriverType());

        /** @var IQueryMaker $QueryMakerClass */
        $QueryMakerClass = "\\Zrnik\\MkSQL\\Queries\\Makers\\QueryMaker" . $driverName;

        /**
         * @var string $QueryMakerClassCheck
         */
        $QueryMakerClassCheck = $QueryMakerClass;

        if (!class_exists($QueryMakerClassCheck)) {
            // @codeCoverageIgnoreStart
            throw new InvalidDriverException(
                sprintf(
                    "Invalid driver '%s' for package `zrnik/mksql` class '%s'\nAllowed drivers: '%s'.",
                    $driverName, __CLASS__, implode('\', \'', DriverType::getNames(false))
                )
            );
            // @codeCoverageIgnoreEnd
        }
        //endregion

        /**
         * @var Query[] $QueryCommands
         */
        $QueryCommands = [];

        $sortedTables = TableOrder::doOrder($this->tables);

        foreach ($sortedTables as $table) {


            if ($this->isAlreadyProcessed($table)) {
                continue;
            }

            $this->indicateProcess($table);

            $table_speed_prepare = microtime(true);

            /**
             * This will create a description of the table, now we want to generate queries:
             * @var TableDescription $TableDescription
             */
            $TableDescription = $QueryMakerClass::describeTable($this->pdo, $table);

            Measure::logTableSpeed(
                $table->getName(),
                Measure::TABLE_SPEED_DESCRIBE,
                microtime(true) - $table_speed_prepare
            );

            $table_speed_generate = microtime(true);

            /**
             * Create Queries:
             */
            foreach ($table->install($TableDescription) as $Command) {
                $QueryCommands[] = $Command;
            }

            Measure::logTableSpeed(
                $table->getName(),
                Measure::TABLE_SPEED_GENERATE,
                microtime(true) - $table_speed_generate
            );

        }

        //region Query[] Executing

        if (count($QueryCommands) > 0) {

            /**
             * @var Query $QueryCommand
             * @noinspection PhpRedundantVariableDocTypeInspection
             */
            foreach ($QueryCommands as $QueryCommand) {

                $queryExecuteSpeed = microtime(true);


                    $QueryCommand->executed = true;
                    try {
                        $QueryCommand->execute($this->pdo, $this);
                    } catch (PDOException $pdoEx) {

                        $QueryCommand->setError($pdoEx);
                        $this->lastError = $pdoEx;

                        throw new TableCreationFailedException(
                            $QueryCommand->getTable()->getName(),
                            $QueryCommand->getColumn()?->getName(),
                            $QueryCommand->getQuery(),
                            $pdoEx
                        );
                    }



                $queryExecuteSpeed =
                    microtime(true) - $queryExecuteSpeed;

                $QueryCommand->speed = $queryExecuteSpeed;

                Measure::logTableSpeed(
                    $QueryCommand->getTable()->getName(),
                    Measure::TABLE_SPEED_EXECUTE,
                    $queryExecuteSpeed
                );

                Measure::reportQueryModification($QueryCommand);
            }

        }

        Measure::reportTotalSpeed(microtime(true) - $timer_total);
    }

    public function clear(): void
    {
        $this->tables = [];
        $this->installable = null;
    }

    /**
     * @return int
     */
    public function getDriverType(): int
    {
        /** @var int $driver */
        $driver = DriverType::getValue(
            $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME),
            false
        );

        Measure::$Driver = $driver;

        return $driver;

    }

    /**
     * Finds all columns referencing FOREIGN KEY of
     * given table's old primary key and replace it
     * with new primary key.
     *
     * Used by Table::setPrimaryKeyName()
     *
     * @param Table $table
     * @param string $oldPrimaryKeyName
     * @throws InvalidArgumentException
     * @internal
     */
    public function updateForeignKeys(Table $table, string $oldPrimaryKeyName): void
    {
        $keyToRemove = $table->getName() . '.' . $oldPrimaryKeyName;
        $keyToAdd = $table->getName() . '.' . $table->getPrimaryKeyName();

        foreach ($this->tableList() as $Table) {
            foreach ($Table->columnList() as $Column) {
                $found = false;
                foreach ($Column->getForeignKeys() as $foreignKeyTarget) {
                    if ($foreignKeyTarget === $keyToRemove) {
                        $found = true;
                    }
                }

                if ($found) {
                    $Column->dropForeignKey($keyToRemove);
                    $Column->addForeignKey($keyToAdd);
                }
            }
        }
    }

    /**
     * @param class-string<BaseEntity> $baseEntityClassName
     */
    public function use(string $baseEntityClassName): void
    {
        /** @var BaseEntity $baseEntityClassNameObject */
        $baseEntityClassNameObject = $baseEntityClassName;
        $baseEntityClassNameObject::hydrateUpdater($this);
    }

    /**
     * @var array<string, string>
     */
    public static array $processedTableIndication = [];

    private function indicateProcess(Table $table): void
    {
        static::$processedTableIndication[$table->getName()] = $table->getHashKey();
    }

    private function isAlreadyProcessed(Table $table): bool
    {
        $tableName = $table->getName();

        if (
            !array_key_exists(
                $tableName,
                static::$processedTableIndication
            )
        ) {
            return false;
        }

        if (static::$processedTableIndication[$tableName] !== $table->getHashKey()) {
            return false;
        }

        return true;
    }
}

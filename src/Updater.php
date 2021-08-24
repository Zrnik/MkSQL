<?php declare(strict_types=1);

namespace Zrnik\MkSQL;

use PDO;
use PDOException;
use ReflectionException;
use Throwable;
use Zrnik\MkSQL\Enum\DriverType;
use Zrnik\MkSQL\Exceptions\InvalidArgumentException;
use Zrnik\MkSQL\Exceptions\InvalidDriverException;
use Zrnik\MkSQL\Exceptions\MkSQLException;
use Zrnik\MkSQL\Exceptions\TableDefinitionExists;
use Zrnik\MkSQL\Exceptions\UnexpectedCall;
use Zrnik\MkSQL\Queries\Makers\IQueryMaker;
use Zrnik\MkSQL\Queries\Query;
use Zrnik\MkSQL\Queries\Tables\TableDescription;
use Zrnik\MkSQL\Repository\BaseEntity;
use Zrnik\MkSQL\Tracy\Measure;
use Zrnik\MkSQL\Utilities\Installable;

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

    /**
     * @param string $tableName
     * @param bool $rewrite
     * @return Table
     * @throws TableDefinitionExists
     * @throws InvalidArgumentException
     */
    public function tableCreate(string $tableName, bool $rewrite = false): Table
    {
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
        if (!$rewrite && isset($this->tables[$table->getName()]))
            throw new TableDefinitionExists("Table '" . $table->getName() . "' already defined!");

        $this->tables[$table->getName()] = $table;
        $table->setParent($this);
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
    //endregion

    /**
     * @return bool
     * @throws InvalidArgumentException
     * @throws InvalidDriverException
     * @throws UnexpectedCall
     */
    public function install(): bool
    {
        if($this->installable !== null) {
            throw new UnexpectedCall(
                sprintf(
                    "Please, do not call '\$updater->install()' inside a '%s::install()' method, as its already handled by '%s'!",
                    $this->installable,
                    Installable::class
                )
            );
        }

        $Success = true;

        $timer_total = microtime(true);

        //region DriverCheck
        /**
         * We need a correct driver and
         * IQueryMaker class for it!
         */
        if ($this->getDriverType() === null) {
            throw new InvalidDriverException("Driver type is 'NULL'!");
        }

        $driverName = DriverType::getName($this->getDriverType());

        /** @var IQueryMaker $QueryMakerClass */
        $QueryMakerClass = "\\Zrnik\\MkSQL\\Queries\\Makers\\QueryMaker" . $driverName;

        /**
         * @var string $QueryMakerClassCheck
         */
        $QueryMakerClassCheck = $QueryMakerClass;

        if (!class_exists($QueryMakerClassCheck)) {
            throw new InvalidDriverException(
                "Invalid driver '" . $driverName . "' for package 'Zrnik\\MkSQL' class 'Updater'. 
                Allowed drivers: " . implode(", ", DriverType::getNames(false)));
        }

        //endregion

        /**
         * @var Query[] $QueryCommands
         */
        $QueryCommands = [];

        foreach ($this->tables as $table)
        {
            if($this->isAlreadyProcessed($table)) {
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
                $table->getName()??'unknown table',
                Measure::TABLE_SPEED_DESCRIBE ,
                microtime(true) - $table_speed_prepare
            );

            $table_speed_generate = microtime(true);

            /**
             * Create Queries:
             */
            foreach($table->install($TableDescription) as $Command) {
                $QueryCommands[] = $Command;
            }

            Measure::logTableSpeed(
                $table->getName()??'unknown table',
                Measure::TABLE_SPEED_GENERATE ,
                microtime(true) - $table_speed_generate
            );

        }

        //region Query[] Executing

        $stopped = false;
        if (count($QueryCommands) > 0)
        {

            /**
             * @var Query $QueryCommand
             */
            foreach ($QueryCommands as $QueryCommand) {

                $queryExecuteSpeed = microtime(true);

                if($stopped)
                {
                    $QueryCommand->executed = false;
                }
                else
                {
                    $QueryCommand->executed = true;
                    try {
                        $QueryCommand->execute($this->pdo);
                    } catch (PDOException $pdoEx) {

                        $stopped = true;
                        $Success = false;
                        $QueryCommand->setError($pdoEx);
                        $this->lastError = $pdoEx;
                    }
                }


                $queryExecuteSpeed =
                    microtime(true) - $queryExecuteSpeed;

                $QueryCommand->speed = $queryExecuteSpeed;

                Measure::logTableSpeed(
                    $QueryCommand->getTable()->getName()??'unknown table',
                    Measure::TABLE_SPEED_EXECUTE,
                    $queryExecuteSpeed
                );

                Measure::reportQueryModification($QueryCommand);
            }

        }

        Measure::reportTotalSpeed(microtime(true) - $timer_total);

        return $Success;
    }

    public function clear(): void
    {
        $this->tables = [];
        $this->installable = null;
    }

    /**
     * @return mixed
     */
    public function getDriverType(): mixed
    {
        $driver =  DriverType::getValue(
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
        $keyToRemove = $table->getName() . "." . $oldPrimaryKeyName;
        $keyToAdd = $table->getName() . "." . $table->getPrimaryKeyName();

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
     * @param class-string<BaseEntity> ...$baseEntities
     * @throws MkSQLException
     * @throws ReflectionException
     */
    public function use(...$baseEntities): void
    {
        /** @var BaseEntity $baseEntityClassName */
        foreach ($baseEntities as $baseEntityClassName) {
            $baseEntityClassName::hydrateUpdater($this);
        }
    }


    private static array $processedTableIndication = [];

    private function indicateProcess(Table $table): void
    {
        static::$processedTableIndication[$table->getName()] = $table->getHashKey();
    }

    private function isAlreadyProcessed(Table $table): bool
    {
        if(!array_key_exists($table->getName(), static::$processedTableIndication)) {
            return false;
        }

        if(static::$processedTableIndication[$table->getName()] !== $table->getHashKey()) {
            return false;
        }

        return true;
    }


}

<?php declare(strict_types=1);
/*
 * Zrník.eu | MkSQL
 * User: Programátor
 * Date: 31.07.2020 7:54
 */

namespace Zrnik\MkSQL;

use InvalidArgumentException;
use PDO;
use PDOException;
use Zrnik\MkSQL\Enum\DriverType;
use Zrnik\MkSQL\Exceptions\InvalidDriverException;
use Zrnik\MkSQL\Exceptions\TableDefinitionExists;
use Zrnik\MkSQL\Queries\Makers\IQueryMaker;
use Zrnik\MkSQL\Queries\Query;
use Zrnik\MkSQL\Queries\Tables\TableDescription;
use Zrnik\MkSQL\Tracy\Metrics;

/**
 * @package Zrnik\MkSQL
 */
class Updater
{
    private PDO $pdo;

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

        foreach ($options as $key => $value)
            $this->pdo->setAttribute($key, $value);
    }

    //region Tables

    /** @var Table[] */
    private array $tables = [];

    /**
     * @param string $tableName
     * @param bool $rewrite
     * @return Table
     * @throws TableDefinitionExists
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
     * @return Table[]
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
        if (isset($this->tables[$tableName]))
            return $this->tables[$tableName];
        return null;
    }
    //endregion

    /**
     * @return bool
     * @throws InvalidDriverException
     */
    public function install(): bool
    {
        $Success = true;

        Metrics::measureTotal();

        //region Query[] Preparing
        Metrics::measureQueryPreparing();

        //region Query Array
        /**
         * @var Query[] $QueryCommands
         */
        $QueryCommands = [];
        //endregion

        //region Driver Type & Query Maker Class

        if ($this->getDriverType() === null)
            throw new InvalidDriverException("Driver type is 'NULL'!");

        $driverName = DriverType::getName($this->getDriverType());

        /** @var IQueryMaker $QueryMakerClass */
        $QueryMakerClass = "\\Zrnik\\MkSQL\\Queries\\Makers\\QueryMaker" . $driverName;

        if (!class_exists($QueryMakerClass))
            throw new InvalidDriverException(
                "Invalid driver '" . $driverName . "' for package 'Zrnik\\MkSQL' class 'Updater'. 
                Allowed drivers: " . implode(", ", DriverType::getNames(false)));

        //endregion

        foreach ($this->tables as $table) {

            //region Describe Table
            Metrics::measureTable($table->getName(), "describing");
            Metrics::logTableInstallCalls($table->getName());

            /**
             * @var TableDescription $TableDescription
             */
            $TableDescription = $QueryMakerClass::describeTable($this->pdo, $table);

            Metrics::measureTable($table->getName(), "describing", true);
            //endregion

            //region Query Generating
            Metrics::measureTable($table->getName(), "sql-generating");

            $QueryCommands = array_merge($QueryCommands, $table->install($TableDescription));

            Metrics::measureTable($table->getName(), "sql-generating", true);
            //endregion

        }

        Metrics::measureQueryPreparing(true);
        //endregion

        //region Query[] Executing
        Metrics::measureQueryExecuting();

        if (count($QueryCommands) > 0) {
            Metrics::logQueries($QueryCommands);

            foreach ($QueryCommands as $QueryCommand) {
                try {
                    $QueryCommand->execute($this->pdo);
                } catch (PDOException $pdoEx) {
                    $Success = false;
                    $QueryCommand->setError($pdoEx);
                    break;
                }
            }
        }

        Metrics::measureQueryExecuting(true);
        //endregion

        Metrics::measureTotal(true);

        return $Success;
    }

    /**
     * @return mixed|null
     */
    public function getDriverType()
    {
        try {
            return DriverType::getValue($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME), false);
        } catch (InvalidArgumentException $ex) {
            return null;
        }
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
}

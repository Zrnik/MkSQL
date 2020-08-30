<?php
/*
 * Zrník.eu | MkSQL
 * User: Programátor
 * Date: 31.07.2020 7:54
 */

namespace Zrny\MkSQL;

use InvalidArgumentException;
use PDO;
use Zrny\MkSQL\Enum\DriverType;
use Zrny\MkSQL\Exceptions\InvalidDriverException;
use Zrny\MkSQL\Nette\Metrics;
use Zrny\MkSQL\Queries\Makers\IQueryMaker;
use Zrny\MkSQL\Queries\Query;
use Zrny\MkSQL\Queries\Tables\TableDescription;

/**
 * @package Zrny\MkSQL
 */
class Updater
{
    //region Properties

    /**
     * @var Table[]
     */
    private array $tables = [];

    /**
     * @var PDO
     */
    private PDO $pdo;

    //endregion

    /**
     * Updater constructor.
     * @param PDO $pdo
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @param string $tableName
     * @return Table
     */
    public function createTable(string $tableName) : Table
    {
        $newTable = new Table($tableName);
        return $this->addTable($newTable);
    }

    /**
     * @param Table $table
     * @return Table
     */
    public function addTable(Table $table) : Table
    {
        $this->tables[$table->getName()] = $table;
        $table->setParent($this);
        return $table;
    }

    /**
     * @return string|null
     */
    private function getDriverType() : ?string
    {
        try
        {
            return DriverType::getValue($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME), false);
        }
        catch (InvalidArgumentException $ex)
        {
            return null;
        }
    }


    public function install()
    {
        Metrics::measureTotal();

        //region QueryPrepare
        Metrics::measureQueryPreparing();


        //region Query Array
        /**
         * @var Query[] $QueryCommands
         */
        $QueryCommands = [];
        //endregion

        //region Driver Type & Query Maker Class

        if($this->getDriverType())
            throw new InvalidDriverException("Driver type is 'NULL'!");

        $driverName = DriverType::getName($this->getDriverType());

        /** @var IQueryMaker $QueryMakerClass */
        $QueryMakerClass = "\\Zrny\\MkSQL\\Queries\\Makers\\QueryMaker".$driverName;

        if(!class_exists($QueryMakerClass))
            throw new InvalidDriverException(
                "Invalid driver '".$driverName."' for package 'Zrny\\MkSQL' class 'Updater'. 
                Allowed drivers: ".implode(", ",DriverType::getNames(false)));

        //endregion

        foreach($this->tables as $table)
        {
            Metrics::measureTable($table->getName(),"describing");

            Metrics::logTableInstallCalls($table->getName());

            /**
             * @var TableDescription $TableDescription
             */
            $TableDescription = $QueryMakerClass::describeTable($this->pdo, $table);

            Metrics::measureTable($table->getName(),"describing", true);

            Metrics::measureTable($table->getName(),"sql-generating");
            $Commands = $table->install($TableDescription);

            Metrics::measureTable($table->getName(),"sql-generating", true);
            $QueryCommands = array_merge($QueryCommands, $Commands);
        }

        Metrics::measureQueryPreparing(true);
        //endregion


        //region Query Executing
        Metrics::measureQueryExecuting();
        //$ErrorQuery = null;
        if(count($QueryCommands) > 0)
        {
            Metrics::logQueries($QueryCommands);

            //New Version

            $Success = true;


            $this->getConnection()->beginTransaction();
            foreach($QueryCommands as $QueryCommand)
            {
                try
                {
                    $this->getConnection()->query($QueryCommand->sql);
                    $QueryCommand->setExecuted(true);
                }
                catch(DriverException $ex)
                {
                    $QueryCommand->setExecuted(false);
                    $QueryCommand->errorText = $ex->getMessage();
                    $Success = false;
                    break;
                }
            }

            if($Success)
                $this->getConnection()->commit();
        }


        Metrics::measureQueryExecuting(true);

        //endregion

        Metrics::measureTotal(true);

    }
}

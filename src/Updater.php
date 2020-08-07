<?php
/*
 * Zrník.eu | MkSQL  
 * User: Programátor
 * Date: 31.07.2020 7:54
 */


namespace Zrny\MkSQL;

use InvalidArgumentException;
use Nette\Database\Connection;
use Nette\Database\DriverException;
use PDO;
use Zrny\MkSQL\Enum\DriverType;
use Zrny\MkSQL\Nette\Metrics;
use Zrny\MkSQL\Queries\Makers\IQueryMaker;
use Zrny\MkSQL\Queries\Query;
use Zrny\MkSQL\Queries\Tables\TableDescription;

/**
 * @package Zrny\MkSQL
 */
class Updater
{
    /**
     * @var Table[]
     */
    private $tables;

    /**
     * @var Connection
     */
    private $database;

    /**
     * @var array
     */
    private $credentials;

    /**
     * Updater constructor.
     * @param string|null $dsn
     * @param string|null $user
     * @param string|null $password
     * @param array|null $options
     * @throws DriverException
     */
    public function __construct(?string $dsn = null, ?string $user = null, ?string $password = null, ?array $options = null)
    {
        $this->credentials = [$dsn,$user,$password, $options];
    }

    private function getConnection()
    {
        if($this->database === null)
            $this->database = new Connection(
                $this->credentials[0],
                $this->credentials[1],
                $this->credentials[2],
                $this->credentials[3]
            );
        return $this->database;
    }

    public function setConnection(Connection $db)
    {
        $this->database = $db;
    }

    /**
     * @param string $tableName
     * @return Table
     */
    public function table(string $tableName) : Table
    {
        $tableName = Utils::confirmName($tableName);
        if(!isset($this->tables[$tableName]))
            $this->tables[$tableName] = new Table($tableName,$this);
        return $this->tables[$tableName];
    }

    private function getDriverType()
    {
        try
        {
            return DriverType::getValue($this->getConnection()->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME), false);
        }
        catch (InvalidArgumentException $ex)
        {
            return null;
        }
    }

    public function install()
    {
        Metrics::measureTotal();
        Metrics::measureQueryPreparing();

        /**
         * @var Query[] $QueryCommands
         */
        $QueryCommands = [];

        foreach($this->tables as $table)
        {

            Metrics::measureTable($table->getName(),"describing");

            Metrics::logTableInstallCalls($table->getName());

            /** @var IQueryMaker $QueryMakerClass */
            $QueryMakerClass = "\\Zrny\\MkSQL\\Queries\\Makers\\QueryMaker".DriverType::getName($this->getDriverType());

            if(!class_exists($QueryMakerClass) || $this->getDriverType() === null)
                throw new DriverException("Invalid driver '".$this->getConnection()->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME)."'
                for package 'Zrny\\MkSQL' class 'Updater'. Allowed drivers: ".implode(", ",DriverType::getNames(false)));

            /**
             * @var TableDescription $TableDescription
             */
            $TableDescription = $QueryMakerClass::describeTable($this->getConnection(), $table);

            Metrics::measureTable($table->getName(),"describing", true);

            Metrics::measureTable($table->getName(),"sql-generating");
            $Commands = $table->install($TableDescription);

            Metrics::measureTable($table->getName(),"sql-generating", true);
            $QueryCommands = array_merge($QueryCommands, $Commands);
        }

        Metrics::measureQueryPreparing(true);

        Metrics::measureQueryExecuting();
        if(count($QueryCommands) > 0)
        {
            try
            {
                $this->getConnection()->beginTransaction();
                foreach($QueryCommands as $QueryCommand){
                    $this->getConnection()->query($QueryCommand->sql);
                    $QueryCommand->setExecuted(true);
                }
                $this->getConnection()->commit();
            }
            catch(DriverException $ex)
            {
                $this->getConnection()->rollBack();
                foreach($QueryCommands as $QueryCommand)
                    $QueryCommand->setRolledBack(true);
                throw DriverException::from($ex);
            }
            Metrics::logQueries($QueryCommands);
        }


        Metrics::measureQueryExecuting(true);

        Metrics::measureTotal(true);

    }





}
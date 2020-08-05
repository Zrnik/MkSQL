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
use PDOException;
use Zrny\MkSQL\Enum\DriverType;

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

    public function install()
    {
        $installStartTime = microtime(true);
        $db = $this->getConnection();

        //Speed Up info_schema fetch
        //$db->query("set global innodb_stats_on_metadata=0");

        if($this->getDriverType() === null)
            throw new DriverException("Invalid driver '".$db->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME)."'
                for package 'Zrny\\MkSQL' class 'Updater'. Allowed drivers: ".implode(", ",DriverType::getNames(false)));

        static::logStructure($this->tables);


        $Commands = [];
        foreach($this->tables as $table)
        {
            $tableSpeedStart = microtime(true);
            if(!isset(static::$_InstallSpeed[$table->getName()]))
                static::$_InstallSpeed[$table->getName()] = 0;

            $Commands = array_merge($Commands, $table->install($db, $this->getDriverType()));
            $speed = microtime(true) - $tableSpeedStart;
            static::$_InstallSpeed[$table->getName()] += $speed;
        }


        if(count($Commands) > 0) {
            try
            {
                $commandsExecutingQueriesTime = microtime(true);
                $db->beginTransaction();
                foreach($Commands as $Command){
                    $db->query($Command["sql"]);
                }
                $db->commit();
                static::$_SecondsSpentExecutingQueries += microtime(true) - $commandsExecutingQueriesTime;

                // It works! Now save structure to cache to possibly display it in TracyBar
            }
            catch(\Exception $ex)
            {
                $db->rollBack();
                throw new DriverException($ex->getMessage());
            }
        }
        static::$_SecondsSpentInstalling = static::$_SecondsSpentInstalling + microtime(true) - $installStartTime;
        bdump(microtime(true) - $installStartTime);
        bdump(static::$_SecondsSpentInstalling * 1000);

    }

    private function getDriverType()
    {
        try
        {
            return DriverType::getValue($this->getConnection()->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME), false);
        }
        catch (InvalidArgumentException $ex)
        {
            return null;
        }
    }

    //region TracyLogs

    public static $_InstallCall = [];
    public static $_InstallSpeed = [];
    public static $_SecondsSpentInstalling = 0;
    public static $_SecondsSpentDescribingTableData = [
        "table" => 0,
        "indexes" => 0,
        "keys" => 0
    ];
    public static $_SecondsSpentGeneratingCommands = 0;




    public static $_SecondsSpentExecutingQueries = 0;

    public static $_StructureLog = [];
    private static function logStructure(array $tables)
    {
        foreach($tables as $table)
        {
            if(!isset(static::$_StructureLog[$table->getName()]))
                static::$_StructureLog[$table->getName()] = [];

            foreach($table->getColumns() as $column)
                static::$_StructureLog[$table->getName()][$column->getName()] = $column;


        }
    }


    public static $_UpdateLog = [];
    public static function logUpdate($table,$column,$command)
    {
        if(!isset(static::$_UpdateLog[$table]))
            static::$_UpdateLog[$table] = [];

        if(!isset(static::$_UpdateLog[$table][$column]))
            static::$_UpdateLog[$table][$column] = [];

        static::$_UpdateLog[$table][$column][] = [
            "reason" => $command["reason"],
            "sql" => $command["sql"]
        ];
    }

    //endregion

}
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
        $db = $this->getConnection();

        if($this->getDriverType() === null)
            throw new DriverException("Invalid driver '".$db->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME)."'
                for package 'Zrny\\MkSQL' class 'Updater'. Allowed drivers: ".implode(", ",DriverType::getNames(false)));

        $Commands = [];
        foreach($this->tables as $table)
        {
            $Commands = array_merge($Commands, $table->install($db, $this->getDriverType()));
        }

        if(count($Commands) > 0) {
            try
            {
                $db->beginTransaction();
                foreach($Commands as $Command){
                    //TODO: Log this somewhere?
                    $db->query($Command["sql"]);
                }
                $db->commit();
            }
            catch(\Exception $ex)
            {
                $db->rollBack();
                throw new DriverException($ex->getMessage());
            }
        }
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


}
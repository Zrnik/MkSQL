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
     * @var int
     */
    private $driverType;

    /**
     * @var Connection
     */
    private $db;

    /**
     * Updater constructor.
     * @param string $dsn
     * @param string|null $user
     * @param string|null $password
     * @param array|null $option
     * @throws DriverException
     */
    public function __construct(string $dsn, ?string $user = null, ?string $password = null, ?array $option = null)
    {
        $driverString = explode(":", $dsn)[0];
        try {
            $this->driverType = DriverType::getValue($driverString, false);
        } catch (InvalidArgumentException $ex) {
            throw new DriverException("Unsupported driver '".$driverString."' passed to 'Zrny\\MkSQL\\Updater' class! Supported drivers: ".implode(",",DriverType::getNames(false)));
        }

        $this->db = new Connection($dsn, $user, $password, $option);
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
        $Commands = [];
        foreach($this->tables as $table)
        {
            $Commands = array_merge($Commands, $table->install($this->db, $this->driverType));
        }

        if(count($Commands) > 0) {
            try
            {
                $this->db->beginTransaction();
                foreach($Commands as $Command){
                    //echo $Command["sql"].PHP_EOL;
                    //echo $Command["reason"].PHP_EOL;
                    //TODO: Log this somewhere?
                    $this->db->query($Command["sql"]);
                }
                $this->db->commit();
            }catch(PDOException $ex)
            {
                $this->db->rollBack();
                throw new $ex;

                //echo '------------------------------------'.PHP_EOL;
                //echo '-- ROLLBACK'.PHP_EOL;
                //echo '------------------------------------'.PHP_EOL;
                //echo '-- '.$ex->getMessage().PHP_EOL;
                //echo '------------------------------------'.PHP_EOL;
            }
        }

    }


}
<?php
/*
 * Zrník.eu | MkSQL  
 * User: Programátor
 * Date: 31.07.2020 9:37
 */


namespace Zrny\MkSQL;


use Composer\Cache;
use LogicException;
use Nette\Database\Connection;
use PDOException;
use React\Promise\Util;
use Tracy\Debugger;
use Zrny\MkSQL\Enum\DriverType;

class Table
{
    /**
     * @var Updater
     */
    private $parent;

    /**
     * @var string
     */
    private $tableName;

    /**
     * @var Column[]
     */
    private $columns = [];

    /**
     * Table constructor.
     * @param string $tableName
     * @param Updater $parent
     */
    public function __construct(string $tableName, Updater $parent)
    {
        $tableName = Utils::confirmName($tableName);
        $this->tableName = $tableName;
        $this->parent = $parent;
    }

    /**
     * Returns name of the table.
     * @return string|null
     */
    public function getName(): string
    {
        return $this->tableName;
    }

    /**
     * @return Column[]
     */
    public function getColumns(): array
    {
        return $this->columns;
    }


    /**
     * Creates a table column
     * @param string $colName
     * @param string|null $colType
     * @return Column
     */
    public function column(string $colName, ?string $colType = "int"): Column
    {
        $colName = Utils::confirmName($colName);
        $colType = Utils::confirmName($colType, ["(", ")"]);

        if ($colName === "id")
            throw new \InvalidArgumentException("You cannot redeclare primary column 'id' in table '" . $this->tableName . "'.");

        if (!isset($this->columns[$colName]))
            $this->columns[$colName] = new Column($colName, $this, $colType);

        if ($this->columns[$colName]->getType() !== $colType)
            throw new LogicException("Cannot redeclare column '" . $colName . "' with type '" . $this->columns[$colName]->getType() . "' as '" . $colType . "'!");


        return $this->columns[$colName];
    }

    /**
     * Ends defining of table.
     * @return Updater
     */
    public function endTable(): Updater
    {
        return $this->parent;
    }

    /**
     * @param Connection $db
     * @param int $driverType
     * @return array
     */
    public function install(Connection $db, int $driverType): array
    {
        if (!isset(Updater::$_InstallCall[$this->getName()]))
            Updater::$_InstallCall[$this->getName()] = 0;
        Updater::$_InstallCall[$this->getName()]++;

        $Commands = [];


        //$tableDescribingSpeedStart = microtime(true);


        $tableDescribing1SpeedStart = microtime(true);
        $description = $this->describeOrCreateTable($db, $driverType);
        Updater::$_SecondsSpentDescribingTableData["table"] += microtime(true) - $tableDescribing1SpeedStart;


        $tableDescribing2SpeedStart = microtime(true);
        $indexes = $this->describeIndexes($db, $driverType);
        Updater::$_SecondsSpentDescribingTableData["indexes"] += microtime(true) - $tableDescribing2SpeedStart;

        $tableDescribing3SpeedStart = microtime(true);
        $keys = $this->describeKeys($db, $driverType);
        Updater::$_SecondsSpentDescribingTableData["keys"] += microtime(true) - $tableDescribing3SpeedStart;

        //$tableDescribingSpeed = microtime(true) - $tableDescribingSpeedStart;
        //Updater::$_SecondsSpentDescribingTables += $tableDescribingSpeed;

        $tableSqlGeneratingStart = microtime(true);
        foreach ($this->columns as $column) {
            $Commands = array_merge($Commands, $column->install($db, $driverType, $description, $indexes, $keys));
        }

        $tableSqlGenerating = microtime(true) - $tableSqlGeneratingStart;
        Updater::$_SecondsSpentGeneratingCommands += $tableSqlGenerating;
        return $Commands;
    }


    private function describeOrCreateTable(Connection $db, int $driverType): array
    {
        $Result = [];
        try {
            if ($driverType === DriverType::MySQL) {
                $Result = $db->fetchAll("SHOW FULL COLUMNS FROM " . $this->tableName . ";");
            }
        } catch (PDOException $ex) {
            if ($driverType === DriverType::MySQL) {
                $db->query("CREATE TABLE " . $this->tableName . " (id int(11)) COMMENT 'Handled by MkSQL';");
                $db->query("CREATE UNIQUE INDEX " . $this->tableName . "_uindex_id on " . $this->tableName . " (id) COMMENT 'AutoPrimary by MkSQL';");
                $db->query("ALTER TABLE " . $this->tableName . " ADD CONSTRAINT " . $this->tableName . "_pkey_id PRIMARY KEY (id);");
                $Result = $db->fetchAll("SHOW FULL COLUMNS FROM " . $this->tableName . ";");
            }
        }
        return $Result;
    }

    private function describeIndexes(Connection $db, int $driverType): array
    {
        $Result = [];
        if ($driverType === DriverType::MySQL) {
            $Result = $db->fetchAll("SHOW INDEXES FROM " . $this->tableName . ";");

        }
        return $Result;
    }

    private function describeKeys(Connection $db, int $driverType)
    {
        $Result = [];
        if ($driverType === DriverType::MySQL)
        {
            //I only need to get Foreign Keys.
            foreach(explode("\n",$db->fetch("SHOW CREATE TABLE ".$this->getName().";")["Create Table"]) as $Line)
            {
                if(strpos(trim($Line), "CONSTRAINT") === 0) //Starts with 'CONSTRAINT'
                {
                    $Data = explode("`",$Line);
                    /*bdump($Data);*/

                    $Result[] = [
                        "TABLE_NAME" => $this->getName(),
                        "COLUMN_NAME" => $Data[3],
                        "REFERENCED_TABLE_NAME" => $Data[5],
                        "REFERENCED_COLUMN_NAME" => $Data[7],
                        "CONSTRAINT_NAME" =>  $Data[1]
                    ];
                }
            }

            //CONSTRAINT

          /*  $required = ["TABLE_NAME","COLUMN_NAME","REFERENCED_TABLE_NAME","REFERENCED_COLUMN_NAME","CONSTRAINT_NAME"];

            $Result = $db->fetchAll("SELECT ".implode(",",$required)." FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_NAME = ?;", $this->getName());
*/
           /*bdump($Result);*/
        }
        return $Result;
    }


}
<?php
/*
 * Zrník.eu | untitled  
 * User: Programátor
 * Date: 20.07.2020 7:34
 */

namespace Zrnik\MkSQL;

use Exception;
use Nette\Database\Connection;
use Nette\Database\DriverException;

class SqlUpdater
{

    /**
     * @var string
     */
    private $tblName = null;

    /**
     * @var Connection
     */
    private $conn = null;

    /**
     * SqlUpdater constructor.
     * @param string $TableName
     * @param Connection $NetteDatabaseConnection
     * @throws Exception
     */
    public function __construct(string $TableName, Connection $NetteDatabaseConnection)
    {
        $this->tblName = self::allowed_table_name($TableName);
        $this->conn = $NetteDatabaseConnection;
    }


    private $cols = [];

    public function addColumn(Column $col)
    {
        $this->cols[] = $col;
        return $col;
    }

    /**
     * @throws Exception
     */
    public function install()
    {
        // Start Transaction
        $this->conn->beginTransaction();

        try {
            $DescribeResults = $this->conn->fetchAll("DESCRIBE " . self::allowed_table_name($this->tblName) . ";");
            $IndexesResults = $this->conn->fetchAll("SHOW INDEXES FROM  " . self::allowed_table_name($this->tblName) . ";");

            //var_dump($IndexesResults);
            //var_dump($DescribeResults);
            /**
             * @var $col Column
             */
            foreach ($this->cols as $col) {
                //Existuje Definovany col v Databazi?
                $ColDBRow = null;
                foreach ($DescribeResults as $describeResult)
                    if ($describeResult["Field"] === $col->ColName)
                        $ColDBRow = $describeResult;

                $ColDBIndexes = [];
                foreach ($IndexesResults as $indexesResult)
                    if ($indexesResult["Column_name"] === $col->ColName)
                        $ColDBIndexes[] = $indexesResult;


                if ($ColDBRow === null) {
                    //není v databázi, vytvoříme

                    $sql = 'ALTER TABLE ' . self::allowed_table_name($this->tblName);
                    $sql .= ' ADD ' . self::allowed_table_name($col->ColName) . ' ' . $col->getTypeString() . " " . ($col->CanBeNull ? "NULL" : "NOT NULL");
                    try {
                        $this->conn->query($sql);
                    } catch (Exception $ex3) {
                        return;
                    }
                }


                //var_dump($ColDBRow);
                //Ma spravny typ? (pokud jsme prave vytvorili tak nejspis jo :) taky se tu da nastavit null/not null tak to k tomu přifaříme...
                $RequireTypeChange = (($ColDBRow !== null && $ColDBRow["Type"] !== $col->getTypeString()) || (($ColDBRow["Null"] === "YES") && !$col->CanBeNull) || (($ColDBRow["Null"] !== "YES") && $col->CanBeNull));
                if ($RequireTypeChange) {
                    try {
                        $this->conn->query("ALTER TABLE " . self::allowed_table_name($this->tblName) . " MODIFY " . self::allowed_table_name($col->ColName) . " " . $col->getTypeString() . " " . ($col->CanBeNull ? "NULL" : "NOT NULL") . ";");
                    } catch (Exception $TypeExc) {
                        $this->conn->rollBack();
                        return;
                    }
                }

                //Ma spravny default?
                $RequireDefaultChange = $col->getDefault() !== $ColDBRow["Default"];
                if ($RequireDefaultChange) {
                    if ($col->getDefault() === null) {
                        try {
                            $this->conn->query("ALTER TABLE " . self::allowed_table_name($this->tblName) .
                                " ALTER COLUMN " . self::allowed_table_name($col->ColName) . " DROP DEFAULT;");
                        } catch (Exception $defEx1) {
                            $this->conn->rollBack();
                            return;
                        }
                    } else {
                        try {
                            $this->conn->query("ALTER TABLE " . self::allowed_table_name($this->tblName) .
                                " ALTER COLUMN " . self::allowed_table_name($col->ColName) . " SET DEFAULT ?;", $col->getDefault());
                        } catch (Exception $defEx2) {
                            $this->conn->rollBack();
                            return;
                        }
                    }
                }

                //Unique?
                $UniqueIndexKey = "mksql_unique_idx_" . self::allowed_table_name($this->tblName) . "_" . self::allowed_table_name($col->ColName);

                $FoundUniqueKey = false;
                foreach ($ColDBIndexes as $index)
                    if ($index["Key_name"] === $UniqueIndexKey)
                        $FoundUniqueKey = true;

                $ChangeUnique = $col->getUnique() != $FoundUniqueKey;

                if ($ChangeUnique) {
                    if ($FoundUniqueKey) {
                        try {
                            $this->conn->query("DROP INDEX " . $UniqueIndexKey . " ON " . self::allowed_table_name($this->tblName));
                        } catch (Exception $uniqueEx1) {
                            $this->conn->rollBack();
                            return;
                        }
                    } else {
                        try {
                            $this->conn->query("CREATE UNIQUE INDEX " . $UniqueIndexKey . " ON " . self::allowed_table_name($this->tblName) . " (" . self::allowed_table_name($col->ColName) . ")");
                        } catch (Exception $uniqueEx2) {
                            $this->conn->rollBack();
                            return;
                        }
                    }
                }
            }
        } catch (DriverException $ex) {
            try {
                $this->conn->query("CREATE TABLE " . self::allowed_table_name($this->tblName) . " (id int NOT NULL AUTO_INCREMENT, PRIMARY KEY (id));");
                $this->conn->commit();
                $this->install();
            } catch (DriverException $ex2) {
                $this->conn->rollBack();
            }
            return;
        }

        $this->conn->commit();
        return;
    }


    public function drop(){
        //tohle bylo jen pro testovaci ucely, ale tak necham to tu :D
        try {
            $this->conn->query("DROP TABLE " . self::allowed_table_name($this->tblName));
        } catch (DriverException $ex) {
        }
    }


    private static $allowed_characters = [
        "0","1","2","3","4","5","6","7","8","9" ,
        "q","w","e","r","t","z","u","i","o","p","a","s","d","f",
        "g","h","j","k","l","y","x","c","v","b","n","m","Q","W",
        "E","R","T","Z","U","I","O","P","A","S","D","F","G","H",
        "J","K","L","Y","X","C","V","B","N","M","_"
    ];

    /**
     * @param $tblname string
     * @return mixed
     * @throws Exception
     */
    public static function allowed_table_name($tblname)
    {
        if(trim(str_replace(self::$allowed_characters,"",$tblname)) !== "")
            throw new Exception("invalid characters in table name");

        return $tblname;
    }

}

<?php
/*
 * Zrník.eu | untitled  
 * User: Programátor
 * Date: 20.07.2020 7:34
 */

namespace Zrnik\MkSQL;

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
     * @throws \Exception
     */
    public function __construct(string $TableName, Connection $NetteDatabaseConnection)
    {
        $this->tblName = self::allowed_table_name($TableName);
        $this->conn = $NetteDatabaseConnection;
    }


    private $cols = [];

    private static function debug(string $string)
    {
        fwrite(STDOUT,"[SqlUpdater:".date("d.m.Y H:i:s")."] ".$string."\n");
    }

    public function addColumn(Column $col)
    {
        $this->cols[] = $col;
        return $col;
    }

    /**
     * @throws \Exception
     */
    public function install()
    {
        //neexistuje li tabulka, vytvoříme :)
        self::debug("Transaction Started!");
        $this->conn->beginTransaction();


        try
        {
            $DescribeResults = $this->conn->fetchAll("DESCRIBE ".self::allowed_table_name($this->tblName).";");
            $IndexesResults = $this->conn->fetchAll("SHOW INDEXES FROM  ".self::allowed_table_name($this->tblName).";");

            //var_dump($IndexesResults);
            //var_dump($DescribeResults);
            /**
             * @var $col Column
             */
            foreach($this->cols as $col)
            {
                //Existuje Definovany col v Databazi?
                $ColDBRow = null;
                foreach($DescribeResults as $describeResult)
                    if($describeResult["Field"] === $col->ColName)
                        $ColDBRow = $describeResult;

                $ColDBIndexes = [];
                foreach($IndexesResults as $indexesResult)
                    if($indexesResult["Column_name"] === $col->ColName)
                        $ColDBIndexes[] = $indexesResult;


                if($ColDBRow === null)
                {
                    //není v databázi, vytvoříme

                    $sql = 'ALTER TABLE '.self::allowed_table_name($this->tblName);
                    $sql .= ' ADD '.self::allowed_table_name($col->ColName).' '.$col->getTypeString()." ".($col->CanBeNull?"NULL":"NOT NULL");
                    try{
                        $this->conn->query($sql);
                        self::debug("[COL:".$col->ColName."] Created!");
                    }catch(\Exception $ex3)
                    {

                        self::debug("[COL:".$col->ColName."] Failed to create column! ");
                        self::debug("Roll Back Sent!");
                        return;
                    }
                }


                //var_dump($ColDBRow);
                //Ma spravny typ? (pokud jsme prave vytvorili tak nejspis jo :) taky se tu da nastavit null/not null tak to k tomu přifaříme...
                $RequireTypeChange = (($ColDBRow !== null && $ColDBRow["Type"] !== $col->getTypeString()) || (($ColDBRow["Null"] === "YES") && !$col->CanBeNull) || (($ColDBRow["Null"] !== "YES") && $col->CanBeNull)  );
                self::debug("[COL:".$col->ColName."][PROP:Type] ".($RequireTypeChange?"CHANGE":"OK")." => ".$col->getTypeString());
                if($RequireTypeChange)
                {
                    try
                    {
                        $this->conn->query("ALTER TABLE ".self::allowed_table_name($this->tblName)." MODIFY ".self::allowed_table_name($col->ColName)." ".$col->getTypeString()." ".($col->CanBeNull?"NULL":"NOT NULL").";");
                    }
                    catch(\Exception $TypeExc)
                    {
                        self::debug("[ERR] ".$TypeExc->getMessage());
                        self::debug("Roll Back Sent!");
                        $this->conn->rollBack();
                        return;
                    }
                }

                //Ma spravny default?
                $RequireDefaultChange = $col->DefaultValue !== $ColDBRow["Default"];
                self::debug("[COL:".$col->ColName."][PROP:Default] ".($RequireDefaultChange?"CHANGE":"OK")." => ".$col->DefaultValue);
                if($RequireDefaultChange)
                {
                    if($col->DefaultValue === null)
                    {
                        try{
                            $this->conn->query("ALTER TABLE ".self::allowed_table_name($this->tblName).
                                " ALTER COLUMN ".self::allowed_table_name($col->ColName)." DROP DEFAULT;");
                        }catch(\Exception $defEx1)
                        {
                            self::debug("[ERR] ".$defEx1->getMessage());
                            self::debug("Roll Back Sent!");
                            $this->conn->rollBack();
                            return;
                        }
                    }
                    else
                    {
                        try{
                        $this->conn->query("ALTER TABLE ".self::allowed_table_name($this->tblName).
                            " ALTER COLUMN ".self::allowed_table_name($col->ColName)." SET DEFAULT ?;", $col->DefaultValue);
                        }catch(\Exception $defEx2)
                        {

                            self::debug("[ERR] ".$defEx2->getMessage());
                            self::debug("Roll Back Sent!");
                            $this->conn->rollBack();
                            return;
                        }
                    }
                }




                //Unique?
                $UniqueIndexKey = "mksql_unique_idx_".self::allowed_table_name($this->tblName)."_".self::allowed_table_name($col->ColName);

                $FoundUniqueKey = false;
                foreach($ColDBIndexes as $index)
                    if($index["Key_name"] === $UniqueIndexKey)
                        $FoundUniqueKey = true;

                $ChangeUnique = $col->RequireUnique != $FoundUniqueKey;

                if($ChangeUnique)
                {

                    if($FoundUniqueKey)
                    {
                        //remove unique key
                        try{
                            $this->conn->query("DROP INDEX ".$UniqueIndexKey." ON ".self::allowed_table_name($this->tblName));
                        }catch(\Exception $uniqueEx1)
                        {

                            self::debug("[ERR] ".$uniqueEx1->getMessage());
                            self::debug("Roll Back Sent!");
                            $this->conn->rollBack();
                            return;
                        }
                    }
                    else
                    {
                        //add unique key!
                        try{
                            $this->conn->query("CREATE UNIQUE INDEX ".$UniqueIndexKey." ON ".self::allowed_table_name($this->tblName)." (".self::allowed_table_name($col->ColName).")");
                        }catch(\Exception $uniqueEx2)
                        {
                            self::debug("[ERR] ".$uniqueEx2->getMessage());
                            self::debug("Roll Back Sent!");
                            $this->conn->rollBack();
                            return;
                        }
                    }
                }


                self::debug("[COL:".$col->ColName."][PROP:Unique] ".($ChangeUnique?"CHANGE":"OK")." => ".$UniqueIndexKey);

                // create unique index table_test_login_uindex on table_test (login);
                // drop index table_test_login_uindex on table_test;




               /* create unique index table_test_login_uindex
	on table_test (login);*/


                /*object(Nette\Database\Row)#19 (6) {
                    ["Field"]=>
                    string(6) "passwd"
                    ["Type"]=>
                    string(8) "char(64)"
                    ["Null"]=>
                    string(3) "YES"
                    ["Key"]=>
                    string(0) ""
                    ["Default"]=>
                    NULL
                    ["Extra"]=>
                    string(0) ""
                }*/


            }

        }catch(DriverException $ex){


            try
            {
                $result = $this->conn->query("CREATE TABLE ".self::allowed_table_name($this->tblName)." (id int NOT NULL AUTO_INCREMENT, PRIMARY KEY (id));");
               // self::debug("Table created, reinitializing $"."this->install()\n");
                self::debug("[TABLE] Created! ".'$this->install();');
                $this->conn->commit();
                self::debug("TableCreate Commit Sent!");
                $this->install();
                //Table created, reinitializeing install();
            }
            catch(DriverException $ex2){
                //self::debug("Table  not created (".$ex->getMessage().") exiting...\n");

                self::debug("[TABLE] Failed to create... Exiting...");
                self::debug("[TABLE:ERR] ".$ex2->getMessage());
                self::debug("Roll Back Sent!");
                $this->conn->rollBack();

            }
            return;
        }


        //pokud se dostanu s kodem bez chyby až sem, mužu s klidem na srdci...
        $this->conn->commit();
        self::debug("Final Commit Sent!");


        return;
    }


    public function drop(){

        //tohle bylo jen pro testovaci ucely, ale tak necham to tu :D
        try{$this->conn->query("DROP TABLE ".self::allowed_table_name($this->tblName));}
        catch(DriverException $ex){}
    }



    private static $allowed_characters = [
        "q","w","e","r","t","z","u","i","o","p","a","s","d","f",
        "g","h","j","k","l","y","x","c","v","b","n","m","Q","W",
        "E","R","T","Z","U","I","O","P","A","S","D","F","G","H",
        "J","K","L","Y","X","C","V","B","N","M","_"
    ];

    /**
     * @param $tblname string
     * @return mixed
     * @throws \Exception
     */
    public static function allowed_table_name($tblname)
    {
        if(trim(str_replace(self::$allowed_characters,"",$tblname)) !== "")
            throw new \Exception("invalid characters in table name");

        return $tblname;
    }

}





/* Metoda ze ktere vychazim...
 *  public static function InstallDatabase(Connection $db, array $Tables)
    {
        foreach($Tables as $TableName => $TableColumns)
        {

            $TableDesc = null;
            try {
                $TableDesc = $db->fetchAll("DESCRIBE ".$TableName);
                //$db->fetch("SELECT * FROM ".$TableName." LIMIT 1");
            } catch(DriverException $ex) {
                if($ex->errorInfo[1] === 1146)
                {
                    $db->query("CREATE TABLE ".$TableName." (id int NOT NULL AUTO_INCREMENT, PRIMARY KEY (id));");
                    $TableDesc = $db->fetchAll("DESCRIBE ".$TableName);
                }
            }


            foreach($TableColumns as $ColumnName => $ColumnType)
            {
                $Exists = false;
                /**
                 * @var $DescItem Row
                 * /
foreach($TableDesc as $DescItem)
    if($DescItem->Field === $ColumnName)
        $Exists = true;


    if(!$Exists)
        $db->query("ALTER TABLE ".$TableName." ADD ".$ColumnName." ".$ColumnType." NOT NULL");
    }
    }
    }
 */
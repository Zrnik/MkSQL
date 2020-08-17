<?php
/*
 * Zrník.eu | MkSQL  
 * User: Programátor
 * Date: 06.08.2020 7:45
 */


namespace Zrny\MkSQL\Queries\Makers;


use Nette\Database\Connection;
use Nette\Database\DriverException;
use Nette\NotImplementedException;
use Nette\Utils\Strings;
use Tracy\Debugger;
use Zrny\MkSQL\Column;
use Zrny\MkSQL\Queries\Query;
use Zrny\MkSQL\Queries\Tables\ColumnDescription;
use Zrny\MkSQL\Queries\Tables\TableDescription;
use Zrny\MkSQL\Table;
use Zrny\MkSQL\Utils;

class QueryMakerSQLite implements IQueryMaker
{
    /**
     * Describing using "SHOW CREATE TABLE" because its the fastest method!
     * @param Connection $db
     * @param Table $table
     * @return TableDescription|null
     */
    public static function describeTable(Connection $db, Table $table): ?TableDescription
    {
        //Debug::
        Debugger::$maxLength = 3000;

        $Desc = new TableDescription();
        $Desc->queryMakerClass = __CLASS__;
        $Desc->table = $table;


        try {
            //$DescriptionData = explode("\n",$db->fetch(".schema ".$table->getName())["Create Table"]);
            $SQLiteTableData = $db->fetchAll("SELECT * FROM sqlite_master WHERE tbl_name = ?;", $table->getName());

            if (count($SQLiteTableData) === 0)
                throw new DriverException("Table does not exist!");
            $Desc->tableExists = true;

            //Columns:
            foreach ($table->getColumns() as $column) {
                $ColDesc = new ColumnDescription();
                $ColDesc->table = $table;
                $ColDesc->column = $column;


                foreach ($SQLiteTableData as $PartRow) {
                    if (Strings::startsWith($PartRow["sql"], "CREATE TABLE")) {
                        $queryToParse = Strings::trim(str_replace([
                            "CREATE TABLE " . $table->getName() . " (",
                            ")[end]"
                        ], "", $PartRow["sql"] . "[end]"));
                        $parts = explode(",", $queryToParse);

                        //bdump($table->getName());

                        foreach ($parts as $part) {
                            $part = Strings::trim($part);

                            //Column Exists
                            if (Strings::startsWith($part, $column->getName() . " ")) {

                                //bdump($part);
                                $ColDesc->columnExists = true;

                                //Type:
                                $ColDesc->type = explode(" ",$part)[1];

                                //Not Null
                                $ColDesc->notNull = Strings::contains($part,"NOT NULL");

                                //Default Value:
                                if(Strings::contains($part, "DEFAULT"))
                                {
                                    list($BeforeDefault,$DefaultPart)
                                        = explode("DEFAULT '", $part);

                                    $defaultValue = "";
                                    $defaultParts = explode("'",$DefaultPart);

                                    foreach($defaultParts as $DefaultPart)
                                    {
                                        $defaultValue .= $DefaultPart;
                                        // Je-li posledni znak Escapovany
                                        // apostrof, pokracujeme
                                        if(Strings::endsWith($DefaultPart,"\\"))
                                        {
                                            $defaultValue .= '\'';
                                        }
                                        else
                                        {
                                            break;
                                        }
                                    }
                                    $ColDesc->default = $defaultValue;
                                }

                            }


                        }
                    }

                    if (Strings::startsWith($PartRow["sql"], "CREATE UNIQUE INDEX")) {
                        $row = trim($PartRow["sql"]);
                        $parts = explode(" ",$row);

                        $_uniqueIndexKey = $parts[3];
                        $_uniqueIndexTable = $parts[5];
                        $_uniqueIndexColumn = Strings::trim($parts[6],"()");

                        if($_uniqueIndexTable === $ColDesc->table->getName())
                            if($_uniqueIndexColumn === $ColDesc->column->getName())
                                $ColDesc->uniqueIndex = $_uniqueIndexKey;
                    }
                }


                // TODO: Support Foreign Keys

                $Desc->columns[] = $ColDesc;
            }
        } catch (DriverException $ex) {
            $Desc->tableExists = false;
        }

        return $Desc;
    }

    public static function createTableQuery(Table $table): ?Query
    {
        $primaryKeyName = Utils::confirmKeyName($table->getName()."_id_pk");

        return new Query(
            $table,null,
            "CREATE TABLE ".$table->getName()." (id integer constraint ".$primaryKeyName." primary key autoincrement);",
            "Table '".$table->getName()."' does not exist. Creating."
        );
    }

    public static function alterTableColumnQuery(Table $table, Column $column): ?Query
    {
        $ModifySQL = 'ALTER TABLE '.$table->getName().' MODIFY '.$column->getName().' '.$column->getType();

        if($column->getDefault())
            $ModifySQL .= " DEFAULT '".$column->getDefault()."'";

        $ModifySQL .= ($column->getNotNull() ?' NOT ':' ').'NULL';

        return new Query(
            $table,$column,
            trim($ModifySQL),
            "reason-fill-by-column-class"
        );
    }

    public static function createTableColumnQuery(Table $table, Column $column): ?Query
    {
        $CreateSQL = 'ALTER TABLE '.$table->getName().' ';
        $CreateSQL .= 'ADD '.$column->getName().' '.$column->getType().' ';

        //Default Value

        if($column->getDefault() !== null)
            $CreateSQL .= 'DEFAULT \''.$column->getDefault().'\' ';

        if($column->getDefault() === null && $column->getNotNull())
            $CreateSQL .= 'DEFAULT \'\' ';

        //Null/NotNull
        $CreateSQL .= ( $column->getNotNull() ? "NOT " : '').'NULL ';

        //Comment

        return new Query(
            $table,$column,
            trim($CreateSQL),
            "Column '".$table->getName().".".$column->getName()."' does not exist. Creating."
        );
    }

    public static function createUniqueIndexQuery(Table $table, Column $column): ?Query
    {
        $uniqueKeyName = Utils::confirmKeyName($table->getName().'_'.$column->getName().'_mksql_uindex');

        return new Query(
            $table,$column,
            'CREATE UNIQUE INDEX '.$uniqueKeyName.' on '.$table->getName().' ('.$column->getName().');',
            "Unique index required on column '".$table->getName().".".$column->getName()."'. Creating."
        );
    }

    public static function removeUniqueIndexQuery(Table $table, Column $column, string $uniqueKeyName): ?Query
    {
        return new Query(
            $table,$column,
            'DROP INDEX '.$uniqueKeyName.' ON '.$table->getName().';',
            "Unique index on column '".$table->getName().".".$column->getName()."' is unwanted. Removing."
        );
    }

    public static function createForeignKey(Table $table, Column $column, string $RefPointerString): ?Query
    {
        //SQLite needs to Re-Create the table for Foreign Keys, just do not support it for now...
        // TODO: Support Foreign Keys
        throw new NotImplementedException("Foreign keys are not implemented in SQLite");

    }

    public static function removeForeignKey(Table $table, Column $column, string $ForeignKeyName): ?Query
    {
        //SQLite needs to Re-Create the table for Foreign Keys, just do not support it for now...
        // TODO: Support Foreign Keys
        throw new NotImplementedException("Foreign keys are not implemented in SQLite");
    }

    public static function compareType(string $type1, string $type2): bool
    {
        $type1 = strtolower($type1);
        $type2 = strtolower($type2);

        if($type1 === "integer")
            $type1 = "int";

        if($type2 === "integer")
            $type2 = "int";

        $Exceptions = [
            "tinyint", "mediumint", "int", "bigint",
        ];

        foreach($Exceptions as $Exception)
            if(Strings::startsWith($type1,$Exception) && Strings::startsWith($type2,$Exception))
                return true;

        return $type1 === $type2;
    }

    public static function compareComment(?string $type1, ?string $type2): bool
    {
        //Comments not supported by SQLite, just assume its correct anyhow
        return true;
    }
}
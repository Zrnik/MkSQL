<?php
/*
 * Zrník.eu | MkSQL  
 * User: Programátor
 * Date: 06.08.2020 7:45
 */

namespace Zrny\MkSQL\Queries\Makers;

use Exception;
use Nette\Utils\Strings;
use PDO;
use Zrny\MkSQL\Column;
use Zrny\MkSQL\Queries\Query;
use Zrny\MkSQL\Queries\Tables\ColumnDescription;
use Zrny\MkSQL\Queries\Tables\TableDescription;
use Zrny\MkSQL\Table;
use Zrny\MkSQL\Utils;

class QueryMakerSQLite implements IQueryMaker
{
    /**
     * @param PDO $pdo
     * @param Table $table
     * @return TableDescription|null
     */
    public static function describeTable(PDO $pdo, Table $table): ?TableDescription
    {
        $Desc = new TableDescription();
        $Desc->queryMakerClass = __CLASS__;
        $Desc->table = $table;

        try {

            //$SQLiteTableData = $db->fetchAll("SELECT * FROM sqlite_master WHERE tbl_name = ?;", $table->getName());
            $Statement = $pdo->prepare("SELECT * FROM sqlite_master WHERE tbl_name = ?;");
            $Statement->execute([$table->getName()]);
            $SQLiteTableData = $Statement->fetchAll(PDO::FETCH_ASSOC);

            if (count($SQLiteTableData) === 0)
                throw new Exception("Table does not exist!");

            $Desc->tableExists = true;

            //Columns:
            foreach ($table->columnList() as $column) {

                $ColDesc = new ColumnDescription();
                $ColDesc->table = $table;
                $ColDesc->column = $column;

                foreach ($SQLiteTableData as $PartRow) {
                    if (Strings::startsWith($PartRow["sql"], "CREATE TABLE")) {
                        $queryToParse = Strings::trim(str_replace([
                            "CREATE TABLE " . $table->getName() . " (",
                            ")[end]"
                        ], "", $PartRow["sql"] . "[end]"));

                        $parts = explode(", ", $queryToParse);

                        foreach ($parts as $part) {
                            $part = Strings::trim($part);

                            //Column Exists
                            if (Strings::startsWith($part, $column->getName() . " ")) {

                                $ColDesc->columnExists = true;

                                //Type:
                                $ColDesc->type = explode(" ", $part)[1];

                                //Not Null
                                $ColDesc->notNull = Strings::contains($part, "NOT NULL");

                                //Default Value:
                                if (Strings::contains($part, "DEFAULT")) {
                                    list($BeforeDefault, $DefaultPart)
                                        = explode("DEFAULT '", $part);

                                    $defaultValue = "";
                                    $defaultParts = explode("'", $DefaultPart);

                                    foreach ($defaultParts as $DefaultPart) {
                                        $defaultValue .= $DefaultPart;
                                        // Escaped Apostrophe
                                        if (Strings::endsWith($DefaultPart, "\\")) {
                                            $defaultValue .= '\'';
                                        } else {
                                            break;
                                        }
                                    }
                                    $ColDesc->default = $defaultValue;
                                }

                                //Foreign Keys:
                                if (Strings::contains($part, "CONSTRAINT"))
                                {
                                    $Constraints = [];

                                    $f = true;
                                    foreach(explode("CONSTRAINT",$part) as $constraint)
                                    {
                                        if($f)
                                        {
                                            $f = false;
                                            continue;
                                        }

                                        if(Strings::contains($constraint,"REFERENCES"))
                                        {
                                            //Yup, seems like foreign key
                                            list($key, $ref) = explode("REFERENCES", $constraint);

                                            $ref = str_replace([")"," "],"",$ref);
                                            $ref = str_replace("(",".",$ref);
                                            $ColDesc->foreignKeys[$ref] = trim($key);
                                            //echo "Found ForeignKey: ".$ref.'=>'.$key.PHP_EOL;
                                        }
                                    }
                                }
                            }
                        }
                    }

                    if (Strings::startsWith($PartRow["sql"], "CREATE UNIQUE INDEX")) {
                        $row = trim($PartRow["sql"]);
                        $parts = explode(" ", $row);

                        $_uniqueIndexKey = $parts[3];
                        $_uniqueIndexTable = $parts[5];
                        $_uniqueIndexColumn = Strings::trim($parts[6], "()");

                        if ($_uniqueIndexTable === $ColDesc->table->getName())
                            if ($_uniqueIndexColumn === $ColDesc->column->getName())
                                $ColDesc->uniqueIndex = $_uniqueIndexKey;
                    }
                }

                $Desc->columns[] = $ColDesc;
            }
        } catch (Exception $ex) {
            $Desc->tableExists = false;
        }

        return $Desc;
    }

    /**
     * @param Table $table
     * @param TableDescription|null $oldTableDescription
     * @return Query[]|null
     */
    public static function createTableQuery(Table $table, ?TableDescription $oldTableDescription): ?array
    {
        $primaryKeyName = Utils::confirmKeyName($table->getName() . "_id_pk");

        return [
            (new Query($table, null))
                ->setQuery("CREATE TABLE " . $table->getName() . " (id integer constraint " . $primaryKeyName . " primary key autoincrement);")
                ->setReason("Table '" . $table->getName() . "' not found.")
        ];
    }

    /**
     * @param Table $table
     * @param Column $_notNeededColumn
     * @param TableDescription|null $oldTableDescription
     * @param ColumnDescription|null $columnDescription
     * @return Query[]|null
     */
    public static function alterTableColumnQuery(Table $table, Column $_notNeededColumn, ?TableDescription $oldTableDescription, ?ColumnDescription $columnDescription): ?array
    {
        $alterTableQueryList = [];

        if(
            isset($_notNeededColumn->_parameters["handled"])
            &&
            $_notNeededColumn->_parameters["handled"] === true
        )
            return [];

        $temporaryName = Utils::confirmTableName($table->getName() . "_mksql_tmp");

        $createTableQuery = static::createTableQuery($table, $oldTableDescription)[0];
        $createTableQuery->setReason("Alteration required for table '".$table->getName().".".$_notNeededColumn->getName()."'.");
        $createTableQuery->setQuery( str_replace(
            "CREATE TABLE " . $table->getName() . " (",
            "CREATE TABLE " . $temporaryName . " (",
            $createTableQuery->getQuery()));

        $alterTableQueryList[] = $createTableQuery;

        $MoveColumns = [];

        foreach ($table->columnList() as $column) {
            $MoveColumns[] = $column->getName();
            $column->_parameters["handled"] = true;
            $createColumnQueryList = static::createTableColumnQuery($table, $column, $oldTableDescription, $columnDescription);
            foreach ($createColumnQueryList as $columnQuery) {
                $columnQuery->setQuery(str_replace(
                    "ALTER TABLE " . $table->getName() . " ADD",
                    "ALTER TABLE " . $temporaryName . " ADD",
                    $columnQuery->getQuery()));

                $alterTableQueryList[] = $columnQuery;
            }
        }

        $keyInBothArrays = ["id"];
        foreach ($MoveColumns as $columnName) {
            foreach ($oldTableDescription->columns as $columnDescription) {
                if ($columnDescription->column->getName() === $columnName) {
                    $keyInBothArrays[] = $columnName;
                    break;
                }
            }

        }

        $columnList = implode(", ", $keyInBothArrays);


        $InsertIntoQuery = new Query($table, $_notNeededColumn);
        $InsertIntoQuery->setQuery("INSERT INTO " . $temporaryName . "(" . $columnList . ") SELECT " . $columnList . " FROM " . $table->getName());
        $InsertIntoQuery->setReason("Altering Table '".$temporaryName."' created, moving data...");
        $alterTableQueryList[] = $InsertIntoQuery;

        $DropOldTable = new Query($table, $_notNeededColumn);
        $DropOldTable->setQuery("DROP TABLE " . $table->getName());
        $DropOldTable->setReason("Altering Table '".$temporaryName."' data moved, dropping original table '".$table->getName()."'...");
        $alterTableQueryList[] = $DropOldTable;


        $RenameTable = new Query($table, $_notNeededColumn);
        $RenameTable->setQuery("ALTER TABLE " . $temporaryName . " RENAME TO " . $table->getName());
        $RenameTable->setReason("Original Table '".$table->getName()."' dropped, renaming table '".$temporaryName."'...");
        $alterTableQueryList[] = $RenameTable;

        //echo " -Modified table ".$table->getName()." creating Unique Indexes".PHP_EOL;

        foreach($table->columnList() as $column)
        {
            if($column->getUnique())
            {
                //echo " - creating unique index for ".$column->getName()." in table ".$table->getName().PHP_EOL;

                $newQueries = static::createUniqueIndexQuery(
                    $table,
                    $column,
                    $oldTableDescription,
                    null
                );

                foreach($newQueries as $query)
                {
                    //echo $query->getQuery().PHP_EOL;
                    $alterTableQueryList[] = $query;
                }
            }
        }
        return $alterTableQueryList;
    }


    /**
     * @param Table $table
     * @param Column $column
     * @param TableDescription|null $oldTableDescription
     * @param ColumnDescription|null $columnDescription
     * @return Query[]|null
     */
    public static function createTableColumnQuery(Table $table, Column $column, ?TableDescription $oldTableDescription, ?ColumnDescription $columnDescription): ?array
    {
        $CreateSQL = 'ALTER TABLE ' . $table->getName() . ' ';
        $CreateSQL .= 'ADD ' . $column->getName() . ' ' . $column->getType() . ' ';

        //Default Value

        if ($column->getDefault() !== null)
            $CreateSQL .= 'DEFAULT \'' . $column->getDefault() . '\' ';

        if ($column->getDefault() === null && $column->getNotNull())
            $CreateSQL .= 'DEFAULT \'\' ';

        //Null/NotNull
        $CreateSQL .= ($column->getNotNull() ? "NOT " : '') . 'NULL ';

        //Comment
        //Not in SQLite, BUT:
        //FOREIGN KEY!

        foreach($column->getForeignKeys() as $keyTarget)
        {
            $ptr = Utils::confirmForeignKeyTarget($keyTarget);
            list($targetTable, $targetColumn) = explode(".", $ptr);

            $foreignKeyName = Utils::confirmKeyName("f_key_" . $table->getName() . '_' . $targetTable . '_' . $column->getName() . '_' . $targetColumn);

            $CreateSQL .= 'CONSTRAINT '.$foreignKeyName.' REFERENCES '.$targetTable.' ('.$targetColumn.') ';
        }


        return [
            (new Query($table, $column))
                ->setQuery(trim($CreateSQL))
                ->setReason("Column '" . $table->getName() . "." . $column->getName() . "' not found.")
        ];
    }

    /**
     * @param Table $table
     * @param Column $column
     * @param TableDescription|null $oldTableDescription
     * @param ColumnDescription|null $columnDescription
     * @return Query[]|null
     */
    public static function createUniqueIndexQuery(Table $table, Column $column, ?TableDescription $oldTableDescription, ?ColumnDescription $columnDescription): ?array
    {
        //I haven't found true answer, some say that SQLite keys
        // are limited by SQLITE_MAX_SQL_LENGTH (default 1 Mil.)
        // and (hardcoded?) maximum of 1 073 741 824
        //
        // Lets just stick with MySQL's 64 for now
        // After i test the real maximum value i will update this.
        $SQLite_Key_MaxLength = 64;

        $newKey = Utils::confirmKeyName(
            "unique_index_" . $table->getName() . "_" . $column->getName(),
            $SQLite_Key_MaxLength
        );

        return [
            (new Query($table, $column))
            ->setQuery('CREATE UNIQUE INDEX ' . $newKey . ' on ' . $table->getName() . ' (' . $column->getName() . ');')
            ->setReason("Unique index on column '" . $table->getName() . "." . $column->getName() . "' not found.")
        ];
    }

    /**
     * @param Table $table
     * @param Column $column
     * @param string $uniqueKeyName
     * @param TableDescription|null $oldTableDescription
     * @param ColumnDescription|null $columnDescription
     * @return Query[]|null
     */
    public static function removeUniqueIndexQuery(Table $table, Column $column, string $uniqueKeyName, ?TableDescription $oldTableDescription, ?ColumnDescription $columnDescription): ?array
    {
        return [
            (new Query($table, $column))
            ->setQuery('DROP INDEX ' . $uniqueKeyName . ';')
            ->setReason("Unique index on column '" . $table->getName() . "." . $column->getName() . "' not defined.")
        ];
    }

    /**
     * @param Table $table
     * @param Column $column
     * @param string $RefPointerString table_name.table_column
     * @param TableDescription|null $oldTableDescription
     * @param ColumnDescription|null $columnDescription
     * @return Query[]|null
     */
    public static function createForeignKey(Table $table, Column $column, string $RefPointerString, ?TableDescription $oldTableDescription, ?ColumnDescription $columnDescription): ?array
    {
        return static::alterTableColumnQuery($table,$column,$oldTableDescription,$columnDescription);
    }

    /**
     * @param Table $table
     * @param Column $column
     * @param string $ForeignKeyName
     * @param TableDescription|null $oldTableDescription
     * @param ColumnDescription|null $columnDescription
     * @return Query[]|null
     */
    public static function removeForeignKey(Table $table, Column $column, string $ForeignKeyName, ?TableDescription $oldTableDescription, ?ColumnDescription $columnDescription): ?array
    {
        return static::alterTableColumnQuery($table,$column,$oldTableDescription,$columnDescription);
    }

    /**
     * @param string $type1
     * @param string $type2
     * @return bool
     */
    public static function compareType(string $type1, string $type2): bool
    {
        $type1 = strtolower($type1);
        $type2 = strtolower($type2);

        if ($type1 === "integer")
            $type1 = "int";

        if ($type2 === "integer")
            $type2 = "int";

        $Exceptions = [
            "tinyint", "mediumint", "int", "bigint",
        ];

        foreach ($Exceptions as $Exception)
            if (Strings::startsWith($type1, $Exception) && Strings::startsWith($type2, $Exception))
                return true;

        return $type1 === $type2;
    }

    /**
     * @param string|null $type1
     * @param string|null $type2
     * @return bool
     */
    public static function compareComment(?string $type1, ?string $type2): bool
    {
        //Comments not supported by SQLite, just report that its correct anyhow
        return true;
    }
}
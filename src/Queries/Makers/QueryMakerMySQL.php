<?php
/*
 * Zrník.eu | MkSQL  
 * User: Programátor
 * Date: 06.08.2020 7:45
 */


namespace Zrny\MkSQL\Queries\Makers;


use Nette\Database\Connection;
use Nette\Database\DriverException;
use Nette\Utils\Strings;
use Zrny\MkSQL\Column;
use Zrny\MkSQL\Queries\Query;
use Zrny\MkSQL\Queries\Tables\ColumnDescription;
use Zrny\MkSQL\Queries\Tables\TableDescription;
use Zrny\MkSQL\Table;
use Zrny\MkSQL\Utils;

class QueryMakerMySQL implements IQueryMaker
{
    /**
     * Describing using "SHOW CREATE TABLE" because its the fastest method!
     * @param Connection $db
     * @param Table $table
     * @return TableDescription|null
     */
    public static function describeTable(Connection $db, Table $table): ?TableDescription
    {
        $Desc = new TableDescription();
        $Desc->queryMakerClass = __CLASS__;
        $Desc->table = $table;
        try{
            $DescriptionData = explode("\n",$db->fetch("SHOW CREATE TABLE ".$table->getName())["Create Table"]);
            $Desc->tableExists = true;

            //Columns:
            foreach($table->getColumns() as $column)
            {
                $ColDesc = new ColumnDescription();
                $ColDesc->table = $table;
                $ColDesc->column = $column;


                /** @var string|null $col_desc */
                $col_desc = null;
                $unique_index_key = null;
                $foreign_keys = [];
                //region Find Column Descriptions
                foreach($DescriptionData as $DescriptionLine)
                {
                    $DescriptionLine = trim($DescriptionLine);


                    if(Strings::startsWith($DescriptionLine,"`".$column->getName()."`" ))
                        $col_desc = $DescriptionLine;

                    if(
                        Strings::startsWith($DescriptionLine,"UNIQUE KEY") &&
                        (
                            Strings::endsWith($DescriptionLine, "(`".$column->getName()."`)")
                            ||
                            Strings::endsWith($DescriptionLine, "(`".$column->getName()."`),")
                        )
                    )
                    {

                        $unique_index_key = explode("`",$DescriptionLine)[1];


                    }

                    if(
                        Strings::startsWith($DescriptionLine,"CONSTRAINT ") &&
                        Strings::contains($DescriptionLine,"FOREIGN KEY")
                    )
                    {
                        $fkData = explode("`", $DescriptionLine);
                        if($fkData[3] === $column->getName())
                            $foreign_keys[$fkData[5].".".$fkData[7]] = $fkData[1];
                    }
                }
                //endregion

                if($col_desc === null)
                {
                    $ColDesc->columnExists = false;
                }
                else
                {
                    $ColDesc->columnExists = true;

                    //Need:
                    // - Type
                    $ColDesc->type = explode(" ",$col_desc)[1];

                    // - NOT NULL
                    $ColDesc->notNull = strpos($col_desc,"NOT NULL") !== false;
                    // - Unique
                    $ColDesc->uniqueIndex = $unique_index_key;
                    // - Default Value
                    if(Strings::contains($col_desc,"DEFAULT '"))
                    {
                        list($trash,$default_part) = explode("DEFAULT '",$col_desc);
                        $RestOfDefault = explode("'",$default_part);

                        $Index = 0;
                        $DefaultValue = $RestOfDefault[$Index];

                        //it ends with backshlash, that means it was real apostrophe that was escaped!
                        while(Strings::endsWith($DefaultValue,"\\") && isset($RestOfDefault[$Index + 1]))
                        {
                            $Index++;
                            $DefaultValue .= "'".$RestOfDefault[$Index];
                        }

                        $ColDesc->default = $DefaultValue;
                    }
                    else
                    {
                        $ColDesc->default = null;
                    }

                    // - Foreign Keys
                    $ColDesc->foreignKeys = $foreign_keys;

                    // - Comment
                    if(Strings::contains($col_desc,"COMMENT '"))
                    {
                        list($trash,$comment_part) = explode("COMMENT '",$col_desc);
                        $RestOfComments = explode("'",$comment_part);

                        $Index = 0;
                        $Comment = $RestOfComments[$Index];

                        //it ends with backshlash, that means it was real apostrophe that was escaped!
                        while(Strings::endsWith($Comment,"\\") && isset($RestOfComments[$Index + 1]))
                        {
                            $Index++;
                            $Comment .= "'".$RestOfComments[$Index];
                        }

                        $ColDesc->comment = $Comment;
                    }
                    else
                    {
                        $ColDesc->comment = null;
                    }
                }

                $Desc->columns[] = $ColDesc;
            }
        }
        catch(DriverException $ex)
        {
            $Desc->tableExists = false;
        }

        return $Desc;
    }

    public static function createTableQuery(Table $table): ?Query
    {
        return new Query(
            $table,null,
            "CREATE TABLE ".$table->getName()." (id int NOT NULL COMMENT 'mksql handled' AUTO_INCREMENT PRIMARY KEY) COMMENT 'Table handled by MkSQL';",
            "Table '".$table->getName()."' does not exist. Creating."
        );
    }

    public static function alterTableColumnQuery(Table $table, Column $column): ?Query
    {
        $ModifySQL = 'ALTER TABLE '.$table->getName().' MODIFY '.$column->getName().' '.$column->getType();

        if($column->getDefault())
            $ModifySQL .= " DEFAULT '".$column->getDefault()."'";

        $ModifySQL .= ($column->getNotNull() ?' NOT ':' ').'NULL';

        if($column->getComment() !== null && $column->getComment() !== "")
            $ModifySQL .= " COMMENT '".$column->getComment()."'";

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

        //Null/NotNull
        $CreateSQL .= ( $column->getNotNull() ? "NOT " : '').'NULL ';

        //Comment
        if($column->getComment() !== null)
            $CreateSQL .= "COMMENT '".$column->getComment()."' ";

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
        $foreignKeyParts = explode(".",$RefPointerString);
        $targetTable = $foreignKeyParts[0];
        $targetColumn = $foreignKeyParts[1];

        $foreignKeyName = Utils::confirmKeyName($table->getName().'_'.$targetTable.'_'.$column->getName().'_'.$targetColumn.'_mksql_fk');

        return new Query(
            $table,$column,
            'ALTER TABLE '.$table->getName().' ADD CONSTRAINT '.$foreignKeyName.'
                         FOREIGN KEY ('.$column->getName().') REFERENCES '.$targetTable.' ('.$targetColumn.');',
            "Foreign key on '".$table->getName().".".$column->getName()."' referencing '".$RefPointerString." required!'. Creating."
        );
    }

    public static function removeForeignKey(Table $table, Column $column, string $ForeignKeyName): ?Query
    {
         return new Query(
             $table,$column,
             "ALTER TABLE ".$table->getName()." DROP FOREIGN KEY ".$ForeignKeyName.";",
             "Foreign key '".$ForeignKeyName."' not defined for column '".$column->getName()."'."
         );
    }

    public static function compareType(string $type1, string $type2): bool
    {
        $type1 = strtolower($type1);
        $type2 = strtolower($type2);

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
        return $type1 === $type2;
    }
}
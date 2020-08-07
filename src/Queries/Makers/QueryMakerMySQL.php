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
        return new Query(
            $table,$column,
            'CREATE UNIQUE INDEX '.$table->getName().'_'.$column->getName().'_mksql_uindex on '.$table->getName().' ('.$column->getName().');',
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

        return new Query(
            $table,$column,
            'ALTER TABLE '.$table->getName().' ADD CONSTRAINT '.$table->getName().'_'.$targetTable.'_'.$column->getName().'_'.$targetColumn.'_mksql_fk
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
}



/*
 *    $description = $this->findMyDescription($full_desc,$driverType);
        $indexes = $this->findMyIndexes($full_indexes,$driverType);
        $keys = $this->findMyKeys($full_keys,$driverType);

        $columnExists = $description !== null;

        //region Create Column
        if(!$columnExists)
        {
            //Create Column
            if($driverType === DriverType::MySQL)
            {
                $CreateSQL = 'ALTER TABLE '.$this->parent->getName().' ';
                $CreateSQL .= 'ADD '.$this->getName().' '.$this->getType().' ';

                //Default Value

                if($this->getDefault() !== null)
                    $CreateSQL .= 'DEFAULT \''.$this->getDefault().'\' ';

                //Null/NotNull
                $CreateSQL .= ( $this->getNotNull() ? "NOT " : '').'NULL ';

                //Comment
                if($this->getComment() !== null)
                    $CreateSQL .= "COMMENT '".$this->getComment()."' ";

                $CreateColumnCommand = [
                    "reason" => "Creating column ".$this->getName().", because it doesnt exists!",
                    "sql" => trim($CreateSQL).';'
                ];
                $Commands[] =  $CreateColumnCommand;
                Updater::logUpdate($this->parent->getName(), $this->getName(),$CreateColumnCommand);

                //unique
                if($this->getUnique())
                {
                    $MakeUniqueCommand = [
                        "reason" => "Column ".$this->getName()." created, but unique key required!",
                        "sql" => 'CREATE UNIQUE INDEX '.$this->parent->getName().'_'.$this->getName().'_mksql_uindex on '.$this->parent->getName().' ('.$this->getName().');'
                    ];
                    $Commands[] = $MakeUniqueCommand;
                    Updater::logUpdate($this->parent->getName(), $this->getName(),$MakeUniqueCommand);
                }

                //foreign key
                if(count($this->getForeignKeys())>0)
                {
                    foreach($this->getForeignKeys() as $ForeignKey)
                    {
                        $foreignKeyParts = explode(".",$ForeignKey);
                        $targetTable = $foreignKeyParts[0];
                        $targetColumn = $foreignKeyParts[1];

                         $ForeignKeyCreateCommand = [
                            "reason" => "Column ".$this->getName()." created, but foreign key '".$ForeignKey."' required!",
                            "sql" => 'ALTER TABLE '.$this->parent->getName().' ADD CONSTRAINT '.$this->parent->getName().'_'.$targetTable.'_'.$this->getName().'_'.$targetColumn.'_mksql_fk
                         FOREIGN KEY ('.$this->getName().') REFERENCES '.$targetTable.' ('.$targetColumn.');'
                        ];
                        $Commands[] = $ForeignKeyCreateCommand;
                        Updater::logUpdate($this->parent->getName(), $this->getName(),$ForeignKeyCreateCommand);
                    }
                }
            }
        }
        //endregion
        //region Update Column
        else
        {
            if($driverType === DriverType::MySQL)
            {

                //region Alter Table
                $useAlterQueryReasons = [];
                $BaseAlterQuery = 'ALTER TABLE '.$this->parent->getName().' MODIFY '.$this->getName().' '.$this->getType();

                //Type
                if(!Utils::typeEquals($description["Type"],$this->getType()))
                {
                    $useAlterQueryReasons[] = 'Type Different ['.$description["Type"].'] != ['.$this->getType().']';
                }

                //Default
                if($description["Default"] === null && $this->getDefault() !== null)
                {
                    $useAlterQueryReasons[] = 'Default Missing';
                    $BaseAlterQuery .= " DEFAULT '".$this->getDefault()."'";
                }
                elseif ($description["Default"] !== null && $this->getDefault() === null)
                {
                    $useAlterQueryReasons[] = 'Default Not Wanted';
                }

                //Null/NotNull
                if($this->getNotNull() !== ( $description["Null"] === "NO" ))
                {
                    $useAlterQueryReasons[] = 'NotNull Different';
                }
                $BaseAlterQuery .= ($this->getNotNull() ?' NOT ':' ').'NULL';

                //Comment:
                //if($this->getComment() === null)
                $Comment = trim($description["Comment"]) === "" ? null : $description["Comment"];
                if($this->getComment() !== null && $this->getComment() !== "")
                    $BaseAlterQuery .= " COMMENT '".$this->getComment()."'";

                if($Comment != $this->getComment())
                    $useAlterQueryReasons[] = "Comment update required ('".$Comment."' != '".$this->getComment()."')";

                $BaseAlterQuery .= ';';
                if(count($useAlterQueryReasons) > 0)
                {
                    $AlterTableCommand = [
                        "reason" => implode(", ",$useAlterQueryReasons),
                        "sql" => $BaseAlterQuery
                    ];
                    $Commands[] = $AlterTableCommand;
                    Updater::logUpdate($this->parent->getName(), $this->getName(), $AlterTableCommand);
                }
                //endregion

                //region Unique Index
                $isColumnUnique = false; //$indexes["Non_unique"] === 0;
                $shouldBeUnique = $this->getUnique();

                foreach($indexes as $index)
                {
                    if($index["Non_unique"] === 0)
                    {
                        $isColumnUnique = true;
                        break;
                    }
                }

                if($isColumnUnique !== $shouldBeUnique)
                {
                    if($shouldBeUnique)
                    {
                        //Add UniqueIndex
                        $AddUniqueIndexCommand = [
                            "reason" => "Column ".$this->getName()." needs unique index, but it doesn't have it!",
                            "sql" => 'CREATE UNIQUE INDEX '.$this->parent->getName().'_'.$this->getName().'_mksql_uindex on '.$this->parent->getName().' ('.$this->getName().');'
                        ];
                        $Commands[] = $AddUniqueIndexCommand;
                        Updater::logUpdate($this->parent->getName(), $this->getName(), $AddUniqueIndexCommand);
                    }
                    else
                    {
                        //Remove all unique indexes
                        foreach($indexes as $index)
                        {
                            if($index["Non_unique"] === 0)
                            {
                                $RemoveUniqueIndexCommand = [
                                    "reason" => "Removing unique index: ".$index["Key_name"],
                                    "sql" => 'DROP INDEX '.$index["Key_name"].' ON '.$this->parent->getName().';'
                                ];
                                $Commands[] = $RemoveUniqueIndexCommand;
                                Updater::logUpdate($this->parent->getName(), $this->getName(), $RemoveUniqueIndexCommand);
                            }
                        }
                    }
                }
                //endregion

                //region Foreign Key

                $existingForeignKeys = [];
                foreach($keys as $key)
                {
                    if($key["REFERENCED_TABLE_NAME"] !== null && $key["REFERENCED_COLUMN_NAME"] !== null)
                        $existingForeignKeys[$key["REFERENCED_TABLE_NAME"].'.'.$key["REFERENCED_COLUMN_NAME"]] = $key["CONSTRAINT_NAME"];
                }

                $keysToCreate = [];
                $keysToRemove = [];

                //region 1. keys to REMOVE
                foreach($existingForeignKeys as $existingKey => $keyName)
                    if(!in_array($existingKey, $this->getForeignKeys()))
                        $keysToRemove[] = $keyName;
                //endregion

                //region 2. keys to ADD
                foreach($this->getForeignKeys() as $key)
                    if(!isset($existingForeignKeys[$key]))
                        $keysToCreate[] = $key;
                //endregion

                foreach($keysToRemove as $keyToRemove)
                {
                    $RemoveKeyCommand = [
                        "reason" => "Key '".$keyToRemove."' is unwanted",
                        "sql" => "ALTER TABLE ".$this->parent->getName()." DROP FOREIGN KEY ".$keyToRemove
                    ];
                    $Commands[] = $RemoveKeyCommand;
                    Updater::logUpdate($this->parent->getName(), $this->getName(), $RemoveKeyCommand);
                }

                foreach($keysToCreate as $keyToCreate)
                {
                    list($targetTable, $targetColumn) = explode(".",$keyToCreate);

                    $AddKeyCommand = [
                        "reason" => "Foreign key '".$keyToCreate."' not found, creating...",
                        "sql" => 'ALTER TABLE '.$this->parent->getName().' ADD CONSTRAINT
                                 '.$this->parent->getName().'_'.$targetTable.'_'.$this->getName().'_'.$targetColumn.'_mksql_fk
                                 FOREIGN KEY ('.$this->getName().') REFERENCES '.$targetTable.' ('.$targetColumn.');'
                    ];
                    $Commands[] = $AddKeyCommand;
                    Updater::logUpdate($this->parent->getName(), $this->getName(), $AddKeyCommand);
                }
                //endregion
            }
        }
        //endregion

        return $Commands;
    }

    /**
     * @param array $full_desc
     * @param int $driverType
     * @return Row|null

private function findMyDescription(array $full_desc, int $driverType) : ?Row
{
    if($driverType === DriverType::MySQL)
    {
        foreach($full_desc as $desc_detail)
            if($desc_detail["Field"] === $this->getName())
                return $desc_detail;
    }
    return null;
}

/**
 * @param array $full_indexes
 * @param int $driverType
 * @return Row[]

private function findMyIndexes(array $full_indexes, int $driverType) : array
{
    $Indexes = [];
    if($driverType === DriverType::MySQL)
    {
        foreach($full_indexes as $index_row)
        {
            if($index_row["Table"] == $this->parent->getName() && $index_row["Column_name"] == $this->getName())
                $Indexes[] = $index_row;
        }
    }
    return $Indexes;
}

/**
 * @param array $full_keys
 * @param int $driverType
 * @return Row[]

private function findMyKeys(array $full_keys, int $driverType) : array
{
    $Keys = [];
    if($driverType === DriverType::MySQL)
    {
        foreach($full_keys as $key_row)
            if($key_row["TABLE_NAME"] == $this->parent->getName() && $key_row["COLUMN_NAME"] == $this->getName())
                $Keys[] = $key_row;
    }
    return $Keys;
}*/
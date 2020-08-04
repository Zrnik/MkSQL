<?php
/*
 * Zrník.eu | MkSQL  
 * User: Programátor
 * Date: 31.07.2020 9:38
 */


namespace Zrny\MkSQL;

use Nette\Database\Row;
use Zrny\MkSQL\Enum\DriverType;

class Column
{
    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $name;

    /**
     * @var Table
     */
    private $parent;

    /**
     * Column constructor.
     * @param string $colName
     * @param Table $parent
     * @param string $colType
     */
    public function __construct(string $colName, Table $parent, string $colType = "int")
    {
        $colName = Utils::confirmName($colName);
        $colType = Utils::confirmName($colType, ["(", ")"]);
        $this->parent = $parent;
        $this->name = $colName;
        $this->type = $colType;
    }

    /**
     * Returns back to parent table.
     * @return Table
     */
    public function endColumn(): Table
    {
        return $this->parent;
    }

    /**
     * Returns column name.
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns column type.
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    //region Unique

    /**
     * @var bool
     */
    private $unique = false;

    /**
     * Sets column to be unique or not
     * @param bool $Unique
     * @return $this
     */
    public function setUnique(bool $Unique = true): Column
    {
        $this->unique = $Unique;
        return $this;
    }

    /**
     * Is column unique?
     * @return bool
     */
    public function getUnique(): bool
    {
        return $this->unique;
    }
    //endregion

    //region NOT NULL
    /**
     * @var bool
     */
    private $NotNull = false;

    /**
     * Sets column to be NOT NULL or can be NULL
     * @param bool $notNull
     * @return $this
     */
    public function setNotNull(bool $notNull = true): Column
    {
        $this->NotNull = $notNull;
        return $this;
    }

    /**
     * Is column NOT NULL?
     * @return bool
     */
    public function getNotNull(): bool
    {
        return $this->NotNull;
    }
    //endregion

    //region Default Value
    /**
     * @var mixed|null
     */
    private $default = null;

    /**
     * Set or unset (with null) default value of column.
     * @param mixed|null $defaultValue
     * @return $this
     */
    public function setDefault($defaultValue): Column
    {
        $this->default = $defaultValue;
        return $this;
    }

    /**
     * @return mixed|null
     */
    public function getDefault()
    {
        return $this->default;
    }
    //endregion

    //region Foreign Keys
    /**
     * @var string[]
     */
    private $foreignKeys = [];

    /**
     * Add foreign key on column
     * @param string $foreignKey
     * @return Column
     */
    public function addForeignKey(string $foreignKey) : Column
    {
        $foreignKey = Utils::confirmName($foreignKey, ["."]);
        $setForeignException = new \LogicException("Foreign key needs to target another table. Use dot. (E.g. 'TableName.ColumnName')");
        $exploded = explode(".",$foreignKey);

        if(count($exploded) != 2)
            throw $setForeignException;

        if(strlen($exploded[0]) <= 0 || strlen($exploded[1]) <= 0)
            throw $setForeignException;

        if(in_array($foreignKey,$this->foreignKeys))
            throw new \InvalidArgumentException("Foreign key '".$foreignKey."' already exist on column '".$this->getName()."'!");

        $this->foreignKeys[] = $foreignKey;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getForeignKeys() : array
    {
        return $this->foreignKeys;
    }
    //endregion

    //region Comment
    /**
     * @var string|null
     */
    private $comment = 'AutoCreated with MkSQL';

    /**
     * Set or unset (with null) comment string for column
     * @param string|null $commentString
     * @return $this
     */
    public function setComment(?string $commentString): Column
    {
        $commentString = Utils::confirmName($commentString, [".", ",", " "]); //Allow dots, commas and spaces to form sentences :)
        $this->comment = $commentString;
        return $this;
    }

    /**
     * Returns string that was set as a comment.
     * @return string|null
     */
    public function getComment(): ?string
    {
        return $this->comment;
    }

    //endregion

    public function install(\Nette\Database\Connection $db, int $driverType, array $full_desc, array $full_indexes, array $full_keys) : array
    {
        $Commands = [];

        $description = $this->findMyDescription($full_desc,$driverType);
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

                $Commands[] = [
                    "reason" => "Creating column ".$this->getName().", because it doesnt exists!",
                    "sql" => trim($CreateSQL).';'
                ];

                //unique
                if($this->getUnique())
                {
                    $Commands[] = [
                        "reason" => "Column ".$this->getName()." created, but unique key required!",
                        "sql" => 'CREATE UNIQUE INDEX '.$this->parent->getName().'_'.$this->getName().'_mksql_uindex on '.$this->parent->getName().' ('.$this->getName().');'
                    ];
                }

                //foreign key
                if(count($this->getForeignKeys())>0)
                {
                    foreach($this->getForeignKeys() as $ForeignKey)
                    {
                        $foreignKeyParts = explode(".",$ForeignKey);
                        $targetTable = $foreignKeyParts[0];
                        $targetColumn = $foreignKeyParts[1];

                        $Commands[] = [
                            "reason" => "Column ".$this->getName()." created, but foreign key '".$ForeignKey."' required!",
                            "sql" => 'ALTER TABLE '.$this->parent->getName().' ADD CONSTRAINT '.$this->parent->getName().'_'.$targetTable.'_'.$this->getName().'_'.$targetColumn.'_mksql_fk
                         FOREIGN KEY ('.$this->getName().') REFERENCES '.$targetTable.' ('.$targetColumn.');'
                        ];
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
                if($description["Type"] !== $this->getType())
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
                $Comment = $description["Comment"] === "" ? null : $description["Comment"];
                if($Comment !== null)
                    $BaseAlterQuery .= " COMMENT '".$Comment."'";

                if($Comment != $this->getComment())
                    $useAlterQueryReasons[] = "Comment update required";

                $BaseAlterQuery .= ';';
                if(count($useAlterQueryReasons) > 0)
                {
                    $Commands[] = [
                        "reason" => implode(", ",$useAlterQueryReasons),
                        "sql" => $BaseAlterQuery
                    ];
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
                        $Commands[] = [
                            "reason" => "Column ".$this->getName()." needs unique index, but it doesn't have it!",
                            "sql" => 'CREATE UNIQUE INDEX '.$this->parent->getName().'_'.$this->getName().'_mksql_uindex on '.$this->parent->getName().' ('.$this->getName().');'
                        ];
                    }
                    else
                    {
                        //Remove all unique indexes
                        foreach($indexes as $index)
                            if($index["Non_unique"] === 0)
                                $Commands[] = [
                                    "reason" => "Removing unique index: ".$index["Key_name"],
                                    "sql" => 'DROP INDEX '.$index["Key_name"].' ON '.$this->parent->getName().';'
                                ];
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
                    $Commands[] = [
                        "reason" => "Key '".$keyToRemove."' is unwanted",
                        "sql" => "ALTER TABLE cards DROP FOREIGN KEY ".$keyToRemove
                    ];
                }

                foreach($keysToCreate as $keyToCreate)
                {
                    list($targetTable, $targetColumn) = explode(".",$keyToCreate);

                    $Commands[] = [
                        "reason" => "Foreign key '".$keyToCreate."' not found, creating...",
                        "sql" => 'ALTER TABLE '.$this->parent->getName().' ADD CONSTRAINT
                                 '.$this->parent->getName().'_'.$targetTable.'_'.$this->getName().'_'.$targetColumn.'_mksql_fk
                                 FOREIGN KEY ('.$this->getName().') REFERENCES '.$targetTable.' ('.$targetColumn.');'
                    ];
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
     */
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
     */
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
     */
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
    }

}
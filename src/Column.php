<?php
/*
 * Zrník.eu | MkSQL
 * User: Programátor
 * Date: 31.07.2020 9:38
 */


namespace Zrny\MkSQL;

use Zrny\MkSQL\Exceptions\InvalidArgumentException;
use Zrny\MkSQL\Queries\Tables\ColumnDescription;
use Zrny\MkSQL\Queries\Tables\TableDescription;

class Column
{
    /**
     * @var string
     */
    private string $type;

    /**
     * @var string
     */
    private string $name;

    /**
     * @var Table|null
     */
    private ?Table $parent = null;

    /**
     * @var array
     * @internal
     */
    public array $_parameters = [];

    /**
     * Column constructor.
     * @param string $columnName
     * @param string $columnType
     */
    public function __construct(string $columnName, string $columnType = "int")
    {
        $this->name = Utils::confirmColumnName($columnName);
        $this->type = Utils::confirmType($columnType);
    }



    //region Parent

    /**
     * Ends defining of column if using
     * the fluent way of creating the tables.
     *
     * @return Table|null
     */
    public function endColumn(): ?Table
    {
        return $this->parent;
    }

    /**
     * @param Table $parent
     */
    public function setParent(Table $parent)
    {
        $this->parent = $parent;
    }

    //endregion

    //region Getters

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

    //endregion

    //region Unique

    /**
     * @var bool
     */
    private bool $unique = false;

    /**
     * Sets column to be unique or not
     * @param bool $Unique
     * @return $this
     */
    public function setUnique(bool $Unique = true): Column
    {
        //Unique must be NotNull
        if($Unique)
            $this->setNotNull(true);

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
    private bool $NotNull = false;

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
     * @var string[]
     */
    private static array $_AllowedDefaultValues = [
        "boolean",
        "integer",
        "double", // float
        "string",
        "NULL",
    ];

    /**
     * Set or unset (with null) default value of column.
     * @param mixed|null $defaultValue
     * @return $this
     */
    public function setDefault($defaultValue = null): Column
    {
        $type = gettype($defaultValue);

        if(!in_array($type, static::$_AllowedDefaultValues))
            throw new InvalidArgumentException("Comment must be '".
                implode(", ",static::$_AllowedDefaultValues)."'. Got '" . $type . "' instead!");

        if($type === "string")
            $defaultValue = Utils::checkForbiddenWords($defaultValue);

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
    private array $foreignKeys = [];

    /**
     * Add foreign key on column
     * @param string $foreignKey
     * @return Column
     */
    public function addForeignKey(string $foreignKey): Column
    {
        $foreignKey = Utils::confirmForeignKeyTarget($foreignKey);

        if (in_array($foreignKey, $this->foreignKeys))
            throw new InvalidArgumentException("Foreign key '" . $foreignKey . "' already exist on column '" . $this->getName() . "'!");

        $this->foreignKeys[] = $foreignKey;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getForeignKeys(): array
    {
        return $this->foreignKeys;
    }
    //endregion

    //region Comment
    /**
     * @var string|null
     */
    private ?string $comment = null;

    /**
     * Set or unset (with null) comment string for column
     * @param string|null $commentString
     * @return $this
     */
    public function setComment(?string $commentString = null): Column
    {
        $this->comment = Utils::confirmComment($commentString);
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

    /**
     * @param TableDescription $tableDescription
     * @param ColumnDescription|null $columnDescription
     * @return array
     */
    public function install(TableDescription $tableDescription, ?ColumnDescription $columnDescription): array
    {
        $Commands = [];

        if($columnDescription === null || !$columnDescription->columnExists)
        {
            $Commands = array_merge($Commands, $tableDescription->queryMakerClass::createTableColumnQuery($tableDescription->table, $this, $tableDescription, $columnDescription));

            foreach($this->getForeignKeys() as $foreignKey)
                $Commands = array_merge($Commands, $tableDescription->queryMakerClass::createForeignKey($tableDescription->table, $this, $foreignKey, $tableDescription, $columnDescription));

            if($this->getUnique())
                $Commands = array_merge($Commands, $tableDescription->queryMakerClass::createUniqueIndexQuery($tableDescription->table, $this, $tableDescription, $columnDescription));
        }
        else
        {
            $Reasons = [];
            //Utils::typeEquals($desc->type, $this->getType())
            if(!$tableDescription->queryMakerClass::compareType($columnDescription->type, $this->getType()))
                $Reasons[] = "type different [".$columnDescription->type." != ".$this->getType()."]";

            if($columnDescription->notNull !== $this->getNotNull())
                $Reasons[] = "not_null [is: ".($columnDescription->notNull?"yes":"no")." need:".($this->getNotNull()?"yes":"no")."]";

            //$desc->comment != $this->getComment()
            if(!$tableDescription->queryMakerClass::compareComment($columnDescription->comment, $this->getComment()))
                $Reasons[] = "comment [".$columnDescription->comment." != ".$this->getComment()."]";

            if($columnDescription->default != $this->getDefault())
                $Reasons[] = "default [".$columnDescription->default." != ".$this->getDefault()."]";

            if(count($Reasons) > 0)
            {
                $Queries = $tableDescription->queryMakerClass::alterTableColumnQuery($columnDescription->table, $columnDescription->column, $tableDescription, $columnDescription);

                $reasons = 'Reasons: '.implode(", ",$Reasons);

                foreach($Queries as $alterQuery)
                    $alterQuery->setReason($reasons);

                $Commands = array_merge($Commands, $Queries);
            }

            //Foreign Keys to Delete:
            if(count($columnDescription->foreignKeys) > 0)
            {
                foreach($columnDescription->foreignKeys as $existingForeignKey => $foreignKeyName)
                {
                    if(!in_array($existingForeignKey,$this->getForeignKeys())
                    )
                    {
                        $Commands = array_merge($Commands, $tableDescription->queryMakerClass::removeForeignKey($columnDescription->table, $columnDescription->column, $foreignKeyName, $tableDescription, $columnDescription));
                    }
                }
            }

            //Foreign Keys to Add:
            foreach($this->getForeignKeys() as $requiredForeignKey)
            {
                if(!isset($columnDescription->foreignKeys[$requiredForeignKey]))
                {
                    $alterationCommands = $tableDescription->queryMakerClass::createForeignKey($columnDescription->table, $columnDescription->column, $requiredForeignKey, $tableDescription, $columnDescription);

                    $Commands = array_merge($Commands, $alterationCommands);
                }
            }

            // Unique?
            if($this->getUnique())
            {
                //Must be unique
                if($columnDescription->uniqueIndex === null)
                {
                    $Commands = array_merge($Commands, $tableDescription->queryMakerClass::createUniqueIndexQuery($columnDescription->table, $columnDescription->column, $tableDescription, $columnDescription));
                }
            }
            else
            {
                //Must not be unique
                if($columnDescription->uniqueIndex !== null)
                {
                    $Commands = array_merge($Commands, $tableDescription->queryMakerClass::removeUniqueIndexQuery($columnDescription->table, $columnDescription->column, $columnDescription->uniqueIndex, $tableDescription, $columnDescription));
                }
            }
        }
        return $Commands;
    }
}

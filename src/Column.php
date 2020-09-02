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
     * @var bool
     * @internal
     */
    public bool $column_handled = false;
    /**
     * @var bool
     * @internal
     */
    public bool $unique_index_handled = false;
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


    //region Parent
    /**
     * @var bool
     */
    private bool $unique = false;
    /**
     * @var bool
     */
    private bool $NotNull = false;

    //endregion

    //region Getters
    /**
     * @var mixed|null
     */
    private $default = null;
    /**
     * @var string[]
     */
    private array $foreignKeys = [];
    /**
     * @var string|null
     */
    private ?string $comment = null;


    //endregion

    //region Unique

    /**
     * Column constructor.
     * @param string $columnName
     * @param string $columnType
     */
    public function __construct(string $columnName, string $columnType = "int")
    {
        $this->name = Utils::confirmColumnName($columnName);
        $this->setType($columnType);
    }

    /**
     * @param Table $parent
     */
    public function setParent(Table $parent)
    {
        $this->parent = $parent;
    }

    /**
     * Add foreign key on column
     * @param string $foreignKey
     * @return Column
     * @throws InvalidArgumentException
     */
    public function addForeignKey(string $foreignKey): Column
    {
        $foreignKey = Utils::confirmForeignKeyTarget($foreignKey);

        list($_refTable, $_refColumn) = explode(".", $foreignKey);

        $referencedTable = $this->endColumn()->endTable()->tableGet($_refTable);
        if ($referencedTable === null)
            throw new InvalidArgumentException("Foreign key '" . $foreignKey . "' is referencing table '" . $_refTable . "' but that table is not defined!");

        $referencedColumn = $referencedTable->columnGet($_refColumn);
        if ($referencedColumn === null && $_refColumn !== $referencedTable->getPrimaryKeyName())
            throw new InvalidArgumentException("Foreign key '" . $foreignKey . "' is referencing column '" . $_refColumn . "' in table '" . $_refTable . "' but that column is not defined!");

        if (
            $_refTable === $this->endColumn()->getName()
            && $_refColumn === $this->getName()
        )
            throw new InvalidArgumentException("Foreign key of column '" . $foreignKey . "' cannot point to itself!");

        if (in_array($foreignKey, $this->foreignKeys))
            throw new InvalidArgumentException("Foreign key '" . $foreignKey . "' already exist on column '" . $this->getName() . "'!");

        $this->foreignKeys[] = $foreignKey;

        return $this;
    }
    //endregion

    //region NOT NULL

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
     * Returns column name.
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $foreignKey
     * @return Column
     */
    public function dropForeignKey(string $foreignKey): Column
    {
        if (($key = array_search($foreignKey, $this->foreignKeys)) !== false) {
            unset($this->foreignKeys[$key]);
        }
        return $this;
    }
    //endregion

    //region Default Value

    /**
     * @param TableDescription $tableDescription
     * @param ColumnDescription|null $columnDescription
     * @return array
     */
    public function install(TableDescription $tableDescription, ?ColumnDescription $columnDescription): array
    {
        $Commands = [];

        if ($columnDescription === null || !$columnDescription->columnExists) {
            $Commands = array_merge($Commands, $tableDescription->queryMakerClass::createTableColumnQuery($tableDescription->table, $this, $tableDescription, $columnDescription));

            foreach ($this->getForeignKeys() as $foreignKey)
                $Commands = array_merge($Commands, $tableDescription->queryMakerClass::createForeignKey($tableDescription->table, $this, $foreignKey, $tableDescription, $columnDescription));

            if ($this->getUnique())
                $Commands = array_merge($Commands, $tableDescription->queryMakerClass::createUniqueIndexQuery($tableDescription->table, $this, $tableDescription, $columnDescription));
        } else {
            $Reasons = [];

            //Utils::typeEquals($desc->type, $this->getType())
            if (!$tableDescription->queryMakerClass::compareType($columnDescription->type, $this->getType()))
                $Reasons[] = "type different [" . $columnDescription->type . " != " . $this->getType() . "]";

            if ($columnDescription->notNull !== $this->getNotNull())
                $Reasons[] = "not_null [is: " . ($columnDescription->notNull ? "yes" : "no") . " need:" . ($this->getNotNull() ? "yes" : "no") . "]";

            //$desc->comment != $this->getComment()
            if (!$tableDescription->queryMakerClass::compareComment($columnDescription->comment, $this->getComment()))
                $Reasons[] = "comment [" . $columnDescription->comment . " != " . $this->getComment() . "]";

            if ($columnDescription->default != $this->getDefault())
                $Reasons[] = "default [" . $columnDescription->default . " != " . $this->getDefault() . "]";

            if (count($Reasons) > 0) {
                $Queries = $tableDescription->queryMakerClass::alterTableColumnQuery($columnDescription->table, $columnDescription->column, $tableDescription, $columnDescription);

                $reasons = 'Reasons: ' . implode(", ", $Reasons);

                foreach ($Queries as $alterQuery)
                    $alterQuery->setReason($reasons);

                $Commands = array_merge($Commands, $Queries);
            }

            //Foreign Keys to Delete:
            if (count($columnDescription->foreignKeys) > 0) {
                foreach ($columnDescription->foreignKeys as $existingForeignKey => $foreignKeyName) {
                    if (!in_array($existingForeignKey, $this->getForeignKeys())) {
                        $Commands = array_merge($Commands, $tableDescription->queryMakerClass::removeForeignKey($columnDescription->table, $columnDescription->column, $foreignKeyName, $tableDescription, $columnDescription));
                    }
                }
            }

            //Foreign Keys to Add:
            foreach ($this->getForeignKeys() as $requiredForeignKey) {
                if (!isset($columnDescription->foreignKeys[$requiredForeignKey])) {
                    $alterationCommands = $tableDescription->queryMakerClass::createForeignKey($columnDescription->table, $columnDescription->column, $requiredForeignKey, $tableDescription, $columnDescription);

                    $Commands = array_merge($Commands, $alterationCommands);
                }
            }

            // Unique?
            if ($this->getUnique()) {
                //Must be unique
                if ($columnDescription->uniqueIndex === null) {
                    $Commands = array_merge($Commands, $tableDescription->queryMakerClass::createUniqueIndexQuery($columnDescription->table, $columnDescription->column, $tableDescription, $columnDescription));
                }
            } else {
                //Must not be unique
                if ($columnDescription->uniqueIndex !== null) {
                    $Commands = array_merge($Commands, $tableDescription->queryMakerClass::removeUniqueIndexQuery($columnDescription->table, $columnDescription->column, $columnDescription->uniqueIndex, $tableDescription, $columnDescription));
                }
            }
        }
        return $Commands;
    }

    /**
     * @return string[]
     */
    public function getForeignKeys(): array
    {
        return $this->foreignKeys;
    }

    /**
     * Is column unique?
     * @return bool
     */
    public function getUnique(): bool
    {
        return $this->unique;
    }

    /**
     * Sets column to be unique or not
     * @param bool $Unique
     * @return $this
     */
    public function setUnique(bool $Unique = true): Column
    {
        //Unique must be NotNull
        if ($Unique)
            $this->setNotNull(true);

        $this->unique = $Unique;

        return $this;
    }
    //endregion

    //region Foreign Keys

    /**
     * Returns column type.
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Returns column type.
     * @param string $columnType
     * @return Column
     */
    public function setType(string $columnType): Column
    {
        $this->type = Utils::confirmType($columnType);
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
    //endregion

    //region Comment

    /**
     * Returns string that was set as a comment.
     * @return string|null
     */
    public function getComment(): ?string
    {
        return $this->comment;
    }

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
     * @return mixed|null
     */
    public function getDefault()
    {
        return $this->default;
    }

    //endregion

    /**
     * Set or unset (with null) default value of column.
     * @param mixed|null $defaultValue
     * @return $this
     */
    public function setDefault($defaultValue = null): Column
    {
        $type = gettype($defaultValue);

        if (!in_array($type, static::$_AllowedDefaultValues))
            throw new InvalidArgumentException("Comment must be '" .
                implode(", ", static::$_AllowedDefaultValues) . "'. Got '" . $type . "' instead!");

        if ($type === "string")
            $defaultValue = Utils::checkForbiddenWords($defaultValue);

        $this->default = $defaultValue;
        return $this;
    }
}

<?php declare(strict_types=1);
/*
 * Zrník.eu | MkSQL
 * User: Programátor
 * Date: 31.07.2020 9:38
 */


namespace Zrnik\MkSQL;

use Zrnik\MkSQL\Exceptions\InvalidArgumentException;
use Zrnik\MkSQL\Queries\Tables\ColumnDescription;
use Zrnik\MkSQL\Queries\Tables\TableDescription;

class Column
{

    public function __construct(string $columnName, string $columnType = "int")
    {
        $this->name = Utils::confirmColumnName($columnName);
        $this->setType($columnType);
    }

    //region Helping Properties

    // TODO: Read Below:
    // This should be something like $this->props[] because its only used by SQLite.
    // Altering column in SQLite requires creating a temp table, move data, remove
    // real table, and then rename the temp name to real name.
    //
    // If it alters one column, it actually alters all of them, so we don't need to do it
    // again and this region's variables are preventing it in 'Zrnik\MkSQL\Queries\Makers\QueryMakerSQLite'.

    /**
     * @internal
     */
    public bool $column_handled = false;
    /**
     * @internal
     */
    public bool $unique_index_handled = false;
    //endregion

    //region Parent
    private ?Table $parent = null;

    /**
     * Sets a parent table.
     *
     * @param Table $parent
     * @internal
     */
    public function setParent(Table $parent): void
    {
        if($parent === null)
            throw new \LogicException(
                "Parent cannot be null!"
            );

        if ($this->parent !== null)
            throw new \LogicException(
                "Column '" . $this->getName() . "' already has a parent '" . $this->getParent()->getName() . "', consider cloning!"
            );

        $this->parent = $parent;
    }

    /**
     * Returns a table that contains this column.
     */
    public function getParent(): ?Table
    {
        return $this->parent;
    }

    /**
     * Ends defining of column if using
     * the fluent way of creating the tables.
     *
     * It's alias of 'getParent()' method.
     */
    public function endColumn(): ?Table
    {
        return $this->getParent();
    }
    //endregion

    //region Column Name
    private string $name;

    /**
     * Returns a name of the column.
     */
    public function getName(): string
    {
        return $this->name;
    }

    //endregion

    //region Type
    private string $type;

    /**
     * Returns a type of the column.
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
    //endregion

    //region NotNull
    private bool $NotNull = false;

    /**
     * Set if column should be 'NOT NULL' or 'NULL'
     * @param bool $notNull
     * @return Column
     */
    public function setNotNull(bool $notNull = true): Column
    {
        $this->NotNull = $notNull;
        return $this;
    }

    /**
     * Is column not null?
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
     * Allowed types of default values.
     *
     * @var string[]
     */
    private static array $_AllowedDefaultValues = [
        "boolean", "integer",
        "double", // float
        "string", "NULL"
    ];

    /**
     * Set or unset (with null) default value of column.
     * @param mixed|null $defaultValue
     * @return $this
     */
    public function setDefault($defaultValue = null): Column
    {
        $type = gettype($defaultValue);

        if (!in_array($type, static::$_AllowedDefaultValues))
            throw new InvalidArgumentException("Comment must be one of '" .
                implode(", ", static::$_AllowedDefaultValues) . "'. Got '" . $type . "' instead!");

        if ($type === "string")
            $defaultValue = Utils::checkForbiddenWords($defaultValue);

        $this->default = $defaultValue;
        return $this;
    }

    /**
     * Gets a default value.
     *
     * @return mixed|null
     */
    public function getDefault()
    {
        return $this->default;
    }
    //endregion

    //region Column Comment
    /**
     * @var string|null
     */
    private ?string $comment = null;

    /**
     * Returns string that was set as a comment.
     * @return string|null
     */
    public function getComment(): ?string
    {
        return $this->comment;
    }

    /**
     * Set or unset comment string for column
     *
     * Use null for no comment
     * Empty argument is also null
     *
     * @param string|null $commentString
     * @return $this
     */
    public function setComment(?string $commentString = null): Column
    {
        $this->comment = Utils::confirmComment($commentString);
        return $this;
    }
    //endregion

    //region Unique Index
    private bool $unique = false;

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
        //Unique must be NotNull?
        if ($Unique)
            $this->setNotNull(true);

        $this->unique = $Unique;

        return $this;
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
     * @param bool $isUpdatedWithThisUpdater
     * @return Column
     */
    //TODO: Allow defining of 'ON DELETE'/'ON UPDATE' behavior , see:
    //      https://www.techonthenet.com/sql_server/foreign_keys/foreign_delete.php
    public function addForeignKey(string $foreignKey, bool $isUpdatedWithThisUpdater = true): Column
    {
        $foreignKey = Utils::confirmForeignKeyTarget($foreignKey);

        list($_refTable, $_refColumn) = explode(".", $foreignKey);

        if ($isUpdatedWithThisUpdater) {
            $referencedTable = $this->getParent()->endTable()->tableGet($_refTable);
            if ($referencedTable === null)
                throw new InvalidArgumentException("Foreign key '" . $foreignKey . "' is referencing table '" . $_refTable . "' but that table is not defined!");

            $referencedColumn = $referencedTable->columnGet($_refColumn);
            if ($referencedColumn === null && $_refColumn !== $referencedTable->getPrimaryKeyName())
                throw new InvalidArgumentException("Foreign key '" . $foreignKey . "' is referencing column '" . $_refColumn . "' in table '" . $_refTable . "' but that column is not defined!");
        }

        if (in_array($foreignKey, $this->foreignKeys))
            throw new InvalidArgumentException("Foreign key '" . $foreignKey . "' already exist on column '" . $this->getName() . "'!");

        $this->foreignKeys[] = $foreignKey;

        return $this;
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

    /**
     * @return string[]
     */
    public function getForeignKeys(): array
    {
        return $this->foreignKeys;
    }
    //endregion

    //region INSTALLATION
    /**
     * Method for installing a column
     *
     * @param TableDescription $tableDescription
     * @param ColumnDescription|null $columnDescription
     * @return array
     * @internal
     *
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
    //endregion

    //region Handle Cloning
    /**
     * As the cloning is required to put one column to more tables,
     * we need to remove the parent before the act of cloning!
     */
    public function __clone()
    {
        $this->parent = null;
    }
    //endregion
}

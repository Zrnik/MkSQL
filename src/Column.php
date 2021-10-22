<?php /** @noinspection PropertyInitializationFlawsInspection */
declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Zrnik\MkSQL;

use JetBrains\PhpStorm\Pure;
use LogicException;
use ReflectionClass;
use Zrnik\MkSQL\Exceptions\InvalidArgumentException;
use Zrnik\MkSQL\Queries\Makers\IQueryMaker;
use Zrnik\MkSQL\Queries\Query;
use Zrnik\MkSQL\Queries\Tables\ColumnDescription;
use Zrnik\MkSQL\Queries\Tables\TableDescription;
use function count;
use function gettype;
use function in_array;

class Column
{
    /**
     * Column constructor.
     * @param string $columnName
     * @param string|null $columnType
     * @throws InvalidArgumentException
     * @noinspection ParameterDefaultValueIsNotNullInspection
     */
    public function __construct(string $columnName, ?string $columnType = 'int')
    {
        if ($columnType === null) {
            $columnType = 'int';
        }

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
        if ($this->parent !== null) {
            throw new LogicException(
                "Column '" . $this->getName() . "' already has a parent '" . $this->getParent()?->getName() . "', consider cloning!"
            );
        }

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
    #[Pure]
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
     * @throws InvalidArgumentException
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
    private mixed $default = null;

    /**
     * Allowed types of default values.
     *
     * @var string[]
     */
    private static array $_AllowedDefaultValues = [
        'boolean', 'integer',
        'double', // float
        'string', 'NULL'
    ];

    /**
     * Set or unset (with null) default value of column.
     * @param mixed|null $defaultValue
     * @return $this
     * @throws InvalidArgumentException
     */
    public function setDefault(mixed $defaultValue = null): Column
    {
        $type = gettype($defaultValue);

        if (!in_array($type, self::$_AllowedDefaultValues)) {
            throw new InvalidArgumentException(
                sprintf(
                    "Comment must be one of '%s'. Got '%s' instead!",
                    implode(', ', self::$_AllowedDefaultValues),
                    $type
                )
            );
        }

        if ($type === 'string') {
            $defaultValue = Utils::checkForbiddenWords($defaultValue);
        }

        $this->default = $defaultValue;
        return $this;
    }

    /**
     * Gets a default value.
     *
     * @return mixed
     */
    public function getDefault(): mixed
    {
        return $this->default ?? null;
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
     * @throws InvalidArgumentException
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
        if ($this->unique !== $Unique) {
            $this->unique_index_handled = false;
        }

        //Unique must be NotNull?
        if ($Unique) {
            $this->setNotNull();
        }

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
     * @return Column
     * @throws InvalidArgumentException
     */
    //TODO: Allow defining of 'ON DELETE'/'ON UPDATE' behavior , see:
    //      https://www.techonthenet.com/sql_server/foreign_keys/foreign_delete.php
    public function addForeignKey(string $foreignKey): Column
    {
        $foreignKey = Utils::confirmForeignKeyTarget($foreignKey);

        if (in_array($foreignKey, $this->foreignKeys, true)) {
            throw new InvalidArgumentException("Foreign key '" . $foreignKey . "' already exist on column '" . $this->getName() . "'!");
        }

        $this->foreignKeys[] = $foreignKey;

        return $this;
    }

    /**
     * @param string $foreignKey
     * @return Column
     */
    public function dropForeignKey(string $foreignKey): Column
    {
        /** @var string|false $key */
        $key = array_search($foreignKey, $this->foreignKeys, true);

        if ($key !== false) {
            unset($this->foreignKeys[(string)$key]);
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
     * @return Query[]
     * @internal
     * @noinspection PhpFunctionCyclomaticComplexityInspection
     */
    public function install(TableDescription $tableDescription, ?ColumnDescription $columnDescription): array
    {
        $Commands = [];

        /**
         * @var IQueryMaker $queryMaker
         */
        $queryMaker = $tableDescription->queryMakerClass;

        if ($columnDescription === null || !$columnDescription->columnExists) {
            $Commands = array_merge($Commands, $queryMaker::createTableColumnQuery($tableDescription->table, $this, $tableDescription, $columnDescription) ?? []);

            foreach ($this->getForeignKeys() as $foreignKey) {
                $newCommands = $queryMaker::createForeignKey($tableDescription->table, $this, $foreignKey, $tableDescription, $columnDescription) ?? [];
                foreach ($newCommands as $newCommand) {
                    $Commands[] = $newCommand;
                }
            }

            if ($this->getUnique()) {
                $newCommands = $queryMaker::createUniqueIndexQuery($tableDescription->table, $this, $tableDescription, $columnDescription) ?? [];
                foreach ($newCommands as $newCommand) {
                    $Commands[] = $newCommand;
                }
            }
        } else {
            $Reasons = [];

            //Utils::typeEquals($desc->type, $this->getType())
            if (!$queryMaker::compareType($columnDescription->type, $this->getType())) {
                $Reasons[] = 'type different [' . $columnDescription->type . ' != ' . $this->getType() . ']';
            }

            if ($columnDescription->notNull !== $this->getNotNull()) {
                $Reasons[] = 'not_null [is: ' . ($columnDescription->notNull ? 'yes' : 'no') . ' need:' . ($this->getNotNull() ? 'yes' : 'no') . ']';
            }

            //$desc->comment != $this->getComment()
            if (!$queryMaker::compareComment($columnDescription->comment, $this->getComment())) {
                $Reasons[] = 'comment [' . $columnDescription->comment . ' != ' . $this->getComment() . ']';
            }

            if ($columnDescription->default !== $this->getDefault()) {
                $Reasons[] = 'default [' . $columnDescription->default . ' !== ' . $this->getDefault() . ']';
            }

            if (count($Reasons) > 0) {
                $Queries = $queryMaker::alterTableColumnQuery($columnDescription->table, $columnDescription->column, $tableDescription, $columnDescription) ?? [];

                $reasons = 'Reasons: ' . implode(', ', $Reasons);

                foreach ($Queries as $alterQuery) {
                    $alterQuery->setReason($reasons);
                }

                $Commands = array_merge($Commands, $Queries);
            }

            //Foreign Keys to Delete:
            if (count($columnDescription->foreignKeys) > 0) {
                foreach ($columnDescription->foreignKeys as $existingForeignKey => $foreignKeyName) {
                    if (!in_array($existingForeignKey, $this->getForeignKeys(), true)) {
                        $rfkCommands = $queryMaker::removeForeignKey($columnDescription->table, $columnDescription->column, $foreignKeyName, $tableDescription, $columnDescription) ?? [];
                        foreach ($rfkCommands as $rfkCommand) {
                            $Commands[] = $rfkCommand;
                        }
                    }
                }
            }

            //Foreign Keys to Add:
            foreach ($this->getForeignKeys() as $requiredForeignKey) {
                if (!isset($columnDescription->foreignKeys[$requiredForeignKey])) {
                    $alterationCommands = $queryMaker::createForeignKey($columnDescription->table, $columnDescription->column, $requiredForeignKey, $tableDescription, $columnDescription) ?? [];
                    foreach ($alterationCommands as $command) {
                        $Commands[] = $command;
                    }
                }
            }

            // Unique?
            if ($this->getUnique()) {
                //Must be unique
                if ($columnDescription->uniqueIndex === null) {

                    $createUniqueIndexQueries = $queryMaker::createUniqueIndexQuery(
                            $columnDescription->table, $columnDescription->column,
                            $tableDescription, $columnDescription
                        ) ?? [];

                    foreach ($createUniqueIndexQueries as $command) {
                        $Commands[] = $command;
                    }
                }

            } else if ($columnDescription->uniqueIndex !== null) {
                $Commands = array_merge(
                    $Commands,
                    $queryMaker::removeUniqueIndexQuery(
                        $columnDescription->table, $columnDescription->column,
                        $columnDescription->uniqueIndex, $tableDescription,
                        $columnDescription
                    ) ?? []
                );
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

    /**
     * @return array<string, mixed>
     */
    public function getHashData(): array
    {
        $hashData = [];
        $reflection = new ReflectionClass($this);
        foreach ($reflection->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }
            $propName = $property->getName();
            $propValue = $this->$propName ?? 'null';

            if (!is_scalar($propValue)) {
                continue;
            }

            $hashData[$propName] = $propValue;
        }

        return $hashData;
    }

}

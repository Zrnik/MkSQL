<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Zrnik\MkSQL;

use JetBrains\PhpStorm\Pure;
use Zrnik\MkSQL\Exceptions\ColumnDefinitionExists;
use Zrnik\MkSQL\Exceptions\InvalidArgumentException;
use Zrnik\MkSQL\Exceptions\PrimaryKeyAutomaticException;
use Zrnik\MkSQL\Queries\Query;
use Zrnik\MkSQL\Queries\Tables\TableDescription;
use Zrnik\MkSQL\Tracy\Measure;


class Table
{
    /**
     * Table constructor.
     * @param string $tableName
     * @throws InvalidArgumentException
     */
    public function __construct(string $tableName)
    {
        $this->tableName = Utils::confirmTableName($tableName);
    }

    //region Parent

    /**
     * @var Updater|null
     */
    private ?Updater $parent = null;

    /**
     * Sets a parent updater for this table,
     * used internally from 'Updater' class.
     *
     * @param Updater $parent
     * @internal
     */
    public function setParent(Updater $parent): void
    {
        $this->parent = $parent;
    }

    /**
     * Returns a parent 'Updater'.
     *
     * @internal
     */
    public function getParent(): ?Updater
    {
        return $this->parent;
    }

    /**
     * Ends defining of table if using
     * the fluent way of creating the tables.
     *
     * It's alias of 'getParent'
     *
     * @return ?Updater
     */
    #[Pure]
    public function endTable(): ?Updater
    {
        return $this->getParent();
    }
    //endregion

    //region Name
    private string $tableName;

    /**
     * Returns name of the table.
     * The result is already checked and corrected in constructor.
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->tableName;
    }
    //endregion

    //region Primary Key
    private string $primaryKeyName = 'id';

    /**
     * Returns a primary key name
     */
    public function getPrimaryKeyName(): string
    {
        return $this->primaryKeyName;
    }

    /**
     * @param string $newPrimaryKeyName
     * @return $this
     * @throws InvalidArgumentException
     */
    public function setPrimaryKeyName(string $newPrimaryKeyName): Table
    {
        $oldPrimaryKeyName = $this->primaryKeyName;
        $this->primaryKeyName = Utils::confirmColumnName($newPrimaryKeyName);

        // If we got a parent Updater, we need to find all references to this table
        // and replace foreign keys to still point to this table...

        $parent = $this->getParent();

        $parent?->updateForeignKeys($this, $oldPrimaryKeyName);

        return $this;
    }


    private string $primaryKeyType = 'int';

    /**
     * Returns a primary key name
     */
    public function getPrimaryKeyType(): string
    {
        return $this->primaryKeyType;
    }

    /**
     * @param string $newPrimaryKeyType
     * @return $this
     * @throws InvalidArgumentException
     */
    public function setPrimaryKeyType(string $newPrimaryKeyType): Table
    {
        $oldPrimaryKeyType = $this->primaryKeyType;
        $this->primaryKeyType = Utils::confirmType($newPrimaryKeyType);

        // If I have a parent Updater, I need to find all references to this table
        // and replace foreign keys to still point to this table...

        $parent = $this->getParent();

        $parent?->updateForeignKeys($this, $oldPrimaryKeyType);

        return $this;
    }
    //endregion

    //region Columns
    /**
     * @var Column[]
     */
    private array $columns = [];

    /**
     * @param string $columnName
     * @param string|null $columnType
     * @param bool $rewrite
     * @return Column
     * @throws ColumnDefinitionExists
     * @throws PrimaryKeyAutomaticException
     * @throws InvalidArgumentException
     * @noinspection ParameterDefaultValueIsNotNullInspection
     */
    public function columnCreate(
        string  $columnName,
        ?string $columnType = 'int',
        bool    $rewrite = false,
    ): Column
    {
        $column = new Column($columnName, $columnType);
        return $this->columnAdd($column, $rewrite);
    }

    public function columnCreateForeign(string $columnName, Table $targetTable): Column
    {
        return $this->columnCreate(
            $columnName, $targetTable->getPrimaryKeyType()
        )
            ->addForeignKey(
                sprintf(
                    '%s.%s',
                    $targetTable->getName(),
                    $targetTable->getPrimaryKeyName(),
                )
            );
    }

    /**
     * @param Column $column
     * @param bool $rewrite
     * @return Column
     * @throws ColumnDefinitionExists
     * @throws PrimaryKeyAutomaticException
     * @throws InvalidArgumentException
     */
    public function columnAdd(Column $column, bool $rewrite = false): Column
    {
        if ($column->getName() === $this->getPrimaryKeyName()) {
            throw new PrimaryKeyAutomaticException("Primary, auto incrementing key '" . $this->getPrimaryKeyName() . "' is created automatically.");
        }

        if (!$rewrite && isset($this->columns[$column->getName()])) {
            throw new ColumnDefinitionExists("Column '" . $column->getName() . "' already defined in table '" . $this->getName() . "'.");
        }

        if ($column->getName() === $this->getName()) {
            throw new InvalidArgumentException("Column name '" . $column->getName() . "' cannot be same as table name '" . $this->getName() . "'.");
        }

        $column->setParent($this);

        // setParent can fail, we don't want to add the
        // column when that happen, so we need to have it below!
        $this->columns[$column->getName()] = $column;

        return $column;
    }

    /**
     * @return Column[]
     */
    public function columnList(): array
    {
        return $this->columns;
    }

    /**
     * @param string $columnName
     * @return Column|null
     */
    public function columnGet(string $columnName): ?Column
    {
        return $this->columns[$columnName] ?? null;
    }
    //endregion

    //region Install
    /**
     * @param TableDescription $desc
     * @return Query[]
     */
    public function install(TableDescription $desc): array
    {

        $commands = [];

        if (!$desc->tableExists) {
            $createTableCommands = $desc->queryMakerClass::createTableQuery($this, $desc) ?? [];
            foreach ($createTableCommands as $createTableCommand) {
                $commands[] = $createTableCommand;
            }
        }

        if ($desc->tableExists && $desc->primaryKeyName !== $this->getPrimaryKeyName()) {
            $newCommands = $desc->queryMakerClass::changePrimaryKeyQuery(
                    $desc->primaryKeyName,
                    $this, $desc
                ) ?? [];

            foreach ($newCommands as $newCommand) {
                $commands[] = $newCommand;
            }
        }

        foreach ($this->columns as $column) {
            $column->column_handled = false;
        }

        Measure::reportStructureTable($this);
        foreach ($this->columns as $column) {

            Measure::reportStructureColumn($this, $column);

            foreach ($column->install($desc, $desc->columnGet($column->getName())) as $command) {
                $commands[] = $command;
            }
        }

        return $commands;
    }

    //endregion
    public function getHashKey(): string
    {
        $hashData = [];

        foreach ($this->columnList() as $column) {
            $hashData[$column->getName()] = $column->getHashData();
        }

        return hash('sha256', (string)var_export($hashData, true));
    }


}

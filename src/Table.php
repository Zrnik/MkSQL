<?php declare(strict_types=1);
/*
 * Zrník.eu | MkSQL
 * User: Programátor
 * Date: 31.07.2020 9:37
 */

namespace Zrnik\MkSQL;

use Zrnik\MkSQL\Exceptions\ColumnDefinitionExists;
use Zrnik\MkSQL\Exceptions\InvalidArgumentException;
use Zrnik\MkSQL\Exceptions\PrimaryKeyAutomaticException;
use Zrnik\MkSQL\Queries\Query;
use Zrnik\MkSQL\Queries\Tables\TableDescription;
use Zrnik\MkSQL\Tracy\Metrics;

class Table
{
    /**
     * Table constructor.
     * @param string $tableName
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
    public function setParent(Updater $parent)
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
     * @return Updater
     */
    public function endTable(): Updater
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
    public function getName(): string
    {
        return $this->tableName;
    }
    //endregion

    //region Primary Key
    private string $primaryKeyName = "id";

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
     */
    public function setPrimaryKeyName(string $newPrimaryKeyName): Table
    {
        $oldPrimaryKeyName = $this->primaryKeyName;
        $this->primaryKeyName = Utils::confirmColumnName($newPrimaryKeyName);

        // If i have parent a parent Updater, i need to find all references to this table
        // and replace foreign keys to still point to this table...
        //
        // its mainly for integration test...

        $parent = $this->getParent();

        if($parent !== null)
            $parent->updateForeignKeys($this, $oldPrimaryKeyName);

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
     */
    public function columnCreate(string $columnName, ?string $columnType = "int", bool $rewrite = false): Column
    {
        $column = new Column($columnName, $columnType);
        return $this->columnAdd($column, $rewrite);
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
        if ($column->getName() === $this->getPrimaryKeyName())
            throw new PrimaryKeyAutomaticException("Primary, auto incrementing key '" . $this->getPrimaryKeyName() . "' is created automatically.");

        if (!$rewrite && isset($this->columns[$column->getName()]))
            throw new ColumnDefinitionExists("Column '" . $column->getName() . "' already defined in table '" . $this->getName() . "'.");

        if ($column->getName() == $this->getName())
            throw new InvalidArgumentException("Column name '" . $column->getName() . "' cannot be same as table name '" . $this->getName() . "'.");

        $column->setParent($this);

        // setParent can fail, we dont want to add the
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
        if (isset($this->columns[$columnName]))
            return $this->columns[$columnName];

        return null;
    }
    //endregion

    //region Install
    /**
     * @param TableDescription $desc
     * @return Query[]
     */
    public function install(TableDescription $desc): array
    {
        $Commands = [];

        if (!$desc->tableExists)
            $Commands = array_merge($Commands, $desc->queryMakerClass::createTableQuery($this, $desc));

        if ($desc->tableExists) {
            if ($desc->primaryKeyName !== $this->getPrimaryKeyName()) {
                $Commands = array_merge($Commands, $desc->queryMakerClass::changePrimaryKeyQuery(
                    $desc->primaryKeyName,
                    $this, $desc
                ));
            }
        }


        foreach ($this->columns as $column) {
            $column->column_handled = false;
        }

        foreach ($this->columns as $column) {
            $Commands = array_merge($Commands, $column->install($desc, $desc->columnGet($column->getName())));
            Metrics::logStructure($this, $column);
        }

        return $Commands;
    }
    //endregion
}

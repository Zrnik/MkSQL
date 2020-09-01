<?php
/*
 * Zrník.eu | MkSQL
 * User: Programátor
 * Date: 31.07.2020 9:37
 */

namespace Zrny\MkSQL;

use Zrny\MkSQL\Exceptions\ColumnDefinitionExists;
use Zrny\MkSQL\Exceptions\PrimaryKeyAutomaticException;
use Zrny\MkSQL\Queries\Query;
use Zrny\MkSQL\Queries\Tables\TableDescription;
use Zrny\MkSQL\Tracy\Metrics;

class Table
{
    /**
     * @var Updater|null
     */
    private ?Updater $parent;

    /**
     * @var string
     */
    private string $tableName;

    /**
     * @var Column[]
     */
    private array $columns = [];

    /**
     * @var array
     * @internal
     */
    public array $_parameters = [];

    /**
     * Table constructor.
     * @param string $tableName
     */
    public function __construct(string $tableName)
    {
        $this->tableName =  Utils::confirmTableName($tableName);
    }

    //region Parent

    /**
     * Ends defining of table if using
     * the fluent way of creating the tables.
     *
     * @return Updater
     */
    public function endTable(): Updater
    {
        return $this->parent;
    }

    /**
     * @param Updater $parent
     */
    public function setParent(Updater $parent)
    {
        $this->parent = $parent;
    }

    //endregion

    //region Getters

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

    //region Columns

    /**
     * @param string $columnName
     * @param string|null $columnType
     * @param bool $rewrite
     * @return Column
     * @throws ColumnDefinitionExists
     * @throws PrimaryKeyAutomaticException
     */
    public function columnCreate(string $columnName, ?string $columnType = "int", bool $rewrite = false) : Column
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
     */
    public function columnAdd(Column $column, bool $rewrite = false) : Column
    {
        if($column->getName() === "id")
            throw new PrimaryKeyAutomaticException("Primary, auto incrementing key 'id' is created automatically.");

        if(!$rewrite && isset($this->columns[$column->getName()]))
            throw new ColumnDefinitionExists("Column '".$column->getName()."' already defined in table '".$this->getName()."'.");

        $this->columns[$column->getName()] = $column;
        $column->setParent($this);
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
    public function columnGet(string $columnName) : ?Column
    {
        if(isset($this->columns[$columnName]))
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

        foreach ($this->columns as $column) {
            $Commands = array_merge($Commands, $column->install($desc, $desc->columnGet($column->getName())));
            Metrics::logStructure($this, $column);
        }

        return $Commands;
    }
    //endregion


}

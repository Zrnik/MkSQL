<?php
/*
 * Zrník.eu | MkSQL  
 * User: Programátor
 * Date: 31.07.2020 9:37
 */

namespace Zrny\MkSQL;

use LogicException;
use Zrny\MkSQL\Nette\Metrics;
use Zrny\MkSQL\Queries\Query;
use Zrny\MkSQL\Queries\Tables\TableDescription;

class Table
{
    /**
     * @var Updater
     */
    private $parent;

    /**
     * @var string
     */
    private $tableName;

    /**
     * @var Column[]
     */
    private $columns = [];

    /**
     * Table constructor.
     * @param string $tableName
     * @param Updater $parent
     */
    public function __construct(string $tableName, Updater $parent)
    {
        $tableName = Utils::confirmName($tableName);
        $this->tableName = $tableName;
        $this->parent = $parent;
    }

    /**
     * Returns name of the table.
     * @return string|null
     */
    public function getName(): string
    {
        return $this->tableName;
    }

    /**
     * @return Column[]
     */
    public function getColumns(): array
    {
        return $this->columns;
    }


    /**
     * Creates a table column
     * @param string $colName
     * @param string|null $colType
     * @return Column
     */
    public function column(string $colName, ?string $colType = "int"): Column
    {
        $colName = Utils::confirmName($colName);
        $colType = Utils::confirmName($colType, ["(", ")", ","]);

        if ($colName === "id")
            throw new \InvalidArgumentException("You cannot redeclare primary column 'id' in table '" . $this->tableName . "'.");

        if (!isset($this->columns[$colName]))
            $this->columns[$colName] = new Column($colName, $this, $colType);

        if ($this->columns[$colName]->getType() !== $colType)
            throw new LogicException("Cannot redeclare column '" . $colName . "' with type '" . $this->columns[$colName]->getType() . "' as '" . $colType . "'!");

        return $this->columns[$colName];
    }

    /**
     * Ends defining of table.
     * @return Updater
     */
    public function endTable(): Updater
    {
        return $this->parent;
    }

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
            $Commands = array_merge($Commands, $column->install($desc, $desc->column($column->getName())));
            Metrics::logStructure($this, $column);
        }

        return $Commands;
    }
}
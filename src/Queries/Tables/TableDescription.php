<?php
/*
 * Zrník.eu | MkSQL
 * User: Programátor
 * Date: 06.08.2020 7:55
 */

namespace Zrny\MkSQL\Queries\Tables;

use Zrny\MkSQL\Queries\Makers\IQueryMaker;
use Zrny\MkSQL\Table;

class TableDescription
{
    /**
     * @var IQueryMaker
     */
    public $queryMakerClass = '';

    /**
     * @var bool
     */
    public bool $tableExists = false;

    /**
     * @var ColumnDescription[]
     */
    public array $columns = [];

    /**
     * @var Table
     */
    public Table $table;

    /**
     * @var array
     * @internal
     */
    public array $_parameters = [];

    /**
     * @param string $getName
     * @return ColumnDescription|null
     */
    public function column(string $getName) : ?ColumnDescription
    {
        $desc = null;

        foreach($this->columns as $column)
            if($column->column->getName() === $getName)
                return $column;

        return null;
    }
}

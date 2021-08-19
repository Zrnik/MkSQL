<?php
/*
 * Zrník.eu | MkSQL
 * User: Programátor
 * Date: 06.08.2020 7:55
 */

namespace Zrnik\MkSQL\Queries\Tables;

use JetBrains\PhpStorm\Pure;
use Zrnik\MkSQL\Queries\Makers\IQueryMaker;
use Zrnik\MkSQL\Table;

class TableDescription
{
    /** @var string|IQueryMaker */
    public string|IQueryMaker $queryMakerClass = '';

    public bool $tableExists = false;

    /**
     * @var string
     */
    public string $primaryKeyName = 'id';

    /**
     * @var ColumnDescription[]
     */
    public array $columns = [];

    /**
     * @var Table
     */
    public Table $table;

    /**
     * @param string $getName
     * @return ColumnDescription|null
     */
    #[Pure]
    public function columnGet(string $getName): ?ColumnDescription
    {
        foreach ($this->columns as $column) {
            if ($column->column->getName() === $getName) {
                return $column;
            }
        }
        return null;
    }
}

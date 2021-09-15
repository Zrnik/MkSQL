<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
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

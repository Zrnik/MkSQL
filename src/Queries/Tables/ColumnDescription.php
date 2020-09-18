<?php
/*
 * Zrník.eu | MkSQL
 * User: Programátor
 * Date: 06.08.2020 7:55
 */

namespace Zrnik\MkSQL\Queries\Tables;

use Zrnik\MkSQL\Column;
use Zrnik\MkSQL\Table;

class ColumnDescription
{
    /**
     * @var bool
     */
    public bool $columnExists = false;

    /**
     * @var Column
     */
    public Column $column;

    /**
     * @var Table
     */
    public Table $table;

    /**
     * @var bool
     */
    public bool $notNull = false;

    /**
     * @var string|null
     */
    public ?string $comment = null;

    /**
     * @var string|null
     */
    public ?string $uniqueIndex = null;

    /**
     * @var string[]
     */
    public array $foreignKeys = [];

    /**
     * @var mixed
     */
    public $default = null;

    /**
     * @var string
     */
    public string $type = '';
}

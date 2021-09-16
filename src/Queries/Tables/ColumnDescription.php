<?php /** @noinspection PropertyInitializationFlawsInspection */
declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
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
     * @var mixed|null
     */
    public mixed $default = null;

    /**
     * @var string
     */
    public string $type = '';
}

<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Zrnik\MkSQL\Queries;

use Zrnik\MkSQL\Table;

class QueryInfo
{
    public bool $isExecuted = false;
    public bool $isSuccess = false;

    public ?string $errorText = null;

    public string $querySql = '';

    public float $executionSpeed = 0;

    public ?Table $referencedTable = null;

}

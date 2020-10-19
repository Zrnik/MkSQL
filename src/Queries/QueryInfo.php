<?php declare(strict_types=1);
/*
 * Zrník.eu | AgronaroWebsite  
 * User: Programátor
 * Date: 19.10.2020 9:17
 */


namespace Zrnik\MkSQL\Queries;


use Zrnik\MkSQL\Column;
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
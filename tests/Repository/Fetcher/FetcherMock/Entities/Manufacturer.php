<?php declare(strict_types=1);

namespace Tests\Repository\Fetcher\FetcherMock\Entities;

use Zrnik\MkSQL\Repository\Attributes\ColumnType;
use Zrnik\MkSQL\Repository\Attributes\FetchArray;
use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName('manufacturer')]
class Manufacturer extends BaseEntity
{
    #[PrimaryKey]
    public ?int $id = null;

    /** @var Car[] $cars */
    #[FetchArray(Car::class)]
    public array $cars;

    #[ColumnType('varchar(255)')]
    public string $name;
}
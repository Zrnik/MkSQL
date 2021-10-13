<?php declare(strict_types=1);

namespace Tests\Repository\Fetcher\FetcherMock\Entities;

use Zrnik\MkSQL\Repository\Attributes\ColumnName;
use Zrnik\MkSQL\Repository\Attributes\ColumnType;
use Zrnik\MkSQL\Repository\Attributes\ForeignKey;
use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName('part')]
class Part extends BaseEntity
{
    #[PrimaryKey]
    public ?int $id = null;

    #[ColumnName('auto_id')]
    #[ForeignKey(Car::class)]
    public Car $car;

    #[ColumnType('varchar(255)')]
    public string $name;
}

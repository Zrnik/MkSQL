<?php declare(strict_types=1);

namespace Tests\Repository\Fetcher\FetcherMock\Entities;

use Zrnik\MkSQL\Repository\Attributes\ColumnName;
use Zrnik\MkSQL\Repository\Attributes\ColumnType;
use Zrnik\MkSQL\Repository\Attributes\FetchArray;
use Zrnik\MkSQL\Repository\Attributes\ForeignKey;
use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName('car')]
class Car extends BaseEntity
{
    #[PrimaryKey]
    public ?int $id = null;

    #[ForeignKey(Manufacturer::class)]
    #[ColumnName('man_id')]
    public Manufacturer $manufacturer;

    /**
     * @var Part[]
     */
    #[FetchArray(Part::class)]
    public array $parts;

    #[ColumnType('varchar(255)')]
    public string $name;
}

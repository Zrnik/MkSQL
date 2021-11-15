<?php declare(strict_types=1);

namespace Tests\Mock\EntitiesWithDefaultValues;

use Zrnik\MkSQL\Repository\Attributes\ColumnType;
use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName('test_default_EntityWithNoDefaults')]
class EntityWithNoDefaults extends BaseEntity
{
    #[PrimaryKey]
    public ?int $id = null;

    #[ColumnType('text')]
    public string $noDefaultString;
}

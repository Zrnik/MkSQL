<?php declare(strict_types=1);

namespace Tests\Mock\BaseRepositoryAndBaseEntity\HydrateTestEntities;

use Zrnik\MkSQL\Repository\Attributes\ColumnType;
use Zrnik\MkSQL\Repository\Attributes\ForeignKey;
use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName('FetchedEntityFromSubEntity')]
class FetchedEntityFromSubEntity extends BaseEntity
{
    #[PrimaryKey]
    public ?int $id = null;

    #[ForeignKey(SubEntityOne::class)]
    public SubEntityOne $fetchedEntity;
}

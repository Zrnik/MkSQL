<?php declare(strict_types=1);

namespace Tests\Mock\BaseRepositoryAndBaseEntity\HydrateTestEntities;

use Zrnik\MkSQL\Repository\Attributes\FetchArray;
use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName('SubEntityOne')]
class SubEntityOne extends BaseEntity
{
    #[PrimaryKey]
    public ?int $id = null;

    /**
     * @var array<FetchedEntityFromSubEntity>
     */
    #[FetchArray(FetchedEntityFromSubEntity::class)]
    public array $fetchedEntities = [];
}

<?php declare(strict_types=1);

namespace Tests\Mock\BaseRepositoryAndBaseEntity\HydrateTestEntities;

use Zrnik\MkSQL\Repository\Attributes\FetchArray;
use Zrnik\MkSQL\Repository\Attributes\ForeignKey;
use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName('FetchedEntity')]
class FetchedEntity extends BaseEntity
{
    #[PrimaryKey]
    public ?int $id = null;

    #[ForeignKey(MainEntity::class)]
    public MainEntity $mainEntity;

    /**
     * @var SubEntityFetchedEntity[]
     */
    #[FetchArray(SubEntityFetchedEntity::class)]
    public array $subEntityFetchedEntities = [];
}

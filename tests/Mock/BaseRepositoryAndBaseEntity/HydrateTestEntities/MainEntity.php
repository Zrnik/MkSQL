<?php declare(strict_types=1);

namespace Tests\Mock\BaseRepositoryAndBaseEntity\HydrateTestEntities;

use Zrnik\MkSQL\Repository\Attributes\FetchArray;
use Zrnik\MkSQL\Repository\Attributes\ForeignKey;
use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName('MainEntity')]
class MainEntity extends BaseEntity
{
    #[PrimaryKey]
    public ?int $id = null;

    /**
     * @var FetchedEntity[]
     */
    #[FetchArray(FetchedEntity::class)]
    public array $fetchedEntities = [];

    #[ForeignKey(SubEntityOne::class)]
    public SubEntityOne $subEntityOne;

    #[ForeignKey(SubEntityTwo::class)]
    public SubEntityTwo $subEntityTwo;
}

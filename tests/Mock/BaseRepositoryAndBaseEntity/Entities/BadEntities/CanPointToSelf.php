<?php declare(strict_types=1);

namespace Tests\Mock\BaseRepositoryAndBaseEntity\Entities\BadEntities;

use Zrnik\MkSQL\Repository\Attributes\ForeignKey;
use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName('canPointToSelf')]
class CanPointToSelf extends BaseEntity
{
    #[PrimaryKey]
    public ?int $id = null;

    #[ForeignKey(CanPointToSelf::class)]
    public ?CanPointToSelf $anotherCanPointToSelf = null;
}

<?php

declare(strict_types=1);

namespace Tests\Mock\BaseRepositoryAndBaseEntity\Entities\BadEntities;

use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName('only_primary_key_entity')]
class OnlyPrimaryKeyEntity extends BaseEntity
{
    #[PrimaryKey]
    public ?int $id = null;
}

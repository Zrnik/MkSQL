<?php

namespace Mock\BaseRepositoryAndBaseEntity\Entities\BadEntities;

use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName('NonNullablePrimaryKeyEntity')]
class NonNullablePrimaryKeyEntity extends BaseEntity
{
    #[PrimaryKey]
    public int $id;
}

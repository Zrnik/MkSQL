<?php

namespace Mock\BaseRepositoryAndBaseEntity\Entities\BadEntities;

use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName('MultiplePrimaryKeysDefined')]
class MultiplePrimaryKeysDefined extends BaseEntity
{
    #[PrimaryKey]
    public ?int $id;

    #[PrimaryKey]
    public ?string $alsoPK;
}

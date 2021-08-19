<?php

namespace Mock\BaseRepositoryAndBaseEntity\Entities\BadEntities\SubEntityNotPointingBack;

use Zrnik\MkSQL\Repository\Attributes\ForeignKey;
use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName("SubEntityNotPointingBack_EntityNotPointingBack")]
class EntityNotPointingBack extends BaseEntity
{
    #[PrimaryKey]
    public ?int $id = null;




}

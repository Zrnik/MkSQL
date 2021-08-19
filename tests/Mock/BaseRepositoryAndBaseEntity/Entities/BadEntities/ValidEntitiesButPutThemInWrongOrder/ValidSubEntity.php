<?php

namespace Mock\BaseRepositoryAndBaseEntity\Entities\BadEntities\ValidEntitiesButPutThemInWrongOrder;

use Zrnik\MkSQL\Repository\Attributes\ForeignKey;
use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName("ValidEntitiesButPutThemInWrongOrder_ValidSubEntity")]
class ValidSubEntity extends BaseEntity
{
    #[PrimaryKey]
    public ?int $id = null;

    #[ForeignKey(ValidMainEntity::class)]
    public ValidMainEntity $mainEntity;


}

<?php

namespace Mock\BaseRepositoryAndBaseEntity\Entities\BadEntities\ValidEntitiesButPutThemInWrongOrder;

use Zrnik\MkSQL\Repository\Attributes\FetchArray;
use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName("ValidEntitiesButPutThemInWrongOrder_ValidMainEntity")]
class ValidMainEntity extends BaseEntity
{
    #[PrimaryKey]
    public ?int $id = null;

    /**
     * @var ValidSubEntity[]
     */
    #[FetchArray(ValidSubEntity::class)]
    public array $subEntities;


}

<?php

namespace Mock\BaseRepositoryAndBaseEntity\Entities\BadEntities\SubEntityNotPointingBack;

use Zrnik\MkSQL\Repository\Attributes\FetchArray;
use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName("SubEntityNotPointingBack_MainEntity")]
class MainEntity extends BaseEntity
{
    #[PrimaryKey]
    public ?int $id = null;

    /**
     * @var EntityNotPointingBack[]
     */
    #[FetchArray(EntityNotPointingBack::class)]
    public array $subEntities;


}

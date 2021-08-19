<?php

namespace Mock\BaseRepositoryAndBaseEntity\Entities\BadEntities;

use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName('MissingPrimaryKeyEntity')]
class MissingPrimaryKeyEntity extends BaseEntity
{

}

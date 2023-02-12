<?php declare(strict_types=1);

namespace Tests\Mock\BaseRepositoryAndBaseEntity\Entities\BadEntities\OnlyOneForeignKeyTargetingSameClass;

use Zrnik\MkSQL\Repository\Attributes\ColumnType;
use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName('OnlyOneForeignKeyTargetingSameClass_ReferencedClassTwo')]
class ReferencedClassTwo extends BaseEntity
{
    #[PrimaryKey]
    public ?int $id = null;

    /**
     * @see Zrnik\MkSQL\Exceptions\OnlyPrimaryKeyNotAllowedException
     */
    #[ColumnType('int(11)')]
    public int $dontMindMe = 0;
}

<?php declare(strict_types=1);

namespace Tests\Mock\BaseRepositoryAndBaseEntity\Entities\BadEntities\OnlyOneForeignKeyTargetingSameClass;

use Zrnik\MkSQL\Repository\Attributes\ForeignKey;
use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName('OnlyOneForeignKeyTargetingSameClass_ReferencingBothIsOk')]
class ReferencingBothIsOk extends BaseEntity
{
    #[PrimaryKey]
    public ?int $id = null;

    #[ForeignKey(ReferencedClassOne::class)]
    public ReferencedClassOne $c1;

    #[ForeignKey(ReferencedClassTwo::class)]
    public ReferencedClassTwo $c2;


}
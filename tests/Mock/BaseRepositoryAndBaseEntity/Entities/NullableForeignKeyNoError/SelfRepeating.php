<?php declare(strict_types=1);

namespace Tests\Mock\BaseRepositoryAndBaseEntity\Entities\NullableForeignKeyNoError;

use Zrnik\MkSQL\Repository\Attributes\ForeignKey;
use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName('NullableForeignKeyNoError_SelfRepeating')]
class SelfRepeating extends BaseEntity
{
    #[PrimaryKey]
    public ?int $id = null;

    #[ForeignKey(SelfRepeating::class)]
    public ?SelfRepeating $referrer = null;
}

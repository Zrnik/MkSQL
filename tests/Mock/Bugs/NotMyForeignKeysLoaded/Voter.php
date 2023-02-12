<?php

declare(strict_types=1);

namespace Tests\Mock\Bugs\NotMyForeignKeysLoaded;

use Tests\Mock\BaseRepositoryAndBaseEntity\Entities\CustomTypes\BooleanTypeTestingOnlyConverter;
use Zrnik\MkSQL\Repository\Attributes\ColumnType;
use Zrnik\MkSQL\Repository\Attributes\CustomType;
use Zrnik\MkSQL\Repository\Attributes\ForeignKey;
use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName('poll_voters')]
class Voter extends BaseEntity
{
    #[PrimaryKey]
    public ?int $id = null;

    /**
     * @see Zrnik\MkSQL\Exceptions\OnlyPrimaryKeyNotAllowedException
     */
    #[ColumnType('int(11)')]
    public int $dontMindMe = 0;
}
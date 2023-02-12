<?php

declare(strict_types=1);

namespace Tests\Mock\Bugs\NotMyForeignKeysLoaded;

use Tests\Mock\BaseRepositoryAndBaseEntity\Entities\CustomTypes\BooleanTypeTestingOnlyConverter;
use Zrnik\MkSQL\Repository\Attributes\CustomType;
use Zrnik\MkSQL\Repository\Attributes\ForeignKey;
use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName('poll_votes')]
class PollVote extends BaseEntity
{
    #[PrimaryKey]
    public ?int $id = null;

    #[ForeignKey(Poll::class)]
    public Poll $poll;

    #[ForeignKey(Voter::class)]
    public Voter $voter;

    #[CustomType(BooleanTypeTestingOnlyConverter::class)]
    public bool $isPositive = true;
}
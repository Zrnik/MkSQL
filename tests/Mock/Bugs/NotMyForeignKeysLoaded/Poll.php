<?php

declare(strict_types=1);

namespace Tests\Mock\Bugs\NotMyForeignKeysLoaded;


use Tests\Mock\BaseRepositoryAndBaseEntity\Entities\CustomTypes\BooleanTypeTestingOnlyConverter;
use Zrnik\MkSQL\Repository\Attributes\ColumnType;
use Zrnik\MkSQL\Repository\Attributes\CustomType;
use Zrnik\MkSQL\Repository\Attributes\FetchArray;
use Zrnik\MkSQL\Repository\Attributes\NotNull;
use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName('polls')]
class Poll extends BaseEntity
{
    #[PrimaryKey]
    public ?int $id = null;

    #[NotNull]
    #[ColumnType('text')]
    public string $question = '';

    /**
     * @var PollVote[]
     */
    #[FetchArray(PollVote::class)]
    public array $votes;

    #[CustomType(BooleanTypeTestingOnlyConverter::class)]
    public bool $isArchived = false;
}

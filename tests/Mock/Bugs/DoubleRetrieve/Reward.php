<?php declare(strict_types=1);

namespace Tests\Mock\Bugs\DoubleRetrieve;

use Zrnik\MkSQL\Repository\Attributes\ForeignKey;
use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName('unset_double_retrieve_3_Reward')]
class Reward extends BaseEntity
{
    #[PrimaryKey]
    public ?int $id = null;

    public int $rewardAmount;

    #[ForeignKey(Person::class)]
    public Person $receiver;

    #[ForeignKey(AccountEntry::class)]
    public AccountEntry $relatedEntry;
}

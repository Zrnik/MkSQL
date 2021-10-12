<?php declare(strict_types=1);

namespace Tests\Mock\Bugs\DoubleRetrieve;

use Zrnik\MkSQL\Repository\Attributes\FetchArray;
use Zrnik\MkSQL\Repository\Attributes\ForeignKey;
use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName('unset_double_retrieve_2_AccountEntry')]
class AccountEntry extends BaseEntity
{
    #[PrimaryKey]
    public ?int $id = null;

    #[ForeignKey(Person::class)]
    public Person $owner;

    public int $amount;

    /**
     * @var Reward[]
     */
    #[FetchArray(Reward::class)]
    public array $rewards;
}

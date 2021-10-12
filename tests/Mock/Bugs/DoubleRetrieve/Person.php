<?php declare(strict_types=1);

namespace Tests\Mock\Bugs\DoubleRetrieve;

use Zrnik\MkSQL\Repository\Attributes\ColumnType;
use Zrnik\MkSQL\Repository\Attributes\FetchArray;
use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName('unset_double_retrieve_1_Person')]
class Person extends BaseEntity
{
    #[PrimaryKey]
    public ?int $id = null;

    #[ColumnType('varchar(255)')]
    public string $name;

    /**
     * @var AccountEntry[]
     */
    #[FetchArray(AccountEntry::class)]
    public array $accountEntries;
}

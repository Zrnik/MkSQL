<?php declare(strict_types=1);

namespace Examples\Accounts\Orm;

use Zrnik\MkSQL\Repository\Attributes\ColumnType;
use Zrnik\MkSQL\Repository\Attributes\Comment;
use Zrnik\MkSQL\Repository\Attributes\NotNull;
use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\Attributes\Unique;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName('account')]
class Account extends BaseEntity
{
    #[PrimaryKey]
    public ?int $id = null;

    #[NotNull]
    #[Unique]
    #[ColumnType('varchar(60)')]
    public string $username;

    #[NotNull]
    #[ColumnType('char(64)')]
    #[Comment('sha256')]
    public string $password;
}

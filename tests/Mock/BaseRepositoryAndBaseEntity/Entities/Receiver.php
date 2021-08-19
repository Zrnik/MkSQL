<?php

namespace Mock\BaseRepositoryAndBaseEntity\Entities;

use Zrnik\MkSQL\Repository\Attributes\ColumnName;
use Zrnik\MkSQL\Repository\Attributes\ColumnType;
use Zrnik\MkSQL\Repository\Attributes\NotNull;
use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName("receiver_list")]
class Receiver extends BaseEntity
{
    #[PrimaryKey]
    #[ColumnType("varchar(30)")]
    #[ColumnName("receiverPrivateKey")]
    public ?string $receiverId = null;

    #[ColumnType("varchar(255)")]
    #[ColumnName("receiverName")]
    #[NotNull]
    public string $name;
}

<?php

namespace Mock\BaseRepositoryAndBaseEntity\Entities;

use Zrnik\MkSQL\Repository\Attributes\ColumnName;
use Zrnik\MkSQL\Repository\Attributes\ColumnType;
use Zrnik\MkSQL\Repository\Attributes\ForeignKey;
use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName('auction_item')]
class AuctionItem extends BaseEntity
{
    #[PrimaryKey]
    public ?int $id = null;

    #[ForeignKey(Auction::class)]
    #[ColumnName("theAuctionIRelateTo")]
    public Auction $auction;

    #[ColumnType("varchar(64)")]
    public string $name;

    #[ColumnType("bool")]
    public bool $sold = false;
}

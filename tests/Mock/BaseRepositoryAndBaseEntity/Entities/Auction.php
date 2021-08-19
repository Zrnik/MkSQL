<?php

namespace Mock\BaseRepositoryAndBaseEntity\Entities;

use Zrnik\MkSQL\Repository\Attributes\ColumnName;
use Zrnik\MkSQL\Repository\Attributes\ColumnType;
use Zrnik\MkSQL\Repository\Attributes\FetchArray;
use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName("auction")]
class Auction extends BaseEntity
{
    #[PrimaryKey]
    public ?int $id = null;

    #[ColumnType("varchar(64)")]
    #[ColumnName("theNameOfTheAuction")]
    public string $name;

    /**
     * @var AuctionItem[]
     */
    #[FetchArray(AuctionItem::class)]
    public array $auctionItems;

}

<?php

namespace Mock\BaseRepositoryAndBaseEntity\Entities;

use Brick\DateTime\LocalDateTime;
use Mock\BaseRepositoryAndBaseEntity\Entities\CustomTypes\LocalDateTimeTypeConverter;
use Zrnik\MkSQL\Repository\Attributes\ColumnType;
use Zrnik\MkSQL\Repository\Attributes\DefaultValue;
use Zrnik\MkSQL\Repository\Attributes\Fetch;
use Zrnik\MkSQL\Repository\Attributes\FetchArray;
use Zrnik\MkSQL\Repository\Attributes\ForeignKey;
use Zrnik\MkSQL\Repository\Attributes\NotNull;
use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\CustomType;
use Zrnik\MkSQL\Repository\Attributes\Unique;
use Zrnik\MkSQL\Repository\BaseEntity;
use Zrnik\MkSQL\Repository\Attributes\TableName;

#[TableName("invoice_list")]
class Invoice extends BaseEntity
{
    #[PrimaryKey]
    public ?int $invoiceId = null;

    #[ColumnType("varchar(64)")]
    #[Unique]
    #[NotNull]
    public string $invoiceToken;

    #[ColumnType("char(3)")]
    #[NotNull]
    #[DefaultValue]
    public string $invoiceCurrency = "EUR";

    #[ColumnType("varchar(64)")]
    #[CustomType(LocalDateTimeTypeConverter::class)]
    #[NotNull]
    public LocalDateTime $createDate;

    #[ForeignKey(Receiver::class)]
    #[NotNull]
    public Receiver $receiver;

    /**
     * @var InvoiceItem[]
     */
    #[FetchArray(InvoiceItem::class)]
    public array $invoiceItems;

}

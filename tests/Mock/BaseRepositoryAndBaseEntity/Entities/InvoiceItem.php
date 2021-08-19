<?php

namespace Mock\BaseRepositoryAndBaseEntity\Entities;

use Brick\DateTime\LocalDateTime;
use Zrnik\MkSQL\Repository\Attributes\ColumnType;
use Zrnik\MkSQL\Repository\Attributes\DefaultValue;
use Zrnik\MkSQL\Repository\Attributes\ForeignKey;
use Zrnik\MkSQL\Repository\Attributes\NotNull;
use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName("invoice_item")]
class InvoiceItem extends BaseEntity
{
    #[PrimaryKey]
    public ?int $invoiceItemId = null;


    #[ForeignKey(Invoice::class)]
    #[NotNull]
    public Invoice $invoice;

    #[ColumnType("varchar(255)")]
    #[NotNull]
    public string $name;

    #[ColumnType("int")]
    #[NotNull]
    #[DefaultValue]
    public int $amount = 1;

    #[ColumnType("decimal(15,2)")]
    #[NotNull]
    #[DefaultValue]
    public float $price = 0;
}

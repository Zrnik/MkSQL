<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Tests\Mock\BaseRepositoryAndBaseEntity\Entities;

use Brick\DateTime\LocalDateTime;
use Tests\Mock\BaseRepositoryAndBaseEntity\Entities\CustomTypes\LocalDateTimeTypeTestingOnlyConverter;
use Tests\Mock\BaseRepositoryAndBaseEntity\Entities\CustomTypes\NonNullableStringTypeConverter;
use Tests\Mock\BaseRepositoryAndBaseEntity\Entities\CustomTypes\NullableStringTypeConverter;
use Zrnik\MkSQL\Repository\Attributes\ColumnType;
use Zrnik\MkSQL\Repository\Attributes\CustomType;
use Zrnik\MkSQL\Repository\Attributes\DefaultValue;
use Zrnik\MkSQL\Repository\Attributes\FetchArray;
use Zrnik\MkSQL\Repository\Attributes\ForeignKey;
use Zrnik\MkSQL\Repository\Attributes\NotNull;
use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\Attributes\Unique;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName('invoice_list')]
class Invoice extends BaseEntity
{
    #[PrimaryKey]
    public ?int $invoiceId = null;

    #[ColumnType('varchar(64)')]
    #[Unique]
    #[NotNull]
    public string $invoiceToken;

    #[ColumnType('char(3)')]
    #[NotNull]
    #[DefaultValue]
    public string $invoiceCurrency = 'EUR';

    #[CustomType(LocalDateTimeTypeTestingOnlyConverter::class)]
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

    #[CustomType(NullableStringTypeConverter::class)]
    public ?string $nullableProp = null;

    #[CustomType(NonNullableStringTypeConverter::class)]
    public string $nonNullableProp = '';


}

<?php declare(strict_types=1);

namespace Tests\Mock\BaseRepositoryAndBaseEntity\Entities\NullableForeignKeyNoError;

use Zrnik\MkSQL\Repository\Attributes\ColumnType;
use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName('NullableForeignKeyNoError_SubClass')]
class SubClass extends BaseEntity
{
    #[PrimaryKey]
    public ?int $id = null;

    #[ColumnType('varchar(255)')]
    public string $text = '';
}

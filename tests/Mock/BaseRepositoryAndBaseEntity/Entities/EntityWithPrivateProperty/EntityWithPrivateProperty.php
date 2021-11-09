<?php declare(strict_types=1);

namespace Tests\Mock\BaseRepositoryAndBaseEntity\Entities\EntityWithPrivateProperty;

use Zrnik\MkSQL\Repository\Attributes\ColumnType;
use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName('EntityWithPrivateProperty')]
class EntityWithPrivateProperty extends BaseEntity
{
    #[PrimaryKey]
    public ?int $id = null;

    #[ColumnType('varchar(255)')]
    public string $name;

    private string $secret = '';

    public function getSecret(): string {
        return $this->secret;
    }
}

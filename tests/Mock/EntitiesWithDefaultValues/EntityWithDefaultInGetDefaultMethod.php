<?php declare(strict_types=1);

namespace Tests\Mock\EntitiesWithDefaultValues;

use JetBrains\PhpStorm\ArrayShape;
use Zrnik\MkSQL\Repository\Attributes\ColumnName;
use Zrnik\MkSQL\Repository\Attributes\ColumnType;
use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName('test_default_EntityWithDefaultInGetDefaultMethod')]
class EntityWithDefaultInGetDefaultMethod extends BaseEntity
{
    #[PrimaryKey]
    public ?int $id = null;

    #[ColumnType('text')]
    #[ColumnName('itsInGetDefaults_ButAsPropertyName')]
    public string $defaultInGetDefault;

    /** @noinspection PhpMissingParentCallCommonInspection */
    #[ArrayShape(['defaultInGetDefault' => 'string'])]
    protected function getDefaults(): array
    {
        return [
            'defaultInGetDefault' => 'Hello World'
        ];
    }
}
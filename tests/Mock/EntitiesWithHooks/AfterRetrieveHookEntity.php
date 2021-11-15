<?php declare(strict_types=1);

namespace Tests\Mock\EntitiesWithHooks;

use Zrnik\MkSQL\Repository\Attributes\ColumnType;
use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName('hook_test_AfterRetrieveHookEntity')]
class AfterRetrieveHookEntity extends BaseEntity
{
    #[PrimaryKey]
    public ?int $id = null;

    #[ColumnType('varchar(100)')]
    public string $afterSaveException = 'Hello World';

    /**
     * @throws EntityWithHookException
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function afterRetrieve(): void
    {
        throw new EntityWithHookException(EntityHookExceptionType::AFTER_RETRIEVE);
    }
}

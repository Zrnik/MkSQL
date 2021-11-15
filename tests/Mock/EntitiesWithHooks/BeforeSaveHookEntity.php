<?php declare(strict_types=1);

namespace Tests\Mock\EntitiesWithHooks;

use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName('hook_test_BeforeSaveHookEntity')]
class BeforeSaveHookEntity extends BaseEntity
{
    #[PrimaryKey]
    public ?int $id = null;

    /**
     * @throws EntityWithHookException
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function beforeSave(): void
    {
        throw new EntityWithHookException(EntityHookExceptionType::BEFORE_SAVE);
    }

}

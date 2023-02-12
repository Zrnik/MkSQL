<?php declare(strict_types=1);

namespace Tests\Mock\EntitiesWithHooks;

use Zrnik\MkSQL\Repository\Attributes\ColumnType;
use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName('hook_test_BeforeSaveHookEntity')]
class BeforeSaveHookEntity extends BaseEntity
{
    #[PrimaryKey]
    public ?int $id = null;

    /**
     * @see Zrnik\MkSQL\Exceptions\OnlyPrimaryKeyNotAllowedException
     */
    #[ColumnType('int(11)')]
    public int $dontMindMe = 0;

    /**
     * @throws EntityWithHookException
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function beforeSave(): void
    {
        throw new EntityWithHookException(EntityHookExceptionType::BEFORE_SAVE);
    }

}

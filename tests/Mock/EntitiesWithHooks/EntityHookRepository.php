<?php declare(strict_types=1);

namespace Tests\Mock\EntitiesWithHooks;

use Zrnik\MkSQL\Updater;
use Zrnik\MkSQL\Utilities\Installable;

class EntityHookRepository extends Installable
{
    protected function install(Updater $updater): void
    {
        $updater->use(AfterRetrieveHookEntity::class);
        $updater->use(AfterSaveHookEntity::class);
        $updater->use(BeforeSaveHookEntity::class);
    }
}

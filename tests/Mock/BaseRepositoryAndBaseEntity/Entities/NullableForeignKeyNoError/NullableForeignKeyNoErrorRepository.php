<?php declare(strict_types=1);

namespace Tests\Mock\BaseRepositoryAndBaseEntity\Entities\NullableForeignKeyNoError;

use Zrnik\MkSQL\Updater;
use Zrnik\MkSQL\Utilities\Installable;

class NullableForeignKeyNoErrorRepository extends Installable
{
    protected function install(Updater $updater): void
    {
        $updater->use(SelfRepeating::class);
        $updater->use(SuperClass::class);
        $updater->use(SubClass::class);
    }
}

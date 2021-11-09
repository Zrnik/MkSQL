<?php declare(strict_types=1);

namespace Tests\Mock\BaseRepositoryAndBaseEntity\Entities\EntityWithPrivateProperty;

use Zrnik\MkSQL\Updater;
use Zrnik\MkSQL\Utilities\Installable;

class PrivatePropertyRepository extends Installable
{
    protected function install(Updater $updater): void
    {
        $updater->use(EntityWithPrivateProperty::class);
    }
}

<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Tests\Mock\BaseRepositoryAndBaseEntity;

use Tests\Mock\BaseRepositoryAndBaseEntity\Entities\Invoice;
use Zrnik\MkSQL\Exceptions\MkSQLException;
use Zrnik\MkSQL\Updater;
use Zrnik\MkSQL\Utilities\Installable;

class CallingInstallInInstallableRepository extends Installable
{
    /**
     * @throws MkSQLException
     */
    protected function install(Updater $updater): void
    {
        $updater->use(Invoice::class);
        $updater->install();
    }
}

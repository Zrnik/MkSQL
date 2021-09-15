<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Mock\BaseRepositoryAndBaseEntity;

use Mock\BaseRepositoryAndBaseEntity\Entities\Invoice;
use Mock\BaseRepositoryAndBaseEntity\Entities\InvoiceItem;
use Mock\BaseRepositoryAndBaseEntity\Entities\Receiver;
use Zrnik\MkSQL\Exceptions\MkSQLException;
use Zrnik\MkSQL\Updater;
use Zrnik\MkSQL\Utilities\Installable;

class InvoiceRepository extends Installable
{
    /**
     * @throws MkSQLException
     */
    protected function install(Updater $updater): void
    {
        $updater->use(Receiver::class);
        $updater->use(Invoice::class);
        $updater->use(InvoiceItem::class);
    }
}

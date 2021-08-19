<?php

namespace Mock\BaseRepositoryAndBaseEntity;

use Mock\BaseRepositoryAndBaseEntity\Entities\Invoice;
use Mock\BaseRepositoryAndBaseEntity\Entities\InvoiceItem;
use Mock\BaseRepositoryAndBaseEntity\Entities\Receiver;
use ReflectionException;
use Zrnik\MkSQL\Exceptions\MkSQLException;
use Zrnik\MkSQL\Updater;
use Zrnik\MkSQL\Utilities\Installable;

class InvoiceRepository extends Installable
{
    /**
     * @throws ReflectionException
     * @throws MkSQLException
     */
    public function install(Updater $updater): void
    {
        $updater->use(
            Receiver::class,
            Invoice::class,
            InvoiceItem::class,
        );
    }
}

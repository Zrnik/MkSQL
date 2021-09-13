<?php

namespace Mock\BaseRepositoryAndBaseEntity;

use Mock\BaseRepositoryAndBaseEntity\Entities\Auction;
use Mock\BaseRepositoryAndBaseEntity\Entities\AuctionItem;
use Mock\BaseRepositoryAndBaseEntity\Entities\InvalidItem;
use ReflectionException;
use Zrnik\MkSQL\Exceptions\MissingForeignKeyDefinitionInEntityException;
use Zrnik\MkSQL\Exceptions\MkSQLException;
use Zrnik\MkSQL\Updater;
use Zrnik\MkSQL\Utilities\Installable;

class AuctionRepository extends Installable
{

    /**
     * @throws MissingForeignKeyDefinitionInEntityException
     * @throws ReflectionException
     * @throws MkSQLException
     */
    protected function install(Updater $updater): void
    {
        $updater->use(
            Auction::class,
            AuctionItem::class,
        );
    }
}

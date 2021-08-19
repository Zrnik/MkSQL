<?php

namespace Mock\BaseRepositoryAndBaseEntity;

use Mock\BaseRepositoryAndBaseEntity\Entities\Auction;
use Mock\BaseRepositoryAndBaseEntity\Entities\AuctionItem;
use Zrnik\MkSQL\Updater;
use Zrnik\MkSQL\Utilities\Installable;

class AuctionRepository extends Installable
{



    protected function install(Updater $updater): void
    {
        $updater->use(
            Auction::class,
            AuctionItem::class
        );

        $newAuction = Auction::create();
    }
}

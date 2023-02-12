<?php

declare(strict_types=1);

namespace Tests\Mock\Bugs\NotMyForeignKeysLoaded;

use Zrnik\MkSQL\Updater;
use Zrnik\MkSQL\Utilities\Installable;

class PollRepository extends Installable
{

    protected function install(Updater $updater): void
    {
        $updater->use(Poll::class);
    }
}
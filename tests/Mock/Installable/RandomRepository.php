<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Tests\Mock\Installable;

use Zrnik\MkSQL\Updater;
use Zrnik\MkSQL\Utilities\Installable;

class RandomRepository extends Installable
{
    public bool $installed = false;

    protected function install(Updater $updater): void
    {
        $this->installed = true;
    }
}

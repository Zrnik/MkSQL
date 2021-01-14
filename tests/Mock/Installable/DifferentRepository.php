<?php declare(strict_types=1);
/**
 * @generator PhpStorm
 * @author Štěpán Zrník <stepan@zrnik.eu>
 * @date 14.01.2021
 * @project MkSQL
 * @copyright (c) 2021 - Štěpán Zrník
 */

namespace Mock\Installable;

use Zrnik\MkSQL\Updater;
use Zrnik\MkSQL\Utilities\Installable;

class DifferentRepository extends Installable
{
    public bool $installed = false;

    function install(Updater $updater): void
    {
        $this->installed = true;
    }
}
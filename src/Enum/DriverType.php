<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Zrnik\MkSQL\Enum;

use Zrnik\Base\Enum;

class DriverType extends Enum
{
    public const MySQL = 0;
    public const SQLite = 1;
}

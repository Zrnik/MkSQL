<?php
/*
 * Zrník.eu | MkSQL
 * User: Programátor
 * Date: 31.07.2020 10:03
 */

namespace Zrnik\MkSQL\Enum;

use Zrnik\Base\Enum;

class DriverType extends Enum
{
    public const MySQL = 0;
    public const SQLite = 1;
}

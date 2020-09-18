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
    const MySQL = 0;
    const SQLite = 1;
}

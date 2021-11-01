<?php declare(strict_types=1);

namespace Zrnik\MkSQL\Repository\Saver;

use Zrnik\Base\Enum;

class SaveMethod extends Enum
{
    public const INSERT = 0;
    public const UPDATE = 1;
}

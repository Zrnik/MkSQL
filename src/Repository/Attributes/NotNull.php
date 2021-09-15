<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Zrnik\MkSQL\Repository\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class NotNull
{
    public function __construct()
    {

    }
}

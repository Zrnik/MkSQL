<?php

namespace Zrnik\MkSQL\Repository\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class NotNull
{
    public function __construct()
    { }
}

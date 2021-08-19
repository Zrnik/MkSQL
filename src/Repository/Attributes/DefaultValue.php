<?php

namespace Zrnik\MkSQL\Repository\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class DefaultValue
{
    public function __construct()
    { }
}

<?php

namespace Zrnik\MkSQL\Repository\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class FetchArray
{
    public function __construct(public string $iterableType)
    {
    }
}

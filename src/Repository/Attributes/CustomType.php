<?php

namespace Zrnik\MkSQL\Repository\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class CustomType
{
    public function __construct(public string $typeConverterClassExtendingCustomTypeClass)
    {
    }
}

<?php

namespace Zrnik\MkSQL\Repository\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class ForeignKey
{
    public function __construct(public string $foreignEntityClassName)
    { }
}

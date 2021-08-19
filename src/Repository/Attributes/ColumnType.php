<?php

namespace Zrnik\MkSQL\Repository\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ColumnType
{
    public function __construct(public string $databaseType)
    { }
}

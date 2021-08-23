<?php

namespace Zrnik\MkSQL\Repository\Types;

use Zrnik\MkSQL\Repository\Attributes\CustomType;
use Zrnik\MkSQL\Repository\CustomTypeConverter;

class BooleanType extends CustomTypeConverter
{

    public function serialize(mixed $value): int
    {
        /** @var bool $value */
        $value = $this->assertType($value, 'bool');
        return $value ? 1 : 0;
        // TODO: Implement serialize() method.
    }

    public function deserialize(mixed $value): bool
    {
        return (int) $value === 1;
    }

    public function getDatabaseType(): string
    {
        return "tinyint";
    }
}

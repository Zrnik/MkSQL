<?php

namespace Mock\BaseRepositoryAndBaseEntity\Entities\CustomTypes;

use Zrnik\MkSQL\Exceptions\InvalidArgumentException;
use Zrnik\MkSQL\Repository\CustomTypeConverter;

class NonNullableStringTypeConverter extends CustomTypeConverter
{
    /**
     * @throws InvalidArgumentException
     */
    public function serialize(mixed $value): mixed
    {
        return $this->assertType($value, "string");
    }

    public function deserialize(mixed $value): string
    {
        return (string) $value;
    }

    public function getDatabaseType(): string
    {
        return "text";
    }
}

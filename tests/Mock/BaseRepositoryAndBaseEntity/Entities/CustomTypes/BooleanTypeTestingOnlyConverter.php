<?php

namespace Mock\BaseRepositoryAndBaseEntity\Entities\CustomTypes;


use Zrnik\MkSQL\Exceptions\InvalidArgumentException;
use Zrnik\MkSQL\Repository\CustomTypeConverter;

class BooleanTypeTestingOnlyConverter extends CustomTypeConverter
{

    /**
     * @throws InvalidArgumentException
     */
    public function serialize(mixed $value): int
    {
        /** @var bool $bool */
        $bool = $this->assertType($value,get_debug_type(true));
        return $bool ? 1 : 0;
    }

    public function deserialize(mixed $value): bool
    {
        return (int)$value === 1;
    }

    public function getDatabaseType(): string
    {
        return "tinyint(1)";
    }
}

<?php

namespace Mock\BaseRepositoryAndBaseEntity\Entities\CustomTypes;

use Brick\DateTime\LocalDateTime;
use Zrnik\MkSQL\Exceptions\InvalidArgumentException;
use Zrnik\MkSQL\Repository\CustomTypeConverter;

class LocalDateTimeTypeTestingOnlyConverter extends CustomTypeConverter
{
    /**
     * @throws InvalidArgumentException
     */
    public function serialize(mixed $value): ?string
    {
        /** @var ?LocalDateTime $value */
        $value = $this->assertType($value, LocalDateTime::class);
        return $value?->jsonSerialize();
    }

    public function deserialize(mixed $value): ?LocalDateTime
    {
        if($value === null) {
            return null;
        }

        return LocalDateTime::parse($value);
    }

    public function getDatabaseType(): string
    {
        return "varchar(255)";
    }

}

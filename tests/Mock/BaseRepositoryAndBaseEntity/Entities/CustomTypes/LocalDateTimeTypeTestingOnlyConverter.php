<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Tests\Mock\BaseRepositoryAndBaseEntity\Entities\CustomTypes;

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
        if ($value === null) {
            return null;
        }

        return LocalDateTime::parse($value);
    }

    public function getDatabaseType(): string
    {
        return 'varchar(255)';
    }

}

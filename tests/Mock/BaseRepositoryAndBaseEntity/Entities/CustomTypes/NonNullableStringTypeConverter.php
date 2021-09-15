<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

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
        return $this->assertType($value, 'string');
    }

    public function deserialize(mixed $value): string
    {
        return (string) $value;
    }

    public function getDatabaseType(): string
    {
        return 'text';
    }
}

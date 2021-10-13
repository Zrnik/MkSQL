<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Tests\Mock\BaseRepositoryAndBaseEntity\Entities\CustomTypes;


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
        $bool = $this->assertType($value, get_debug_type(true));
        return $bool ? 1 : 0;
    }

    public function deserialize(mixed $value): bool
    {
        return (int)$value === 1;
    }

    public function getDatabaseType(): string
    {
        return 'tinyint(1)';
    }
}

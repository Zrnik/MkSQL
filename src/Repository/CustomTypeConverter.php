<?php

namespace Zrnik\MkSQL\Repository;

use Zrnik\MkSQL\Exceptions\InvalidArgumentException;

abstract class CustomTypeConverter
{

    /**
     * @throws InvalidArgumentException
     */
    public static function initialize(string $className): CustomTypeConverter
    {
        //TODO: Cache the instances?
        $instance = new $className();

        if(!($instance instanceof self)) {
            throw new InvalidArgumentException(
                sprintf(
                    "Class '%s' is not a '%s' descendant!",
                    $className, __CLASS__
                )
            );
        }

        return $instance;
    }

    abstract public function serialize(mixed $value): mixed;

    abstract public function deserialize(mixed $value): mixed;

    abstract public function getDatabaseType(): string;

    /**
     * @param mixed $value
     * @param string $type
     * @return mixed
     * @throws InvalidArgumentException
     */
    protected function assertType(mixed $value, string $type): mixed
    {
        $realType = get_debug_type($value);
        if ($realType !== $type) {
            throw new InvalidArgumentException(
                sprintf(
                    "Type converter '%s' expects '%s', but got '%s'.",
                    static::class, $type, $realType
                )
            );
        }
        return $value;
    }
}

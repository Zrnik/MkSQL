<?php

namespace Zrnik\MkSQL\Repository;

use JetBrains\PhpStorm\Pure;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;
use Zrnik\MkSQL\Exceptions\InvalidArgumentException;

abstract class CustomTypeConverter
{
    public function __construct(private ReflectionProperty $property)
    {
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function initialize(string $className, ReflectionProperty $property): CustomTypeConverter
    {
        //TODO: Cache the instances?
        $instance = new $className($property);

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
        if($value === null && $this->isNullable()) {
            return null;
        }

        $realType = get_debug_type($value);
        if ($realType !== $type) {
            throw new InvalidArgumentException(
                sprintf(
                    "Type converter '%s' for key '%s' expects '%s', but got '%s'.",
                    static::class, $this->getKey(), $type, $realType
                )
            );
        }
        return $value;
    }

    private function isNullable(): bool
    {
        if(BaseEntity::columnNotNull($this->property)) {
            return false;
        }

        $type = $this->property->getType();
        if(($type instanceof ReflectionNamedType) && !$type->allowsNull()) {
            return false;
        }
        if(($type instanceof ReflectionUnionType) && !$type->allowsNull()) {
            return false;
        }

        return true;
    }

    #[Pure]
    private function getKey(): string
    {
        return BaseEntity::columnName($this->property);
    }
}

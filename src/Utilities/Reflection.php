<?php

namespace Zrnik\MkSQL\Utilities;

use JetBrains\PhpStorm\Pure;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;
use Zrnik\MkSQL\Exceptions\RequiredClassAttributeMissingException;
use Zrnik\MkSQL\Repository\Attributes\TableName;

class Reflection
{

    /**
     * @param ReflectionClass<object> $reflection
     * @param class-string $attributeClassName
     * @return bool
     */
    #[Pure]
    public static function classHasAttribute(ReflectionClass $reflection, string $attributeClassName): bool
    {
        return self::classGetAttribute($reflection, $attributeClassName) !== null;
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @param class-string $attributeClassName
     * @return array<ReflectionAttribute>
     */
    #[Pure]
    public static function classGetAttributes(ReflectionClass $reflection, string $attributeClassName): array
    {
        $attributes = [];

        foreach ($reflection->getAttributes() as $reflectionAttribute) {
            if ($reflectionAttribute->getName() === $attributeClassName) {
                $attributes[] = $reflectionAttribute;
            }
        }

        return $attributes;
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @param class-string $attributeClassName
     * @return ReflectionAttribute|null
     */
    #[Pure]
    public static function classGetAttribute(ReflectionClass $reflection, string $attributeClassName): ?ReflectionAttribute
    {
        $attrs = self::classGetAttributes($reflection, $attributeClassName);
        if(count($attrs) > 0) {
            return $attrs[0];
        }
        return null;
    }

    #[Pure]
    public static function attributeGetArgument(ReflectionAttribute $reflectionAttribute, int $index = 0): mixed
    {
        $args = $reflectionAttribute->getArguments();
        return $args[$index] ?? null;
    }


    /**
     * @param ReflectionProperty $reflectionProperty
     * @param class-string $attributeClassName
     * @return bool
     */
    #[Pure]
    public static function propertyHasAttribute(ReflectionProperty $reflectionProperty, string $attributeClassName): bool
    {
        return self::propertyGetAttribute($reflectionProperty, $attributeClassName) !== null;
    }

    /**
     * @param ReflectionProperty $reflectionProperty
     * @param class-string $attributeClassName
     * @return ReflectionAttribute|null
     */
    #[Pure]
    public static function propertyGetAttribute(ReflectionProperty $reflectionProperty, string $attributeClassName): ?ReflectionAttribute
    {
        $attrs = self::propertyGetAttributes($reflectionProperty, $attributeClassName);
        if(count($attrs) > 0) {
            return $attrs[0];
        }
        return null;
    }

    /**
     * @param ReflectionProperty $reflectionProperty
     * @param class-string $attributeClassName
     * @return array<ReflectionAttribute>
     */
    #[Pure]
    public static function propertyGetAttributes(ReflectionProperty $reflectionProperty, string $attributeClassName): array
    {
        $attributes = [];

        foreach ($reflectionProperty->getAttributes() as $reflectionAttribute) {
            if ($reflectionAttribute->getName() === $attributeClassName) {
                $attributes[] = $reflectionAttribute;
            }
        }

        return $attributes;

    }


}

<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Zrnik\MkSQL\Utilities;

use JetBrains\PhpStorm\Pure;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;
use function count;

class Reflection
{
    /**
     * @param ReflectionClass<object> $reflection
     * @param class-string $attributeClassName
     * @return bool
     * @noinspection UnknownInspectionInspection
     * @noinspection PhpUnused
     */
    #[Pure]
    public static function classHasAttribute(ReflectionClass $reflection, string $attributeClassName): bool
    {
        return self::classGetAttribute($reflection, $attributeClassName) !== null;
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @param class-string $attributeClassName
     * @return array<ReflectionAttribute<object>>
     * @noinspection UnknownInspectionInspection
     * @noinspection PhpUnused
     */
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
     * @return ReflectionAttribute<object>|null
     * @noinspection UnknownInspectionInspection
     * @noinspection PhpUnused
     */
    public static function classGetAttribute(ReflectionClass $reflection, string $attributeClassName): ?ReflectionAttribute
    {
        $attrs = self::classGetAttributes($reflection, $attributeClassName);
        if (count($attrs) > 0) {
            return $attrs[0];
        }
        return null;
    }

    /**
     * @param ReflectionAttribute<object> $reflectionAttribute
     * @param int $index
     * @return mixed
     * @noinspection UnknownInspectionInspection
     * @noinspection PhpUnused
     */
    public static function attributeGetArgument(ReflectionAttribute $reflectionAttribute, int $index = 0): mixed
    {
        $args = $reflectionAttribute->getArguments();
        return $args[$index] ?? null;
    }


    /**
     * @param ReflectionProperty $reflectionProperty
     * @param class-string $attributeClassName
     * @return bool
     * @noinspection UnknownInspectionInspection
     * @noinspection PhpUnused
     */
    #[Pure]
    public static function propertyHasAttribute(ReflectionProperty $reflectionProperty, string $attributeClassName): bool
    {
        return self::propertyGetAttribute($reflectionProperty, $attributeClassName) !== null;
    }

    /**
     * @param ReflectionProperty $reflectionProperty
     * @param class-string $attributeClassName
     * @return ReflectionAttribute<object>|null
     * @noinspection UnknownInspectionInspection
     * @noinspection PhpUnused
     */
    public static function propertyGetAttribute(ReflectionProperty $reflectionProperty, string $attributeClassName): ?ReflectionAttribute
    {
        $attrs = self::propertyGetAttributes($reflectionProperty, $attributeClassName);
        if (count($attrs) > 0) {
            return $attrs[0];
        }
        return null;
    }

    /**
     * @param ReflectionProperty $reflectionProperty
     * @param class-string $attributeClassName
     * @return array<ReflectionAttribute<object>>
     * @noinspection UnknownInspectionInspection
     * @noinspection PhpUnused
     */
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

    /**
     * @param ReflectionClass<object> $reflection
     * @param string $propertyName
     * @return ReflectionProperty|null
     * @noinspection UnknownInspectionInspection
     * @noinspection PhpUnused
     */
    #[Pure]
    public static function classGetProperty(ReflectionClass $reflection, string $propertyName): ?ReflectionProperty
    {
        foreach ($reflection->getProperties() as $property) {
            if ($property->name === $propertyName) {
                return $property;
            }
        }
        return null;
    }
}

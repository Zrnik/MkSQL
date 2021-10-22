<?php declare(strict_types=1);

namespace Zrnik\MkSQL\Utilities\EntityReflection;

use ReflectionAttribute;
use Zrnik\MkSQL\Repository\Attributes\FetchArray;
use Zrnik\MkSQL\Repository\Attributes\ForeignKey;
use Zrnik\MkSQL\Repository\BaseEntity;
use Zrnik\MkSQL\Utilities\Reflection;

class EntityReflection
{

    /**
     * @param BaseEntity|class-string<BaseEntity> $entity
     * @return ForeignKeyData[]
     */
    public static function getForeignKeys(BaseEntity|string $entity): array
    {
        $reflection = BaseEntity::getReflectionClass($entity);
        $result = [];
        foreach ($reflection->getProperties() as $reflectionProperty) {
            /** @var ?ReflectionAttribute<ForeignKey> $reflectionAttribute */
            $reflectionAttribute = Reflection::propertyGetAttribute($reflectionProperty, ForeignKey::class);
            if ($reflectionAttribute !== null) {
                $result[] = new ForeignKeyData(
                    $reflectionProperty,
                    $reflectionAttribute,
                );
            }
        }
        return $result;
    }

    /**
     * @param BaseEntity|class-string<BaseEntity> $entity
     * @return FetchArrayData[]
     */
    public static function getFetchArrayProperties(BaseEntity|string $entity): array
    {
        $reflection = BaseEntity::getReflectionClass($entity);
        $result = [];
        foreach ($reflection->getProperties() as $reflectionProperty) {
            /** @var ?ReflectionAttribute<FetchArray> $reflectionAttribute */
            $reflectionAttribute = Reflection::propertyGetAttribute($reflectionProperty, FetchArray::class);
            if ($reflectionAttribute !== null) {
                $result[] = new FetchArrayData(
                    $reflectionProperty,
                    $reflectionAttribute,
                );
            }
        }
        return $result;
    }
}

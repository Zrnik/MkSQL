<?php declare(strict_types=1);

namespace Zrnik\MkSQL\Utilities\EntityReflection;

use JetBrains\PhpStorm\Pure;
use ReflectionAttribute;
use ReflectionProperty;
use Zrnik\MkSQL\Exceptions\MissingAttributeArgumentException;
use Zrnik\MkSQL\Repository\Attributes\ForeignKey;
use Zrnik\MkSQL\Repository\BaseEntity;
use Zrnik\MkSQL\Utilities\Reflection;

class ForeignKeyData
{
    /**
     * @param ReflectionProperty $reflectionProperty
     * @param ReflectionAttribute<ForeignKey> $foreignKeyAttribute
     */
    public function __construct(
        private ReflectionProperty  $reflectionProperty,
        private ReflectionAttribute $foreignKeyAttribute,
    )
    {
    }

    public function getProperty(): ReflectionProperty
    {
        return $this->reflectionProperty;
    }

    #[Pure]
    public function getPropertyName(): string
    {
        return $this->getProperty()->getName();
    }

    /**
     * @return class-string<BaseEntity>
     */
    public function getTargetClassName(): string
    {
        $targetClassName = Reflection::attributeGetArgument($this->foreignKeyAttribute);

        if ($targetClassName === null) {
            throw new MissingAttributeArgumentException(
                $this->foreignKeyAttribute, 0
            );
        }

        /** @var class-string<BaseEntity> */
        return $targetClassName;
    }

    #[Pure] public function getEntityClassName(): string
    {
        return $this->getProperty()->getDeclaringClass()->getName();
    }

    public function foreignKeyColumnName(): string
    {
        /** @var BaseEntity $classNameForStaticUsage */
        $classNameForStaticUsage = $this->getEntityClassName();
        return $classNameForStaticUsage::columnName($this->getProperty());
    }
}
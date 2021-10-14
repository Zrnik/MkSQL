<?php declare(strict_types=1);

namespace Zrnik\MkSQL\Utilities\EntityReflection;

use JetBrains\PhpStorm\Pure;
use ReflectionAttribute;
use ReflectionProperty;
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
        private ReflectionProperty $reflectionProperty,
        private ReflectionAttribute $foreignKeyAttribute,
    )
    { }

    public function getProperty(): ReflectionProperty
    {
        return $this->reflectionProperty;
    }

    #[Pure]
    public function getPropertyName(): string
    {
        return $this->getProperty()->getName();
    }

    public function getTargetClassName(): string
    {
        return Reflection::attributeGetArgument($this->foreignKeyAttribute);
    }

    #[Pure] private function getEntityClassName(): string
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
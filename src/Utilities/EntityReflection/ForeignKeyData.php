<?php declare(strict_types=1);

namespace Zrnik\MkSQL\Utilities\EntityReflection;

use JetBrains\PhpStorm\Pure;
use ReflectionAttribute;
use ReflectionProperty;
use Zrnik\MkSQL\Repository\Attributes\ForeignKey;
use Zrnik\MkSQL\Utilities\Reflection;

class ForeignKeyData
{
    /**
     * @param ReflectionProperty $propertyReflection
     * @param ReflectionAttribute<ForeignKey> $foreignKeyAttribute
     */
    public function __construct(
        private ReflectionProperty $propertyReflection,
        private ReflectionAttribute $foreignKeyAttribute,
    )
    { }

    #[Pure]
    public function getPropertyName(): string
    {
        return $this->propertyReflection->getName();
    }

    public function getTargetClassName(): string
    {
        return Reflection::attributeGetArgument($this->foreignKeyAttribute);
    }
}
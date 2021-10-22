<?php declare(strict_types=1);

namespace Zrnik\MkSQL\Utilities\EntityReflection;

use JetBrains\PhpStorm\Pure;
use ReflectionAttribute;
use ReflectionProperty;
use Zrnik\MkSQL\Repository\Attributes\FetchArray;
use Zrnik\MkSQL\Repository\BaseEntity;
use Zrnik\MkSQL\Utilities\Reflection;

class FetchArrayData
{
    /**
     * @param ReflectionProperty $reflectionProperty
     * @param ReflectionAttribute<FetchArray> $reflectionAttribute
     */
    public function __construct(
        private ReflectionProperty  $reflectionProperty,
        private ReflectionAttribute $reflectionAttribute
    )
    {
    }

    #[Pure]
    public function getPropertyName(): string
    {
        return $this->reflectionProperty->getName();
    }

    /**
     * @return class-string<BaseEntity>
     */
    public function getTargetClassName(): string
    {
        return Reflection::attributeGetArgument($this->reflectionAttribute);
    }
}
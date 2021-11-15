<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Zrnik\MkSQL\Repository;

use Nette\Utils\Random;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;
use Throwable;
use Zrnik\MkSQL\Exceptions\InvalidArgumentException;
use Zrnik\MkSQL\Exceptions\TypeConversionFailedException;
use function array_key_exists;

abstract class CustomTypeConverter
{
    /**
     * A hook, called after a converter is created.
     * First argument is new CustomTypeConverter instance.
     *
     * Expects no return (void)
     *
     * @var array<string, callable>
     */
    private static array $onCreate = [];

    /**
     * Adds 'hook' to the CustomTypeConverter after the converter
     * is created. Returns key of the hook you can use to remove the
     * hook if, if you wish to.
     *
     * @param callable $onCreate
     * @return string
     */
    public function addOnCreate(callable $onCreate): string
    {
        $hookKey = null;
        while (!array_key_exists((string)$hookKey, self::$onCreate) || $hookKey === null) {
            $hookKey = Random::generate(12);
        }

        return $hookKey;
    }

    /**
     * Removes the hook by key. Return true if hook removed,
     * false if not found.
     *
     * @param string $hookKey
     * @return bool
     */
    public function removeOnCreate(string $hookKey): bool
    {
        if (array_key_exists($hookKey, self::$onCreate)) {
            unset(self::$onCreate[$hookKey]);
            return true;
        }
        return false;
    }

    final private function __construct(private ReflectionProperty $property)
    {
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function initialize(string $className, ReflectionProperty $property): CustomTypeConverter
    {
        //TODO: Cache the instances?
        $instance = new $className($property);

        if (!($instance instanceof self)) {
            throw new InvalidArgumentException(
                sprintf(
                    "Class '%s' is not a '%s' descendant!",
                    $className, __CLASS__
                )
            );
        }

        foreach (self::$onCreate as $onCreateHook) {
            $onCreateHook($instance);
        }

        return $instance;
    }

    /**
     * @param string $entityName
     * @param string $propertyName
     * @param mixed $propertyValue
     * @return mixed
     * @throws TypeConversionFailedException
     */
    final public function serializeKey(string $entityName, string $propertyName, mixed $propertyValue): mixed
    {
        try {
            return $this->serialize($propertyValue);
        } catch (Throwable $e) {
            throw new TypeConversionFailedException($entityName, $propertyName, 'serialize', static::class, $e);
        }
    }

    /**
     * @param string $entityName
     * @param string $propertyName
     * @param mixed $propertyValue
     * @return mixed
     * @throws TypeConversionFailedException
     */
    final public function deserializeKey(string $entityName, string $propertyName, mixed $propertyValue): mixed
    {
        try {
            return $this->deserialize($propertyValue);
        } catch (Throwable $e) {
            throw new TypeConversionFailedException($entityName, $propertyName, 'deserialize', static::class, $e);
        }
    }

    /**
     * @param mixed $value
     * @return mixed
     * @throws Throwable
     */
    abstract public function serialize(mixed $value): mixed;

    /**
     * @param mixed $value
     * @return mixed
     * @throws Throwable
     */
    abstract public function deserialize(mixed $value): mixed;

    /**
     * @return string
     */
    abstract public function getDatabaseType(): string;

    /**
     * @param mixed $value
     * @param string $type
     * @return mixed
     * @throws InvalidArgumentException
     */
    protected function assertType(mixed $value, string $type): mixed
    {
        if ($value === null && $this->isNullable()) {
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

    /**
     * @return bool
     */
    private function isNullable(): bool
    {
        if (BaseEntity::columnNotNull($this->property)) {
            return false;
        }

        $type = $this->property->getType();
        if (($type instanceof ReflectionNamedType) && !$type->allowsNull()) {
            return false;
        }
        if (($type instanceof ReflectionUnionType) && !$type->allowsNull()) {
            return false;
        }

        return true;
    }

    private function getKey(): string
    {
        return BaseEntity::columnName($this->property);
    }
}

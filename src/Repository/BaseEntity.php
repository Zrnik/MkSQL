<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Zrnik\MkSQL\Repository;

use Attribute;
use JetBrains\PhpStorm\Pure;
use JsonSerializable;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;
use Zrnik\MkSQL\Exceptions\CircularReferenceDetectedException;
use Zrnik\MkSQL\Exceptions\InvalidArgumentException;
use Zrnik\MkSQL\Exceptions\InvalidPropertyTypeException;
use Zrnik\MkSQL\Exceptions\MissingAttributeArgumentException;
use Zrnik\MkSQL\Exceptions\MissingForeignKeyDefinitionInEntityException;
use Zrnik\MkSQL\Exceptions\MultipleForeignKeysTargetingSameClassException;
use Zrnik\MkSQL\Exceptions\PrimaryKeyDefinitionException;
use Zrnik\MkSQL\Exceptions\ReflectionFailedException;
use Zrnik\MkSQL\Exceptions\RequiredClassAttributeMissingException;
use Zrnik\MkSQL\Exceptions\UnableToResolveTypeException;
use Zrnik\MkSQL\Repository\Attributes\ColumnName;
use Zrnik\MkSQL\Repository\Attributes\ColumnType;
use Zrnik\MkSQL\Repository\Attributes\Comment;
use Zrnik\MkSQL\Repository\Attributes\CustomType;
use Zrnik\MkSQL\Repository\Attributes\DefaultValue;
use Zrnik\MkSQL\Repository\Attributes\FetchArray;
use Zrnik\MkSQL\Repository\Attributes\ForeignKey;
use Zrnik\MkSQL\Repository\Attributes\NotNull;
use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\Attributes\Unique;
use Zrnik\MkSQL\Updater;
use Zrnik\MkSQL\Utilities\EntityReflection\EntityReflection;
use Zrnik\MkSQL\Utilities\Reflection;
use function array_key_exists;
use function count;
use function in_array;
use function is_array;
use function is_string;

abstract class BaseEntity implements JsonSerializable
{
    //region Reflection 'Cache'
    /**
     * @var array<string, ReflectionClass<BaseEntity>>
     */
    private static array $reflectionClasses = [];

    /**
     * @param BaseEntity|class-string<BaseEntity> $obj
     * @return ReflectionClass<BaseEntity>
     * @throws ReflectionFailedException
     * @internal
     */
    public static function getReflectionClass(BaseEntity|string $obj): ReflectionClass
    {
        /** @var class-string<BaseEntity> $objKey */
        $objKey = is_string($obj) ? $obj : get_debug_type($obj);
        if (!array_key_exists($objKey, self::$reflectionClasses)) {
            try {
                /**
                 * @var ReflectionClass<BaseEntity> $reflectionInstance
                 * @noinspection PhpRedundantVariableDocTypeInspection
                 * @throws ReflectionException
                 */
                $reflectionInstance = new ReflectionClass($objKey);
                self::$reflectionClasses[$objKey] = $reflectionInstance;
            } catch (ReflectionException $e) {
                throw new ReflectionFailedException(
                    sprintf(
                        "Failed to get reflection of '%s' class!",
                        $objKey
                    ),
                    $e->getCode(), $e
                );
            }
        }
        return self::$reflectionClasses[$objKey];
    }

    /**
     * @param string $propertyName
     * @return ReflectionProperty|null
     * @throws ReflectionFailedException
     */
    public static function propertyReflection(string $propertyName): ?ReflectionProperty
    {
        $reflection = static::getReflectionClass(static::class);
        return Reflection::classGetProperty($reflection, $propertyName);
    }
    //endregion

    //region Override functions
    /**
     * Override this function, if you want to set default values,
     * that cannot be an expression in the property itself.
     * @return array<string, mixed>
     */
    protected function getDefaults(): array
    {
        return [];
    }
    //endregion

    //region Array
    /**
     * @return array<mixed>
     * @throws InvalidArgumentException
     * @throws PrimaryKeyDefinitionException
     * @throws ReflectionFailedException
     */
    public function toArray(): array
    {
        $result = [];
        $reflection = static::getReflectionClass($this);

        foreach ($reflection->getProperties() as $reflectionProperty) {

            if (!$reflectionProperty->isInitialized($this)) {
                continue;
            }

            $propertyName = $reflectionProperty->getName();
            $propertyValue = $this->$propertyName;

            // If there is `#[FetchArray]` attribute, we skip it!
            if (Reflection::propertyHasAttribute($reflectionProperty, FetchArray::class)) {
                continue;
            }

            // If there is a `#[ColumnName]` attribute, we use its name instead.
            $columnNameAttr = Reflection::propertyGetAttribute($reflectionProperty, ColumnName::class);
            if ($columnNameAttr !== null) {
                $propertyName = Reflection::attributeGetArgument($columnNameAttr);
            }

            // If there is a `#[CustomType]` attribute, we use it.

            $propertyValue = static::customTypeSerialize(
                $propertyValue, $reflectionProperty
            );

            // If there is a `#[ForeignKey]` attribute, we insert it's primary key value here!
            $foreignAttributeType = Reflection::propertyGetAttribute($reflectionProperty, ForeignKey::class);
            if ($foreignAttributeType !== null) {

                // That means '$propertyValue' is an object extending 'BaseEntity'
                if ($propertyValue instanceof self) {
                    /** @var BaseEntity $foreignEntityClassName */
                    $foreignEntityClassName = Reflection::attributeGetArgument($foreignAttributeType);
                    $primaryKeyName = $foreignEntityClassName::getPrimaryKeyName();
                    $propertyValue = $propertyValue->toArray()[$primaryKeyName];

                    // Also, we need to update the `$rawData` property!

                } else {
                    throw new InvalidArgumentException(
                        sprintf(
                            "Expected '%s' to be value of '%s', but its '%s' instead!",
                            $propertyName, self::class, get_debug_type($propertyValue)
                        )
                    );
                }
            }

            $result[$propertyName] = $propertyValue;
        }

        foreach (self::getColumnNames() as $columnName) {
            $this->rawData[$columnName] = $result[$columnName] ?? null;
        }

        return $result;
    }

    /**
     * @throws ReflectionFailedException
     * @throws InvalidArgumentException
     * @throws PrimaryKeyDefinitionException
     */
    public function updateRawData(): void
    {
        $data = $this->toArray();
        foreach (self::getColumnNames() as $columnName) {
            $this->rawData[$columnName] = $data[$columnName] ?? null;
        }
    }

    final private function __construct()
    {
    }

    public static function create(): static
    {
        $entity = new static();

        // We MUST initialize `FetchArray` properties as array!
        foreach (EntityReflection::getFetchArrayProperties($entity) as $fetchArrayData) {
            $propertyName = $fetchArrayData->getPropertyName();
            $entity->$propertyName = [];
        }

        return $entity;
    }

    /**
     * @var array<mixed>
     */
    private array $rawData = [];

    /**
     * @return mixed[]
     */
    public function getRawData(): array
    {
        return $this->rawData;
    }

    /**
     * @param iterable<mixed> $iterable
     * @return static
     * @throws InvalidArgumentException
     * @throws ReflectionFailedException
     */
    public static function fromIterable(iterable $iterable): static
    {

        if (is_array($iterable)) {
            /** @var array<mixed> $data */
            $data = $iterable;
        } else {
            /**
             * @var array<mixed> $data
             * @noinspection PhpParamsInspection
             */
            $data = iterator_to_array($iterable);
        }

        $obj = static::create();

        //$obj->rawData = $data;

        foreach (self::getColumnNames() as $columnName) {
            $obj->rawData[$columnName] = $data[$columnName] ?? null;
        }

        $reflection = static::getReflectionClass($obj);
        $defaults = $obj->getDefaults();

        foreach ($reflection->getProperties() as $reflectionProperty) {

            $propertyName = $reflectionProperty->getName();
            $reflectionPropertyName = $reflectionProperty->getName();
            $intrinsicType = $reflectionProperty->getType();


            // -1. If its `#[ForeignKey]`, we skip it, as it should be provided after
            // everything is fetched...
            if (Reflection::propertyHasAttribute($reflectionProperty, ForeignKey::class)) {
                continue;
            }

            // 0. If there is a `#[ColumnName]` attribute, we use its name instead.
            $columnNameAttr = Reflection::propertyGetAttribute($reflectionProperty, ColumnName::class);
            if ($columnNameAttr !== null) {
                $propertyName = Reflection::attributeGetArgument($columnNameAttr);
            }

            // 1. If it has intrinsic default value, let it be.
            $propertyValue = $reflectionProperty->hasDefaultValue()
                ? $reflectionProperty->getDefaultValue() : null;

            // 2. If its `#[FetchArray]`, we initialize empty array!
            if (
                $propertyValue === null
                && Reflection::propertyHasAttribute(
                    $reflectionProperty, FetchArray::class
                )
            ) {
                $propertyValue = [];
            }

            // 3. If we have defined new default value in 'getDefaults',
            // we override intrinsic default.
            if (array_key_exists($propertyName, $defaults)) {
                $propertyValue = $defaults[$propertyName];
            }

            // 4. If we have value in "data", we will use it!
            if (array_key_exists($propertyName, $data)) {
                $propertyValue = $data[$propertyName];
            }

            // 5. If we have a "CustomType" set, we will convert
            // it with it!
            $propertyValue = static::customTypeDeserialize(
                $propertyValue, $reflectionProperty
            );

            if ($intrinsicType !== null && $propertyValue === null && !$intrinsicType->allowsNull()) {
                throw new InvalidArgumentException(
                    sprintf(
                        "Entity '%s' does not allow null for property '%s'!",
                        static::class, $propertyName
                    )
                );
            }


            if ($propertyValue !== null && ($intrinsicType instanceof ReflectionNamedType) && $intrinsicType->isBuiltin()) {
                settype($propertyValue, $intrinsicType->getName());
            }

            // 6. We rewrite the actual value:
            $obj->$reflectionPropertyName = $propertyValue;
        }

        return $obj;
    }

    //endregion

    //region General Info

    //region Column Names
    /**
     * @param ReflectionClass<BaseEntity>|null $reflection
     * @return string[]
     * @throws ReflectionFailedException
     * @noinspection PhpSameParameterValueInspection
     */
    private static function getColumnNames(?ReflectionClass $reflection = null): array
    {
        $reflection = $reflection ?? static::getReflectionClass(static::class);

        $columnNames = [];

        foreach ($reflection->getProperties() as $property) {

            if (Reflection::propertyHasAttribute($property, FetchArray::class)) {
                continue;
            }

            $columnName = $property->getName();
            $ColumnNamePropertyAttribute = Reflection::propertyGetAttribute($property, ColumnName::class);
            if ($ColumnNamePropertyAttribute !== null) {
                $columnName = Reflection::attributeGetArgument($ColumnNamePropertyAttribute);
            }
            $columnNames[$property->getName()] = $columnName;
        }

        return $columnNames;

    }
    //endregion

    //region Table Name
    /**
     * @param ReflectionClass<BaseEntity>|null $reflection
     * @return string
     * @throws MissingAttributeArgumentException
     * @throws ReflectionFailedException
     * @throws RequiredClassAttributeMissingException
     */
    public static function getTableName(?ReflectionClass $reflection = null): string
    {
        $reflection = $reflection ?? static::getReflectionClass(static::class);

        /** @var class-string<Attribute> $tableNameAttributeClassName */
        $tableNameAttributeClassName = TableName::class;

        $tableNameAttr = Reflection::classGetAttribute($reflection, $tableNameAttributeClassName);

        if ($tableNameAttr === null) {
            throw new RequiredClassAttributeMissingException(
                static::class,
                $tableNameAttributeClassName
            );
        }

        $tableName = Reflection::attributeGetArgument($tableNameAttr);

        if ($tableName === null) {
            throw new MissingAttributeArgumentException(
                $tableNameAttr
            );
        }

        return $tableName;
    }
    //endregion

    //region Primary Key Property Reflection
    /**
     * @param ReflectionClass<BaseEntity>|null $reflection
     * @return ReflectionProperty
     * @throws PrimaryKeyDefinitionException
     * @throws ReflectionFailedException
     */
    private static function getPrimaryKeyReflectionProperty(?ReflectionClass $reflection = null): ReflectionProperty
    {
        $reflection = $reflection ?? static::getReflectionClass(static::class);

        /** @var array<ReflectionProperty> $selectedPrimaryKeys */
        $selectedPrimaryKeys = [];
        foreach ($reflection->getProperties() as $reflectionProperty) {
            if (Reflection::propertyHasAttribute($reflectionProperty, PrimaryKey::class)) {
                $selectedPrimaryKeys[] = $reflectionProperty;
            }
        }

        $primaryKeyCount = count($selectedPrimaryKeys);

        if ($primaryKeyCount === 0) {
            throw new PrimaryKeyDefinitionException(
                sprintf(
                    "Class '%s' has no primary key defined! Use '%s' attribute to define one!",
                    $reflection->getName(), PrimaryKey::class
                )
            );
        }

        if ($primaryKeyCount > 1) {
            throw new PrimaryKeyDefinitionException(
                sprintf(
                    "Class '%s' has multiple primary keys defined! Please use only one!",
                    $reflection->getName()
                )
            );
        }

        return $selectedPrimaryKeys[0];
    }
    //endregion

    //region Primary Key Name
    /**
     * @param ReflectionClass<BaseEntity>|null $reflection
     * @return string
     * @throws PrimaryKeyDefinitionException
     * @throws ReflectionFailedException
     */
    public static function getPrimaryKeyName(?ReflectionClass $reflection = null): string
    {
        return self::columnName(self::getPrimaryKeyReflectionProperty($reflection));
    }

    /**
     * @param ReflectionClass<BaseEntity>|null $reflection
     * @return string
     */
    public static function getPrimaryKeyPropertyName(?ReflectionClass $reflection = null): string
    {
        return self::getPrimaryKeyReflectionProperty($reflection)->getName();
    }


    //endregion

    //region Primary Key Type
    /**
     * @param ReflectionClass<BaseEntity>|null $reflection
     * @return string
     * @throws InvalidArgumentException
     * @throws PrimaryKeyDefinitionException
     * @throws ReflectionFailedException
     * @throws UnableToResolveTypeException
     */
    public static function getPrimaryKeyType(?ReflectionClass $reflection = null): string
    {
        $primaryKeyReflectionProperty = self::getPrimaryKeyReflectionProperty($reflection);
        $propertyType = $primaryKeyReflectionProperty->getType();

        if ($propertyType instanceof ReflectionNamedType) {

            if (!$propertyType->allowsNull()) {
                throw new PrimaryKeyDefinitionException(
                    sprintf(
                        "Type of primary key for entity '%s' must be nullable!",
                        static::class
                    )
                );
            }
        } else if ($propertyType instanceof ReflectionUnionType) {

            if (!$propertyType->allowsNull()) {
                throw new PrimaryKeyDefinitionException(
                    sprintf(
                        "Type of primary key for entity '%s' must be nullable!",
                        static::class
                    )
                );
            }
        } else {
            throw new PrimaryKeyDefinitionException(
                sprintf(
                    "Type of primary key for entity '%s' was not resolved!",
                    static::class
                )
            );
        }

        if (!$primaryKeyReflectionProperty->hasDefaultValue() || $primaryKeyReflectionProperty->getDefaultValue() !== null) {
            throw new PrimaryKeyDefinitionException(
                sprintf(
                    "Type of primary key for entity '%s' must have null as its default value!",
                    static::class
                )
            );
        }


        return self::columnType(self::getPrimaryKeyReflectionProperty($reflection));
    }

    /**
     * /**
     * @param ReflectionClass<BaseEntity>|null $reflection
     * @return string
     */
    public static function getIntrinsicPrimaryKeyType(?ReflectionClass $reflection = null): string
    {
        $primaryKeyReflectionProperty = self::getPrimaryKeyReflectionProperty($reflection);
        $propertyType = $primaryKeyReflectionProperty->getType();

        if ($propertyType instanceof ReflectionNamedType) {
            return $propertyType->getName();
        }

        if ($propertyType instanceof ReflectionUnionType) {
            foreach ($propertyType->getTypes() as $reflectionNamedType) {
                if ($reflectionNamedType->isBuiltin()) {
                    return $reflectionNamedType->getName();
                }
            }
        }

        return get_debug_type(0);
    }
    //endregion

    //region Column Name
    public static function columnName(ReflectionProperty $reflectionProperty): string
    {
        $columnNameAttribute = Reflection::propertyGetAttribute($reflectionProperty, ColumnName::class);
        if ($columnNameAttribute !== null) {
            $attributeName = Reflection::attributeGetArgument($columnNameAttribute);
            if (is_string($attributeName)) {
                return $attributeName;
            }
        }
        return $reflectionProperty->getName();
    }
    //endregion

    //region Column Type
    /**
     * @param ReflectionProperty $reflectionProperty
     * @return string
     * @throws InvalidArgumentException
     * @throws PrimaryKeyDefinitionException
     * @throws ReflectionFailedException
     * @throws UnableToResolveTypeException
     */
    public static function columnType(ReflectionProperty $reflectionProperty): string
    {
        // $propertyName = static::columnName($reflectionProperty);
        $foreignAttributeType = Reflection::propertyGetAttribute($reflectionProperty, ForeignKey::class);

        if ($foreignAttributeType !== null) {
            /** @var BaseEntity $foreignEntityClassName */
            $foreignEntityClassName = Reflection::attributeGetArgument($foreignAttributeType);
            return $foreignEntityClassName::getPrimaryKeyType();
        }

        $attributeCustomType = Reflection::propertyGetAttribute(
            $reflectionProperty, CustomType::class
        );

        if ($attributeCustomType !== null) {
            $typeConverter = CustomTypeConverter::initialize(
                Reflection::attributeGetArgument($attributeCustomType), $reflectionProperty
            );

            return $typeConverter->getDatabaseType();
        }

        $attributeType = Reflection::propertyGetAttribute(
            $reflectionProperty, ColumnType::class
        );

        if ($attributeType !== null) {
            // Attribute has priority
            return Reflection::attributeGetArgument($attributeType);
        }

        $intrinsicType = $reflectionProperty->getType();

        if ($intrinsicType instanceof ReflectionNamedType) {
            // Or else, we use intrinsic type definition! (It can f*ck up stuff if it's not int though...)
            return $intrinsicType->getName();
        }

        if ($intrinsicType instanceof ReflectionUnionType) {
            throw new UnableToResolveTypeException(sprintf("Property '%s' has union type. Union types are not supported!", $reflectionProperty->getName()));
        }

        throw new UnableToResolveTypeException(sprintf("Unable to resolve type of '%s'.", $reflectionProperty->getName()));
    }
    //endregion

    //region Column `NOT NULL`
    #[Pure]
    public static function columnNotNull(ReflectionProperty $property): bool
    {
        return Reflection::propertyHasAttribute($property, NotNull::class);
    }
    //endregion

    //region Column `UNIQUE`
    #[Pure]
    public static function columnUnique(ReflectionProperty $property): bool
    {
        return Reflection::propertyHasAttribute($property, Unique::class);
    }
    //endregion

    //region Column `DEFAULT`
    public static function columnDefaultValue(ReflectionProperty $property): mixed
    {
        $defaultValueAttr = Reflection::propertyGetAttribute($property, DefaultValue::class);
        if ($defaultValueAttr !== null) {
            return Reflection::attributeGetArgument($defaultValueAttr);
        }
        return null;
    }
    //endregion

    //region Column `COMMENT`
    public static function columnComment(ReflectionProperty $property): mixed
    {
        $defaultValueAttr = Reflection::propertyGetAttribute($property, Comment::class);
        if ($defaultValueAttr !== null) {
            return Reflection::attributeGetArgument($defaultValueAttr);
        }
        return null;
    }
    //endregion

    //region Column `FOREIGN KEY(s)`
    /**
     * @param ReflectionProperty $property
     * @return array<string>
     * @throws MissingAttributeArgumentException
     * @throws PrimaryKeyDefinitionException
     * @throws ReflectionFailedException
     * @throws RequiredClassAttributeMissingException
     */
    private static function columnForeignKeys(ReflectionProperty $property): array
    {
        $foreignKeyAttributes = Reflection::propertyGetAttributes($property, ForeignKey::class);
        $keys = [];

        foreach ($foreignKeyAttributes as $foreignKeyAttribute) {
            /** @var BaseEntity $foreignClassName */
            $foreignClassName = Reflection::attributeGetArgument($foreignKeyAttribute);
            $keys[] = sprintf(
                '%s.%s',
                $foreignClassName::getTableName(),
                $foreignClassName::getPrimaryKeyName()
            );

        }

        return $keys;
    }
    //endregion

    //region Custom Type Converting
    /**
     * @throws InvalidArgumentException
     */
    public static function customTypeDeserialize(mixed $propertyValue, ReflectionProperty $reflectionProperty): mixed
    {
        $customTypeAttribute = Reflection::propertyGetAttribute(
            $reflectionProperty, CustomType::class
        );

        if ($customTypeAttribute !== null) {
            $converterClassName = Reflection::attributeGetArgument($customTypeAttribute);
            $converter = CustomTypeConverter::initialize($converterClassName, $reflectionProperty);
            $propertyValue = $converter->deserialize($propertyValue);
        }

        return $propertyValue;
    }

    /**
     * @param mixed $propertyValue
     * @param ReflectionProperty $reflectionProperty
     * @return mixed
     * @throws InvalidArgumentException
     */
    public static function customTypeSerialize(
        mixed              $propertyValue,
        ReflectionProperty $reflectionProperty
    ): mixed
    {
        $customTypeAttribute = Reflection::propertyGetAttribute(
            $reflectionProperty, CustomType::class
        );

        if ($customTypeAttribute !== null) {
            $converterClassName = Reflection::attributeGetArgument($customTypeAttribute);
            $converter = CustomTypeConverter::initialize($converterClassName, $reflectionProperty);
            $propertyValue = $converter->serialize($propertyValue);
        }

        return $propertyValue;
    }
    //endregion

    //endregion

    /**
     * @param Updater $updater
     * @param string[] $baseEntitiesWeHaveAlreadySeen
     * @param bool $isFetchArray
     */
    public static function hydrateUpdater(Updater $updater, array $baseEntitiesWeHaveAlreadySeen = [], bool $isFetchArray = false): void
    {
        $reflection = static::getReflectionClass(static::class);

        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        $table = $updater->tableCreate(self::getTableName($reflection), true);
        $table->setPrimaryKeyName(self::getPrimaryKeyName($reflection));
        $table->setPrimaryKeyType(self::getPrimaryKeyType($reflection));

        /** @var array<class-string<BaseEntity>> $hydrateAfter */
        $hydrateAfter = [];

        /** @var array<class-string<BaseEntity>> $fetchArrayEntities */
        $fetchArrayEntities = [];

        $referencedForeignKeys = [];

        // Columns:
        foreach ($properties as $property) {

            //region Primary key is handled, if its primary key, skip it
            if (Reflection::propertyHasAttribute($property, PrimaryKey::class)) {
                continue;
            }
            //endregion

            //region Handle `FetchArray`
            /*
             * If the property has `#[FetchArray]` attribute, we expect it to have
             * foreign key pointing in our way. We are not adding it to the current table!
             */
            $fetchArrayAttribute = Reflection::propertyGetAttribute($property, FetchArray::class);
            if ($fetchArrayAttribute !== null) {

                // Check, if the fetched array is pointing to us!
                $pointerFound = false;
                $subClassName = Reflection::attributeGetArgument($fetchArrayAttribute);

                $reflection = self::getReflectionClass($subClassName);
                foreach ($reflection->getProperties() as $foreignProperty) {
                    $foreignPropertyForeignKeyAttribute = Reflection::propertyGetAttribute($foreignProperty, ForeignKey::class);
                    if ($foreignPropertyForeignKeyAttribute !== null) {
                        $foreignPropertyForeignKeyAttributeValue = Reflection::attributeGetArgument($foreignPropertyForeignKeyAttribute);
                        if ($foreignPropertyForeignKeyAttributeValue === static::class) {
                            $pointerFound = true;
                        }
                        // We also check, if the type is also us, so we can use it with the repository!
                        $type = $foreignProperty->getType();
                        if (($type instanceof ReflectionNamedType) && $type->getName() !== $foreignPropertyForeignKeyAttributeValue) {
                            throw new InvalidPropertyTypeException(
                                $foreignPropertyForeignKeyAttributeValue, $type->getName(),
                                $reflection->getName(), $property->getName(),
                            );
                        }
                    }
                }

                if (!$pointerFound) {
                    throw new MissingForeignKeyDefinitionInEntityException(
                        static::class, $subClassName
                    );
                }

                $fetchArrayEntities[] = $subClassName;

                continue;
            }
            //endregion

            //region Check if referenced foreign key was previously defined (else it will result in an error!)

            /**
             * Instead of returning error, we will hydrate updater with the sub-class.
             * We also need to look for infinite recursion and trow error only in that case.
             */

            $foreignKeyAttribute = Reflection::propertyGetAttribute($property, ForeignKey::class);

            if ($foreignKeyAttribute !== null) {

                $referencedEntityName = Reflection::attributeGetArgument($foreignKeyAttribute);

                if (array_key_exists($referencedEntityName, $referencedForeignKeys)) {
                    throw new MultipleForeignKeysTargetingSameClassException(
                        $reflection->getName(), $referencedEntityName, $referencedForeignKeys[$referencedEntityName]
                    );
                }

                if (!$isFetchArray && in_array($referencedEntityName, $baseEntitiesWeHaveAlreadySeen, true)) {
                    throw new CircularReferenceDetectedException(
                        static::class, $property->getName()
                    );
                }

                $referencedForeignKeys[$referencedEntityName] = $property->getName();
                $hydrateAfter[] = $referencedEntityName;
            }

            //endregion

            $column = $table->columnCreate(
                self::columnName($property),
                self::columnType($property)
            );

            $column->setNotNull(self::columnNotNull($property));
            $column->setUnique(self::columnUnique($property));
            $column->setDefault(self::columnDefaultValue($property));
            $column->setComment(self::columnComment($property));

            foreach (self::columnForeignKeys($property) as $foreignKey) {
                $column->addForeignKey($foreignKey);
            }
        }

        $baseEntitiesWeHaveAlreadySeen[] = static::class;
        foreach ($hydrateAfter as $hydrateEntityClassName) {

            /** @var BaseEntity $hydrateEntity */
            $hydrateEntity = $hydrateEntityClassName;

            if ($isFetchArray && in_array($hydrateEntityClassName, $baseEntitiesWeHaveAlreadySeen, true)) {
                continue;
            }

            $hydrateEntity::hydrateUpdater(
                $updater, $baseEntitiesWeHaveAlreadySeen
            );

            $baseEntitiesWeHaveAlreadySeen[] = $hydrateEntityClassName;
        }


        foreach ($fetchArrayEntities as $fetchArrayEntityClassName) {

            /** @var BaseEntity $fetchArrayEntity */
            $fetchArrayEntity = $fetchArrayEntityClassName;

            $fetchArrayEntity::hydrateUpdater(
                $updater, $baseEntitiesWeHaveAlreadySeen, true
            );
        }


    }

    /**
     * @return mixed
     * @throws PrimaryKeyDefinitionException
     * @throws ReflectionFailedException
     */
    public function getPrimaryKeyValue(): mixed
    {
        $primaryKeyPropertyName = static::getPrimaryKeyPropertyName();
        return $this->$primaryKeyPropertyName;
    }

    /**
     * @throws PrimaryKeyDefinitionException
     * @throws ReflectionFailedException
     */
    public function setPrimaryKeyValue(mixed $newPrimaryKeyValue): void
    {
        $primaryKeyPropertyName = self::getPrimaryKeyReflectionProperty()->getName();
        // TODO: Create tests for this, as it was a bug here!
        if ($newPrimaryKeyValue !== null) {
            // Transfer the type, so we don't get TypeError
            settype($newPrimaryKeyValue, self::getIntrinsicPrimaryKeyType());
        }
        $this->$primaryKeyPropertyName = $newPrimaryKeyValue;

        foreach (EntityReflection::getFetchArrayProperties($this) as $fetchArrayData) {
            foreach (EntityReflection::getForeignKeys($fetchArrayData->getTargetClassName()) as $targetForeignKeyPropertyData) {
                if (static::class === $targetForeignKeyPropertyData->getTargetClassName()) {
                    $thisPropertyName = $fetchArrayData->getPropertyName();
                    $targetPropertyName = $targetForeignKeyPropertyData->getPropertyName();
                    foreach ($this->$thisPropertyName as $value) {
                        $value->$targetPropertyName = $this;
                    }
                }
            }
        }
    }

    public function hash(): string
    {
        return sprintf(
            '%s-%s',
            hash(
                'sha256',
                spl_object_hash($this)
            ),
            $this::getTableName()
        );
    }

    /**
     * @return mixed[]
     */
    public function jsonSerialize(): array
    {
        $result = [];

        $data = $this->toArray();

        // Add 'FetchArray' values:
        $reflection = self::getReflectionClass($this);
        foreach ($reflection->getProperties() as $property) {

            $fetchArrayAttribute = Reflection::propertyGetAttribute(
                $property, FetchArray::class
            );

            if ($fetchArrayAttribute !== null) {
                $propertyName = $property->getName();
                $result[$propertyName] = $this->$propertyName;
            } else {
                $dataKey = $property->getName();

                $reflectionProperty = self::propertyReflection($dataKey);
                if ($reflectionProperty !== null) {
                    $columnNameAttribute = Reflection::propertyGetAttribute($reflectionProperty, ColumnName::class);
                    if ($columnNameAttribute !== null) {
                        $dataKey = Reflection::attributeGetArgument($columnNameAttribute) ?? $dataKey;
                    }
                }

                $result[$property->getName()] = $data[$dataKey];
            }

        }

        return $result;
    }

    public function fixSubEntityForeignKeys(): void
    {
        foreach (EntityReflection::getFetchArrayProperties($this) as $fetchArrayProperty) {
            $propertyName = $fetchArrayProperty->getPropertyName();
            /** @var BaseEntity $childEntity */
            foreach ($this->$propertyName as $childEntity) {
                foreach (EntityReflection::getForeignKeys($childEntity) as $foreignKeyData) {
                    $childEntityForeignKeyPropertyName = $foreignKeyData->getPropertyName();
                    if (
                        $foreignKeyData->getTargetClassName() === self::class
                    ) {
                        $childEntity->$childEntityForeignKeyPropertyName = $this;
                    }
                }
            }
        }
        $this->updateRawData();
    }

}

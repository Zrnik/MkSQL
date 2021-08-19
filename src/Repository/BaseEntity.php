<?php

namespace Zrnik\MkSQL\Repository;

use Attribute;
use JetBrains\PhpStorm\Pure;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;
use Zrnik\MkSQL\Exceptions\InvalidArgumentException;
use Zrnik\MkSQL\Exceptions\InvalidEntityOrderException;
use Zrnik\MkSQL\Exceptions\InvalidTypeException;
use Zrnik\MkSQL\Exceptions\MissingAttributeArgumentException;
use Zrnik\MkSQL\Exceptions\MkSQLException;
use Zrnik\MkSQL\Exceptions\PrimaryKeyDefinitionException;
use Zrnik\MkSQL\Exceptions\ReflectionFailedException;
use Zrnik\MkSQL\Exceptions\RequiredClassAttributeMissingException;
use Zrnik\MkSQL\Exceptions\UnableToResolveTypeException;
use Zrnik\MkSQL\Repository\Attributes\ColumnName;
use Zrnik\MkSQL\Repository\Attributes\ColumnType;
use Zrnik\MkSQL\Repository\Attributes\CustomType;
use Zrnik\MkSQL\Repository\Attributes\DefaultValue;
use Zrnik\MkSQL\Repository\Attributes\FetchArray;
use Zrnik\MkSQL\Repository\Attributes\ForeignKey;
use Zrnik\MkSQL\Repository\Attributes\NotNull;
use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\Attributes\Unique;
use Zrnik\MkSQL\Repository\Types\BooleanType;
use Zrnik\MkSQL\Updater;
use Zrnik\MkSQL\Utilities\Reflection;

abstract class BaseEntity
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
        if (!array_key_exists($objKey, static::$reflectionClasses)) {
            try {
                /**
                 * @var ReflectionClass<BaseEntity> $reflectionInstance
                 * @noinspection PhpRedundantVariableDocTypeInspection
                 */
                $reflectionInstance = new ReflectionClass($objKey);
                static::$reflectionClasses[$objKey] = $reflectionInstance;
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
        return static::$reflectionClasses[$objKey];
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
     * @throws ReflectionException
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
            $customTypeAttribute = Reflection::propertyGetAttribute(
                $reflectionProperty, CustomType::class
            );

            if ($customTypeAttribute !== null) {
                $converterClassName = Reflection::attributeGetArgument($customTypeAttribute);
                $converter = CustomTypeConverter::initialize($converterClassName);
                $propertyValue = $converter->serialize($propertyValue);
            } else {
                // Is there any 'default' converter?
                $type = $reflectionProperty->getType();

                if ($type instanceof ReflectionNamedType) {

                    $converterClassName = match ($type->getName()) {
                        "bool" => BooleanType::class,
                        default => null
                    };

                    if ($converterClassName !== null) {
                        $converter = CustomTypeConverter::initialize($converterClassName);
                        $propertyValue = $converter->serialize($propertyValue);
                    }

                }

            }

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

        foreach (static::getColumnNames() as $columnName) {
            $this->rawData[$columnName] = $result[$columnName] ?? null;
        }

        return $result;
    }

    /**
     * @throws ReflectionFailedException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws PrimaryKeyDefinitionException
     */
    public function updateRawData(): void
    {
        $data = $this->toArray();
        foreach (static::getColumnNames() as $columnName) {
            $this->rawData[$columnName] = $data[$columnName];
        }
    }

    final private function __construct()
    {
    }

    public static function create(): static
    {
        return new static();
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
     * @throws PrimaryKeyDefinitionException
     * @throws ReflectionException
     * @throws ReflectionFailedException
     */
    public static function fromIterable(iterable $iterable): static
    {

        if (is_array($iterable)) {
            /** @var array<mixed> $data */
            $data = $iterable;
        } else {
            /** @var array<mixed> $data */
            $data = iterator_to_array($iterable);
        }

        $obj = static::create();

        //$obj->rawData = $data;

        foreach (static::getColumnNames() as $columnName) {
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

            // 2. If we have defined new default value in 'getDefaults',
            // we override intrinsic default.
            if (array_key_exists($propertyName, $defaults)) {
                $propertyValue = $defaults[$propertyName];
            }

            // 3. If we have value in "data", we will use it!
            if (array_key_exists($propertyName, $data)) {
                $propertyValue = $data[$propertyName];
            }

            // 4. If we have a "CustomType" set, we will convert
            // it with it!

            $customTypeAttribute = Reflection::propertyGetAttribute(
                $reflectionProperty, CustomType::class
            );

            if ($customTypeAttribute !== null) {
                $converterClassName = Reflection::attributeGetArgument($customTypeAttribute);
                $converter = CustomTypeConverter::initialize($converterClassName);
                $propertyValue = $converter->deserialize($propertyValue);
            }

            if ($intrinsicType !== null && $propertyValue === null && !$intrinsicType->allowsNull()) {
                throw new InvalidArgumentException(
                    sprintf(
                        "Entity '%s' does not allow null for property '%s'!",
                        static::class, $propertyName
                    )
                );
            }

            // 4. We rewrite the actual value:
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
     * @throws ReflectionException
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
     * @throws ReflectionException
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
     * @throws ReflectionException
     */
    public static function getPrimaryKeyName(?ReflectionClass $reflection = null): string
    {
        return self::columnName(self::getPrimaryKeyReflectionProperty($reflection));
    }
    //endregion

    //region Primary Key Type
    /**
     * @param ReflectionClass<BaseEntity>|null $reflection
     * @return string
     * @throws PrimaryKeyDefinitionException
     * @throws ReflectionException
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

        if(!$primaryKeyReflectionProperty->hasDefaultValue() || $primaryKeyReflectionProperty->getDefaultValue() !== null) {
            throw new PrimaryKeyDefinitionException(
                sprintf(
                    "Type of primary key for entity '%s' must have null as its default value!",
                    static::class
                )
            );
        }


        return self::columnType(self::getPrimaryKeyReflectionProperty($reflection));
    }
    //endregion

    //region Column Name
    #[Pure]
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
     * @throws ReflectionException
     * @throws UnableToResolveTypeException
     */
    public static function columnType(ReflectionProperty $reflectionProperty): string
    {
        $foreignAttributeType = Reflection::propertyGetAttribute($reflectionProperty, ForeignKey::class);

        if ($foreignAttributeType !== null) {
            /** @var BaseEntity $foreignEntityClassName */
            $foreignEntityClassName = Reflection::attributeGetArgument($foreignAttributeType);
            return $foreignEntityClassName::getPrimaryKeyType();
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
    #[Pure]
    public static function columnDefaultValue(ReflectionProperty $property): mixed
    {
        $defaultValueAttr = Reflection::propertyGetAttribute($property, DefaultValue::class);
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
     * @throws ReflectionException
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
                "%s.%s",
                $foreignClassName::getTableName(),
                $foreignClassName::getPrimaryKeyName()
            );

        }

        return $keys;
    }
    //endregion

    //endregion

    /**
     * @param Updater $updater
     * @throws ReflectionException
     * @throws RequiredClassAttributeMissingException
     * @throws MkSQLException
     * @throws MissingForeignKeyDefinitionInEntityException
     */
    public static function hydrateUpdater(Updater $updater): void
    {
        $reflection = static::getReflectionClass(static::class);

        $table = $updater->tableCreate(self::getTableName($reflection));

        $table->setPrimaryKeyName(self::getPrimaryKeyName($reflection));
        $table->setPrimaryKeyType(self::getPrimaryKeyType($reflection));


        // Columns:
        foreach ($reflection->getProperties() as $property) {

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
                        if (($type instanceof ReflectionNamedType) && $type->getName() !== static::class) {
                            throw new InvalidTypeException(
                                static::class, $type->getName()
                            );
                        }
                    }
                }

                if (!$pointerFound) {
                    throw new MissingForeignKeyDefinitionInEntityException(
                        static::class, $subClassName
                    );
                }

                continue;
            }
            //endregion

            //region Check if referenced foreign key was previously defined (else it will result in an error!)
            $foreignKeyAttribute = Reflection::propertyGetAttribute($property, ForeignKey::class);
            if ($foreignKeyAttribute !== null) {

                $referencedEntityName = Reflection::attributeGetArgument($foreignKeyAttribute);
                /** @var BaseEntity $referencedEntity */
                $referencedEntity = $referencedEntityName;
                $referencedTableName = $referencedEntity::getTableName();
                $updaterTable = $updater->tableGet($referencedTableName);

                if ($updaterTable === null) {
                    throw new InvalidEntityOrderException(
                        static::class, $referencedEntityName
                    );
                }
            }
            //endregion

            $column = $table->columnCreate(
                self::columnName($property),
                self::columnType($property)
            );
            $column->setNotNull(self::columnNotNull($property));
            $column->setUnique(self::columnUnique($property));
            $column->setDefault(self::columnDefaultValue($property));

            foreach (self::columnForeignKeys($property) as $foreignKey) {
                $column->addForeignKey($foreignKey);
            }

        }

    }


    /**
     * @throws ReflectionException
     * @throws PrimaryKeyDefinitionException
     */
    public function getPrimaryKeyValue(): mixed
    {
        $primaryKeyPropertyName = self::getPrimaryKeyReflectionProperty()->getName();
        return $this->$primaryKeyPropertyName;
    }

    /**
     * @throws ReflectionException
     * @throws PrimaryKeyDefinitionException
     * @throws ReflectionFailedException
     */
    public function setPrimaryKeyValue(mixed $newPrimaryKeyValue): void
    {
        $primaryKeyPropertyName = self::getPrimaryKeyReflectionProperty()->getName();
        $this->$primaryKeyPropertyName = $newPrimaryKeyValue;

        foreach ($this->getSubEntities() as $subEntity) {

            $subEntityReflection = static::getReflectionClass($subEntity);
            foreach ($subEntityReflection->getProperties() as $subProperty) {
                $foreignKeyAttribute = Reflection::propertyGetAttribute(
                    $subProperty, ForeignKey::class
                );

                if ($foreignKeyAttribute !== null) {
                    $subPropertyName = $subProperty->getName();
                    $subEntity->$subPropertyName = $this;
                }
            }
        }


    }

    /**
     * @return BaseEntity[]
     * @throws ReflectionFailedException
     */
    public function getSubEntities(): array
    {
        $reflection = self::getReflectionClass(static::class);
        $subEntities = [];

        foreach ($reflection->getProperties() as $property) {
            $fetchArrayAttr = Reflection::propertyGetAttribute($property, FetchArray::class);
            if ($fetchArrayAttr !== null) {
                $propertyName = $property->getName();
                $entityList = $this->$propertyName;
                foreach ($entityList as $subEntity) {
                    $subEntities[] = $subEntity;
                }
            }
        }

        return $subEntities;
    }


}

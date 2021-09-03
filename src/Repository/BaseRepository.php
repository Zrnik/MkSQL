<?php

namespace Zrnik\MkSQL\Repository;

use PDO;
use ReflectionException;
use ReflectionNamedType;
use Zrnik\MkSQL\Exceptions\InvalidArgumentException;
use Zrnik\MkSQL\Exceptions\MissingAttributeArgumentException;
use Zrnik\MkSQL\Exceptions\MkSQLException;
use Zrnik\MkSQL\Exceptions\PrimaryKeyDefinitionException;
use Zrnik\MkSQL\Exceptions\ReflectionFailedException;
use Zrnik\MkSQL\Exceptions\RequiredClassAttributeMissingException;
use Zrnik\MkSQL\Repository\Attributes\ColumnName;
use Zrnik\MkSQL\Repository\Attributes\FetchArray;
use Zrnik\MkSQL\Repository\Attributes\ForeignKey;
use Zrnik\MkSQL\Utilities\Reflection;

class BaseRepository
{

    public function __construct(protected PDO $pdo)
    {
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * @param BaseEntity|BaseEntity[] $entities
     * @throws ReflectionException
     * @throws InvalidArgumentException
     * @throws PrimaryKeyDefinitionException
     * @throws MkSQLException
     */
    public function save(BaseEntity|array $entities): void
    {
        if ($entities instanceof BaseEntity) {
            /** @var BaseEntity[] $entities */
            $entities = [$entities];
        }

        /** @var BaseEntity[] $insert */
        $insert = [];
        /** @var BaseEntity[] $update */
        $update = [];
        /** @var BaseEntity $entity */
        foreach ($entities as $entity) {
            if ($entity->getPrimaryKeyValue() === null) {
                $insert[] = $entity;
            } else {
                $update[] = $entity;
            }
        }

        if (count($insert) > 0) {
            $this->insert($insert);
        }

        if (count($update) > 0) {
            $this->update($update);
        }
    }

    /**
     * @param BaseEntity[] $entities
     * @throws MkSQLException
     * @throws ReflectionException
     */
    private function insert(array $entities): void
    {
        foreach ($entities as $entity) {

            $data = $entity->toArray();

            $sql = sprintf(
                "INSERT INTO %s (%s) VALUES (%s)",
                $entity::getTableName(),
                implode(
                    ",",
                    array_keys($data)
                ), implode(
                    ",",
                    array_map(
                        static function (string $key) {
                            return ":" . $key;
                        },
                        array_keys($data)
                    )
                )
            );

            $convertedKeyData = [];

            foreach ($data as $key => $value) {
                $convertedKeyData[":" . $key] = $value;
            }

            $statement = $this->pdo->prepare($sql);

            $statement->execute($convertedKeyData);

            $entity->setPrimaryKeyValue($this->pdo->lastInsertId());

            $this->save($entity->getSubEntities());

            $entity->updateRawData();
        }

    }

    /**
     * @param BaseEntity[] $entities
     * @throws InvalidArgumentException
     * @throws PrimaryKeyDefinitionException
     * @throws ReflectionException
     * @throws MissingAttributeArgumentException
     * @throws ReflectionFailedException
     * @throws RequiredClassAttributeMissingException
     * @throws MkSQLException
     */
    private function update(array $entities): void
    {

        foreach ($entities as $entity) {

            $data = $entity->toArray();
            $primaryKeyName = $entity::getPrimaryKeyName();
            $primaryKeyValue = $data[$primaryKeyName];
            unset($data[$primaryKeyName]);

            //dump($data);

            $sql = sprintf(
                /** @lang */"UPDATE %s SET %s WHERE %s=%s",
                $entity::getTableName(),
                implode(
                    ", ",
                    array_map(
                        static function ($key) {
                            return $key . "=:" . $key;
                        },
                        array_keys($data)
                    )
                )
                , $primaryKeyName, ":" . $primaryKeyName
            );

            $statement = $this->pdo->prepare($sql);

            $data[$primaryKeyName] = $primaryKeyValue;

            $statement->execute($data);

            $this->save($entity->getSubEntities());

            $entity->updateRawData();
        }

    }


    /**
     * @param string $baseEntityClassString
     * @param mixed $primaryKeyValue
     * @return ?BaseEntity
     * @throws InvalidArgumentException
     * @throws MissingAttributeArgumentException
     * @throws PrimaryKeyDefinitionException
     * @throws ReflectionException
     * @throws ReflectionFailedException
     * @throws RequiredClassAttributeMissingException
     */
    public function getResultByPrimaryKey(string $baseEntityClassString, mixed $primaryKeyValue): ?BaseEntity
    {
        /** @var BaseEntity $baseEntity */
        $baseEntity = $baseEntityClassString;
        $primaryKey = $baseEntity::getPrimaryKeyName();
        return $this->getResultByKey($baseEntityClassString, $primaryKey, $primaryKeyValue);
    }

    /**
     * @param string $baseEntityClassString
     * @param mixed $primaryKeyValue
     * @return BaseEntity[]
     * @throws InvalidArgumentException
     * @throws MissingAttributeArgumentException
     * @throws PrimaryKeyDefinitionException
     * @throws ReflectionException
     * @throws ReflectionFailedException
     * @throws RequiredClassAttributeMissingException
     */
    public function getResultsByPrimaryKey(string $baseEntityClassString, mixed $primaryKeyValue): array
    {
        /** @var BaseEntity $baseEntity */
        $baseEntity = $baseEntityClassString;
        $primaryKey = $baseEntity::getPrimaryKeyName();
        return $this->getResultsByKey($baseEntityClassString, $primaryKey, $primaryKeyValue);
    }

    /**
     * @param string $baseEntityClassString
     * @param string $key
     * @param mixed $value
     * @return ?BaseEntity
     * @throws InvalidArgumentException
     * @throws MissingAttributeArgumentException
     * @throws PrimaryKeyDefinitionException
     * @throws ReflectionException
     * @throws ReflectionFailedException
     * @throws RequiredClassAttributeMissingException
     */
    public function getResultByKey(string $baseEntityClassString, string $key, mixed $value): ?BaseEntity
    {
        $result = $this->getResultsByKeys($baseEntityClassString, $key, [$value]);
        if (count($result) > 0) {
            return $result[0];
        }
        return null;
    }

    /**
     * @param string $baseEntityClassString
     * @return BaseEntity[]
     * @throws MissingAttributeArgumentException
     * @throws ReflectionException
     * @throws ReflectionFailedException
     * @throws RequiredClassAttributeMissingException
     * @throws PrimaryKeyDefinitionException
     * @throws InvalidArgumentException
     */
    public function getAll(string $baseEntityClassString): array
    {
        //TODO: This is a hack `WHERE 1 = 1`... Can this be done better way?
        return $this->getResultsByKey($baseEntityClassString);
    }

    /**
     * @param string $baseEntityClassString
     * @param string|null $key
     * @param mixed|null $value
     * @return BaseEntity[]
     * @throws InvalidArgumentException
     * @throws MissingAttributeArgumentException
     * @throws PrimaryKeyDefinitionException
     * @throws ReflectionException
     * @throws ReflectionFailedException
     * @throws RequiredClassAttributeMissingException
     */
    public function getResultsByKey(string $baseEntityClassString, ?string $key = null, mixed $value = null): array
    {
        if(is_array($value)) {
            throw new InvalidArgumentException("For array value, please use 'getResultsByKeys' method!");
        }


        return $this->getResultsByKeys($baseEntityClassString, $key, $value === null ? [] : [$value]);
    }

    /**
     * @param string $baseEntityClassString
     * @param string|null $key
     * @param array<mixed> $values
     * @return BaseEntity[]
     * @throws InvalidArgumentException
     * @throws MissingAttributeArgumentException
     * @throws PrimaryKeyDefinitionException
     * @throws ReflectionException
     * @throws ReflectionFailedException
     * @throws RequiredClassAttributeMissingException
     */
    public function getResultsByKeys(string $baseEntityClassString,?string $key = null, array $values = [], int $level = 0): array
    {
        /** @var BaseEntity $baseEntity */
        $baseEntity = $baseEntityClassString;

        $tableName = $baseEntity::getTableName();

        $sql = sprintf("SELECT * FROM %s", $tableName);

        if ($key !== null) {

            // Check if the key has `#[ColumnName]` attribute
            foreach(BaseEntity::getReflectionClass($baseEntity)->getProperties() as $reflectionProperty) {
                if($reflectionProperty->getName() === $key) {
                    $columnNameAttribute = Reflection::propertyGetAttribute($reflectionProperty, ColumnName::class);
                    if($columnNameAttribute !== null) {
                        $key = Reflection::attributeGetArgument($columnNameAttribute);
                    }
                }
            }

            if(count($values) === 0) {
                return [];
            }

            $sql = sprintf(
                "SELECT * FROM %s WHERE %s IN (%s)",
                $tableName, $key, str_repeat('?,', count($values) - 1) . '?'
            );
        }

        $statement = $this->pdo->prepare($sql);
        //dump($values);
        $statement->execute($values);
        $results = $statement->fetchAll();
        if ($results === false) {
            return [];
        }

        //region Sub-Fetches

        $usedPrimaryKeyList = [];
        //region PrimaryKeyList
        foreach ($results as $result) {
            $usedPrimaryKeyList[] = $result[$baseEntity::getPrimaryKeyName()];
        }
        //endregion

        $subElements = [];

        foreach (BaseEntity::getReflectionClass($baseEntity)->getProperties() as $thisEntityProperty) {
            $fetchArrayAttribute = Reflection::propertyGetAttribute($thisEntityProperty, FetchArray::class);
            if ($fetchArrayAttribute !== null) {

                $subEntityClassName = Reflection::attributeGetArgument($fetchArrayAttribute);
                $pointer = null;
                $pointerProperty = null;
                foreach (BaseEntity::getReflectionClass($subEntityClassName)->getProperties() as $subEntityProperty) {
                    $subEntityForeignKeyAttribute = Reflection::propertyGetAttribute($subEntityProperty, ForeignKey::class);
                    if (($subEntityForeignKeyAttribute !== null) && Reflection::attributeGetArgument($subEntityForeignKeyAttribute) === $baseEntityClassString) {
                        $pointer = $subEntityProperty;
                        $pointerProperty = $subEntityProperty->getName();
                        $columnNamePointerAttribute = Reflection::propertyGetAttribute($subEntityProperty, ColumnName::class);
                        if ($columnNamePointerAttribute !== null) {
                            $pointer = Reflection::attributeGetArgument($columnNamePointerAttribute);
                        }
                    }
                }

                if ($pointer === null) {
                    continue;
                }

                $subElements[$thisEntityProperty->getName()] = [
                    "className" => $subEntityClassName,
                    "pointingColumnName" => $pointer,
                    "pointingPropertyName" => $pointerProperty,
                    "data" => []
                ];

                $subResults = $this->getResultsByKeys(
                    $subEntityClassName, $pointer, $usedPrimaryKeyList, $level + 1
                );

                foreach ($subResults as $subResult) {
                    $raw = $subResult->getRawData();
                    $dataKey = $raw[$pointer];
                    if (!array_key_exists($dataKey, $subElements[$thisEntityProperty->getName()]["data"])) {
                        $subElements[$thisEntityProperty->getName()]["data"][$dataKey] = [];
                    }
                    $subElements[$thisEntityProperty->getName()]["data"][$dataKey][] = $subResult;
                }
            }
        }

        $resultEntities = [];

        foreach ($results as $data) {

            $primaryKeyValue = $data[$baseEntity::getPrimaryKeyName()];

            foreach($subElements as $subElementKey => $subElementProperties) {
                $data[$subElementKey] = $subElementProperties["data"][$primaryKeyValue];
            }

            $entity = $baseEntity::fromIterable(
                $data
            );

            foreach($subElements as $subElementKey => $subElementProperties) {
                $subElementEntityPointingPropertyName = $subElementProperties["pointingPropertyName"];
                foreach($entity->$subElementKey as $subElementEntity) {
                    $subElementEntity->$subElementEntityPointingPropertyName = $entity;
                }
            }

            if($level === 0) {
                // Is it level 0? Check if any `#[ForeignKey]` is unset, and if yes, fetch it!
                foreach($entity::getReflectionClass($entity)->getProperties() as $reflectionProperty) {
                    if(Reflection::propertyHasAttribute($reflectionProperty, ForeignKey::class)) {
                        if(!$reflectionProperty->isInitialized($entity) ) {
                            $type = $reflectionProperty->getType();
                            if($type instanceof ReflectionNamedType) {
                                $propertyName = $reflectionProperty->getName();
                                $columnName = $reflectionProperty->getName();
                                $columnNameAttribute = Reflection::propertyGetAttribute($reflectionProperty, ColumnName::class);

                                if($columnNameAttribute !== null) {
                                    $columnName = Reflection::attributeGetArgument($columnNameAttribute) ?? $columnName;
                                }

                                $entity->$propertyName = $this->getResultByPrimaryKey(
                                    $type->getName(), $entity->getRawData()[$columnName]
                                );
                            }
                        }
                    }
                }
            }

            $resultEntities[] = $entity;
        }

        return $resultEntities;
    }

    /**
     * @param class-string<BaseEntity> $className
     * @param string $propertyName
     * @return array<mixed>
     * @throws MissingAttributeArgumentException
     * @throws ReflectionException
     * @throws ReflectionFailedException
     * @throws RequiredClassAttributeMissingException
     * @throws InvalidArgumentException
     */
    public function distinctValues(string $className, string $propertyName): array
    {
        $reflection = BaseEntity::getReflectionClass($className);
        $tableName = BaseEntity::getTableName($reflection);

        $property = Reflection::classGetProperty($reflection, $propertyName);
        if($property === null) {
            throw new InvalidArgumentException(
                sprintf(
                    "Property '%s' does not exists on class '%s'!",
                    $propertyName, $className
                )
            );
        }
        $columnName = BaseEntity::columnName($property);

        $sql = sprintf(
            'SELECT DISTINCT %s FROM %s',
            $columnName, $tableName
        );

        $pdoStatement = $this->pdo->query($sql);

        if($pdoStatement === false) {
            return []; // Dafuq? Guess we should handle this somehow...
        }

        $data = $pdoStatement->fetchAll(PDO::FETCH_ASSOC);

        if($data === false) {
            return []; // Dafuq? Guess we should handle this somehow...
        }

        $result = [];

        foreach($data as $row) {
            $result[] = BaseEntity::customTypeDeserialize(
                $row[$columnName], $property
            );
        }

        return $result;
    }
}

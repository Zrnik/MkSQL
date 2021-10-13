<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Zrnik\MkSQL\Repository;

use PDO;
use Zrnik\MkSQL\Exceptions\InvalidArgumentException;
use Zrnik\MkSQL\Repository\Fetcher\Fetcher;
use Zrnik\MkSQL\Utilities\Reflection;
use function count;
use function is_array;

abstract class BaseRepository
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
     * @return string[]
     */
    public function save(BaseEntity|array $entities): array
    {
        /** @var string[] $savedHashList */
        $savedHashList = [];
        $this->saveReal($entities, $savedHashList);
        return $savedHashList;
    }

    /**
     * @param BaseEntity|BaseEntity[] $entities
     * @param string[] $savedHashList
     */
    public function saveReal(BaseEntity|array $entities, array &$savedHashList): void
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
            $this->insert($insert, $savedHashList);
        }

        if (count($update) > 0) {
            $this->update($update, $savedHashList);
        }
    }

    /**
     * @param BaseEntity[] $entities
     * @param string[] $savedHashList
     */
    private function insert(array $entities, array &$savedHashList): void
    {
        $subEntitiesToSave = [];

        foreach ($entities as $entity) {

            $data = $entity->toArray();

            if ($entity->getPrimaryKeyValue() !== null) {
                // Hello sir, I would like to inform you,
                // that the process of saving already
                // given a primary key value to you...

                // would you mind do 'update'
                // instead of 'saving'?

                // Thank you very much!
                $this->saveReal([$entity], $savedHashList);
                continue;
            }

            $sql = sprintf(
                'INSERT INTO %s (%s) VALUES (%s)',
                $entity::getTableName(),
                implode(
                    ',',
                    array_keys($data)
                ), implode(
                    ',',
                    array_map(
                        static function (string $key) {
                            return ':' . $key;
                        },
                        array_keys($data)
                    )
                )
            );

            $convertedKeyData = [];

            foreach ($data as $key => $value) {
                $convertedKeyData[':' . $key] = $value;
            }

            $statement = $this->pdo->prepare($sql);

            $statement->execute($convertedKeyData);

            $entity->setPrimaryKeyValue($this->pdo->lastInsertId());

            $subEntities = $entity->getSubEntities($savedHashList);


            foreach ($subEntities as $subEntity) {
                $subEntitiesToSave[] = $subEntity;
            }

            $entity->updateRawData();
        }

        $this->saveReal($subEntitiesToSave, $savedHashList);
    }

    /**
     * @param BaseEntity[] $entities
     * @param string[] $savedHashList
     */
    private function update(array $entities, array &$savedHashList): void
    {
        $subEntitiesToSave = [];

        foreach ($entities as $entity) {

            $data = $entity->toArray();
            $primaryKeyName = $entity::getPrimaryKeyName();
            $primaryKeyValue = $data[$primaryKeyName];
            unset($data[$primaryKeyName]);

            if ($primaryKeyValue === null) {
                $this->saveReal([$entity], $savedHashList);
                continue;
            }

            $sql = sprintf(
            /** @lang */ 'UPDATE %s SET %s WHERE %s=%s',
                $entity::getTableName(),
                implode(
                    ', ',
                    array_map(
                        static function ($key) {
                            return $key . '=:' . $key;
                        },
                        array_keys($data)
                    )
                ),
                $primaryKeyName,
                ':' . $primaryKeyName
            );

            $statement = $this->pdo->prepare($sql);

            $data[$primaryKeyName] = $primaryKeyValue;

            $statement->execute($data);

            $subEntities = $entity->getSubEntities($savedHashList);

            foreach ($subEntities as $subEntity) {
                $subEntitiesToSave[] = $subEntity;
                $savedHashList[] = $subEntity->hash();
            }

            $entity->updateRawData();
        }

        $this->saveReal($subEntitiesToSave, $savedHashList);
    }


    /**
     * @param class-string<BaseEntity> $baseEntityClassString
     * @param mixed $primaryKeyValue
     * @return ?BaseEntity
     */
    public function getResultByPrimaryKey(string $baseEntityClassString, mixed $primaryKeyValue): ?BaseEntity
    {
        /** @var BaseEntity $baseEntity */
        $baseEntity = $baseEntityClassString;
        $primaryKey = $baseEntity::getPrimaryKeyName();
        return $this->getResultByKey($baseEntityClassString, $primaryKey, $primaryKeyValue);
    }

    /**
     * @param class-string<BaseEntity> $baseEntityClassString
     * @param mixed $primaryKeyValue
     * @return BaseEntity[]
     */
    public function getResultsByPrimaryKey(string $baseEntityClassString, mixed $primaryKeyValue): array
    {
        /** @var BaseEntity $baseEntity */
        $baseEntity = $baseEntityClassString;
        $primaryKey = $baseEntity::getPrimaryKeyName();
        return $this->getResultsByKey($baseEntityClassString, $primaryKey, $primaryKeyValue);
    }

    /**
     * @param class-string<BaseEntity> $baseEntityClassString
     * @param string $key
     * @param mixed $value
     * @return ?BaseEntity
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
     * @param class-string<BaseEntity> $baseEntityClassString
     * @return BaseEntity[]
     */
    public function getAll(string $baseEntityClassString): array
    {
        //TODO: This is a hack `WHERE 1 = 1`... Can this be done better way?
        return $this->getResultsByKey($baseEntityClassString);
    }

    /**
     * @param class-string<BaseEntity> $baseEntityClassString
     * @param string|null $key
     * @param mixed|null $value
     * @return BaseEntity[]
     */
    public function getResultsByKey(string $baseEntityClassString, ?string $key = null, mixed $value = null): array
    {
        if (is_array($value)) {
            throw new InvalidArgumentException("For array value, please use 'getResultsByKeys' method!");
        }


        return $this->getResultsByKeys(
            $baseEntityClassString, $key, $value === null ? [] : [$value]
        );
    }

    /**
     * @param class-string<BaseEntity> $baseEntityClassString
     * @param string|null $key
     * @param array<mixed> $values
     * @return BaseEntity[]
     * @noinspection PhpFunctionCyclomaticComplexityInspection
     * @noinspection PhpComplexFunctionInspection
     */
    public function getResultsByKeys(
        string  $baseEntityClassString,
        ?string $key = null,
        array   $values = [],
    ): array
    {
        return (new Fetcher($this->getPdo()))->getResultsByKeys($baseEntityClassString, $key, $values);


        /* * @var BaseEntity $baseEntity * /
        $baseEntity = $baseEntityClassString;

        if ($fetchObjectStorage === null) {
            $fetchObjectStorage = new FetchObjectStorage();
        }

        $tableName = $baseEntity::getTableName();

        $sql = sprintf('SELECT * FROM %s', $tableName);

        if ($key !== null) {

            // Check if the key has `#[ColumnName]` attribute
            foreach (BaseEntity::getReflectionClass($baseEntity)->getProperties() as $reflectionProperty) {
                if ($reflectionProperty->getName() === $key) {
                    $columnNameAttribute = Reflection::propertyGetAttribute($reflectionProperty, ColumnName::class);
                    if ($columnNameAttribute !== null) {
                        $key = Reflection::attributeGetArgument($columnNameAttribute);
                    }
                }
            }

            if (count($values) === 0) {
                return [];
            }

            $sql = sprintf(
                'SELECT * FROM %s WHERE %s IN (%s)',
                $tableName, $key, str_repeat('?,', count($values) - 1) . '?'
            );
        }

        $statement = $this->pdo->prepare($sql);

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
                /** @var ?string $pointer * /
                $pointer = null;
                $pointerProperty = null;
                foreach (BaseEntity::getReflectionClass($subEntityClassName)->getProperties() as $subEntityProperty) {
                    $subEntityForeignKeyAttribute = Reflection::propertyGetAttribute($subEntityProperty, ForeignKey::class);
                    if (($subEntityForeignKeyAttribute !== null) && Reflection::attributeGetArgument($subEntityForeignKeyAttribute) === $baseEntityClassString) {
                        $pointerProperty = $subEntityProperty->getName();
                        $columnNamePointerAttribute = Reflection::propertyGetAttribute($subEntityProperty, ColumnName::class);
                        if ($columnNamePointerAttribute !== null) {
                            $pointer = Reflection::attributeGetArgument($columnNamePointerAttribute);
                        } else {
                            $pointer = $pointerProperty;
                        }
                    }
                }

                if ($pointer === null) {
                    continue;
                }

                $subElements[$thisEntityProperty->getName()] = [
                    'className' => $subEntityClassName,
                    'pointingColumnName' => $pointer,
                    'pointingPropertyName' => $pointerProperty,
                    'data' => []
                ];

                $subResults = $this->getResultsByKeys(
                    $subEntityClassName, $pointer, $usedPrimaryKeyList, $level + 1, $fetchObjectStorage
                );

                foreach ($subResults as $subResult) {
                    $raw = $subResult->getRawData();
                    $dataKey = $raw[$pointer];
                    if (!array_key_exists($dataKey, $subElements[$thisEntityProperty->getName()]['data'])) {
                        $subElements[$thisEntityProperty->getName()]['data'][$dataKey] = [];
                    }
                    $subElements[$thisEntityProperty->getName()]['data'][$dataKey][] = $subResult;
                }
            }
        }

        $resultEntities = [];

        foreach ($results as $data) {

            $primaryKeyValue = $data[$baseEntity::getPrimaryKeyName()];

            foreach ($subElements as $subElementKey => $subElementProperties) {
                if (array_key_exists($primaryKeyValue, $subElementProperties['data'])) {
                    $data[$subElementKey] = $subElementProperties['data'][$primaryKeyValue];
                }
            }

            $entity =
                $fetchObjectStorage->getObject(
                    $baseEntityClassString, $primaryKeyValue,
                    function () use ($baseEntity, $data) {
                        return $baseEntity::fromIterable(
                            $data
                        );
                    }
                );


            foreach ($subElements as $subElementKey => $subElementProperties) {
                $subElementEntityPointingPropertyName = $subElementProperties['pointingPropertyName'];
                foreach ($entity->$subElementKey as $subElementEntity) {
                    $subElementEntity->$subElementEntityPointingPropertyName = $entity;
                }
            }


            /*if ($level !== 0) {
                foreach ($entity::getReflectionClass($entity)->getProperties() as $reflectionProperty) {
                    if (
                        Reflection::propertyHasAttribute($reflectionProperty, ForeignKey::class)
                        && !$reflectionProperty->isInitialized($entity)
                    ) {

                        //dump("Create Foreign Key " . $reflectionProperty->getName() );

                        $type = $reflectionProperty->getType();
                        if ($type instanceof ReflectionNamedType) {
                            $propertyName = $reflectionProperty->getName();
                            $columnName = $reflectionProperty->getName();
                            $columnNameAttribute = Reflection::propertyGetAttribute($reflectionProperty, ColumnName::class);

                            if ($columnNameAttribute !== null) {
                                $columnName = Reflection::attributeGetArgument($columnNameAttribute) ?? $columnName;
                            }

                            $primaryKeyValueOfForeignKey = $entity->getRawData()[$columnName];
                            $foreignKeyEntityBaseEntityClassName = $type->getName();


                            $entity->$propertyName = $fetchObjectStorage->getObject(
                                $foreignKeyEntityBaseEntityClassName, $primaryKeyValueOfForeignKey,
                                function () use ($type, $primaryKeyValueOfForeignKey, $fetchObjectStorage) {
                                    return $this->getResultByPrimaryKey(
                                        $type->getName(), $primaryKeyValueOfForeignKey, $fetchObjectStorage
                                    );
                                }
                            );
                        }
                    }
                }
            }* /


            // Is it level 0? Check if any `#[ForeignKey]` is unset, and if yes, fetch it!
            foreach ($entity::getReflectionClass($entity)->getProperties() as $reflectionProperty) {
                if (Reflection::propertyHasAttribute($reflectionProperty, ForeignKey::class) && !$reflectionProperty->isInitialized($entity)) {
                    $type = $reflectionProperty->getType();
                    if ($type instanceof ReflectionNamedType) {

                        /** @var BaseEntity $foreignTypeBaseEntity * /
                        $foreignTypeBaseEntity = $type->getName();

                        $propertyName = $reflectionProperty->getName();
                        $columnName = $reflectionProperty->getName();
                        $columnNameAttribute = Reflection::propertyGetAttribute($reflectionProperty, ColumnName::class);



                        if ($columnNameAttribute !== null) {
                            $columnName = Reflection::attributeGetArgument($columnNameAttribute) ?? $columnName;
                        }

                        $primaryKeyValueOfForeignKey = $entity->getRawData()[$columnName];

                        if ($level === 0) {
                            $entity->$propertyName = $this->getResultByPrimaryKey(
                                $type->getName(), $primaryKeyValueOfForeignKey, $fetchObjectStorage
                            );
                        } else {
                            if ($fetchObjectStorage->hasObject($type->getName(), $primaryKeyValueOfForeignKey)) {
                                $entity->$propertyName = $fetchObjectStorage->getStoredObject($type->getName(), $primaryKeyValueOfForeignKey);
                            } else {
                                // Only prepare:
                                $entity->$propertyName = $foreignTypeBaseEntity::prepare($primaryKeyValueOfForeignKey);
                                //dump('Skipped - notExists!');
                            }
                        }
                    }
                }
            }


            $resultEntities[] = $entity;
        }

        return $fetchObjectStorage->linkRecursiveObjects($resultEntities);
        */
    }

    /**
     * @param class-string<BaseEntity> $className
     * @param string $propertyName
     * @return array<mixed>
     * @noinspection SpellCheckingInspection
     */
    public function distinctValues(string $className, string $propertyName): array
    {
        $reflection = BaseEntity::getReflectionClass($className);
        $tableName = BaseEntity::getTableName($reflection);

        $property = Reflection::classGetProperty($reflection, $propertyName);
        if ($property === null) {
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

        if ($pdoStatement === false) {
            return []; // Dafuq? Guess we should handle this somehow...
        }

        $data = $pdoStatement->fetchAll(PDO::FETCH_ASSOC);

        if ($data === false) {
            return []; // Dafuq? Guess we should handle this somehow...
        }

        $result = [];

        foreach ($data as $row) {
            $result[] = BaseEntity::customTypeDeserialize(
                $row[$columnName], $property
            );
        }

        return $result;
    }
}

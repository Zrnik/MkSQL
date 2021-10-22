<?php declare(strict_types=1);

namespace Zrnik\MkSQL\Repository\Saver;

use JetBrains\PhpStorm\Pure;
use PDO;
use Zrnik\MkSQL\Exceptions\MkSQLException;
use Zrnik\MkSQL\Repository\BaseEntity;
use Zrnik\MkSQL\Utilities\EntityReflection\EntityReflection;
use function array_key_exists;
use function count;
use function in_array;

class Saver
{
    private const DEFAULT_INSERT_CHUNK_SIZE = 500;

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param BaseEntity[] $entities
     */
    public function saveEntities(array $entities): void
    {
        $entityList = $this->listAllEntities($entities);

        $updateEntities = [];
        $insertEntities = [];

        foreach ($entityList as $classEntityList) {
            foreach ($classEntityList as $entity) {
                if ($entity->getPrimaryKeyValue() === null) {
                    $insertEntities[] = $entity;
                } else {
                    $updateEntities[] = $entity;
                }
            }
        }

        $orderedInserts = $this->sortInsertTableEntities($insertEntities);


        $chunkByTables = self::chunkByTables($orderedInserts);

        foreach ($chunkByTables as $insertChunk) {
            foreach (array_chunk($insertChunk, self::DEFAULT_INSERT_CHUNK_SIZE) as $chunk) {
                $this->insert($chunk);
            }
        }

        $this->pdo->beginTransaction();
        foreach ($updateEntities as $updateEntity) {
            $this->update($updateEntity);
        }
        $this->pdo->commit();
    }

    /**
     * @param BaseEntity[] $entities
     * @return array<class-string<BaseEntity>, array<int|string, BaseEntity>>
     */
    private function listAllEntities(array $entities): array
    {
        $entityList = [];

        foreach ($entities as $entity) {
            $this->fillEntityListRecursively($entity, $entityList);
        }

        return $entityList;
    }

    /**
     * @param BaseEntity $entity
     * @param array<class-string<BaseEntity>, array<int|string, BaseEntity>> $entityList
     */
    private function fillEntityListRecursively(BaseEntity $entity, array &$entityList): void
    {
        if (!array_key_exists($entity::class, $entityList)) {
            $entityList[$entity::class] = [];
        }

        if (!array_key_exists($entity->hash(), $entityList[$entity::class])) {

            foreach ($this->supEntitiesOf($entity) as $subEntity) {
                $this->fillEntityListRecursively($subEntity, $entityList);
            }

            $entityList[$entity::class][$entity->hash()] = $entity;

            foreach ($this->subEntitiesOf($entity) as $subEntity) {
                $this->fillEntityListRecursively($subEntity, $entityList);
            }

            foreach ($this->supEntitiesOf($entity) as $subEntity) {
                $this->fillEntityListRecursively($subEntity, $entityList);
            }

        }
    }

    /**
     * Sub entities (fetch array)
     * @param BaseEntity $entity
     * @return BaseEntity[]
     */
    private function subEntitiesOf(BaseEntity $entity): array
    {
        $subEntities = [];

        foreach (EntityReflection::getFetchArrayProperties($entity) as $fetchArrayData) {
            $propertyName = $fetchArrayData->getPropertyName();
            foreach ($entity->$propertyName as $subEntity) {
                $subEntities[] = $subEntity;
            }
        }

        return $subEntities;
    }

    /**
     * Superior entities (foreign key)
     * @param BaseEntity $entity
     * @return BaseEntity[]
     */
    private function supEntitiesOf(BaseEntity $entity): array
    {
        $superiorEntities = [];

        foreach (EntityReflection::getForeignKeys($entity) as $foreignKeyData) {
            $initialized = $foreignKeyData->getProperty()->isInitialized($entity);
            if (!$initialized) {
                continue;
            }
            $propertyName = $foreignKeyData->getPropertyName();
            $superiorEntities[] = $entity->$propertyName;
        }
        return $superiorEntities;
    }

    private function update(BaseEntity $entity): void
    {
        $data = $entity->toArray();
        $primaryKeyName = $entity::getPrimaryKeyName();
        $primaryKeyValue = $data[$primaryKeyName];
        unset($data[$primaryKeyName]);

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

        $entity->updateRawData();
    }


    /**
     * All $entities must be same type!
     * @param BaseEntity[] $entities
     */
    private function insert(array $entities): void
    {
        foreach ($entities as $entity) {
            $entity->fixSubEntityForeignKeys();
        }

        // We need at least one entity
        if (count($entities) <= 0) {
            return;
        }

        $dataKeys = array_keys($entities[0]->toArray());

        //region Remove primary key from data
        $primaryKeyName = $entities[0]::getPrimaryKeyName();
        $primaryKeyKey = array_search($primaryKeyName, $dataKeys, true);
        if ($primaryKeyKey !== false) {
            unset($dataKeys[$primaryKeyKey]);
        }
        //endregion

        $tableName = $entities[0]::getTableName();

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s;',
            $tableName,
            implode(',', $dataKeys),
            self::createPlaceholderRows(count($entities), count($dataKeys)),
        );

        $values = [];
        foreach ($entities as $entity) {
            $data = $entity->toArray();
            foreach ($dataKeys as $dataKey) {
                $values[] = $data[$dataKey];
            }
        }

        $this->pdo->beginTransaction();

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);

        //region Update `PRIMARY KEY`s
        $lastPk = (int)$this->pdo->lastInsertId();

        //region Fix for MySQL/MariaDB
        /** @see https://www.php.net/manual/en/pdo.lastinsertid.php#122009 */
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (strtolower($driver) === 'mysql') {
            $lastPk += count($entities) - 1;
        }
        //endregion

        $firstPk = $lastPk - count($entities) + 1;

        // TODO: Update to support for 'string' primary keys (uuid?)!
        foreach ($entities as $entity) {
            $entity->setPrimaryKeyValue($firstPk);
            $entity->updateRawData();
            $firstPk++;
        }
        //endregion

        $this->pdo->commit();
    }

    /**
     * @param int $count
     * @param int $placeholderAmount
     * @return string
     */
    #[Pure]
    private static function createPlaceholderRows(int $count, int $placeholderAmount): string
    {
        $result = '';

        for ($i = 0; $i < $count; $i++) {
            $result .= sprintf('%s(%s)', $i === 0 ? ' ' : ', ', self::createPlaceholders($placeholderAmount));
        }

        return trim($result);
    }

    /**
     * @param int $count
     * @return string
     */
    private static function createPlaceholders(int $count): string
    {
        $result = '';

        for ($i = 0; $i < $count; $i++) {
            $result .= sprintf('%s?', $i === 0 ? ' ' : ', ');
        }

        return trim($result);
    }

    /**
     * @param BaseEntity[] $entities
     * @return array<string, BaseEntity[]>
     */
    private static function chunkByTables(array $entities): array
    {
        $orderByTable = [];

        foreach ($entities as $entity) {
            if (!array_key_exists($entity::class, $orderByTable)) {
                $orderByTable[$entity::class] = [];
            }
            $orderByTable[$entity::class][] = $entity;
        }

        return $orderByTable;
    }

    /**
     * @param BaseEntity[] $entities
     * @return BaseEntity[]
     */
    private function sortInsertTableEntities(array $entities): array
    {
        /** @var array<string, string[]> $dependencyTable <class-name, required[]> */
        $dependencyTable = [];

        $baseEntitiesToSort = [];

        foreach ($entities as $entity) {

            $baseEntitiesToSort[] = $entity;

            //region ForeignKey: This entity, depends on target
            foreach (EntityReflection::getForeignKeys($entity) as $foreignKeyData) {

                $currentClassName = $entity::class;
                $targetClassName = $foreignKeyData->getTargetClassName();

                if (!array_key_exists($currentClassName, $dependencyTable)) {
                    $dependencyTable[$currentClassName] = [];
                }

                if (!in_array($targetClassName, $dependencyTable[$currentClassName], true)) {
                    $dependencyTable[$currentClassName][] = $targetClassName;
                }

            }
            //endregion

            //region FetchArray: Target depends on this entity

            foreach (EntityReflection::getFetchArrayProperties($entity) as $fetchArrayProperty) {

                $currentClassName = $entity::class;
                $targetClassName = $fetchArrayProperty->getTargetClassName();

                if (!array_key_exists($targetClassName, $dependencyTable)) {
                    $dependencyTable[$targetClassName] = [];
                }

                if (!in_array($currentClassName, $dependencyTable[$targetClassName], true)) {
                    $dependencyTable[$targetClassName][] = $currentClassName;
                }

            }

            //endregion

        }


        $order = self::createOrderRecursivelyByDependencyTable($baseEntitiesToSort, $dependencyTable);

        $orderedEntities = [];

        foreach ($order as $className) {
            foreach ($baseEntitiesToSort as $baseEntity) {
                if ($baseEntity::class === $className) {
                    $orderedEntities[] = $baseEntity;
                }
            }
        }

        return $orderedEntities;

    }


    /**
     * @param BaseEntity[] $entities
     * @param array<string, string[]> $dependencyTable
     * @return string[]
     */
    private static function createOrderRecursivelyByDependencyTable(array $entities, array $dependencyTable): array
    {
        $order = [];

        foreach ($entities as $entity) {
            $order = self::addEntityToOrder($order, $entity::class, $dependencyTable);
        }

        return $order;
    }

    /**
     * @param string[] $order
     * @param string $entityClassName
     * @param array<string, string[]> $dependencyTable
     * @param int $stack
     * @return string[]
     */
    private static function addEntityToOrder(array $order, string $entityClassName, array $dependencyTable, int $stack = 0): array
    {
        if ($stack > 150) {
            throw new MkSQLException('Stack Overflow');
        }

        if (array_key_exists($entityClassName, $dependencyTable)) {
            foreach ($dependencyTable[$entityClassName] as $requiredSubTable) {
                $order = self::addEntityToOrder($order, $requiredSubTable, $dependencyTable, $stack + 1);
            }
        }

        if (!in_array($entityClassName, $order, true)) {
            $order[] = $entityClassName;
        }

        return $order;
    }
}

<?php declare(strict_types=1);

namespace Zrnik\MkSQL\Repository\Saver;

use PDO;
use Zrnik\MkSQL\Repository\Attributes\FetchArray;
use Zrnik\MkSQL\Repository\Attributes\ForeignKey;
use Zrnik\MkSQL\Repository\BaseEntity;
use Zrnik\MkSQL\Utilities\Reflection;
use function array_key_exists;

class Saver
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param BaseEntity[] $entities
     */
    public function saveEntities(array $entities): void
    {
        $entityList = $this->listAllEntities($entities);
        foreach ($entityList as $classEntityList) {
            foreach($classEntityList as $entity) {
                if($entity->getPrimaryKeyValue() === null) {
                    $this->insert($entity);
                } else {
                    $this->update($entity);
                }
            }
        }
    }

    /**
     * @param BaseEntity[] $entities
     * @return array<class-string<BaseEntity>, array<int|string, BaseEntity>>
     */
    private function listAllEntities(array $entities): array
    {
        $entityList = [];

        foreach($entities as $entity) {
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
        if(!array_key_exists($entity::class, $entityList)) {
            $entityList[$entity::class] = [];
        }

        if(!array_key_exists($entity->hash(), $entityList[$entity::class])) {
            $entityList[$entity::class][$entity->hash()] = $entity;

            foreach($this->subEntitiesOf($entity) as $subEntity) {
                $this->fillEntityListRecursively($subEntity, $entityList);
            }

            foreach($this->supEntitiesOf($entity) as $subEntity) {
                $this->fillEntityListRecursively($subEntity, $entityList);
            }

        }
    }

    /**
     * @param BaseEntity $entity
     * @return BaseEntity[]
     */
    private function subEntitiesOf(BaseEntity $entity): array
    {
        $reflection = BaseEntity::getReflectionClass($entity);

        $subEntities = [];

        foreach ($reflection->getProperties() as $property) {
            $fetchArrayAttribute = Reflection::propertyGetAttribute($property, FetchArray::class);
            if($fetchArrayAttribute !== null) {
                $propertyName = $property->getName();
                foreach($entity->$propertyName as $subEntity) {
                    $subEntities[] = $subEntity;
                }
            }
        }

        return $subEntities;
    }

    /**
     * @param BaseEntity $entity
     * @return BaseEntity[]
     */
    private function supEntitiesOf(BaseEntity $entity): array
    {
        $reflection = BaseEntity::getReflectionClass($entity);

        $subEntities = [];

        foreach ($reflection->getProperties() as $property) {

            if(!$property->isInitialized($entity)) {
                continue;
            }

            $foreignKeyAttribute = Reflection::propertyGetAttribute($property, ForeignKey::class);
            if($foreignKeyAttribute !== null) {
                $propertyName = $property->getName();
                $subEntities[] = $entity->$propertyName;
            }
        }

        return $subEntities;
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

    private function insert(BaseEntity $entity): void
    {
        $data = $entity->toArray();

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

        $entity->updateRawData();
    }
}

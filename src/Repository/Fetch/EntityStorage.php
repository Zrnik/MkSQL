<?php declare(strict_types=1);

namespace Zrnik\MkSQL\Repository\Fetch;

use Zrnik\MkSQL\Repository\BaseEntity;
use Zrnik\MkSQL\Utilities\EntityReflection\EntityReflection;

class EntityStorage
{
    /** @var BaseEntity[] */
    private array $entities = [];

    public function __construct()
    {
    }

    public function addEntity(BaseEntity $entity): void
    {
        $this->entities[] = $entity;
    }

    /**
     * @param class-string<BaseEntity> $baseEntityClassString
     * @return BaseEntity[]
     */
    public function getEntitiesByClassName(string $baseEntityClassString): array
    {
        $result = [];
        foreach ($this->entities as $entity) {
            if ($entity::class === $baseEntityClassString) {
                $result[] = $entity;
            }
        }
        return $result;
    }

    /**
     * @return BaseEntity[]
     */
    public function getEntities(): array
    {
        return $this->entities;
    }

    /**
     * @param class-string<BaseEntity> $className
     * @param mixed $primaryKeyValue
     * @return bool
     */
    public function has(string $className, mixed $primaryKeyValue): bool
    {
        return $this->getEntityByPrimaryKey($className, $primaryKeyValue) !== null;
    }

    /**
     * @param class-string<BaseEntity> $className
     * @param mixed $primaryKeyValue
     * @return BaseEntity|null
     */
    private function getEntityByPrimaryKey(
        string $className, mixed $primaryKeyValue
    ): ?BaseEntity
    {
        foreach ($this->getEntitiesByClassName($className) as $entity) {
            if ((string)$entity->getPrimaryKeyValue() === (string)$primaryKeyValue) {
                return $entity;
            }
        }

        return null;
    }

    /**
     * @param class-string<BaseEntity> $className
     * @param string $columnName
     * @param mixed $columnValue
     * @return BaseEntity[]
     */
    private function getEntitiesByColumn(string $className, string $columnName, mixed $columnValue): array
    {
        $result = [];
        foreach ($this->getEntitiesByClassName($className) as $entity) {
            if ((string)$entity->getRawData()[$columnName] === (string)$columnValue) {
                $result[] = $entity;
            }
        }

        return $result;
    }

    public function linkEntities(): void
    {
        foreach ($this->entities as $entity) {

            foreach (EntityReflection::getForeignKeys($entity) as $foreignKeyData) {

                $requiredPrimaryKey = $entity->getRawData()[$foreignKeyData->foreignKeyColumnName()];

                $foreignEntity = $this->getEntityByPrimaryKey(
                    $foreignKeyData->getTargetClassName(), $requiredPrimaryKey
                );

                $propertyName = $foreignKeyData->getPropertyName();
                $entity->$propertyName = $foreignEntity;
            }


            foreach (EntityReflection::getFetchArrayProperties($entity) as $fetchArrayData) {


                $neededClass = $fetchArrayData->getTargetClassName();

                $propertyName = $fetchArrayData->getPropertyName();

                foreach (EntityReflection::getForeignKeys($neededClass) as $aimingBackForeignKeyData) {

                    $fetchEntities = $this->getEntitiesByColumn(
                        $neededClass,
                        BaseEntity::columnName($aimingBackForeignKeyData->getProperty()),
                        $entity->getPrimaryKeyValue()
                    );

                    /** @var BaseEntity[] $data */
                    $data = $entity->$propertyName;
                    foreach ($fetchEntities as $fetchEntity) {
                        $data[] = $fetchEntity;
                    }
                    $entity->$propertyName = $data;
                }
            }
        }
    }


}

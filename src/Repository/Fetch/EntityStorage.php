<?php declare(strict_types=1);

namespace Zrnik\MkSQL\Repository\Fetch;

use JetBrains\PhpStorm\Pure;
use Zrnik\MkSQL\Repository\BaseEntity;
use Zrnik\MkSQL\Utilities\EntityReflection\EntityReflection;
use function array_key_exists;
use function count;

class EntityStorage
{
    /** @var BaseEntity[] */
    private array $entities = [];

    /** @var array<string, int> <$className.$primaryKeyValue, $entities.index>  */
    private array $entitiesIndexPointer = [];

    public function __construct()
    {
    }

    public function addEntity(BaseEntity $entity): void
    {
        $newIndex = count($this->entities);
        $this->entities[] = $entity;
        $this->entitiesIndexPointer[
            $this->cnpk($entity::class, $entity->getPrimaryKeyValue())
        ] = $newIndex;
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
    #[Pure] public function has(string $className, mixed $primaryKeyValue): bool
    {
        return array_key_exists(
            $this->cnpk($className, $primaryKeyValue),
            $this->entitiesIndexPointer
        );
        //return $this->getEntityByPrimaryKey($className, $primaryKeyValue) !== null;
    }

    /**
     * @param class-string<BaseEntity> $className
     * @param mixed $primaryKeyValue
     * @return BaseEntity|null
     */
    #[Pure] private function getEntityByPrimaryKey(
        string $className, mixed $primaryKeyValue
    ): ?BaseEntity
    {
        $index =
            $this->entitiesIndexPointer
            [$this->cnpk($className, $primaryKeyValue)]
            ?? 'null'
        ;

        return $this->entities[$index] ?? null;
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

        // Here, we have all entities linked, we can call afterRetrieve hook
        foreach ($this->entities as $entity) {
            $entity->afterRetrieve();
        }

    }

    /**
     * @param string $className
     * @param mixed $primaryKeyValue
     * @return string
     * @noinspection SpellCheckingInspection
     */
    private function cnpk(string $className, mixed $primaryKeyValue): string
    {
        return $className . '::' . ((string) $primaryKeyValue);
    }


}

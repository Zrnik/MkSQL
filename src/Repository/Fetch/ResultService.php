<?php declare(strict_types=1);

namespace Zrnik\MkSQL\Repository\Fetch;

use Zrnik\MkSQL\Repository\BaseEntity;
use Zrnik\MkSQL\Utilities\EntityReflection\EntityReflection;
use function count;
use function in_array;

class ResultService
{
    private EntityStorage $entityStorage;
    private CompletionIndication $completion;

    public function __construct()
    {
        $this->entityStorage = new EntityStorage();
        $this->completion = new CompletionIndication();
    }

    /**
     * @param bool $dryRun
     * @return CompletionKeyValues[]
     */
    public function completionData(bool $dryRun = false): array
    {
        $completionResult = new CompletionBuilder();

        foreach ($this->entityStorage->getEntities() as $entity) {

            if ($this->completion->isCompleted($entity)) {
                continue;
            }

            //region ForeignKey
            foreach (EntityReflection::getForeignKeys($entity) as $foreignKeyData) {

                /** @var BaseEntity $targetClassStaticUsage */
                $targetClassStaticUsage = $foreignKeyData->getTargetClassName();

                $keyToLoad = $entity->getRawData()[$foreignKeyData->foreignKeyColumnName()];

                $completionResult->add(
                    $foreignKeyData->getTargetClassName(),
                    $targetClassStaticUsage::getPrimaryKeyName(),
                    $keyToLoad
                );

            }
            //endregion


            //region FetchArray
            foreach (EntityReflection::getFetchArrayProperties($entity) as $fetchArrayData) {
                $entityPrimaryKeyValue = $entity->getRawData()[$entity::getPrimaryKeyName()];
                foreach (EntityReflection::getForeignKeys($fetchArrayData->getTargetClassName()) as $foreignKeyAimingBack) {
                    if ($foreignKeyAimingBack->getTargetClassName() === $entity::class) {
                        $completionResult->add(
                            $fetchArrayData->getTargetClassName(),
                            $foreignKeyAimingBack->getPropertyName(),
                            $entityPrimaryKeyValue
                        );
                    }
                }
            }
            //endregion


            if (!$dryRun) {
                $this->completion->setCompleted($entity);
            }
        }

        return $completionResult->getCompletionData();
    }

    public function complete(): bool
    {
        return count($this->completionData(true)) === 0;
    }

    /**
     * @param class-string<BaseEntity> $baseEntityClassString
     * @param array<mixed[]> $rows
     */
    public function addRows(string $baseEntityClassString, array $rows): void
    {
        /** @var BaseEntity $baseEntityForStaticUsage */
        $baseEntityForStaticUsage = $baseEntityClassString;
        $primaryKeyColumnName = $baseEntityForStaticUsage::getPrimaryKeyName();
        foreach ($rows as $row) {
            $newPrimaryValue = $row[$primaryKeyColumnName];
            if (!$this->entityStorage->has($baseEntityClassString, $newPrimaryValue)) {
                $this->entityStorage->addEntity($baseEntityForStaticUsage::fromIterable($row));
            }
        }
    }

    /**
     * @param class-string<BaseEntity> $baseEntityClassString
     * @param string|null $propertyName
     * @param mixed[] $values
     * @return BaseEntity[]
     */
    public function getEntities(string $baseEntityClassString, ?string $propertyName, array $values): array
    {
        /** @var BaseEntity $baseEntityForStaticUsage */
        $baseEntityForStaticUsage = $baseEntityClassString;
        $result = [];

        if ($propertyName === null) {
            return $this->entityStorage->getEntitiesByClassName($baseEntityClassString);
        }

        $propertyReflection = $baseEntityForStaticUsage::propertyReflection($propertyName);

        if ($propertyReflection !== null) {
            $columnName = $baseEntityForStaticUsage::columnName($propertyReflection);
            foreach ($this->entityStorage->getEntitiesByClassName($baseEntityClassString) as $entity) {
                $entityValue = $entity->getRawData()[$columnName];
                if (in_array($entityValue, $values, false)) {
                    $result[] = $entity;
                }
            }
        }

        return $result;
    }

    public function linkEntities(): void
    {
        $this->entityStorage->linkEntities();
    }
}
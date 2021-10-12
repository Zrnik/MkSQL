<?php declare(strict_types=1);

namespace Zrnik\MkSQL\Repository;

use Closure;

class FetchObjectStorage
{
    private array $entityStorage = [];

    /**
     * @param string $baseEntityClassName
     * @param mixed $primaryKeyValue
     * @param Closure $factory
     * @return BaseEntity
     */
    public function getObject(
        string  $baseEntityClassName, mixed $primaryKeyValue,
        Closure $factory
    ): BaseEntity
    {
        /** @var BaseEntity $object */
        $object = $this->getStoredObject($baseEntityClassName, $primaryKeyValue);

        if ($object === null) {
            $object = $this->storeObject($baseEntityClassName, $primaryKeyValue, $factory());
        }

        return $object;
    }

    /*private function objectStored(string $baseEntityClassName, mixed $primaryKeyValue): bool
    {
        return $this->getStoredObject($baseEntityClassName, $primaryKeyValue) !== null;
    }*/

    private function getStoredObject(string $baseEntityClassName, mixed $primaryKeyValue): ?BaseEntity
    {
        return $this->entityStorage[$this->createKey($baseEntityClassName, $primaryKeyValue)] ?? null;
    }

    private function storeObject(
        string $baseEntityClassName, mixed $primaryKeyValue, BaseEntity $object
    ): BaseEntity
    {
        $this->entityStorage[$this->createKey($baseEntityClassName, $primaryKeyValue)]
            = $object;

        return $object;
    }

    private function createKey(string $baseEntityClassName, mixed $primaryKeyValue): string
    {
        /** @var BaseEntity $baseEntity */
        $baseEntity = $baseEntityClassName;
        return sprintf('%s-%s', $baseEntity::getTableName(), (string)$primaryKeyValue);
    }
}

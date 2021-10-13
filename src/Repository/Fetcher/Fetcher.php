<?php declare(strict_types=1);

namespace Zrnik\MkSQL\Repository\Fetcher;

use JetBrains\PhpStorm\Pure;
use PDO;
use Zrnik\MkSQL\Repository\Attributes\ColumnName;
use Zrnik\MkSQL\Repository\BaseEntity;
use Zrnik\MkSQL\Utilities\Reflection;
use function count;
use function in_array;

/**
 * This is One-Fetch use class!
 */
class Fetcher
{

    /**
     * @param PDO $pdo
     */
    #[Pure]
    public function __construct(
        private PDO $pdo
    )
    {
    }

    /**
     * @param class-string<BaseEntity> $baseEntityClassString
     * @param ?string $key
     * @param array<mixed> $values
     * @return BaseEntity[]
     */
    public function getResultsByKeys(
        string  $baseEntityClassString,
        ?string $key = null, array $values = []
    ): array
    {
        //This is quicker :)
        if ($key !== null && count($values) === 0) {
            return [];
        }

        $fetchResult = new FetchResult($baseEntityClassString);

        $fetchSql = $this->createFetchSql($baseEntityClassString, $key, $values);
        $fetchResult->addRows($baseEntityClassString, $fetchSql->fetchAll($this->pdo));

        $tries = 20;
        while ($fetchResult->needsCompletion()) {
            $completionKeys = $fetchResult->getCompletionKeys();

            /**
             * @var class-string<BaseEntity> $entityClass
             * @var array<int|string, mixed[]> $keyValues
             */
            foreach ($completionKeys as $entityClass => $keyValues) {
                /**
                 * @var int|string $subKey
                 * @var mixed[] $subValues
                 */
                foreach ($keyValues as $subKey => $subValues) {
                    $fetchSql = $this->createFetchSql($entityClass, (string)$subKey, $subValues);
                    $fetchResult->addRows($entityClass, $fetchSql->fetchAll($this->pdo));
                }
            }


            //Kill Switch
            $tries--;
            if ($tries <= 0) {
                break;
            }
        }

        return $this->filterEntities($fetchResult->getEntities(), $key, $values);
    }

    /**
     * @param class-string<BaseEntity> $baseEntityClassString
     * @param string|null $key
     * @param array<mixed> $values
     * @return FetchSql
     */
    private function createFetchSql(string $baseEntityClassString, ?string $key = null, array $values = []): FetchSql
    {
        /** @var BaseEntity $baseEntityForStaticUse */
        $baseEntityForStaticUse = $baseEntityClassString;

        $tableName = $baseEntityForStaticUse::getTableName();

        if ($key === null) {
            return new FetchSql(sprintf('SELECT * FROM %s', $tableName));
        }

        if (count($values) === 0) {
            // This is actually handled in `getResultsByKey` but it's here for peace of mind...
            return new FetchSql(sprintf('SELECT * FROM %s WHERE 0 = 1', $tableName));
        }

        $property = $baseEntityForStaticUse::propertyReflection($key);
        if ($property !== null) {
            $columnNameAttribute = Reflection::propertyGetAttribute($property, ColumnName::class);
            if ($columnNameAttribute !== null) {
                $columnName = Reflection::attributeGetArgument($columnNameAttribute);
                $key = $columnName;
            }
        }

        $fetchSql = new FetchSql(
            sprintf(
                'SELECT * FROM %s WHERE %s IN (%s)', $tableName, $key,
                str_repeat('?,', count($values) - 1) . '?'
            )
        );

        $fetchSql->values = $values;

        return $fetchSql;
    }

    /**
     * @param BaseEntity[] $entities
     * @param ?string $key //PROPERTY NAME, NOT COLUMN NAME!
     * @param mixed $values
     * @return BaseEntity[]
     */
    private function filterEntities(array $entities, ?string $key = null, mixed $values = []): array
    {

        if ($key !== null) {

            $result = [];

            foreach ($entities as $entity) {
                $reflection = BaseEntity::getReflectionClass($entity);
                foreach ($reflection->getProperties() as $property) {

                    $propertyName = $property->getName();
                    $propertyReflection = $entity::propertyReflection($propertyName);
                    if ($propertyReflection !== null) {
                        $columnName = $entity::columnName($propertyReflection);

                        if ($propertyName === $key) {
                            $value = $entity->getRawData()[$columnName];

                            if (in_array($value, $values, false)) {
                                $result[$entity->getPrimaryKeyValue()] = $entity;
                            }
                        }
                    }
                }
            }

            return array_values($result);

        }

        return array_values($entities);
    }


}
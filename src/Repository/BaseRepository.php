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
use Zrnik\MkSQL\Repository\Saver\Saver;
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
     */
    public function save(BaseEntity|array $entities): void
    {
        if ($entities instanceof BaseEntity) {
            /** @var BaseEntity[] $entities */
            $entities = [$entities];
        }

        $saver = new Saver($this->pdo);
        $saver->saveEntities($entities);
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
        return (new Fetcher($this->getPdo()))
            ->getResultsByKeys($baseEntityClassString, $key, $values);
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

<?php declare(strict_types=1);

namespace Zrnik\MkSQL\Repository\Fetch;

use PDO;
use PDOException;
use Zrnik\MkSQL\Exceptions\FetchFailedException;
use Zrnik\MkSQL\Exceptions\SaveFailedException;
use Zrnik\MkSQL\Repository\Attributes\ColumnName;
use Zrnik\MkSQL\Repository\BaseEntity;
use Zrnik\MkSQL\Repository\Saver\SaveMethod;
use Zrnik\MkSQL\Utilities\Reflection;
use function count;

class FetchQuery
{
    /** @var mixed[] */
    private array $values = [];

    final private function __construct(
        public string $query
    )
    {
    }

    /**
     * @param PDO $pdo
     * @return array<array<bool|float|int|string|null>>
     */
    public function fetchAll(PDO $pdo): array
    {
        $values = array_values($this->values);

        try{
            $statement = $pdo->prepare($this->query);
        } catch (PDOException $ex) {
            throw new FetchFailedException(
                $this->query, $ex
            );
        }

        $statement->execute($values);

        /**
         * Since PHP 8.0 it never returns false, and MkSQL needs PHP 8+ so.....
         * @see https://www.php.net/manual/en/pdostatement.fetchall.php#refsect1-pdostatement.fetchall-changelog
         * @noinspection PhpUnnecessaryLocalVariableInspection
         *
         * @var array<array<bool|float|int|string|null>> $fetch
         */
        $fetch = $statement->fetchAll();
        $this->executedQueries++;

        return $fetch;
    }

    /**
     * @param class-string $baseEntityClassString
     * @param string|null $propertyName // It can take columnName too...
     * @param array<mixed> $values
     * @return FetchQuery
     */
    public static function create(
        string  $baseEntityClassString,
        ?string $propertyName = null,
        array   $values = []
    ): FetchQuery
    {

        /** @var BaseEntity $baseEntityForStaticUse */
        $baseEntityForStaticUse = $baseEntityClassString;

        $tableName = $baseEntityForStaticUse::getTableName();

        if ($propertyName === null) {
            return new FetchQuery(sprintf('SELECT * FROM %s', $tableName));
        }

        if (count($values) === 0) {
            // This is actually handled in `getResultsByKey` but it's here for peace of mind...
            return new FetchQuery(sprintf('SELECT * FROM %s WHERE 0 = 1', $tableName));
        }

        $columnName = $propertyName;

        $property = $baseEntityForStaticUse::propertyReflection($propertyName);
        if ($property !== null) {
            $columnNameAttribute = Reflection::propertyGetAttribute($property, ColumnName::class);
            if ($columnNameAttribute !== null) {
                $columnName = Reflection::attributeGetArgument($columnNameAttribute);
            }
        }

        $fetchSql = new FetchQuery(
            sprintf(
                'SELECT * FROM %s WHERE %s IN (%s)', $tableName, $columnName,
                str_repeat('?,', count($values) - 1) . '?'
            )
        );

        $fetchSql->values = $values;

        return $fetchSql;
    }

    //region Executed Query Count
    private int $executedQueries = 0;
    public function getExecutedQueryCount() : int {
        return $this->executedQueries;
    }
    //endregion
}

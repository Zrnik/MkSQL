<?php declare(strict_types=1);

namespace Zrnik\MkSQL\Repository\Fetcher;

use PDO;

class FetchSql
{
    /** @var mixed[] */
    public array $values = [];

    public function __construct(
        public string $query
    )
    {
    }

    /**
     * @param PDO $pdo
     * @return mixed[]
     */
    public function fetchAll(PDO $pdo): array
    {
        $statement = $pdo->prepare($this->query);
        $statement->execute($this->values);
        $results = $statement->fetchAll();
        if ($results === false) {
            return [];
        }
        return $results;
    }
}
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
        /**
         * Since PHP 8.0 it never returns false
         * @see https://www.php.net/manual/en/pdostatement.fetchall.php#refsect1-pdostatement.fetchall-changelog
         * @noinspection PhpUnnecessaryLocalVariableInspection
         *
         * @var mixed[] $fetch
         */
        $fetch = $statement->fetchAll();

        return $fetch;
    }
}
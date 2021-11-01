<?php declare(strict_types=1);

namespace Zrnik\MkSQL\Exceptions;

use JetBrains\PhpStorm\Pure;
use PDOException;

class FetchFailedException extends MkSQLException
{
    #[Pure] public function __construct(string $sql, PDOException $exception)
    {
        parent::__construct(
            sprintf(
                "Fetch query\n'%s'\nfailed with message:\n%s",
                $sql, $exception->getMessage()
            ),
            (int) $exception->getCode(),
            $exception
        );
    }
}

<?php declare(strict_types=1);

namespace Zrnik\MkSQL\Exceptions;

use PDOException;
use Zrnik\MkSQL\Repository\Saver\SaveMethod;

class SaveFailedException extends MkSQLException
{
    public function __construct(string $sql, int $method, PDOException $exception)
    {
        parent::__construct(
            sprintf(
                "%s query\n'%s'\nfailed with message:\n%s",
                SaveMethod::getName($method), $sql, $exception->getMessage()
            ),
            (int) $exception->getCode(),
            $exception
        );
    }
}

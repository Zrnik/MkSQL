<?php declare(strict_types=1);

namespace Zrnik\MkSQL\Exceptions;

use Throwable;

class TableCreationFailedException extends MkSQLException
{
    public function __construct(
        string $tableName, ?string $columnName, string $query, Throwable $previous
    )
    {

        $reason = $previous->getMessage();

        $message = $columnName === null
            ? sprintf(
                "Unable to create table '%s'\nreason: %s\nquery: %s",
                $tableName, $reason, $query
            )
            : sprintf(
                "Unable to create column '%s::%s'\nreason: %s\nquery: %s",
                $tableName, $columnName, $reason, $query
            );

        parent::__construct($message, (int) $previous->getCode(), $previous);
    }
}

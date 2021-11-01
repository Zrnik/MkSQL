<?php declare(strict_types=1);

namespace Zrnik\MkSQL\Exceptions;

use Throwable;

class TypeConversionFailedException extends MkSQLException
{
    public function __construct(string $keyName, string $typeConverterClassName, Throwable $throwable)
    {
        parent::__construct(
            sprintf(
                "Class '%s' failed to convert '%s' with message:\n\n%s",
                $typeConverterClassName, $keyName, $throwable->getMessage()
            ),
            $throwable->getCode(),
            $throwable
        );
    }
}
<?php declare(strict_types=1);

namespace Zrnik\MkSQL\Exceptions;

use Throwable;

class TypeConversionFailedException extends MkSQLException
{
    public function __construct(string $entityName, string $propertyName, string $method, string $typeConverterClassName, Throwable $throwable)
    {
        parent::__construct(
            sprintf(
                "Class '%s' failed to %s '%s::%s' with message:\n\n%s",
                $typeConverterClassName, $method, $entityName, $propertyName, $throwable->getMessage()
            ),
            $throwable->getCode(),
            $throwable
        );
    }
}
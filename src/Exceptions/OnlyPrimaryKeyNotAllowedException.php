<?php declare(strict_types=1);

namespace Zrnik\MkSQL\Exceptions;

use JetBrains\PhpStorm\Pure;

class OnlyPrimaryKeyNotAllowedException extends MkSQLException
{
    #[Pure]
    public function __construct(string $entityClassName)
    {
        parent::__construct(
            sprintf(
                'Please do not provide more columns for entity "%s", only primary key is not allowed!',
                $entityClassName
            )
        );
    }
}

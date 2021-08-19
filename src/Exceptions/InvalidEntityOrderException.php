<?php

namespace Zrnik\MkSQL\Exceptions;

use JetBrains\PhpStorm\Pure;
use Throwable;

class InvalidEntityOrderException extends MkSQLException
{

    #[Pure] public function __construct(
        string $entityClass, string $referencedClass
    )
    {
        parent::__construct(
            sprintf(
                "Entity '%s' is referencing another entity '%s' which is not initialized yet. Please put '%s' before '%s' in your `%s->use(...);` statements!",
                $entityClass,$referencedClass,$referencedClass,$entityClass,'$updater'
            )
        );
    }

}

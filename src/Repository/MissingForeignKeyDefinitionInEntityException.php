<?php

namespace Zrnik\MkSQL\Repository;

use Exception;
use JetBrains\PhpStorm\Pure;

class MissingForeignKeyDefinitionInEntityException extends Exception
{

    #[Pure]
    public function __construct(string $class, string $subClass)
    {
        parent::__construct(
            sprintf(
                "Entity '%s' is expecting, that another entity '%s' has a foreign key pointing to it, but none found!",
                $class, $subClass
            )
        );

    }
}

<?php

namespace Zrnik\MkSQL\Exceptions;

use JetBrains\PhpStorm\Pure;
use ReflectionAttribute;

class MissingAttributeArgumentException extends MkSQLException
{
    /**
     * @param ReflectionAttribute<object> $attr
     * @param int $index
     */
    #[Pure]
    public function __construct(ReflectionAttribute $attr, int $index = 0)
    {
        parent::__construct(
            sprintf(
                "Attribute '%s' is missing an argument with index '%s'!",
                $attr->getName(), $index
            )
        );
    }
}

<?php

namespace Zrnik\MkSQL\Exceptions;

use JetBrains\PhpStorm\Pure;
use Throwable;

class InvalidTypeException extends MkSQLException
{
    /**
     * @param string $expected
     * @param string $got
     */
    #[Pure]
    public function __construct(string $expected, string $got)
    {
        parent::__construct(
            sprintf(
                "Expected type of '%s', but got '%s' instead!",
                $expected, $got
            )
        );
    }
}

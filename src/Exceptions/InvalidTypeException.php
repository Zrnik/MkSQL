<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Zrnik\MkSQL\Exceptions;

use JetBrains\PhpStorm\Pure;

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

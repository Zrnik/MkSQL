<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Zrnik\MkSQL\Exceptions;

use JetBrains\PhpStorm\Pure;

class InvalidPropertyTypeException extends MkSQLException
{
    /**
     * @param string $expected
     * @param string $got
     * @param string $className
     * @param string $propertyName
     */
    #[Pure]
    public function __construct(
        string $expected, string $got,
        string $className, string $propertyName,
    )
    {
        parent::__construct(
            sprintf(
                "Property '%s' of class '%s' expected type of '%s', but got '%s' instead!",
                $propertyName, $className, $expected, $got
            )
        );
    }
}

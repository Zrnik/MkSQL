<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Zrnik\MkSQL\Exceptions;

use JetBrains\PhpStorm\Pure;
use Zrnik\MkSQL\Repository\BaseEntity;

class CircularReferenceDetectedException extends MkSQLException
{
    /**
     * @param class-string<BaseEntity> $entityClassName
     * @param string $propertyName
     */
    #[Pure]
    public function __construct(string $entityClassName, string $propertyName)
    {
        parent::__construct(
            sprintf(
                "Circular reference is not supported, yet it was detected for class '%s' in property '%s'!",
                $entityClassName, $propertyName
            )
        );
    }
}

<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Zrnik\MkSQL\Exceptions;

use JetBrains\PhpStorm\Pure;
use Zrnik\MkSQL\Repository\BaseEntity;

class MultipleForeignKeysTargetingSameClassException extends MkSQLException
{
    /**
     * @param class-string<BaseEntity> $targetingClass
     * @param class-string<BaseEntity> $targetClass
     * @param string $propertyName
     */
    #[Pure]
    public function __construct(string $targetingClass, string $targetClass, string $propertyName)
    {
        parent::__construct(
            sprintf(
                "Class '%s' have foreign key target '%s' already defined on property '%s'",
                $targetingClass, $targetClass, $propertyName
            )
        );
    }
}

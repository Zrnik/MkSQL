<?php

namespace Zrnik\MkSQL\Exceptions;

use Attribute;
use JetBrains\PhpStorm\Pure;
use Throwable;
use Zrnik\MkSQL\Repository\BaseEntity;

class RequiredClassAttributeMissingException extends MkSQLException
{
    /**
     * @param class-string<BaseEntity> $entityClassName
     * @param class-string<Attribute> $attributeClassName
     */
    #[Pure] public function __construct(
        string $entityClassName,
        string $attributeClassName,
    )
    {
        parent::__construct(
            sprintf(
                "Class '%s' requires to have '%s' attribute, but it was not provided!",
                $entityClassName, $attributeClassName
            )
        );
    }

}

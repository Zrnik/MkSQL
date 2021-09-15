<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Zrnik\MkSQL\Exceptions;

use JetBrains\PhpStorm\Pure;

class MissingForeignKeyDefinitionInEntityException extends MkSQLException
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

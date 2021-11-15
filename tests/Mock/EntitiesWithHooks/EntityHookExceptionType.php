<?php declare(strict_types=1);

namespace Tests\Mock\EntitiesWithHooks;

use Zrnik\Base\Enum;

class EntityHookExceptionType extends Enum
{
    public const BEFORE_SAVE = 0;
    public const AFTER_SAVE = 1;

    public const AFTER_RETRIEVE = 10;
}

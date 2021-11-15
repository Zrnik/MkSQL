<?php declare(strict_types=1);

namespace Tests\Mock\EntitiesWithHooks;

use Exception;

class EntityWithHookException extends Exception
{
    public int $hookExceptionType;

    public function __construct(int $hookExceptionType)
    {
        $this->hookExceptionType = $hookExceptionType;

        parent::__construct(
            sprintf(
                'Hook exception "%s" thrown!',
                EntityHookExceptionType::getName($hookExceptionType)
            )
        );
    }
}

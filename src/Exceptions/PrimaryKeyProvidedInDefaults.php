<?php declare(strict_types=1);

namespace Zrnik\MkSQL\Exceptions;

use JetBrains\PhpStorm\Pure;

class PrimaryKeyProvidedInDefaults extends MkSQLException
{
    #[Pure] public function __construct()
    {
        parent::__construct('Please do not provide primary key value in "getDefaults" function!');
    }
}

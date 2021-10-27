<?php declare(strict_types=1);

namespace Zrnik\MkSQL\Utilities;

use function array_key_exists;
use function get_defined_constants;

class Misc
{
    /**
     * My testsuite is defining 'MKSQL_PHPUNIT_RUNNING' just for this purpose!
     * @return bool
     */
    public static function isPhpUnitTest(): bool {
        return array_key_exists('MKSQL_PHPUNIT_RUNNING', get_defined_constants());
    }
}
<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Utilities;

use Mock\Installable\DifferentRepository;
use Mock\Installable\RandomRepository;
use Mock\PDO;
use PHPUnit\Framework\TestCase;
use Zrnik\MkSQL\Exceptions\MkSQLException;
use Zrnik\MkSQL\Utilities\Installable;

class InstallableTest extends TestCase
{
    /**
     * @throws MkSQLException
     */
    public function testSingleInstallationInMultipleRepositories(): void
    {
        $pdo = new PDO();

        $randomRepo1 = new RandomRepository($pdo);
        static::assertTrue($randomRepo1->installed);

        $randomRepo2 = new RandomRepository($pdo);
        static::assertFalse($randomRepo2->installed);

        $differentRepo1 = new DifferentRepository($pdo);
        static::assertTrue($differentRepo1->installed);

        $differentRepo2 = new DifferentRepository($pdo);
        static::assertFalse($differentRepo2->installed);

        Installable::uninstallAll($pdo);

        $randomRepo1 = new RandomRepository($pdo);
        static::assertTrue($randomRepo1->installed);

        $differentRepo1 = new DifferentRepository($pdo);
        static::assertTrue($differentRepo1->installed);

    }

}

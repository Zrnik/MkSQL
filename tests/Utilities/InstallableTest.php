<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Tests\Utilities;

use PHPUnit\Framework\TestCase;
use Tests\Mock\BaseRepositoryAndBaseEntity\CallingInstallInInstallableRepository;
use Tests\Mock\Installable\DifferentRepository;
use Tests\Mock\Installable\RandomRepository;
use Tests\Mock\PDO;
use Zrnik\MkSQL\Exceptions\MkSQLException;
use Zrnik\MkSQL\Exceptions\UnexpectedCall;
use Zrnik\MkSQL\Utilities\Installable;
use Zrnik\PHPUnit\Exceptions;

class InstallableTest extends TestCase
{
    use Exceptions;

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

        $this->assertExceptionThrown(
            UnexpectedCall::class,
            function () use ($pdo) {
                new CallingInstallInInstallableRepository(
                    $pdo
                );
            }
        );

    }

}

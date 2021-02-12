<?php declare(strict_types=1);
/**
 * @generator PhpStorm
 * @author Štěpán Zrník <stepan@zrnik.eu>
 * @date 14.01.2021
 * @project MkSQL
 * @copyright (c) 2021 - Štěpán Zrník
 */

namespace Utilities;

use Mock\Installable\DifferentRepository;
use Mock\Installable\RandomRepository;
use Mock\PDO;
use PHPUnit\Framework\TestCase;
use Zrnik\MkSQL\Utilities\Installable;

class InstallableTest extends TestCase
{
    public function testSingleInstallationInMultipleRepositories(): void
    {
        $pdo = new PDO();

        $randomRepo1 = new RandomRepository($pdo);
        $this->assertTrue($randomRepo1->installed);

        $randomRepo2 = new RandomRepository($pdo);
        $this->assertFalse($randomRepo2->installed);

        $differentRepo1 = new DifferentRepository($pdo);
        $this->assertTrue($differentRepo1->installed);

        $differentRepo2 = new DifferentRepository($pdo);
        $this->assertFalse($differentRepo2->installed);

        Installable::uninstallAll($pdo);

        $randomRepo1 = new RandomRepository($pdo);
        $this->assertTrue($randomRepo1->installed);

        $differentRepo1 = new DifferentRepository($pdo);
        $this->assertTrue($differentRepo1->installed);

    }

}

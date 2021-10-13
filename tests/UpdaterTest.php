<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Tests;

use PHPUnit\Framework\TestCase;
use Tests\Mock\PDO;
use Zrnik\MkSQL\Exceptions\InvalidArgumentException;
use Zrnik\MkSQL\Exceptions\MkSQLException;
use Zrnik\MkSQL\Exceptions\TableDefinitionExists;
use Zrnik\MkSQL\Table;
use Zrnik\MkSQL\Updater;
use Zrnik\PHPUnit\Exceptions;

class UpdaterTest extends TestCase
{
    use Exceptions;

    /**
     * @throws MkSQLException
     */
    public function testInstall(): void
    {
        $Updater = new Updater(new \Pdo('sqlite::memory:'));

        $this->assertNoExceptionThrown(function () use ($Updater) {
            $Updater->tableCreate('exampleTable')->columnCreate('exampleColumn');
            $Updater->install();
        });

        static::assertCount(1, $Updater->tableList());
        $Updater->clear();
        static::assertCount(0, $Updater->tableList());

        $Updater->tableCreate('exampleTable')->columnCreate('exampleColumn');
        $Updater->install();
        static::assertCount(1, $Updater->tableList());
        $Updater->tableRemove('exampleTable');
        static::assertCount(0, $Updater->tableList());
    }

    private function createPDO(): PDO
    {
        return new PDO();
    }

    /**
     * @throws TableDefinitionExists
     * @throws InvalidArgumentException
     */
    public function testTableGet(): void
    {
        $Updater = new Updater($this->createPDO());
        $Updater->tableCreate('someTable');

        static::assertNotNull($Updater->tableGet('someTable'));
        static::assertNull($Updater->tableGet('differentNonExistingTable'));

    }

    /**
     * @throws MkSQLException
     */
    public function testTableAdd(): void
    {
        $Updater = new Updater($this->createPDO());

        //No problem with adding table now
        $Table = new Table('someTable');
        $Updater->tableAdd($Table);
        $this->addToAssertionCount(1); //Created (because no exception)

        $this->assertExceptionThrown(
            TableDefinitionExists::class,
            function () use ($Updater, $Table) {
                $Updater->tableAdd($Table);
            }
        );

    }

    /**
     * @throws MkSQLException
     */
    public function testTableCreate(): void
    {
        $Updater = new Updater($this->createPDO());

        //No problem with adding table now
        $Updater->tableCreate('someTable');
        $this->addToAssertionCount(1); //Created (because no exception)

        //Now it exists, it should throw an exception
        $this->assertExceptionThrown(
            TableDefinitionExists::class,
            function () use ($Updater) {
                $Updater->tableCreate('someTable');
            }
        );
    }


    /**
     * @throws MkSQLException
     */
    public function testTableList(): void
    {
        $Updater = new Updater($this->createPDO());

        static::assertSame(
            [],
            $Updater->tableList()
        );

        $Updater->tableCreate('someTable');


        /**
         * This should be known from the 'return'
         * statement of 'tableList' method docblock
         */
        $tableList = $Updater->tableList();

        /** @phpstan-ignore-next-line */
        static::assertArrayHasKey(
            'someTable',
            $tableList
        );

        static::assertIsObject($tableList['someTable']);

        $Updater->tableCreate('anotherTable');

        $tableList = $Updater->tableList();

        /** @phpstan-ignore-next-line */
        static::assertArrayHasKey(
            'someTable',
            $tableList
        );

        static::assertArrayHasKey(
            'anotherTable',
            $tableList
        );

    }
}

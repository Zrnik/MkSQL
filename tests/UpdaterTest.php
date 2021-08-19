<?php declare(strict_types=1);

/*
 * Zrník.eu | MkSQL
 * User: Programátor
 * Date: 31.08.2020 15:47
 */


use PHPUnit\Framework\TestCase;
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

        $Updater = new Updater($this->createPDO());
        $Updater->install();

        //Expected no exceptions
        $this->addToAssertionCount(1);
    }

    private function createPDO(): \Mock\PDO
    {
        return new \Mock\PDO();
    }

    /**
     * @throws TableDefinitionExists
     * @throws \Zrnik\MkSQL\Exceptions\InvalidArgumentException
     */
    public function testTableGet(): void
    {
        $Updater = new Updater($this->createPDO());
        $Updater->tableCreate("someTable");

        $this->assertNotNull($Updater->tableGet("someTable"));
        $this->assertNull($Updater->tableGet("differentNonExistingTable"));

    }

    /**
     * @throws MkSQLException
     */
    public function testTableAdd(): void
    {
        $Updater = new Updater($this->createPDO());

        //No problem with adding table now
        $Table = new Table("someTable");
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
        $Updater->tableCreate("someTable");
        $this->addToAssertionCount(1); //Created (because no exception)

        //Now it exists, it should throw an exception
        $this->assertExceptionThrown(
            TableDefinitionExists::class,
            function () use ($Updater) {
                $Updater->tableCreate("someTable");
            }
        );
    }


    /**
     * @throws MkSQLException
     */
    public function testTableList(): void
    {
        $Updater = new Updater($this->createPDO());

        $this->assertSame(
            [],
            $Updater->tableList()
        );

        $Updater->tableCreate("someTable");


        /**
         * This should be known from the 'return'
         * statement of 'tableList' method docblock
         */
        $tableList = $Updater->tableList();

        /** @phpstan-ignore-next-line */
        $this->assertArrayHasKey(
            "someTable",
            $tableList
        );

        $this->assertIsObject($tableList["someTable"]);

        $Updater->tableCreate("anotherTable");

        $tableList = $Updater->tableList();

        /** @phpstan-ignore-next-line */
        $this->assertArrayHasKey(
            "someTable",
            $tableList
        );

        $this->assertArrayHasKey(
            "anotherTable",
            $tableList
        );

    }
}

<?php declare(strict_types=1);

/*
 * Zrník.eu | MkSQL
 * User: Programátor
 * Date: 31.08.2020 15:47
 */


use PHPUnit\Framework\TestCase;
use Zrnik\MkSQL\Exceptions\InvalidDriverException;
use Zrnik\MkSQL\Exceptions\TableDefinitionExists;
use Zrnik\MkSQL\Table;
use Zrnik\MkSQL\Updater;

class UpdaterTest extends TestCase
{
    /**
     * @throws InvalidDriverException
     */
    public function testInstall()
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
     */
    public function testTableGet()
    {
        $Updater = new Updater($this->createPDO());
        $Updater->tableCreate("someTable");

        $this->assertNotNull($Updater->tableGet("someTable"));
        $this->assertNull($Updater->tableGet("differentNonExistingTable"));

    }

    /**
     * @throws TableDefinitionExists
     * @throws Exception
     */
    public function testTableAdd()
    {
        $Updater = new Updater($this->createPDO());

        //No problem with adding table now
        $Table = new Table("someTable");
        $Updater->tableAdd($Table);
        $this->addToAssertionCount(1); //Created (because no exception)

        try {
            $Updater->tableAdd($Table);
            throw new Exception("Expected exception " . TableDefinitionExists::class . " not thrown!");
        } catch (TableDefinitionExists $_) {
            $this->addToAssertionCount(1);
        }

    }

    /**
     * @throws TableDefinitionExists
     * @throws Exception
     */
    public function testTableCreate()
    {
        $Updater = new Updater($this->createPDO());

        //No problem with adding table now
        $Updater->tableCreate("someTable");
        $this->addToAssertionCount(1); //Created (because no exception)

        //Now it exists, it should throw an exception
        try {
            $Updater->tableCreate("someTable");
            throw new Exception("Expected exception " . TableDefinitionExists::class . " not thrown!");
        } catch (TableDefinitionExists $_) {
            $this->addToAssertionCount(1);
        }


    }


    /**
     * @throws TableDefinitionExists
     */
    public function testTableList()
    {
        $Updater = new Updater($this->createPDO());

        $this->assertSame(
            [],
            $Updater->tableList()
        );

        $Updater->tableCreate("someTable");

        $this->assertArrayHasKey(
            "someTable",
            $Updater->tableList()
        );

        $this->assertIsObject($Updater->tableList()["someTable"]);


        $Updater->tableCreate("anotherTable");


        $this->assertArrayHasKey(
            "someTable",
            $Updater->tableList()
        );

        $this->assertArrayHasKey(
            "anotherTable",
            $Updater->tableList()
        );

    }
}

<?php declare(strict_types=1);

/*
 * Zrník.eu | MkSQL  
 * User: Programátor
 * Date: 31.08.2020 15:47
 */


use Mock\PDO;
use Zrny\MkSQL\Updater;
use PHPUnit\Framework\TestCase;

class UpdaterTest extends TestCase
{

    private function createPDO() : \Mock\PDO
    {
        return new \Mock\PDO();
    }

    public function testInstall()
    {

        $Updater = new Updater($this->createPDO());
        $Updater->install();

        //Expected no exceptions
        $this->addToAssertionCount(1);
    }


    public function testTableGet()
    {
        $Updater = new Updater($this->createPDO());
        $Updater->tableCreate("someTable");

        $this->assertNotNull($Updater->tableGet("someTable"));
        $this->assertNull($Updater->tableGet("differentNonExistingTable"));

    }

    public function testTableAdd()
    {
        $Updater = new Updater($this->createPDO());

        //No problem with adding table now
        $Table = new \Zrny\MkSQL\Table("someTable");
        $Updater->tableAdd($Table);
        $this->addToAssertionCount(1); //Created (because no exception)

        //Now it exists, it should throw an exception
        $this->expectException(\Zrny\MkSQL\Exceptions\TableDefinitionExists::class);
        $Updater->tableAdd($Table);
    }

    public function testTableCreate()
    {
        $Updater = new Updater($this->createPDO());

        //No problem with adding table now
        $Table = $Updater->tableCreate("someTable");
        $this->addToAssertionCount(1); //Created (because no exception)

        //Now it exists, it should throw an exception
        $this->expectException(\Zrny\MkSQL\Exceptions\TableDefinitionExists::class);
        $Table = $Updater->tableCreate("someTable");
    }



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

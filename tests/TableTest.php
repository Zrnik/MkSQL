<?php declare(strict_types=1);

/*
 * Zrník.eu | MkSQL  
 * User: Programátor
 * Date: 31.08.2020 15:45
 */


use Zrny\MkSQL\Table;
use PHPUnit\Framework\TestCase;

class TableTest extends TestCase
{

    public function testConstructor()
    {
        new Table("someting");
        new Table("This_is_Fine_150");
        $this->addToAssertionCount(1); //Its OK
        $this->expectException(\Zrny\MkSQL\Exceptions\InvalidArgumentException::class);
        new Table("no spaces");
        new Table("no_special_characters!");
        new Table("no_special_characters?");
        new Table("no.dots");
    }



    public function testColumnCreate()
    {
        $TestedTable = new Table("testedTable");
        $Column = $TestedTable->columnCreate("testedColumn");
        $Column = $TestedTable->columnCreate("anotherColumn");
        $this->addToAssertionCount(1);

        $this->expectException(\Zrny\MkSQL\Exceptions\InvalidArgumentException::class);
        $Column = $TestedTable->columnCreate("invalid.column.name");

        $this->expectException(\Zrny\MkSQL\Exceptions\PrimaryKeyAutomaticException::class);
        $Column = $TestedTable->columnCreate("id");

        $this->expectException(\Zrny\MkSQL\Exceptions\ColumnDefinitionExists::class);
        $Column = $TestedTable->columnCreate("testedColumn");
        $Column = $TestedTable->columnCreate("anotherColumn");
    }

    public function testColumnGet()
    {
        $TestedTable = new Table("testedTable");
        $Column = $TestedTable->columnCreate("testedColumn");
        $Column = $TestedTable->columnCreate("anotherColumn");

        $this->assertNotNull($TestedTable->columnGet("testedColumn"));
        $this->assertNotNull($TestedTable->columnGet("anotherColumn"));
        $this->assertNull($TestedTable->columnGet("unknownColumn"));

    }


    public function testColumnList()
    {
        $TestedTable = new Table("testedTable");
        $Column = $TestedTable->columnCreate("testedColumn");

        $this->assertArrayHasKey(
            "testedColumn",
            $TestedTable->columnList()
        );

        $this->assertArrayNotHasKey(
            "anotherColumn",
            $TestedTable->columnList()
        );

        $Column = $TestedTable->columnCreate("anotherColumn");

        $this->assertArrayHasKey(
            "testedColumn",
            $TestedTable->columnList()
        );

        $this->assertArrayHasKey(
            "anotherColumn",
            $TestedTable->columnList()
        );

        $this->assertIsObject($TestedTable->columnList()["testedColumn"]);
        $this->assertIsObject($TestedTable->columnList()["anotherColumn"]);
    }


    public function testGetName()
    {
        $Table = new Table("NameThisTableDefinitelyHas");
        $this->assertSame(
            "NameThisTableDefinitelyHas",
            $Table->getName()
        );

        $Table = new Table("And_another");
        $this->assertSame(
            "And_another",
            $Table->getName()
        );
    }

    public function testInstall()
    {
        $Table = new Table("testedTable");
        $Table->install(\Queries\Tables\TableDescriptionTest::exampleTableDescription());
    }

    public function testSetParent()
    {

    }

    public function testColumnAdd()
    {

    }
}

<?php declare(strict_types=1);

/*
 * Zrník.eu | MkSQL  
 * User: Programátor
 * Date: 31.08.2020 15:45
 */


use Zrny\MkSQL\Column;
use Zrny\MkSQL\Exceptions\ColumnDefinitionExists;
use Zrny\MkSQL\Exceptions\PrimaryKeyAutomaticException;
use Zrny\MkSQL\Table;
use PHPUnit\Framework\TestCase;

class TableTest extends TestCase
{

    /**
     * @throws Exception
     */
    public function testConstructor()
    {
        new Table("something");
        new Table("This_is_Fine_150");
        $this->addToAssertionCount(1); //Its OK

        try
        {
            new Table("no spaces");
            throw new Exception("Expected exception ".\Zrny\MkSQL\Exceptions\InvalidArgumentException::class." not thrown!");
        }
        catch(\Zrny\MkSQL\Exceptions\InvalidArgumentException $_)
        {
            $this->addToAssertionCount(1);
        }

        try
        {
            new Table("no_special_characters!");
            throw new Exception("Expected exception ".\Zrny\MkSQL\Exceptions\InvalidArgumentException::class." not thrown!");
        }
        catch(\Zrny\MkSQL\Exceptions\InvalidArgumentException $_)
        {
            $this->addToAssertionCount(1);
        }

        try
        {
            new Table("no_special_characters?");
            throw new Exception("Expected exception ".\Zrny\MkSQL\Exceptions\InvalidArgumentException::class." not thrown!");
        }
        catch(\Zrny\MkSQL\Exceptions\InvalidArgumentException $_)
        {
            $this->addToAssertionCount(1);
        }

        try
        {
            new Table("no.dots");
            throw new Exception("Expected exception ".\Zrny\MkSQL\Exceptions\InvalidArgumentException::class." not thrown!");
        }
        catch(\Zrny\MkSQL\Exceptions\InvalidArgumentException $_)
        {
            $this->addToAssertionCount(1);
        }

    }


    /**
     * @throws ColumnDefinitionExists
     * @throws PrimaryKeyAutomaticException
     * @throws Exception
     */
    public function testColumnCreate()
    {
        $TestedTable = new Table("testedTable");
        $Column = $TestedTable->columnCreate("testedColumn");
        $Column = $TestedTable->columnCreate("anotherColumn");
        $this->addToAssertionCount(1);


        try
        {
            $Column = $TestedTable->columnCreate("invalid.column.name");
            throw new Exception("Expected exception ".\Zrny\MkSQL\Exceptions\InvalidArgumentException::class." not thrown!");
        }
        catch(\Zrny\MkSQL\Exceptions\InvalidArgumentException $_)
        {
            $this->addToAssertionCount(1);
        }



        try
        {
            $Column = $TestedTable->columnCreate("id");
            throw new Exception("Expected exception ". PrimaryKeyAutomaticException::class." not thrown!");
        }
        catch(PrimaryKeyAutomaticException $_)
        {
            $this->addToAssertionCount(1);
        }


        try
        {
            $Column = $TestedTable->columnCreate("testedColumn");
            throw new Exception("Expected exception ". ColumnDefinitionExists::class." not thrown!");
        }
        catch(ColumnDefinitionExists $_)
        {
            $this->addToAssertionCount(1);
        }

        try
        {
            $Column = $TestedTable->columnCreate("anotherColumn");
            throw new Exception("Expected exception ". ColumnDefinitionExists::class." not thrown!");
        }
        catch(ColumnDefinitionExists $_)
        {
            $this->addToAssertionCount(1);
        }

    }

    /**
     * @throws ColumnDefinitionExists
     * @throws PrimaryKeyAutomaticException
     */
    public function testColumnGet()
    {
        $TestedTable = new Table("testedTable");
        $Column = $TestedTable->columnCreate("testedColumn");
        $Column = $TestedTable->columnCreate("anotherColumn");

        $this->assertNotNull($TestedTable->columnGet("testedColumn"));
        $this->assertNotNull($TestedTable->columnGet("anotherColumn"));
        $this->assertNull($TestedTable->columnGet("unknownColumn"));

    }

    /**
     * @throws ColumnDefinitionExists
     * @throws PrimaryKeyAutomaticException
     */
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

    public function testPrimaryKeyName()
    {
        //Private property wraper
        $TestedTable = new Table("testedTable");
        $this->assertSame(
            "id",
            $TestedTable->getPrimaryKeyName()
        );

        $TestedTable->setPrimaryKeyName("testing_id");

        $this->assertSame(
            "testing_id",
            $TestedTable->getPrimaryKeyName()
        );
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

    /**
     * @throws ColumnDefinitionExists
     * @throws PrimaryKeyAutomaticException
     * @throws Exception
     */
    public function testColumnAdd()
    {
        $Table = new Table("testedTable");
        $ColumnToAdd = new Column("existing_column");

        $Table->columnAdd($ColumnToAdd);
        $this->addToAssertionCount(1);

        //Parent should be set!
        $this->assertSame(
            "testedTable",
            $ColumnToAdd->endColumn()->getName()
        );

        $this->assertNotNull(
            $Table->columnGet("existing_column")
        );

        $this->assertNull(
            $Table->columnGet("random_column_that_doesnt_exist")
        );

        //cannot add twice :)
        try
        {
            $Table->columnAdd($ColumnToAdd);
            throw new Exception("Expected exception ". ColumnDefinitionExists::class." not thrown!");
        }
        catch(ColumnDefinitionExists $_)
        {
            $this->addToAssertionCount(1);
        }

    }
}

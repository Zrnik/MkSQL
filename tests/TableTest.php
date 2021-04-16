<?php declare(strict_types=1);

/*
 * Zrník.eu | MkSQL
 * User: Programátor
 * Date: 31.08.2020 15:45
 */


use PHPUnit\Framework\TestCase;
use Zrnik\MkSQL\Column;
use Zrnik\MkSQL\Exceptions\ColumnDefinitionExists;
use Zrnik\MkSQL\Exceptions\PrimaryKeyAutomaticException;
use Zrnik\MkSQL\Table;

class TableTest extends TestCase
{

    /**
     * @throws Exception
     */
    public function testConstructor(): void
    {
        new Table("something");
        new Table("This_is_Fine_150");
        $this->addToAssertionCount(1); //Its OK

        try {
            new Table("no spaces");
            throw new Exception("Expected exception " . \Zrnik\MkSQL\Exceptions\InvalidArgumentException::class . " not thrown!");
        } catch (\Zrnik\MkSQL\Exceptions\InvalidArgumentException $_) {
            $this->addToAssertionCount(1);
        }

        try {
            new Table("no_special_characters!");
            throw new Exception("Expected exception " . \Zrnik\MkSQL\Exceptions\InvalidArgumentException::class . " not thrown!");
        } catch (\Zrnik\MkSQL\Exceptions\InvalidArgumentException $_) {
            $this->addToAssertionCount(1);
        }

        try {
            new Table("no_special_characters?");
            throw new Exception("Expected exception " . \Zrnik\MkSQL\Exceptions\InvalidArgumentException::class . " not thrown!");
        } catch (\Zrnik\MkSQL\Exceptions\InvalidArgumentException $_) {
            $this->addToAssertionCount(1);
        }

        try {
            new Table("no.dots");
            throw new Exception("Expected exception " . \Zrnik\MkSQL\Exceptions\InvalidArgumentException::class . " not thrown!");
        } catch (\Zrnik\MkSQL\Exceptions\InvalidArgumentException $_) {
            $this->addToAssertionCount(1);
        }

    }


    /**
     * @throws ColumnDefinitionExists
     * @throws PrimaryKeyAutomaticException
     * @throws Exception
     */
    public function testColumnCreate(): void
    {
        $TestedTable = new Table("testedTable");
        $TestedTable->columnCreate("testedColumn");
        $TestedTable->columnCreate("anotherColumn");
        $this->addToAssertionCount(1);


        try {
            $TestedTable->columnCreate("invalid.column.name");
            throw new Exception("Expected exception " . \Zrnik\MkSQL\Exceptions\InvalidArgumentException::class . " not thrown!");
        } catch (\Zrnik\MkSQL\Exceptions\InvalidArgumentException $_) {
            $this->addToAssertionCount(1);
        }


        try {
            $TestedTable->columnCreate("id");
            throw new Exception("Expected exception " . PrimaryKeyAutomaticException::class . " not thrown!");
        } catch (PrimaryKeyAutomaticException $_) {
            $this->addToAssertionCount(1);
        }


        try {
            $TestedTable->columnCreate("testedColumn");
            throw new Exception("Expected exception " . ColumnDefinitionExists::class . " not thrown!");
        } catch (ColumnDefinitionExists $_) {
            $this->addToAssertionCount(1);
        }

        try {
            $TestedTable->columnCreate("anotherColumn");
            throw new Exception("Expected exception " . ColumnDefinitionExists::class . " not thrown!");
        } catch (ColumnDefinitionExists $_) {
            $this->addToAssertionCount(1);
        }

    }

    /**
     * @throws ColumnDefinitionExists
     * @throws PrimaryKeyAutomaticException
     */
    public function testColumnGet(): void
    {
        $TestedTable = new Table("testedTable");
        $TestedTable->columnCreate("testedColumn");
        $TestedTable->columnCreate("anotherColumn");

        $this->assertNotNull($TestedTable->columnGet("testedColumn"));
        $this->assertNotNull($TestedTable->columnGet("anotherColumn"));
        $this->assertNull($TestedTable->columnGet("unknownColumn"));

    }

    /**
     * @throws ColumnDefinitionExists
     * @throws PrimaryKeyAutomaticException
     */
    public function testColumnList(): void
    {
        $TestedTable = new Table("testedTable");
        $TestedTable->columnCreate("testedColumn");

        $this->assertArrayHasKey(
            "testedColumn",
            $TestedTable->columnList()
        );

        $this->assertArrayNotHasKey(
            "anotherColumn",
            $TestedTable->columnList()
        );

        $TestedTable->columnCreate("anotherColumn");

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

    public function testPrimaryKeyName(): void
    {
        //Private property wrapper
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


    public function testGetName(): void
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
    public function testColumnAdd(): void
    {
        $Table = new Table("testedTable");
        $ColumnToAdd = new Column("existing_column");

        $Table->columnAdd($ColumnToAdd);
        $this->addToAssertionCount(1);

        //Parent should be set!

        $col = $ColumnToAdd->endColumn();

        $this->assertNotNull($col);

        $this->assertSame(
            "testedTable",
            $col->getName()
        );

        $this->assertNotNull(
            $Table->columnGet("existing_column")
        );

        $this->assertNull(
            $Table->columnGet("random_column_that_doesnt_exist")
        );

        //cannot add twice :)
        try {
            $Table->columnAdd($ColumnToAdd);
            throw new Exception("Expected exception " . ColumnDefinitionExists::class . " not thrown!");
        } catch (ColumnDefinitionExists $_) {
            $this->addToAssertionCount(1);
        }

    }
}

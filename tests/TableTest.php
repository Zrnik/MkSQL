<?php declare(strict_types=1);

/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Tests;

use Exception;
use PHPUnit\Framework\TestCase;
use Zrnik\MkSQL\Column;
use Zrnik\MkSQL\Exceptions\ColumnDefinitionExists;
use Zrnik\MkSQL\Exceptions\InvalidArgumentException;
use Zrnik\MkSQL\Exceptions\PrimaryKeyAutomaticException;
use Zrnik\MkSQL\Table;
use Zrnik\PHPUnit\Exceptions;


class TableTest extends TestCase
{
    use Exceptions;

    /**
     * @throws Exception
     */
    public function testConstructor(): void
    {
        new Table('something');
        new Table('This_is_Fine_150');
        $this->addToAssertionCount(1); // It's OK

        $this->assertExceptionThrown(
            InvalidArgumentException::class,
            function () {
                new Table('no spaces');
            }
        );

        $this->assertExceptionThrown(
            InvalidArgumentException::class,
            function () {
                new Table('no_special_characters!');
            }
        );

        $this->assertExceptionThrown(
            InvalidArgumentException::class,
            function () {
                new Table('no_special_characters?');
            }
        );

        $this->assertExceptionThrown(
            InvalidArgumentException::class,
            function () {
                new Table('no.dots');
            }
        );

    }


    /**
     * @throws ColumnDefinitionExists
     * @throws PrimaryKeyAutomaticException
     * @throws Exception
     */
    public function testColumnCreate(): void
    {
        $TestedTable = new Table('testedTable');
        $TestedTable->columnCreate('testedColumn');
        $TestedTable->columnCreate('anotherColumn');
        $this->addToAssertionCount(1);

        $this->assertExceptionThrown(
            InvalidArgumentException::class,
            function () use ($TestedTable) {
                $TestedTable->columnCreate('invalid.column.name');
            }
        );


        $this->assertExceptionThrown(
            PrimaryKeyAutomaticException::class,
            function () use ($TestedTable) {
                $TestedTable->columnCreate('id');
            }
        );

        $this->assertExceptionThrown(
            ColumnDefinitionExists::class,
            function () use ($TestedTable) {
                $TestedTable->columnCreate('testedColumn');
            }
        );

        $this->assertExceptionThrown(
            ColumnDefinitionExists::class,
            function () use ($TestedTable) {
                $TestedTable->columnCreate('anotherColumn');
            }
        );
    }

    /**
     * @throws ColumnDefinitionExists
     * @throws PrimaryKeyAutomaticException
     */
    public function testColumnGet(): void
    {
        $TestedTable = new Table('testedTable');
        $TestedTable->columnCreate('testedColumn');
        $TestedTable->columnCreate('anotherColumn');

        static::assertNotNull($TestedTable->columnGet('testedColumn'));
        static::assertNotNull($TestedTable->columnGet('anotherColumn'));
        static::assertNull($TestedTable->columnGet('unknownColumn'));

    }

    /**
     * @throws ColumnDefinitionExists
     * @throws PrimaryKeyAutomaticException
     */
    public function testColumnList(): void
    {
        $TestedTable = new Table('testedTable');
        $TestedTable->columnCreate('testedColumn');

        static::assertArrayHasKey(
            'testedColumn',
            $TestedTable->columnList()
        );

        static::assertArrayNotHasKey(
            'anotherColumn',
            $TestedTable->columnList()
        );

        $TestedTable->columnCreate('anotherColumn');

        static::assertArrayHasKey(
            'testedColumn',
            $TestedTable->columnList()
        );

        static::assertArrayHasKey(
            'anotherColumn',
            $TestedTable->columnList()
        );

        static::assertIsObject($TestedTable->columnList()['testedColumn']);
        static::assertIsObject($TestedTable->columnList()['anotherColumn']);
    }

    public function testPrimaryKeyName(): void
    {
        //Private property wrapper
        $TestedTable = new Table('testedTable');
        static::assertSame(
            'id',
            $TestedTable->getPrimaryKeyName()
        );

        $TestedTable->setPrimaryKeyName('testing_id');

        static::assertSame(
            'testing_id',
            $TestedTable->getPrimaryKeyName()
        );
    }


    public function testGetName(): void
    {
        $Table = new Table('NameThisTableDefinitelyHas');
        static::assertSame(
            'NameThisTableDefinitelyHas',
            $Table->getName()
        );

        $Table = new Table('And_another');
        static::assertSame(
            'And_another',
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
        $Table = new Table('testedTable');
        $ColumnToAdd = new Column('existing_column');

        $Table->columnAdd($ColumnToAdd);
        $this->addToAssertionCount(1);

        //Parent should be set!

        $col = $ColumnToAdd->endColumn();

        static::assertNotNull($col);

        static::assertSame(
            'testedTable',
            $col->getName()
        );

        static::assertNotNull(
            $Table->columnGet('existing_column')
        );

        static::assertNull(
            $Table->columnGet('random_column_that_doesnt_exist')
        );

        //cannot add twice :)
        $this->assertExceptionThrown(
            ColumnDefinitionExists::class,
            function () use ($Table, $ColumnToAdd) {
                $Table->columnAdd($ColumnToAdd);
            }
        );
    }
}

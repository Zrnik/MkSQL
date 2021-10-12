<?php declare(strict_types=1);

/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Tests;

use Exception;
use LogicException;
use Tests\Mock\MockSQLMaker_ExistingTable_First;
use PHPUnit\Framework\TestCase;
use Tests\Mock\PDO;
use Zrnik\MkSQL\Column;
use Zrnik\MkSQL\Exceptions\ColumnDefinitionExists;
use Zrnik\MkSQL\Exceptions\MkSQLException;
use Zrnik\MkSQL\Exceptions\PrimaryKeyAutomaticException;
use Zrnik\MkSQL\Exceptions\TableDefinitionExists;
use Zrnik\MkSQL\Exceptions\InvalidArgumentException;
use Zrnik\MkSQL\Table;
use Zrnik\MkSQL\Updater;
use Zrnik\PHPUnit\Exceptions;

class ColumnTest extends TestCase
{
    use Exceptions;

    /**
     * @throws Exception
     */
    public function testConstructor(): void
    {
        new Column('tested');
        $this->addToAssertionCount(1);

        new Column('tested', 'text');
        $this->addToAssertionCount(1);

        new Column('tested', 'something(10, 20, 30)');
        $this->addToAssertionCount(1);


        $this->assertExceptionThrown(
            InvalidArgumentException::class,
            function () {
                new Column('tested.table', 'text');
            }
        );

        $this->assertExceptionThrown(
            InvalidArgumentException::class,
            function () {
                new Column('tested', 'text.value');
            }
        );
    }

    public function testGetName(): void
    {
        $tested1 = new Column('tested_column_1');
        static::assertSame(
            'tested_column_1',
            $tested1->getName()
        );

        $tested2 = new Column('testing_different_value');
        static::assertSame(
            'testing_different_value',
            $tested2->getName()
        );
    }

    /**
     * @throws Exception
     */
    public function testForeignKeys(): void
    {
        $updater = new Updater(new PDO());

        $updater->tableCreate('accounts');
        $sessions = $updater->tableCreate('sessions')
            ->columnCreate('account');

        static::assertSame(
            [],
            $sessions->getForeignKeys()
        );

        $sessions->addForeignKey('accounts.id');

        static::assertSame(
            [
                'accounts.id'
            ],
            $sessions->getForeignKeys()
        );

        $this->assertExceptionThrown(
            InvalidArgumentException::class,
            function () use ($sessions) {
                $sessions->addForeignKey('double.dot.error');
            }
        );

        $this->assertExceptionThrown(
            InvalidArgumentException::class,
            function () use ($sessions) {
                $sessions->addForeignKey('no_dot_error');
            }
        );


        $this->assertExceptionThrown(
            InvalidArgumentException::class,
            function () use ($sessions) {
                $sessions->addForeignKey('accounts.id');
            }
        );

    }

    /**
     * @throws Exception
     */
    public function testComments(): void
    {
        $column = new Column('tested');

        static::assertNull($column->getComment());

        $column->setComment('Hello World');

        static::assertSame(
            'Hello World',
            $column->getComment()
        );


        $column->setComment('Its possible to form sentences, even with comma.');

        static::assertSame(
            'Its possible to form sentences, even with comma.',
            $column->getComment()
        );

        //No argument will reset (or when u set argument as null!)
        $column->setComment();
        static::assertNull($column->getComment());


        // only A-Z, a-z, 0-9, underscore, comma and space are allowed!
        $this->assertExceptionThrown(
            InvalidArgumentException::class,
            function () use ($column) {
                $column->setComment('It works!');
            }
        );

        $this->assertExceptionThrown(
            InvalidArgumentException::class,
            function () use ($column) {
                $column->setComment('Really?');
            }
        );

    }


    /**
     * @throws Exception
     */
    public function testTypes(): void
    {
        //default type is INT!
        static::assertSame(
            'int',
            (new Column('hello'))->getType()
        );

        //It should save and then return it
        static::assertSame(
            'longtext',
            (new Column('hello', 'longtext'))->getType()
        );

        // Only allowed stuff and removed spaces. (A-Z, a-z, 0-9, parenthesis and a comma)
        static::assertSame(
            'something(10,11,12)',
            (new Column('hello', 'something (10, 11, 12) '))->getType()
        );


        //something invalid in type:
        $this->assertExceptionThrown(
            InvalidArgumentException::class,
            function () {
                new Column('hello', 'world.invalid');
            }
        );

    }

    public function testNotNull(): void
    {
        //Only wraps public property...
        $tested = new Column('tested');

        static::assertNotTrue($tested->getNotNull());

        $tested->setNotNull();
        static::assertTrue($tested->getNotNull());

        $tested->setNotNull(false);
        static::assertNotTrue($tested->getNotNull());

        $tested->setNotNull();
        static::assertTrue($tested->getNotNull());
    }

    public function testGetUnique(): void
    {
        //Only wraps public property...
        $tested = new Column('tested');

        static::assertNotTrue($tested->getUnique());

        $tested->setUnique();
        static::assertTrue($tested->getUnique());

        $tested->setUnique(false);
        static::assertNotTrue($tested->getUnique());

        $tested->setUnique();
        static::assertTrue($tested->getUnique());
    }


    public function testDefault(): void
    {
        $column = new Column('tested');

        //String or null
        //default = null
        static::assertNull($column->getDefault());

        //Set string
        $column->setDefault('Hello World');

        static::assertSame(
            'Hello World',
            $column->getDefault()
        );

        //No argument will reset (or when u set argument as null!)
        $column->setDefault();
        static::assertNull($column->getDefault());

        //Default value can be anything, we leave it up to programmer.

        $column->setDefault(0);
        static::assertSame(
            0,
            $column->getDefault()
        );

        $column->setDefault(false);
        static::assertFalse(
            $column->getDefault()
        );

        $column->setDefault(PHP_INT_MAX);
        static::assertSame(
            PHP_INT_MAX,
            $column->getDefault()
        );

        $column->setDefault(0.42069); //stoned nice
        static::assertSame(
            0.42069,
            $column->getDefault()
        );
    }

    /**
     * @throws MkSQLException
     */
    public function testSetParent(): void
    {
        $table = new Table('RequiredAsParent');
        $column = new Column('tested');

        //endColumn returns parent!
        static::assertNull($column->endColumn());

        $column->setParent($table);

        static::assertSame(
            'RequiredAsParent',
            $column->endColumn()?->getName()
        );


        $column = new Column('tested2');
        static::assertNull($column->endColumn());

        // table addColumn will autoSet parent!
        $table->columnAdd($column);
        static::assertSame(
            'RequiredAsParent',
            $column->endColumn()?->getName()
        );

        //and created column is automatically parented too
        static::assertSame(
            'RequiredAsParent',
            $table->columnCreate('new_auto_created_column')->endColumn()?->getName()
        );


        $table2 = new Table('RequiredAsParent2');

        $columnToRewriteParent = new Column('tested3');

        $table->columnAdd($columnToRewriteParent);

        //Here i expect a logic exception!
        $this->assertExceptionThrown(
            LogicException::class,
            function () use ($table2, $columnToRewriteParent) {
                $table2->columnAdd($columnToRewriteParent);
            }
        );

        // Cloning is OK, should not throw error!
        $table2->columnAdd(clone $columnToRewriteParent);
        $this->addToAssertionCount(1);
    }

    /**
     * @throws ColumnDefinitionExists
     * @throws PrimaryKeyAutomaticException
     * @throws TableDefinitionExists
     */
    public function testInstall(): void
    {
        //Get Mocked QueryMaker TableDefinition with prepared Tables:
        $desc = MockSQLMaker_ExistingTable_First::describeTable(new PDO(), new Table('null'));

        static::assertNotNull($desc);

        $column = $desc->table->columnGet('name');

        static::assertNotNull($column);

        //Should not fail.
        $QueriesToExecute = $column->install($desc, $desc->columnGet('name'));

        //Mock SQLMakers should not create any queries!
        static::assertSame(
            [],
            $QueriesToExecute
        );
    }
}

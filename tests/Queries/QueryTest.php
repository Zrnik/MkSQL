<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Queries;

use Mock\PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use Zrnik\MkSQL\Column;
use Zrnik\MkSQL\Queries\Query;
use Zrnik\MkSQL\Table;
use Zrnik\MkSQL\Updater;

class QueryTest extends TestCase
{
    public function testReason(): void
    {
        //Reason is wrapper for private property...
        $query = new Query(new Table('tested'), null);

        //Default is empty
        static::assertEmpty($query->getReason());

        $query->setReason('This is a reason! :)');
        static::assertSame(
            'This is a reason! :)',
            $query->getReason()
        );

        $query->setReason('No reason');
        static::assertSame(
            'No reason',
            $query->getReason()
        );

        //no param = ""
        $query->setReason();
        static::assertEmpty($query->getReason());
    }

    public function testError(): void
    {
        $query = new Query(new Table('tested'), null);

        static::assertNull($query->errorText);

        $query->setError(new PDOException('This is PDO exception!'));

        static::assertSame(
            'This is PDO exception!',
            $query->errorText
        );
    }

    public function testGetTable(): void
    {
        $query = new Query(new Table('tested'), null);

        static::assertSame(
            'tested',
            $query->getTable()->getName()
        );

        $query = new Query(new Table('different_one'), null);

        static::assertSame(
            'different_one',
            $query->getTable()->getName()
        );
    }

    public function testGetColumn(): void
    {
        $query = new Query(new Table('tested'), null);

        static::assertNull($query->getColumn());

        $query = new Query(new Table('tested'), new Column('tested'));

        static::assertSame(
            'tested',
            $query->getColumn()?->getName()
        );

        $query = new Query(new Table('tested'), new Column('different_one'));

        static::assertSame(
            'different_one',
            $query->getColumn()?->getName()
        );
    }


    public function testExecute(): void
    {
        $query = new Query(new Table('tested'), null);

        $MockPDO = new PDO();

        $MockPDO->expectQuery(/** @lang */ 'SELECT * FROM random_table WHERE id = ?');
        $MockPDO->expectParams([10]);

        $query->setQuery(/** @lang */ 'SELECT * FROM random_table WHERE id = ?');
        $query->paramAdd(10); //id

        $query->execute($MockPDO, new Updater($MockPDO));
        $this->addToAssertionCount(1);
    }


    public function testQuery(): void
    {
        $query = new Query(new Table('tested'), new Column('tested'));
        static::assertEmpty($query->getQuery());

        $query->setQuery('SOME QUERY FROM test');

        static::assertSame(
            'SOME QUERY FROM test',
            $query->getQuery()
        );

        $query->setQuery('');
        static::assertEmpty($query->getQuery());

        $query->setQuery('another');
        static::assertSame(
            'another',
            $query->getQuery()
        );
    }


    public function testParams(): void
    {
        $query = new Query(new Table('tested'), new Column('tested'));

        static::assertSame(
            [],
            $query->params()
        );

        $query->paramAdd('test');

        static::assertSame(
            [
                'test'
            ],
            $query->params()
        );

        $query->paramAdd(1337);

        static::assertSame(
            [
                'test',
                1337
            ],
            $query->params()
        );

        $query->paramAdd(0.42069);

        static::assertSame(
            [
                'test',
                1337,
                0.42069
            ],
            $query->params()
        );

        $query->paramAdd(true);

        static::assertSame(
            [
                'test',
                1337,
                0.42069,
                true
            ],
            $query->params()
        );

        $query->paramAdd(null);

        static::assertSame(
            [
                'test',
                1337,
                0.42069,
                true,
                null
            ],
            $query->params()
        );
    }

}

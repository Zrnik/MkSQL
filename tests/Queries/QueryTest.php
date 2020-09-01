<?php declare(strict_types=1);
/*
 * Zrník.eu | MkSQL  
 * User: Programátor
 * Date: 31.08.2020 15:47
 */

namespace Queries;

use Mock\PDO;
use PDOException;
use Zrny\MkSQL\Column;
use Zrny\MkSQL\Queries\Query;
use PHPUnit\Framework\TestCase;
use Zrny\MkSQL\Table;

class QueryTest extends TestCase
{

    public function testReason()
    {
        //Reason is wrapper for private property...
        $query = new Query(new Table("tested"),null);

        //Default is empty
        $this->assertEmpty($query->getReason());

        $query->setReason("This is a reason! :)");
        $this->assertSame(
            "This is a reason! :)",
            $query->getReason()
        );

        $query->setReason("No reason");
        $this->assertSame(
            "No reason",
            $query->getReason()
        );

        //no param = ""
        $query->setReason();
        $this->assertEmpty($query->getReason());
    }

    public function testError()
    {
        $query = new Query(new Table("tested"),null);

        $this->assertNull($query->errorText);

        $query->setError(new PDOException("This is PDO exception!"));

        $this->assertSame(
            "This is PDO exception!",
            $query->errorText
        );
    }

    public function testGetTable()
    {
        $query = new Query(new Table("tested"),null);

        $this->assertSame(
            "tested",
            $query->getTable()->getName()
        );

        $query = new Query(new Table("different_one"),null);

        $this->assertSame(
            "different_one",
            $query->getTable()->getName()
        );
    }

    public function testGetColumn()
    {
        $query = new Query(new Table("tested"),null);

        $this->assertNull($query->getColumn());

        $query = new Query(new Table("tested"),new Column("tested"));

        $this->assertSame(
            "tested",
            $query->getColumn()->getName()
        );

        $query = new Query(new Table("tested"),new Column("different_one"));

        $this->assertSame(
            "different_one",
            $query->getColumn()->getName()
        );
    }



    public function testExecute()
    {
        $query = new Query(new Table("tested"),null);

        $MockPDO = new PDO();

        $MockPDO->expectQuery("SELECT * FROM random_table WHERE id = ?");
        $MockPDO->expectParams([10]);

        $query->setQuery("SELECT * FROM random_table WHERE id = ?");
        $query->paramAdd(10); //id

        $query->execute($MockPDO);
    }



    public function testQuery()
    {
        $query = new Query(new Table("tested"),new Column("tested"));
        $this->assertEmpty($query->getQuery());

        $query->setQuery("SOME QUERY FROM test");

        $this->assertSame(
            "SOME QUERY FROM test",
            $query->getQuery()
        );

        $query->setQuery('');
        $this->assertEmpty($query->getQuery());

        $query->setQuery('another');
        $this->assertSame(
            "another",
            $query->getQuery()
        );
    }


    public function testParams()
    {
        $query = new Query(new Table("tested"),new Column("tested"));

        $this->assertSame(
            [],
            $query->params()
        );

        $query->paramAdd("test");

        $this->assertSame(
            [
                "test"
            ],
            $query->params()
        );

        $query->paramAdd(1337);

        $this->assertSame(
            [
                "test",
                1337
            ],
            $query->params()
        );

        $query->paramAdd(0.42069);

        $this->assertSame(
            [
                "test",
                1337,
                0.42069
            ],
            $query->params()
        );

        $query->paramAdd(true);

        $this->assertSame(
            [
                "test",
                1337,
                0.42069,
                true
            ],
            $query->params()
        );

        $query->paramAdd(null);

        $this->assertSame(
            [
                "test",
                1337,
                0.42069,
                true,
                null
            ],
            $query->params()
        );
    }

}

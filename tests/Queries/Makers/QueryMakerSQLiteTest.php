<?php declare(strict_types=1);
/*
 * Zrník.eu | MkSQL
 * User: Programátor
 * Date: 31.08.2020 15:44
 */

namespace Queries\Makers;

use Mock\MockSQLMaker_NotExistingTable_First;
use Mock\PDO;
use Zrny\MkSQL\Column;
use Zrny\MkSQL\Queries\Makers\QueryMakerSQLite;
use PHPUnit\Framework\TestCase;
use Zrny\MkSQL\Table;

class QueryMakerSQLiteTest extends TestCase
{

    public function testDescribeTable()
    {
        $MockPDO = new PDO();
        $MockPDO->mockResult([
            "SELECT * FROM sqlite_master WHERE tbl_name = 'tested_not_exist'" => [],
            "SELECT * FROM sqlite_master WHERE tbl_name = 'known_table'" => [
                [
                    "type" => "table",
                    "name" => "known_table",
                    "tbl_name" => "known_table",
                    "rootpage" => 2,
                    "sql" => /** @lang SQLite */ "CREATE TABLE known_table
                    (
                        id integer not null
                            constraint known_table_pk
                                primary key autoincrement
                    , name varchar(255) default 'undefined' not null, price decimal(13,2))"
                ]
            ],
            "SELECT * FROM sqlite_master WHERE tbl_name = 'sub_table'" => [
                [
                    "type" => "table",
                    "name" => "sub_table",
                    "tbl_name" => "sub_table",
                    "rootpage" => 6,
                    "sql" => /** @lang SQLite */ "CREATE TABLE \"sub_table\"
                        (
                            id integer not null
                                constraint sub_table_pk
                                    primary key autoincrement,
                            parent int
                                constraint sub_table_known_table_id_fk
                                    references known_table
                        )"
                ]
            ],
        ]);

        $description = QueryMakerSQLite::describeTable($MockPDO, new Table("tested_not_exist"));

        $this->assertNotNull($description);
        $this->assertNotTrue($description->tableExists);

        $Table = new Table("known_table");

        $Table->columnCreate("name","varchar(255)")->setNotNull()->setDefault("undefined");
        $Table->columnCreate("price","decimal(13, 2)");

        $description = QueryMakerSQLite::describeTable($MockPDO,$Table);

        $this->assertNotNull($description);

        $this->assertTrue($description->tableExists);

        $this->assertSame(
            "known_table",
            $description->table->getName()
        );

        $this->assertSame(
            "name",
            $description->columnGet("name")->column->getName()
        );

        $this->assertSame(
            "varchar(255)",
            $description->columnGet("name")->type
        );

        $this->assertSame(
            [],
            $description->columnGet("name")->foreignKeys
        );

        $this->assertTrue($description->columnGet("name")->columnExists);

        $this->assertSame(
            "undefined",
            $description->columnGet("name")->default
        );

        $this->assertNull($description->columnGet("name")->comment);

        $this->assertTrue($description->columnGet("name")->notNull);

        $this->assertNull($description->columnGet("name")->uniqueIndex);

        $this->assertSame(
            "price",
            $description->columnGet("price")->column->getName()
        );

        $this->assertSame(
            null,
            $description->columnGet("price")->default
        );


        $Table = new Table("sub_table");
        $Table->columnCreate("parent")->addForeignKey("known_table.id");

        $description = QueryMakerSQLite::describeTable($MockPDO,$Table);

        $this->assertNotNull($description);

        $this->assertTrue($description->tableExists);

        //var_dump($description->columnGet("parent"));

        $this->assertSame("int", $description->columnGet("parent")->type);

        $this->assertSame([
            'known_table.id' => 'sub_table_known_table_id_fk'
        ], $description->columnGet("parent")->foreignKeys);

        //SQLite does not support comments!
        $this->assertSame(
            null,
            $description->columnGet("parent")->comment
        );
    }


    public function testCreateTableQuery()
    {
        $Desc = MockSQLMaker_NotExistingTable_First::describeTable(new PDO(), new Table(""));
        $Queries = QueryMakerSQLite::createTableQuery($Desc->table, $Desc);

        //Create SQLite Table = 1 query
        $this->assertCount(
            1,$Queries
        );
    }


    public function testCreateUniqueIndexQuery()
    {
        $Desc = MockSQLMaker_NotExistingTable_First::describeTable(new PDO(), new Table(""));
        $Queries = QueryMakerSQLite::createUniqueIndexQuery(
            $Desc->table,
            $Desc->table->columnGet("name"),
            $Desc,
            $Desc->columnGet("name")
        );

        //Create SQLite Unique Index = 1 query
        $this->assertCount(
            1,$Queries
        );

    }

    public function testRemoveUniqueIndexQuery()
    {}


    public function testCreateTableColumnQuery()
    {}

    public function testAlterTableColumnQuery()
    {}


    public function testCreateForeignKey()
    {}

    public function testRemoveForeignKey()
    {}


    public function testCompareType()
    {}

    public function testCompareComment()
    {}

}

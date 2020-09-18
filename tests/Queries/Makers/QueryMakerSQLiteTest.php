<?php declare(strict_types=1);
/*
 * Zrník.eu | MkSQL
 * User: Programátor
 * Date: 31.08.2020 15:44
 */

namespace Queries\Makers;

use Mock\MockSQLMaker_NotExistingTable_First;
use Mock\PDO;
use PHPUnit\Framework\TestCase;
use Zrnik\MkSQL\Exceptions\ColumnDefinitionExists;
use Zrnik\MkSQL\Exceptions\PrimaryKeyAutomaticException;
use Zrnik\MkSQL\Exceptions\TableDefinitionExists;
use Zrnik\MkSQL\Queries\Makers\QueryMakerSQLite;
use Zrnik\MkSQL\Table;
use Zrnik\MkSQL\Updater;

class QueryMakerSQLiteTest extends TestCase
{

    /**
     * @throws ColumnDefinitionExists
     * @throws PrimaryKeyAutomaticException
     * @throws TableDefinitionExists
     */
    public function testDescribeTable()
    {
        $MockPDO = new PDO();
        $MockPDO->mockResult([
            /** @lang text */ "SELECT * FROM sqlite_master WHERE tbl_name = 'tested_not_exist'" => [],
            /** @lang text */ "SELECT * FROM sqlite_master WHERE tbl_name = 'known_table'" => [
                [
                    "type" => "table",
                    "name" => "known_table",
                    "tbl_name" => "known_table",
                    "root" . "page" => 2,
                    "sql" => /** @lang SQLite */ "CREATE TABLE known_table
                    (
                        id integer not null
                            constraint known_table_pk
                                primary key autoincrement
                    , name varchar(255) default 'undefined' not null, price decimal(13,2))"
                ]
            ],
            /** @lang text */ "SELECT * FROM sqlite_master WHERE tbl_name = 'sub_table'" => [
                [
                    "type" => "table",
                    "name" => "sub_table",
                    "tbl_name" => "sub_table",
                    "root" . "page" => 6,
                    "sql" => /** @lang */ "CREATE TABLE \"sub_table\"
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

        $updater = new Updater($MockPDO);

        $description = QueryMakerSQLite::describeTable($MockPDO, new Table("tested_not_exist"));

        $this->assertNotNull($description);
        $this->assertNotTrue($description->tableExists);

        $Table = $updater->tableCreate("known_table");

        $Table->columnCreate("name", "varchar(255)")->setNotNull()->setDefault("undefined");
        $Table->columnCreate("price", "decimal(13, 2)");

        $description = QueryMakerSQLite::describeTable($MockPDO, $Table);

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


        $Table = $updater->tableCreate("sub_table");
        $Table->columnCreate("parent")->addForeignKey("known_table.id");

        $description = QueryMakerSQLite::describeTable($MockPDO, $Table);

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

    /**
     * @throws ColumnDefinitionExists
     * @throws PrimaryKeyAutomaticException
     * @throws TableDefinitionExists
     */
    public function testChangePrimaryKeyQuery()
    {
        $Desc = MockSQLMaker_NotExistingTable_First::describeTable(new PDO(), new Table(""));
        $Queries = QueryMakerSQLite::changePrimaryKeyQuery(
            "id",
            $Desc->table,
            $Desc
        );

        // Same like altering a column, requires a temp table...
        $this->assertGreaterThanOrEqual(
            4, $Queries
        );
    }

    /**
     * @throws ColumnDefinitionExists
     * @throws PrimaryKeyAutomaticException
     * @throws TableDefinitionExists
     */
    public function testCreateTableQuery()
    {
        $Desc = MockSQLMaker_NotExistingTable_First::describeTable(new PDO(), new Table(""));
        $Queries = QueryMakerSQLite::createTableQuery($Desc->table, $Desc);

        //Create SQLite Table = 1 query
        $this->assertCount(
            1, $Queries
        );
    }

    /**
     * @throws ColumnDefinitionExists
     * @throws PrimaryKeyAutomaticException
     * @throws TableDefinitionExists
     */
    public function testAlterTableColumnQuery()
    {

        $Desc = MockSQLMaker_NotExistingTable_First::describeTable(new PDO(), new Table(""));
        $Queries = QueryMakerSQLite::alterTableColumnQuery(
            $Desc->table,
            $Desc->table->columnGet("name"),
            $Desc,
            $Desc->columnGet("name")
        );

        // Changing a table in SQLite requires creating of temporary table!
        // 1. Create Temporary Table (+1)
        // 2. Add columns to temporary table (+0 as there can be none)
        // 3. Move Data (+1)
        // 3. Drop Old Table (+1)
        // 3. Rename Temporary Table (+1)
        // 2. Crate unique indexes (+0 as there can be none)
        //
        // = Minimum of 4!
        $this->assertGreaterThanOrEqual(
            4, $Queries
        );

        // Create Temporary Table
        $this->assertStringContainsString(
            "CREATE TABLE",
            $Queries[0]->getQuery()
        );

        $index = count($Queries) - 1;
        while (strpos($Queries[$index]->getQuery(), "CREATE UNIQUE INDEX") !== false) {
            $index--;
        }

        //Now we go backwards:

        //RENAME TEMPORARY TO ORIGINAL
        $this->assertStringContainsString(
            "ALTER TABLE",
            $Queries[$index]->getQuery()
        );

        $this->assertStringContainsString(
            "RENAME TO",
            $Queries[$index]->getQuery()
        );

        //DROP OLD TABLE
        $this->assertStringContainsString(
            "DROP TABLE",
            $Queries[$index - 1]->getQuery()
        );

        //INSERT FROM OLD TABLE TO TEMP TABLE
        $this->assertStringContainsString(
            "INSERT INTO",
            $Queries[$index - 2]->getQuery()
        );
        $this->assertStringContainsString(
            "SELECT",
            $Queries[$index - 2]->getQuery()
        );
        $this->assertStringContainsString(
            "FROM",
            $Queries[$index - 2]->getQuery()
        );

        // Rest of queries are adding
        // columns to temp table and/or
        // creating indexes
    }

    /**
     * @throws ColumnDefinitionExists
     * @throws PrimaryKeyAutomaticException
     * @throws TableDefinitionExists
     */
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
            1, $Queries
        );

        $this->assertStringContainsString(
            "CREATE UNIQUE INDEX",
            $Queries[0]->getQuery()
        );


    }

    /**
     * @throws ColumnDefinitionExists
     * @throws PrimaryKeyAutomaticException
     * @throws TableDefinitionExists
     */
    public function testRemoveUniqueIndexQuery()
    {
        $Desc = MockSQLMaker_NotExistingTable_First::describeTable(new PDO(), new Table(""));
        $Queries = QueryMakerSQLite::removeUniqueIndexQuery(
            $Desc->table,
            $Desc->table->columnGet("name"),
            "some_index_we_have_found",
            $Desc,
            $Desc->columnGet("name")
        );

        //Remove SQLite Unique Index = 1 query
        $this->assertCount(
            1, $Queries
        );

        $this->assertStringContainsString(
        /** @lang text */ "DROP INDEX some_index_we_have_found",
            $Queries[0]->getQuery()
        );
    }

    /**
     * @throws ColumnDefinitionExists
     * @throws PrimaryKeyAutomaticException
     * @throws TableDefinitionExists
     */
    public function testCreateTableColumnQuery()
    {
        $Desc = MockSQLMaker_NotExistingTable_First::describeTable(new PDO(), new Table(""));
        $Queries = QueryMakerSQLite::createTableColumnQuery(
            $Desc->table,
            $Desc->table->columnGet("name"),
            $Desc,
            $Desc->columnGet("name")
        );

        //Create SQLite Column = 1 query
        $this->assertCount(
            1, $Queries
        );

        $this->assertStringContainsString(
        /** @lang text */ "ALTER TABLE",
            $Queries[0]->getQuery()
        );

        $this->assertStringContainsString(
        /** @lang text */ "ADD",
            $Queries[0]->getQuery()
        );
    }

    /**
     * @throws ColumnDefinitionExists
     * @throws PrimaryKeyAutomaticException
     * @throws TableDefinitionExists
     */
    public function testCreateForeignKey()
    {
        $Desc = MockSQLMaker_NotExistingTable_First::describeTable(new PDO(), new Table(""));
        $Queries = QueryMakerSQLite::createForeignKey(
            $Desc->table,
            $Desc->table->columnGet("name"),
            "table.column",
            $Desc,
            $Desc->columnGet("name")
        );

        // Same like altering a column, requires a temp table...
        $this->assertGreaterThanOrEqual(
            4, $Queries
        );
    }

    /**
     * @throws ColumnDefinitionExists
     * @throws PrimaryKeyAutomaticException
     * @throws TableDefinitionExists
     */
    public function testRemoveForeignKey()
    {
        $Desc = MockSQLMaker_NotExistingTable_First::describeTable(new PDO(), new Table(""));
        $Queries = QueryMakerSQLite::removeForeignKey(
            $Desc->table,
            $Desc->table->columnGet("name"),
            "some_key_we_have_found_before",
            $Desc,
            $Desc->columnGet("name")
        );

        // Again, rewriting whole table. good luck XD
        $this->assertGreaterThanOrEqual(
            4, $Queries
        );
    }


    public function testCompareType()
    {
        $SameTypes = [
            "InTeGeR" => "integer",
            "int" => "integer",
            "Hello (1, 2, 3)" => "hello(1,2,3)",
            "integer" => "int",
            "tinyint(1)" => "tinyint",
            "int(3310)" => "integer",
            "int(3310) " => "int",
        ];

        foreach ($SameTypes as $t1 => $t2)
            $this->assertTrue(QueryMakerSQLite::compareType($t1, $t2));

        $NotSameTypes = [
            "int" => "string",
            "tinyint" => "mediumint",
            "text" => "varchar(255)"
        ];

        foreach ($NotSameTypes as $t1 => $t2)
            $this->assertNotTrue(QueryMakerSQLite::compareType($t1, $t2));
    }

    public function testCompareComment()
    {
        $LiterallyAnything = [
            "yep" => "yep",
            "hello" => "world",
            ":)" => ":(",
            "string_or_null" => null,
            null => "string_or_null"
        ];

        //As sqlite does not support comments, it always returns true so it does not trigger an update everytime.
        foreach ($LiterallyAnything as $c1 => $c2)
            $this->assertTrue(QueryMakerSQLite::compareComment($c1, $c2));
    }

}

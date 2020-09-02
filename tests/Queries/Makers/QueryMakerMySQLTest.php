<?php declare(strict_types=1);
/*
 * Zrník.eu | MkSQL
 * User: Programátor
 * Date: 31.08.2020 15:44
 */

namespace Queries\Makers;

use Mock\MockSQLMaker_ExistingTable_Second;
use Mock\MockSQLMaker_NotExistingTable_First;
use Mock\PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use Zrny\MkSQL\Exceptions\ColumnDefinitionExists;
use Zrny\MkSQL\Exceptions\PrimaryKeyAutomaticException;
use Zrny\MkSQL\Exceptions\TableDefinitionExists;
use Zrny\MkSQL\Queries\Makers\QueryMakerMySQL;
use Zrny\MkSQL\Table;
use Zrny\MkSQL\Updater;

/**
 * Class QueryMakerMySQLTest
 * @package Queries\Makers
 */
class QueryMakerMySQLTest extends TestCase
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
            "SHOW CREATE TABLE tested_not_exist" => new PDOException("Table does not exists!"),
            "SHOW CREATE TABLE known_table" => [
                "Table" => 'known_table',
                "Create Table" =>  /** @lang */ 'CREATE TABLE `known_table` (
                      `id` int NOT NULL AUTO_INCREMENT,
                      `name` varchar(255) NOT NULL DEFAULT \'undefined\',
                      `price` decimal(13,2) DEFAULT NULL,
                      PRIMARY KEY (`id`)
                    ) ENGINE=DoIEvenCare'
            ],
            "SHOW CREATE TABLE sub_table" => [
                "Table" => 'known_table',
                "Create Table" => /** @lang */ 'CREATE TABLE `sub_table` (
                  `id` int NOT NULL AUTO_INCREMENT,
                  `parent` int NOT NULL COMMENT \'example comment\',
                  PRIMARY KEY (`id`),
                  KEY `sub_table_known_table_id_fk` (`parent`),
                  CONSTRAINT `sub_table_known_table_id_fk` FOREIGN KEY (`parent`) REFERENCES `known_table` (`id`)
                ) ENGINE=NoIReallyDontCare'
            ]
        ]);

        $updater = new Updater($MockPDO);

        $description = QueryMakerMySQL::describeTable($MockPDO, new Table("tested_not_exist"));

        $this->assertNotNull($description);
        $this->assertNotTrue($description->tableExists);

        $Table = $updater->tableCreate("known_table");

        $Table->columnCreate("name", "varchar(255)")->setNotNull()->setDefault("undefined");
        $Table->columnCreate("price", "decimal(13, 2)");

        $description = QueryMakerMySQL::describeTable($MockPDO, $Table);

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

        $description = QueryMakerMySQL::describeTable($MockPDO, $Table);


        $this->assertNotNull($description);

        $this->assertTrue($description->tableExists);

        $this->assertSame("int", $description->columnGet("parent")->type);

        $this->assertSame([
            'known_table.id' => 'sub_table_known_table_id_fk'
        ], $description->columnGet("parent")->foreignKeys);

        $this->assertSame(
            "example comment",
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
        $Queries = QueryMakerMySQL::changePrimaryKeyQuery(
            "id",
            $Desc->table,
            $Desc
        );

        $this->assertCount(
            1, $Queries
        );

        $this->assertStringContainsString(
            "ALTER TABLE",
            $Queries[0]->getQuery()
        );

        $this->assertStringContainsString(
            "int NOT NULL AUTO_INCREMENT",
            $Queries[0]->getQuery()
        );

        $this->assertStringContainsString(
            "CHANGE",
            $Queries[0]->getQuery()
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
        $Queries = QueryMakerMySQL::createTableQuery(
            $Desc->table,
            $Desc
        );

        //Create MySQL Table = 1 query
        $this->assertCount(
            1, $Queries
        );

        $this->assertStringContainsString(
            "CREATE TABLE",
            $Queries[0]->getQuery()
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
        $Queries = QueryMakerMySQL::alterTableColumnQuery(
            $Desc->table,
            $Desc->table->columnGet("name"),
            $Desc,
            $Desc->columnGet("name")
        );

        //Alter MySQL Column = 1 query
        $this->assertCount(
            1, $Queries
        );

        $this->assertStringContainsString(
            "ALTER TABLE",
            $Queries[0]->getQuery()
        );


    }

    /**
     * @throws ColumnDefinitionExists
     * @throws PrimaryKeyAutomaticException
     * @throws TableDefinitionExists
     */
    public function testCreateUniqueIndexQuery()
    {
        $Desc = MockSQLMaker_NotExistingTable_First::describeTable(new PDO(), new Table(""));
        $Queries = QueryMakerMySQL::createUniqueIndexQuery(
            $Desc->table,
            $Desc->table->columnGet("name"),
            $Desc,
            $Desc->columnGet("name")
        );

        //Alter MySQL Column = 1 query
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
        $Desc = MockSQLMaker_ExistingTable_Second::describeTable(new PDO(), new Table(""));

        $Queries = QueryMakerMySQL::removeUniqueIndexQuery(
            $Desc->table,
            $Desc->table->columnGet("parent"),
            "some_index_we_have_found",
            $Desc,
            $Desc->columnGet("parent")
        );

        //Alter MySQL Column = 1 query
        $this->assertCount(
            1, $Queries
        );

        $this->assertStringContainsString(
        /** @lang */ "DROP INDEX some_index_we_have_found ON",
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
        $Queries = QueryMakerMySQL::createTableColumnQuery(
            $Desc->table,
            $Desc->table->columnGet("name"),
            $Desc,
            $Desc->columnGet("name")
        );

        //Alter MySQL Column = 1 query
        $this->assertCount(
            1, $Queries
        );

        $this->assertStringContainsString(
        /** @lang */ "ALTER TABLE",
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
        $Queries = QueryMakerMySQL::createForeignKey(
            $Desc->table,
            $Desc->table->columnGet("name"),
            "table.column",
            $Desc,
            $Desc->columnGet("name")
        );

        //Alter MySQL Column = 1 query
        $this->assertCount(
            1, $Queries
        );

        $this->assertStringContainsString(
        /** @lang */ "ALTER TABLE",
            $Queries[0]->getQuery()
        );

        $this->assertStringContainsString(
        /** @lang */ "FOREIGN KEY",
            $Queries[0]->getQuery()
        );

        $this->assertStringContainsString(
        /** @lang */ "REFERENCES table (column)",
            $Queries[0]->getQuery()
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
        $Queries = QueryMakerMySQL::removeForeignKey(
            $Desc->table,
            $Desc->table->columnGet("name"),
            "some_key_we_found_before",
            $Desc,
            $Desc->columnGet("name")
        );

        //Alter MySQL Column = 1 query
        $this->assertCount(
            1, $Queries
        );

        $this->assertStringContainsString(
        /** @lang */ "ALTER TABLE",
            $Queries[0]->getQuery()
        );

        $this->assertStringContainsString(
        /** @lang */ "DROP FOREIGN KEY " . "some_key_we_found_before",
            $Queries[0]->getQuery()
        );
    }


    public function testCompareType()
    {
        $SameTypes = [
            "InTeGeR" => "integer",
            "Hello (1, 2, 3)" => "hello(1,2,3)",
            "tinyint(1)" => "tinyint",
            "int(3310)" => "int",
        ];

        foreach ($SameTypes as $t1 => $t2)
            $this->assertTrue(QueryMakerMySQL::compareType($t1, $t2));

        $NotSameTypes = [
            "int" => "string",
            "tinyint" => "mediumint",
            "text" => "varchar(255)"
        ];

        foreach ($NotSameTypes as $t1 => $t2)
            $this->assertNotTrue(QueryMakerMySQL::compareType($t1, $t2));
    }


    public function testCompareComment()
    {
        $this->assertTrue(QueryMakerMySQL::compareComment(null, null));
        $this->assertTrue(QueryMakerMySQL::compareComment("foo", "foo"));
        $this->assertTrue(QueryMakerMySQL::compareComment("bar", "bar"));
        $this->assertTrue(QueryMakerMySQL::compareComment("baz", "baz"));

        $this->assertNotTrue(QueryMakerMySQL::compareComment(null, "foo"));
        $this->assertNotTrue(QueryMakerMySQL::compareComment("foo", "bar"));
        $this->assertNotTrue(QueryMakerMySQL::compareComment("bar", "baz"));
        $this->assertNotTrue(QueryMakerMySQL::compareComment("baz", null));
    }

}

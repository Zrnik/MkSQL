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
use Zrnik\MkSQL\Exceptions\ColumnDefinitionExists;
use Zrnik\MkSQL\Exceptions\PrimaryKeyAutomaticException;
use Zrnik\MkSQL\Exceptions\TableDefinitionExists;
use Zrnik\MkSQL\Queries\Makers\QueryMakerMySQL;
use Zrnik\MkSQL\Table;
use Zrnik\MkSQL\Updater;

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
    public function testDescribeTable(): void
    {
        $MockPDO = new PDO();
        $MockPDO->mockResult([
            "SHOW CREATE TABLE tested_not_exist" => new PDOException("Table does not exists!"),
            "SHOW CREATE TABLE known_table" => [
                "Table" => 'known_table',
                "Create Table" => /** @lang */ 'CREATE TABLE `known_table` (
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

        $_column_get_name = $description->columnGet("name");
        $_column_get_price = $description->columnGet("price");

        $this->assertNotNull($_column_get_name);
        $this->assertNotNull($_column_get_price);


        $this->assertTrue($description->tableExists);

        $this->assertSame(
            "known_table",
            $description->table->getName()
        );

        $this->assertSame(
            "name",
            $_column_get_name->column->getName()
        );

        $this->assertSame(
            "varchar(255)",
            $_column_get_name->type
        );

        $this->assertSame(
            [],
            $_column_get_name->foreignKeys
        );

        $this->assertTrue($_column_get_name->columnExists);

        $this->assertSame(
            "undefined",
            $_column_get_name->default
        );

        $this->assertNull($_column_get_name->comment);

        $this->assertTrue($_column_get_name->notNull);

        $this->assertNull($_column_get_name->uniqueIndex);

        $this->assertSame(
            "price",
            $_column_get_price->column->getName()
        );

        $this->assertNull(
            $_column_get_price->default
        );


        $Table = $updater->tableCreate("sub_table");
        $Table->columnCreate("parent")->addForeignKey("known_table.id");

        $description = QueryMakerMySQL::describeTable($MockPDO, $Table);

        $this->assertNotNull($description);

        $column_get_parent = $description->columnGet("parent");

        $this->assertNotNull($column_get_parent);


        $this->assertTrue($description->tableExists);

        $this->assertSame("int", $column_get_parent->type);

        $this->assertSame([
            'known_table.id' => 'sub_table_known_table_id_fk'
        ], $column_get_parent->foreignKeys);

        $this->assertSame(
            "example comment",
            $column_get_parent->comment
        );
    }

    /**
     * @throws ColumnDefinitionExists
     * @throws PrimaryKeyAutomaticException
     * @throws TableDefinitionExists
     */
    public function testChangePrimaryKeyQuery(): void
    {
        $Desc = MockSQLMaker_NotExistingTable_First::describeTable(new PDO(), new Table(""));

        $this->assertNotNull($Desc);

        $Queries = QueryMakerMySQL::changePrimaryKeyQuery(
            "id",
            $Desc->table,
            $Desc
        );

        $this->assertNotNull($Queries);

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
    public function testCreateTableQuery(): void
    {
        $Desc = MockSQLMaker_NotExistingTable_First::describeTable(new PDO(), new Table(""));

        $this->assertNotNull($Desc);

        $Queries = QueryMakerMySQL::createTableQuery(
            $Desc->table,
            $Desc
        );

        $this->assertNotNull($Queries);

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
    public function testAlterTableColumnQuery(): void
    {
        $Desc = MockSQLMaker_NotExistingTable_First::describeTable(new PDO(), new Table(""));

        $this->assertNotNull($Desc);

        $table_column_get_name = $Desc->table->columnGet("name");

        $this->assertNotNull($table_column_get_name);

        $Queries = QueryMakerMySQL::alterTableColumnQuery(
            $Desc->table,
            $table_column_get_name,
            $Desc,
            $Desc->columnGet("name")
        );

        $this->assertNotNull($Queries);

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
    public function testCreateUniqueIndexQuery(): void
    {
        $Desc = MockSQLMaker_NotExistingTable_First::describeTable(new PDO(), new Table(""));

        $this->assertNotNull($Desc);

        $table_column_get_name = $Desc->table->columnGet("name");

        $this->assertNotNull($table_column_get_name);


        $Queries = QueryMakerMySQL::createUniqueIndexQuery(
            $Desc->table,
            $table_column_get_name,
            $Desc,
            $Desc->columnGet("name")
        );

        $this->assertNotNull($Queries);

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
    public function testRemoveUniqueIndexQuery(): void
    {
        $Desc = MockSQLMaker_ExistingTable_Second::describeTable(new PDO(), new Table(""));

        $this->assertNotNull($Desc);

        $table_column_get_parent = $Desc->table->columnGet("parent");

        $this->assertNotNull($table_column_get_parent);

        $Queries = QueryMakerMySQL::removeUniqueIndexQuery(
            $Desc->table,
            $table_column_get_parent,
            "some_index_we_have_found",
            $Desc,
            $Desc->columnGet("parent")
        );

        $this->assertNotNull($Queries);

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
    public function testCreateTableColumnQuery(): void
    {
        $Desc = MockSQLMaker_NotExistingTable_First::describeTable(new PDO(), new Table(""));

        $this->assertNotNull($Desc);

        $table_column_get_name = $Desc->table->columnGet("name");

        $this->assertNotNull($table_column_get_name);

        $Queries = QueryMakerMySQL::createTableColumnQuery(
            $Desc->table,
            $table_column_get_name,
            $Desc,
            $Desc->columnGet("name")
        );

        $this->assertNotNull($Queries);

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
    public function testCreateForeignKey(): void
    {
        $Desc = MockSQLMaker_NotExistingTable_First::describeTable(new PDO(), new Table(""));

        $this->assertNotNull($Desc);

        $table_column_get_name = $Desc->table->columnGet("name");

        $this->assertNotNull($table_column_get_name);

        $Queries = QueryMakerMySQL::createForeignKey(
            $Desc->table,
            $table_column_get_name,
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
    public function testRemoveForeignKey(): void
    {
        $Desc = MockSQLMaker_NotExistingTable_First::describeTable(new PDO(), new Table(""));

        $this->assertNotNull($Desc);

        $table_column_get_name = $Desc->table->columnGet("name");

        $this->assertNotNull($table_column_get_name);

        $Queries = QueryMakerMySQL::removeForeignKey(
            $Desc->table,
            $table_column_get_name,
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


    public function testCompareType(): void
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


    public function testCompareComment(): void
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

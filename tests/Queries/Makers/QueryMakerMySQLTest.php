<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Tests\Queries\Makers;

use Tests\Mock\MockSQLMaker_ExistingTable_Second;
use Tests\Mock\MockSQLMaker_NotExistingTable_First;
use Tests\Mock\PDO;
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
            'SHOW CREATE TABLE tested_not_exist' => new PDOException('Table does not exists!'),
            'SHOW CREATE TABLE known_table' => [
                'Table' => 'known_table',
                'Create Table' => /** @lang */ 'CREATE TABLE `known_table` (
                      `id` int NOT NULL AUTO_INCREMENT,
                      `name` varchar(255) NOT NULL DEFAULT \'undefined\',
                      `price` decimal(13,2) DEFAULT NULL,
                      PRIMARY KEY (`id`)
                    ) ENGINE=DoIEvenCare'
            ],
            'SHOW CREATE TABLE sub_table' => [
                'Table' => 'known_table',
                'Create Table' => /** @lang */ 'CREATE TABLE `sub_table` (
                  `id` int NOT NULL AUTO_INCREMENT,
                  `parent` int NOT NULL COMMENT \'example comment\',
                  PRIMARY KEY (`id`),
                  KEY `sub_table_known_table_id_fk` (`parent`),
                  CONSTRAINT `sub_table_known_table_id_fk` FOREIGN KEY (`parent`) REFERENCES `known_table` (`id`)
                ) ENGINE=NoIReallyDontCare'
            ]
        ]);

        $updater = new Updater($MockPDO);

        $description = QueryMakerMySQL::describeTable($MockPDO, new Table('tested_not_exist'));

        static::assertNotNull($description);
        static::assertNotTrue($description->tableExists);

        $Table = $updater->tableCreate('known_table');

        $Table->columnCreate('name', 'varchar(255)')->setNotNull()->setDefault('undefined');
        $Table->columnCreate('price', 'decimal(13, 2)');

        $description = QueryMakerMySQL::describeTable($MockPDO, $Table);

        static::assertNotNull($description);

        $_column_get_name = $description->columnGet('name');
        $_column_get_price = $description->columnGet('price');

        static::assertNotNull($_column_get_name);
        static::assertNotNull($_column_get_price);


        static::assertTrue($description->tableExists);

        static::assertSame(
            'known_table',
            $description->table->getName()
        );

        static::assertSame(
            'name',
            $_column_get_name->column->getName()
        );

        static::assertSame(
            'varchar(255)',
            $_column_get_name->type
        );

        static::assertSame(
            [],
            $_column_get_name->foreignKeys
        );

        static::assertTrue($_column_get_name->columnExists);

        static::assertSame(
            'undefined',
            $_column_get_name->default
        );

        static::assertNull($_column_get_name->comment);

        static::assertTrue($_column_get_name->notNull);

        static::assertNull($_column_get_name->uniqueIndex);

        static::assertSame(
            'price',
            $_column_get_price->column->getName()
        );

        static::assertNull(
            $_column_get_price->default
        );


        $Table = $updater->tableCreate('sub_table');
        $Table->columnCreate('parent')->addForeignKey('known_table.id');

        $description = QueryMakerMySQL::describeTable($MockPDO, $Table);

        static::assertNotNull($description);

        $column_get_parent = $description->columnGet('parent');

        static::assertNotNull($column_get_parent);


        static::assertTrue($description->tableExists);

        static::assertSame('int', $column_get_parent->type);

        static::assertSame([
            'known_table.id' => 'sub_table_known_table_id_fk'
        ], $column_get_parent->foreignKeys);

        static::assertSame(
            'example comment',
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
        $Desc = MockSQLMaker_NotExistingTable_First::describeTable(new PDO(), new Table(''));

        static::assertNotNull($Desc);

        $Queries = QueryMakerMySQL::changePrimaryKeyQuery(
            'id',
            $Desc->table,
            $Desc
        );

        static::assertNotNull($Queries);

        static::assertCount(
            1, $Queries
        );

        static::assertStringContainsString(
            'ALTER TABLE',
            $Queries[0]->getQuery()
        );

        static::assertStringContainsString(
            'int NOT NULL AUTO_INCREMENT',
            $Queries[0]->getQuery()
        );

        static::assertStringContainsString(
            'CHANGE',
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
        $Desc = MockSQLMaker_NotExistingTable_First::describeTable(new PDO(), new Table(''));

        static::assertNotNull($Desc);

        $Queries = QueryMakerMySQL::createTableQuery(
            $Desc->table,
            $Desc
        );

        static::assertNotNull($Queries);

        //Create MySQL Table = 1 query
        static::assertCount(
            1, $Queries
        );

        static::assertStringContainsString(
            'CREATE TABLE',
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
        $Desc = MockSQLMaker_NotExistingTable_First::describeTable(new PDO(), new Table(''));

        static::assertNotNull($Desc);

        $table_column_get_name = $Desc->table->columnGet('name');

        static::assertNotNull($table_column_get_name);

        $Queries = QueryMakerMySQL::alterTableColumnQuery(
            $Desc->table,
            $table_column_get_name,
            $Desc,
            $Desc->columnGet('name')
        );

        static::assertNotNull($Queries);

        //Alter MySQL Column = 1 query
        static::assertCount(
            1, $Queries
        );

        static::assertStringContainsString(
            'ALTER TABLE',
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
        $Desc = MockSQLMaker_NotExistingTable_First::describeTable(new PDO(), new Table(''));

        static::assertNotNull($Desc);

        $table_column_get_name = $Desc->table->columnGet('name');

        static::assertNotNull($table_column_get_name);


        $Queries = QueryMakerMySQL::createUniqueIndexQuery(
            $Desc->table,
            $table_column_get_name,
            $Desc,
            $Desc->columnGet('name')
        );

        static::assertNotNull($Queries);

        //Alter MySQL Column = 1 query
        static::assertCount(
            1, $Queries
        );

        static::assertStringContainsString(
            'CREATE UNIQUE INDEX',
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
        $Desc = MockSQLMaker_ExistingTable_Second::describeTable(new PDO(), new Table(''));

        static::assertNotNull($Desc);

        $table_column_get_parent = $Desc->table->columnGet('parent');

        static::assertNotNull($table_column_get_parent);

        $Queries = QueryMakerMySQL::removeUniqueIndexQuery(
            $Desc->table,
            $table_column_get_parent,
            'some_index_we_have_found',
            $Desc,
            $Desc->columnGet('parent')
        );

        static::assertNotNull($Queries);

        //Alter MySQL Column = 1 query
        static::assertCount(
            1, $Queries
        );

        static::assertStringContainsString(
        /** @lang */ 'DROP INDEX some_index_we_have_found ON',
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
        $Desc = MockSQLMaker_NotExistingTable_First::describeTable(new PDO(), new Table(''));

        static::assertNotNull($Desc);

        $table_column_get_name = $Desc->table->columnGet('name');

        static::assertNotNull($table_column_get_name);

        $Queries = QueryMakerMySQL::createTableColumnQuery(
            $Desc->table,
            $table_column_get_name,
            $Desc,
            $Desc->columnGet('name')
        );

        static::assertNotNull($Queries);

        //Alter MySQL Column = 1 query
        static::assertCount(
            1, $Queries
        );

        static::assertStringContainsString(
        /** @lang */ 'ALTER TABLE',
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
        $Desc = MockSQLMaker_NotExistingTable_First::describeTable(new PDO(), new Table(''));

        static::assertNotNull($Desc);

        $table_column_get_name = $Desc->table->columnGet('name');

        static::assertNotNull($table_column_get_name);

        $Queries = QueryMakerMySQL::createForeignKey(
            $Desc->table,
            $table_column_get_name,
            'table.column',
            $Desc,
            $Desc->columnGet('name')
        );

        //Alter MySQL Column = 1 query
        static::assertCount(
            1, $Queries
        );

        static::assertStringContainsString(
        /** @lang */ 'ALTER TABLE',
            $Queries[0]->getQuery()
        );

        static::assertStringContainsString(
        /** @lang */ 'FOREIGN KEY',
            $Queries[0]->getQuery()
        );

        static::assertStringContainsString(
        /** @lang */ 'REFERENCES table (column)',
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
        $Desc = MockSQLMaker_NotExistingTable_First::describeTable(new PDO(), new Table(''));

        static::assertNotNull($Desc);

        $table_column_get_name = $Desc->table->columnGet('name');

        static::assertNotNull($table_column_get_name);

        $Queries = QueryMakerMySQL::removeForeignKey(
            $Desc->table,
            $table_column_get_name,
            'some_key_we_found_before',
            $Desc,
            $Desc->columnGet('name')
        );

        //Alter MySQL Column = 1 query
        static::assertCount(
            1, $Queries
        );

        static::assertStringContainsString(
        /** @lang */ 'ALTER TABLE',
            $Queries[0]->getQuery()
        );

        static::assertStringContainsString(
        /** @lang */ 'DROP FOREIGN KEY ' . 'some_key_we_found_before',
            $Queries[0]->getQuery()
        );
    }


    public function testCompareType(): void
    {
        $SameTypes = [
            'InTeGeR' => 'integer',
            'Hello (1, 2, 3)' => 'hello(1,2,3)',
            'tinyint(1)' => 'tinyint',
            'int(3310)' => 'int',
        ];

        foreach ($SameTypes as $t1 => $t2) {
            static::assertTrue(QueryMakerMySQL::compareType($t1, $t2));
        }

        $NotSameTypes = [
            'int' => 'string',
            'tinyint' => 'mediumint',
            'text' => 'varchar(255)'
        ];

        foreach ($NotSameTypes as $t1 => $t2) {
            static::assertNotTrue(QueryMakerMySQL::compareType($t1, $t2));
        }
    }


    public function testCompareComment(): void
    {
        static::assertTrue(QueryMakerMySQL::compareComment(null, null));
        static::assertTrue(QueryMakerMySQL::compareComment('foo', 'foo'));
        static::assertTrue(QueryMakerMySQL::compareComment('bar', 'bar'));
        static::assertTrue(QueryMakerMySQL::compareComment('baz', 'baz'));

        static::assertNotTrue(QueryMakerMySQL::compareComment(null, 'foo'));
        static::assertNotTrue(QueryMakerMySQL::compareComment('foo', 'bar'));
        static::assertNotTrue(QueryMakerMySQL::compareComment('bar', 'baz'));
        static::assertNotTrue(QueryMakerMySQL::compareComment('baz', null));
    }

}

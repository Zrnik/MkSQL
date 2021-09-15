<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
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
use function count;

class QueryMakerSQLiteTest extends TestCase
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
            /** @lang text */ "SELECT * FROM sqlite_master WHERE tbl_name = 'tested_not_exist'" => [],
            /** @lang text */ "SELECT * FROM sqlite_master WHERE tbl_name = 'known_table'" => [
                [
                    'type' => 'table',
                    'name' => 'known_table',
                    'tbl_name' => 'known_table',
                    'root' . 'page' => 2,
                    'sql' => /** @lang SQLite */ "CREATE TABLE known_table
                    (
                        id integer not null
                            constraint known_table_pk
                                primary key autoincrement
                    , name varchar(255) default 'undefined' not null, price decimal(13,2))"
                ]
            ],
            /** @lang text */ "SELECT * FROM sqlite_master WHERE tbl_name = 'sub_table'" => [
                [
                    'type' => 'table',
                    'name' => 'sub_table',
                    'tbl_name' => 'sub_table',
                    'root' . 'page' => 6,
                    'sql' => /** @lang */ 'CREATE TABLE "sub_table"
                        (
                            id integer not null
                                constraint sub_table_pk
                                    primary key autoincrement,
                            parent int
                                constraint sub_table_known_table_id_fk
                                    references known_table
                        )'
                ]
            ],
        ]);

        $updater = new Updater($MockPDO);

        $description = QueryMakerSQLite::describeTable($MockPDO, new Table('tested_not_exist'));

        static::assertNotNull($description);

        static::assertNotTrue($description->tableExists);

        $Table = $updater->tableCreate('known_table');

        $Table->columnCreate('name', 'varchar(255)')->setNotNull()->setDefault('undefined');
        $Table->columnCreate('price', 'decimal(13, 2)');

        $description = QueryMakerSQLite::describeTable($MockPDO, $Table);

        static::assertNotNull($description);

        static::assertTrue($description->tableExists);

        static::assertSame(
            'known_table',
            $description->table->getName()
        );

        $column_Name = $description->columnGet('name');

        static::assertNotNull($column_Name);

        static::assertSame(
            'name',
            $column_Name->column->getName()
        );

        static::assertSame(
            'varchar(255)',
            $column_Name->type
        );

        static::assertSame(
            [],
            $column_Name->foreignKeys
        );

        static::assertTrue($column_Name->columnExists);

        static::assertSame(
            'undefined',
            $column_Name->default
        );

        static::assertNull($column_Name->comment);

        static::assertTrue($column_Name->notNull);

        static::assertNull($column_Name->uniqueIndex);

        $column_get_price = $description->columnGet('price');

        static::assertNotNull($column_get_price);

        static::assertSame(
            'price',
            $column_get_price->column->getName()
        );

        static::assertNull(
            $column_get_price->default
        );


        $Table = $updater->tableCreate('sub_table');
        $Table->columnCreate('parent')->addForeignKey('known_table.id');

        $description = QueryMakerSQLite::describeTable($MockPDO, $Table);

        static::assertNotNull($description);

        $column_get_parent = $description->columnGet('parent');

        static::assertNotNull($column_get_parent);

        static::assertTrue($description->tableExists);

        static::assertSame('int', $column_get_parent->type);

        static::assertSame([
            'known_table.id' => 'sub_table_known_table_id_fk'
        ], $column_get_parent->foreignKeys);

        //SQLite does not support comments!
        static::assertNull(
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

        $Queries = QueryMakerSQLite::changePrimaryKeyQuery(
            'id',
            $Desc->table,
            $Desc
        );

        // Same like altering a column, requires a temp table...
        static::assertGreaterThanOrEqual(
            4, $Queries
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

        $Queries = QueryMakerSQLite::createTableQuery($Desc->table, $Desc);

        static::assertNotNull($Queries);

        //Create SQLite Table = 1 query
        static::assertCount(
            1, $Queries
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

        $desc_table_column_name = $Desc->table->columnGet('name');

        static::assertNotNull($desc_table_column_name);

        $Queries = QueryMakerSQLite::alterTableColumnQuery(
            $Desc->table,
            $desc_table_column_name,
            $Desc,
            $Desc->columnGet('name')
        );

        static::assertNotNull($Queries);

        // Changing a table in SQLite requires creating of temporary table!
        // 1. Create Temporary Table (+1)
        // 2. Add columns to temporary table (+0 as there can be none)
        // 3. Move Data (+1)
        // 3. Drop Old Table (+1)
        // 3. Rename Temporary Table (+1)
        // 2. Crate unique indexes (+0 as there can be none)
        //
        // = Minimum of 4!
        static::assertGreaterThanOrEqual(
            4, $Queries
        );

        // Create Temporary Table
        static::assertStringContainsString(
            'CREATE TABLE',
            $Queries[0]->getQuery()
        );

        $index = count($Queries) - 1;
        while (str_contains($Queries[$index]->getQuery(), 'CREATE UNIQUE INDEX')) {
            $index--;
        }

        //Now we go backwards:

        //RENAME TEMPORARY TO ORIGINAL
        static::assertStringContainsString(
            'ALTER TABLE',
            $Queries[$index]->getQuery()
        );

        static::assertStringContainsString(
            'RENAME TO',
            $Queries[$index]->getQuery()
        );

        //DROP OLD TABLE
        static::assertStringContainsString(
            'DROP TABLE',
            $Queries[$index - 1]->getQuery()
        );

        //INSERT FROM OLD TABLE TO TEMP TABLE
        static::assertStringContainsString(
            'INSERT INTO',
            $Queries[$index - 2]->getQuery()
        );
        static::assertStringContainsString(
            'SELECT',
            $Queries[$index - 2]->getQuery()
        );
        static::assertStringContainsString(
            'FROM',
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
    public function testCreateUniqueIndexQuery(): void
    {
        $Desc = MockSQLMaker_NotExistingTable_First::describeTable(new PDO(), new Table(''));

        static::assertNotNull($Desc);

        $desc_table_column_name = $Desc->table->columnGet('name');

        static::assertNotNull($desc_table_column_name);


        $Queries = QueryMakerSQLite::createUniqueIndexQuery(
            $Desc->table,
            $desc_table_column_name,
            $Desc,
            $Desc->columnGet('name')
        );

        //Create SQLite Unique Index = 1 query
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
        $Desc = MockSQLMaker_NotExistingTable_First::describeTable(new PDO(), new Table(''));

        static::assertNotNull($Desc);

        $desc_table_column_name = $Desc->table->columnGet('name');

        static::assertNotNull($desc_table_column_name);

        $Queries = QueryMakerSQLite::removeUniqueIndexQuery(
            $Desc->table,
            $desc_table_column_name,
            'some_index_we_have_found',
            $Desc,
            $Desc->columnGet('name')
        );

        static::assertNotNull($Queries);

        //Remove SQLite Unique Index = 1 query
        static::assertCount(
            1, $Queries
        );

        static::assertArrayHasKey(
            0, $Queries
        );

        static::assertStringContainsString(
        /** @lang text */ 'DROP INDEX some_index_we_have_found',
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

        $desc_table_column_name = $Desc->table->columnGet('name');

        static::assertNotNull($desc_table_column_name);

        $Queries = QueryMakerSQLite::createTableColumnQuery(
            $Desc->table,
            $desc_table_column_name,
            $Desc,
            $Desc->columnGet('name')
        );

        //Create SQLite Column = 1 query
        static::assertCount(
            1, $Queries
        );

        static::assertStringContainsString(
        /** @lang text */ 'ALTER TABLE',
            $Queries[0]->getQuery()
        );

        static::assertStringContainsString(
        /** @lang text */ 'ADD',
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

        $desc_table_column_name = $Desc->table->columnGet('name');

        static::assertNotNull($desc_table_column_name);

        $Queries = QueryMakerSQLite::createForeignKey(
            $Desc->table,
            $desc_table_column_name,
            'table.column',
            $Desc,
            $Desc->columnGet('name')
        );

        // Same like altering a column, requires a temp table...
        static::assertGreaterThanOrEqual(
            4, $Queries
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

        $column_Name = $Desc->table->columnGet('name');

        static::assertNotNull($column_Name);

        $Queries = QueryMakerSQLite::removeForeignKey(
            $Desc->table,
            $column_Name,
            'some_key_we_have_found_before',
            $Desc,
            $Desc->columnGet('name')
        );

        // Again, rewriting whole table. good luck XD
        static::assertGreaterThanOrEqual(
            4, $Queries
        );
    }


    public function testCompareType(): void
    {
        $SameTypes = [
            'InTeGeR' => 'integer',
            'int' => 'integer',
            'Hello (1, 2, 3)' => 'hello(1,2,3)',
            'integer' => 'int',
            'tinyint(1)' => 'tinyint',
            'int(3310)' => 'integer',
            'int(3310) ' => 'int',
        ];

        foreach ($SameTypes as $t1 => $t2) {
            static::assertTrue(QueryMakerSQLite::compareType($t1, $t2));
        }

        $NotSameTypes = [
            'int' => 'string',
            'tinyint' => 'mediumint',
            'text' => 'varchar(255)'
        ];

        foreach ($NotSameTypes as $t1 => $t2) {
            static::assertNotTrue(QueryMakerSQLite::compareType($t1, $t2));
        }
    }

    public function testCompareComment(): void
    {
        $LiterallyAnything = [
            'yep' => 'yep',
            'hello' => 'world',
            ':)' => ':(',
            'string_or_null' => null,
            null => 'string_or_null'
        ];

        //As sqlite does not support comments, it always returns true, so it does not trigger an update everytime.
        foreach ($LiterallyAnything as $c1 => $c2) {
            static::assertTrue(QueryMakerSQLite::compareComment($c1, $c2));
        }
    }

}

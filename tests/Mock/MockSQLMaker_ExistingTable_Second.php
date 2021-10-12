<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Tests\Mock;

use PDO;
use Zrnik\MkSQL\Column;
use Zrnik\MkSQL\Exceptions\ColumnDefinitionExists;
use Zrnik\MkSQL\Exceptions\PrimaryKeyAutomaticException;
use Zrnik\MkSQL\Exceptions\TableDefinitionExists;
use Zrnik\MkSQL\Queries\Makers\IQueryMaker;
use Zrnik\MkSQL\Queries\Tables\ColumnDescription;
use Zrnik\MkSQL\Queries\Tables\TableDescription;
use Zrnik\MkSQL\Table;
use Zrnik\MkSQL\Updater;

class MockSQLMaker_ExistingTable_Second implements IQueryMaker
{

    /**
     * @param PDO $pdo
     * @param Table $table
     * @return TableDescription|null
     * @throws ColumnDefinitionExists
     * @throws PrimaryKeyAutomaticException
     * @throws TableDefinitionExists
     */
    public static function describeTable(PDO $pdo, Table $table): ?TableDescription
    {
        //New Desc
        $Description = new TableDescription();
        $Description->queryMakerClass = static::class;

        //Existing!
        $Description->tableExists = true;

        // Create Definition
        $updater = new Updater($pdo);
        //Referenced Table
        $updater->tableCreate('existing_1');

        /** @noinspection UselessUnsetInspection */
        unset($table);

        $table = $updater->tableCreate('existing_2');
        $colParent = $table->columnCreate('parent')->setUnique()->setNotNull()->addForeignKey('existing_1.id');
        $colCreate = $table->columnCreate('create_time');
        $Description->table = $table;

        // Add Columns to Definition
        $Column_Parent = new ColumnDescription();
        $Column_Parent->table = $table;
        $Column_Parent->column = $colParent;
        $Column_Parent->type = $Column_Parent->column->getType();

        $Column_CreateTime = new ColumnDescription();
        $Column_CreateTime->table = $table;
        $Column_CreateTime->column = $colCreate;
        $Column_CreateTime->type = $Column_CreateTime->column->getType();

        $Description->columns[] = $Column_Parent;
        $Description->columns[] = $Column_CreateTime;

        return $Description;
    }

    /**
     * @inheritDoc
     */
    public static function changePrimaryKeyQuery(string $oldKey, Table $table, ?TableDescription $oldTableDescription): ?array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public static function createTableQuery(Table $table, ?TableDescription $oldTableDescription): ?array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public static function alterTableColumnQuery(Table $table, Column $column, ?TableDescription $oldTableDescription, ColumnDescription $columnDescription): ?array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public static function createTableColumnQuery(Table $table, Column $column, ?TableDescription $oldTableDescription, ?ColumnDescription $columnDescription): ?array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public static function createUniqueIndexQuery(Table $table, Column $column, ?TableDescription $oldTableDescription, ?ColumnDescription $columnDescription): ?array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public static function removeUniqueIndexQuery(Table $table, Column $column, string $uniqueIndex, ?TableDescription $oldTableDescription, ?ColumnDescription $columnDescription): ?array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public static function createForeignKey(Table $table, Column $column, string $RefPointerString, ?TableDescription $oldTableDescription, ?ColumnDescription $columnDescription): ?array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public static function removeForeignKey(Table $table, Column $column, string $ForeignKeyName, ?TableDescription $oldTableDescription, ?ColumnDescription $columnDescription): ?array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public static function compareType(string $type1, string $type2): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public static function compareComment(?string $comment1, ?string $comment2): bool
    {
        return true;
    }
}

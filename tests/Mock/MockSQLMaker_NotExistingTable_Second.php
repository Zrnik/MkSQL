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

class MockSQLMaker_NotExistingTable_Second implements IQueryMaker
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
        $Description->tableExists = false;

        // Create Definition
        $updater = new Updater($pdo);
        /** @noinspection UselessUnsetInspection */
        unset($table);
        $table = $updater->tableCreate('not_existing_2');
        $table->columnCreate('parent')->setUnique()->setNotNull()->addForeignKey('not_existing_1.id');
        $table->columnCreate('create_time');
        $Description->table = $table;

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
    public static function compareComment(
        float|bool|int|string|null $comment1,
        float|bool|int|string|null $comment2
    ): bool
    {
        return true;
    }
}

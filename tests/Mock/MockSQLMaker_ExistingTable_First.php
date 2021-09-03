<?php declare(strict_types=1);
/*
 * Zrník.eu | MkSQL
 * User: Programátor
 * Date: 31.08.2020 16:56
 */


namespace Mock;


use PDO;
use Zrnik\MkSQL\Column;
use Zrnik\MkSQL\Exceptions\ColumnDefinitionExists;
use Zrnik\MkSQL\Exceptions\InvalidArgumentException;
use Zrnik\MkSQL\Exceptions\PrimaryKeyAutomaticException;
use Zrnik\MkSQL\Exceptions\TableDefinitionExists;
use Zrnik\MkSQL\Queries\Makers\IQueryMaker;
use Zrnik\MkSQL\Queries\Tables\ColumnDescription;
use Zrnik\MkSQL\Queries\Tables\TableDescription;
use Zrnik\MkSQL\Table;
use Zrnik\MkSQL\Updater;

class MockSQLMaker_ExistingTable_First implements IQueryMaker
{

    /**
     * @param PDO $pdo
     * @param Table $table
     * @return TableDescription|null
     * @throws ColumnDefinitionExists
     * @throws PrimaryKeyAutomaticException
     * @throws TableDefinitionExists
     * @throws InvalidArgumentException
     */
    public static function describeTable(PDO $pdo, Table $table): ?TableDescription
    {
        $table->getHashKey();

        //New Desc
        $Description = new TableDescription();
        $Description->queryMakerClass = static::class;

        //Existing!
        $Description->tableExists = true;

        // Create Definition
        $updater = new Updater($pdo);
        $table = $updater->tableCreate("existing_1");
        $table->columnCreate("name", "varchar(255)")->setUnique()->setNotNull();
        $table->columnCreate("desc", "text");
        $Description->table = $table;

        // Add Columns to Definition
        $Column_Name = new ColumnDescription();
        $Column_Name->table = $table;
        $Column_Name->column = $table->columnGet("name") ?? new Column("name", "varchar(255)");
        $Column_Name->type = $Column_Name->column->getType();

        $Column_Desc = new ColumnDescription();
        $Column_Desc->table = $table;
        $Column_Desc->column = $table->columnGet("desc") ?? new Column("desc", "text");
        $Column_Desc->type = $Column_Desc->column->getType();

        $Description->columns[] = $Column_Name;
        $Description->columns[] = $Column_Desc;

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

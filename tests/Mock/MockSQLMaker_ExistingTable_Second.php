<?php declare(strict_types=1);
/*
 * Zrník.eu | MkSQL  
 * User: Programátor
 * Date: 31.08.2020 16:56
 */


namespace Mock;


use PDO;
use Zrny\MkSQL\Column;
use Zrny\MkSQL\Queries\Makers\IQueryMaker;
use Zrny\MkSQL\Queries\Query;
use Zrny\MkSQL\Queries\Tables\ColumnDescription;
use Zrny\MkSQL\Queries\Tables\TableDescription;
use Zrny\MkSQL\Table;

class MockSQLMaker_ExistingTable_Second implements IQueryMaker
{

    /**
     * @inheritDoc
     */
    public static function describeTable(PDO $pdo, Table $table): ?TableDescription
    {
        //New Desc
        $Description = new TableDescription();
        $Description->queryMakerClass = static::class;

        //Existing!
        $Description->tableExists = true;
 
        // Create Definition
        $table = new Table("existing_2");
        $table->columnCreate("parent")->setUnique()->setNotNull()->addForeignKey("existing_1.id");
        $table->columnCreate("create_time");
        $Description->table = $table;

        // Add Columns to Definition
        $Column_Parent = new ColumnDescription();
        $Column_Parent->table = $table;
        $Column_Parent->column = $table->columnGet("parent") ?? new Column("parent");
        $Column_Parent->type =  $Column_Parent->column->getType();

        $Column_CreateTime = new ColumnDescription();
        $Column_CreateTime->table = $table;
        $Column_CreateTime->column = $table->columnGet("create_time") ?? new Column("create_time");
        $Column_CreateTime->type =  $Column_CreateTime->column->getType();

        $Description->columns[] = $Column_Parent;
        $Description->columns[] = $Column_CreateTime;

        return $Description;
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
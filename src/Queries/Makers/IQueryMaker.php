<?php
/*
 * Zrník.eu | MkSQL
 * User: Programátor
 * Date: 06.08.2020 7:44
 */


namespace Zrnik\MkSQL\Queries\Makers;

use PDO;
use Zrnik\MkSQL\Column;
use Zrnik\MkSQL\Queries\Query;
use Zrnik\MkSQL\Queries\Tables\ColumnDescription;
use Zrnik\MkSQL\Queries\Tables\TableDescription;
use Zrnik\MkSQL\Table;

interface IQueryMaker
{
    //region Table Information
    /**
     * Creates a TableDescription from PDO and table name.
     *
     * @param PDO $pdo
     * @param Table $table
     * @return TableDescription|null
     */
    public static function describeTable(PDO $pdo, Table $table): ?TableDescription;

    //endregion

    //region Table Operations
    /**
     * @param Table $table
     * @param TableDescription|null $oldTableDescription
     * @return Query[]|null
     */
    public static function createTableQuery(Table $table, ?TableDescription $oldTableDescription): ?array;


    /**
     * @param string $oldKey
     * @param Table $table
     * @param TableDescription|null $oldTableDescription
     * @return Query[]|null
     */
    public static function changePrimaryKeyQuery(
        string $oldKey,
        Table $table, ?TableDescription $oldTableDescription
    ): ?array;

    //endregion

    //region Column Operations

    /**
     * @param Table $table
     * @param Column $column
     * @param TableDescription|null $oldTableDescription
     * @param ColumnDescription|null $columnDescription
     * @return Query[]|null
     */
    public static function alterTableColumnQuery(Table $table, Column $column, ?TableDescription $oldTableDescription, ColumnDescription $columnDescription): ?array;

    /**
     * @param Table $table
     * @param Column $column
     * @param TableDescription|null $oldTableDescription
     * @param ColumnDescription|null $columnDescription
     * @return Query[]|null
     */
    public static function createTableColumnQuery(Table $table, Column $column, ?TableDescription $oldTableDescription, ?ColumnDescription $columnDescription): ?array;

    /**
     * @param Table $table
     * @param Column $column
     * @param TableDescription|null $oldTableDescription
     * @param ColumnDescription|null $columnDescription
     * @return Query[]|null
     */
    public static function createUniqueIndexQuery(Table $table, Column $column, ?TableDescription $oldTableDescription, ?ColumnDescription $columnDescription): ?array;

    /**
     * @param Table $table
     * @param Column $column
     * @param string $uniqueIndex
     * @param TableDescription|null $oldTableDescription
     * @param ColumnDescription|null $columnDescription
     * @return Query[]|null
     */
    public static function removeUniqueIndexQuery(Table $table, Column $column, string $uniqueIndex, ?TableDescription $oldTableDescription, ?ColumnDescription $columnDescription): ?array;

    /**
     * @param Table $table
     * @param Column $column
     * @param string $RefPointerString
     * @param TableDescription|null $oldTableDescription
     * @param ColumnDescription|null $columnDescription
     * @return Query[]|null
     */
    public static function createForeignKey(Table $table, Column $column, string $RefPointerString, ?TableDescription $oldTableDescription, ?ColumnDescription $columnDescription): ?array;

    /**
     * @param Table $table
     * @param Column $column
     * @param string $ForeignKeyName
     * @param TableDescription|null $oldTableDescription
     * @param ColumnDescription|null $columnDescription
     * @return Query[]|null
     */
    public static function removeForeignKey(Table $table, Column $column, string $ForeignKeyName, ?TableDescription $oldTableDescription, ?ColumnDescription $columnDescription): ?array;

    //endregion

    //region Comparing

    /**
     * @param string $type1
     * @param string $type2
     * @return bool
     */
    public static function compareType(string $type1, string $type2): bool;

    /**
     * @param string|null $comment1
     * @param string|null $comment2
     * @return bool
     */
    public static function compareComment(?string $comment1, ?string $comment2): bool;

    //endregion
}

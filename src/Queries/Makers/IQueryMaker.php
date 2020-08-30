<?php
/*
 * Zrník.eu | MkSQL
 * User: Programátor
 * Date: 06.08.2020 7:44
 */


namespace Zrny\MkSQL\Queries\Makers;

use Nette\Database\Connection;
use Zrny\MkSQL\Column;
use Zrny\MkSQL\Queries\Query;
use Zrny\MkSQL\Queries\Tables\ColumnDescription;
use Zrny\MkSQL\Queries\Tables\TableDescription;
use Zrny\MkSQL\Table;

interface IQueryMaker
{
    // Table Information

    public static function describeTable(\PDO $pdo, Table $table) : ?TableDescription;

    // Table Operations

    /**
     * @param Table $table
     * @param TableDescription|null $oldTableDescription
     * @return Query[]|null
     */
    public static function createTableQuery(Table $table, ?TableDescription $oldTableDescription) : ?array;

    //ColumnOperations

    /**
     * @param Table $table
     * @param Column $column
     * @param TableDescription|null $oldTableDescription
     * @param ColumnDescription|null $columnDescription
     * @return Query[]|null
     */
    public static function alterTableColumnQuery(Table $table, Column $column, ?TableDescription $oldTableDescription, ColumnDescription $columnDescription) : ?array;

    /**
     * @param Table $table
     * @param Column $column
     * @param TableDescription|null $oldTableDescription
     * @param ColumnDescription|null $columnDescription
     * @return Query[]|null
     */
    public static function createTableColumnQuery(Table $table, Column $column, ?TableDescription $oldTableDescription, ?ColumnDescription $columnDescription) : ?array;

    /**
     * @param Table $table
     * @param Column $column
     * @param TableDescription|null $oldTableDescription
     * @param ColumnDescription|null $columnDescription
     * @return Query[]|null
     */
    public static function createUniqueIndexQuery(Table $table, Column $column, ?TableDescription $oldTableDescription, ?ColumnDescription $columnDescription) : ?array;

    /**
     * @param Table $table
     * @param Column $column
     * @param string $uniqueIndex
     * @param TableDescription|null $oldTableDescription
     * @param ColumnDescription|null $columnDescription
     * @return Query[]|null
     */
    public static function removeUniqueIndexQuery(Table $table, Column $column, string $uniqueIndex, ?TableDescription $oldTableDescription, ?ColumnDescription $columnDescription) : ?array;

    /**
     * @param Table $table
     * @param Column $column
     * @param string $RefPointerString
     * @param TableDescription|null $oldTableDescription
     * @param ColumnDescription|null $columnDescription
     * @return Query[]|null
     */
    public static function createForeignKey(Table $table, Column $column, string $RefPointerString, ?TableDescription $oldTableDescription, ?ColumnDescription $columnDescription) : ?array;

    /**
     * @param Table $table
     * @param Column $column
     * @param string $ForeignKeyName
     * @param TableDescription|null $oldTableDescription
     * @param ColumnDescription|null $columnDescription
     * @return Query[]|null
     */
    public static function removeForeignKey(Table $table, Column $column, string $ForeignKeyName, ?TableDescription $oldTableDescription, ?ColumnDescription $columnDescription) : ?array;

    //Comparsions:

    public static function compareType(string $type1, string $type2) : bool;
    public static function compareComment(?string $comment1, ?string $comment2) : bool;

}

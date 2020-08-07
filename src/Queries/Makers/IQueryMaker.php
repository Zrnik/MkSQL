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
use Zrny\MkSQL\Queries\Tables\TableDescription;
use Zrny\MkSQL\Table;

interface IQueryMaker
{
    // Table Information

    public static function describeTable(Connection $db, Table $table) : ?TableDescription;

    // Table Operations

    public static function createTableQuery(Table $table) : ?Query;

    //ColumnOperations

    public static function alterTableColumnQuery(Table $table, Column $column) : ?Query;
    public static function createTableColumnQuery(Table $table, Column $column) : ?Query;

    public static function createUniqueIndexQuery(Table $table, Column $column) : ?Query;
    public static function removeUniqueIndexQuery(Table $table, Column $column, string $uniqueIndex) : ?Query;

    public static function createForeignKey(Table $table, Column $column, string $RefPointerString) : ?Query;
    public static function removeForeignKey(Table $table, Column $column, string $ForeignKeyName) : ?Query;

}
<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Zrnik\MkSQL\Queries\Makers;

use Exception;
use Nette\Utils\Strings;
use PDO;
use Zrnik\MkSQL\Column;
use Zrnik\MkSQL\Exceptions\InvalidArgumentException;
use Zrnik\MkSQL\Queries\Query;
use Zrnik\MkSQL\Queries\QueryInfo;
use Zrnik\MkSQL\Queries\Tables\ColumnDescription;
use Zrnik\MkSQL\Queries\Tables\TableDescription;
use Zrnik\MkSQL\Table;
use Zrnik\MkSQL\Tracy\Measure;
use Zrnik\MkSQL\Utils;
use function array_key_exists;
use function count;

class QueryMakerSQLite implements IQueryMaker
{


    // I haven't found true answer, some say that SQLite keys
    // are limited by SQLITE_MAX_SQL_LENGTH (default 1 Mil.)
    // and (hardcoded?) maximum of 1 073 741 824
    //

    // This was bugging!
    // Let's not change it into tokens...
    public const SQLiteKeyMaxLen = 2048;

    /**
     * @param PDO $pdo
     * @param Table $table
     * @return TableDescription|null
     * @noinspection MultiAssignmentUsageInspection
     * @noinspection PhpComplexFunctionInspection
     * @noinspection PhpFunctionCyclomaticComplexityInspection
     */
    public static function describeTable(PDO $pdo, Table $table): ?TableDescription
    {
        $QueryInfo = new QueryInfo();
        $QueryInfo->executionSpeed = microtime(true);
        $QueryInfo->referencedTable = $table;

        $Desc = new TableDescription();
        $Desc->queryMakerClass = __CLASS__;
        $Desc->table = $table;

        /**
         * @noinspection BadExceptionsProcessingInspection
         * I know :(
         */
        try {
            $QueryInfo->querySql = /** @lang text */
                "SELECT * FROM sqlite_master WHERE tbl_name = '" . $table->getName() . "'";

            $Statement = $pdo->prepare($QueryInfo->querySql);

            $QueryInfo->isExecuted = true;
            $Statement->execute();

            /** @var array<array<bool|float|int|string|null>>|false $SQLiteTableData */
            $SQLiteTableData = $Statement->fetchAll(PDO::FETCH_ASSOC);
            $QueryInfo->isSuccess = true;

            if ($SQLiteTableData === false || count($SQLiteTableData) === 0) {
                throw new Exception('Table does not exist!');
            }

            $Desc->tableExists = true;

            //Columns:
            foreach ($table->columnList() as $column) {

                $ColDesc = new ColumnDescription();
                $ColDesc->table = $table;
                $ColDesc->column = $column;

                foreach ($SQLiteTableData as $PartRow) {

                    if (!array_key_exists('sql', $PartRow) || $PartRow['sql'] === null) {
                        continue;
                    }

                    /**
                     * @var string $sql
                     */
                    $sql = str_replace(
                        ["\r", "\n", 'CREATE TABLE "' . $table->getName() . '"'],
                        [' ', ' ', 'CREATE TABLE ' . $table->getName()],
                        (string)$PartRow['sql']
                    );

                    while (Strings::contains($sql, '  ')) {
                        $sql = str_replace('  ', ' ', $sql);
                    }

                    if (Strings::startsWith($sql, 'CREATE TABLE')) {


                        $queryToParse = Strings::trim(str_replace([
                            'CREATE TABLE ' . $table->getName() . ' (',
                            ')[end]'
                        ], '', $sql . '[end]'));

                        $parts = explode(', ', $queryToParse);

                        //Find Primary Key Name:
                        foreach ($parts as $part) {
                            if (Strings::contains(strtolower($part), 'primary key')) {
                                [$primaryKey] = explode(' ', Strings::normalize($part));
                                $primaryKey = Strings::normalize($primaryKey);
                                $Desc->primaryKeyName = $primaryKey;
                            }
                        }

                        foreach ($parts as $part) {
                            $part = Strings::trim($part);

                            //echo $part.PHP_EOL.PHP_EOL;
                            //Column Exists
                            if (
                                Strings::startsWith($part, $column->getName() . ' ')
                                ||
                                Strings::startsWith($part, '`' . $column->getName() . '` ')
                            ) {

                                $ColDesc->columnExists = true;

                                //Type:
                                $ColDesc->type = explode(' ', $part)[1];

                                //Not Null
                                $ColDesc->notNull =
                                    Strings::contains($part, 'NOT NULL')
                                    ||
                                    Strings::contains($part, 'not null');

                                //Default Value:
                                if (Strings::contains(strtolower($part), 'default')) {

                                    $BeforeDefault = '';
                                    $DefaultPart = '';

                                    if (Strings::contains($part, 'DEFAULT')) {
                                        [$BeforeDefault, $DefaultPart]
                                            = explode("DEFAULT '", $part);
                                    }

                                    if (Strings::contains($part, 'default')) {
                                        [$BeforeDefault, $DefaultPart]
                                            = explode("default '", $part);
                                    }

                                    unset($BeforeDefault);

                                    $defaultValue = '';
                                    $defaultParts = explode("'", $DefaultPart);

                                    foreach ($defaultParts as $DefaultPart) {
                                        $defaultValue .= $DefaultPart;
                                        // Escaped Apostrophe
                                        if (Strings::endsWith($DefaultPart, "\\")) {
                                            $defaultValue .= '\'';
                                        } else {
                                            break;
                                        }
                                    }
                                    $ColDesc->default = $defaultValue;
                                }

                                //Foreign Keys:
                                if (
                                    Strings::contains($part, 'CONSTRAINT')
                                    ||
                                    Strings::contains($part, 'constraint')) {

                                    $constWord = Strings::contains($part, 'CONSTRAINT') ? 'CONSTRAINT' : 'constraint';

                                    $f = true;
                                    foreach (explode($constWord, $part) as $constraint) {
                                        if ($f) {
                                            $f = false;
                                            continue;
                                        }

                                        if (
                                            Strings::contains($constraint, 'REFERENCES')
                                            ||
                                            Strings::contains($constraint, 'references')
                                        ) {
                                            $refWord = Strings::contains($part, 'REFERENCES') ? 'REFERENCES' : 'references';

                                            //Yup, seems like foreign key
                                            [$key, $ref] = explode($refWord, $constraint);

                                            /** @var string $ref */
                                            $ref = str_replace(
                                                [')', ' ', '('],
                                                ['', '', '.'],
                                                $ref
                                            );

                                            // If there is no dot in the ref string,
                                            // it references primary key of table...
                                            // if it's all created from MkSQL then
                                            // the primary key is ID...

                                            if (!Strings::contains($ref, '.')) {
                                                $ref .= '.' . $table->getPrimaryKeyName();
                                            }

                                            $ColDesc->foreignKeys[$ref] = trim($key);
                                            //echo "Found ForeignKey: ".$ref.'=>'.$key.PHP_EOL;
                                        }
                                    }
                                }
                            }
                        }
                    }

                    if (Strings::startsWith($sql, 'CREATE UNIQUE INDEX')) {
                        $row = trim($sql);
                        $parts = explode(' ', $row);

                        $_uniqueIndexKey = $parts[3];
                        $_uniqueIndexTable = $parts[5];
                        $_uniqueIndexColumn = Strings::trim($parts[6], '()');

                        if (($_uniqueIndexTable === $ColDesc->table->getName()) && $_uniqueIndexColumn === $ColDesc->column->getName()) {
                            $ColDesc->uniqueIndex = $_uniqueIndexKey;
                        }
                    }
                }

                $Desc->columns[] = $ColDesc;
            }
        } catch (Exception) {
            $QueryInfo->isSuccess = false;
            $Desc->tableExists = false;
        }


        $QueryInfo->executionSpeed = microtime(true) - $QueryInfo->executionSpeed;
        Measure::reportQueryDescription($QueryInfo);

        return $Desc;
    }

    /**
     * @param string $oldKey
     * @param Table $table
     * @param TableDescription|null $oldTableDescription
     * @return Query[]|null
     * @throws InvalidArgumentException
     */
    public static function changePrimaryKeyQuery(string $oldKey, Table $table, ?TableDescription $oldTableDescription): ?array
    {

        return static::alterTableColumnQuery(
            $table,
            new Column('not_needed'),
            $oldTableDescription,
            null, [$table->getPrimaryKeyName() => $oldKey]);
    }

    /**
     * @param Table $table
     * @param Column $column
     * @param TableDescription|null $oldTableDescription
     * @param ColumnDescription|null $columnDescription
     * @param array<string> $swapPrimaryKeyName
     * @return Query[]|null
     * @throws InvalidArgumentException
     */
    public static function alterTableColumnQuery(
        Table              $table, Column $column,
        ?TableDescription  $oldTableDescription,
        ?ColumnDescription $columnDescription,
        array              $swapPrimaryKeyName = []
    ): ?array
    {
        $alterTableQueryList = [];

        if ($column->column_handled) {
            return [];
        }

        $temporaryName = Utils::confirmTableName($table->getName() . '_mksql_tmp');

        $createTableQuery = static::createTableQuery($table, $oldTableDescription)[0];
        $createTableQuery->setReason("Alteration required for table '" . $table->getName() . '.' . $column->getName() . "'.");
        $createTableQuery->setQuery(str_replace(
            'CREATE TABLE ' . $table->getName() . ' (',
            'CREATE TABLE ' . $temporaryName . ' (',
            $createTableQuery->getQuery()));

        $alterTableQueryList[] = $createTableQuery;

        $MoveColumns = [];

        foreach ($table->columnList() as $subColumn) {
            $MoveColumns[] = $column->getName();
            $createColumnQueryList = static::createTableColumnQuery($table, $subColumn, $oldTableDescription, $columnDescription, true);
            foreach ($createColumnQueryList as $columnQuery) {
                $columnQuery->setQuery(str_replace(
                    'ALTER TABLE ' . $table->getName() . ' ADD',
                    'ALTER TABLE ' . $temporaryName . ' ADD',
                    $columnQuery->getQuery()));

                $alterTableQueryList[] = $columnQuery;
            }
            $subColumn->column_handled = true;
        }

        $keyInBothArrays = [$table->getPrimaryKeyName()];
        foreach ($MoveColumns as $columnName) {
            if($oldTableDescription !== null) {
                foreach ($oldTableDescription->columns as $subColumnDescription) {
                    if ($subColumnDescription->column->getName() === $columnName) {
                        $keyInBothArrays[] = $columnName;
                        break;
                    }
                }
            }
        }

        $columnList = implode(', ', $keyInBothArrays);

        $InsertIntoQuery = new Query($table, $column);

        //Implementation of $swapColumnNames
        $originalList = [];
        foreach ($keyInBothArrays as $oldColName) {

            $insertCol = $oldColName;
            $insertCol = $swapPrimaryKeyName[$insertCol] ?? $oldColName;

            $originalList[] = $insertCol;
        }

        $selectList = implode(', ', $originalList);


        $InsertIntoQuery->setQuery('INSERT INTO ' . $temporaryName . '(' . $columnList . ') SELECT ' . $selectList . ' FROM ' . $table->getName());
        $InsertIntoQuery->setReason("Altering Table '" . $temporaryName . "' created, moving data...");
        $alterTableQueryList[] = $InsertIntoQuery;

        $DropOldTable = new Query($table, $column);
        $DropOldTable->setQuery('DROP TABLE ' . $table->getName());
        $DropOldTable->setReason("Altering Table '" . $temporaryName . "' data moved, dropping original table '" . $table->getName() . "'...");
        $alterTableQueryList[] = $DropOldTable;


        $RenameTable = new Query($table, $column);
        $RenameTable->setQuery('ALTER TABLE ' . $temporaryName . ' RENAME TO ' . $table->getName());
        $RenameTable->setReason("Original Table '" . $table->getName() . "' dropped, renaming table '" . $temporaryName . "'...");
        $alterTableQueryList[] = $RenameTable;

        //echo " -Modified table ".$table->getName()." creating Unique Indexes".PHP_EOL;

        foreach ($table->columnList() as $listedColumn) {

            $listedColumnDesc = $oldTableDescription?->columnGet($listedColumn->getName());

            if ($listedColumnDesc?->uniqueIndex !== null || $listedColumn->getUnique()) {
                //echo " - creating unique index for ".$column->getName()." in table ".$table->getName().PHP_EOL;

                $newQueries = static::createUniqueIndexQuery(
                    $table,
                    $listedColumn,
                    $oldTableDescription,
                    null
                );

                foreach ($newQueries as $query) {
                    //echo $query->getQuery().PHP_EOL;
                    $alterTableQueryList[] = $query;
                }
            }
        }


        return $alterTableQueryList;
    }

    /**
     * @param Table $table
     * @param TableDescription|null $oldTableDescription
     * @return Query[]
     * @throws InvalidArgumentException
     */
    public static function createTableQuery(Table $table, ?TableDescription $oldTableDescription): array
    {
        $primaryKeyName = Utils::confirmKeyName($table->getName() . '_' . $table->getPrimaryKeyName() . '_pk', self::SQLiteKeyMaxLen);

        return [
            (new Query($table, null))
                ->setQuery(

                    sprintf(
                    /** @lang */ 'CREATE TABLE %s (%s %s constraint %s primary key %s) ',
                        $table->getName(), $table->getPrimaryKeyName(),
                        self::fixPrimaryKeyType($table->getPrimaryKeyType()),
                        $primaryKeyName,
                        str_starts_with(strtolower(self::fixPrimaryKeyType($table->getPrimaryKeyType())), 'int') ? 'autoincrement' : ''

                    )

                //"CREATE TABLE " . $table->getName() . " (" . $table->getPrimaryKeyName() . " " . self::fixPrimaryKeyType($table->getPrimaryKeyType()) . " constraint " . $primaryKeyName . " primary key autoincrement);"

                )
                ->setReason("Table '" . $table->getName() . "' not found.")
        ];
    }

    /**
     * @param Table $table
     * @param Column $column
     * @param TableDescription|null $oldTableDescription
     * @param ColumnDescription|null $columnDescription
     * @param bool $isForTemporaryTable
     * @return Query[]
     * @throws InvalidArgumentException
     */
    public static function createTableColumnQuery(Table $table, Column $column, ?TableDescription $oldTableDescription, ?ColumnDescription $columnDescription, bool $isForTemporaryTable = false): array
    {

        if (!$isForTemporaryTable && $column->column_handled) {
            return [];
        }

        $CreateSQL = 'ALTER TABLE ' . $table->getName() . ' ';
        $CreateSQL .= 'ADD ' . $column->getName() . ' ' . $column->getType() . ' ';

        //Default Value

        if ($column->getDefault() !== null) {
            $CreateSQL .= 'DEFAULT \'' . $column->getDefault() . '\' ';
        }

        /**
         * @seehttps://sqlite.org/forum/info/ffa52447275d247a
         */
        if ($column->getDefault() === null && $column->getNotNull()) {
            $CreateSQL .= 'DEFAULT \'\' ';
        }

        //Null/NotNull
        $CreateSQL .= ($column->getNotNull() ? 'NOT ' : '') . 'NULL ';

        //Comment
        //Not in SQLite, BUT:

        //FOREIGN KEY!

        foreach ($column->getForeignKeys() as $keyTarget) {
            $ptr = Utils::confirmForeignKeyTarget($keyTarget);
            [$targetTable, $targetColumn] = explode('.', $ptr);

            $foreignKeyName = Utils::confirmKeyName('f_key_' . $table->getName() . '_' . $targetTable . '_' . $column->getName() . '_' . $targetColumn, self::SQLiteKeyMaxLen);

            $CreateSQL .= 'CONSTRAINT ' . $foreignKeyName . ' REFERENCES ' . $targetTable . ' (' . $targetColumn . ') ';
        }


        return [
            (new Query($table, $column))
                ->setQuery(trim($CreateSQL))
                ->setReason("Column '" . $table->getName() . '.' . $column->getName() . "' not found.")
        ];
    }

    /**
     * @param Table $table
     * @param Column $column
     * @param TableDescription|null $oldTableDescription
     * @param ColumnDescription|null $columnDescription
     * @return Query[]
     * @throws InvalidArgumentException
     */
    public static function createUniqueIndexQuery(Table $table, Column $column, ?TableDescription $oldTableDescription, ?ColumnDescription $columnDescription): array
    {
        if ($column->unique_index_handled) {
            return [];
        }

        $column->unique_index_handled = true;


        $newKey = Utils::confirmKeyName(
            'unique_index_' . $table->getName() . '_' . $column->getName(),
            self::SQLiteKeyMaxLen
        );

        return [
            (new Query($table, $column))
                ->setQuery('CREATE UNIQUE INDEX ' . $newKey . ' ON ' . $table->getName() . ' (' . $column->getName() . ');')
                ->setReason("Unique index on column '" . $table->getName() . '.' . $column->getName() . "' not found.")
        ];
    }

    /**
     * @param Table $table
     * @param Column $column
     * @param string $uniqueIndex
     * @param TableDescription|null $oldTableDescription
     * @param ColumnDescription|null $columnDescription
     * @return Query[]|null
     */
    public static function removeUniqueIndexQuery(Table $table, Column $column, string $uniqueIndex, ?TableDescription $oldTableDescription, ?ColumnDescription $columnDescription): ?array
    {

        return [
            (new Query($table, $column))
                ->setQuery('DROP INDEX ' . $uniqueIndex . ';')
                ->setReason("Unique index on column '" . $table->getName() . '.' . $column->getName() . "' not defined.")
        ];
    }

    /**
     * @param Table $table
     * @param Column $column
     * @param string $RefPointerString table_name.table_column
     * @param TableDescription|null $oldTableDescription
     * @param ColumnDescription|null $columnDescription
     * @return Query[]|null
     */
    public static function createForeignKey(Table $table, Column $column, string $RefPointerString, ?TableDescription $oldTableDescription, ?ColumnDescription $columnDescription): ?array
    {
        return static::alterTableColumnQuery($table, $column, $oldTableDescription, $columnDescription);
    }

    /**
     * @param Table $table
     * @param Column $column
     * @param string $ForeignKeyName
     * @param TableDescription|null $oldTableDescription
     * @param ColumnDescription|null $columnDescription
     * @return Query[]|null
     */
    public static function removeForeignKey(Table $table, Column $column, string $ForeignKeyName, ?TableDescription $oldTableDescription, ?ColumnDescription $columnDescription): ?array
    {
        return static::alterTableColumnQuery($table, $column, $oldTableDescription, $columnDescription);
    }

    /**
     * @param string $type1
     * @param string $type2
     * @return bool
     * @throws InvalidArgumentException
     */
    public static function compareType(string $type1, string $type2): bool
    {
        $type1 = Utils::confirmType(strtolower($type1));
        $type2 = Utils::confirmType(strtolower($type2));

        if ($type1 === 'integer') {
            $type1 = 'int';
        }

        if ($type2 === 'integer') {
            $type2 = 'int';
        }

        $Exceptions = [
            'tinyint', 'mediumint', 'int', 'bigint',
        ];

        foreach ($Exceptions as $Exception) {
            if (Strings::startsWith($type1, $Exception) && Strings::startsWith($type2, $Exception)) {
                return true;
            }
        }

        return $type1 === $type2;
    }

    /**
     * @param float|bool|int|string|null $comment1
     * @param float|bool|int|string|null $comment2
     * @return bool
     */
    public static function compareComment(
        float|bool|int|string|null $comment1,
        float|bool|int|string|null $comment2,
    ): bool
    {
        //Comments not supported by SQLite, just report that its correct
        return true;
    }

    private static function fixPrimaryKeyType(string $keyType): string
    {
        if(str_starts_with($keyType,'int'))
        {
            return 'integer';
        }

        return $keyType;
    }
}

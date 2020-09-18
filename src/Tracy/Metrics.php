<?php
/*
 * Zrník.eu | MkSQL
 * User: Programátor
 * Date: 06.08.2020 8:05
 */

namespace Zrnik\MkSQL\Tracy;

use Zrnik\MkSQL\Column;
use Zrnik\MkSQL\Queries\Query;
use Zrnik\MkSQL\Table;

class Metrics
{
    /**
     * @var float|int
     */
    public static float $_measurementTotal = 0;
    /**
     * @var float|int
     */
    public static float $_measurementQueryExecuting = 0;
    /**
     * @var float|int
     */
    public static float $_measurementQueryPreparing = 0;
    /**
     * @var array
     */
    public static array $_measurementTables = [];
    /**
     * @var float|int
     */
    private static float $_measurementTotalBegin = 0;
    /**
     * @var float|int
     */
    private static float $_measurementQueryExecutingBegin = 0;
    /**
     * @var float|int
     */
    private static float $_measurementQueryPreparingBegin = 0;
    /**
     * @var array
     */
    private static array $_measurementTablesBegins = [];
    /**
     * @var array
     */
    private static array $_TableCalls = [];
    /**
     * @var Query[]
     */
    private static array $_Queries = [];
    /**
     * @var array
     */
    private static array $_Structure = [];

    /**
     * @param false $finished
     */
    public static function measureTotal($finished = false)
    {
        if ($finished) {
            static::$_measurementTotal += microtime(true) - static::$_measurementTotalBegin;
        } else {
            static::$_measurementTotalBegin = microtime(true);
        }
    }

    /**
     * @param false $finished
     */
    public static function measureQueryExecuting($finished = false)
    {
        if ($finished) {
            static::$_measurementQueryExecuting += microtime(true) - static::$_measurementQueryExecutingBegin;
        } else {
            static::$_measurementQueryExecutingBegin = microtime(true);
        }
    }

    /**
     * @param bool $finished
     */
    public static function measureQueryPreparing(bool $finished = false)
    {
        if ($finished) {
            static::$_measurementQueryPreparing += microtime(true) - static::$_measurementQueryPreparingBegin;
        } else {
            static::$_measurementQueryPreparingBegin = microtime(true);
        }
    }

    /**
     * @param string $TableName
     * @param string $Action
     * @param false $finished
     */
    public static function measureTable(string $TableName, string $Action, $finished = false)
    {
        if (!isset(static::$_measurementTables[$TableName]))
            static::$_measurementTables[$TableName] = [];
        if (!isset(static::$_measurementTables[$TableName][$Action]))
            static::$_measurementTables[$TableName][$Action] = 0;
        if (!isset(static::$_measurementTablesBegins[$TableName]))
            static::$_measurementTablesBegins[$TableName] = [];
        if (!isset(static::$_measurementTablesBegins[$TableName][$Action]))
            static::$_measurementTablesBegins[$TableName][$Action] = 0;

        if ($finished) {
            static::$_measurementTables[$TableName][$Action] +=
                microtime(true) -
                static::$_measurementTablesBegins[$TableName][$Action];
        } else {
            static::$_measurementTablesBegins[$TableName][$Action] = microtime(true);
        }
    }

    /**
     * @param string $tableName
     */
    public static function logTableInstallCalls(string $tableName): void
    {
        if (!isset(static::$_TableCalls[$tableName]))
            static::$_TableCalls[$tableName] = 0;
        static::$_TableCalls[$tableName]++;
    }

    /**
     * @param string $tableName
     * @return mixed
     */
    public static function getTableCallCount(string $tableName)
    {
        if (!isset(static::$_TableCalls[$tableName]))
            static::$_TableCalls[$tableName] = 0;
        return static::$_TableCalls[$tableName];
    }

    public static function logQueries(array $Queries)
    {
        static::$_Queries = array_merge(static::$_Queries, $Queries);
    }

    /**
     * @return Query[]
     */
    public static function getQueries(): array
    {
        return static::$_Queries;
    }

    /**
     * @param Table $table
     * @param Column $column
     */
    public static function logStructure(Table $table, Column $column)
    {
        if (!isset(static::$_Structure[$table->getName()]))
            static::$_Structure[$table->getName()] = [];

        if (!isset(static::$_Structure[$table->getName()][$column->getName()]))
            static::$_Structure[$table->getName()][$column->getName()] = $column;
    }

    /**
     * @return array
     */
    public static function getStructure()
    {
        return static::$_Structure;
    }
}

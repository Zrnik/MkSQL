<?php
/*
 * Zrník.eu | MkSQL  
 * User: Programátor
 * Date: 06.08.2020 8:05
 */


namespace Zrny\MkSQL\Nette;

use Zrny\MkSQL\Column;
use Zrny\MkSQL\Queries\Query;
use Zrny\MkSQL\Table;

class Metrics
{

    public static $_measurementTotal = 0;
    private static $_measurementTotalBegin = 0;
    public static function measureTotal($finished = false)
    {
        if($finished)
        {
            static::$_measurementTotal += microtime(true) - static::$_measurementTotalBegin;
        }
        else
        {
            static::$_measurementTotalBegin = microtime(true);
        }
    }


    public static $_measurementQueryExecuting = 0;
    private static $_measurementQueryExecutingBegin = 0;
    public static function measureQueryExecuting($finished = false)
    {
        if($finished)
        {
            static::$_measurementQueryExecuting += microtime(true) - static::$_measurementQueryExecutingBegin;
        }
        else
        {
            static::$_measurementQueryExecutingBegin = microtime(true);
        }
    }


    public static $_measurementQueryPreparing = 0;
    private static $_measurementQueryPreparingBegin = 0;
    public static function measureQueryPreparing($finished = false)
    {
        if($finished)
        {
            static::$_measurementQueryPreparing += microtime(true) - static::$_measurementQueryPreparingBegin;
        }
        else
        {
            static::$_measurementQueryPreparingBegin = microtime(true);
        }
    }


    public static $_measurementTables = [];
    private static $_measurementTablesBegins = [];
    public static function measureTable(string $TableName, string $Action, $finished = false)
    {
        if(!isset(static::$_measurementTables[$TableName]))
            static::$_measurementTables[$TableName] = [];
        if(!isset(static::$_measurementTables[$TableName][$Action]))
            static::$_measurementTables[$TableName][$Action] = 0;
        if(!isset(static::$_measurementTablesBegins[$TableName]))
            static::$_measurementTablesBegins[$TableName] = [];
        if(!isset(static::$_measurementTablesBegins[$TableName][$Action]))
            static::$_measurementTablesBegins[$TableName][$Action] = 0;

        if($finished)
        {
            static::$_measurementTables[$TableName][$Action] +=
                microtime(true) -
                static::$_measurementTablesBegins[$TableName][$Action];
        }
        else
        {
            static::$_measurementTablesBegins[$TableName][$Action] = microtime(true);
        }
    }

    private static $_TableCalls = [];
    public static function logTableInstallCalls(string $tableName) : void
    {
        if(!isset(static::$_TableCalls[$tableName]))
            static::$_TableCalls[$tableName] = 0;
        static::$_TableCalls[$tableName]++;
    }

    public static function getTableCallCount(string $tableName)
    {
        if(!isset(static::$_TableCalls[$tableName]))
            static::$_TableCalls[$tableName] = 0;
        return static::$_TableCalls[$tableName];
    }

    /**
     * @var Query[]
     */
    private static $_Queries = [];
    public static function logQueries(array $Queries)
    {
        static::$_Queries = array_merge(static::$_Queries, $Queries);
    }

    /**
     * @return Query[]
     */
    public static function getQueries()
    {
        return static::$_Queries;
    }


    private static $_Structure = [];
    public static function logStructure(Table $table, Column $column)
    {
        if(!isset(static::$_Structure[$table->getName()]))
            static::$_Structure[$table->getName()] = [];

        if(!isset(static::$_Structure[$table->getName()][$column->getName()]))
            static::$_Structure[$table->getName()][$column->getName()] = $column;
    }

    public static function getStructure()
    {
        return static::$_Structure;
    }




}

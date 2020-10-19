<?php declare(strict_types=1);
/*
 * Zrník.eu | AgronaroWebsite  
 * User: Programátor
 * Date: 19.10.2020 8:52
 */


namespace Zrnik\MkSQL\Tracy;


use Zrnik\MkSQL\Column;
use Zrnik\MkSQL\Exceptions\InvalidArgumentException;
use Zrnik\MkSQL\Queries\Query;
use Zrnik\MkSQL\Queries\QueryInfo;
use Zrnik\MkSQL\Table;

class Measure
{

    //region Public Static Driver Indicator!
    public static ?int $Driver = null;
    //endregion

    //region Total Speed
    private static float $_totalSpeed = 0;

    public static function reportTotalSpeed(float $microTime)
    {
        static::$_totalSpeed += $microTime;
    }

    public static function getTotalSpeed()
    {
        return static::$_totalSpeed;
    }
    //endregion

    //region Table Speed

    const TABLE_SPEED_DESCRIBE = 1;
    const TABLE_SPEED_GENERATE = 2;
    const TABLE_SPEED_EXECUTE = 3;

    private static array $_tableSpeeds = [];


    public static function logTableSpeed(string $tableName, int $type, float $speed): void
    {
        if(!in_array($type, [
            self::TABLE_SPEED_DESCRIBE,
            self::TABLE_SPEED_GENERATE,
            self::TABLE_SPEED_EXECUTE
        ]))
            throw new InvalidArgumentException("Invalid type '".$type."' for measurement!");

        if(!isset(static::$_tableSpeeds[$tableName]))
            static::$_tableSpeeds[$tableName] = [];

        if(!isset(static::$_tableSpeeds[$tableName][$type]))
            static::$_tableSpeeds[$tableName][$type] = 0;

        static::$_tableSpeeds[$tableName][$type] += $speed;
    }

    public static function getTableSpeed(string $tableName, int $type): float
    {
        if(!isset(static::$_tableSpeeds[$tableName]))
            static::$_tableSpeeds[$tableName] = [];

        if(!isset(static::$_tableSpeeds[$tableName][$type]))
            static::$_tableSpeeds[$tableName][$type] = 0;

        return static::$_tableSpeeds[$tableName][$type];
    }

    public static function getTableTotalSpeed(?string $checkedTable = null): float
    {
        $speed = 0;

        foreach(static::$_tableSpeeds as $tableName => $measurements)
        {
            if($checkedTable !== null && $checkedTable !== $tableName)
                continue;

            $speed += static::getTableSpeed($tableName,self::TABLE_SPEED_DESCRIBE);
            $speed += static::getTableSpeed($tableName,self::TABLE_SPEED_GENERATE);
            $speed += static::getTableSpeed($tableName,self::TABLE_SPEED_EXECUTE);

        }





        return $speed;
    }


    //endregion

    //region Queries

    /**
     * @var QueryInfo[]
     */
    public static array $_DescriptionQueries = [];

    /**
     * @var Query[]
     */
    public static array $_ModificationQueries = [];

    /**
     * @return QueryInfo[]
     */
    public static function getQueryDescription()
    {
        return static::$_DescriptionQueries;
    }

    /**
     * @return Query[]
     */
    public static function getQueryModification()
    {
        return static::$_ModificationQueries;
    }

    public static function reportQueryDescription(QueryInfo $query)
    {
        static::$_DescriptionQueries[] = $query;
    }

    public static function reportQueryModification(Query $query)
    {
        static::$_ModificationQueries[] = $query;
    }

    public static function querySpeedDescription(): float
    {
        $speed = 0;

        foreach(static::$_DescriptionQueries as $descQuery)
            $speed += $descQuery->executionSpeed;

        return $speed;
    }

    public static function querySpeedModification(): float
    {
        $speed = 0;

        foreach(static::$_ModificationQueries as $modQuery)
            $speed += $modQuery->speed;

        return $speed;
    }


    public static function queryCountDescription(): float
    {
        return count(static::$_DescriptionQueries);
    }

    public static function queryCountModification(): float
    {
        return count(static::$_ModificationQueries);
    }

    //endreion



   /* public static function reportPrepareQuery(QueryInfo $queryInfo)
    {
        static::$_preparedQueries[] = $queryInfo;
    }

    public static function getTotalQueriesPrepareSpeed()
    {
        $res = 0;
        /**
         * @var $preparedQuery QueryInfo
         * /
        foreach (static::$_preparedQueries as $preparedQuery)
            $res += $preparedQuery->executionSpeed;

        return $res;
    }

    public static function getTotalQueriesPrepareCount(): int
    {
        return count(static::$_preparedQueries);
    }

    public static array $_executedQueries = [];

    public static function reportExecuteQuery(Query $query)
    {
        static::$_executedQueries[] = $query;
    }

    public static function getTotalQueriesExecuteCount(): int
    {
        return count(static::$_executedQueries);
    }

    /**
     * @return Query[]
     * /
    public static function getExecuteQueries()
    {
        return static::$_executedQueries;
    }*/


    //region Structure

    private static array $_Tables = [];
    private static array $_Columns = [];

    public static function structureTableList()
    {
        return static::$_Tables;
    }


    public static function structureColumnList(string $tableName)
    {
        if (!isset(static::$_Columns[$tableName])) {
            return [];
        }
        return static::$_Columns[$tableName];
    }

    public static function reportStructureTable(Table $table)
    {
        if (!isset(static::$_Tables[$table->getName()]))
            static::$_Tables[$table->getName()] = [
                "calls" => 0,
                "objects" => []
            ];

        static::$_Tables[$table->getName()]["calls"]++;
        static::$_Tables[$table->getName()]["objects"][] = $table;
    }

    public static function reportStructureColumn(Table $table, Column $column)
    {
        if (!isset(static::$_Columns[$table->getName()]))
            static::$_Columns[$table->getName()] = [];

        if (!isset(static::$_Columns[$table->getName()][$column->getName()]))
            static::$_Columns[$table->getName()][$column->getName()] = [];

        static::$_Columns[$table->getName()][$column->getName()] = $column;
    }

    public static function structureTableCount(): int
    {
        return count(static::$_Tables);
    }


    /**
     * Returns a count of tabled for ONE or ALL tables if $tableName is null!
     * @param string|null $filterTable
     * @return int
     */
    public static function structureColumnCount(?string $filterTable = null): int
    {
        $result = 0;

        foreach (static::$_Columns as $tableName => $columnList) {
            if ($filterTable !== null && $filterTable !== $tableName)
                continue;

            $result += count($columnList);
        }

        return $result;
    }

    //endregion

}
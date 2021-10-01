<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Zrnik\MkSQL\Tracy;

use JetBrains\PhpStorm\Pure;
use Zrnik\MkSQL\Column;
use Zrnik\MkSQL\Exceptions\InvalidArgumentException;
use Zrnik\MkSQL\Queries\Query;
use Zrnik\MkSQL\Queries\QueryInfo;
use Zrnik\MkSQL\Table;
use function count;
use function in_array;

class Measure
{

    //region Public Static Driver Indicator!
    public static ?int $Driver = null;
    //endregion

    //region Total Speed

    private static float $_totalSpeed = 0;

    public static function reportTotalSpeed(float $microTime): void
    {
        static::$_totalSpeed += $microTime;
    }

    public static function getTotalSpeed(): float
    {
        return static::$_totalSpeed;
    }
    //endregion

    //region Table Speed

    public const TABLE_SPEED_DESCRIBE = 1;
    public const TABLE_SPEED_GENERATE = 2;
    public const TABLE_SPEED_EXECUTE = 3;

    /**
     * @var array<array<float>>
     */
    private static array $_tableSpeeds = [];


    /**
     * @param string $tableName
     * @param int $type
     * @param float $speed
     * @throws InvalidArgumentException
     */
    public static function logTableSpeed(string $tableName, int $type, float $speed): void
    {
        if (!in_array($type, [
            self::TABLE_SPEED_DESCRIBE,
            self::TABLE_SPEED_GENERATE,
            self::TABLE_SPEED_EXECUTE
        ])) {
            throw new InvalidArgumentException("Invalid type '" . $type . "' for measurement!");
        }

        if (!isset(static::$_tableSpeeds[$tableName])) {
            static::$_tableSpeeds[$tableName] = [];
        }

        if (!isset(static::$_tableSpeeds[$tableName][$type])) {
            static::$_tableSpeeds[$tableName][$type] = 0;
        }

        static::$_tableSpeeds[$tableName][$type] += $speed;
    }

    public static function getTableSpeed(string $tableName, int $type): float
    {
        if (!isset(static::$_tableSpeeds[$tableName])) {
            static::$_tableSpeeds[$tableName] = [];
        }

        if (!isset(static::$_tableSpeeds[$tableName][$type])) {
            static::$_tableSpeeds[$tableName][$type] = 0;
        }

        return static::$_tableSpeeds[$tableName][$type];
    }

    public static function getTableTotalSpeed(?string $checkedTable = null): float
    {
        $speed = 0;

        foreach (static::$_tableSpeeds as $tableName => $measurements) {
            if ($checkedTable !== null && $checkedTable !== $tableName) {
                continue;
            }

            $speed += static::getTableSpeed($tableName, self::TABLE_SPEED_DESCRIBE);
            $speed += static::getTableSpeed($tableName, self::TABLE_SPEED_GENERATE);
            $speed += static::getTableSpeed($tableName, self::TABLE_SPEED_EXECUTE);
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
    public static function getQueryDescription(): array
    {
        return static::$_DescriptionQueries;
    }

    /**
     * @return Query[]
     */
    public static function getQueryModification(): array
    {
        return static::$_ModificationQueries;
    }

    public static function reportQueryDescription(QueryInfo $query): void
    {
        static::$_DescriptionQueries[] = $query;
    }

    public static function reportQueryModification(Query $query): void
    {
        static::$_ModificationQueries[] = $query;
    }

    public static function querySpeedDescription(): float
    {
        $speed = 0;

        foreach (static::$_DescriptionQueries as $descQuery) {
            $speed += $descQuery->executionSpeed;
        }

        return $speed;
    }

    public static function querySpeedModification(): float
    {
        $speed = 0;

        foreach (static::$_ModificationQueries as $modQuery) {
            $speed += $modQuery->speed;
        }

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

    //endregion

    //region Structure

    /**
     * @var array<array>
     */
    private static array $_Tables = [];

    /**
     * @var array<array>
     */
    private static array $_Columns = [];

    /**
     * @return array<array>
     */
    public static function structureTableList(): array
    {
        return static::$_Tables;
    }


    /**
     * @param string $tableName
     * @return array<array>
     */
    public static function structureColumnList(string $tableName): array
    {
        return static::$_Columns[$tableName] ?? [];
    }

    /**
     * @param Table $table
     */
    public static function reportStructureTable(Table $table): void
    {
        if (!isset(static::$_Tables[$table->getName()])) {
            static::$_Tables[$table->getName()] = [
                'calls' => 0,
                'objects' => []
            ];
        }

        static::$_Tables[$table->getName()]['calls']++;
        static::$_Tables[$table->getName()]['objects'][] = $table;
    }

    /**
     * @param Table $table
     * @param Column $column
     */
    public static function reportStructureColumn(Table $table, Column $column): void
    {
        if (!isset(static::$_Columns[$table->getName()])) {
            static::$_Columns[$table->getName()] = [];
        }

        if (!isset(static::$_Columns[$table->getName()][$column->getName()])) {
            static::$_Columns[$table->getName()][$column->getName()] = [];
        }

        static::$_Columns[$table->getName()][$column->getName()] = $column;
    }

    #[Pure]
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
            if ($filterTable !== null && $filterTable !== $tableName) {
                continue;
            }

            $result += count($columnList);
        }

        return $result;
    }

    //endregion

}

<?php declare(strict_types=1);

namespace Zrnik\MkSQL\Utilities;

use JetBrains\PhpStorm\Pure;
use Zrnik\MkSQL\Exceptions\MkSQLException;
use Zrnik\MkSQL\Table;
use function array_key_exists;
use function in_array;

class TableOrder
{
    /**
     * @param Table[] $tables
     * @return Table[]
     */
    public static function doOrder(array $tables): array
    {
        $order = self::createOrderRecursivelyByDependencyTable(
            $tables, self::getDependencyTable($tables)
        );

        $orderedList = [];

        foreach ($order as $tableName) {
            $orderedList[] = self::getTableByName($tableName, $tables);
        }

        return $orderedList;
    }

    /**
     * @param Table[] $tables
     * @return array<string, string[]>
     */
    #[Pure]
    private static function getDependencyTable(array $tables): array
    {
        $requirements = [];
        foreach ($tables as $table) {

            foreach ($table->columnList() as $column) {
                foreach ($column->getForeignKeys() as $foreignKey) {
                    $requiredTableName = self::tableNameFromForeignKeyString($foreignKey);

                    if (!array_key_exists($table->getName(), $requirements)) {
                        $requirements[$table->getName()] = [];
                    }

                    if (!in_array($requiredTableName, $requirements[$table->getName()], true)) {
                        $requirements[$table->getName()][] = $requiredTableName;
                    }
                }
            }
        }
        return $requirements;
    }

    private static function tableNameFromForeignKeyString(string $foreignKey): string
    {
        [$fkTable] = explode('.', $foreignKey);
        return $fkTable;
    }

    /**
     * @param Table[] $tables
     * @param array<string, string[]> $dependencyTable
     * @return string[]
     */
    private static function createOrderRecursivelyByDependencyTable(array $tables, array $dependencyTable): array
    {
        $order = [];

        foreach ($tables as $table) {
            if ($table->getName() !== null) {
                $order = self::addTableToOrder($order, $table->getName(), $dependencyTable);
            }
        }

        return $order;
    }

    /**
     * @param string[] $order
     * @param string $tableName
     * @param array<string, string[]> $dependencyTable
     * @param int $stack
     * @return string[]
     */
    private static function addTableToOrder(array $order, string $tableName, array $dependencyTable, int $stack = 0): array
    {
        if ($stack > 150) {
            throw new MkSQLException('Stack Overflow');
        }

        if (array_key_exists($tableName, $dependencyTable)) {
            foreach ($dependencyTable[$tableName] as $requiredSubTable) {
                $order = self::addTableToOrder($order, $requiredSubTable, $dependencyTable, $stack + 1);
            }
        }

        if (!in_array($tableName, $order, true)) {
            $order[] = $tableName;
        }

        return $order;
    }

    /**
     * @param string $tableName
     * @param Table[] $tables
     * @return Table
     */
    private static function getTableByName(string $tableName, array $tables): Table
    {
        foreach ($tables as $table) {
            if ($table->getName() === $tableName) {
                return $table;
            }
        }

        throw new MkSQLException('Somehow, i ordered table that is not in the list? This must be a bug!');
    }

}
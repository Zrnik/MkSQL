<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Queries\Tables;

use Mock\MockSQLMaker_ExistingTable_First;
use Mock\PDO;
use PHPUnit\Framework\TestCase;
use Zrnik\MkSQL\Exceptions\ColumnDefinitionExists;
use Zrnik\MkSQL\Exceptions\PrimaryKeyAutomaticException;
use Zrnik\MkSQL\Exceptions\TableDefinitionExists;
use Zrnik\MkSQL\Table;

class TableDescriptionTest extends TestCase
{
    /**
     * @throws ColumnDefinitionExists
     * @throws PrimaryKeyAutomaticException
     * @throws TableDefinitionExists
     */
    public function testColumn(): void
    {
        //Mock some description:

        $tableDescription = MockSQLMaker_ExistingTable_First::describeTable(new PDO(), new Table('null'));

        $existingColumns = [
            'name',
            'desc',
        ];

        foreach ($existingColumns as $colName) {
            static::assertNotNull($tableDescription?->columnGet($colName));
        }

        $nonExistingColumns = [
            'column_that_doesnt_exists',
            'hello_world_column',
        ];

        foreach ($nonExistingColumns as $colName) {
            static::assertNull($tableDescription->columnGet($colName));
        }
    }
}

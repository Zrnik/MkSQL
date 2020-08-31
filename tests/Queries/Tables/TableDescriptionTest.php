<?php declare(strict_types=1);
/*
 * Zrník.eu | MkSQL  
 * User: Programátor
 * Date: 31.08.2020 8:47
 */

namespace Queries\Tables;

use Mock\MockSQLMakerExistingTables;
use Mock\PDO;
use Zrny\MkSQL\Column;
use Zrny\MkSQL\Queries\Tables\ColumnDescription;
use Zrny\MkSQL\Queries\Tables\TableDescription;
use PHPUnit\Framework\TestCase;
use Zrny\MkSQL\Table;

class TableDescriptionTest extends TestCase
{

    public function testColumn()
    {
        //Mock some description:

        $tableDescription = MockSQLMakerExistingTables::describeTable(new PDO(), new Table("null"));

        $existingColumns = [
            "random_column"
        ];

        foreach($existingColumns as $colName)
            $this->assertNotNull($tableDescription->column($colName));

        $nonExistingColumns = [
            "another_column_that_doesnt_exists",
            "hello_world_column",
        ];

        foreach($nonExistingColumns as $colName)
            $this->assertNull($tableDescription->column($colName));
    }

}

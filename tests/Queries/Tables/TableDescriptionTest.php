<?php declare(strict_types=1);
/*
 * Zrník.eu | MkSQL  
 * User: Programátor
 * Date: 31.08.2020 8:47
 */

namespace Queries\Tables;

use Mock\MockSQLMaker_ExistingTable_First;
use Mock\PDO;
use PHPUnit\Framework\TestCase;
use Zrny\MkSQL\Table;

class TableDescriptionTest extends TestCase
{

    public function testColumn()
    {
        //Mock some description:

        $tableDescription = MockSQLMaker_ExistingTable_First::describeTable(new PDO(), new Table("null"));

        $existingColumns = [
            "name",
            "desc",
        ];

        foreach($existingColumns as $colName)
            $this->assertNotNull($tableDescription->columnGet($colName));

        $nonExistingColumns = [
            "column_that_doesnt_exists",
            "hello_world_column",
        ];

        foreach($nonExistingColumns as $colName)
            $this->assertNull($tableDescription->columnGet($colName));
    }

}

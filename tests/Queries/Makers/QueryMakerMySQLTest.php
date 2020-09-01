<?php declare(strict_types=1);
/*
 * Zrník.eu | MkSQL  
 * User: Programátor
 * Date: 31.08.2020 15:44
 */

namespace Queries\Makers;

use Mock\PDO;
use Zrny\MkSQL\Queries\Makers\QueryMakerMySQL;
use PHPUnit\Framework\TestCase;
use Zrny\MkSQL\Table;

class QueryMakerMySQLTest extends TestCase
{

    public function testDescribeTable()
    {
        $MockPDO = new PDO();
        $MockPDO->mockResult([
            "SHOW CREATE TABLE tested_not_exist" => new \PDOException("Table does not exists!"),
            "SHOW CREATE TABLE known_table" => [
                "Table" => 'known_table',
                "Create Table" => 'CREATE TABLE `known_table` (
                      `id` int NOT NULL AUTO_INCREMENT,
                      `name` varchar(255) NOT NULL DEFAULT \'undefined\',
                      `price` decimal(13,2) DEFAULT NULL,
                      PRIMARY KEY (`id`)
                    ) ENGINE=DoIEvenCare'
            ],
            "SHOW CREATE TABLE sub_table" => [
                "Table" => 'known_table',
                "Create Table" => 'CREATE TABLE `sub_table` (
                  `id` int NOT NULL AUTO_INCREMENT,
                  `parent` int NOT NULL COMMENT \'example comment\',
                  PRIMARY KEY (`id`),
                  KEY `sub_table_known_table_id_fk` (`parent`),
                  CONSTRAINT `sub_table_known_table_id_fk` FOREIGN KEY (`parent`) REFERENCES `known_table` (`id`)
                ) ENGINE=NoIReallyDontCare'
            ]
        ]);

        $description = QueryMakerMySQL::describeTable($MockPDO, new Table("tested_not_exist"));

        $this->assertNotNull($description);
        $this->assertNotTrue($description->tableExists);

        $Table = new Table("known_table");

        $Table->columnCreate("name","varchar(255)")->setNotNull()->setDefault("undefined");
        $Table->columnCreate("price","decimal(13, 2)");

        $description = QueryMakerMySQL::describeTable($MockPDO,$Table);

        $this->assertNotNull($description);

        $this->assertTrue($description->tableExists);

        $this->assertSame(
            "known_table",
            $description->table->getName()
        );

        $this->assertSame(
            "name",
            $description->columnGet("name")->column->getName()
        );

        $this->assertSame(
            "varchar(255)",
            $description->columnGet("name")->type
        );

        $this->assertSame(
            [],
            $description->columnGet("name")->foreignKeys
        );

        $this->assertTrue($description->columnGet("name")->columnExists);

        $this->assertSame(
            "undefined",
            $description->columnGet("name")->default
        );

        $this->assertNull($description->columnGet("name")->comment);

        $this->assertTrue($description->columnGet("name")->notNull);

        $this->assertNull($description->columnGet("name")->uniqueIndex);

        $this->assertSame(
            "price",
            $description->columnGet("price")->column->getName()
        );

        $this->assertSame(
            null,
            $description->columnGet("price")->default
        );


        $Table = new Table("sub_table");
        $Table->columnCreate("parent")->addForeignKey("known_table.id");

        $description = QueryMakerMySQL::describeTable($MockPDO,$Table);


        $this->assertNotNull($description);

        $this->assertTrue($description->tableExists);

        $this->assertSame("int", $description->columnGet("parent")->type);

        $this->assertSame([
            'known_table.id' => 'sub_table_known_table_id_fk'
        ], $description->columnGet("parent")->foreignKeys);

        $this->assertSame(
            "example comment",
            $description->columnGet("parent")->comment
        );
    }


    public function testCreateTableQuery()
    {}


    public function testCreateUniqueIndexQuery()
    {}

    public function testRemoveUniqueIndexQuery()
    {}


    public function testCreateTableColumnQuery()
    {}

    public function testAlterTableColumnQuery()
    {}


    public function testCreateForeignKey()
    {}

    public function testRemoveForeignKey()
    {}


    public function testCompareType()
    {}

    public function testCompareComment()
    {}

}

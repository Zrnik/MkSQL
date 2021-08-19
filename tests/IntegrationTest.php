<?php declare(strict_types=1);

/*
 * Zrník.eu | MkSQL
 * User: Programátor
 * Date: 02.09.2020 13:52
 */

use Mock\BaseRepositoryAndBaseEntity\AuctionRepository;
use Mock\BaseRepositoryAndBaseEntity\Entities\Auction;
use Mock\BaseRepositoryAndBaseEntity\Entities\AuctionItem;
use Mock\BaseRepositoryAndBaseEntity\InvoiceRepository;
use Nette\Neon\Neon;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use Zrnik\MkSQL\Column;
use Zrnik\MkSQL\Enum\DriverType;
use Zrnik\MkSQL\Exceptions\MkSQLException;
use Zrnik\MkSQL\Updater;
use Zrnik\MkSQL\Utilities\Installable;

class IntegrationTest extends TestCase
{

    /**
     * @throws MkSQLException
     */
    public function testIntegration(): void
    {
        // MySQL Integration:
        $configFile = file_exists(__DIR__ . "/../.github/config/integrationTestDatabase.neon")
            ? __DIR__ . "/../.github/config/integrationTestDatabase.neon"
            : __DIR__ . "/../.github/config/integrationTestDatabase.neon.dist";

        $config = Neon::decode((string)file_get_contents($configFile));

        $MySQL_PDO = new PDO(
            $config["dsn"],
            $config["user"],
            $config["pass"]
        );

        $this->processTest($MySQL_PDO);

        // SQLite Integration:
        //$SQLite_PDO = new PDO("sqlite::memory:");
        // memory is enough, but for visual data:

        $tempDir = __DIR__ . '/../temp';
        if (!file_exists($tempDir)) {
            @mkdir($tempDir, 0777, true);
        }
        $SQLiteFile = $tempDir . '/mksql_test.sqlite';
        $SQLite_PDO = new PDO(sprintf('sqlite:%s', $SQLiteFile));


        $this->processTest($SQLite_PDO);
    }

    /**
     * @param PDO $pdo
     * @throws MkSQLException
     */
    private function processTest(PDO $pdo): void
    {
        $Updater = new Updater($pdo);
        $this->addToAssertionCount(1);

        echo PHP_EOL . "Testing \""
            . DriverType::getName($Updater->getDriverType()) .
            "\" Driver:" . PHP_EOL . "[";

        $this->assertNotNull($Updater->getDriverType());
        //echo "Processing Test with: " .  . PHP_EOL;

        $this->dropAll($pdo);
        $this->assertTrue($this->installDefault($Updater));
        $this->dot();

        // #####################################
        // ### SUB TESTS #######################
        // #####################################

        // #1. Changes in PrimaryKey name
        $this->subTestPrimaryKeyNameChange($Updater);

        // #2. Changed Type
        $this->subTestTypeChange($Updater);

        // #3. Changed NotNull
        $this->subTestNotNull($Updater);

        // #4. Changed Default Value
        $this->subTestDefaultValue($Updater);

        // #5. Changed Comment
        $this->subTestComment($Updater);

        // #6. Changes in ForeignKeys
        $this->subTestForeignKeys($Updater);

        // #7. Changes in UniqueIndexes
        $this->subTestUniqueIndexes($Updater);

        // #####################################
        // ### BaseRepoAndEntityTest: ##########
        // #####################################

        $this->subTestBaseRepoAndEntity($pdo);

        echo "]" . PHP_EOL . "Complete!";
    }

    private function dropAll(PDO $pdo): void
    {
        //Must be in reverse order because of foreign key constraints
        $tables = [
            "last_action",
            "sessions",
            "accounts",

            "last_action_mksql_tmp",
            "sessions_mksql_tmp",
            "accounts_mksql_tmp"

            //Temp tables created by SQLite
        ];

        foreach ($tables as $table_name) {
            $pdo->exec(
                sprintf(
                /** @lang */ "DROP TABLE IF EXISTS %s"
                    , $table_name
                )
            );
        }
    }

    /**
     * @param Updater $Updater
     * @return bool
     * @throws MkSQLException
     */
    private function installDefault(Updater $Updater): bool
    {
        //Table Accounts only with Login:
        $Accounts = $Updater->tableCreate("accounts")->setPrimaryKeyName("account_id");

        $Accounts->columnCreate("login", "varchar(60)")
            ->setUnique()
            ->setNotNull();

        //Table Sessions with foreign key account pointing to 'accounts.account_id'

        $Sessions = $Updater->tableCreate("sessions");

        $Sessions->columnCreate("token", "varchar(64)")
            ->setUnique()
            ->setNotNull();

        $Sessions->columnCreate("account")
            ->addForeignKey("accounts.account_id");

        $LastAction = $Updater->tableCreate("last_action");

        $this->assertNotNull($LastAction);

        $LastAction->columnCreate("session")
            ->addForeignKey("sessions.id");

        $LastAction->columnCreate("action_time");

        $TokenColumn = new Column("cloned_token", "varchar(100)");
        $Accounts->columnAdd(clone $TokenColumn);
        $Sessions->columnAdd(clone $TokenColumn);
        $LastAction->columnAdd(clone $TokenColumn);

        return $Updater->install();
    }

    /**
     * @param Updater $Updater
     * @throws MkSQLException
     */
    private function subTestPrimaryKeyNameChange(Updater $Updater): void
    {
        $tableAccounts = $Updater->tableGet("accounts");
        $this->assertNotNull($tableAccounts);


        $tableAccounts->setPrimaryKeyName("new_id");
        $this->assertTrue($Updater->install());
        $this->dot();

        $tableAccounts->setPrimaryKeyName("id");
        $this->assertTrue($Updater->install());
        $this->dot();
    }

    /**
     * @param Updater $Updater
     * @throws MkSQLException
     */
    private function subTestTypeChange(Updater $Updater): void
    {
        $lastActionTable = $Updater->tableGet("last_action");
        $this->assertNotNull($lastActionTable);

        $columnActionTime = $lastActionTable->columnGet("action_time");
        $this->assertNotNull($columnActionTime);

        $columnActionTime->setType("varchar(10)");
        $this->assertTrue($Updater->install());
        $this->dot();

        $columnActionTime->setType("char(15)");
        $this->assertTrue($Updater->install());
        $this->dot();

        $columnActionTime->setType("int");
        $this->assertTrue($Updater->install());
        $this->dot();

    }

    /**
     * @param Updater $Updater
     * @throws MkSQLException
     */
    private function subTestNotNull(Updater $Updater): void
    {
        $tableAccounts = $Updater->tableGet("accounts");

        $this->assertNotNull($tableAccounts);

        $columnLogin = $tableAccounts->columnGet("login");

        $this->assertNotNull($columnLogin);

        $columnLogin->setNotNull(false);
        $this->assertTrue($Updater->install());
        $this->dot();

        $columnLogin->setNotNull();
        $this->assertTrue($Updater->install());
        $this->dot();
    }

    /**
     * @param Updater $Updater
     * @throws MkSQLException
     */
    private function subTestDefaultValue(Updater $Updater): void
    {
        $lastActionTable = $Updater->tableGet("last_action");
        $this->assertNotNull($lastActionTable);

        $columnActionTime = $lastActionTable->columnGet("action_time");
        $this->assertNotNull($columnActionTime);

        $columnActionTime->setDefault(time());
        $this->assertTrue($Updater->install());
        $this->dot();

        $columnActionTime->setDefault(strtotime("-3 days"));
        $this->assertTrue($Updater->install());
        $this->dot();
    }

    /**
     * @param Updater $Updater
     * @throws MkSQLException
     */
    private function subTestComment(Updater $Updater): void
    {
        $lastActionTable = $Updater->tableGet("last_action");
        $this->assertNotNull($lastActionTable);

        $columnActionTime = $lastActionTable->columnGet("action_time");
        $this->assertNotNull($columnActionTime);

        $columnActionTime->setComment("A tested column");
        $this->assertTrue($Updater->install());
        $this->dot();

        $columnActionTime->setComment(); // null
        $this->assertTrue($Updater->install());
        $this->dot();

        $columnActionTime->setComment("Integration Tested Column");
        $this->assertTrue($Updater->install());
        $this->dot();
    }

    /**
     * @param Updater $Updater
     * @throws MkSQLException
     */
    private function subTestForeignKeys(Updater $Updater): void
    {
        $tableSessions = $Updater->tableGet("sessions");
        $this->assertNotNull($tableSessions);

        $tableSessionsColumnToken = $tableSessions->columnGet("token");
        $this->assertNotNull($tableSessionsColumnToken);

        $tableSessionsColumnToken->addForeignKey("accounts.login");
        $this->assertTrue($Updater->install());
        $this->dot();

        $tableSessionsColumnToken->dropForeignKey("accounts.login");
        $this->assertTrue($Updater->install());
        $this->dot();

    }

    /**
     * @param Updater $Updater
     * @throws MkSQLException
     */
    private function subTestUniqueIndexes(Updater $Updater): void
    {
        $lastActionTable = $Updater->tableGet("last_action");
        $this->assertNotNull($lastActionTable);

        $columnActionTime = $lastActionTable->columnGet("action_time");
        $this->assertNotNull($columnActionTime);


        $columnActionTime->setUnique();
        $this->assertTrue($Updater->install());
        $this->dot();
        $columnActionTime->setUnique(false);
        $this->assertTrue($Updater->install());
        $this->dot();
        $columnActionTime->setUnique();
        $this->assertTrue($Updater->install());
        $this->dot();
    }


    private function dot(): void
    {
        echo ".";
    }

    /**
     * @throws MkSQLException
     * @throws ReflectionException
     */
    private function subTestBaseRepoAndEntity(PDO $pdo): void
    {
        // Only checks Installation (Base Entity)
        Installable::uninstallAll($pdo);
        $invoiceRepo = new InvoiceRepository($pdo);

        // Checks Base Repository
        $auctionRepository = new AuctionRepository($pdo);

        foreach (array_reverse($auctionRepository->getTables()) as $table) {
            $pdo->exec(sprintf("DELETE FROM %s", $table->getName()));
        }

        $auction1 = Auction::create();
        $auction1->name = "First Auction";

        $auction1->auctionItems[0] = AuctionItem::create();
        $auction1->auctionItems[0]->name = "Auction Item One";

        $auction1->auctionItems[1] = AuctionItem::create();
        $auction1->auctionItems[1]->name = "Auction Item Two";

        $this->assertRowCountInTable($pdo, Auction::getTableName(), 0);
        $this->assertRowCountInTable($pdo, AuctionItem::getTableName(), 0);

        $auctionRepository->save($auction1);

        $this->assertRowCountInTable($pdo, Auction::getTableName(), 1);
        $this->assertRowCountInTable($pdo, AuctionItem::getTableName(), 2);

        $auction2 = Auction::create();
        $auction2->name = "Second Auction";

        $auction2->auctionItems[0] = AuctionItem::create();
        $auction2->auctionItems[0]->name = "Auction Item One";

        $auction2->auctionItems[1] = AuctionItem::create();
        $auction2->auctionItems[1]->name = "Auction Item Two";

        $this->assertRowCountInTable($pdo, Auction::getTableName(), 1);
        $this->assertRowCountInTable($pdo, AuctionItem::getTableName(), 2);

        $auctionRepository->save($auction2);

        $this->assertRowCountInTable($pdo, Auction::getTableName(), 2);
        $this->assertRowCountInTable($pdo, AuctionItem::getTableName(), 4);

        $auction2->auctionItems[0]->sold = true;

        $auctionRepository->save($auction2);

        $this->assertRowCountInTable($pdo, Auction::getTableName(), 2);
        $this->assertRowCountInTable($pdo, AuctionItem::getTableName(), 4);

        $auction2->auctionItems[1]->sold = true;

        $auctionRepository->save($auction2->auctionItems[1]);

        $this->assertRowCountInTable($pdo, Auction::getTableName(), 2);
        $this->assertRowCountInTable($pdo, AuctionItem::getTableName(), 4);

        /** @var Auction[] $auctionList1 */
        $auctionList1 = $auctionRepository->getAll(Auction::class);
        $this->assertCount(2, $auctionList1);

        /** @var Auction $fetchedAuction1 */
        $fetchedAuction1 = $auctionRepository->getResultByPrimaryKey(Auction::class, $auction1->id);

       /* dump($auction1);
        dumpe($fetchedAuction1);*/

        $this->assertEquals($auction1, $fetchedAuction1);
        $this->assertNotSame($auction1, $fetchedAuction1);

        /** @var Auction $fetchedAuction2 */
        $fetchedAuction2 = $auctionRepository->getResultByPrimaryKey(Auction::class, $auction2->id);

        $this->assertEquals($auction2, $fetchedAuction2);
        $this->assertNotSame($auction2, $fetchedAuction2);

        /** @var AuctionItem[] $auctionItemsOfAuction1 */
        $auctionItemsOfAuction1 = $auctionRepository->getResultsByKey(
            AuctionItem::class, "auction", $fetchedAuction1->id
        );

        $this->assertEquals($auction1->auctionItems[0], $auctionItemsOfAuction1[0]);
        $this->assertNotSame($auction1->auctionItems[0], $auctionItemsOfAuction1[0]);

        /** @var Auction $fetchedAuctionByName1 */
        $fetchedAuctionByName1 = $auctionRepository->getResultByKey(
            Auction::class, "name", "First Auction"
        );

        $this->assertEquals($auction1, $fetchedAuctionByName1);
        $this->assertNotSame($auction1, $fetchedAuctionByName1);

        /** @var Auction $fetchedAuctionByName2 */
        $fetchedAuctionByName2 = $auctionRepository->getResultByKey(
            Auction::class, "name", "Second Auction"
        );

        $this->assertEquals($auction2, $fetchedAuctionByName2);
        $this->assertNotSame($auction2, $fetchedAuctionByName2);
        $this->assertEquals($fetchedAuction2, $fetchedAuctionByName2);
        $this->assertNotSame($fetchedAuction2, $fetchedAuctionByName2);
        $this->assertEquals($fetchedAuction2, $auction2);
        $this->assertNotSame($fetchedAuction2, $auction2);
    }



    private function assertRowCountInTable(PDO $pdo, string $tableName, int $assertCount): void
    {
        $statement = $pdo->query("SELECT * FROM " . $tableName);

        if($statement === false) {
            throw new AssertionFailedError(
                "No result returned!"
            );
        }

        $fetch = $statement->fetchAll();

        if($fetch === false) {
            throw new AssertionFailedError(
                "No result returned!"
            );
        }

        $result = count($fetch);

        if ($result !== $assertCount) {
            throw new AssertionFailedError(
                sprintf(
                    "The number of rows in table '%s' does not match! Expected: %s Got: %s",
                    $tableName, $assertCount, $result
                )
            );
        }

        $this->addToAssertionCount(1);
    }
}

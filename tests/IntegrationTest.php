<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

use Brick\DateTime\LocalDateTime;
use Mock\BaseRepositoryAndBaseEntity\AuctionRepository;
use Mock\BaseRepositoryAndBaseEntity\Entities\Auction;
use Mock\BaseRepositoryAndBaseEntity\Entities\AuctionItem;
use Mock\BaseRepositoryAndBaseEntity\Entities\AutoHydrateAndCircularReference\ReferencingEntityOne;
use Mock\BaseRepositoryAndBaseEntity\HydrateTestEntities\MainEntity;
use Mock\BaseRepositoryAndBaseEntity\InvoiceRepository;
use Nette\Neon\Neon;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use Tracy\Debugger;
use Zrnik\MkSQL\Column;
use Zrnik\MkSQL\Enum\DriverType;
use Zrnik\MkSQL\Exceptions\CircularReferenceDetectedException;
use Zrnik\MkSQL\Exceptions\ColumnDefinitionExists;
use Zrnik\MkSQL\Exceptions\InvalidDriverException;
use Zrnik\MkSQL\Exceptions\MkSQLException;
use Zrnik\MkSQL\Exceptions\PrimaryKeyAutomaticException;
use Zrnik\MkSQL\Exceptions\TableDefinitionExists;
use Zrnik\MkSQL\Exceptions\UnexpectedCall;
use Zrnik\MkSQL\Tracy\Measure;
use Zrnik\MkSQL\Tracy\Panel;
use Zrnik\MkSQL\Updater;
use Zrnik\MkSQL\Utilities\Installable;
use Zrnik\PHPUnit\Exceptions;

class IntegrationTest extends TestCase
{
    use Exceptions;

    /**
     * @throws MkSQLException
     */
    public function testIntegration(): void
    {
        // Initialize TracyPanel so it's not marked as unused.
        Debugger::getBar()->addPanel(new Panel());


        // MySQL Integration:
        $configFile = file_exists(__DIR__ . '/../.github/config/integrationTestDatabase.neon')
            ? __DIR__ . '/../.github/config/integrationTestDatabase.neon'
            : __DIR__ . '/../.github/config/integrationTestDatabase.neon.dist';

        $config = Neon::decode((string)file_get_contents($configFile));

        $MySQL_PDO = new PDO(
            $config['dsn'],
            $config['user'],
            $config['pass']
        );

        $MySQL_VersionStatement = $MySQL_PDO->query('select version();');
        $MySQL_Version = $MySQL_VersionStatement === false ? '?' : $MySQL_VersionStatement->fetch()[0];

        $this->processTest($MySQL_PDO, $MySQL_Version);

        // SQLite Integration:
        //$SQLite_PDO = new PDO("sqlite::memory:");
        // memory is enough, but for visual data:

        $tempDir = __DIR__ . '/../temp';
        if (!file_exists($tempDir)) {
            @mkdir($tempDir, 0777, true);
        }
        $SQLiteFile = $tempDir . '/mksql_test.sqlite';
        $SQLite_PDO = new PDO(sprintf('sqlite:%s', $SQLiteFile));
        $SQLite_VersionStatement = $SQLite_PDO->query('select sqlite_version();');
        $SQLite_Version = $SQLite_VersionStatement === false ? '?' : $SQLite_VersionStatement->fetch()[0];
        $this->processTest($SQLite_PDO, $SQLite_Version);
    }

    /**
     * @param PDO $pdo
     * @param string $version
     */
    private function processTest(PDO $pdo, string $version = ''): void
    {
        $Updater = new Updater($pdo);
        $this->addToAssertionCount(1);

        echo PHP_EOL . 'Testing "'
            . DriverType::getName($Updater->getDriverType()) .
            '" Driver ['.$version.']:' . PHP_EOL . '[';

        static::assertNotNull($Updater->getDriverType());
        //echo "Processing Test with: " .  . PHP_EOL;

        $this->dropAll($pdo);
        static::assertTrue($this->installDefault($Updater));
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
        $this->subTestCircularReference($pdo);

        $this->subTestSingleInstallForMultipleDefinedTables($pdo);

        // #####################################
        // ### Hydrate updater test: ###########
        // #####################################

        $this->subTestHydrateUpdater($pdo);

        echo ']' . PHP_EOL . 'Complete!';
    }

    private function dropAll(PDO $pdo): void
    {
        //Must be in reverse order because of foreign key constraints
        $tables = [
            'last_action',
            'sessions',
            'accounts',

            'last_action_mksql_tmp',
            'sessions_mksql_tmp',
            'accounts_mksql_tmp'

            //Temp tables created by SQLite
        ];

        foreach ($tables as $table_name) {
            $pdo->exec(
                sprintf(
                /** @lang */ 'DROP TABLE IF EXISTS %s'
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
        $Accounts = $Updater->tableCreate('accounts')->setPrimaryKeyName('account_id');

        $Accounts->columnCreate('login', 'varchar(60)')
            ->setUnique()
            ->setNotNull();

        //Table Sessions with foreign key account pointing to 'accounts.account_id'

        $Sessions = $Updater->tableCreate('sessions');

        $Sessions->columnCreate('token', 'varchar(64)')
            ->setUnique()
            ->setNotNull();

        $Sessions->columnCreate('account')
            ->addForeignKey('accounts.account_id');

        $LastAction = $Updater->tableCreate('last_action');

        static::assertNotNull($LastAction);

        $LastAction->columnCreate('session')
            ->addForeignKey('sessions.id');

        $LastAction->columnCreate('action_time');

        $TokenColumn = new Column('cloned_token', 'varchar(100)');
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
        $tableAccounts = $Updater->tableGet('accounts');
        static::assertNotNull($tableAccounts);

        $tableAccounts->setPrimaryKeyName('new_id');
        static::assertTrue($Updater->install());
        $this->dot();

        $tableAccounts->setPrimaryKeyName('id');
        static::assertTrue($Updater->install());
        $this->dot();
    }

    /**
     * @param Updater $Updater
     * @throws MkSQLException
     */
    private function subTestTypeChange(Updater $Updater): void
    {
        $lastActionTable = $Updater->tableGet('last_action');
        static::assertNotNull($lastActionTable);

        $columnActionTime = $lastActionTable->columnGet('action_time');
        static::assertNotNull($columnActionTime);

        $columnActionTime->setType('varchar(10)');
        static::assertTrue($Updater->install());
        $this->dot();

        $columnActionTime->setType('char(15)');
        static::assertTrue($Updater->install());
        $this->dot();

        $columnActionTime->setType('int');
        static::assertTrue($Updater->install());
        $this->dot();

    }

    /**
     * @param Updater $Updater
     * @throws MkSQLException
     */
    private function subTestNotNull(Updater $Updater): void
    {
        $tableAccounts = $Updater->tableGet('accounts');

        static::assertNotNull($tableAccounts);

        $columnLogin = $tableAccounts->columnGet('login');

        static::assertNotNull($columnLogin);

        $columnLogin->setNotNull(false);
        static::assertTrue($Updater->install());
        $this->dot();

        $columnLogin->setNotNull();
        static::assertTrue($Updater->install());
        $this->dot();
    }

    /**
     * @param Updater $Updater
     * @throws MkSQLException
     */
    private function subTestDefaultValue(Updater $Updater): void
    {
        $lastActionTable = $Updater->tableGet('last_action');
        static::assertNotNull($lastActionTable);

        $columnActionTime = $lastActionTable->columnGet('action_time');
        static::assertNotNull($columnActionTime);

        $columnActionTime->setDefault(time());
        static::assertTrue($Updater->install());
        $this->dot();

        $columnActionTime->setDefault(strtotime('-3 days'));
        static::assertTrue($Updater->install());
        $this->dot();
    }

    /**
     * @param Updater $Updater
     * @throws MkSQLException
     */
    private function subTestComment(Updater $Updater): void
    {
        $lastActionTable = $Updater->tableGet('last_action');
        static::assertNotNull($lastActionTable);

        $columnActionTime = $lastActionTable->columnGet('action_time');
        static::assertNotNull($columnActionTime);

        $columnActionTime->setComment('A tested column');
        static::assertTrue($Updater->install());
        $this->dot();

        $columnActionTime->setComment(); // null
        static::assertTrue($Updater->install());
        $this->dot();

        $columnActionTime->setComment('Integration Tested Column');
        static::assertTrue($Updater->install());
        $this->dot();
    }

    /**
     * @param Updater $Updater
     * @throws MkSQLException
     */
    private function subTestForeignKeys(Updater $Updater): void
    {
        $tableSessions = $Updater->tableGet('sessions');
        static::assertNotNull($tableSessions);

        $tableSessionsColumnToken = $tableSessions->columnGet('token');
        static::assertNotNull($tableSessionsColumnToken);

        $tableSessionsColumnToken->addForeignKey('accounts.login');
        static::assertTrue($Updater->install());
        $this->dot();

        $tableSessionsColumnToken->dropForeignKey('accounts.login');
        static::assertTrue($Updater->install());
        $this->dot();

    }

    /**
     * @param Updater $Updater
     * @throws MkSQLException
     */
    private function subTestUniqueIndexes(Updater $Updater): void
    {
        $lastActionTable = $Updater->tableGet('last_action');
        static::assertNotNull($lastActionTable);

        $columnActionTime = $lastActionTable->columnGet('action_time');
        static::assertNotNull($columnActionTime);

        $columnActionTime->setUnique();
        static::assertTrue($Updater->install());
        $this->dot();
        $columnActionTime->setUnique(false);
        static::assertTrue($Updater->install());
        $this->dot();
        $columnActionTime->setUnique();
        static::assertTrue($Updater->install());
        $this->dot();
    }


    private function dot(): void
    {
        echo '.';
    }

    /**
     * @throws MkSQLException
     */
    private function subTestBaseRepoAndEntity(PDO $pdo): void
    {
        // Only checks Installation (Base Entity)
        Installable::uninstallAll($pdo);
        new InvoiceRepository($pdo); // Just for installation
        $this->addToAssertionCount(1);

        // Checks Base Repository
        $auctionRepository = new AuctionRepository($pdo);

        foreach (array_reverse($auctionRepository->getTables()) as $table) {
            /**
             * @noinspection SqlWithoutWhere
             * @noinspection UnknownInspectionInspection
             */
            $pdo->exec(sprintf('DELETE FROM %s', $table->getName()));
        }

        $auction1 = Auction::create();
        $auction1->name = 'First Auction';

        $auction1->auctionItems[0] = AuctionItem::create();
        $auction1->auctionItems[0]->name = 'Auction Item One';

        $auction1->auctionItems[1] = AuctionItem::create();
        $auction1->auctionItems[1]->name = 'Auction Item Two';

        $this->assertRowCountInTable($pdo, Auction::getTableName(), 0);
        $this->assertRowCountInTable($pdo, AuctionItem::getTableName(), 0);

        $auctionRepository->save($auction1);

        $this->assertRowCountInTable($pdo, Auction::getTableName(), 1);
        $this->assertRowCountInTable($pdo, AuctionItem::getTableName(), 2);

        $auction2 = Auction::create();
        $auction2->name = 'Second Auction';

        $auction2->auctionItems[0] = AuctionItem::create();
        $auction2->auctionItems[0]->name = 'Auction Item One';

        $auction2->auctionItems[1] = AuctionItem::create();
        $auction2->auctionItems[1]->name = 'Auction Item Two';

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
        static::assertCount(2, $auctionList1);
        $this->dot();

        /** @var Auction $fetchedAuction1 */
        $fetchedAuction1 = $auctionRepository->getResultByPrimaryKey(Auction::class, $auction1->id);

        static::assertEquals($auction1, $fetchedAuction1);
        static::assertNotSame($auction1, $fetchedAuction1);
        $this->dot();

        /** @var Auction $fetchedAuction2 */
        $fetchedAuction2 = $auctionRepository->getResultByPrimaryKey(Auction::class, $auction2->id);

        static::assertEquals($auction2, $fetchedAuction2);
        static::assertNotSame($auction2, $fetchedAuction2);
        $this->dot();

        /** @var AuctionItem[] $auctionItemsOfAuction1 */
        $auctionItemsOfAuction1 = $auctionRepository->getResultsByKey(
            AuctionItem::class, 'auction', $fetchedAuction1->id
        );

        static::assertEquals($auction1->auctionItems[0], $auctionItemsOfAuction1[0]);
        static::assertNotSame($auction1->auctionItems[0], $auctionItemsOfAuction1[0]);
        $this->dot();

        /** @var Auction $fetchedAuctionByName1 */
        $fetchedAuctionByName1 = $auctionRepository->getResultByKey(
            Auction::class, 'name', 'First Auction'
        );

        static::assertEquals($auction1, $fetchedAuctionByName1);
        static::assertNotSame($auction1, $fetchedAuctionByName1);
        $this->dot();

        /** @var Auction $fetchedAuctionByName2 */
        $fetchedAuctionByName2 = $auctionRepository->getResultByKey(
            Auction::class, 'name', 'Second Auction'
        );

        static::assertEquals($auction2, $fetchedAuctionByName2);
        static::assertNotSame($auction2, $fetchedAuctionByName2);
        static::assertEquals($fetchedAuction2, $fetchedAuctionByName2);
        static::assertNotSame($fetchedAuction2, $fetchedAuctionByName2);
        static::assertEquals($fetchedAuction2, $auction2);
        static::assertNotSame($fetchedAuction2, $auction2);
        $this->dot();

        /** @var AuctionItem[] $allAuctionItems */
        $allAuctionItems = $auctionRepository->getAll(AuctionItem::class);

        $allAuctionItems[0]->whenSold = LocalDateTime::of(
            2020, 9, 1, 12
        );

        $allAuctionItems[1]->whenSold = LocalDateTime::of(
            2020, 9, 1, 12
        );

        $allAuctionItems[2]->whenSold = LocalDateTime::of(
            2021, 9, 1, 12
        );

        $auctionRepository->save($allAuctionItems);

        static::assertSame(
            [
                'Auction Item One',
                'Auction Item Two',
            ],
            $auctionRepository->distinctValues(
                AuctionItem::class, 'name'
            )
        );
        $this->dot();

        $this->assertExceptionThrown(
            \Zrnik\MkSQL\Exceptions\InvalidArgumentException::class,
            function () use ($auctionRepository) {
                $auctionRepository->distinctValues(
                    AuctionItem::class, 'nonExisting'
                );
            }
        );
        $this->dot();

        static::assertEquals(
            [
                LocalDateTime::of(
                    2020, 9, 1, 12
                ),
                LocalDateTime::of(
                    2021, 9, 1, 12
                ),
                null,
            ],
            $auctionRepository->distinctValues(
                AuctionItem::class, 'whenSold'
            )
        );
        $this->dot();


    }

    private function assertRowCountInTable(PDO $pdo, string $tableName, int $assertCount): void
    {
        $statement = $pdo->query('SELECT * FROM ' . $tableName);

        if ($statement === false) {
            throw new AssertionFailedError(
                'No result returned!'
            );
        }

        $fetch = $statement->fetchAll();

        if ($fetch === false) {
            throw new AssertionFailedError(
                'No result returned!'
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
        $this->dot();
    }


    /**
     * @param PDO $pdo
     * @throws InvalidDriverException
     * @throws UnexpectedCall
     * @throws ColumnDefinitionExists
     * @throws \Zrnik\MkSQL\Exceptions\InvalidArgumentException
     * @throws PrimaryKeyAutomaticException
     * @throws TableDefinitionExists
     */
    public function subTestSingleInstallForMultipleDefinedTables(PDO $pdo): void
    {

        $updater = new Updater($pdo);

        $hello = $updater->tableCreate('hello');
        $hello->columnCreate('world', 'text');

        $updater->install();

        $startingCalls = Measure::structureTableList()['hello']['calls'];

        static::assertSame(
            $startingCalls, Measure::structureTableList()['hello']['calls']
        );
        $this->dot();


        $updater->install();

        static::assertSame(
            $startingCalls, Measure::structureTableList()['hello']['calls']
        );
        $this->dot();

        $hello->columnCreate('new_one');

        $updater->install();

        static::assertSame(
            $startingCalls + 1, Measure::structureTableList()['hello']['calls']
        );
        $this->dot();


    }

    private function subTestCircularReference(PDO $pdo): void
    {
        $updater = new Updater($pdo);

        $this->assertExceptionThrown(
            CircularReferenceDetectedException::class,
            function () use ($updater) {
                $updater->use(ReferencingEntityOne::class);
            }
        );

        $this->dot();
    }

    private function subTestHydrateUpdater(PDO $pdo): void
    {
        $updater = new Updater($pdo);
        $updater->use(MainEntity::class);

        $list = [];
        foreach($updater->tableList() as $table) {
            $list[] = $table->getName();
        }

        $neededTables = [
            'MainEntity',
            'FetchedEntity',
            'FetchedEntityFromSubEntity',
            'SubEntityFetchedEntity',
            'SubEntityOne',
            'SubEntityTwo'
        ];

        sort($list);
        sort($neededTables);

        static::assertSame(
            $neededTables, $list
        );
    }
}

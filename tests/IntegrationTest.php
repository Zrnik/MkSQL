<?php declare(strict_types=1);

/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Tests;

use Brick\DateTime\LocalDateTime;
use Exception;
use JsonException;
use Nette\Neon\Neon;
use PDO;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use Tests\Mock\BaseRepositoryAndBaseEntity\AuctionRepository;
use Tests\Mock\BaseRepositoryAndBaseEntity\Entities\Auction;
use Tests\Mock\BaseRepositoryAndBaseEntity\Entities\AuctionItem;
use Tests\Mock\BaseRepositoryAndBaseEntity\Entities\AutoHydrateAndCircularReference\ReferencingEntityOne;
use Tests\Mock\BaseRepositoryAndBaseEntity\Entities\EntityWithPrivateProperty\EntityWithPrivateProperty;
use Tests\Mock\BaseRepositoryAndBaseEntity\Entities\EntityWithPrivateProperty\PrivatePropertyRepository;
use Tests\Mock\BaseRepositoryAndBaseEntity\Entities\NullableForeignKeyNoError\NullableForeignKeyNoErrorRepository;
use Tests\Mock\BaseRepositoryAndBaseEntity\Entities\NullableForeignKeyNoError\SelfRepeating;
use Tests\Mock\BaseRepositoryAndBaseEntity\Entities\NullableForeignKeyNoError\SubClass;
use Tests\Mock\BaseRepositoryAndBaseEntity\Entities\NullableForeignKeyNoError\SuperClass;
use Tests\Mock\BaseRepositoryAndBaseEntity\HydrateTestEntities\FetchedEntity;
use Tests\Mock\BaseRepositoryAndBaseEntity\HydrateTestEntities\FetchedEntityFromSubEntity;
use Tests\Mock\BaseRepositoryAndBaseEntity\HydrateTestEntities\MainEntity;
use Tests\Mock\BaseRepositoryAndBaseEntity\HydrateTestEntities\SubEntityFetchedEntity;
use Tests\Mock\BaseRepositoryAndBaseEntity\HydrateTestEntities\SubEntityOne;
use Tests\Mock\BaseRepositoryAndBaseEntity\HydrateTestEntities\SubEntityTwo;
use Tests\Mock\Bugs\DoubleRetrieve\AccountEntry;
use Tests\Mock\Bugs\DoubleRetrieve\DoubleRetrieveRepository;
use Tests\Mock\Bugs\DoubleRetrieve\Person;
use Tests\Mock\Bugs\DoubleRetrieve\Reward;
use Tests\Mock\Bugs\TreeSave\RepeatingNode;
use Tests\Mock\Bugs\TreeSave\TreeSaveRepository;
use Tracy\Debugger;
use Zrnik\MkSQL\Column;
use Zrnik\MkSQL\Enum\DriverType;
use Zrnik\MkSQL\Exceptions\CircularReferenceDetectedException;
use Zrnik\MkSQL\Exceptions\ColumnDefinitionExists;
use Zrnik\MkSQL\Exceptions\InvalidArgumentException;
use Zrnik\MkSQL\Exceptions\InvalidDriverException;
use Zrnik\MkSQL\Exceptions\MkSQLException;
use Zrnik\MkSQL\Exceptions\PrimaryKeyAutomaticException;
use Zrnik\MkSQL\Exceptions\TableCreationFailedException;
use Zrnik\MkSQL\Exceptions\TableDefinitionExists;
use Zrnik\MkSQL\Exceptions\UnexpectedCall;
use Zrnik\MkSQL\Tracy\Measure;
use Zrnik\MkSQL\Tracy\Panel;
use Zrnik\MkSQL\Updater;
use Zrnik\MkSQL\Utilities\Installable;
use Zrnik\PHPUnit\Exceptions;
use function count;

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

        $this->runIntegrationTest($MySQL_PDO, $MySQL_Version);

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

        $this->runIntegrationTest($SQLite_PDO, $SQLite_Version);
    }

    /**
     * @param PDO $pdo
     * @param string $version
     * @throws JsonException
     * @throws Exception
     */
    private function processTest(PDO $pdo, string $version = ''): void
    {
        $Updater = new Updater($pdo);
        $this->addToAssertionCount(1);

        echo PHP_EOL . 'Testing "'
            . DriverType::getName($Updater->getDriverType()) .
            '" Driver [' . $version . ']:' . PHP_EOL . '[';

        static::assertNotNull($Updater->getDriverType());
        //echo "Processing Test with: " .  . PHP_EOL;

        $this->dropAll($pdo);

        $this->assertNoExceptionThrown(
            function () use ($Updater) {
                $this->installDefault($Updater);
            }
        );

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

        // #8. Errors
        $this->subTestErrors($Updater);


        // #####################################
        // ### Hydrate updater test: ###########
        // #####################################

        $this->subTestHydrateUpdater($pdo);

        // #####################################
        // ### BaseRepoAndEntityTest: ##########
        // #####################################

        $this->subTestBaseRepoAndEntity($pdo);
        $this->subTestCircularReference($pdo);

        // #####################################
        // ### Json Tests: #####################
        // #####################################

        $this->subTestJsonSerialize($pdo);

        // #####################################
        // ### Update entities: ################
        // #####################################

        $this->subTestDoNotUpdateUpToDateEntities($pdo);

        // #####################################
        // ### Bug Tests: ######################
        // #####################################
        $this->subTestDoubleRetrieve($pdo);

        $this->subTestSingleInstallForMultipleDefinedTables($pdo);

        $this->nullableForeignKeyNoError($pdo);

        $this->treeSavingFailed($pdo);

        $this->subTestAllowPrivatePropertiesInEntities($pdo);


        echo ']' . PHP_EOL . 'Complete!';


    }


    private function runIntegrationTest(PDO $pdo, string $version): void
    {
        $this->cleanup($pdo);

        //Yes, every test is run 2 times!
        $this->processTest($pdo, $version);
        $this->processTest($pdo, $version);

        $this->cleanup($pdo);
    }

    private function cleanup(PDO $pdo): void
    {
        $clearDatabase = [
            'last_action', 'sessions', 'accounts', 'hello',

            AuctionItem::getTableName(),
            Auction::getTableName(),

            SubEntityFetchedEntity::getTableName(),
            FetchedEntity::getTableName(),
            FetchedEntityFromSubEntity::getTableName(),
            MainEntity::getTableName(),
            SubEntityOne::getTableName(),
            SubEntityTwo::getTableName(),

            Reward::getTableName(),
            AccountEntry::getTableName(),
            Person::getTableName(),
        ];

        foreach ($clearDatabase as $tableName) {
            $pdo->exec(sprintf('DROP TABLE IF EXISTS %s', $tableName));
        }
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
     * @throws MkSQLException
     */
    private function installDefault(Updater $Updater): void
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

        $Updater->install();
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

        $this->assertNoExceptionThrown(function () use ($Updater) {
            $Updater->install();
        });

        $this->dot();

        $tableAccounts->setPrimaryKeyName('id');

        $this->assertNoExceptionThrown(function () use ($Updater) {
            $Updater->install();
        });

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
        $this->assertNoExceptionThrown(
            function () use ($Updater) {
                $Updater->install();
            }
        );
        $this->dot();

        $columnActionTime->setType('char(15)');
        $this->assertNoExceptionThrown(
            function () use ($Updater) {
                $Updater->install();
            }
        );
        $this->dot();

        $columnActionTime->setType('int');
        $this->assertNoExceptionThrown(
            function () use ($Updater) {
                $Updater->install();
            }
        );
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
        $this->assertNoExceptionThrown(
            function () use ($Updater) {
                $Updater->install();
            }
        );
        $this->dot();

        $columnLogin->setNotNull();
        $this->assertNoExceptionThrown(
            function () use ($Updater) {
                $Updater->install();
            }
        );
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
        $this->assertNoExceptionThrown(
            function () use ($Updater) {
                $Updater->install();
            }
        );
        $this->dot();

        $columnActionTime->setDefault(strtotime('-3 days'));
        $this->assertNoExceptionThrown(
            function () use ($Updater) {
                $Updater->install();
            }
        );
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
        $this->assertNoExceptionThrown(
            function () use ($Updater) {
                $Updater->install();
            }
        );
        $this->dot();

        $columnActionTime->setComment(); // null
        $this->assertNoExceptionThrown(
            function () use ($Updater) {
                $Updater->install();
            }
        );
        $this->dot();

        $columnActionTime->setComment('Integration Tested Column');
        $this->assertNoExceptionThrown(
            function () use ($Updater) {
                $Updater->install();
            }
        );
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
        $this->assertNoExceptionThrown(
            function () use ($Updater) {
                $Updater->install();
            }
        );
        $this->dot();

        $tableSessionsColumnToken->dropForeignKey('accounts.login');
        $this->assertNoExceptionThrown(
            function () use ($Updater) {
                $Updater->install();
            }
        );
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
        $this->assertNoExceptionThrown(
            function () use ($Updater) {
                $Updater->install();
            }
        );
        $this->dot();
        $columnActionTime->setUnique(false);
        $this->assertNoExceptionThrown(
            function () use ($Updater) {
                $Updater->install();
            }
        );
        $this->dot();
        $columnActionTime->setUnique();
        $this->assertNoExceptionThrown(
            function () use ($Updater) {
                $Updater->install();
            }
        );
        $this->dot();
    }


    private function subTestErrors(Updater $Updater): void
    {
        $tables = $Updater->tableList();

        // Create error item (column name cannot be 'unique' because its a keyword)
        $tables[array_keys($tables)[0]]->columnCreate(
            'unique', 'int(11)'
        );

        $this->assertExceptionThrown(
            TableCreationFailedException::class,
            function () use ($Updater) {
                $Updater->install();
            }
        );
        $this->dot();

        // We do not look for errors, we are getting exception!
        // static::assertCount(1, Measure::getErrors());

        // Remove the error from cache, so we can continue...
        foreach (Measure::getQueryModification() as $qm) {
            $qm->errorText = null;
        }

        foreach (Measure::getQueryDescription() as $qd) {
            $qd->errorText = null;
        }

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
        new AuctionRepository($pdo); // Just for installation
        $this->addToAssertionCount(1);
        Installable::uninstallAll($pdo);

        // Checks Base Repository
        $auctionRepository = new AuctionRepository($pdo);

        foreach (array_reverse($auctionRepository->getTables()) as $table) {
            /**
             * @noinspection SqlWithoutWhere
             * @noinspection UnknownInspectionInspection
             */
            $pdo->exec(sprintf('DELETE FROM %s', $table->getName()));
        }

        $this->assertRowCountInTable($pdo, Auction::getTableName(), 0);
        $this->assertRowCountInTable($pdo, AuctionItem::getTableName(), 0);

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

        $auctionRepository->save($auction1);

        /** @var Auction $fetchedAuction1 */
        $fetchedAuction1 = $auctionRepository->getResultByPrimaryKey(Auction::class, $auction1->id);

        //region I am not testing this, it does not matter...


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

        $fetchedAuctionItem = $auctionRepository->getResultByKey(
            AuctionItem::class, 'id', $auction1->auctionItems[0]->id
        );

        static::assertEquals($auction1->auctionItems[0], $fetchedAuctionItem);
        static::assertNotSame($auction1->auctionItems[0], $fetchedAuctionItem);

        /** @var AuctionItem[] $allAuctionItems */
        $allAuctionItems = $auctionRepository->getAll(AuctionItem::class);

        static::assertNull(
            $allAuctionItems[0]->auction?->auctionItems[0]->whenSold
        );

        $allAuctionItems[0]->whenSold = LocalDateTime::of(
            2020, 9, 1, 12
        );

        $allAuctionItems[1]->whenSold = LocalDateTime::of(
            2020, 9, 1, 12
        );

        $allAuctionItems[2]->whenSold = LocalDateTime::of(
            2021, 9, 1, 12
        );

        static::assertNotNull(
            $allAuctionItems[0]->auction?->auctionItems[0]->whenSold
        );

        static::assertSame(
            $allAuctionItems[0]->whenSold,
            $allAuctionItems[0]->auction->auctionItems[0]->whenSold,
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
            InvalidArgumentException::class,
            function () use ($auctionRepository) {
                $auctionRepository->distinctValues(
                    AuctionItem::class, 'nonExisting'
                );
            }
        );
        $this->dot();

        $distinctValues = $auctionRepository->distinctValues(
            AuctionItem::class, 'whenSold'
        );

        sort($distinctValues);

        static::assertEquals(
            [
                null,
                LocalDateTime::of(
                    2020, 9, 1, 12
                ),
                LocalDateTime::of(
                    2021, 9, 1, 12
                ),
            ],
            $distinctValues
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
     * @throws InvalidArgumentException
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
        $updater1 = new Updater($pdo);
        $updater1->use(MainEntity::class);
        $updater1->install();

        $updater2 = new Updater($pdo);
        $updater2->use(FetchedEntity::class);
        $updater1->install();

        $list1 = [];
        foreach ($updater1->tableList() as $table) {
            $list1[] = $table->getName();
        }

        $list2 = [];
        foreach ($updater2->tableList() as $table) {
            $list2[] = $table->getName();
        }

        $neededTables = [
            'MainEntity',
            'FetchedEntity',
            'FetchedEntityFromSubEntity',
            'SubEntityFetchedEntity',
            'SubEntityOne',
            'SubEntityTwo'
        ];

        sort($list1);
        sort($list2);
        sort($neededTables);

        static::assertSame(
            $neededTables, $list1
        );

        static::assertSame(
            $neededTables, $list2
        );
    }

    private function subTestDoubleRetrieve(PDO $pdo): void
    {
        $repository = new DoubleRetrieveRepository($pdo);

        //Clear Tables
        foreach (array_reverse($repository->getTables()) as $table) {
            /**
             * @noinspection SqlWithoutWhere
             * @noinspection UnknownInspectionInspection
             */
            $pdo->exec(sprintf('DELETE FROM %s', $table->getName()));
        }

        //region create data
        $_acct1 = Person::create();
        $_acct1->name = 'Account 1 - Winner';


        $_acct2 = Person::create();
        $_acct2->name = 'Account 2 - Reward Receiver';

        $_acct3 = Person::create();
        $_acct3->name = 'Account 3 - Winner';

        $_acct4 = Person::create();
        $_acct4->name = 'Account 4 - Reward Receiver';

        $_acct1Entries = [];
        $_acct3Entries = [];

        $entry1 = AccountEntry::create();
        $entry2 = AccountEntry::create();
        $_acct1Entries[] = $entry1;
        $_acct3Entries[] = $entry2;

        $entry1->amount = 100;
        $entry1->owner = $_acct1;
        $entry1->rewards = [];

        $entry2->amount = 100;
        $entry2->owner = $_acct3;
        $entry2->rewards = [];

        $reward1 = Reward::create();
        $entry1->rewards[] = $reward1;
        $reward1->receiver = $_acct2;
        $reward1->relatedEntry = $entry1;
        $reward1->rewardAmount = 10;

        $reward2 = Reward::create();
        $entry2->rewards[] = $reward2;
        $reward2->receiver = $_acct4;
        $reward2->relatedEntry = $entry2;
        $reward2->rewardAmount = 10;

        $_acct1->accountEntries = $_acct1Entries;
        $_acct2->accountEntries = [];
        $_acct3->accountEntries = $_acct3Entries;
        $_acct4->accountEntries = [];

        $accountList = [$_acct1, $_acct2, $_acct3, $_acct4];
        $repository->save($accountList);

        //endregion

        $retrieved = $repository->getAll(Person::class);

        /** @var Person $account1 */
        /** @var Person $account2 */
        [$account1, $account2] = $retrieved;

        $deepAccount2 = $account1->accountEntries[0]->rewards[0]->receiver;

        static::assertSame($account2->id, $deepAccount2->id);
    }

    /**
     * @throws JsonException
     */
    public function subTestJsonSerialize(PDO $pdo): void
    {

        $auctionRepository = new AuctionRepository($pdo);

        $auction = Auction::create();
        $auction->name = 'JSON Auction';

        $item1 = AuctionItem::create();
        $item1->auction = $auction;
        $item1->name = 'item1';

        $item2 = AuctionItem::create();
        $item2->auction = $auction;
        $item2->name = 'item2';

        $auctionRepository->save($auction);
        $auctionRepository->save($item1);
        $auctionRepository->save($item2);


        $retrieved = $auctionRepository->getResultByKey(Auction::class, 'id', $auction->id);

        $json = $this->assertNoExceptionThrown(function () use ($retrieved) {
            return json_encode($retrieved, JSON_THROW_ON_ERROR);
        });

        $expectedString = sprintf('
            {
                "id": %s,
                "name": "JSON Auction",
                "auctionItems": [
                    {
                        "id": %s,
                        "auction": %s,
                        "name": "item1",
                        "sold": 0,
                        "whenSold": null
                    },
                    {
                        "id": %s,
                        "auction": %s,
                        "name": "item2",
                        "sold": 0,
                        "whenSold": null
                    }
                ]
            }
        ', $auction->id, $item1->id, $auction->id, $item2->id, $auction->id
        );

        $expectedJson = json_encode(json_decode($expectedString, true, 512, JSON_THROW_ON_ERROR), JSON_THROW_ON_ERROR);

        static::assertSame($expectedJson, $json);
        static::assertEquals($expectedJson, $json);
    }

    private function subTestDoNotUpdateUpToDateEntities(PDO $pdo): void
    {
        $auctionRepository = new AuctionRepository($pdo);

        $requiredQueryCount = 0;
        self::assertEquals($requiredQueryCount, $auctionRepository->getExecutedQueryCount());

        $auction = Auction::create();
        $auction->name = 'Not Saved Auction';

        $auctionRepository->save($auction);

        $requiredQueryCount++; //Only Insert
        self::assertEquals($requiredQueryCount, $auctionRepository->getExecutedQueryCount());

        $auctionRepository->save($auction);

        // Should be skipped, as no change happened
        self::assertEquals($requiredQueryCount, $auctionRepository->getExecutedQueryCount());

        $auction->name = 'Saved Auction';
        $auctionRepository->save($auction);
        $requiredQueryCount++; // Update Query
        self::assertEquals($requiredQueryCount, $auctionRepository->getExecutedQueryCount());

        $ai1 = AuctionItem::create();
        $ai1->name = 'Not Saved Item One';
        $ai1->auction = $auction;

        $ai2 = AuctionItem::create();
        $ai2->name = 'Not Saved Item Two';
        $ai2->auction = $auction;

        $auction->auctionItems[] = $ai1;
        $auction->auctionItems[] = $ai2;

        $auctionRepository->save($auction);
        $requiredQueryCount++; // 2 Inserts in One Query!
        self::assertEquals($requiredQueryCount, $auctionRepository->getExecutedQueryCount());

        $auction->name = 'Saved Auction With Sub Auctions';
        $auctionRepository->save($ai1);
        $auctionRepository->save($ai2);
        $requiredQueryCount++; // Only one update (for Auction entity)
        self::assertEquals($requiredQueryCount, $auctionRepository->getExecutedQueryCount());

        $ai2->name = 'Saved Item Two';
        $auctionRepository->save($ai1);
        $requiredQueryCount++; // Should resolve all sub/sup entities and update item 2 when saving item 1
        self::assertEquals($requiredQueryCount, $auctionRepository->getExecutedQueryCount());

        $ai1->name = 'Auction Item 1';
        $ai2->name = 'Auction Item 2';

        $auctionRepository->save($auction);
        $requiredQueryCount += 2; // 2 updates of item names
        self::assertEquals($requiredQueryCount, $auctionRepository->getExecutedQueryCount());

        $auction->name .= 'add';
        $ai1->name .= 'add';
        $ai2->name .= 'add';

        $auctionRepository->save($auction);
        $auctionRepository->save($auction);
        $requiredQueryCount += 3; // 3 updates of names and skip

        $auction->name .= 'add';
        $ai1->name .= 'add';
        $ai2->name .= 'add';

        $auctionRepository->save($ai1);
        $auctionRepository->save($ai1);
        $requiredQueryCount += 3; // 3 updates of names and skip


        $auction->name .= 'add';
        $ai1->name .= 'add';
        $ai2->name .= 'add';

        $auctionRepository->save($ai2);
        $auctionRepository->save($ai2);
        $requiredQueryCount += 3; // 3 updates of names and skip

        self::assertEquals($requiredQueryCount, $auctionRepository->getExecutedQueryCount());
    }

    private function nullableForeignKeyNoError(PDO $pdo): void
    {
        $repository = new NullableForeignKeyNoErrorRepository($pdo);

        $this->assertNoExceptionThrown(static function () use ($repository) {
            $selfRepeating1 = SelfRepeating::create();
            $selfRepeating2 = SelfRepeating::create();
            $selfRepeating2->referrer = $selfRepeating1;
            $repository->save([$selfRepeating1, $selfRepeating2]);
        });

        $this->assertNoExceptionThrown(static function () use ($repository) {
            $superClass1 = SuperClass::create();
            $superClass2 = SuperClass::create();
            $subClass1 = SubClass::create();
            $superClass1->subClass = $subClass1;
            $repository->save([$superClass1, $superClass2]);
        });

    }

    /**
     * @throws Exception
     */
    private function treeSavingFailed(PDO $pdo): void
    {
        $repository = new TreeSaveRepository($pdo);
        $repository->dropAll();

        $headNode = RepeatingNode::create(['name' => 'Head Node']);
        $subNode1 = RepeatingNode::create(['name' => 'sub level 1', 'parent' => $headNode]);
        $subNode2 = RepeatingNode::create(['name' => 'sub level 3', 'parent' => $subNode1]);
        $subNode3 = RepeatingNode::create(['name' => 'sub level 4', 'parent' => $subNode2]);

        $nodeList = [$headNode, $subNode1, $subNode2, $subNode3];

        $repository->save($nodeList);

        $statement = $pdo->query('SELECT * FROM ' . RepeatingNode::getTableName());

        if ($statement === false) {
            throw new Exception('No result form database!');
        }

        /** @var mixed[] $saved */
        $saved = $statement->fetchAll(PDO::FETCH_ASSOC);

        static::assertNull($saved[0]['parent']);
        static::assertSame($headNode->id, (int)$saved[1]['parent']);
        static::assertSame($subNode1->id, (int)$saved[2]['parent']);
        static::assertSame($subNode2->id, (int)$saved[3]['parent']);
    }

    private function subTestAllowPrivatePropertiesInEntities(PDO $pdo): void
    {
        $repo = new PrivatePropertyRepository($pdo);

        /** @var EntityWithPrivateProperty $entity */
        $entity = EntityWithPrivateProperty::create();
        $entity->name = 'Hello World';

        $repo->save($entity);

        /** @var EntityWithPrivateProperty[] $entities */
        $entities = $repo->getAll(EntityWithPrivateProperty::class);

        static::assertSame($entities[count($entities) - 1]->id, $entity->id);

    }
}

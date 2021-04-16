<?php declare(strict_types=1);

/*
 * Zrník.eu | MkSQL
 * User: Programátor
 * Date: 02.09.2020 13:52
 */

use PHPUnit\Framework\TestCase;
use Zrnik\MkSQL\Column;
use Zrnik\MkSQL\Enum\DriverType;
use Zrnik\MkSQL\Exceptions\ColumnDefinitionExists;
use Zrnik\MkSQL\Exceptions\InvalidDriverException;
use Zrnik\MkSQL\Exceptions\PrimaryKeyAutomaticException;
use Zrnik\MkSQL\Exceptions\TableDefinitionExists;
use Zrnik\MkSQL\Updater;

class IntegrationTest extends TestCase
{

    /**
     * @throws ColumnDefinitionExists
     * @throws InvalidDriverException
     * @throws PrimaryKeyAutomaticException
     * @throws TableDefinitionExists
     */
    public function testIntegration(): void
    {
        // MySQL Integration:
        $MySQL_PDO = new PDO("mysql:dbname=mk_sql_test;host=127.0.0.1;port=3800", "root", "mk_sql_test");
        $this->processTest($MySQL_PDO);

        // SQLite Integration:
        $SQLite_PDO = new PDO("sqlite::memory:");
        // memory is enough, but for visual data:
        //$SQLite_PDO = new PDO('sqlite:mksql_test.sqlite');

        $this->processTest($SQLite_PDO);
    }

    /**
     * @param PDO $pdo
     * @throws ColumnDefinitionExists
     * @throws InvalidDriverException
     * @throws PrimaryKeyAutomaticException
     * @throws TableDefinitionExists
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
            $pdo->exec("DROP TABLE IF EXISTS " . $table_name . " ");
        }
    }

    /**
     * @param Updater $Updater
     * @return bool
     * @throws ColumnDefinitionExists
     * @throws InvalidDriverException
     * @throws PrimaryKeyAutomaticException
     * @throws TableDefinitionExists
     */
    private function installDefault(Updater $Updater): bool
    {
        //Table Accounts only with Login:
        $Accounts = $Updater->tableCreate("accounts")->setPrimaryKeyName("account_id");

        $Accounts->columnCreate("login", "varchar(60)")
            ->setUnique()
            ->setNotNull();

        //Table Sessions with foreign key account pointing to accounts.account_id

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
     * @throws InvalidDriverException
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
     * @throws InvalidDriverException
     */
    private function subTestTypeChange(Updater $Updater): void
    {
        $lastActionTable =  $Updater->tableGet("last_action");
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
     * @throws InvalidDriverException
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
     * @throws InvalidDriverException
     */
    private function subTestDefaultValue(Updater $Updater): void
    {
        $lastActionTable =  $Updater->tableGet("last_action");
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
     * @throws InvalidDriverException
     */
    private function subTestComment(Updater $Updater): void
    {
        $lastActionTable =  $Updater->tableGet("last_action");
        $this->assertNotNull($lastActionTable);

        $columnActionTime = $lastActionTable->columnGet("action_time");
        $this->assertNotNull($columnActionTime);

        $columnActionTime->setComment("A tested column");
        $this->assertTrue($Updater->install());
        $this->dot();

        $columnActionTime->setComment(null);
        $this->assertTrue($Updater->install());
        $this->dot();

        $columnActionTime->setComment("Integration Tested Column");
        $this->assertTrue($Updater->install());
        $this->dot();
    }

    /**
     * @param Updater $Updater
     * @throws InvalidDriverException
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
     * @throws InvalidDriverException
     */
    private function subTestUniqueIndexes(Updater $Updater): void
    {
        $lastActionTable =  $Updater->tableGet("last_action");
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

}

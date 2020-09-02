<?php declare(strict_types=1);

/*
 * Zrník.eu | MkSQL  
 * User: Programátor
 * Date: 02.09.2020 13:52
 */

use PHPUnit\Framework\TestCase;
use Zrny\MkSQL\Enum\DriverType;
use Zrny\MkSQL\Exceptions\ColumnDefinitionExists;
use Zrny\MkSQL\Exceptions\InvalidDriverException;
use Zrny\MkSQL\Exceptions\PrimaryKeyAutomaticException;
use Zrny\MkSQL\Exceptions\TableDefinitionExists;
use Zrny\MkSQL\Updater;

class IntegrationTest extends TestCase
{

    /**
     * @throws ColumnDefinitionExists
     * @throws InvalidDriverException
     * @throws PrimaryKeyAutomaticException
     * @throws TableDefinitionExists
     */
    public function testIntegration()
    {
        // MySQL Integration:
        // $MySQL_PDO = new PDO("mysql:dbname=mksql_test;host=127.0.0.1","travis","");

        // $this->processTest($MySQL_PDO);

        // SQLite Integration:
        $MySQL_PDO = new PDO("sqlite::memory:");
        // memory is enough, but for visual data:
        $MySQL_PDO = new PDO('sqlite:mksql_test.sqlite');

        $this->processTest($MySQL_PDO);
    }

    /**
     * @param PDO $pdo
     * @throws ColumnDefinitionExists
     * @throws InvalidDriverException
     * @throws PrimaryKeyAutomaticException
     * @throws TableDefinitionExists
     */
    private function processTest(PDO $pdo)
    {
        $Updater = new Updater($pdo);
        $this->addToAssertionCount(1);

        echo PHP_EOL."-- START ------------------------".PHP_EOL;

        $this->assertNotNull($Updater->getDriverType());
        echo "Processing Test with: ". DriverType::getName($Updater->getDriverType()).PHP_EOL;

        $this->dropAll($pdo);

        $this->assertTrue($this->installDefault($Updater));

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

        echo PHP_EOL."-- DONE -------------------------".PHP_EOL;
    }

    public function executeBeforeFirstTest()
    {
        die("EXECUTE BEFORE FIRST TEST");
    }

    private function dropAll(PDO $pdo) : void
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

        foreach($tables as $table_name)
        {
            $pdo->exec("DROP TABLE IF EXISTS ".$table_name." ");
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
    private function installDefault(Updater $Updater) : bool
    {
        //Table Accounts only with Login:
        $Accounts = $Updater->tableCreate("accounts");

        $Accounts->columnCreate("login","varchar(60)")
            ->setUnique()
            ->setNotNull();

        //Table Sessions with foreign key account pointing to accounts.id

        $Sessions = $Updater->tableCreate("sessions");

        $Sessions->columnCreate("token", "varchar(64)")
            ->setUnique()
            ->setNotNull()
        ;

        $Sessions->columnCreate("account")
            ->addForeignKey("accounts.id");

        $LastAction = $Updater->tableCreate("last_action");

        $LastAction->columnCreate("session")
            ->addForeignKey("sessions.id");


        $LastAction->columnCreate("action_time");

        return $Updater->install();
    }

    /**
     * @param Updater $Updater
     * @throws InvalidDriverException
     */
    private function subTestPrimaryKeyNameChange(Updater $Updater)
    {
        $Updater->tableGet("accounts")->setPrimaryKeyName("new_id");
        $this->assertTrue($Updater->install());

        $Updater->tableGet("accounts")->setPrimaryKeyName("id");
        $this->assertTrue($Updater->install());
    }

    private function subTestTypeChange(Updater $Updater)
    {
        $Updater->tableGet("last_action")->columnGet("action_time")->setType("varchar(10)");
        $this->assertTrue($Updater->install());

        $Updater->tableGet("last_action")->columnGet("action_time")->setType("char(15)");
        $this->assertTrue($Updater->install());

        $Updater->tableGet("last_action")->columnGet("action_time")->setType("int");
        $this->assertTrue($Updater->install());

    }

    private function subTestNotNull(Updater $Updater)
    {
        $Updater->tableGet("accounts")->columnGet("login")->setNotNull(false);
        $this->assertTrue($Updater->install());

        $Updater->tableGet("accounts")->columnGet("login")->setNotNull();
        $this->assertTrue($Updater->install());
    }

    private function subTestDefaultValue(Updater $Updater)
    {
        $Updater->tableGet("last_action")->columnGet("action_time")->setDefault(time());
        $this->assertTrue($Updater->install());

        $Updater->tableGet("last_action")->columnGet("action_time")->setDefault(strtotime("-3 days"));
        $this->assertTrue($Updater->install());
    }

    private function subTestComment(Updater $Updater)
    {
        $Updater->tableGet("last_action")->columnGet("action_time")->setComment("A tested column");
        $this->assertTrue($Updater->install());

        $Updater->tableGet("last_action")->columnGet("action_time")->setComment(null);
        $this->assertTrue($Updater->install());

        $Updater->tableGet("last_action")->columnGet("action_time")->setComment("Integration Tested Column");
        $this->assertTrue($Updater->install());
    }

    private function subTestForeignKeys(Updater $Updater)
    {
        $Updater->tableGet("sessions")->columnGet("token")->addForeignKey("accounts.login");
        $this->assertTrue($Updater->install());

        $Updater->tableGet("sessions")->columnGet("token")->dropForeignKey("accounts.login");
        $this->assertTrue($Updater->install());

    }

    private function subTestUniqueIndexes(Updater $Updater)
    {
        $Updater->tableGet("last_action")->columnGet("action_time")->setUnique();
        $this->assertTrue($Updater->install());
        $Updater->tableGet("last_action")->columnGet("action_time")->setUnique(false);
        $this->assertTrue($Updater->install());
        $Updater->tableGet("last_action")->columnGet("action_time")->setUnique();
        $this->assertTrue($Updater->install());
    }



}
<?php declare(strict_types=1);
/**
 * @generator PhpStorm
 * @author Štěpán Zrník <stepan@zrnik.eu>
 * @date 13.01.2021
 * @project MkSQL
 * @copyright (c) 2021 - Štěpán Zrník
 */

namespace Zrnik\MkSQL\Utilities;

use PDO;
use PDOException;
use Zrnik\MkSQL\Exceptions\MkSQLException;
use Zrnik\MkSQL\Updater;

abstract class Installable
{
    /**
     * Key = The installable class.
     * Value = Array of tables created
     *
     * @var array<string, array<string>>
     */
    private static array $_repositoriesInstalled = [];

    private PDO $pdo;

    /**
     * Installable constructor.
     * @param PDO $pdo
     * @throws MkSQLException
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->executeInstallation();
    }

    /**
     * Do not call '$updater->install()' in this method,
     * 'Installable' class will handle it!
     *
     * @param Updater $updater
     * @throws MkSQLException
     */
    abstract function install(Updater $updater): void;

    /**
     * @throws MkSQLException
     */
    private function executeInstallation(): void
    {
        $installed = array_key_exists(static::class, self::$_repositoriesInstalled);

        if ($installed)
            return;

        // Create Updated
        $updater = new Updater($this->pdo);
        $updater->installable = static::class;

        $this->install($updater);

        self::$_repositoriesInstalled[static::class] = [];
        foreach ($updater->tableList() as $table)
            self::$_repositoriesInstalled[static::class][] = $table->getName();

        // Process Updated
        $updater->installable = null;
        $updater->install();
    }

    public static function uninstallAll(PDO $pdo): void
    {
        foreach (self::$_repositoriesInstalled as $tables)
            foreach ($tables as $table) {
                try {
                    $pdo->query(sprintf("DELETE FROM %s", $table));
                } catch (PDOException) {
                    // Whatever...
                }
            }

        self::$_repositoriesInstalled = [];
    }

}
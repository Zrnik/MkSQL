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
use ReflectionException;
use Zrnik\MkSQL\Exceptions\MkSQLException;
use Zrnik\MkSQL\Repository\BaseRepository;
use Zrnik\MkSQL\Table;
use Zrnik\MkSQL\Updater;

abstract class Installable extends BaseRepository
{
    /**
     * Key = The installable class.
     * Value = Array of tables created
     *
     * @var array<string, array<string>>
     */
    private static array $_repositoriesInstalled = [];

    /**
     * @var Table[]
     */
    private array $tables = [];

    /**
     * Installable constructor.
     * @param PDO $pdo
     * @throws MkSQLException
     * @throws ReflectionException
     */
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->pdo = $this->getPdo();
        $this->executeInstallation();
    }

    /**
     * Do not call '$updater->install()' in this method,
     * 'Installable' class will handle it!
     *
     * @param Updater $updater
     * @throws MkSQLException
     * @throws ReflectionException
     */
    abstract protected function install(Updater $updater): void;

    /**
     * @throws MkSQLException
     * @throws ReflectionException
     */
    private function executeInstallation(): void
    {
        $installed = array_key_exists(static::class, self::$_repositoriesInstalled);

        if ($installed) {
            return;
        }

        // Create Updated
        $updater = new Updater($this->pdo);
        $updater->installable = static::class;

        $this->install($updater);

        self::$_repositoriesInstalled[static::class] = [];
        foreach ($updater->tableList() as $table){
            self::$_repositoriesInstalled[static::class][] = $table->getName();
        }

        // Process Updated
        $updater->installable = null;
        $updater->install();
        $this->tables = $updater->tableList();
    }

    public static function uninstallAll(PDO $pdo): void
    {
        foreach (self::$_repositoriesInstalled as $tables) {
            foreach ($tables as $table) {
                try {
                    $pdo->exec(sprintf("DELETE FROM %s", $table));
                } catch (PDOException) {
                    // Whatever...
                }
            }
        }
        self::$_repositoriesInstalled = [];
    }

    /**
     * @return Table[]
     */
    public function getTables(): array
    {
        return $this->tables;
    }

}

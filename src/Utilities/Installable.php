<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Zrnik\MkSQL\Utilities;

use JetBrains\PhpStorm\Pure;
use PDO;
use PDOException;
use Zrnik\MkSQL\Exceptions\MkSQLException;
use Zrnik\MkSQL\Repository\BaseRepository;
use Zrnik\MkSQL\Table;
use Zrnik\MkSQL\Updater;
use function array_key_exists;

abstract class Installable extends BaseRepository
{
    /**
     * Key = The installable class.
     * Value = Array of tables created
     *
     * @var array<string, array<string>>
     */
    private static array $_repositoriesInstalled = [];

    private Updater $updater;

    /**
     * Installable constructor.
     * @param PDO $pdo
     * @throws MkSQLException
     */
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->pdo = $this->getPdo();
        $this->updater = new Updater($this->pdo);
        $this->executeInstallation();
    }

    /**
     * Do not call '$updater->install()' in this method,
     * 'Installable' class will handle it!
     *
     * @param Updater $updater
     * @throws MkSQLException
     */
    abstract protected function install(Updater $updater): void;

    /**
     * @throws MkSQLException
     */
    private function executeInstallation(): void
    {
        $installed = array_key_exists(static::class, self::$_repositoriesInstalled);

        if ($installed) {
            return;
        }

        $this->updater->installable = static::class;

        $this->install($this->updater);

        self::$_repositoriesInstalled[static::class] = [];
        foreach ($this->updater->tableList() as $table) {
            self::$_repositoriesInstalled[static::class][] = $table->getName();
        }

        // Process Updated
        $this->updater->installable = null;
        $this->beforeInstallation();
        $this->updater->install();
        $this->afterInstallation();
    }

    public static function uninstallAll(PDO $pdo): void
    {
        foreach (self::$_repositoriesInstalled as $tables) {
            foreach ($tables as $table) {
                try {
                    /**
                     * @noinspection SqlWithoutWhere
                     * @noinspection UnknownInspectionInspection
                     */
                    $pdo->exec(sprintf('DELETE FROM %s', $table));
                } catch (PDOException) {
                    // Whatever...
                }
            }
        }

        self::$_repositoriesInstalled = [];
        Updater::$processedTableIndication = [];
    }

    /**
     * @return Table[]
     */
    #[Pure] public function getTables(): array
    {
        return $this->updater->tableList();
    }

    /** Hook fired before the 'install' function of the updater */
    protected function beforeInstallation(): void { }

    /** Hook fired after the 'install' function of the updater */
    protected function afterInstallation(): void { }

}

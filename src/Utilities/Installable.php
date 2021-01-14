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
use Zrnik\MkSQL\Exceptions\ColumnDefinitionExists;
use Zrnik\MkSQL\Exceptions\InvalidDriverException;
use Zrnik\MkSQL\Exceptions\PrimaryKeyAutomaticException;
use Zrnik\MkSQL\Exceptions\TableDefinitionExists;
use Zrnik\MkSQL\Updater;

abstract class Installable
{
    /**
     * @var string[]
     */
    private static array $_repositoriesInstalled = [];

    private PDO $pdo;

    /**
     * Installable constructor.
     * @param PDO $pdo
     * @throws ColumnDefinitionExists
     * @throws InvalidDriverException
     * @throws PrimaryKeyAutomaticException
     * @throws TableDefinitionExists
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
     * @throws InvalidDriverException
     * @throws TableDefinitionExists
     * @throws ColumnDefinitionExists
     * @throws PrimaryKeyAutomaticException
     */
    abstract function install(Updater $updater): void;



    /**
     * @throws ColumnDefinitionExists
     * @throws InvalidDriverException
     * @throws PrimaryKeyAutomaticException
     * @throws TableDefinitionExists
     */
    private function executeInstallation(): void
    {

        $installed = in_array(static::class, self::$_repositoriesInstalled);

        if($installed)
            return;

        self::$_repositoriesInstalled[] = static::class;

        $updater = new Updater($this->pdo);
        $updater->installable = static::class;
        $this->install($updater);
        $updater->installable = null;
        $updater->install();
    }

}
<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Zrnik\MkSQL\Queries;

use PDO;
use PDOException;
use Zrnik\MkSQL\Column;
use Zrnik\MkSQL\Enum\DriverType;
use Zrnik\MkSQL\Table;
use Zrnik\MkSQL\Updater;

/**
 * Class Query
 * @package Zrnik\MkSQL\Queries
 */
class Query
{

    //region Settings Properties
    /**
     * @var bool
     */
    public bool $executed = false;
    /**
     * @var string|null
     */
    public ?string $errorText = null;
    /**
     * @var string
     */
    private string $Query = '';
    /**
     * @var array<mixed>
     */
    private array $Parameters = [];
    /**
     * @var Table
     */
    private Table $referencedTable;
    //endregion

    //region Result Properties
    /**
     * @var Column|null
     */
    private ?Column $referencedColumn;
    /**
     * @var string
     */
    private string $reason = '';
    //endregion

    public float $speed = 0;

    /**
     * @var int|null
     * @see DriverType
     */
    private ?int $driver = null;

    /**
     * Query constructor.
     * @param Table $table
     * @param Column|null $column
     */
    public function __construct(Table $table, ?Column $column)
    {
        $this->referencedTable = $table;
        $this->referencedColumn = $column;
    }

    public function getDriver() : ?int
    {
        return $this->driver;
    }

    /**
     * @param PDO $pdo
     * @param Updater $updater
     * @return bool
     */
    public function execute(PDO $pdo, Updater $updater): bool
    {
        $this->driver = $updater->getDriverType();
        $this->executed = true;
        return $pdo->prepare($this->Query)->execute($this->Parameters);
    }

    //region Sql, Reason & Result

    /**
     * @return string
     */
    public function getQuery(): string
    {
        return $this->Query;
    }

    /**
     * @param string $sql
     * @return $this
     */
    public function setQuery(string $sql): Query
    {
        $this->Query = $sql;
        return $this;
    }

    /**
     * @return string
     */
    public function getReason(): string
    {
        return $this->reason;
    }

    /**
     * @param string $reason
     * @return $this
     */
    public function setReason(string $reason = ''): Query
    {
        $this->reason = $reason;
        return $this;
    }

    /**
     * @param PDOException $pdoException
     */
    public function setError(PDOException $pdoException): void
    {
        $this->errorText = $pdoException->getMessage();
    }

    //endregion

    //region Parameters

    /**
     * @param mixed $value
     * @return $this
     */
    public function paramAdd(mixed $value): Query
    {
        $this->Parameters[] = $value;
        return $this;
    }

    /**
     * @return array<mixed>
     */
    public function params(): array
    {
        return $this->Parameters;
    }

    //endregion

    //region Table and Column

    /**
     * @return Table
     */
    public function getTable(): Table
    {
        return $this->referencedTable;
    }

    /**
     * @return Column|null
     */
    public function getColumn(): ?Column
    {
        return $this->referencedColumn;
    }

    //endregion


}

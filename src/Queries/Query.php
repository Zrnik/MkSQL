<?php
/*
 * Zrník.eu | MkSQL
 * User: Programátor
 * Date: 06.08.2020 7:41
 */

namespace Zrny\MkSQL\Queries;

use PDO;
use PDOException;
use Zrny\MkSQL\Column;
use Zrny\MkSQL\Table;

class Query
{

    //region Settings Properties
    /**
     * @var string
     */
    private string $Query = '';
    /**
     * @var array
     */
    private array $Parameters = [];
    /**
     * @var Table
     */
    private Table $referencedTable;
    /**
     * @var Column|null
     */
    private ?Column $referencedColumn;
    /**
     * @var string
     */
    private string $reason = '';
    //endregion

    //region Result Properties
    /**
     * @var bool
     */
    public bool $executed = false;

    /**
     * @var string|null
     */
    public ?string $errorText = null;
    //endregion

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

    /**
     * @param PDO $pdo
     * @return bool
     */
    public function execute(PDO $pdo) : bool
    {
        $this->executed = true;
        return $pdo->prepare($this->Query)->execute($this->Parameters);
    }

    //region Sql, Reason & Result

    /**
     * @param string $sql
     * @return $this
     */
    public function setQuery(string $sql) : Query
    {
        $this->Query = $sql;
        return $this;
    }

    /**
     * @return string
     */
    public function getQuery() : string
    {
        return $this->Query;
    }

    /**
     * @param string $reason
     * @return $this
     */
    public function setReason(string $reason) : Query
    {
        $this->reason = $reason;
        return $this;
    }

    /**
     * @return string
     */
    public function getReason() : string
    {
        return $this->reason;
    }

    /**
     * @param PDOException $pdoException
     */
    public function setError(PDOException $pdoException) : void
    {
        $this->errorText = $pdoException->getMessage();
    }

    //endregion

    //region Parameters

    /**
     * @param string $value
     * @return $this
     */
    public function paramAdd(string $value) : Query
    {
        $this->Parameters[] = $value;
        return $this;
    }

    /**
     * @return array
     */
    public function params() : array
    {
        return $this->Parameters;
    }

    //endregion

    //region Table and Column

    /**
     * @return Table
     */
    public function getTable() : Table
    {
        return $this->referencedTable;
    }

    /**
     * @return Column|null
     */
    public function getColumn() : ?Column
    {
        return $this->referencedColumn;
    }

    //endregion

}

<?php
/*
 * Zrník.eu | MkSQL  
 * User: Programátor
 * Date: 06.08.2020 7:41
 */


namespace Zrny\MkSQL\Queries;


use Zrny\MkSQL\Column;
use Zrny\MkSQL\Table;

class Query
{

    /**
     * @var string
     */
    public $sql;
    /**
     * @var string
     */
    public $reason;

    /**
     * @var Table
     */
    public $table;
    /**
     * @var ?Column
     */
    public $column;
    /**
     * @var bool
     */
    public $executed;
    /**
     * @var bool
     */
    public $rolledBack;


    /**
     * Query constructor.
     * @param Table $table
     * @param ?Column $column
     * @param string $sql
     * @param string $reason
     */
    public function __construct(Table $table, ?Column $column, string $sql, string $reason)
    {
        $this->sql = $sql;
        $this->reason = $reason;
        $this->table = $table;
        $this->column = $column;
    }

    public function setExecuted(bool $executed)
    {
        $this->executed = $executed;
    }

    public function setRolledBack(bool $rolledBack)
    {
        bdump($this->reason);
        bdump($this->sql);
        $this->rolledBack = $rolledBack;
    }
}
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
     */
    public function execute(PDO $pdo) : void
    {
        $this->executed = true;
        //TODO: Execute Query

        //Tohle tu je kvuli PhpStormu aby neprudil...

        if($pdo->inTransaction())
            return;
        $pdo->prepare($this->Query);
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
     * @param string $key
     * @param string $value
     * @return $this
     */
    public function paramAdd(string $key, string $value) : Query
    {
        $this->Parameters[$key] = $value;
        return $this;
    }

    /**
     * @param array $param_kv
     * @return $this
     */
    public function paramsAdd(array $param_kv) : Query
    {
        foreach($param_kv as $key => $value)
            $this->paramAdd($key, $value); //This will ensure they are both strings.

        return $this;
    }

    /**
     * *Crying in YAGNI*
     * @param $key
     * @return Query
     */
    public function paramRemove($key) : Query
    {
        if(isset($this->Parameters[$key]))
            unset($this->Parameters[$key]);

        return $this;
    }

    /**
     * @return array
     */
    public function paramList() : array
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

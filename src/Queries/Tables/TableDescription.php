<?php
/*
 * Zrník.eu | MkSQL  
 * User: Programátor
 * Date: 06.08.2020 7:55
 */


namespace Zrny\MkSQL\Queries\Tables;


use Zrny\MkSQL\Queries\Makers\IQueryMaker;

class TableDescription
{

    /**
     * @var IQueryMaker
     */
    public $queryMakerClass = '';


    /**
     * @var bool
     */
    public $tableExists = false;

    /**
     * @var ColumnDescription[]
     */
    public $columns = [];
    /**
     * @var \Zrny\MkSQL\Table
     */
    public $table;


    /**
     * @param string $getName
     * @return ColumnDescription|null
     */
    public function column(string $getName) : ?ColumnDescription
    {
        $desc = null;

        foreach($this->columns as $column)
            if($column->column->getName() === $getName)
                return $column;

        return null;
    }


}
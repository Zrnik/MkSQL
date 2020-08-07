<?php
/*
 * Zrník.eu | MkSQL  
 * User: Programátor
 * Date: 06.08.2020 7:55
 */


namespace Zrny\MkSQL\Queries\Tables;

class ColumnDescription
{
    public $columnExists = false;
    /**
     * @var \Zrny\MkSQL\Column
     */
    public $column;
    /**
     * @var \Zrny\MkSQL\Table
     */
    public $table;

    /**
     * @var bool
     */
    public $notNull;
    /**
     * @var string|null
     */
    public $comment;
    /**
     * @var string|null
     */
    public $uniqueIndex;
    /**
     * @var string[]
     */
    public $foreignKeys = [];
    /**
     * @var string|null
     */
    public $default;
    /**
     * @var string
     */
    public $type;


}
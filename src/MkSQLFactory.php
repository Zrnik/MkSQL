<?php
/*
 * Zrník.eu | MkSQL  
 * User: Programátor
 * Date: 17.08.2020 16:17
 */


namespace Zrny\MkSQL;


use Nette\Database\Connection;

class MkSQLFactory
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function create()
    {
        return (new Updater())->setConnection($this->connection);
    }
}
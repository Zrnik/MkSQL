<?php declare(strict_types=1);

namespace Mock;

use PDOException;

class PDO extends \PDO
{
    /**
     * @var mixed
     */
    private $_expectedQuery = null;
    /**
     * @var mixed
     */
    private $_expectParams = null;
    /**
     * @var mixed
     */
    private $_mockResults = null;

    public function __construct()
    {
        parent::__construct("sqlite::memory:", null, null, null);
    }

    public function getAttribute($attribute)
    {
        if ($attribute === PDO::ATTR_DRIVER_NAME) {
            return "mysql";
        }
        return parent::getAttribute($attribute);
    }

    public function expectQuery(string $query)
    {
        $this->_expectedQuery = $query;
    }

    public function expectParams(array $array)
    {
        $this->_expectParams = $array;
    }

    public function mockResult($array)
    {
        $this->_mockResults = $array;
    }

    /**
     * @param mixed $statement
     * @param null $options
     * @return bool|\PDOStatement
     */
    public function prepare($statement, $options = NULL)
    {
        if ($this->_expectedQuery !== null && $statement !== $this->_expectedQuery)
            throw new PDOException("Mock PDO expected statement '" . $this->_expectedQuery . "' but got '" . $statement . "'!");

        $pdoStatement = new PDOStatement($this->_expectParams);

        if ($this->_mockResults !== null && isset($this->_mockResults[$statement])) {
            $pdoStatement->prepareResult($this->_mockResults[$statement]);
        }

        return $pdoStatement;
    }
}

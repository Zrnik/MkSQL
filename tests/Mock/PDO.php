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

    public function expectQuery(string $query): void
    {
        $this->_expectedQuery = $query;
    }

    /**
     * @param array<mixed> $array
     */
    public function expectParams(array $array): void
    {
        $this->_expectParams = $array;
    }

    /**
     * @param array<mixed> $array
     */
    public function mockResult(array $array): void
    {
        $this->_mockResults = $array;
    }

    /**
     * @param mixed $query
     * @param array<mixed>|null $options
     * @return PDOStatement<mixed>
     */
    public function prepare(mixed $query, array $options = NULL): PDOStatement
    {

        if ($this->_expectedQuery !== null && $query !== $this->_expectedQuery) {
            throw new PDOException("Mock PDO expected statement '" . $this->_expectedQuery . "' but got '" . $query . "'!");
        }

        $pdoStatement = new PDOStatement($this->_expectParams);

        if ($this->_mockResults !== null && isset($this->_mockResults[$query])) {
            $pdoStatement->prepareResult($this->_mockResults[$query]);
        }

        return $pdoStatement;
    }
}

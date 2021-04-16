<?php declare(strict_types=1);
/*
 * Zrník.eu | MkSQL
 * User: Programátor
 * Date: 01.09.2020 10:55
 */


namespace Mock;


use PDO;
use PDOException;
use Throwable;

class PDOStatement extends \PDOStatement
{
    /**
     * @var array<mixed>|null
     */
    private ?array $_expectedParams;
    /**
     * @var mixed
     */
    private $_mockResult = null;

    /**
     * PDOStatement constructor.
     * @param null $expectedParams
     */
    public function __construct(mixed $expectedParams = null)
    {
        $this->_expectedParams = $expectedParams;
    }


    public function prepareResult(mixed $result): void
    {
        $this->_mockResult = $result;
    }

    /**
     * @param int $mode
     * @param mixed ...$args
     * @return mixed
     * @throws Throwable
     */
    public function fetchAll($mode = PDO::FETCH_BOTH, ...$args): mixed
    {
        return $this->fetch();
    }

    /**
     * @param int|null $mode
     * @param int $cursorOrientation
     * @param int $cursorOffset
     * @return mixed
     * @throws Throwable
     */
    public function fetch(int $mode = null, $cursorOrientation = PDO::FETCH_ORI_NEXT, $cursorOffset = 0): mixed
    {
        if ($this->_mockResult !== null) {
            if (is_object($this->_mockResult) && $this->_mockResult instanceof Throwable) {
                //echo PHP_EOL."Throwable Mock".PHP_EOL;
                throw $this->_mockResult;
            }

            //echo PHP_EOL."Triggered Mock".PHP_EOL;
            return $this->_mockResult;
        }

        return null;
    }

    /**
     * @param array<mixed>|null $params
     * @return bool
     */
    public function execute(?array $params = null): bool
    {
        if ($this->_expectedParams !== null) {
            foreach ($this->_expectedParams as $ExpectedIndex => $ExpectedParam) {
                if (
                    !isset($params[$ExpectedIndex])
                    ||
                    $params[$ExpectedIndex] != $ExpectedParam
                )
                    throw new PDOException(
                        "Mock PDOStatement expected '" . $ExpectedIndex . "' 
                    at index '" . $ExpectedParam . "' but got
                     '" . ($params[$ExpectedIndex] ?? strval(null)) . "'."
                    );
            }
        }
        return true;
    }
}

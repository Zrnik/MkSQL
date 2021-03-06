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
     * @var array|null
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
    public function __construct($expectedParams = null)
    {
        $this->_expectedParams = $expectedParams;
    }

    public function prepareResult($result)
    {
        $this->_mockResult = $result;
    }


    public function fetchAll($mode = PDO::FETCH_BOTH, ...$args)
    {
        return $this->fetch();
    }

    /**
     * @param null $fetch_style
     * @param int $cursor_orientation
     * @param int $cursor_offset
     * @return mixed|null
     * @throws Throwable
     */
    public function fetch($fetch_style = null, $cursor_orientation = PDO::FETCH_ORI_NEXT, $cursor_offset = 0): mixed
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

    public function execute($input_parameters = null)
    {
        if ($this->_expectedParams !== null) {
            foreach ($this->_expectedParams as $ExpectedIndex => $ExpectedParam) {
                if (
                    !isset($input_parameters[$ExpectedIndex])
                    ||
                    $input_parameters[$ExpectedIndex] != $ExpectedParam
                )
                    throw new PDOException(
                        "Mock PDOStatement expected '" . $ExpectedIndex . "' 
                    at index '" . $ExpectedParam . "' but got
                     '" . ($input_parameters[$ExpectedIndex] ?? strval(null)) . "'."
                    );
            }
        }
        return true;
    }
}

<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Tests\Mock;

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
    private mixed $_mockResult;

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
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function fetchAll($mode = PDO::FETCH_BOTH, ...$args): mixed
    {
        return $this->fetch();
    }

    /**
     * @param int $mode
     * @param int $cursorOrientation
     * @param int $cursorOffset
     * @return mixed
     * @throws Throwable
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function fetch($mode = PDO::FETCH_BOTH, $cursorOrientation = PDO::FETCH_ORI_NEXT, $cursorOffset = 0): mixed
    {
        if ($this->_mockResult !== null) {
            if ($this->_mockResult instanceof Throwable) {
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
     * @noinspection GrazieInspection
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function execute(?array $params = null): bool
    {
        if ($this->_expectedParams !== null) {
            foreach ($this->_expectedParams as $ExpectedIndex => $ExpectedParam) {
                if (
                    !isset($params[$ExpectedIndex])
                    ||
                    $params[$ExpectedIndex] !== $ExpectedParam
                ) {
                    throw new PDOException(
                    // This is kind of fucked up lol...
                        sprintf(
                            "Mock PDOStatement expected '%s' at index '%s', but got '%s' instead.",
                            $ExpectedIndex, $ExpectedParam, ($params[$ExpectedIndex] ?? 'null')
                        )
                    );
                }
            }
        }
        return true;
    }
}

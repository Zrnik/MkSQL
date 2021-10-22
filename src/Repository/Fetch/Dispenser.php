<?php declare(strict_types=1);

namespace Zrnik\MkSQL\Repository\Fetch;

use PDO;
use Zrnik\MkSQL\Exceptions\MkSQLException;
use Zrnik\MkSQL\Repository\BaseEntity;

class Dispenser
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param class-string<BaseEntity> $baseEntityClassString
     * @param ?string $propertyName
     * @param array<mixed> $values
     * @return BaseEntity[]
     */
    public function getResultsByKeys(
        string  $baseEntityClassString,
        ?string $propertyName = null, array $values = []
    ): array
    {
        $resultCompiler = new ResultService();

        $sql = FetchQuery::create($baseEntityClassString, $propertyName, $values);
        $resultCompiler->addRows($baseEntityClassString, $sql->fetchAll($this->pdo));

        $killSwitch = 150;
        while (!$resultCompiler->complete()) {
            $completionData = $resultCompiler->completionData();
            foreach ($completionData as $completionQuery) {
                $sql = FetchQuery::create($completionQuery->baseEntityClassName, $completionQuery->columnName, $completionQuery->values);
                $resultCompiler->addRows($completionQuery->baseEntityClassName, $sql->fetchAll($this->pdo));
            }

            if ($killSwitch <= 0) {
                throw new MkSQLException('Stack Overflow!');
            } else {
                $killSwitch--;
            }
        }

        $resultCompiler->linkEntities();

        return $resultCompiler->getEntities($baseEntityClassString, $propertyName, $values);
    }
}

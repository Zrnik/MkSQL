<?php declare(strict_types=1);

namespace Zrnik\MkSQL\Repository\Fetch;

use JetBrains\PhpStorm\Pure;
use Zrnik\MkSQL\Repository\BaseEntity;
use function count;

class CompletionKeyValues
{
    /**
     * @param class-string<BaseEntity> $baseEntityClassName
     * @param string $columnName
     * @param mixed[] $values
     */
    final private function __construct(
        public string $baseEntityClassName,
        public string $columnName,
        public array  $values,
    )
    {

    }

    /**
     * @param class-string<BaseEntity> $baseEntityClassName
     * @param string $columnName
     * @param mixed[] $values
     * @return CompletionKeyValues
     */
    #[Pure] public static function create(
        string $baseEntityClassName,
        string $columnName,
        array  $values,
    ): CompletionKeyValues
    {
        return new CompletionKeyValues($baseEntityClassName, $columnName, $values);
    }

    public function hasAnyValues(): bool
    {
        return count($this->values) > 0;
    }

}
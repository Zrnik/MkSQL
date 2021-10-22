<?php declare(strict_types=1);

namespace Zrnik\MkSQL\Repository\Fetch;

use JetBrains\PhpStorm\Pure;
use Zrnik\MkSQL\Repository\BaseEntity;
use function in_array;

class CompletionBuilder
{
    /** @var CompletionKeyValues[] */
    private array $storage = [];

    public function __construct()
    {
    }

    /**
     * @return CompletionKeyValues[]
     */
    #[Pure] public function getCompletionData(): array
    {
        $result = [];

        foreach ($this->storage as $completionKeyValues) {
            if ($completionKeyValues->hasAnyValues()) {
                $result[] = $completionKeyValues;
            }
        }

        return $result;
    }

    /**
     * @param class-string<BaseEntity> $className
     * @param string $columnName
     * @param mixed $value
     */
    public function add(string $className, string $columnName, mixed $value): void
    {
        $completionClass = $this->classOf($className, $columnName);
        if ($value !== null && !in_array($value, $completionClass->values, true)) {
            $completionClass->values[] = $value;
        }
    }

    /**
     * @param class-string<BaseEntity> $className
     * @param string $columnName
     * @return CompletionKeyValues
     */
    private function classOf(string $className, string $columnName): CompletionKeyValues
    {
        foreach ($this->storage as $existingCompletionKeyValues) {
            if (
                $existingCompletionKeyValues->baseEntityClassName === $className
                && $existingCompletionKeyValues->columnName === $columnName
            ) {
                return $existingCompletionKeyValues;
            }
        }

        $newCompletionKeyValues = CompletionKeyValues::create($className, $columnName, []);
        $this->storage[] = $newCompletionKeyValues;
        return $newCompletionKeyValues;
    }
}
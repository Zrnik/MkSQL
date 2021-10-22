<?php declare(strict_types=1);

namespace Zrnik\MkSQL\Repository\Fetch;

use Zrnik\MkSQL\Repository\BaseEntity;
use function in_array;

class CompletionIndication
{
    /**
     * @var string[]
     */
    private array $storage = [];

    public function __construct()
    {
    }

    public function isCompleted(BaseEntity $entity): bool
    {
        return in_array($this->keyOf($entity), $this->storage, true);
    }

    public function setCompleted(BaseEntity $entity): void
    {
        if (!$this->isCompleted($entity)) {
            $this->storage[] = $this->keyOf($entity);
        }
    }

    private function keyOf(BaseEntity $entity): string
    {
        $rawData = $entity->getRawData();
        $primaryKeyName = $entity::getPrimaryKeyName();
        return sprintf('%s::primaryKey(%s)', $entity::class, $rawData[$primaryKeyName]);
    }
}
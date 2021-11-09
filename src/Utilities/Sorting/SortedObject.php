<?php declare(strict_types=1);

namespace Zrnik\MkSQL\Utilities\Sorting;

class SortedObject
{
    /**
     * @param int|string $key
     * @param object $object
     */
    public function __construct(public mixed $key, public object $object)
    {
    }
}
<?php declare(strict_types=1);

namespace Zrnik\MkSQL\Utilities\Sorting;

use function array_key_exists;
use function chr;
use function count;
use function ord;

class Sorting
{


    /**
     * WILL NOT PRESERVE KEY!
     * @param object[] $objects
     * @param string $propertyName
     * @param bool $preserveKeys
     * @return object[]
     */
    public static function sortObjectsByProperty(array $objects, string $propertyName, bool $preserveKeys = false): array
    {

        /** @var array<string, SortedObject> $arrayToSort */
        $arrayToSort = [];


        foreach ($objects as $key => $object) {

            $sortableKey = (string)$object->$propertyName;

            while (array_key_exists($sortableKey, $arrayToSort)) {
                $sortableKey = self::incrementKey($sortableKey);
            }

            $arrayToSort[$sortableKey] = new SortedObject($key, $object);
        }

        ksort($arrayToSort);

        $result = [];
        foreach ($arrayToSort as $sortedObject) {
            if ($preserveKeys) {
                $result[$sortedObject->key] = $sortedObject->object;
            } else {
                $result[] = $sortedObject->object;
            }
        }

        return $result;

    }

    private static function incrementKey(string $sortableKey): string
    {
        $chars = str_split($sortableKey);
        $lastIndex = count($chars) - 1;
        $chars[$lastIndex] = chr(ord($chars[$lastIndex]) + 1);
        return implode('', $chars);
    }


}


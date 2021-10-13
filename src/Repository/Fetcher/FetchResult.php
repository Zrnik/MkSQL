<?php declare(strict_types=1);

namespace Zrnik\MkSQL\Repository\Fetcher;

use ReflectionProperty;
use Zrnik\MkSQL\Repository\Attributes\FetchArray;
use Zrnik\MkSQL\Repository\Attributes\ForeignKey;
use Zrnik\MkSQL\Repository\BaseEntity;
use Zrnik\MkSQL\Utilities\Reflection;
use function array_key_exists;
use function count;

class FetchResult
{
    /* <className, <primaryKeyValue, row[]>>  */
    /** @var array<class-string, array<string|int, array<mixed>>> $rowData */
    private array $rowData = [];

    /**
     * @param string $selectedBaseEntityClassString
     */
    public function __construct(
        private string $selectedBaseEntityClassString
    )
    {
    }

    /**
     * @return BaseEntity[]
     */
    public function getEntities(): array
    {
        $entities = [];

        foreach ($this->rowData as $className => $rows) {

            /** @var BaseEntity $classNameForStaticUse */
            $classNameForStaticUse = $className;

            $entities[$className] = [];

            foreach ($rows as $row) {
                $entities[$className][$row[$classNameForStaticUse::getPrimaryKeyName()]] = $classNameForStaticUse::fromIterable($row);
            }

        }


        // 'Inject' foreign keys:
        /**
         * @var class-string<BaseEntity> $className
         * @var BaseEntity[] $keyEntities
         */
        foreach ($entities as $className => $keyEntities) {
            /**
             * @var BaseEntity $entity
             */
            foreach ($keyEntities as $entity) {
                foreach (BaseEntity::getReflectionClass($className)->getProperties() as $reflectionProperty) {
                    $propertyName = $reflectionProperty->getName();
                    $foreignKeyAttribute = Reflection::propertyGetAttribute($reflectionProperty, ForeignKey::class);
                    if ($foreignKeyAttribute !== null) {
                        $requiredClassName = Reflection::attributeGetArgument($foreignKeyAttribute);
                        $requiredPrimaryKeyValue = $entity->getRawData()[$entity::columnName($reflectionProperty)];
                        $entity->$propertyName = $entities[$requiredClassName][$requiredPrimaryKeyValue];
                    }
                }
            }
        }

        // 'Inject' fetch array
        /**
         * @var class-string<BaseEntity> $className
         * @var BaseEntity[] $keyEntities
         */
        foreach ($entities as $className => $keyEntities) {
            /**
             * @var BaseEntity $entity
             */
            foreach ($keyEntities as $entity) {
                foreach (BaseEntity::getReflectionClass($className)->getProperties() as $reflectionProperty) {
                    $propertyName = $reflectionProperty->getName();
                    $fetchArrayAttribute = Reflection::propertyGetAttribute($reflectionProperty, FetchArray::class);
                    if ($fetchArrayAttribute !== null) {
                        $requiredClassName = Reflection::attributeGetArgument($fetchArrayAttribute);


                        $pointerReflectionProperties = $this->findPropertyPointingTo($requiredClassName, $entity::class);
                        $values = [];

                        //dump($entity::class . " needs all: " . $requiredClassName);

                        foreach ($pointerReflectionProperties as $pointerRp) {
                            $pointerName = BaseEntity::columnName($pointerRp);
                            //dump($pointerName);

                            if (array_key_exists($requiredClassName, $entities)) {
                                foreach ($entities[$requiredClassName] as $row) {
                                    if ((string)$row->getRawData()[$pointerName] === (string)$entity->getPrimaryKeyValue()) {
                                        //dump("FOUND:::");
                                        //dump($row);
                                        $values[$row->getPrimaryKeyValue()] = $row;
                                    }
                                }
                            }

                            //dump("Where '".$pointerName."': " . $entity->getPrimaryKeyValue());
                            //$values[] =
                            // $entity->$propertyName[] = null;

                        }
                        $entity->$propertyName = array_values($values);
                    }
                }
            }
        }


        if (!array_key_exists($this->selectedBaseEntityClassString, $entities)) {
            $entities[$this->selectedBaseEntityClassString] = [];
        }

        return $entities[$this->selectedBaseEntityClassString];
    }

    /**
     * @param class-string<BaseEntity> $baseEntityClassString
     * @param mixed[] $rows
     */
    public function addRows(string $baseEntityClassString, array $rows): void
    {

        /** @var BaseEntity $baseEntityForStaticUse */
        $baseEntityForStaticUse = $baseEntityClassString;

        if (!array_key_exists($baseEntityClassString, $this->rowData)) {
            $this->rowData[$baseEntityClassString] = [];
        }

        foreach ($rows as $row) {
            $primaryKeyColumnName = $baseEntityForStaticUse::getPrimaryKeyName();
            $this->rowData[$baseEntityClassString][$row[$primaryKeyColumnName]] = $row;
        }

    }

    /**
     * Returns array, with missing primary key values
     *
     * Shape: <className, <columnName, columnValues[]>>
     *
     * @return array<class-string, array<string|int, array<mixed>>>
     */
    public function getCompletionKeys(): array
    {
        $completionKeys = [];

        /**
         * @var class-string<BaseEntity> $classString
         * @var mixed[] $rowsByPrimaryKey
         */
        foreach ($this->rowData as $classString => $rowsByPrimaryKey) {

            //region Foreign Key

            $foreignKeyProperties = $this->foreignKeyProperties($classString);

            /** @var BaseEntity $currentEntityStatic */
            $currentEntityStatic = $classString;

            /**
             * @var string $propertyName
             * @var class-string<BaseEntity> $foreignEntityClassName
             */
            foreach ($foreignKeyProperties as $propertyName => $foreignEntityClassName) {
                /** @var BaseEntity $foreignEntityStatic */
                $foreignEntityStatic = $foreignEntityClassName;

                if (!array_key_exists($foreignEntityClassName, $completionKeys)) {
                    $completionKeys[$foreignEntityClassName] = [];
                }

                $values = [];
                foreach ($rowsByPrimaryKey as $row) {
                    $propertyReflection = $currentEntityStatic::propertyReflection($propertyName);
                    if ($propertyReflection !== null) {
                        $values[] = $row[BaseEntity::columnName($propertyReflection)];
                    }
                }

                $completionKeys
                [$foreignEntityClassName]
                [$foreignEntityStatic::getPrimaryKeyName()] = array_unique($values);

            }

            //endregion

            //region FetchArray

            $fetchArrayData = $this->fetchArrayData($classString);

            foreach ($fetchArrayData as $foreignKeyProperty) {

                /** @var class-string<BaseEntity> $declaringClass */
                $declaringClass = $foreignKeyProperty->getDeclaringClass()->getName();

                if (!array_key_exists($declaringClass, $completionKeys)) {
                    $completionKeys[$declaringClass] = [];
                }

                $columnName = BaseEntity::columnName($foreignKeyProperty);

                if (!array_key_exists($columnName, $completionKeys[$declaringClass])) {
                    $completionKeys[$declaringClass][$columnName] = [];
                }

                foreach ($this->rowData as $className => $rows) {

                    /** @var BaseEntity $classNameStaticUse */
                    $classNameStaticUse = $className;

                    $primaryKeyName = $classNameStaticUse::getPrimaryKeyName();

                    foreach ($rows as $row) {
                        $completionKeys[$declaringClass][$columnName][] = $row[$primaryKeyName];
                    }

                }
            }

            //endregion

        }

        return $this->removeExistingRowsFromCompletionKeys($completionKeys);
    }

    public function needsCompletion(): bool
    {
        return count($this->getCompletionKeys()) > 0;
    }

    /**
     * @param class-string<BaseEntity> $classString
     * @return array<string,string> <propertyName, foreignEntityClassName>
     */
    private function foreignKeyProperties(string $classString): array
    {
        $reflection = BaseEntity::getReflectionClass($classString);

        $result = [];
        foreach ($reflection->getProperties() as $reflectionProperty) {
            $fkAttribute = Reflection::propertyGetAttribute($reflectionProperty, ForeignKey::class);
            if ($fkAttribute !== null) {
                $result[$reflectionProperty->getName()] = Reflection::attributeGetArgument($fkAttribute);
            }
        }

        return $result;
    }

    /**
     * @param class-string<BaseEntity> $classString
     * @return ReflectionProperty[]
     */
    private function fetchArrayData(string $classString): array
    {
        $reflection = BaseEntity::getReflectionClass($classString);

        $result = [];

        foreach ($reflection->getProperties() as $reflectionProperty) {
            $faAttribute = Reflection::propertyGetAttribute($reflectionProperty, FetchArray::class);
            if ($faAttribute !== null) {

                $fetchClassName = Reflection::attributeGetArgument($faAttribute);
                $pointerReflectionProperties = $this->findPropertyPointingTo($fetchClassName, $classString);

                foreach ($pointerReflectionProperties as $pointerReflectionProperty) {

                    $result[] = $pointerReflectionProperty;

                }
            }
        }

        return $result;
    }

    /**
     * @param array<class-string<BaseEntity>, array<string|int, mixed[]>> $completionKeys
     * @return array<class-string<BaseEntity>, array<string|int, mixed[]>>
     */
    private function removeExistingRowsFromCompletionKeys(array $completionKeys): array
    {
        $newCompletionKeys = [];
        /**
         * @var class-string<BaseEntity> $className
         */
        foreach ($completionKeys as $className => $keyValues) {
            $newKeyValues = [];
            foreach ($keyValues as $key => $values) {

                $key = (string)$key;

                if (!array_key_exists($key, $newKeyValues)) {
                    $newKeyValues[$key] = [];
                }

                foreach ($values as $value) {

                    $found = false;

                    if (array_key_exists($className, $this->rowData)) {
                        foreach ($this->rowData[$className] as $pkeyRow) {

                            $primaryKeyColumnName = BaseEntity::getPrimaryKeyName(
                                BaseEntity::getReflectionClass($className)
                            );

                            if ((string)$pkeyRow[$primaryKeyColumnName] === (string)$value) {
                                $found = true;
                            }
                        }
                    }

                    if (!$found) {
                        $newKeyValues[$key][] = $value;
                    }

                }

                if (count($newKeyValues[$key]) === 0) {
                    unset($newKeyValues[$key]);
                }
            }

            if (count($newKeyValues) > 0) {
                $newCompletionKeys[$className] = $newKeyValues;
            }
        }

        return $newCompletionKeys;
    }

    /**
     * @param class-string<BaseEntity> $referencingClass
     * @param class-string<BaseEntity> $searchClass
     * @return ReflectionProperty[]
     */
    private function findPropertyPointingTo(string $referencingClass, string $searchClass): array
    {
        $result = [];
        $reflection = BaseEntity::getReflectionClass($referencingClass);
        foreach ($reflection->getProperties() as $reflectionProperty) {
            $foreignKeyAttribute = Reflection::propertyGetAttribute($reflectionProperty, ForeignKey::class);
            if ($foreignKeyAttribute !== null) {
                $arg = Reflection::attributeGetArgument($foreignKeyAttribute);
                if ($arg === $searchClass) {
                    $result[] = $reflectionProperty;
                }
            }
        }
        return $result;
    }


}

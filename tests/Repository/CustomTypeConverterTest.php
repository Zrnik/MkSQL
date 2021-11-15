<?php declare(strict_types=1);

namespace Tests\Repository;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;
use Tests\Mock\Repository\ConfigurationNotFoundException;
use Tests\Mock\Repository\HookExampleConverter;
use Zrnik\MkSQL\Repository\CustomTypeConverter;
use Zrnik\PHPUnit\Exceptions;

class CustomTypeConverterTest extends TestCase
{
    use Exceptions;

    public string $testProperty;

    public function testHook(): void
    {
        $thisReflection = new ReflectionClass($this);

        /** @var HookExampleConverter $converter */
        $converter = CustomTypeConverter::initialize(
            HookExampleConverter::class, $thisReflection->getProperty('testProperty')
        );

        $exampleArray = ['hello', 'world'];

        $this->assertExceptionThrown(
            ConfigurationNotFoundException::class,
            function () use ($converter, $exampleArray) {
                $converter->serialize($exampleArray);
            }
        );

        CustomTypeConverter::addOnCreate(
            static function (CustomTypeConverter $converter) {
                if($converter instanceof HookExampleConverter) {
                    $converter->configuration = new stdClass();
                }
            }
        );

        //Re-create the converter, so the hook has a chance to get applied:

        /** @var HookExampleConverter $converter */
        $converter = CustomTypeConverter::initialize(
            HookExampleConverter::class, $thisReflection->getProperty('testProperty')
        );


        $this->assertNoExceptionThrown(
            function () use ($converter, $exampleArray) {
                $converter->serialize($exampleArray);
            }
        );


    }


}

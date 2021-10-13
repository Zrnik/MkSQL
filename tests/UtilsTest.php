<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Tests;

use Exception;
use JetBrains\PhpStorm\ArrayShape;
use PHPUnit\Framework\TestCase;
use Zrnik\MkSQL\Exceptions\InvalidArgumentException;
use Zrnik\MkSQL\Utils;
use Zrnik\PHPUnit\Exceptions;

class UtilsTest extends TestCase
{
    use Exceptions;

    /**
     * @throws Exception
     */
    public function testConfirmKeyName(): void
    {
        /* *
         *
         *   ["TestedKey"][0] = Tested MaxLength
         *   ["TestedKey"][1] = Expected Result
         *
         * */

        $TestedValues = [
            'some_key_22_chars_long' =>
                [
                    [10, '13304f79b5'],
                    [20, '13304f79b541b450ec1d'],
                    [30, 'some_key_22_chars_long'],
                ],

            'damn_this_is_a_really_long_key_i_wonder_when_it_will_end_well_just_NOW' =>
                [
                    [10, 'ca3e66e31b'],
                    [20, 'ca3e66e31b3f7a664039'],
                    [30, 'ca3e66e31b3f7a6640399d15103183'],
                    [40, 'ca3e66e31b3f7a6640399d1510318314'],
                    [50, 'ca3e66e31b3f7a6640399d1510318314'],
                    [69, 'ca3e66e31b3f7a6640399d1510318314'],
                    [70, 'damn_this_is_a_really_long_key_i_wonder_when_it_will_end_well_just_NOW'],
                ],
        ];

        foreach ($TestedValues as $TestedKey => $ResultArray) {
            foreach ($ResultArray as $ResultInfo) {
                [$TestedLength, $ExpectedResult] = $ResultInfo;
                static::assertSame(
                    $ExpectedResult,
                    Utils::confirmKeyName($TestedKey, $TestedLength)
                );
            }
        }

        //Base length should be 64 (MySQL said)
        $String64 = '1234567890123456789012345678901234567890123456789012345678901234';
        $String65 = '12345678901234567890123456789012345678901234567890123456789012345';

        static::assertSame(
            $String64,
            Utils::confirmKeyName($String64)
        );

        static::assertSame(
            '823cc889fc7318dd33dde0654a80b70a',
            Utils::confirmKeyName($String65)
        );


        $InvalidKeyValues = [
            'We dont like spaces',
            "We_also_don't_like_apostrophes",
            'NOT_EVEN_EXCL_MARKS!',
            'so_do_we_like_commas,no_we_dont',
        ];

        foreach ($InvalidKeyValues as $InvalidKey) {
            $this->assertExceptionThrown(
                InvalidArgumentException::class,
                function () use ($InvalidKey) {
                    Utils::confirmKeyName($InvalidKey);
                }
            );
        }
    }

    /**
     * @throws Exception
     */
    public function testConfirmForeignKeyTarget(): void
    {
        $CorrectValues = [
            'table_name.column_name',
            'AnyOtherTable.SomeColumn',
        ];

        foreach ($CorrectValues as $TestedValue) {
            static::assertSame(
                $TestedValue,
                Utils::confirmForeignKeyTarget($TestedValue)
            );
        }

        $IncorrectValues = [
            'no_dot_so_its_error',
            'more.than.one_dot',
            'space not allowed',
            'so_are_not,commas'
        ];


        foreach ($IncorrectValues as $TestedValue) {
            $this->assertExceptionThrown(
                InvalidArgumentException::class,
                function () use ($TestedValue) {
                    Utils::confirmForeignKeyTarget($TestedValue);
                }
            );
        }
    }

    /**
     * @throws Exception
     */
    public function testConfirmType(): void
    {
        $ValidTypes = [
            'int' => 'int',
            'longtext' => 'longtext',
            'varchar(666)' => 'varchar(666)',
            'decimal(15,5)' => 'decimal(15,5)',
            'technically_valid' => 'technically_valid',
            'also_valid(1, 2, 3)' => 'also_valid(1,2,3)' //Spaces are not allowed, but they are automatically removed.
        ];

        foreach ($ValidTypes as $TestedType => $ExpectedResult) {
            static::assertSame(
                $ExpectedResult,
                Utils::confirmType($TestedType)
            );
        }

        $InvalidTypes = [
            "Someone'sType",
            'JustTesting!',
            'decimal[15,5]',
            'varchar{666}',
        ];

        foreach ($InvalidTypes as $TestedType) {
            $this->assertExceptionThrown(
                InvalidArgumentException::class,
                function () use ($TestedType) {
                    Utils::confirmType($TestedType);
                }
            );
        }
    }

    /**
     * @throws Exception
     */
    public function testConfirmColumnName(): void
    {
        $defaultOKValues = $this->getDefaultTestedValuesOK();
        $defaultERRValues = $this->getDefaultTestedValuesERR();

        foreach ($defaultOKValues as $TestedKey => $ExpectedValue) {
            static::assertSame(
                $ExpectedValue,
                Utils::confirmColumnName($TestedKey)
            );
        }

        foreach ($defaultERRValues as $TestedKey) {
            $this->assertExceptionThrown(
                InvalidArgumentException::class,
                function () use ($TestedKey) {
                    Utils::confirmColumnName($TestedKey);
                }
            );
        }
    }

    /**
     * [Tested Key] => Expected Result
     * @return array<string>
     */
    #[ArrayShape(['hello_world' => 'string', 'this_is_ok' => 'string', 'ThisIsAlsoOK' => 'string', 'XD' => 'string'])]
    private function getDefaultTestedValuesOK(): array
    {
        return [
            'hello_world' => 'hello_world',
            'this_is_ok' => 'this_is_ok',
            'ThisIsAlsoOK' => 'ThisIsAlsoOK',
            'XD' => 'XD',
        ];


    }

    /**
     * InvalidKey[]
     * @return string[]
     */
    private function getDefaultTestedValuesERR(): array
    {
        return [
            'No spaces',
            'no_dots.',
            'not_AZazOR_underscore :)',
        ];
    }

    /**
     * @throws Exception
     */
    public function testConfirmTableName(): void
    {
        $defaultOKValues = $this->getDefaultTestedValuesOK();
        $defaultERRValues = $this->getDefaultTestedValuesERR();

        foreach ($defaultOKValues as $TestedKey => $ExpectedValue) {
            static::assertSame(
                $ExpectedValue,
                Utils::confirmTableName($TestedKey)
            );
        }

        foreach ($defaultERRValues as $TestedKey) {
            $this->assertExceptionThrown(
                InvalidArgumentException::class,
                function () use ($TestedKey) {
                    Utils::confirmTableName($TestedKey);
                }
            );
        }
    }

    /**
     * @throws Exception
     */
    public function testConfirmComment(): void
    {
        $OKComments = [
            'Hello, this is a valid comment.'
        ];

        foreach ($OKComments as $TestedComment) {
            {
                static::assertSame(
                    Utils::confirmComment($TestedComment),
                    $TestedComment
                );
            }
        }

        $InvalidComments = [
            "Aren't apostrophes illegal?",
            'And question marks?',
            'Also check exclamation!',
            'You may not notice it, but "this" is also not allowed.'
        ];


        foreach ($InvalidComments as $TestedComment) {
            $this->assertExceptionThrown(
                InvalidArgumentException::class,
                function () use ($TestedComment) {
                    Utils::confirmComment($TestedComment);
                }
            );
        }

    }

    /**
     * @throws Exception
     */
    public function testCommentsException(): void
    {
        $ValidKeys = [
            'This_is_a_VALID_key_with_AZ_az_and_an_underscore'
        ];

        foreach ($ValidKeys as $TestedComment) {
            static::assertSame(
                Utils::internalTestCommentsError($TestedComment),
                $TestedComment
            );
        }


        $KeysWithComments = [
            'hello_here_is_comment--malicious_comment',
            'dont_try_comments/*malicious_comment_XD*/i_will_notice'
        ];

        foreach ($KeysWithComments as $TestedComment) {
            $this->assertExceptionThrown(
                InvalidArgumentException::class,
                function () use ($TestedComment) {
                    Utils::internalTestCommentsError($TestedComment);
                }
            );
        }
    }
}

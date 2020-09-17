<?php declare(strict_types=1);

/*
 * Zrník.eu | MkSQL
 * User: Programátor
 * Date: 31.08.2020 8:00
 */


use PHPUnit\Framework\TestCase;
use Zrny\MkSQL\Utils;

class UtilsTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testConfirmKeyName()
    {
        /* *
         *
         *   ["TestedKey"][0] = Tested MaxLength
         *   ["TestedKey"][1] = Expected Result
         *
         * */

        $TestedValues = [
            "some_key_22_chars_long" =>
                [
                    [10, "13304f79b5"],
                    [20, "13304f79b541b450ec1d"],
                    [30, "some_key_22_chars_long"],
                ],

            "damn_this_is_a_really_long_key_i_wonder_when_it_will_end_well_just_NOW" =>
                [
                    [10, "ca3e66e31b"],
                    [20, "ca3e66e31b3f7a664039"],
                    [30, "ca3e66e31b3f7a6640399d15103183"],
                    [40, "ca3e66e31b3f7a6640399d1510318314"],
                    [50, "ca3e66e31b3f7a6640399d1510318314"],
                    [69, "ca3e66e31b3f7a6640399d1510318314"],
                    [70, "damn_this_is_a_really_long_key_i_wonder_when_it_will_end_well_just_NOW"],
                ],
        ];

        foreach ($TestedValues as $TestedKey => $ResultArray) {
            foreach ($ResultArray as $ResultInfo) {
                $TestedLength = $ResultInfo[0];
                $ExpectedResult = $ResultInfo[1];
                $this->assertSame(
                    $ExpectedResult,
                    Utils::confirmKeyName($TestedKey, $TestedLength)
                );
            }
        }

        //Base length should be 64 (MySQL said)
        $String64 = "1234567890123456789012345678901234567890123456789012345678901234";
        $String65 = "12345678901234567890123456789012345678901234567890123456789012345";

        $this->assertSame(
            $String64,
            Utils::confirmKeyName($String64)
        );

        $this->assertSame(
            "823cc889fc7318dd33dde0654a80b70a",
            Utils::confirmKeyName($String65)
        );


        $InvalidKeyValues = [
            "We dont like spaces",
            "We_also_don't_like_apostrophes",
            "NOT_EVEN_EXCL_MARKS!",
            "so_do_we_like_commas,no_we_dont",
        ];

        foreach ($InvalidKeyValues as $InvalidKey) {
            try {
                Utils::confirmKeyName($InvalidKey);
                throw new Exception("Expected exception " . \Zrny\MkSQL\Exceptions\InvalidArgumentException::class . " not thrown!");
            } catch (\Zrny\MkSQL\Exceptions\InvalidArgumentException $_) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /**
     * @throws Exception
     */
    public function testConfirmForeignKeyTarget()
    {
        $CorrectValues = [
            "table_name.column_name",
            "AnyOtherTable.SomeColumn",
        ];

        foreach ($CorrectValues as $TestedValue)
            $this->assertSame(
                $TestedValue,
                Utils::confirmForeignKeyTarget($TestedValue)
            );

        $IncorrectValues = [
            "no_dot_so_its_error",
            "more.than.one_dot",
            "space not allowed",
            "so_are_not,commas"
        ];


        foreach ($IncorrectValues as $TestedValue) {
            try {
                Utils::confirmForeignKeyTarget($TestedValue);
                throw new Exception("Expected exception " . \Zrny\MkSQL\Exceptions\InvalidArgumentException::class . " not thrown!");
            } catch (\Zrny\MkSQL\Exceptions\InvalidArgumentException $_) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /**
     * @throws Exception
     */
    public function testConfirmType()
    {
        $ValidTypes = [
            "int" => "int",
            "longtext" => "longtext",
            "varchar(666)" => "varchar(666)",
            "decimal(15,5)" => "decimal(15,5)",
            "technically_valid" => "technically_valid",
            "also_valid(1, 2, 3)" => "also_valid(1,2,3)" //Spaces are not allowed, but they are automatically removed.
        ];

        foreach ($ValidTypes as $TestedType => $ExpectedResult)
            $this->assertSame(
                $ExpectedResult,
                Utils::confirmType($TestedType)
            );

        $InvalidTypes = [
            "Someone'sType",
            "JustTesting!",
            "decimal[15,5]",
            "varchar{666}",
        ];

        foreach ($InvalidTypes as $TestedType) {
            try {
                Utils::confirmType($TestedType);
                throw new Exception("Expected exception " . \Zrny\MkSQL\Exceptions\InvalidArgumentException::class . " not thrown!");
            } catch (\Zrny\MkSQL\Exceptions\InvalidArgumentException $_) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /**
     * @throws Exception
     */
    public function testConfirmColumnName()
    {
        $defaultOKValues = $this->getDefaultTestedValuesOK();
        $defaultERRValues = $this->getDefaultTestedValuesERR();

        foreach ($defaultOKValues as $TestedKey => $ExpectedValue)
            $this->assertSame(
                $ExpectedValue,
                Utils::confirmColumnName($TestedKey)
            );

        foreach ($defaultERRValues as $TestedKey) {
            try {
                Utils::confirmColumnName($TestedKey);
                throw new Exception("Expected exception " . \Zrny\MkSQL\Exceptions\InvalidArgumentException::class . " not thrown!");
            } catch (\Zrny\MkSQL\Exceptions\InvalidArgumentException $_) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /**
     * [Tested Key] => Expected Result
     */
    private function getDefaultTestedValuesOK()
    {
        return [
            "hello_world" => "hello_world",
            "this_is_ok" => "this_is_ok",
            "ThisIsAlsoOK" => "ThisIsAlsoOK",
            "XD" => "XD",
        ];


    }

    /**
     * InvalidKey[]
     */
    private function getDefaultTestedValuesERR()
    {
        return [
            "No spaces",
            "no_dots.",
            "not_AZazOR_underscore :)",
        ];
    }

    /**
     * @throws Exception
     */
    public function testConfirmTableName()
    {
        $defaultOKValues = $this->getDefaultTestedValuesOK();
        $defaultERRValues = $this->getDefaultTestedValuesERR();

        foreach ($defaultOKValues as $TestedKey => $ExpectedValue)
            $this->assertSame(
                $ExpectedValue,
                Utils::confirmTableName($TestedKey)
            );

        foreach ($defaultERRValues as $TestedKey) {
            try {
                Utils::confirmTableName($TestedKey);
                throw new Exception("Expected exception " . \Zrny\MkSQL\Exceptions\InvalidArgumentException::class . " not thrown!");
            } catch (\Zrny\MkSQL\Exceptions\InvalidArgumentException $_) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /**
     * @throws Exception
     */
    public function testConfirmComment()
    {
        $OKComments = [
            "Hello, this is a valid comment."
        ];

        foreach ($OKComments as $TestedComment)
            $this->assertSame(
                Utils::confirmComment($TestedComment),
                $TestedComment
            );

        $InvalidComments = [
            "Aren't apostrophes illegal?",
            "And question marks?",
            "Also check exclamation!",
            "You may not notice it, but \"this\" is also not allowed."
        ];


        foreach ($InvalidComments as $TestedComment) {
            try {
                Utils::confirmComment($TestedComment);
                throw new Exception("Expected exception " . \Zrny\MkSQL\Exceptions\InvalidArgumentException::class . " not thrown!");
            } catch (\Zrny\MkSQL\Exceptions\InvalidArgumentException $_) {
                $this->addToAssertionCount(1);
            }
        }

    }

    /**
     * @throws Exception
     */
    public function testCommentsException()
    {
        $ValidKeys = [
            "This_is_a_VALID_key_with_AZ_az_and_an_underscore"
        ];

        foreach ($ValidKeys as $TestedComment)
            $this->assertSame(
                Utils::__testCommentsError($TestedComment),
                $TestedComment
            );


        $KeysWithComments = [
            "hello_here_is_comment--malicious_comment",
            "dont_try_comments/*malicious_comment_XD*/i_will_notice"
        ];

        foreach ($KeysWithComments as $TestedComment) {
            try {
                Utils::__testCommentsError($TestedComment);
                throw new Exception("Expected exception " . \Zrny\MkSQL\Exceptions\InvalidArgumentException::class . " not thrown!");
            } catch (\Zrny\MkSQL\Exceptions\InvalidArgumentException $_) {
                $this->addToAssertionCount(1);
            }
        }
    }

}

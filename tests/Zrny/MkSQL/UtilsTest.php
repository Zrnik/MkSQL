<?php declare(strict_types=1);

/*
 * Zrník.eu | ZrnyWeb  
 * User: Programátor
 * Date: 29.08.2020 13:04
 */

namespace Zrny\MkSQL;

use PHPUnit\Framework\TestCase;

class UtilsTest extends TestCase
{


    public function testConfirmName()
    {
        $ValidNames = [
            "table",
            "table_name",
            "HelloWorld",
            "Be_Free"
        ];

        foreach($ValidNames as $ValidName)
            $this->assertNull(Utils::confirmName($ValidName));

        $ValidWithSpace = [
            "Hello World",
            "With_ Space"
        ];

        foreach($ValidWithSpace as $ValidName)
            $this->assertNull(Utils::confirmName($ValidName,[" "]));

        $ValidTypes = [
            "longtext",
            "tinyint(1)",
            "decimal(10,5)"
        ];

        foreach($ValidTypes as $ValidName)
            $this->assertNull(Utils::confirmName($ValidName,["(",")",","]));





    }


}

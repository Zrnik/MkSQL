<?php
/*
 * Zrník.eu | MkSQL
 * User: Programátor
 * Date: 31.07.2020 10:21
 */


namespace Zrny\MkSQL;

use InvalidArgumentException;
use Nette\Utils\Strings;

class Utils
{
    /**
     * List of default allowed characters.
     *
     * @var string[]
     */
    private static array $_defaultAllowedCharacters = [
        "A","a","B","b","C","c","D","d","E","e","F","f","G","g","H","h","I","i","J","j",
        "K","k","L","l","M","m","N","n","O","o","P","p","Q","q","R","r","S","s","T","t",
        "U","u","V","v","W","w","X","x","Y","y","Z","z",
        "0","1","2","3","4","5","6","7","8","9","_"
    ];

    /**
     * This will confirm if the name is good for use in SQL query.
     * We are using (against all rules) string concat-ing when making queries.
     *
     * @param string|null $name
     * @param array $AdditionalAllowed
     * @return string|null
     */
    public static function confirmName(?string $name, array $AdditionalAllowed = []) : ?string
    {
        // Null is invalid
        if($name === null)
            throw new InvalidArgumentException("Name is NULL!");

        // Comments are not allowed obviously :)
        if(Strings::contains($name, "--"))
            throw new InvalidArgumentException("Comment found in SQL query!");

        if(Strings::contains($name, "/*") || Strings::contains($name, "*/"))
            throw new InvalidArgumentException("Comment found in SQL query!");

        // Remove allowed characters, if the string isn't empty, it contains invalid characters!
        $Allowed = array_merge(self::$_defaultAllowedCharacters,$AdditionalAllowed);

        if(str_replace($Allowed,"",$name) !== "")
            throw new InvalidArgumentException("Argument '".$name."' contains invalid characters!");

        // Its kind of FallTrough...
        return $name;
    }


    /**
     * If the key is too long, we use md5 to make it shorter and trim it if required.
     *
     * @param string $keyName
     * @param int $maxLen
     * @return string
     */
    public static function confirmKeyName(string $keyName, int $maxLen = 64) : string
    {
        if(Strings::length($keyName) > $maxLen)
            return substr(md5($keyName),0,min(32, $maxLen));
        return $keyName;
    }



}

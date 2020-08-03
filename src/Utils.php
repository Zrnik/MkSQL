<?php
/*
 * Zrník.eu | MkSQL  
 * User: Programátor
 * Date: 31.07.2020 10:21
 */


namespace Zrny\MkSQL;

use InvalidArgumentException;
use Tracy\Debugger;

class Utils
{

    private static $_defaultAllowedCharacters = [
        "A","a","B","b","C","c","D","d","E","e","F","f","G","g","H","h","I","i","J","j",
        "K","k","L","l","M","m","N","n","O","o","P","p","Q","q","R","r","S","s","T","t",
        "U","u","V","v","W","w","X","x","Y","y","Z","z",
        "0","1","2","3","4","5","6","7","8","9","_"
    ];

    private static $SQLKeywords = [

    ];

    public static function confirmName($name, $AdditionalAllowed = [])
    {
        if($name === null)
            return null;

        $name = trim($name);

        $Allowed = array_merge(self::$_defaultAllowedCharacters,$AdditionalAllowed);

        if(trim(str_replace($Allowed,"",$name)) !== "")
            throw new InvalidArgumentException("Argument '".$name."' contains invalid characters!");

        if(in_array(strtolower($name), self::$SQLKeywords))
            throw new InvalidArgumentException("Argument '".$name."' is SQL keyword!");

        return $name;
    }





    // speedDebugging:
   /* private static $_SpeedReports = [];
    public static function measure($key)
    {
        self::$_SpeedReports[$key] = 0;
        Debugger::timer("dbg-reports-".$key);
    }

    public static function report($key)
    {
        self::$_SpeedReports[$key] = round(1000*Debugger::timer("dbg-reports-".$key),5);
    }

    public static function getSpeedReports()
    {
        return self::$_SpeedReports;
    }*/










}
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
        "ADD", "ADD CONSTRAINT", "ALTER", "ALTER COLUMN", "ALTER TABLE", "ALL", "AND",
        "ANY", "AS", "ASC", "BACKUP DATABASE", "BETWEEN", "CASE", "CHECK", "COLUMN",
        "CONSTRAINT", "CREATE", "CREATE DATABASE", "CREATE INDEX", "CREATE OR REPLACE VIEW",
        "CREATE TABLE", "CREATE PROCEDURE", "CREATE UNIQUE INDEX", "CREATE VIEW", "DATABASE",
        "DEFAULT", "DELETE", "DESC", "DISTINCT", "DROP", "DROP COLUMN", "DROP CONSTRAINT",
        "DROP DATABASE", "DROP DEFAULT", "DROP INDEX", "DROP TABLE", "DROP VIEW", "EXEC",
        "EXISTS", "FOREIGN KEY", "FROM", "FULL OUTER JOIN", "GROUP BY", "HAVING", "IN",
        "INDEX", "INNER JOIN", "INSERT INTO", "IS NULL", "IS NOT NULL",
        "JOIN", "LEFT JOIN", "LIKE", "LIMIT", "NOT", "NOT NULL", "OR", "ORDER BY", "OUTER JOIN",
        "PRIMARY KEY", "PROCEDURE", "RIGHT JOIN", "ROWNUM", "SELECT", "SELECT DISTINCT",
        "SELECT INTO", "SELECT TOP", "SET", "TABLE", "TOP", "TRUNCATE TABLE", "UNION",
        "UNION ALL", "UNIQUE", "UPDATE", "VALUES", "VIEW", "WHERE",
    ];

    /**
     * @param $name
     * @param array $AdditionalAllowed
     * @return string|null
     */
    public static function confirmName($name, $AdditionalAllowed = [])
    {
        if($name === null)
            return null;

        $name = trim($name);

        $Allowed = array_merge(self::$_defaultAllowedCharacters,$AdditionalAllowed);

        if(trim(str_replace($Allowed,"",$name)) !== "")
            throw new InvalidArgumentException("Argument '".$name."' contains invalid characters!");

        if(str_replace(self::$SQLKeywords, "", strtoupper($name)) !== strtoupper($name))
            throw new InvalidArgumentException("Argument '".$name."' contains SQL keyword!");

        return $name;
    }
}
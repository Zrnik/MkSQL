<?php
/*
 * Zrník.eu | MkSQL  
 * User: Programátor
 * Date: 20.07.2020 9:48
 */


namespace Zrnik\MkSQL;


class ColumnType
{

    CONST TINYTEXT = 100010;
    CONST TEXT = 100011;
    CONST MEDIUMTEXT = 100012;
    CONST LONGTEXT = 100013;

    CONST TINYBLOB = 200010;
    CONST BLOB = 200011;
    CONST MEDIUMBLOB = 200012;
    CONST LONGBLOB = 200013;

    CONST CHAR = 100;



    private $Params = [];
    private $Type = -1;

    public function __construct(int $type)
    {
        $this->Type = $type;
    }

    public function addParam($par)
    {
        $this->Params[] = $par;
        return $this;
    }

    public function canUniqueOrDefault()
    {
        $Disallowed = [
            self::TINYTEXT,self::TEXT,self::MEDIUMTEXT,self::LONGTEXT,
            self::TINYBLOB,self::BLOB,self::MEDIUMBLOB,self::LONGBLOB,
        ];
        return !in_array($this->Type,$Disallowed);
    }



    private function parameterized($type)
    {
        if(count($this->Params) === 0)
            return $type;
        return $type."(".implode(",",$this->Params).")";
    }

    public function getString()
    {
        switch($this->Type)
        {
            // TEXT/BLOB nemaji delkove parametry

            case self::TINYTEXT:
                return "tinytext";
            case self::TEXT:
                return "text";
            case self::MEDIUMTEXT:
                return "mediumtext";
            case self::LONGTEXT:
                return "longtext";


            case self::TINYBLOB:
                return "tinyblob";
            case self::BLOB:
                return "blob";
            case self::MEDIUMBLOB:
                return "mediumblob";
            case self::LONGBLOB:
                return "longblob";

            case self::CHAR:
                return $this->parameterized("char");

            default:
                break;
        }

        return false;
    }




}
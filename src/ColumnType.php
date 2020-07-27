<?php
/*
 * Zrník.eu | MkSQL  
 * User: Programátor
 * Date: 20.07.2020 9:48
 */

namespace Zrnik\MkSQL;

class ColumnType
{

    //region CONST
    const TINYTEXT = 100010;
    const TEXT = 100011;
    const MEDIUMTEXT = 100012;
    const LONGTEXT = 100013;

    const TINYBLOB = 200010;
    const BLOB = 200011;
    const MEDIUMBLOB = 200012;
    const LONGBLOB = 200013;


    const DECIMAL = 210010;
    const DOUBLE = 210011;
    const FLOAT = 210012;

    const INT = 220010;
    const MEDIUMINT = 220011;
    const SMALLINT = 220012;
    const TINYINT = 220013;
    const BIGINT = 220014;

    const CHAR = 1;
    const VARCHAR = 2;
    const BINARY = 3;
    const VARBINARY = 4;

    const ENUM = 1000;
    const SET = 1001;

    const BIT = 2000;
    const BOOL = 2001;
    //endregion

    private $Type = -1;

    public function __construct(int $type, array $params = [])
    {
        $this->Type = $type;
        $this->Params = $params;
    }


    private $Params = [];

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

            //Sem si vygeneroval

            case self::DECIMAL:
                return $this->parameterized(strtolower("DECIMAL"));
            case self::DOUBLE:
                return $this->parameterized(strtolower("DOUBLE"));
            case self::FLOAT:
                return $this->parameterized(strtolower("FLOAT"));
            case self::BIGINT:
                return 'bigint(20)';
            case self::INT:
                return 'int(11)';
            case self::MEDIUMINT:
                return "mediumint(9)";
            case self::SMALLINT:
                return "smallint(6)";
            case self::TINYINT:
                return "tinyint(4)";
            case self::CHAR:
                return $this->parameterized(strtolower("CHAR"));
            case self::VARCHAR:
                return $this->parameterized(strtolower("VARCHAR"));
            case self::BINARY:
                return $this->parameterized(strtolower("BINARY"));
            case self::VARBINARY:
                return $this->parameterized(strtolower("VARBINARY"));
            case self::ENUM:
                return $this->parameterized(strtolower("ENUM"));
            case self::SET:
                return $this->parameterized(strtolower("SET"));
            case self::BIT:
                return $this->parameterized(strtolower("BIT"));
            case self::BOOL:
                return $this->parameterized(strtolower("BOOL"));

            default:
                break;
        }

        return false;
    }

    private function parameterized($type)
    {
        if (count($this->Params) === 0)
            return $type;

        return $type . "(" . implode(",", $this->Params) . ")";
    }
}
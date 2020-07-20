<?php
/*
 * Zrník.eu | untitled  
 * User: Programátor
 * Date: 20.07.2020 7:41
 */


namespace Zrnik\MkSQL;


use Exception;
use Nette\Database\ConstraintViolationException;

class Column
{
    public $ColName = null;

    public function __construct(string $ColumnName, ColumnType $type = null)
    {
        $this->ColName = $ColumnName;
        $this->setType($type);
    }

    /**
     * @var $Type ColumnType
     */
    private $Type = null;

    public function setType(?ColumnType $type)
    {
        $this->Type = $type;
        return $this;
    }

    /**
     * @return bool|string
     * @throws Exception
     */
    public function getTypeString()
    {
        $ts = $this->Type->getString();
        if ($ts === false)
            throw new Exception("Unknown Type!");
        return $ts;
    }

    public $CanBeNull = true;

    public function notNull($notnull = true)
    {
        $this->CanBeNull = !$notnull;
        return $this;
    }

    private $DefaultValue = null;

    public function setDefault(?string $string = null)
    {
        if ($this->RequireUnique === true)
            throw new ConstraintViolationException("Cannot set Default to column with Unique!");

        if ($this->Type === null)
            throw new ConstraintViolationException("Type required before setting default value!");

        if (!$this->Type->canUniqueOrDefault())
            throw new ConstraintViolationException("This type cannot have default value!");

        $this->DefaultValue = $string;
        return $this;
    }

    public function getDefault()
    {
        return $this->DefaultValue;
    }

    private $RequireUnique = false;

    public function setUnique(bool $needUnique = true)
    {
        if ($this->DefaultValue !== null)
            throw new ConstraintViolationException("Cannot set Unique to column with Default Value");

        if ($this->Type === null)
            throw new ConstraintViolationException("Type required before setting unique!");

        if (!$this->Type->canUniqueOrDefault())
            throw new ConstraintViolationException("This type cannot be unique!");

        $this->RequireUnique = $needUnique;
        return $this;
    }

    public function getUnique()
    {
        return $this->RequireUnique;
    }


}
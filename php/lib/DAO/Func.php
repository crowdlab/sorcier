<?php

namespace DAO;

use DAO;

/**
 * A base class for functions used in DAO conditions.
 */
class Func
{
    private $name;
    private $arg;

    public function __construct($name, $arg)
    {
        $this->name = $name;
        $this->arg = $arg;
    }

    public static function imbue($name, $arg)
    {
        return new static($name, $arg);
    }

    public function getName()
    {
        return $this->name;
    }

    public function getArg()
    {
        return $this->arg;
    }

    public function __toString()
    {
        return "{$this->name}('".QueryGen::escape($this->arg)."')";
    }
}

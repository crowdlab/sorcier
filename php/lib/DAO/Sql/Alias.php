<?php

namespace DAO\Sql;

use DAO\Sql;

/**
 * A base class for sql aliases used in sql field lists.
 */
class Alias
{
    private $what;
    private $alias;

    public function __construct($what, $alias)
    {
        $this->what = $what;
        $this->alias = $alias;
    }

    /**
     * generate sql statement for alias construct.
     */
    public static function imbue($what, $alias)
    {
        return new static($what, $alias);
    }

    /**
     * generate sql statement for alias construct.
     */
    public function __toString()
    {
        if (!is_object($this->what)) {
            $r = "{$this->what} AS `{$this->alias}`";
        } else {
            $r = ((string) $this->what)." AS `{$this->alias}`";
        }

        return $r;
    }
}

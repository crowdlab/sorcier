<?php

namespace DAO\Sql;

use DAO\Sql;

/**
 * A base class for expressions used in sql field lists.
 */
class Expr
{
    private $expr;
    private $as;

    /**
     * @param string      $expr выражение
     * @param string|null $as   псевдоним
     */
    public function __construct($expr, $as = null)
    {
        if (is_object($expr) && $expr instanceof self) {
            $this->expr = $expr->getExpr();
            $this->as = $expr->getAs();
        } else {
            $this->expr = $expr;
        }
        if ($as != null) {
            $this->as = $as;
        }
    }

    public static function imbue($expr, $as = null)
    {
        return new static($expr, $as);
    }

    public function setAs($as)
    {
        $this->as = $as;
    }

    /** получить выражение */
    public function getExpr()
    {
        return $this->expr;
    }

    /** получить значение AS */
    public function getAs()
    {
        return $this->as;
    }

    const KEY = '__KEY__';

    /** получить ключ для вставки в условие */
    public function make_key()
    {
        return self::KEY.((string) $this);
    }

    /** сгенерировать подвыражение SQL */
    public function __toString()
    {
        $as = $this->as != null ? " AS `{$this->as}`" : '';

        return "{$this->expr}$as";
    }
}

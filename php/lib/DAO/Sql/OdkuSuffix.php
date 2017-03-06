<?php

namespace DAO\Sql;

use DAO\Sql;

/**
 * Sql ON DUPLICATE KEY UPDATE suffix.
 */
class OdkuSuffix
{
    protected $q;

    public static function imbue($set)
    {
        return new static($set);
    }

    /**
     * Конструктор
     *
     * @param $set set
     */
    public function __construct($set)
    {
        $prefix = 'ON DUPLICATE KEY UPDATE ';
        $set_kv = \DAO\QueryGen::make_set_kv($set);
        $this->q = count($set_kv) ? $prefix.implode(',', $set_kv) : '';
    }

    /**
     * Получить текст
     */
    public function __toString()
    {
        return $this->q;
    }
}

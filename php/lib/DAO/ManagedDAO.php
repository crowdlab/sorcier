<?php

namespace DAO;

use DAO;

/**
 * This DAO helps FnMySQL operate.
 */
class ManagedDAO extends MySQLDAO
{
    protected $name;

    public static function perform_query($q)
    {
        return parent::perform_query($q);
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }
}

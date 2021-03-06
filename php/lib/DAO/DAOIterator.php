<?php

namespace DAO;

/**
 * MySQL selection results iterator.
 */
class DAOIterator implements \Iterator
{
    /** query result */
    protected $result;
    /** current record */
    protected $row;
    /** current position */
    protected $pos;
    /** helper object (to enrich records) */
    protected $helper;
    protected $dao;

    public function __construct($result, $dao = null, $helper = null)
    {
        $this->result = $result;
        $this->pos = 0;
        if ($helper) {
            $this->helper = $helper;
        }
        if ($dao) {
            $this->dao = $dao;
        }
    }

    public function num_rows()
    {
        if (!$this->result) {
            return 0;
        }

        return mysqli_num_rows($this->result);
    }

    public function fetch_all($schema = null, $func = null)
    {
        $r = [];
        if (is_callable($schema) && $func == null) {
            $func = $schema;
            $schema = null;
        }
        if (isset($this->dao) && $schema === null) {
            $c = get_class($this->dao);
            if (isset($c::$schema)) {
                $schema = $c::$schema;
            }
        }
        while ($row = mysqli_fetch_assoc($this->result)) {
            $row = \DAO\MySQLDAO::enforce($schema, $row);
            $r[] = $row;
        }
        if ($this->helper) {
            $r = $this->helper->enrichAll($r);
        }
        if ($func) {
            foreach ($r as &$row) {
                $row = call_user_func($func, $row);
            }
        }

        return $r;
    }

    public function fetch_assoc($schema = null, $func = null)
    {
        if (is_callable($schema) && $func == null) {
            $func = $schema;
            $schema = null;
        }
        if (isset($this->dao) && $schema === null) {
            $c = get_class($this->dao);
            if (isset($c::$schema)) {
                $schema = $c::$schema;
            }
        }
        $r = \DAO\MySQLDAO::enforce($schema, mysqli_fetch_assoc($this->result));
        if ($r && $this->helper) {
            $r = $this->helper->enrich($r);
        }
        if ($func) {
            $r = call_user_func($func, $r);
        }

        return $r;
    }

    public function current()
    {
        return $this->row;
    }

    public function key()
    {
        return $this->pos;
    }

    public function rewind()
    {
        mysqli_data_seek($this->result, 0);
        $this->pos = 0;
        $this->row = $this->fetch_assoc();
    }

    public function next()
    {
        $this->row = $this->fetch_assoc();
        $this->pos++;
    }

    public function valid()
    {
        return (bool) $this->row;
    }
}

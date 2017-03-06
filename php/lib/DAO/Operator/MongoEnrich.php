<?php

namespace DAO\Operator;

use DAO\Operator;
use DAO\Operator\Helpers\Mongo as MH;

/**
 * Mongo enrich operator.
 *
 * Adds a collection of items from mongo to every record.
 */
class MongoEnrich implements IOperator
{
    /** dao */
    protected $dao;
    /** parent entity id key */
    protected $idkey;
    /** condition */
    protected $cond;
    /** output key name */
    protected $key;
    /** selection fields */
    protected $fields;
    /** transform function */
    protected $mapper;
    /** item cache */
    protected $cache = [];

    /**
     * Create mongo enrich operator.
     *
     * @param IDAO   $dao    corresponding mongo DAO
     * @param string $idkey  parent entity id key
     * @param array  $cond   condition
     * @param string $key    output key name
     * @param array  $fields selection fields
     * @param func   $mapper transform function
     */
    public function __construct($dao, $idkey, $cond, $key = null,
            $fields = null, $mapper = null)
    {
        $this->dao = $dao;
        $this->idkey = $idkey;
        $this->cond = $cond;
        $this->key = $key
            ? $key
            : $dao->getName();
        $this->fields = $fields
            ? $fields
            : $dao->getFields();
        if ($mapper) {
            $this->mapper = $mapper;
        }
    }

    public function precache($ids)
    {
        $cond = $this->apply(['$in' => $ids]);
        $ret = $this->dao->select($this->fields, $cond);
        $items = $this->dao->fetch_all($ret, null, $this->mapper);
        foreach ($ids as $v) {
            $this->cache[$v] = [];
        }
        foreach ($items as $v) {
            if (!isset($v[$this->idkey])) {
                continue;
            }
            if (is_array($v[$this->idkey])) {
                $v[$this->idkey] = (count($v[$this->idkey]))
                     ? $v[$this->idkey][0]
                     : 0;
            }
            $this->cache[$v[$this->idkey]][] = $v;
        }
    }

    /** substitute marker with value */
    protected function apply($val)
    {
        $cond = $this->cond;
        foreach ($cond as $k => &$v) {
            if ($v == MH::IdMarker) {
                $v = $val;
            }
        }

        return $cond;
    }

    /**
     * Enrich a single record.
     */
    public function enrich($r)
    {
        $id = (int) $r['id'];
        if (!isset($this->cache[$id])) {
            $cond = $this->apply($id);
            $ret = $this->dao->select($this->fields, $cond);
            $files = $this->dao->fetch_all($ret, null, $this->mapper);
        } else {
            $files = $this->cache[$id];
        }
        if ($files) {
            if ($this->mapper) {
                array_walk($files, $this->mapper);
            }
            $files = \DAO\MongoDAO::remapIds($files);
            $r[$this->key] = $files;
        }

        return $r;
    }
}

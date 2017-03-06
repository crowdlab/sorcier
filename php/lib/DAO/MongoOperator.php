<?php

namespace DAO;

use DAO;
use DAO\QueryClass as QC;

/**
 * Functional Mongo operator.
 */
class MongoOperator
{
    use DAO\Operator\Traits\XHelpers;

    /** query class */
    protected $class;
    /** collection */
    protected $from;
    /** dao */
    protected $dao;
    protected $fields = [];
    protected $condition = [];
    protected $limit;
    protected $skip;
    protected $sort = ['_id' => 1];

    protected $classes_allowed =
        ['select', 'update', 'delete', 'insert'];
    protected $xResult = null;

    /**
     * Create object.
     *
     * @param $class one of $this->classes_allowed
     * @param $from operation table
     * @param $param1 depend on class
     *
     * * select: $fields, $condition
     * * update: $set, $condition
     * * insert: $fields, $values
     * * delete: $condition
     * @param $param2 see param1
     */
    public function __construct($class, $param1 = null, $param2 = null, $param3 = null)
    {
        if (!in_array($class, $this->classes_allowed, true)) {
            throw new InvalidArgumentException('incorrect class');
        }
        $this->class = $class;
        switch ($class) {
            case QC::select:
                if ($param1) {
                    $this->fields = $param1;
                }
                if ($param2) {
                    $this->condition = $param2;
                }
                break;
            case QC::update:
                if ($param1) {
                    $this->set = $param1;
                }
                if ($param2) {
                    $this->condition = $param2;
                }
                break;
            case QC::delete:
                if ($param1) {
                    $this->condition = $param1;
                }
                break;
            case QC::insert:
                if ($param1) {
                    $this->fields = $param1;
                }
                if ($param2) {
                    $this->ignore = $param2;
                }
                if ($param3) {
                    $this->suffix = $param3;
                }
                break;
        }
    }

    /**
     * основная таблица для изменения.
     */
    public function in($from)
    {
        return $this->from($from);
    }

    public function into($from)
    {
        return $this->from($from);
    }

    public function from($from)
    {
        if (is_object($from)) {
            $this->dao = $from;
            $from = $from->getName();
        }
        $this->from = $from;

        return $this;
    }

    public function orderBy($what, $desc = false)
    {
        $this->orderby = $what;
        $this->orderbyDesc = $desc;

        return $this;
    }

    public function limit($limit, $skip = null)
    {
        $this->limit = $limit;
        if (is_numeric($skip)) {
            $this->skip = $skip;
        }

        return $this;
    }

    public function select($fields)
    {
        if ($this->class != 'select') {
            throw new InvalidArgumentException('incorrect class');
        }
        $this->fields = $fields;

        return $this;
    }

    public function where($condition)
    {
        $this->condition = $condition;

        return $this;
    }

    /**
     * run query.
     */
    public function x()
    {
        if ($this->xResult) {
            return $this->xResult;
        }
        $dao = ManagedMongoDAO::getInstance();
        $dao->setName($this->dao->getName());
        switch ($this->class) {
            case QC::select:
                $this->xResult = $dao->select(
                    $this->fields,
                    $this->condition,
                    $this->sort,
                    $this->limit,
                    $this->skip
                );

                return $this->xResult;
                break;
            case QC::delete:
            case QC::update:
            case QC::insert:
                return false;
                // break;
        }
    }
}

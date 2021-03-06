<?php

namespace DAO;

use DAO;

/**
 * Mongo DAO: if you want to interact with MongoDB, please, extend this.
 */
abstract class MongoDAO implements IDAO
{
    use \Singleton;
    use \DAO\Enforcer;
    use \DAO\Helpers;

    abstract protected function getName();

    const IdKey = '_id';

    protected $collection = null;

    protected $insert_id = null;

    public function insert_id()
    {
        return $this->insert_id;
    }

    /**
     * Формат даты для вставки в Mongo.
     */
    const DATE_FORMAT = 'd.m.Y H:i';

    /**
     * Get working MongoCollection.
     *
     * @return \MongoCollection
     */
    protected function getCollection()
    {
        if (!$this->collection) {
            $this->setCollection();
        }
        if (!$this->collection) {
            throw new DAO\Exception('bad data source for '.$this->getName());
        }

        return $this->collection;
    }

    protected function setCollection()
    {
        $mongo = \Connector::getInstance()->getMongo();
        if (!$mongo) {
            if (\Config::get('daemon')) {
                return false;
            }
            \Common::die500('database error');
        }
        $db = \Config::get('mongo_db');
        $this->db = $mongo->selectDB($db);
        $this->collection = $mongo->selectCollection($db, $this->getName());

        if (!$this->collection) {
            $err = 'Mongo error selecting collection';
            $info = ['mongo_db' => $db];
            \logger\Log::instance()->logError($err, $info);
            \Common::die500('database error');
        }
    }

    /**
     * Prepare condition (key-value pair).
     */
    protected static function process_condition($k, $v)
    {
        $r = [];
        if (in_array(strtolower($k), ['or', 'and', 'in'], true)) {
            $k = '$'.strtolower($k);
        }
        $cond_ops = ['<', '>', '<>', '!='];
        if (in_array($k, $cond_ops, true) && is_array($v)) {
            return static::cond_cond($k, $v);
        } elseif (is_array($v)) {
            $r['k'] = $k;
            $r['v'] = static::prepare_cond($v, $k == '_id');
        } else {
            if ($k === '_id' && !is_object($v)) {
                $v = new \MongoId($v);
            }
            $r['k'] = $k;
            $r['v'] = $v;
        }

        return $r;
    }

    /**
     * Generate condition w/ conditional operator.
     */
    protected static function cond_cond($k, $v)
    {
        $trans = [
            '>'  => '$gt',
            '<'  => '$lt',
            '!=' => '$ne',
            '<>' => '$ne',
        ];
        list($kk, $vv) = each($v);
        if ($kk === '_id' && !is_object($vv)) {
            $vv = new \MongoId($vv);
        }

        return [
            'k' => $kk,
            'v' => [$trans[$k] => $vv],
        ];
    }

    /**
     * Modify simple mongo entity.
     *
     * @param array $request updated fields
     * @param int   $id      id | condition
     * @param array $params:
     *                       allowed
     *                       callback key-value processing callback
     *                       retval   return updated records
     *
     * @return [ 'message' => ok, 'rows' => 1, 'fields' => [...] ]
     */
    public function modItem($request, $id, $params = [])
    {
        $params_default = [
            'allowed'  => null,
            'callback' => null,
            'retval'   => true,
        ];
        $params = array_append($params, $params_default);
        foreach ($params as $k => $v) {
            $$k = $v;
        }
        if (empty($allowed)) {
            $allowed = (isset(static::$allowed)) ? static::$allowed : [];
        }
        $fields = [];
        foreach ($request as $k => $v) {
            if (!in_array($k, $allowed, true)) {
                continue;
            }
            if (isset(static::$checkers)
                    && isset(static::$checkers[$k])
                    && !call_user_func(static::$checkers[$k], $v)) {
                continue;
            }
            if ($callback && is_callable($callback)) {
                $v = $callback([$k => $v]);
            }

            if (isset(static::$allowedSchema)
                && isset(static::$allowedSchema[$k])
                && static::$allowedSchema[$k] == 'int'
            ) {
                $v = (int) $v;
            }

            $fields[$k] = $v;
        }
        if (count($fields) == 0) {
            \Common::die500('no fields to set', $request);
        }
        $cond = (!is_object($id) && !is_array($id))
            ? [static::IdKey => new \MongoId($id)]
            : $id;
        $r = $this->update(['$set' => $fields], $cond);
        $res = ['message' => 'ok'];
        if ($retval) {
            // return selected values
            $r = $this->select(array_keys($fields), [static::IdKey => $id]);
            $res['fields'] = self::getFirst($r);
        }

        return $res;
    }

    /**
     * Get first element in iteration.
     */
    protected static function getFirst($x)
    {
        foreach ($x as $v) {
            return $v;
        }
    }

    /**
     * Prepare condition.
     *
     * @param $condition  условие
     */
    protected static function prepare_cond($condition, $is_id = false)
    {
        $result = [];
        if ($condition === null) {
            $condition = [];
        }
        foreach ($condition as $k => $v) {
            $r = static::process_condition($k, $v);
            if (!$r) {
                continue;
            }
            $result[$r['k']] = (isset($result[$r['k']]))
                ? array_merge($result[$r['k']], $r['v'])
                : $r['v'];
        }

        return $result;
    }

    /**
     * Fetch all records.
     *
     * @param $r query result
     * @param $schema  schema
     * @param $func    transformer
     * @param $limit   limit
     */
    public function fetch_all($r, $schema = null, $func = null, $limit = null)
    {
        $result = [];
        if ($r === false) {
            return $result;
        }
        $has_limit = $limit != null;
        if (is_callable($schema)) {
            $func = $schema;
        }
        if ($schema === null && isset(static::$schema)) {
            $schema = static::$schema;
        }
        while ($row = $this->fetch_assoc($r)) {
            if (is_array($schema) && is_array($row)) {
                $row = self::enforce($schema, $row);
            }
            if ($func) {
                $row = is_string($func)
                    ? $func($row)
                    : call_user_func($func, $row);
            }
            $result[] = $row;
            if ($has_limit) {
                if (!($limit--)) {
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Fetch a record as an associative array.
     *
     * @param \MongoCursor $r
     *
     * @return array
     */
    public function fetch_assoc($r, $schema = null, $func = null)
    {
        if (!is_object($r) || !$r->valid()) {
            return false;
        }
        $row = $r->current();
        $r->next();
        if (is_callable($schema)) {
            $func = $schema;
        }
        if ($schema === null && isset(static::$schema)) {
            $schema = static::$schema;
        }
        if (is_array($schema) && is_array($row)) {
            $row = self::enforce($schema, $row);
        }
        if ($func && $row != null) {
            $row = is_string($func)
                ? $func($row)
                : call_user_func($func, $row);
        }

        return $row;
    }

    /**
     * Selection.
     *
     * Operators supported, see
     * {@link http://www.mongodb.org/display/DOCS/Atomic+Operations}
     */
    public function select($fields, $condition,
            $sort = ['_id' => 1], $limit = 0, $skip = 0)
    {
        $condition = $this->prepare_cond($condition);
        $coll = $this->getCollection();
        $info = [
            'condition' => json_encode($condition),
            'fields'    => json_encode($fields),
        ];
        \logger\Log::instance()->logInfo('MONGO SELECT', $info);
        $it = count($fields) == 0
            ? $coll->find($condition)
            : $coll->find($condition, $fields);
        if (count($sort) > 0) {
            $it = $it->sort($sort);
        }
        if ($limit > 0) {
            $it = $it->limit($limit);
        }
        if ($skip > 0) {
            $it = $it->skip($skip);
        }
        if ($it) {
            $it->rewind();
        }

        return $it;
    }

    /**
     * Functional style select.
     *
     * @param $fields
     * @param $cond
     */
    public function select_fn($fields = [], $cond = null)
    {
        $op = new MongoOperator(QueryClass::select, $fields, $cond);

        return $op->from($this);
    }

    /**
     * Make string value of id from MongoID for returned object.
     */
    public static function remapId($v)
    {
        $v['id'] = (string) $v[static::IdKey];
        unset($v['_id']);

        return $v;
    }

    /**
     * Make string value of mongo id.
     */
    public static function remapIds($items)
    {
        $items_mapped = [];
        foreach ($items as $k => $v) {
            if ($v['_id']) {
                $v['id'] = (string) $v[static::IdKey];
                unset($v['_id']);
            }
            $items_mapped[$k] = $v;
        }

        return $items_mapped;
    }

    /**
     * Make seconds from MongoDate int.
     */
    public static function remapDates($items)
    {
        if ($items instanceof \MongoDate) {
            if (isset($items->usec) && $items->usec != 0) {
                return $items->usec;
            }

            return $items->sec;
        }
        if (!is_array($items)) {
            return $items;
        }
        $items_mapped = [];
        foreach ($items as $k => $v) {
            $items_mapped[$k] = self::remapDates($v);
        }

        return $items_mapped;
    }

    /** Generate date in Mongo format */
    public static function gen_date($date = null)
    {
        if (!isset($date)) {
            $date = time();
        }
        if (is_object($date) && get_class($date) == 'DateTime') {
            return new \MongoDate($date->getTimestamp());
        }
        if (is_numeric($date)) {
            return new \MongoDate($date);
        }
    }

    /** Get new mongo id */
    public static function gen_id()
    {
        return new \MongoId();
    }

    /** Make mongo in from string */
    public static function make_id($id)
    {
        if (is_object($id)) {
            return $id;
        }
        if (\Common::is_valid_mongoId($id)) {
            return new \MongoId($id);
        }
    }

    /**
     * Update record.
     *
     * @param array $set updated fields
     * @param $condition update condition
     * @param $options
     */
    public function update($set, $condition, $options = ['upsert' => false, 'multi' => false])
    {
        $condition = $this->prepare_cond($condition);
        $coll = $this->getCollection();
        \logger\Log::instance()->logInfo('MONGO UPDATE', [
            'condition' => json_encode($condition),
            'set'       => json_encode($set),
        ]);
        if (!isset($set['$push'])
            && !isset($set['$pull'])
            && !isset($set['$set'])
            && !isset($set['$unset'])
            && !isset($set['$addToSet'])) {
            $set = ['$set' => $set];
        }

        return $coll->update($condition, $set, $options);
    }

    /**
     * Insert new record.
     *
     * @param $kv
     */
    public function save($kv, $ignore = false)
    {
        $coll = $this->getCollection();
        $r = $coll->save($kv);

        return $r;
    }

    /**
     * Insert new record.
     *
     * @param $kv
     */
    public function push($kv, $ignore = false)
    {
        $coll = $this->getCollection();
        $r = $coll->insert($kv);
        if (isset($kv['_id'])) {
            $this->insert_id = $kv['_id'];
        }

        return $r;
    }

    /**
     * Insert array of records.
     *
     * @param $fields fields
     * @param $what   values
     */
    public function insert($fields, $what, $ignore = false)
    {
        $coll = $this->getCollection();
        if (count($fields) != count($what)) {
            throw new \Exception('Field list size mismatch');
        }
        $record = array_combine($fields, $what);
        \logger\Log::instance()->logInfo('Mongo query: ', $record);
        $r = $coll->insert($record);
        if (isset($record['_id'])) {
            $this->insert_id = $record['_id'];
        }

        return $r;
    }

    /**
     * Delete rows satisfying the condition.
     */
    public function delete($condition)
    {
        $condition = $this->prepare_cond($condition);
        $coll = $this->getCollection();

        return $coll->remove($condition);
    }

    /**
     * Count fields satisfying the condition.
     *
     * @param $field
     * @param $condition
     */
    public function count($field, $condition)
    {
        $coll = $this->getCollection();

        return $coll->count($condition);
    }

    /**
     * Perform full-text search (MongoDB 2.4+).
     *
     * Requires 'text' index on one or more fields.
     *
     * @param $text   keywords to search (OR operator by defaults, use quotes for exact search)
     * @param $limit  result collection size limit (no limit by default)
     */
    public function search($text, $limit = null)
    {
        $coll = $this->getCollection();
        $coll->ensureIndex(['name' => 'text']);
        $cond = [
            'text'   => $this->getName(),
            'search' => $text,
        ];
        if ($limit) {
            $cond['limit'] = $limit;
        }

        return $this->db->command($cond);
    }
}

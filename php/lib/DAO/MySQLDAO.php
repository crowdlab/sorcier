<?php
namespace DAO;
use DAO;
/**
 * MySQL DAO: if you want to interact with MySQL, please extend this
 */
abstract class MySQLDAO implements IDAO {
	use \Singleton;
	use \DAO\Enforcer;
	use \DAO\Helpers;

	const IdKey = 'id';

	abstract public function getName();

	/**
	 * Field checker functions
	 */
	protected static $checkers = [];
	/**
	 * Transaction started
	 */
	protected $started = false;

	/**
	 * Check if required fields are set
	 */
	public static function checkRequired($rqst, &$fields = null, $for = null) {
		if (!is_array($rqst)) return false;
		$keys = array_keys($rqst);
		if ($for == null) $for = static::$required;
		foreach($for as $v) {
			if (!in_array($v, $keys, true)
					|| empty($rqst[$v]) && $rqst[$v] !== false) {
				if ($fields !== null)
					$fields []= $v;
				return false;
			}
		}
		return true;
	}

	/**
	 * Start transaction
	 */
	public function start() {
		if ($this->started) return;
		$this->perform_query('SET AUTOCOMMIT=0');
		$this->perform_query('START TRANSACTION');
		$this->started = true;
	}

	/**
	 * Roll back transaction
	 */
	public function rollback() {
		$this->perform_query('ROLLBACK');
		$this->started = false;
	}

	/**
	 * Commit transaction
	 */
	public function commit() {
		if (!$this->started) return;
		$this->perform_query('COMMIT');
		$this->started = false;
	}

	/**
	 * Check if table exists
	 */
	public function hasTable($table = null) {
		if ($table == null) $table = $this->getName();
		$q = "SHOW TABLES LIKE '$table'";
		$r = $this->perform_query($q);
		return ($this->num_rows($r) == 1);
	}

	/**
	 * Run query
	 */
	public static function perform_query($q) {
		\logger\Log::instance()->logDebug("SQL: $q");
		$_DB = \Connector::getInstance()->getMySQL();
		if (!$_DB) {
			if (\Config::get('daemon')) return false;
			\Common::die500('database error');
		}
		if (\Config::get('daemon')) {
			if (!mysqli_ping($_DB)) {
				$_DB = \Connector::getInstance()->getMySQL(true);
			}
		}
		$r = mysqli_query($_DB, $q);
		if ($r)
			return $r;
		$err = mysqli_error($_DB);
		\logger\Log::instance()->logError("perform_query error",
			['error' => $err, 'query' => $q]);
		if (\Config::get('daemon')) return false;
		if (\Config::get('debug'))
			\Common::dieError(['error' => $err, 'query' => $q]);
		\Common::die500('database error');
	}

	/**
	 * Get last insert id
	 */
	public function insert_id() {
		$_DB = \Connector::getInstance()->getMySQL();
		return (int)mysqli_insert_id($_DB);
	}

	/**
	 * Select
	 *
	 * @param array $fields
	 * @param array $condition
	 * @param array|integer $limit
	 * @param string $join
	 */
	public function select($fields, $condition = [], $limit = null,
			$orderby = null, $join = '') {
		$table_name = $this->getName();
		$fields = $this->prefixWithName($fields);
		$q = new DAO\Sql\Select($table_name, $fields, $this->prefixCond($condition),
			$limit, $orderby, $join);
		return $this->perform_query((string)$q);
	}

	/**
	 * Select count(field) where condition
	 */
	public function count($field, $condition) {
		$field = $field == 1 ? $field : "`$field`";
		$table_name = $this->getName();
		$cond = QueryGen::make_cond($condition);
		$q = "SELECT count($field) as `count`
		      FROM   `$table_name`
		      WHERE  $cond";
		return $this->fetch_assoc(
			$this->perform_query($q),
			null,
			function($row){return (int)$row['count'];}
		);
	}

	/**
	 * Update record
	 *
	 * @param array $set updated fields
	 * @param array $condition
	 */
	public function update($set, $condition, $suffix = '') {
		$cond = QueryGen::make_cond($condition);
		if (isset(static::$schema))
			$set = static::enforce(static::$schema, $set);
		$set_kv = QueryGen::make_set_kv($set);
		$sset = implode(',', $set_kv);
		$table_name = $this->getName();
		$q = "UPDATE `$table_name`
		      SET    $sset
		      WHERE  $cond
			  $suffix;";
		return $this->perform_query($q);
	}

	/**
	 * Generate join part of query
	 * @param $fields
	 * @param $table
	 * @param $prefix
	 */
	protected static function joinFields($fields, $table, $prefix) {
		$ret = [];
		foreach($fields as $v)
			$ret []= \DAO\Sql\Alias::imbue("$table.$v", "$prefix$v");
		return $ret;
	}

	/**
	 * Update record (functional)
	 *
	 * @param array $set kv
	 */
	public function update_fn($set) {
		return \DAO\FnMySQL::update($set)->from($this);
	}

	/**
	 * Functional style select
	 * @param $fields
	 * @param $cond
	 */
	public function select_fn($fields, $cond = null) {
		$fields = $this->prefixWithName($fields);
		if ($cond != null) $cond = $this->prefixCond($cond);
		return \DAO\FnMySQL::select($fields, $cond)->from($this);
	}

	/**
	 * Insert record
	 * @param $kv
	 * @param bool $ignore insert ignore
	 * @param string $suffix request suffix (like, 'ON DUPLICATE KEY UPDATE ...')
	 */
	public function push($kv, $ignore = false, $suffix = '') {
		if (isset(static::$schema))
			$kv = static::enforce(static::$schema, $kv);
		if (is_array($suffix)) $suffix = '';
		return $this->insert(array_keys($kv), array_values($kv), $ignore, $suffix);
	}

	/**
	 * Insert records
	 * @param array $fields
	 * @param array $what
	 * @param bool $ignore insert ignore
	 * @param string $suffix request suffix (like, 'ON DUPLICATE KEY UPDATE ...')
	 */
	public function insert($fields, $what = null, $ignore = false, $suffix = '') {
		// single kv array
		if ($what == null) {
			$_fields = $fields;
			$fields = array_keys($_fields);
			$what = array_values($_fields);
		}
		// multiple values to insert
		if (isset($what[0]) && is_array($what[0])) {
			$ins = array();
			foreach ($what as $v)
				$ins[] = QueryGen::make_insert($v);
			$ins = implode(',', $ins);
		} else {
			$ins = QueryGen::make_insert($what);
		}
		$fields_s = '(' . QueryGen::make_fields($fields) . ')';
		$table_name = $this->getName();
		$ign = $ignore ? 'IGNORE' : '';
		$q = "INSERT $ign
		      INTO `$table_name` $fields_s
		      VALUES $ins
		      $suffix";
		return $this->perform_query($q);
	}

	/**
	 * remove records
	 *
	 * @param array $condition
	 */
	public function delete($condition) {
		$cond = QueryGen::make_cond($condition);
		$table_name = $this->getName();
		$q = "DELETE
		      FROM `$table_name`
		      WHERE $cond";
		return $this->perform_query($q);
	}

	/**
	 * mark recored as deleted
	 * @param $condition
	 * @param $field
	 */
	public function deleteItem($condition, $field = 'deleted', $dval = 1) {
		return $this->update([$field => $dval], $condition);
	}

	/**
	 * clean up result array
	 * @param $item item
	 * @param $sup  supplementary
	 */
	public static function cleanup($item, $sup = null) {
		if (!$item) return $item;
		if (!isset(static::$supplementary) && $sup == null) return $item;
		if (!$sup) $sup = static::$supplementary;
		foreach($item as $k => $v) {
			if (in_array($k, $sup, true) && $v === null)
				unset($item[$k]);
		}
		return $item;
	}

	/**
	 * Add table name prefixes to condition fields
	 */
	public function prefixCond($cond) {
		if (!is_array($cond)) return $cond;
		$_cond = $cond;
		$cond = [];
		foreach($_cond as $k => $v) {
			if (strpos($k, '.') === false && strpos($k, '__KEY__') === false &&
					!in_array(strtolower($k), \DAO\QueryGen::$operators, true))
				$k = $this->getName().".$k";
			$cond[$k] = $v;
		}
		return $cond;
	}

	/**
	 * add table name prefix to array of records
	 */
	public function prefixWithName($array) {
		$name = $this->getName();
		return array_map(function($k) use($name) {
			if (strpos($k, '(') === false && strpos($k, '.') === false
					&& strpos($k, '__KEY__') === false &&
					!in_array(strtolower($k), \DAO\QueryGen::$operators, true))
				return "$name.$k";
			return $k;
		}, $array);
	}

	public function reindexAll($ids) {
		$count = 0;
		foreach($ids as $id) {
			try {
				$this->reindex($id, true);
			}
			catch (Elastica\Exception\AbstractException $e) {
				echo "error indexing user $id\n";
				echo $e->getMessage();
			}
			++$count;
		}
		return $count;
	}

	/**
	 * Leave only permitted fields
	 * @param $request  modification query
	 * @param $allowed  permitter fields
	 * @param $callback result handler
	 * @param $id
	 */
	protected static function getAllowedFields($request, $allowed = null,
			$callback = null, $id = null) {
		$fields = [];
		if (empty($allowed))
			$allowed = (isset(static::$allowed)) ? static::$allowed : [];
		foreach ($request as $k => $v) {
			if (!in_array($k, $allowed, true)) continue;
			if (isset(static::$checkers[$k]) &&
					!call_user_func(static::$checkers[$k], $v, $id))
				continue;
			if ($callback && is_callable($callback))
				$v = $callback([$k => $v]);

			if (isset(static::$allowedSchema)
				&& isset(static::$allowedSchema[$k])
				&& static::$allowedSchema[$k] == 'int'
			)
				$v = (int)$v;

			$fields[$k] = $v;
		}
		return $fields;
	}

	/**
	 * Modify simple entity
	 *
	 * @param array     $request  request (new values)
	 * @param integer   $id       entity id
	 * @param array     $params:
	 *    allowed  optional fields
	 *    callback key-value processing callback
	 *    retval   return whole changed record
	 *    throw    throw exception if no changes (not thrown by default)
	 *    special  special conditions checker
	 *    diff     requires retval, return changed fields
	 * @return [ 'message' => ok, 'rows' => 1, 'fields' => [...] ]
	 */
	public function modItem($request, $id, $params = []) {
		$params_default = [
			'allowed'  => null,
			'callback' => null,
			'diff'     => false,
			'retval'   => true,
			'special'  => null,
			'throw'    => true,
		];
		$params = array_append($params, $params_default);
		foreach ($params as $k => $v)
			$$k = $v;
		// transposition guard
		if (!is_array($request)) list($request, $id) = array($id, $request);
		// filter fields
		if (!is_array($request)) return null; // error
		$fields = static::getAllowedFields($request, $allowed, $callback, $id);
		if ($special && !$special($fields))
			return ['error' => \Common::InternalError, 'code' => 403];
		$new_extra = static::filterExtra($request);
		if ($throw && !count($fields) && !count($new_extra))
			\Common::die500('no fields to set', $request);
		$cond = is_array($id) ? $id : [static::IdKey => $id];
		$aff_rows = 0;
		if ($diff) {
			$r = $this->select_fn(array_keys($fields))->where($cond);
			$item = $this->fetch_assoc($r);
			$prev = self::enforce(static::$schema, $item);
		}
		if (count($fields) > 0) {
			$this->update($fields, $cond);
			$aff_rows = $this->affected_rows();
		}

		$extra = [];
		foreach($new_extra as $k => $v) {
			$r = $this->setExtra($k, $v, $id, true);
			if ($r) $extra[$k] = $r;
		}

		$res = ['message' => 'ok', 'rows' => $aff_rows];
		if (count($extra) > 0)
			$res['extra'] = $extra;
		if ($retval) {
			// return selected values
			$r = $this->select_fn(array_keys($fields))->where($cond);
			$item = self::enforce(static::$schema, self::getFirst($r->x()));
			$res['fields'] = $item;
			$res['extra']  = $extra;
			if ($diff)
				$res['diff'] = array_diff_values($item, $prev);
		}
		return $res;
	}

	/**
	 * Filter extra fields (not saved by this DAO directly)
	 */
	public static function filterExtra($fields) {
		$ret = [];
		if (!isset(static::$extraFields)) return $ret;
		foreach($fields as $k => $v) {
			if (in_array($k, static::$extraFields, true))
				$ret[$k] = $v;
		}
		return $ret;
	}

	/**
	 * Return first element of iterator (applying transform if needed)
	 */
	protected static function getFirst($x, $transform = null) {
		foreach ($x as $v)
			return $transform ? $transform($v) : $v;
	}

	/**
	 * Return affected rows count
	 */
	public function affected_rows() {
		$_DB = \Connector::getInstance()->getMySQL();
		return mysqli_affected_rows($_DB);
	}

	/**
	 * Check if entity matching condition exists
	 */
	public function exists($cond) {
		if (!is_array($cond)) $cond = [static::IdKey => $cond];
		$r = $this->select([1], $cond);
		return $r && $this->num_rows($r) > 0;
	}

	/**
	 * The function returns the count of selected rows.
	 */
	public function num_rows($r) {
		if (!$r) return 0;
		return $r instanceof \mysqli_result
			? mysqli_num_rows($r)
			: $r->num_rows();
	}

	/**
	 * Returns result row as an associative array
	 * @param $r call result
	 * @param $schema transformation schema
	 * @param $func transform function
	 */
	public function fetch_assoc($r, $schema = null, $func = null) {
		$row = $r instanceof \mysqli_result
			? mysqli_fetch_assoc($r)
			: $r->fetch_assoc();
		if (is_callable($schema))
			$func = $schema;
		if (($schema === null || is_callable($schema))
				&& isset(static::$schema))
			$schema = static::$schema;
		if (is_array($schema) && is_array($row))
			$row = self::enforce($schema, $row);
		return $func && $row
			? is_string($func) ? $func($row) : call_user_func($func, $row)
			: $row;
	}

	/**
	 * Fetch all records
	 * @param $r query result
	 * @param $schema  schema
	 * @param $func    transform function
	 */
	public function fetch_all($r, $schema = null, $func = null, $limit = null) {
		$result = [];
		if ($r === false) return $result;
		if ($r instanceof MySQLOperator) $r = $r->x();
		$has_limit = $limit != null;
		if (is_callable($schema))
			$func = $schema;
		if (($schema === null || is_callable($schema))
				&& isset(static::$schema))
			$schema = static::$schema;
		while ($row = ($r instanceof \mysqli_result
				? mysqli_fetch_assoc($r)
				: $r->fetch_assoc())) {
			if (is_array($schema) && is_array($row))
				$row = self::enforce($schema, $row);
			if ($func) {
				$row = is_string($func)
					? $func($row)
					: call_user_func($func, $row);
			}
			$result []= $row;
			if ($has_limit) {
				if (!($limit--)) break;
			}
		}
		return $result;
	}

	/**
	 * Fetch results w/ digital indices
	 * @param $r query result
	 */
	public function fetch_array($r) {
		return mysqli_fetch_array($r, MYSQL_NUM);
	}
}
?>

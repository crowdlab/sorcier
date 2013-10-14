<?php
namespace DAO;
use DAO;
use DAO\QueryClass as QC;

/**
 * Functional MySQL Operator
 */
class MySQLOperator {
	use DAO\Operator\Traits\Mongo;
	use DAO\Operator\Traits\XHelpers;

	/** query class */
	protected $class;
	/** main table */
	protected $from;
	/** dao */
	protected $dao;
	/** order by what */
	protected $orderby;
	/** order by desc */
	protected $orderbyDesc = false;
	/** group by */
	protected $groupby;
	/** having */
	protected $having;
	/**
	 * массив [
	 *	'table' => 'table',
	 *	'on'    => condition
	 * ]
	 */
	protected $join = [];
	/** Limitation */
	protected $limit;
	/** Skip */
	protected $skip;
	/** Fields */
	protected $fields = [];
	/** Condition */
	protected $condition = 0; // false
	/** IGNORE (в случае INSERT) */
	protected $ignore = false;
	/** SET (in case of UPDATE) */
	protected $set;
	/** INSERT suffix */
	protected $suffix;
	/**
	 * functional predicate
	 *
	 * join operator supported only:
	 * `$obj->on($u->isAdmin())->join(...)`
	 */
	protected $predicate = true;
	/**
	 * Query classes
	 */
	protected $classes_allowed =
		['select', 'update', 'delete', 'insert'];
	protected $helper = null;

	/**
	 * Create MySQLOperator object
	 *
	 * @param $class one of $this->classes_allowed
	 * @param $from operation table
	 * @param $param1 depend on class
	 *
	 * * select: $fields, $condition
	 * * update: $set, $condition
	 * * insert: $fields, $values
	 * * delete: $condition
	 *
	 * @param $param2 see param1
	 */
	public function __construct($class, $param1 = null, $param2 = null, $param3 = null) {
		if (!in_array($class, $this->classes_allowed, true))
			throw new InvalidArgumentException("incorrect class");
		$this->class = $class;
		switch ($class) {
			case QC::select:
				if ($param1)
					$this->fields = $param1;
				if ($param2)
					$this->condition = $param2;
				break;
			case QC::update:
				if ($param1)
					$this->set = $param1;
				if ($param2)
					$this->condition = $param2;
				break;
			case QC::delete:
				if ($param1)
					$this->condition = $param1;
				break;
			case QC::insert:
				if ($param1)
					$this->fields = $param1;
				if ($param2)
					$this->ignore = $param2;
				if ($param3)
					$this->suffix = $param3;
				break;
		}
	}

	protected static function quoteTableName($v) {
		return (strpos($v, ' ') !== false) ? $v : "`$v`";
	}

	public function in($from) {
		return $this->from($from);
	}

	/**
	 * добавить поля
	 */
	public function add_fields($fields) {
		$this->fields = array_merge($this->fields, $fields);
		return $this;
	}

	public function into($from) {
		return $this->from($from);
	}

	public function from($from) {
		if (is_object($from)) {
			$this->dao = $from;
			$from = $from->getName();
		}
		$this->from = static::quoteTableName($from);
		return $this;
	}

	public function orderBy($what, $desc = false) {
		$this->orderby = $what;
		$this->orderbyDesc = $desc;
		return $this;
	}

	public function set($set) {
		$this->set = $set;
		return $this;
	}

	public function group_by($groupby) {
		$this->groupby = $groupby;
		return $this;
	}

	public function groupBy($groupby) {
		$this->groupby = $groupby;
		return $this;
	}

	public function having($having) {
		$this->having = $having;
		return $this;
	}

	public function limit($limit, $skip = null) {
		$this->limit = $limit;
		if (is_numeric($skip))
			$this->skip = $skip;
		return $this;
	}

	/**
	 * JOIN
	 * @param $on JOIN condition
	 * @param $table table
	 */
	public function join($table, $on = null, $prefix = '') {
		if (!$this->predicate) {
			$this->predicate = true;
			return $this;
		}
		if (is_object($table)) {
			$this->join = array_merge($this->join, $table->get());
			return $this;
		}
		if (is_array($table)) {
			$table = $table['table'];
			$on = $table['on'];
		}
		if (is_array($on)) // join condition as array
			$on = \DAO\QueryGen::make_cond($on, true, '', true);
		$this->join []= [
			'on'     => $on,
			'table'  => $table,
			'prefix' => $prefix
		];
		return $this;
	}

	/**
	 * Pre-cache items for
	 * @param $ids
	 */
	public function precache($ids) {
		if ($this->helper)
			return $this->helper->precache($ids);
	}

	/**
	 * Predicate for next join
	 */
	public function on($cond) {
		$this->predicate = $cond;
		return $this;
	}

	/**
	 * LEFT JOIN
	 */
	public function left_join($table, $on) {
		if (!$this->predicate) {
			$this->predicate = true;
			return $this;
		}
		return $this->join($table, $on, 'LEFT');
	}

	public function where($condition) {
		if ($this->dao)
			$condition = $this->dao->prefixCond($condition);
		$this->condition = $condition;
		return $this;
	}

	public function select($fields) {
		if ($this->class != 'select')
			throw new InvalidArgumentException("incorrect class");
		$this->fields = $fields;
		return $this;
	}

	protected $xResult = null;

	/**
	 * Выполнить запрос
	 * @param $count_affected выдавать количество задействованных строк
	 * иначе возвращает итератор для выборки и true/false в других случаях
	 */
	public function x($count_affected = false) {
		if ($this->xResult) return $this->xResult;
		$dao = ManagedDAO::getInstance();
		$r = $dao->perform_query((string) $this);
		if ($count_affected) {
			switch($this->class) {
				case QC::select:
					$aff = $dao->num_rows($r);
					break;
				case QC::delete:
				case QC::update:
				case QC::insert:
					$aff = $dao->affected_rows($r);
					break;
			}
			return $aff;
		}
		$this->xResult = $this->class == QC::select
			? new DAOIterator($r, $this->dao, $this->helper)
			: (bool) $r;
		return $this->xResult;
	}
	
	/**
	 * Добавить блок on duplicate key update
	 */
	public function on_duplicate_key_update($update) {
		$this->set_dup = $update;
		return $this;
	}

	/** generate select query for condition */
	protected function genSelect($cond) {
		$join = '';
		foreach ($this->join as $v) {
			$join .= " {$v['prefix']} JOIN {$v['table']} ON " .
				QueryGen::make_cond($v['on']);
		}
		$q = "SELECT " .
			QueryGen::make_fields($this->fields) .
			" FROM {$this->from} $join WHERE $cond";
		if ($this->groupby) {
			$q .= " GROUP BY {$this->groupby}";
			if ($this->having) {
				$q .= " HAVING " .
					QueryGen::make_cond($this->having);
			}
		}
		if ($this->orderby) {
			$q .= " ORDER BY {$this->orderby}";
			if ($this->orderbyDesc)
				$q .= " DESC";
		}
		if ($this->limit) {
			if ($this->skip)
				$q .= " LIMIT {$this->skip}, {$this->limit}";
			else if (!is_array($this->limit))
				$q .= " LIMIT {$this->limit}";
			else
				$q .= " LIMIT {$this->limit[0]}, {$this->limit[1]}";
		}
		return $q;
	}

	/** generate insert query */
	protected function genInsert() {
		$what = array_values($this->fields);
		$fields = array_keys($this->fields);
		$ins = QueryGen::make_insert($what);
		$fields_s = '(' . QueryGen::make_fields($fields) . ')';
		$ign = $this->ignore ? ' IGNORE' : '';
		if (is_object($this->suffix))
			$this->suffix = (string) $this->suffix;
		// on duplicate key update
		if (isset($this->set_dup) && $this->set_dup) {
			$set_kv = QueryGen::make_set_kv($this->set_dup);
			$sset = implode(',', $set_kv);
			$this->suffix = " ON DUPLICATE KEY UPDATE $sset";
		}
		$q = "INSERT$ign
			INTO {$this->from}
			$fields_s
			VALUES $ins
			{$this->suffix}";
		return $q;
	}
	/**
	 * generate query text
	 */
	public function __toString() {
		if ($this->condition == null) $this->condition = [];
		$cond = QueryGen::make_cond($this->condition);
		switch($this->class) {
			case QC::select:
				$q = $this->genSelect($cond);
				break;
			case QC::insert:
				$q = $this->genInsert();
				break;
			case QC::update:
				$set_kv = QueryGen::make_set_kv($this->set);
				$sset = implode(',', $set_kv);
				$q = "UPDATE {$this->from}
					SET $sset
					WHERE $cond;";
				break;
			case QC::delete:
				$q = "DELETE FROM {$this->from}
					WHERE $cond";
				break;
			default:
				throw new \Exception("Unsupported query class {$this->class}");
		}
		return $q;
	}
}
?>

<?php
namespace DAO\Sql;
use DAO\Sql;

/**
 * Sql join class
 */
class Join {
	/** generated query */
	protected $q;
	/** join predicate */
	protected $predicate = true;

	/**
	 * Предикат
	 */
	public function on($cond) {
		$this->predicate = $cond;
		return $this;
	}

	/**
	 * Создать объект и вернуть его
	 * @param $table_name таблица
	 * @param $cond условие
	 * @param $prefix префикс (left, right, inner, ...)
	 */
	public static function imbue($table_name = null, $cond = null, $prefix = '') {
		return new static($table_name, $cond, $prefix);
	}

	/**
	 * Добавить еще один join
	 * @param $table_name таблица
	 * @param $cond условие
	 * @param $prefix префикс (left, right, inner, ...)
	 */
	public function left_join($table_name, $cond) {
		if (!$this->predicate) {
			$this->predicate = true;
			return $this;
		}
		$this->q .= (string) self::imbue($table_name, $cond, "LEFT");
		return $this;
	}

	/**
	 * Добавить еще один join
	 * @param $table_name таблица
	 * @param $cond условие
	 * @param $prefix префикс (left, right, inner, ...)
	 */
	public function join($table_name, $cond, $prefix = '') {
		if (!$this->predicate) {
			$this->predicate = true;
			return $this;
		}
		$this->q .= (string) self::imbue($table_name, $cond, $prefix);
		return $this;
	}

	/**
	 * Создать join
	 * @param $table_name таблица
	 * @param $cond условие
	 * @param $prefix префикс (left, right, inner, ...)
	 */
	public function __construct($table_name = null, $cond = null, $prefix = '') {
		if (!empty($table_name))
			$this->q = " $prefix JOIN $table_name ON " .
				\DAO\QueryGen::make_cond($cond, true, '', true); // noescape on
		else $this->q = ''; // empty join
	}

	public function __toString() {
		return $this->q;
	}
}

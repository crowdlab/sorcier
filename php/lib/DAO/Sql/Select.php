<?php
namespace DAO\Sql;
use DAO\Sql;

/**
 * SqlExpr-like generator for inner selects
 */
class Select {
	protected $q;

	/**
	 * Конструктор запроса
	 * @param $table_name имя таблицы
	 * @param $fields поля
	 * @param $condition условие
	 * @param $limit ограничение (число, либо два числа (start, limit), либо строка "limit ...")
	 * @param $orderby по чему сортировать (можно добавить DESC)
	 * @param $join join
	 */
	public function __construct($table_name, $fields = [], $condition = [],
			$limit = null, $orderby = null, $join = '') {
		$sfields = count($fields) > 0
			? \DAO\QueryGen::make_fields($fields)
			: '1';
		$cond = \DAO\QueryGen::make_cond($condition);
		$q = "SELECT $sfields
		      FROM $table_name
		      $join
		      WHERE $cond";
		if (isset($orderby)) {
			$q .= " ORDER BY $orderby";
		}
		if (isset($limit)) {
			if (is_numeric($limit))
				$limit = [$limit];
			if (is_array($limit)) {
				$limit = \Common::intArray($limit);
				if (in_array(count($limit), [1, 2], true))
					$q .= " LIMIT " . implode(",", $limit);
			} else if (is_string($limit)) { // limit $start, $limit
				$q .= " $limit";
			}
		}
		$this->q = $q;
	}

	/**
	 * Получить текст запроса
	 */
	public function __toString() { return $this->q; }
}
?>

<?php
namespace DAO;
use DAO;

/**
 * Pageable collections operations
 */
trait Pageable {
	/**
	 * Get pageable object
	 *
	 * @param $op    selection operator
	 * @param $page  current page (1 by default)
	 * @param $items_per_page
	 * @param $field field to count by (id by default)
	 * @return [
	 *	'items' => [...],
	 *	'pager' => ['no' => 5, 'current' => $page]
	 * ]
	 */
	protected function getPageable($op, $page = 1, $items_per_page = 20,
			$field = 'id') {
		$cop = clone($op);
		$my = $this instanceof MySQLDAO;
		if ($my) {
			$count = $cop
				->select(\DAO\Sql\Expr::imbue("COUNT('$field')", 'c'))
				->fetch_assoc()['c'];
		} else {
			$count = $op->x()->count();
		}
		$pager = [
			'current' => (int) $page,
			'no'      => int_divide($count, $items_per_page)
						+ ($count % $items_per_page ? 1 : 0)
		];
		$op = $op->limit($items_per_page, ($page - 1) * $items_per_page);
		$items = $my ? $op->fetch_all() : iterator_to_array($op->x());
		if ($this instanceof MongoDAO) $items = array_values(static::remapIds($items));
		return [
			'items' => $items,
			'pager' => $pager
		];
	}
}
?>

<?php
namespace DAO;
use DAO;
trait SelectListTrait {
	/**
	 * Выбрать по списку id сохраняя порядок
	 * @param $fields
	 * @param $ids id список
	 * @param $default_cond условие если списка нет/пустой -- по умолчанию нет ограничений
	 * @param $limit limit
	 */
	public function select_list($fields, $ids, $default_cond = [], $limit = null) {
		$cond = $ids
			? [static::IdKey => ['$in' => $ids]]
			: $default_cond;
		$join = implode(',', $ids);
		return $this->select($fields, $cond, $limit,
			$join ? " FIND_IN_SET(id,'$join')" : null);
	}
}
?>

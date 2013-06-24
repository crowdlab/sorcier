<?php
namespace DAO;
use DAO;

/**
 * This trait may be used to extend DAO w/ collection-like mod capability
 *
 * Please set static::$parent_key for correct operation
 */
trait CollectionTrait {
	/**
	 * add item to collection
	 * @param $uid   root entity id
	 * @param $value item
	 */
	public function add($uid, $value) {
		$value = static::checkAllowed($value);
		$value[static::$parent_key] = $uid;
		$r = $this->push($value, true);
		if ($r) return $this->insert_id();
		return ['error' => 'could not add'];
	}

	/**
	 * rm collection item
	 * @param $uid    root entity id
	 * @param $id     entity id
	 * @param $idkey  id key name
	 */
	public function delItem($uid, $id) {
		$cond = [
			static::$parent_key => $uid,
			static::IdKey => $id
		];
		return $this->delete($cond);
	}

	/**
	 * Clean up collection item
	 * @param $uid    root entity id
	 * @param $except except these
	 * @param $idkey  id key name
	 */
	public function clean($uid, $except = []) {
		$cond = [static::$parent_key => $uid];
		if (count($except))
			$cond[static::IdKey] = ['$notin' => $except];
		return $this->delete($cond);
	}

	/**
	 * Add or modify entity
	 * @param $eid    root entity id (user for contact, for example)
	 * @param $value  value (may have id field -- mod called in this case)
	 * @param $idkey  id key name
	 */
	public function addMod($eid, $value) {
		if (method_exists($this, 'addModHelper'))
			$value = $this->addModHelper($value);
		if ($value === false)
			return ['error' => 'not modified', 'code' => 403];
		$params = ['id_key' => static::IdKey, 'throw' => false];
		if (isset($value[static::IdKey]))
			$cond = [
				static::IdKey => $value[static::IdKey],
				static::$parent_key => $eid
			];
		return (isset($value[static::IdKey]))
			// if only id specified, do nothing
			? (count($value) > 1
				? $this->modItem($value, $cond, $params)
				: 1)
			: $this->add($eid, $value);
	}
}
?>

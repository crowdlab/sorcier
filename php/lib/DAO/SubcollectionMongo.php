<?php
namespace DAO;
use DAO;

/**
 * Mongo subcollection operations
 *
 * Please implement static::getParentDAO() to use this trait
 * Also use DAO\Helpers
 */
trait SubcollectionMongo {
	/**
	 * Get subcollection items
	 *
	 * Do not use this if you fetch whole item
	 */
	public function get($uid) {
		$dao = static::getParentDAO();
		$name = $dao->getName();
		$cond = [static::$parent_key => static::make_id($uid)];
		$r = $dao->select([$name], $cond);
		// todo: schema?
		return $dao->fetch_all($r);
	}

	/**
	 * add item to collection
	 * @param $uid   root entity id
	 * @param $value item
	 */
	public function add($uid, $value) {
		$value = static::checkAllowed($value);
		$dao = static::getParentDAO();
		$name = $dao->getName();
		if (!isset($value[static::IdKey]))
			$value[static::IdKey] = new \MongoId();
		else if (is_string($value[static::IdKey]))
			$value[static::IdKey] = new \MongoId($value[static::IdKey]);
		$push = ['$push' => [$name => $value]];
		$cond = [static::$parent_key => static::make_id($uid)];
		$r = $dao->update($push, $cond, ['upsert' => true]);
		if ($r) return $dao->insert_id();
		return ['error' => 'could not add'];
	}

	/**
	 * rm collection item
	 * @param $uid    root entity id
	 * @param $id     entity id
	 * @param $idkey  id key name
	 */
	public function delItem($uid, $id) {
		$cond = [static::$parent_key => static::make_id($uid)];
		$dao = static::getParentDAO();
		$name = $dao->getName();
		$pull = ['$pull' => [
			$name => [static::IdKey => $id]
		]];
		return $dao->update($pull, $cond);
	}

	/**
	 * Clean up collection item
	 * @param $uid    root entity id
	 * @param $idkey  id key name
	 */
	public function clean($uid) {
		$cond = [static::$parent_key => static::make_id($uid)];
		$dao = static::getParentDAO();
		$name = $dao->getName();
		$unst = ['$unset' => [$name => 1]];
		return $dao->update($unst, $cond);
	}

	/**
	 * Modify subcollection item
	 */
	protected function mod($value, $cond, $subcond) {
		$dao = static::getParentDAO();
		$name = $dao->getName();
		$key = "$name.".static::IdKey;
		$cond[$key] = $subcond[static::IdKey];
		$mv = [];
		foreach ($value as $k => $v)
			$mv["$name.$.$k"] = $v;
		$st = ['$set' => $mv];
		$dao->update($st, $cond);
	}

	/**
	 * Fix id for correct operations
	 */
	protected function addModHelper($v) {
		if (isset($v[static::IdKey]) && is_string($v[static::IdKey]))
			$v[static::IdKey] = new \MongoId($v[static::IdKey]);
		return $v;
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
		$params = ['throw' => false];
		if (isset($value[static::IdKey])) {
			$cond = [static::$parent_key => static::make_id($eid)];
			$subcond = [static::IdKey => $value[static::IdKey]];
		}
		return (isset($value[static::IdKey]))
			// if only id specified, do nothing
			? (count($value) > 1
				? $this->mod($value, $cond, $subcond)
				: 1)
			: $this->add($eid, $value);
	}
}

?>

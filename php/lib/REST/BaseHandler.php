<?php
namespace REST;
use REST;
use Tonic;
/**
 * Base class for rest collection handlers
 */
abstract class BaseHandler extends Tonic\Resource {
	/**
	 * DAO must use CollectionTrait!
	 */
	abstract public static function getDAO();
	/**
	 * Post-modify function (after POST/DELETE)
	 */
	public function postMod() { }

	/**
	 * @method DELETE
	 * @provides application/json
	 */
	function delete() {
		$id = $this->id;
		if ($id != \UserSingleton::getInstance()->getId())
			return new Tonic\Response(Tonic\Response::FORBIDDEN);
		$cid = $this->cid;
		$cdao = static::getDAO();
		$r = $cdao->delItem($id, $cid);
		$this->postMod();
		return tonicResponse(Tonic\Response::OK, $r);
	}

	/**
	 * @method POST
	 * @provides application/json
	 */
	function post() {
		$uid = $this->id;
		if ($uid != \UserSingleton::getInstance()->getId())
			return new Tonic\Response(Tonic\Response::FORBIDDEN);
		$cid = $this->cid;
		$cdao = static::getDAO();
		$params = (empty($_REQUEST))
			? (array) $this->request->data
			: $_REQUEST;
		if (!isset($params['id']))
			$params['id'] = $cid;
		$r = $cdao->addMod($uid, $params);
		$this->postMod();
		// return new value
		$r = $cdao->get($uid, $cid);
		return tonicResponse(Tonic\Response::OK, $r);
	}

	/**
	 * @method GET
	 * @provides application/json
	 */
	function get() {
		$id = $this->id;
		$cid = $this->cid;
		$cdao = static::getDAO();
		$r = $cdao->get($id, $cid);
		return tonicResponse(Tonic\Response::OK, $r);
	}
}
?>

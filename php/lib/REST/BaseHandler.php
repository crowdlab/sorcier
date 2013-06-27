<?php
namespace REST;
use REST;
use Tonic;
/**
 * Base class for rest collection handlers
 * Please implement static getDAO(), which returns collection DAO
 */
abstract class BaseHandler extends Tonic\Resource {
	use Shifter;
	/**
	 * Post-modify function (after POST/DELETE)
	 */
	protected function postMod() { }

	/**
	 * Function to check security on post/delete/get
	 */
	protected function securityCheck($method = 'get') { return true; }

	/**
	 * @method DELETE
	 * @provides application/json
	 */
	function delete() {
		$id = $this->id;
		if ($id != \UserSingleton::getInstance()->getId()
			|| !$this->securityCheck(__METHOD__))
			return new Tonic\Response(Tonic\Response::FORBIDDEN);
		list($id, $cid) = $this->getShifted();
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
		if ($uid != \UserSingleton::getInstance()->getId()
			|| !$this->securityCheck(__METHOD__))
			return new Tonic\Response(Tonic\Response::FORBIDDEN);
		list($id, $cid) = $this->getShifted();
		$cdao = static::getDAO();
		$params = (empty($_REQUEST))
			? (array) $this->request->data
			: $_REQUEST;
		if (!isset($params['id']))
			$params['id'] = $cid;
		$r = $cdao->addMod($id, $params);
		$this->postMod();
		// return new value
		$r = $cdao->get($id, $cid);
		return tonicResponse(Tonic\Response::OK, $r);
	}

	/**
	 * @method GET
	 * @provides application/json
	 */
	function get() {
		list($id, $cid) = $this->getShifted();
		$cdao = static::getDAO();
		if (!$this->securityCheck(__METHOD__))
			return new Tonic\Response(Tonic\Response::FORBIDDEN);
		$r = $cdao->get($id, $cid);
		return tonicResponse(Tonic\Response::OK, $r);
	}
}
?>

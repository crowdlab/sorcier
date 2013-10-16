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
		$id = $this->getUid();
		$u = \UserSingleton::getInstance();
		if ($id != $u->getId() && !$u->isAdmin()
				|| !$this->securityCheck(__METHOD__))
			return new Tonic\Response(Tonic\Response::FORBIDDEN);
		list($id, $cid) = $this->getShifted();
		$cdao = static::getDAO();
		$r = $cdao->delItem($id, $cid);
		$this->postMod();
		return tonicResponse(Tonic\Response::OK, $r);
	}

	protected function getUid() {
		return $this->id;
	}

	/**
	 * @method POST
	 * @provides application/json
	 */
	function post() {
		$uid = $this->getUid();
		$u = \UserSingleton::getInstance();
		if (!($uid == $u->getId() && $this->securityCheck(__METHOD__)
			|| $u->isAdmin()))
			return new Tonic\Response(Tonic\Response::FORBIDDEN);
		list($id, $cid) = $this->getShifted();
		$cdao = static::getDAO();
		$params = (empty($_REQUEST))
			? (array) $this->request->data
			: $_REQUEST;
		if (!isset($params['id']))
			$params['id'] = $cid;
		$lang = isset($this->lang)
			? $this->lang
			: null;
		$r = $cdao->addMod($id, $params, $lang);
		if (!$r || isset($r['error']))
			return tonicResponse(Tonic\Response::INTERNALSERVERERROR, $r);

		$this->postMod();
		// return new value
		$r = $cdao->get($id, $cid, $lang);
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
		$lang = isset($this->lang)
			? $this->lang
			: null;
		$r = $cdao->get($id, $cid, $lang);
		if ($r == null)
			return tonicResponse(Tonic\Response::NOTFOUND);
		return tonicResponse(Tonic\Response::OK, $r);
	}
}
?>

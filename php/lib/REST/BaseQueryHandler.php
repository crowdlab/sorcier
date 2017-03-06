<?php

namespace REST;

use REST;
use Tonic;

/**
 * Base class for rest collection query handlers
 * Please implement static getDAO(), which returns collection DAO.
 */
abstract class BaseQueryHandler extends Tonic\Resource
{
    use Shifter;

    /**
     * precheck function can reject request if needed.
     *
     * @param $error Tonic\Response
     */
    public function precheck($params, &$error = null)
    {
        return true;
    }

    /**
     * Post-add function (after PUT).
     */
    public function postPut()
    {
    }

    /**
     * @method POST
     * @provides application/json
     */
    public function post()
    {
        return $this->put();
    }

    protected function getUid()
    {
        return $this->id;
    }

    /**
     * @method PUT
     * @provides application/json
     */
    public function put()
    {
        $uid = $this->getUid();
        $u = \UserSingleton::getInstance();
        if ($uid != $u->getId() && !$u->isAdmin()) {
            return new Tonic\Response(Tonic\Response::FORBIDDEN);
        }
        $cdao = static::getDAO();
        $params = (array) $this->request->data;

        $error = [];
        if (!$this->precheck($params, $error)) {
            return $error;
        }
        // we need to go deeper
        list($uid, $cid) = $this->getShifted();
        $cid = $cdao->addMod($uid, $params);
        if (is_array($cid) && isset($cid['error'])) {
            $r = ['error' => 'use POST to modify existing values'];

            return tonicResponse(Tonic\Response::BADREQUEST, $r);
        }
        $r = $cdao->get($uid, $cid);

        return tonicResponse(Tonic\Response::OK, $r);
    }

    /**
     * Getter access control.
     *
     * @return true if allowed, false otherwise
     */
    protected function getAccessCheck($uid, $cid)
    {
        return true;
    }

    /**
     * @method GET
     * @provides application/json
     */
    public function get()
    {
        list($uid, $cid) = $this->getShifted();
        if (!static::getAccessCheck($uid, $cid)) {
            return new Tonic\Response(Tonic\Response::FORBIDDEN);
        }
        $dao = static::getDAO();
        $r = $dao->get($uid);

        return tonicResponse(Tonic\Response::OK, $r);
    }
}

<?php

namespace DAO\Operator\Traits;

/**
 * Additional mongo-related operators.
 */
trait Mongo
{
    /**
     * Mongo enrich: add a collection of items from mongo to every record.
     *
     * @param IDAO   $with   mongo dao
     * @param string $idkey  parent entity id key
     * @param array  $cond   condition (shall include IdMarker)
     * @param string $key    output key name
     * @param array  $fields selection fields
     * @param func   $mapper transform function
     */
    public function mongo_enrich($with, $idkey, $cond, $key = null,
            $fields = null, $mapper = null)
    {
        if (!$this->helper) {
            $this->helper = new \DAO\Operator\Container();
        }
        $op = new \DAO\Operator\MongoEnrich($with, $idkey, $cond, $key,
            $fields, $mapper);
        $this->helper->add($op);

        return $this;
    }
}

<?php
namespace DAO\Operator;
use DAO\Operator;

/** operator container */
class Container implements IOperator {
	protected $ops = [];

	/**
	 * add operator to chain
	 *
	 * @param IOperator $Op
	 */
	public function add($op) {
		$this->ops []= $op;
		return $this;
	}

	public function enrich($r) {
		foreach ($this->ops as $op)
			$r = $op->enrich($r);
		return $r;
	}

	public function enrichAll($r) {
		foreach ($r as &$v) {
			foreach ($this->ops as $op)
				$v = $op->enrich($v);
		}
		return $r;
	}

	public function precache($ids) {
		foreach ($this->ops as &$v)
			$v->precache($ids);
	}
}
?>

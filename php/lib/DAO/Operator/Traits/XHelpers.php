<?php
namespace DAO\Operator\Traits;
use DAO\Operator\Traits;

/**
 * Вызовы в обход x()
 */
trait XHelpers {
	/**
	 * Если забыть сделать x
	 */
	public function fetch_all($schema = null, $func = null) {
		if (is_callable($schema) && $func == null) {
			$func = $schema;
			$schema = null;
		}
		if (isset($this->dao) && $schema === null) {
			$c = get_class($this->dao);
			if (isset($c::$schema))
				$schema = ($schema === null)
					? $c::$schema
					: array_merge($schema, $c::$schema);
		}
		return $this->x()->fetch_all($schema, $func);
	}

	/**
	 * Если забыть сделать x
	 */
	public function fetch_assoc($schema = null, $func = null) {
		if (is_callable($schema) && $func == null) {
			$func = $schema;
			$schema = null;
		}
		if (isset($this->dao)) {
			$c = get_class($this->dao);
			if (isset($c::$schema))
				$schema = ($schema === null)
					? $c::$schema
					: array_merge($schema, $c::$schema);
		}
		return $this->x()->fetch_assoc($schema, $func);
	}

	/**
	 * Если забыть сделать x
	 */
	public function num_rows() {
		return $this->x()->num_rows();
	}
}
?>

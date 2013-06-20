<?php
namespace DAO\Sql;
use DAO\Sql;

/**
 * A base class for sql numerics
 */
class Num {
	private $what;

	public function __construct($what) {
		if (!is_numeric($what))
			throw new \InvalidArgumentException("$what is not a number");
		$this->what = $what;
	}

	/**
	 * generate sql statement for num construct
	 */
	public static function imbue($what) {
		return new static($what);
	}

	/**
	 * generate sql statement for alias construct
	 */
	public function __toString() {
		return $this->what;
	}
}

?>

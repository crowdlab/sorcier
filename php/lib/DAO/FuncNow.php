<?php
namespace DAO;
use DAO;

/**
 * Function that maps to SQL NOW()
 *
 * @see DAO\Func
 */
class FuncNow {
	public function __toString() { return "NOW()"; }
}

?>

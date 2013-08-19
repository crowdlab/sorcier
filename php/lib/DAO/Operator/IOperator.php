<?php
namespace DAO\Operator;
use DAO\Operator;

/** operator interface */
interface IOperator {
	/** enrich record */
	function enrich($r);
}
?>

<?php
namespace DAO;
use DAO;
/**
 * DAO interface
 *
 * This interface was created assuming that most data access operations
 * on different storage backends can be generalized to 5 primitives:
 * select, push, update, delete, count.
 */
interface IDAO {
	/**
	 * Select: fetch data (exactly $fields) specified by $condition
	 * @param $fields    field list
	 * @param $condition fetch condition
	 */
	public function select($fields, $condition);
	/**
	 * Update: modify records specified by $condition with $set
	 * @param $set       modify operator (most likely, kv-array)
	 * @param $condition update condition
	 */
	public function update($set, $condition);
	/**
	 * Insert record to database
	 * @return record id or false on failure
	 */
	public function push($kv, $ignore = false);
	/**
	 * Remove records from database based on condition
	 */
	public function delete($condition);
	/**
	 * Count records based on $condition
	 * @param $field if specified, count unique instances of $field
	 * @param $condition
	 */
	public function count($field, $condition);
}
?>

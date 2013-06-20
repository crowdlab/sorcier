<?php
namespace DAO;
use DAO;

/**
 * MySQL selection results iterator
 */
class DAOIterator implements \Iterator {
	protected $result;
	protected $row;
	protected $pos;

	public function __construct($result) {
		$this->result = $result;
		$this->pos = 0;
	}

	public function num_rows() {
		if (!$this->result) return 0;
		return mysqli_num_rows($this->result);
	}

	public function fetch_all($schema = []) {
		$r = [];
		while($row = mysqli_fetch_assoc($this->result)) {
			$r[]= \DAO\MySQLDAO::enforce($schema, $row);
		}
		return $r;
	}

	public function fetch_assoc($schema = []) {
		return \DAO\MySQLDAO::enforce($schema, mysqli_fetch_assoc($this->result));
	}

	public function current() {
		return $this->row;
	}

	public function key() {
		return $this->pos;
	}

	public function rewind() {
		mysqli_data_seek($this->result, 0);
		$this->pos = 0;
		$this->row = $this->fetch_assoc();
	}

	public function next() {
		$this->row = $this->fetch_assoc();
		$this->pos++;
	}

	public function valid() {
		return (bool) $this->row;
	}
}
?>

<?php
namespace DAO;
use DAO;

/**
 * MySQL selection results iterator
 */
class DAOIterator implements \Iterator {
	/** результат запроса */
	protected $result;
	/** текущая запись */
	protected $row;
	/** текущая позиция */
	protected $pos;
	/** объект-модификатор (для сопряжения источников данных) */
	protected $helper;
	/** объект доступа к данным (для схемы) */
	protected $dao;

	/**
	 * Построить итератор
	 */
	public function __construct($result, $dao = null, $helper = null) {
		$this->result = $result;
		$this->pos = 0;
		if ($helper)
			$this->helper = $helper;
		if ($dao)
			$this->dao = $dao;
	}

	public function num_rows() {
		if (!$this->result) return 0;
		return mysqli_num_rows($this->result);
	}

	public function fetch_all($schema = null, $func = null) {
		$r = [];
		if (is_callable($schema) && $func == null) {
			$func = $schema;
			$schema = null;
		}
		if ($schema === null)
			if ($this->dao && isset($this->dao->schema))
				$schema = $this->dao->schema;
			else
				$schema = [];
		while($row = mysqli_fetch_assoc($this->result)) {
			$row = \DAO\MySQLDAO::enforce($schema, $row);
			$r[]= $row;
		}
		if ($this->helper)
			$r = $this->helper->enrichAll($r);
		if ($func)
			foreach($r as &$row)
				$row = call_user_func($func, $row);
		return $r;
	}

	public function fetch_assoc($schema = null, $func = null) {
		if (is_callable($schema) && $func == null) {
			$func = $schema;
			$schema = null;
		}
		if ($schema === null)
			if ($this->dao && isset($this->dao->schema))
				$schema = $this->dao->schema;
			else
				$schema = [];
		$r = \DAO\MySQLDAO::enforce($schema, mysqli_fetch_assoc($this->result));
		if ($r && $this->helper)
			$r = $this->helper->enrich($r);
		if ($func)
			foreach($r as &$row)
				$row = call_user_func($func, $row);
		return $r;
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

<?php
namespace DAO;
use DAO;
/**
 * RedisDAO: if you want to interact with Redis, please, extend this.
 */
abstract class RedisDAO {
	use \Singleton;
	use \DAO\RedisList;

	/**
	 * Return redis connector
	 */
	protected function redis() {
		return \Connector::getInstance()->getRedis();
	}

	/**
	 * Context
	 * @return string
	 */
	abstract public function getName();
	/**
	 * Default storage timeout
	 * @return integer
	 */
	abstract public function getTimeout();

	/**
	 * Select by hash (single or multi)
	 * @param mixed $fields
	 *	null - получить все
	 *	string - получить значение по ключу
	 *	array - получить некотырые ключи
	 * @return array of $field => $value
	 */
	public function selectHash($fields = null) {
		switch (gettype($fields)) {
		case 'array':
			$res = $this->redis()->hMGet($this->getName(), $fields);
			break;
		case 'string':
			$res = $this->redis()->hGet($this->getName(), $fields);
			break;
		case 'NULL':
			$res = $this->redis()->hGetAll($this->getName());
			break;
		default:
			$res = null;
		}
		return $res;
	}

	/**
	 * Update by hash
	 * @param array $fields key-value
	 * @return boolean
	 */
	public function updateHash($fields) {
		return $this->insertHash($fields);
	}

	/**
	 * Insert into hash
	 * @param array $fields key-value
	 * @return boolean
	 */
	public function insertHash($fields, $val = null) {
		if ($val != null && is_string($fields))
			$fields = [$fields => $val];
		$res = $this->redis()->hMset($this->getName(), $fields);
		$this->redis()->setTimeout($this->getName(), $this->getTimeout());
		return $res;
	}

	public function expire($suffix = '') {
		return $this->redis()->setTimeout(
			static::mkKey($this->getName(), $suffix),
			$this->getTimeout()
		);
	}

	/**
	 * Remove key in hash
	 * @param string $hashKey
	 * @return boolean
	 */
	public function deleteHash($hashKey) {
		return $this->redis()->hDel($this->getName(), $hashKey);
	}

	/**
	 * Count keys in hash
	 * @return integer
	 */
	public function countHash() {
		return $this->redis()->hLen($this->getName());
	}
	
	/**
	 * Delete all by name $this->getName()
	 */
	public function deleteAll() {
		return $this->redis()->delete($this->getName());
	}
	
	/**
	 * Select string by key
	 * @return string value
	 */
	public function selectString($suffix = '') {
		$key = $suffix
			? static::mkKey($this->getName(), $suffix)
			: $this->getName();
		return $this->redis()->get($key);
	}

	const DELIMITER = ':';

	/**
	 * Make key according to args
	 */
	protected static function mkKey() {
		$args = func_get_args();
		return implode(static::DELIMITER, $args);
	}

	/**
	 * Rate increment
	 * @param $suffix
	 */
	public function rateInc($suffix = '') {
		if ($this->selectString($suffix) == null) {
			$this->incr($suffix);
			// set timeout only first time!
			$this->expire($suffix);
		} else {
			$this->incr($suffix);
		}
	}

	/**
	 * Update record
	 */
	public function updateString($value, $suffix = '') {
		return $this->insertString($value, $suffix);
	}

	/**
	 * Insert string
	 * @param string $value
	 * @param string $suffix
	 * @return boolean
	 */
	public function insertString($value, $suffix = '') {
		$key = $suffix
			? static::mkKey($this->getName(), $suffix)
			: $this->getName();
		return $this->redis()->setex($key, $this->getTimeout(), $value);
	}
	
	/**
	 * Increment record
	 * @param string $suffix
	 * @return boolean
	 */
	public function incr($suffix = '') {
		$key = $suffix
			? static::mkKey($this->getName(), $suffix)
			: $this->getName();
		$res = $this->redis()->incr($key);
		return $res;
	}
}

?>

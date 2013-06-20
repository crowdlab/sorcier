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
	 * Возвращает коннектор к Редису
	 */
	protected function redis() {
		return \Connector::getInstance()->getRedis();
	}

	/**
	 * Контекст
	 * @return string
	 */
	abstract public function getName();
	/**
	 * Время хранения переменной в секундах
	 * @return integer
	 */
	abstract public function getTimeout();

	/**
	 * Произвести выборку
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
	 * Обновление хеша
	 * @param array $fields массив ключ => значение или просто строка
	 * @return boolean true - обновление удалось, false - не получилося
	 */
	public function updateHash($fields) {
		return $this->insertHash($fields);
	}

	/**
	 * Добавление хеша
	 * @param array $fields массив ключ => значение
	 * @return boolean true - вставка удалась, false - не получилося
	 */
	public function insertHash($fields, $val = null) {
		if ($val != null && is_string($fields)) {
			// single pair case
			$fields = array($fields => $val);
		}
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
	 * Удаление ключа в хеше
	 * @param string $hashKey ключ в хеше
	 * @return boolean true - удаление удалось, false - не получилося
	 */
	public function deleteHash($hashKey) {
		return $this->redis()->hDel($this->getName(), $hashKey);
	}

	/**
	 * Подсчет ключей
	 * @return integer количество ключей в хеше
	 */
	public function countHash() {
		return $this->redis()->hLen($this->getName());
	}
	
	/**
	 * Удаление данных в контексте $this->getName()
	 */
	public function deleteAll() {
		return $this->redis()->delete($this->getName());
	}
	
	/**
	 * Обработка строки
	 */
	
	/**
	 * Произвести выборку
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
	 * Получить ключ по набору аргументов
	 */
	protected static function mkKey() {
		$args = func_get_args();
		return implode(static::DELIMITER, $args);
	}

	/**
	 * Увеличить счетчик с протуханием
	 * @param $suffix ключ суффикса
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
	 * Обновить
	 */
	public function updateString($value, $suffix = '') {
		return $this->insertString($value, $suffix);
	}

	/**
	 * Установка значения
	 * @param string $value - значение
	 * @param string $suffix суффикс ключа
	 * @return boolean true - вставка удалась, false - не получилося
	 */
	public function insertString($value, $suffix = '') {
		$key = $suffix ? static::mkKey($this->getName(), $suffix) : $this->getName();
		return $this->redis()->setex($key, $this->getTimeout(), $value);
	}
	
	/**
	 * Увеличение значения
	 * @param string $suffix суффикс ключа
	 * @return boolean true - вставка удалась, false - не получилося
	 */
	public function incr($suffix = '') {
		$key = $suffix ? static::mkKey($this->getName(), $suffix) : $this->getName();
		$res = $this->redis()->incr($key);
		return $res;
	}
}

?>

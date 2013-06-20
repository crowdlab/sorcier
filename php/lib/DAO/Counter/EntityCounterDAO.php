<?php
namespace DAO\Counter;
use DAO\Counter;
/**
 * Класс для счетчиков
 */
abstract class EntityCounterDAO extends \DAO\RedisDAO {
	/**
	 * Получить счетчик для объекта
	 */
	public function get($id) {
		return $this->selectString($id);
	}

	/**
	 * No timeout -- do not use timeout-enabled functions
	 */
	public function getTimeout() {
		return 0;
	}

	const AnonType    = 'anon';
	const AuthorType  = 'author';
	const VisitorType = 'visit';
	const TotalType   = 'total';

	public function getTotalKey($id) {
		return static::mkKey($this->getName(), $id, static::TotalType);
	}

	/**
	 * Получить ключ для заданного временнОго периода
	 * @param $period \Enum\StatType
	 */
	public function getPeriodKey($period, $id, $ktype, $ts = null) {
		switch ($period) {
			case \Enum\StatType::Daily:
				return $this->getDailyKey($id, $ktype, $ts);
			case \Enum\StatType::Weekly:
				return $this->getWeeklyKey($id, $ktype, $ts);
			case \Enum\StatType::Monthly:
				return $this->getMonthlyKey($id, $ktype, $ts);
		}
		return null;
	}

	public function getWeeklyKey($id, $ktype, $ts = null) {
		return static::mkKey($this->getName(), $id, $ktype, TimeKeys::getWeeklyKey($ts));
	}

	public function getMonthlyKey($id, $ktype, $ts = null) {
		return static::mkKey($this->getName(), $id, $ktype, TimeKeys::getMonthlyKey($ts));
	}

	public function getDailyKey($id, $ktype, $ts = null) {
		return static::mkKey($this->getName(), $id, $ktype, TimeKeys::getDailyKey($ts));
	}

	public function getValue($key) {
		return $this->redis()->get($key);
	}

	/**
	 * Увеличить значение
	 * @param $id       идентификатор сущности
	 * @param $owner_id идентификатор владельца
	 * @param $ktype    тип ключа (по умолчанию зависит от типа пользователя)
	 */
	public function incCounter($id, $owner_id = null, $ktype = null) {
		if ($ktype === null) {
			if (!\UserSingleton::canInstance())
				$ktype = static::AnonType;
			else if (\UserSingleton::getInstance()->getId() != $owner_id)
				$ktype = static::VisitorType;
			else
				$ktype = static::AuthorType;
		}

		$total_key   = $this->getTotalKey($id);
		$daily_key   = $this->getDailyKey($id, $ktype);
		$weekly_key  = $this->getWeeklyKey($id, $ktype);
		$monthly_key = $this->getMonthlyKey($id, $ktype);

		$keys = [
			$total_key   => -1,
			$daily_key   => TimeKeys::getDailyTtl(),
			$weekly_key  => TimeKeys::getWeeklyTtl(),
			$monthly_key => TimeKeys::getMonthlyTtl()
		];
		foreach($keys as $key => $timeout) {
			$this->redis()->incr($key);
			$this->redis()->setTimeout($key, $timeout);
		}
		// TODO (vissi): user buckets
	}
}
?>

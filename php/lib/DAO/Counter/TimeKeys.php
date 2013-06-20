<?php
namespace DAO\Counter;
use DAO\Counter;

/** 
 * ВременнЫе ключи (для redis)
 * Для подсчета значений за определенный период
 */
class TimeKeys {
	/**
	 * Префикс ключа для текущего дня
	 */
	public static function getDailyKey($ts = null) {
		if ($ts === null) $ts = time();
		return date('y_m_d', $ts);
	}

	public static function getWeeklyKey($ts = null) {
		if ($ts === null) $ts = time();
		return date('y_W', $ts);
	}

	public static function getMonthlyKey($ts = null) {
		if ($ts === null) $ts = time();
		return date('y_m', $ts);
	}

	public static function getDailyTtl() {
		return 2 * 86400;
	}

	public static function getWeeklyTtl() {
		return 2 * 7 * 86400;
	}

	public static function getMonthlyTtl() {
		return 2 * 31 * 86400;
	}
}
?>

<?php
namespace DAO;
use DAO;
/**
 * Вспомогательные методы для DAO
 */
trait Helpers {
	/**
	 * Дополнить массив записей доп данными
	 * TODO (vissi): медленно, лучше не использовать для большого количества записей
	 */
	public function enrichAll($r, $with = null) {
		foreach($r as $k => $v)
			$r[$k] = $this->enrich($v, $with);
		return $r;
	}

	/**
	 * Отфильтровать поля, оставив разрешенные
	 * static::$allowed - разрешенные
	 * @param $rqst запрос
	 * @param $special специальная функция-фильтр
	 */
	public static function checkAllowed($rqst, $special = null, $with = null) {
		$res = [];
		if (!is_array($rqst))
			return [];
		if ($special)
			$rqst = call_user_func($special, $rqst);
		foreach($rqst as $k => $v) {
			if (in_array($k, $with ? $with : static::$allowed, true))
				$res[$k] = $v;
		}
		return $res;
	}

}
?>

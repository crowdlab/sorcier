<?php
namespace DAO;
use DAO;
/**
 * Вспомогательные методы для DAO
 */
trait Helpers {
	/**
	 * Выделить id из коллекции
	 */
	public static function getIds($r) {
		$ids = [];
		foreach ($r as $v) {
			if (isset($v[static::IdKey]))
				$ids []= $v[static::IdKey];
		}
		return $ids;
	}

	/**
	 * Дополнить массив записей доп данными
	 * TODO (vissi): медленно, лучше не использовать для большого количества записей
	 */
	public function enrichAll($r, $with = null) {
		$cond = [static::IdKey => ['$in' => static::getIds($r)]];
		$op = $this->select_fn([static::IdKey], $cond);
		foreach($with as $k => $it) {
			$meth = "enrichAll_$it";
			if (method_exists($this, $meth)) {
				unset($with[$k]);
				$op = $this->$meth($op);
			}
		}
		$kv = [];
		$ret = $this->fetch_all($op, function($item) use (&$kv) {
			if (!isset($item[static::IdKey]))
				return $item;
			$id = $item[static::IdKey];
			unset($item[static::IdKey]);
			$kv[$id] = $item;
			return $item;
		});
		foreach($r as $k => &$v) {
			if (isset($r[static::IdKey]) && isset($kv[$r[static::IdKey]]))
				$v = array_merge($v, $kv[$r[static::IdKey]]);
			$r[$k] = $this->enrich($v, $with);
		}
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

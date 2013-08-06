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
	 * @param $r записи
	 * @param $with поля дополнения
	 */
	public function enrichAll($r, $with = null, $idkey = null) {
		if ($with == null)
			$with = [];
		$kv = [];
		if (method_exists($this, 'select_fn')) {
			$ids = static::getIds($r);
			$cond = [static::IdKey => ['$in' => $ids]];
			$is_operator = is_object($r) && $r instanceof MySQLOperator;
			if ($is_operator)
				$op = &$r;
			else
				$op = $this->select_fn([static::IdKey], $cond);
			$run_q = false;
			foreach($with as $k => $it) {
				$meth = "enrichAll_$it";
				if (method_exists($this, $meth)) {
					unset($with[$k]);
					$op = $this->$meth($op);
					$run_q = true;
				}
			}
			if (!$is_operator && $run_q) {
				$ret = $this->fetch_all($op, function($item) use (&$kv) {
					if (!isset($item[static::IdKey]))
						return $item;
					$id = (int) $item[static::IdKey];
					$kv[$id] = $item;
					return $item;
				});
			}
			if ($is_operator)
				return $r;
		}
		foreach($with as $k => $it) {
			$meth = "enrichColl_$it";
			if (method_exists($this, $meth)) {
				unset($with[$k]);
				$r = $this->$meth($r);
			}
		}
		foreach($r as &$v) {
			if (isset($v[static::IdKey]) && isset($kv[$v[static::IdKey]]))
				$v += $kv[$v[static::IdKey]];
			if (count($with))
				$v =$this->enrich($v, $with);
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

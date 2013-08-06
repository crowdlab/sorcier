<?php
require_once __DIR__ . '/../lib/autoload.php.inc';

/**
 * Сhecks the existence and returns HTTP request variable
 * fails on missing value or incorrect mongo id
 *
 * @param  string $name  variable name
 * @param  array  $rqst  Optional assoc array ($_REQUEST by default)
 * @return string
 */
function checkMongoId($name, &$rqst = null) {
	if (is_null($rqst)) $rqst = $_REQUEST;
	$var = isset($rqst[$name]) && \Common::is_valid_mongoId($rqst[$name])
		? $rqst[$name]
		: null;
	if ($var === null) Common::die500("missing $name");
	return $var;
}

/**
 * Сhecks the existence and returns HTTP request variable
 * fails on missing value
 *
 * @param  string $name  variable name
 * @param  array  $rqst  Optional assoc array ($_REQUEST by default)
 * @return string
 */
function checkVar($name, &$rqst = null) {
	if (is_null($rqst)) $rqst = $_REQUEST;
	$var = isset($rqst[$name]) ? $rqst[$name] : null;
	if ($var === null) Common::die500("missing $name");
	return $var;
}

/**
 *  Проверяет наличие целого числа, переданного пользователем
 *
 * @param  string $name  variable name
 * @param  array  $rqst  Optional assoc array ($_REQUEST by default)
 * @return
 */
function checkInt($name, &$rqst = null) {
	if (is_null($rqst)) $rqst = $_REQUEST;
	$var = isset($rqst[$name]) && !is_array($rqst[$name])
		? preg_match("|^[\d]*$|", $rqst[$name])
			? $rqst[$name]
			: Common::die500("bad number format for $name")
		: Common::die500("empty  or incorrect $name");
	return (int) $var;
}

/**
 * Получить параметр запроса (int) со значением по умолчанию
 * @param $var имя
 * @param $default значение по умолчанию
 */
function getOptionalInt($name, $default = null, &$rqst = null) {
	if (is_null($rqst)) $rqst = $_REQUEST;
	$var = isset($rqst[$name])  && !is_array($rqst[$name])
			&& preg_match("|^[\d]*$|", $rqst[$name])
		? (int) $rqst[$name]
		: $default;
	return $var;
}

/**
 * Получить сообщение для возврата ошибки (с переводом)
 */
function makeError($message) {
	return ['error' => $message];
}

/**
 * Получить параметр запроса со значением по умолчанию
 * @param $var имя
 * @param $default значение по умолчанию
 */
function getOptional($name, $default = null, &$rqst = null) {
	if (is_null($rqst)) $rqst = $_REQUEST;
	$var = isset($rqst[$name])
		? $rqst[$name]
		: $default;
	return $var;
}

/**
 * Append key-value pairs from $array2 missing (by key) in $array1
 *
 * like array_merge, but without replacing values
 */
function array_append($array1, $array2) {
	$diff = array_diff_key($array2, $array1);
	return $array1 + $diff;
}

/**
 * Разница по значениям
 */
function array_diff_values($new, $old) {
	$diff = [];
	foreach($new as $k => $v) {
		if (!isset($old[$k]) || $v != $old[$k])
			$diff[$k] = $v;
	}
	return $diff;
}

/**
 * Build ctpp template
 */
function makeCtpp($path) {
	global $T;
	if ($T === null) {
		$T = new ctpp();
		if (!function_exists('_')) {
			function _($s) {
				return $s;
			}
		}
	}
	return $T->load_bytecode(dirname(__FILE__) . $path);
}

/**
 * Is array associative (non-numeric or non-consecutive keys)
 */
function is_assoc($arr) {
	if (!is_array($arr)) return false;
	return array_keys($arr) !== range(0, count($arr) - 1);
}

/**
 * Wrapper for curl_setopt
 */
function curl_setopt_many($ch, $options) {
	foreach($options as $k => $v)
		curl_setopt($ch, $k, $v);
}

if (!defined('MINI_MEMOIZE'))
	define('MINI_MEMOIZE','[mini|memoize|element]');

function is_mini_memoized($args) {
	return $args[count($args)-1]==MINI_MEMOIZE;
}

/**
 * Memoization helper
 */
function get_mini_memoized($funcname,$args) {
	static $cachedvals = array();
	$key = md5($funcname.'-'.json_encode($args));
	if (isset($cachedvals[$key])) return $cachedvals[$key];
	$args[] = MINI_MEMOIZE;
	return $cachedvals[$key] = call_user_func_array($funcname,$args);
}

function tonicResponse($code, $value = null) {
	$v = $value === null ? $value : json_encode($value);
	return new Tonic\Response($code, $v);
}
?>

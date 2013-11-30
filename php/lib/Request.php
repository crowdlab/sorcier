<?php

final class Request {
	/**
	 * Сhecks the existence and returns HTTP request variable
	 * fails on missing value or incorrect mongo id
	 *
	 * @param  string $name  variable name
	 * @param  array  $rqst  Optional assoc array ($_REQUEST by default)
	 * @return string
	 */
	public static function checkMongoId($name, &$rqst = null) {
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
	public static function checkVar($name, &$rqst = null, $die = true) {
		if (is_null($rqst)) $rqst = $_REQUEST;
		$var = isset($rqst[$name]) ? $rqst[$name] : null;
		if ($var === null) {
			if ($die)
				Common::die500("missing $name");
			else
				return null;
		}
		return $var;
	}

	/**
	 * Check if request item is integer
	 *
	 * @param  string $name  variable name
	 * @param  array  $rqst  Optional assoc array ($_REQUEST by default)
	 * @return value or null
	 */
	public static function checkInt($name, &$rqst = null, $die = true) {
		if (is_null($rqst)) $rqst = $_REQUEST;
		$var = isset($rqst[$name]) && !is_array($rqst[$name])
			? preg_match("|^[\d]*$|", $rqst[$name])
				? $rqst[$name]
				: ($die ? Common::die500("bad number format for $name") : null)
			: ($die ? Common::die500("empty  or incorrect $name") : null);
		return (int) $var;
	}

	/**
	 * Check if request item is integer or array
	 */
	public static function checkIntOrArray($name, &$rqst = null, $die = true) {
		if (is_null($rqst)) $rqst = $_REQUEST;
		$var = isset($rqst[$name]) ? $rqst[$name] : null;
		if ($var === null) {
			if ($die)
				Common::die500("missing $name");
			else
				return null;
		}
		if (is_array($var))
			return Common::intArray($var);
		return checkInt($name, $rqst);
	}

	/**
	 * Get integer request item w/ default value
	 * @param $name name
	 * @param $default
	 */
	public static function getOptionalInt($name, $default = null, &$rqst = null) {
		if (is_null($rqst)) $rqst = $_REQUEST;
		$var = isset($rqst[$name])  && !is_array($rqst[$name])
				&& preg_match("|^[\d]*$|", $rqst[$name])
			? (int) $rqst[$name]
			: $default;
		return $var;
	}

	/**
	 * get error message as array
	 */
	function makeError($message) {
		return ['error' => $message];
	}

	/**
	 * Get optional value
	 * @param $name name
	 * @param $default
	 */
	public static function getOptional($name, $default = null, &$rqst = null) {
		if (is_null($rqst)) $rqst = $_REQUEST;
		$var = isset($rqst[$name])
			? $rqst[$name]
			: $default;
		return $var;
	}
}
?>

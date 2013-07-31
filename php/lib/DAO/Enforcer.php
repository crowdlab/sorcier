<?php
namespace DAO;
use DAO;
/**
 * Type schema enforcer
 *
 * PHP is a dynamically typed language, so we need to enforce types to get
 * better output and correct queries for type-safe databases (for example, mongo).
 */
trait Enforcer {
	/**
	 * Enforce type schema of a data row
	 *
	 * For example, if some field is json and you want a php structure from it,
	 * and another field is an integer number, and others are strings,
	 * supply schema like this:
	 * `['json_field' => 'json', 'int_field' => 'int']`
	 * corresponding fields would be casted to requested types.
	 *
	 * Supported types: json, int, bool, intnull, richtext, rm
	 *   rm is pseudo-type for field removal
	 *
	 * @param $schema schema
	 * @param $row    value
	 * @param $optional optional fields (not returned if null)
	 */
	public static function enforce($schema, $row, $optional = []) {
		if (empty($schema) || empty($row)) return $row;
		$unsetf = [];
		foreach ($row as $k => $v) {
			if (in_array($k, $optional, true)
					&& (is_null($v) || is_array($v) && !count($v)))
				$unsetf[] = $k;
			if (!isset($schema[$k])) continue;
			switch($schema[$k]) {
				case 'rm':
					$unsetf []= $k;
					continue;
					break;
				case 'ascii':
					if (!is_null($v) && !is_array($v)) {
						if (!preg_match('/^[a-z][a-z0-9\-]*$/', $v)) {
							$v = str_replace(\Common::$toreplace, \Common::$replacement, $v);
							$v = strtolower($v);
							$v = preg_replace('/[^a-z0-9\-]/', "", $v);
							if (!preg_match('/^[a-z]/', $v)) $v = "o$v";
						}
					} else
						$v = '';
					break;
				case 'json':
					$v = json_decode($v);
					break;
				case 'bool':
					$v = $v && $v !== 'false' ? 1 : 0;
					break;
				case 'datenull':
					if ($v === null || $v == 'null')
						$v = null;
					break;
				case 'int':
					$v = intval($v);
					break;
				case 'array':
					if (!$v) $v = [];
					break;
				case 'intnull':
					if ($v !== null)
						$v = intval($v);
					break;
				case 'richtext':
					$v = \Common::prepare_message($v);
					break;
				default:
					// unknown type
					break;
			}
			$row[$k] = $v;
		}
		foreach ($unsetf as $v)
			unset($row[$v]);
		return $row;
	}

}
?>

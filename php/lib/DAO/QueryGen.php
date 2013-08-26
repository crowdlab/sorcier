<?php
namespace DAO;
use DAO;
/**
 * SQL query generator, used in conjuction with MySQLDAO
 */
class QueryGen {
	/**
	 * Escape string
	 */
	public static function escape($v) {
		if (is_numeric($v)) return $v;
		if (is_array($v) || is_object($v) || is_resource($v))
			throw new \DomainException("expected string or number value");
		$_DB = \Connector::getInstance()->getMySQL();
		return isset($_DB)
			? \mysqli_real_escape_string($_DB, $v)
			: (function_exists('mysql_real_escape_string')
				? \mysql_real_escape_string($v)
				: addslashes($v));
	}

	/**
	 * Make a set of key-value pairs for SQL SET clause
	 */
	public static function make_set_kv($set) {
		$set_kv = [];
		if (!is_array($set)) return [];
		foreach ($set as $k => $v) {
			$k = self::make_key($k);
			if ($v === null)
				$set_kv [] = "$k=NULL";
			else if (is_numeric($v) || is_object($v))
				$set_kv [] = "$k=$v";
			else
				$set_kv [] = "$k='" . self::escape($v) . "'";
		}
		return $set_kv;
	}

	/**
	 * Quote field name
	 */
	protected static function make_key($v) {
		return (strpos($v, '.') !== false || strlen($v) > 0 && $v[0] == '`'
				|| is_numeric($v)) // select 1
			? $v
			: "`$v`";
	}

	/**
	 * Generate list of fields for SQL INSERT/SELECT clause
	 */
	public static function make_fields($set) {
		if (is_array($set) && count($set) == 0) return '1';
		if (!is_array($set)) $set = [$set];
		$set_kv = [];
		foreach ($set as $k => $v) {
			$set_kv []= is_object($v) ? (string) $v : self::make_key($v);
		}
		return implode(',', $set_kv);
	}

	/**
	 * Generate list of values for SQL VALUES() clause
	 */
	public static function make_insert($set) {
		if (!is_array($set)) return '()'; // error
		$set_kv = array();
		foreach ($set as $k => $v) {
			if (is_array($v)) {
				$set_kv []= self::make_insert($v);
			}
			else if ($v === null)
				$set_kv [] = "NULL";
			else if (is_int($v))
				$set_kv [] = $v;
			else if (is_object($v))
				$set_kv [] = (string) $v;
			else
				$set_kv [] = "'" . self::escape($v) . "'";
		}
		$r = implode(',', $set_kv);
		return "($r)";
	}

	/**
	 * Generate COUNT($param) field list
	 */
	public static function gen_count($param) {
		if (is_string($param))
			$param = self::make_key($param);
		return [new Sql\Expr("COUNT($param)", 'count')];
	}

	/**
	 * Prepare key (for example, key make be a function call, like `UNIX_TIMESTAMP(`date`)`)
	 */
	public static function prepare_key($kk) {
		if (is_object($kk))
			return (string) $kk;
		else if (!\Common::startsWith($kk, Sql\Expr::KEY))
			return self::make_key($kk);
		else return substr($kk, strlen(Sql\Expr::KEY));
	}

	/**
	 * supported operators
	 * @readonly
	 */
	public static $operators = ['or', '$or', '$in', '<', '>', '<=', '>=', '!=',
		'<>', '$nin', '$notin', 1, '1', '$lt', '$gt', '$gte', '$lte', '$ne'];

	protected static $binary_ops = ['>', '<', '>=', '<=', 'like', '$like', '$gt',
		'$lt', '$gte', '$lte', '$ne'];

	/**
	 * Generate condition
	 *
	 * Comparison operator defaults to '='. E.g. 'a' => 'b' means that field 'a'
	 * is expected to be equal to string 'b'. Connector between comparisons
	 * defaults to 'AND'. If you want to make a complex condition, use this form:
	 * `['OR' => ['a' => 'b', 'c' => 'd']]`, for other operators:
	 * `['>' => ['a' => 5]]`.
	 *
	 * @param $condition   condition
	 * @param $and join    params with AND operator (true, default), or OR (false)
	 * @param $current_key temporary param for conditions (>, <, etc)
	 * @param $noescape    do not escape values (for simpler join condition generation)
	 */
	public static function make_cond($condition, $and = true, $current_key = '',
			$noescape = false) {
		// false condition
		if ($condition == 0 && !is_array($condition))
			return is_string($condition) ? $condition : '0';
		// array of strings corresponding to requests
		$cond_kv = [];
		foreach ($condition as $k => $v) {
			$kop = strtolower($k);
			if ($kop === 'or' || $kop === '$or')
				$cond_kv []= static::make_cond($v, false, '', $noescape);
			else if ($kop === 'not' || $kop === '$not')
				$cond_kv []= '(NOT '.static::make_cond($v, false, '', $noescape).')';
			else if (!is_numeric($k) && in_array($kop, static::$binary_ops, true)) {
				$cond_kv []= static::cond_binop($k, $v, $current_key);
			} else if ($k === '!=' || $k === '<>') {
				$cond_kv []= static::cond_eq($k, $v, $noescape);
			} else if (in_array($kop, ['$in', '$nin', '$notin'], true)) {
				$cond_kv []= static::cond_in($k, $v, $current_key);
			} else if (is_array($v)) {
				$k = self::prepare_key($k);
				$cond_kv []= self::make_cond($v, true, $k, $noescape);
			} else {
				$k = self::prepare_key($k);
				$cond_kv []= $v !== null
					? ($noescape || is_int($v) || is_object($v)
						? "$k=$v"
						: "$k='".self::escape($v)."'")
					: "$k IS NULL";
			}
		}
		$impl = implode($and ? ' AND ' : ' OR ', $cond_kv);
		$ret = count($condition) > 0
			? (count($cond_kv > 1) ? "($impl)" : $impl)
			: '1';
		return $ret;
	}

	/**
	 * Process in/not in operator in condition
	 */
	protected static function cond_in($k, $v, $current_key = '') {
		$not = in_array(strtolower($k), ['$nin', '$notin'], true) ? 'NOT ' : '';
		if (!is_array($v)) $v = [$v];
		// '$in' => ['column' => ['values']] case
		if (!isset($v[0])) {
			list($kk, $vv) = each($v);
		} else {
			$kk = $current_key;
			$vv = $v;
		}
		$kk = self::prepare_key($kk);
		if (is_array($vv)) {
			$vv = array_map(function($t) {
				return is_int($t) ? $t : "'".self::escape($t)."'";
			}, $vv);
			$vv = implode(',', $vv);
		}
		return (strlen($vv) > 0)
			? "$kk {$not}IN ($vv)"
			: 'false';
	}

	protected static function cond_eq($k, $v, $noescape = false) {
		list($kk, $vv) = each($v);
		$kk = self::prepare_key($kk);
		if (!is_object($vv)) {
			return $vv !== null
				? ($noescape && !is_int($vv) && !is_object($vv)
					? "$kk<>$vv"
					: "$kk<>'".self::escape($vv)."'")
				: "$kk IS NOT NULL";
		} else
			return "$kk<>$vv";
	}

	/**
	 * process binary op in condition
	 */
	protected static function cond_binop($k, $v, $current_key = '', $noescape = false) {
		$map = [
			'$like' => ' LIKE ',
			'$gte'  => '>=',
			'$lte'  => '<=',
			'$gt'   => '>',
			'$lt'   => '<',
			'$ne'   => '<>',
		];
		$k = strtolower($k);
		if (isset($map[$k])) $k = $map[$k];
		if (!is_array($v) && $current_key) {
			// condition normal order ['a' => ['>' => 5]]
			if (!$noescape && !is_int($v) && !is_object($v)) {
				$v = self::escape($v);
				$v= "'$v'";
			}
			return "($current_key$k$v)";
		} else {
			// condition reverse order ['>' => ['a' => 5]]
			list($kk, $vv) = each($v);
			$kk = self::prepare_key($kk);
			if (!$noescape && !is_int($vv) && !is_object($vv)) {
				$vv = self::escape($vv);
				$vv= "'$vv'";
			}
			return "($kk$k$vv)";
		}
	}
}
?>

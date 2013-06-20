<?php
namespace DAO;
use DAO;
/**
 * MySQL DAO: if you want to interact with MySQL, please extend this
 */
abstract class MySQLDAO implements IDAO {
	use \Singleton;
	use \DAO\Enforcer;
	use \DAO\Helpers;

	abstract public function getName();

	/**
	 * Функции для проверки полей, приходящих на изменение
	 */
	protected static $checkers = [];
	/**
	 * Transaction started
	 */
	protected $started = false;

	/**
	 * Проверить, все ли обязательные поля на месте
	 */
	public static function checkRequired($rqst, &$fields = null, $for = null) {
		if (!is_array($rqst)) return false;
		$keys = array_keys($rqst);
		if ($for == null) $for = static::$required;
		foreach($for as $v) {
			if (!in_array($v, $keys, true)
					|| empty($rqst[$v]) && $rqst[$v] !== false) {
				if ($fields !== null)
					$fields []= $v;
				return false;
			}
		}
		return true;
	}

	/**
	 * Начать транзакцию
	 */
	public function start() {
		if ($this->started) return;
		$this->perform_query('SET AUTOCOMMIT=0');
		$this->perform_query('START TRANSACTION');
		$this->started = true;
	}

	/**
	 * Откатить транзакцию
	 */
	public function rollback() {
		$this->perform_query('ROLLBACK');
		$this->started = false;
	}

	/**
	 * Завершить транзакцию успешно (записать)
	 */
	public function commit() {
		if (!$this->started) return;
		$this->perform_query('COMMIT');
		$this->started = false;
	}

	/**
	 * Есть ли в схеме такая таблица
	 */
	public function hasTable($table = null) {
		if ($table == null) $table = $this->getName();
		$q = "SHOW TABLES LIKE '$table'";
		$r = $this->perform_query($q);
		return ($this->num_rows($r) == 1);
	}

	/**
	 * Выполнить запрос
	 */
	public static function perform_query($q) {
		\logger\Log::instance()->logDebug("SQL: $q");
		$_DB = \Connector::getInstance()->getMySQL();
		global $config;
		if (!$_DB) {
			if ($config['daemon']) return false;
			\Common::die500('database error');
		}
		if (isset($config['daemon']) && ($config['daemon'])) {
			if (!mysqli_ping($_DB)) {
				$_DB = \Connector::getInstance()->getMySQL(true);
			}
		}
		$r = mysqli_query($_DB, $q);
		if ($r)
			return $r;
		$err = mysqli_error($_DB);
		\logger\Log::instance()->logError("perform_query error",
			['error' => $err, 'query' => $q]);
		if ($config['daemon']) return false;
		if (isset($config['debug']) && $config['debug'])
			\Common::dieError(['error' => $err, 'query' => $q]);
		\Common::die500('database error');
	}

	/**
	 * Получить идентификатор последней добавленной записи
	 */
	public function insert_id() {
		$_DB = \Connector::getInstance()->getMySQL();
		return (int)mysqli_insert_id($_DB);
	}

	/**
	 * Выборка
	 *
	 * @param array $fields     поля
	 * @param array $condition  условие (поддерживаются операторы,
	 * подробнее см. `QueryGen::make_cond()` )
	 * @param array|integer $limit  Optional сколько пропустить (по умолчанию 0)
	 * @param string $join      дополнительная часть запроса (JOIN)
	 */
	public function select($fields, $condition = [], $limit = null,
			$orderby = null, $join = '') {
		$table_name = $this->getName();
		$fields = $this->prefixWithName($fields);
		$q = new DAO\Sql\Select($table_name, $fields, $this->prefixCond($condition),
			$limit, $orderby, $join);
		return $this->perform_query((string)$q);
	}

	/**
	 * Вернуть точное количество элементов по условию
	 *
	 * Может быть медленно, в зависимости от условия
	 */
	public function count($field, $condition) {
		$field = $field == 1 ? $field : "`$field`";
		$table_name = $this->getName();
		$cond = QueryGen::make_cond($condition);
		$q = "SELECT count($field) as `count`
		      FROM   $table_name
		      WHERE  $cond";
		return $this->fetch_assoc(
			$this->perform_query($q),
			null,
			function($row){return (int)$row['count'];}
		);
	}

	/**
	 * Обновление записи
	 *
	 * @param array $set        список обновляемых полей таблицы
	 * и новые значения в виде 'имя поля'='значение'
	 * @param array $condition  условие (поддерживаются операторы,
	 * подробнее см. `QueryGen::make_cond()` )
	 */
	public function update($set, $condition, $suffix = '') {
		$cond = QueryGen::make_cond($condition);
		if (isset(static::$schema))
			$set = static::enforce(static::$schema, $set);
		$set_kv = QueryGen::make_set_kv($set);
		$sset = implode(',', $set_kv);
		$table_name = $this->getName();
		$q = "UPDATE $table_name
		      SET    $sset
		      WHERE  $cond
			  $suffix;";
		return $this->perform_query($q);
	}

	/**
	 * Сгенерировать блок полей для join
	 * @param $fields поля
	 * @param $table  алиас связанной таблицы
	 * @param $prefix префикс полей
	 */
	protected static function joinFields($fields, $table, $prefix) {
		$ret = [];
		foreach($fields as $v)
			$ret []= \DAO\Sql\Alias::imbue("$table.$v", "$prefix$v");
		return $ret;
	}

	/**
	 * Обновление записи
	 *
	 * @param array $set kv
	 */
	public function update_fn($set) {
		return \DAO\FnMySQL::update($set)->from($this);
	}

	/**
	 * Functional style select
	 * @param $fields поля
	 * @param $cond условие (опционально)
	 */
	public function select_fn($fields, $cond = null) {
		$fields = $this->prefixWithName($fields);
		if ($cond != null) $cond = $this->prefixCond($cond);
		return \DAO\FnMySQL::select($fields, $cond)->from($this);
	}

	/**
	 * Выполнить вставку массива данных
	 * @param $kv массив
	 * @param bool $ignore insert ignore
	 * @param string $suffix дополнение к запросу (например, 'ON DUPLICATE KEY UPDATE ...')
	 */
	public function push($kv, $ignore = false, $suffix = '') {
		if (isset(static::$schema))
			$kv = static::enforce(static::$schema, $kv);
		return $this->insert(array_keys($kv), array_values($kv), $ignore, $suffix);
	}

	/**
	 * Вставка записей в базу
	 * @param array $fields поля
	 * @param array $what значения
	 * @param bool $ignore insert ignore
	 * @param string $suffix дополнение к запросу (например, 'ON DUPLICATE KEY UPDATE ...')
	 */
	public function insert($fields, $what = null, $ignore = false, $suffix = '') {
		// single kv array
		if ($what == null) {
			$_fields = $fields;
			$fields = array_keys($_fields);
			$what = array_values($_fields);
		}
		// multiple values to insert
		if (isset($what[0]) && is_array($what[0])) {
			$ins = array();
			foreach ($what as $v)
				$ins[] = QueryGen::make_insert($v);
			$ins = implode(',', $ins);
		} else {
			$ins = QueryGen::make_insert($what);
		}
		$fields_s = '(' . QueryGen::make_fields($fields) . ')';
		$table_name = $this->getName();
		$ign = $ignore ? 'IGNORE' : '';
		$q = "INSERT $ign
		      INTO $table_name $fields_s
		      VALUES $ins
		      $suffix";
		return $this->perform_query($q);
	}

	/**
	 * Удаление записей
	 *
	 * @param array $condition  условие (поддерживаются операторы,
	 * подробнее см. `QueryGen::make_cond()` )
	 * @deprecated если нужно удалить, стоит использовать deleteItem
	 */
	public function delete($condition) {
		$cond = QueryGen::make_cond($condition);
		$table_name = $this->getName();
		$q = "DELETE
		      FROM $table_name
		      WHERE $cond";
		return $this->perform_query($q);
	}

	/**
	 * Пометить записи, сответствующие условию удаленными
	 * @param $condition условие
	 * @param $field поле-флаг (по умолчанию deleted)
	 */
	public function deleteItem($condition, $field = 'deleted', $dval = 1) {
		return $this->update([$field => $dval], $condition);
	}

	/**
	 * clean up result array
	 * @param $item item
	 * @param $sup  supplementary
	 */
	public static function cleanup($item, $sup = null) {
		if (!$item) return $item;
		if (!isset(static::$supplementary) && $sup == null) return $item;
		if (!$sup) $sup = static::$supplementary;
		foreach($item as $k => $v) {
			if (in_array($k, $sup, true) && $v === null)
				unset($item[$k]);
		}
		return $item;
	}

	/**
	 * Добавить префиксы к ключам условия
	 */
	public function prefixCond($cond) {
		if (!is_array($cond)) return $cond;
		$_cond = $cond;
		$cond = [];
		foreach($_cond as $k => $v) {
			if (strpos($k, '.') === false && strpos($k, '__KEY__') === false &&
					!in_array(strtolower($k), \DAO\QueryGen::$operators, true))
				$k = $this->getName().".$k";
			$cond[$k] = $v;
		}
		return $cond;
	}

	/**
	 * Добавить префикс с названием таблицы к массиву полей
	 */
	public function prefixWithName($array) {
		$name = $this->getName();
		return array_map(function($k) use($name) {
			if (strpos($k, '(') === false && strpos($k, '.') === false
					&& strpos($k, '__KEY__') === false &&
					!in_array(strtolower($k), \DAO\QueryGen::$operators, true))
				return "$name.$k";
			return $k;
		}, $array);
	}

	public function reindexAll($ids) {
		$count = 0;
		foreach($ids as $id) {
			try {
				$this->reindex($id, true);
			}
			catch (Elastica\Exception\AbstractException $e) {
				echo "error indexing user $id\n";
				echo $e->getMessage();
			}
			++$count;
		}
		return $count;
	}

	/**
	 * Оставить только разрешенные поля, применить обработчик, если требуется
	 * @param $request  запрос на модификацию
	 * @param $allowed  разрешенные поля
	 * @param $callback обработчик
	 */
	protected static function getFields($request, $allowed = null, $callback = null) {
		$fields = [];
		if (empty($allowed))
			$allowed = (isset(static::$allowed)) ? static::$allowed : [];
		foreach ($request as $k => $v) {
			if (!in_array($k, $allowed, true)) continue;
			if (isset(static::$checkers[$k]) &&
					!call_user_func(static::$checkers[$k], $v))
				continue;
			if ($callback && is_callable($callback))
				$v = $callback([$k => $v]);

			if (isset(static::$allowedSchema)
				&& isset(static::$allowedSchema[$k])
				&& static::$allowedSchema[$k] == 'int'
			)
				$v = (int)$v;

			$fields[$k] = $v;
		}
		return $fields;
	}

	/**
	 * Модификация простой сущности в MySQL
	 *
	 * @param array     $request  запрос (новые значения)
	 * @param integer   $id       id сущности
	 * @param array     $params:
	 *    allowed  Optional поля (по умолчанию берутся из соответствующего DAO)
	 *    callback вызов для пред-обработки пары ключ-значение
	 *    retval   вернуть полученные значения
	 *    id_key   ключ идентификатора в базе (по умолчанию id)
	 *    schema   схема для возврата
	 *    throw    кидать ли ошибку если нет изменений
	 *    special  проверка особых условий (права доступа и т.п.), возвращает true если ок
	 *    diff     requires retval, вернуть разницу
	 * @return [ 'message' => ok, 'rows' => 1, 'fields' => [...] ]
	 */
	public function modItem($request, $id, $params = []) {
		$params_default = [
			'allowed'  => null,
			'callback' => null,
			'diff'     => false,
			'id_key'   => 'id',
			'retval'   => true,
			'schema'   => [],
			'special'  => null,
			'throw'    => true,
		];
		$params = array_append($params, $params_default);
		foreach ($params as $k => $v)
			$$k = $v;
		// transposition guard
		if (!is_array($request)) list($request, $id) = array($id, $request);
		// filter fields
		if (!is_array($request)) return null; // error
		$fields = static::getFields($request, $allowed, $callback);
		if ($special && !$special($fields))
			return ['error' => \Common::InternalError, 'code' => 403];
		$new_extra = static::filterExtra($request);
		if ($throw && !count($fields) && !count($new_extra))
			\Common::die500('no fields to set', $request);
		$cond = is_array($id) ? $id : [$id_key => $id];
		$ar = 0;
		if ($diff) {
			$r = FnMySQL::select(array_keys($fields))
				->from($this->getName())->where($cond);
			$prev = self::enforce($schema, self::getFirst($r->x()));
		}
		if (count($fields) > 0) {
			$this->update($fields, $cond);
			$ar = $this->affected_rows();
		}

		$extra = [];
		foreach($new_extra as $k => $v) {
			$r = $this->setExtra($k, $v, $id, true);
			if ($r) $extra[$k] = $r;
		}

		$res = ['message' => 'ok', 'rows' => $ar];
		if (count($extra) > 0)
			$res['extra'] = $extra;
		if ($retval) {
			// return selected values
			if (count($schema) == 0 && isset(static::$schema))
				$schema = static::$schema;
			$r = FnMySQL::select(array_keys($fields))
				->from($this->getName())->where($cond);
			$item = self::enforce($schema, self::getFirst($r->x()));
			$res['fields'] = $item;
			$res['extra']  = $extra;
			if ($diff)
				$res['diff'] = array_diff_values($item, $prev);
		}
		if (isset(static::$translationFields))
			$this->clearTranslations($id);
		return $res;
	}

	/**
	 * Выделить доп поля (данные, сохраняемые через другие DAO)
	 */
	public static function filterExtra($fields) {
		$ret = [];
		if (!isset(static::$extraFields)) return $ret;
		foreach($fields as $k => $v) {
			if (in_array($k, static::$extraFields, true))
				$ret[$k] = $v;
		}
		return $ret;
	}

	/**
	 * Вернуть первый элемент массива (первый в итерации)
	 */
	protected static function getFirst($x, $transform = null) {
		foreach ($x as $v)
			return $transform ? $transform($v) : $v;
	}

	/**
	 * The function returns the count of affected rows.
	 */
	public function affected_rows() {
		$_DB = \Connector::getInstance()->getMySQL();
		return mysqli_affected_rows($_DB);
	}

	/**
	 * Существует проект/задача
	 */
	public function exists($cond, $idkey = 'id') {
		if (!is_array($cond)) $cond = [$idkey => $cond];
		$r = $this->select([1], $cond);
		return $r && $this->num_rows($r) > 0;
	}

	/**
	 * The function returns the count of selected rows.
	 */
	public function num_rows($r) {
		if (!$r) return 0;
		return $r instanceof \mysqli_result
			? mysqli_num_rows($r)
			: $r->num_rows(); // FnMySQL cross support
	}

	/**
	 * The function returns a result row as an associative array.
	 * @param $r call result
	 * @param $schema transformation schema
	 * @param $func transform function
	 */
	public function fetch_assoc($r, $schema = null, $func = null) {
		$row = $r instanceof \mysqli_result
			? mysqli_fetch_assoc($r)
			: $r->fetch_assoc();
		if (is_callable($schema))
			$func = $schema;
		if ($schema === null && isset(static::$schema))
			$schema = static::$schema;
		if (is_array($schema) && is_array($row))
			$row = self::enforce($schema, $row);
		return $func && $row
			? is_string($func) ? $func($row) : call_user_func($func, $row)
			: $row;
	}

	/**
	 * Выбрать все записи из набора
	 * @param $r результат
	 * @param $schema схема
	 * @param $func преобразование
	 */
	public function fetch_all($r, $schema = null, $func = null, $limit = null) {
		$result = [];
		if ($r === false) return $result;
		if ($r instanceof MySQLOperator) $r = $r->x();
		$has_limit = $limit != null;
		if (is_callable($schema))
			$func = $schema;
		if ($schema === null && isset(static::$schema))
			$schema = static::$schema;
		while ($row = ($r instanceof \mysqli_result
				? mysqli_fetch_assoc($r)
				: $r->fetch_assoc())) {
			if (is_array($schema) && is_array($row))
				$row = self::enforce($schema, $row);
			if ($func) {
				$row = is_string($func)
					? $func($row)
					: call_user_func($func, $row);
			}
			$result []= $row;
			if ($has_limit) {
				if (!($limit--)) break;
			}
		}
		return $result;
	}

	/**
	 * Выбрать с числовыми индексами
	 * @param $r результат
	 */
	public function fetch_array($r) {
		return mysqli_fetch_array($r, MYSQL_NUM);
	}
}
?>

<?php

namespace DAO;

use DAO;
use DAO\QueryClass as QC;

/**
 * Functional MySQL DAO
 * if you want to interact with MySQL functional way, please use this.
 */
class FnMySQL
{
    use \Singleton;

    /**
     * Начать транзакцию.
     */
    public static function start()
    {
        static::perform_query('SET AUTOCOMMIT=0');
        static::perform_query('START TRANSACTION');
    }

    /**
     * Имя коллекции, к которой по умолчанию применяется.
     */
    protected static $defaultFrom = '';

    /**
     * Задать оператор, откуда выбирать по умолчанию.
     */
    protected static function setFrom($op)
    {
        if (!empty(static::$defaultFrom)) {
            $op->from(static::$defaultFrom);
        }

        return $op;
    }

    /**
     * Завершить транзакцию.
     */
    public static function commit()
    {
        static::perform_query('COMMIT');
    }

    /**
     * Выполнить запрос
     */
    protected static function perform_query($q)
    {
        \logger\Log::instance()->logDebug("SQL: $q");
        $_DB = \Connector::getInstance()->getMySQL();
        $r = mysqli_query($_DB, $q);
        if ($r) {
            return $r;
        }
        $err = mysqli_error($_DB);
        \logger\Log::instance()->logError('FnMySQL perform_query error',
            ['error' => $err, 'query' => $q]);
        \Common::die500('database error');
    }

    /**
     * any instance.
     *
     * @param $class класс
     */
    public static function inst($class)
    {
        $r = new DAO\MySQLOperator($class);

        return self::setFrom($r);
    }

    /**
     * select.
     *
     * @param $fields поля
     * @param $condition условие
     */
    public static function select($fields = [], $condition = [])
    {
        $r = new DAO\MySQLOperator(QC::select, $fields, $condition);

        return self::setFrom($r);
    }

    /**
     * update.
     *
     * @param $set что
     * @param $cond условие
     */
    public static function update($set = [], $cond = [])
    {
        $r = new DAO\MySQLOperator(QC::update, $set, $cond);

        return self::setFrom($r);
    }

    /**
     * вставка.
     *
     * @param $value кортеж (массив ключ-значение)
     */
    public static function push($value = null, $ignore = false, $suffix = '')
    {
        return static::insert($value, $ignore, $suffix);
    }

    /**
     * вставка.
     *
     * @param $value кортеж (массив ключ-значение)
     */
    public static function insert($value = null, $ignore = false, $suffix = '')
    {
        $r = new DAO\MySQLOperator(QC::insert, $value, $ignore, $suffix);

        return self::setFrom($r);
    }

    /**
     * Удаление.
     *
     * @param $cond условие
     */
    public static function delete($cond = [])
    {
        $r = new DAO\MySQLOperator(QC::delete, $cond);

        return self::setFrom($r);
    }

    /**
     * Подсчет
     *
     * @param $field поле
     * @param $condition условие
     */
    public static function count($field, $condition = [])
    {
        $fields = [DAO\Sql\Expr("COUNT($field)")];
        $r = new DAO\MySQLOperator(QC::select, $fields, $condition);

        return self::setFrom($r);
    }
}

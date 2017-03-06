<?php

namespace DAO;

/**
 * Pageable collections operations.
 */
trait Pageable
{
    /**
     * Get pageable object.
     *
     * @param $op    selection operator
     * @param $page  current page (1 by default)
     * @param $params
     * 	items_per_page
     * 	field field to count by (id by default)
     * 	cop   count operator (for special cases like group by queries)
     * 	ret_schema return schema
     *
     * @return [
     *           'items'  => [...],
     *           'schema' => [...],
     *           'pager'  => ['no' => 5, 'current' => $page, 'count' => $count]
     *           ]
     */
    protected function getPageable($op, $page = 1, $params = [])
    {
        if (is_array($params)) {
            foreach ($params as $k => $v) {
                $$k = $v;
            }
        } else {
            $items_per_page = $params;
        }
        if (!isset($items_per_page)) {
            $items_per_page = 20;
        }
        if (!isset($field)) {
            $field = 'id';
        }
        if (!isset($ret_schema)) {
            $ret_schema = true;
        }
        if (!isset($cop)) {
            $cop = clone $op;
        }
        $my = $this instanceof MySQLDAO;
        if ($my) {
            if ($field != 1 && $field != '*') {
                $field = "'$field'";
            }
            $count = $cop
                ->select(\DAO\Sql\Expr::imbue("COUNT($field)", 'c'))
                ->fetch_assoc()['c'];
        } else {
            $count = $op->x()->count();
        }
        if ($page < 1) {
            $page = 1;
        }
        $pager = [
            'current' => (int) $page,
            'count'   => (int) $count,
            'no'      => int_divide($count, $items_per_page)
                        + ($count % $items_per_page ? 1 : 0),
        ];
        $op = $op->limit($items_per_page, ($page - 1) * $items_per_page);
        $items = $my ? $op->fetch_all() : iterator_to_array($op->x());
        if ($this instanceof MongoDAO) {
            $items = array_values(static::remapIds($items));
        }
        $ret = [
            'items' => $items,
            'pager' => $pager,
        ];
        if ($ret_schema && isset(static::$schema)) {
            $ret['schema'] = static::$schema;
        }
        if (isset($opts)) {
            $ret['params'] = $opts;
        }

        return $ret;
    }
}

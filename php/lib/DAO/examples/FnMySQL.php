<?php
require_once __DIR__ . '/../../autoload.php.inc';
if (!isset($argv)) return;
use DAO\FnMySQL;
$op = FnMySQL::select(['name'])
	->from('users')
	->where(['id' => '1'])
	->x();
//foreach ($op as $row)
//	print_r($row);
$op = FnMySQL::select(['name'])
	->from('users')
	->where(['id' => '1'])
	->x(true); // affected
echo "returned $op rows\n";
$op = FnMySQL::select(['name'])
	->from('users')
	->join('another', ['another.z' => 'p'])
	->where(['id' => '1']);
echo "JOIN query: $op\n";
$op = FnMySQL::insert(['x' => 'y'])
	->in('table')
	->where(['a' => 'b']);
echo "$op\n";
$op = FnMySQL::update()
	->into('table')
	->set(['a' => 'b'])
	->where(['c' => 'd']);
echo "$op\n";
$op = FnMySQL::delete()
	->from('table')
	->where(['c' => 'd']);
echo "$op\n";
$op = FnMySQL::count('ololo')
	->in('table')
	->where(['c' => 'd']);
echo "$op\n";
?>

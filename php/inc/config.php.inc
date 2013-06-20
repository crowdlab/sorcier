<?php
if (!isset($_SERVER['HTTP_HOST']))
	$_SERVER['HTTP_HOST'] = 'localhost';
if (!isset($_config_path)) {
	$default_conf = __DIR__ . "/../../conf/config.json";
	$test_conf    = __DIR__ . "/../../conf/config_test.json";
	$_config_path = ((isset($GLOBALS['flag_test']) && ($GLOBALS['flag_test']))
		|| class_exists('PHPUnit_Runner_Version') || !file_exists($default_conf))
		? $test_conf : $default_conf;
}
$config = json_decode(file_get_contents($_config_path), true);
$host = $config['host'];
$proto = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
	? 'https'
	: 'http';
$config['proto'] = $proto;
$config['prefix'] = "$proto://$host";

$GLOBALS['config'] = $config;
?>

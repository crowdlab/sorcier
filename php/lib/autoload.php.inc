<?php

# to avoid conflicts, please start autoloader names here with 'core_'

function autoload_core_lib($class) {
	$path = str_replace('\\', '/', $class);
	$file = __DIR__ . "/$path.php";
	if (file_exists($file)) {
		/** @noinspection PhpIncludeInspection */
		require_once($file);
		return true;
	} else if (stream_resolve_include_path("$path.php")) {
		require_once "$path.php";
		return true;
	}
	return false;
}

$handlers = [
	'core_lib',
];
foreach ($handlers as $v)
	spl_autoload_register("autoload_$v");
?>

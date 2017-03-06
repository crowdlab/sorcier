<?php

// !! this config is for tests only
if (!isset($GLOBALS['config'])) {
    require_once __DIR__.'/config.php.inc';
}
$GLOBALS['config']['daemon'] = 0;

<?php
set_time_limit(0);

if(php_sapi_name() !== 'cli') {
	die('This script should be runned in CLI mode only');
}

require_once 'include.php';

try {
	$fetcher = new CatFetcher\Fetcher;
	$fetcher->run();
} catch(Throwable $exception) {
	echo "<h1>{$exception->getMessage()}</h1><pre>{$exception->getTraceAsString()}</pre>";
	exit;
}
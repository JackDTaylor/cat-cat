<?php
ini_set('memory_limit', '1024M');

use CatFrontend\View;

require_once 'include.php';

try {
	ob_start();

	$application = new CatFrontend\Frontend;
	$result = $application->run();

	if(is_string($result) || $result instanceof View) {
		header('Content-Type: text/html; charset=utf-8');
		echo $result;
		exit;
	}

	if(is_array($result) || $result instanceof stdClass) {
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($result);
		exit;
	}

	dpr($result);
} catch(Throwable $exception) {
	$requested_with = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? null;

	if($requested_with == 'XMLHttpRequest' || isset($_GET['force_ajax'])) {
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode([
			'success' => false,
			'error' => $exception->getMessage(),
		]);
		exit;
	}

	echo "<h1>{$exception->getMessage()}</h1><pre>{$exception->getTraceAsString()}</pre>";
	exit;
}
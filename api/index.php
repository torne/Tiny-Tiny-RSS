<?php
	error_reporting(E_ERROR | E_PARSE);

	require_once "../config.php";

	set_include_path(dirname(__FILE__) . PATH_SEPARATOR .
		dirname(dirname(__FILE__)) . PATH_SEPARATOR .
		dirname(dirname(__FILE__)) . "/include" . PATH_SEPARATOR .
  		get_include_path());

	chdir("..");

	define('TTRSS_SESSION_NAME', 'ttrss_api_sid');

	require_once "db.php";
	require_once "db-prefs.php";
	require_once "functions.php";
	require_once "sessions.php";

	define('AUTH_DISABLE_OTP', true);

	if (defined('ENABLE_GZIP_OUTPUT') && ENABLE_GZIP_OUTPUT &&
			function_exists("ob_gzhandler")) {

		ob_start("ob_gzhandler");
	} else {
		ob_start();
	}

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

	$input = file_get_contents("php://input");

	if (defined('_API_DEBUG_HTTP_ENABLED') && _API_DEBUG_HTTP_ENABLED) {
		// Override $_REQUEST with JSON-encoded data if available
		// fallback on HTTP parameters
		if ($input) {
			$input = json_decode($input, true);
			if ($input) $_REQUEST = $input;
		}
	} else {
		// Accept JSON only
		$input = json_decode($input, true);
		$_REQUEST = $input;
	}

	if ($_REQUEST["sid"]) {
		session_id($_REQUEST["sid"]);
	}

	@session_start();

	if (!init_connection($link)) return;

	$method = strtolower($_REQUEST["op"]);

	$handler = new API($link, $_REQUEST);

	if ($handler->before($method)) {
		if ($method && method_exists($handler, $method)) {
			$handler->$method();
		} else if (method_exists($handler, 'index')) {
			$handler->index($method);
		}
		$handler->after();
	}

	db_close($link);

	header("Api-Content-Length: " . ob_get_length());

	ob_end_flush();
?>

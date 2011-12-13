<?php
	error_reporting(E_ERROR | E_PARSE);

	require_once "../config.php";

	set_include_path(get_include_path() . PATH_SEPARATOR .
		dirname(__FILE__) . PATH_SEPARATOR .
		dirname(dirname(__FILE__)) . PATH_SEPARATOR .
		dirname(dirname(__FILE__)) . "/include" );

	function __autoload($class) {
		$file = "classes/".strtolower(basename($class)).".php";
		if (file_exists($file)) {
			require $file;
		}
	}

	require_once "db.php";
	require_once "db-prefs.php";
	require_once "functions.php";

	chdir("..");

	if (defined('ENABLE_GZIP_OUTPUT') && ENABLE_GZIP_OUTPUT) {
		ob_start("ob_gzhandler");
	}

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

	$session_expire = SESSION_EXPIRE_TIME; //seconds
	$session_name = (!defined('TTRSS_SESSION_NAME')) ? "ttrss_sid_api" : TTRSS_SESSION_NAME . "_api";

	session_name($session_name);

	$input = file_get_contents("php://input");

	// Override $_REQUEST with JSON-encoded data if available
	if ($input) {
		$input = json_decode($input, true);

		if ($input) $_REQUEST = $input;
	}

	if ($_REQUEST["sid"]) {
		session_id($_REQUEST["sid"]);
	}

	session_start();

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

?>

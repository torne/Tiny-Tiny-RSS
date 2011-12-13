<?php
	set_include_path(get_include_path() . PATH_SEPARATOR . "include");

	/* remove ill effects of magic quotes */

	if (get_magic_quotes_gpc()) {
		function stripslashes_deep($value) {
			$value = is_array($value) ?
				array_map('stripslashes_deep', $value) : stripslashes($value);
				return $value;
		}

		$_POST = array_map('stripslashes_deep', $_POST);
		$_GET = array_map('stripslashes_deep', $_GET);
		$_COOKIE = array_map('stripslashes_deep', $_COOKIE);
		$_REQUEST = array_map('stripslashes_deep', $_REQUEST);
	}

	require_once "functions.php";
	require_once "sessions.php";
	require_once "sanity_check.php";
	require_once "config.php";
	require_once "db.php";
	require_once "db-prefs.php";

	no_cache_incantation();

	startup_gettext();

	$script_started = getmicrotime();

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

	if (!init_connection($link)) return;

	if (ENABLE_GZIP_OUTPUT) {
		ob_start("ob_gzhandler");
	}

	function __autoload($class) {
		$file = "classes/".strtolower(basename($class)).".php";
		if (file_exists($file)) {
			require $file;
		}
	}

	$method = $_REQUEST["op"];

	$handler = new Public_Handler($link, $_REQUEST);

	if ($handler) {
		if ($handler->before()) {
			if ($method && method_exists($handler, $method)) {
				$handler->$method();
			} else if (method_exists($handler, 'index')) {
				$handler->index();
			}
			$handler->after();
			return;
		}
	}

	header("Content-Type: text/plain");
	print json_encode(array("error" => array("code" => 7)));

	// We close the connection to database.
	db_close($link);
?>

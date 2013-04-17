<?php
	set_include_path(dirname(__FILE__) ."/include" . PATH_SEPARATOR .
		get_include_path());

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

	require_once "autoload.php";
	require_once "sessions.php";
	require_once "functions.php";
	require_once "sanity_check.php";
	require_once "config.php";
	require_once "db.php";
	require_once "db-prefs.php";

	startup_gettext();

	$script_started = microtime(true);

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

	if (!init_plugins($link)) return;

	if (ENABLE_GZIP_OUTPUT && function_exists("ob_gzhandler")) {
		ob_start("ob_gzhandler");
	}

	$method = $_REQUEST["op"];

	global $pluginhost;
	$override = $pluginhost->lookup_handler("public", $method);

	if ($override) {
		$handler = $override;
	} else {
		$handler = new Handler_Public($link, $_REQUEST);
	}

	if (implements_interface($handler, "IHandler") && $handler->before($method)) {
		if ($method && method_exists($handler, $method)) {
			$handler->$method();
		} else if (method_exists($handler, 'index')) {
			$handler->index();
		}
		$handler->after();
		return;
	}

	header("Content-Type: text/plain");
	print json_encode(array("error" => array("code" => 7)));

	// We close the connection to database.
	db_close($link);
?>

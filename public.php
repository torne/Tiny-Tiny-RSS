<?php
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

	$op = $_REQUEST["op"];

	require_once "functions.php";
	if ($op != "share") require_once "sessions.php";
	require_once "modules/backend-rpc.php";
	require_once "sanity_check.php";
	require_once "config.php";
	require_once "db.php";
	require_once "db-prefs.php";

	no_cache_incantation();

	startup_gettext();

	$script_started = getmicrotime();

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

	if (!$link) {
		if (DB_TYPE == "mysql") {
			print mysql_error();
		}
		// PG seems to display its own errors just fine by default.
		return;
	}

	init_connection($link);

	$method = $_REQUEST["method"];
	$mode = $_REQUEST["mode"];

	if ((!$op || $op == "rss" || $op == "dlg") && !$_REQUEST["noxml"]) {
			header("Content-Type: application/xml; charset=utf-8");
	} else {
			header("Content-Type: text/plain; charset=utf-8");
	}

	if (ENABLE_GZIP_OUTPUT) {
		ob_start("ob_gzhandler");
	}

	handle_public_request($link, $op);

	// We close the connection to database.
	db_close($link);
?>

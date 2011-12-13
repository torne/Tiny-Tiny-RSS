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

	function __autoload($class) {
		$file = "classes/".strtolower(basename($class)).".php";
		if (file_exists($file)) {
			require $file;
		}
	}

	$op = $_REQUEST["op"];

	require_once "functions.php";
	if ($op != "share") require_once "sessions.php";
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

	$method = $_REQUEST['subop'] ? $_REQUEST['subop'] : $_REQUEST["method"];

	header("Content-Type: text/plain; charset=utf-8");

	if (ENABLE_GZIP_OUTPUT) {
		ob_start("ob_gzhandler");
	}

	if (SINGLE_USER_MODE) {
		authenticate_user($link, "admin", null);
	}

	$public_calls = array("globalUpdateFeeds", "rss", "getUnread", "getProfiles", "share",
		"fbexport", "logout", "pubsub");

	if (array_search($op, $public_calls) !== false) {

		handle_public_request($link, $op);
		return;

	} else if (!($_SESSION["uid"] && validate_session($link))) {
		if ($op == 'pref-feeds' && $method == 'add') {
			header("Content-Type: text/html");
			login_sequence($link);
			render_login_form($link);
		} else {
			header("Content-Type: text/plain");
			print json_encode(array("error" => array("code" => 6)));
		}
		return;
	}

	$purge_intervals = array(
		0  => __("Use default"),
		-1 => __("Never purge"),
		5  => __("1 week old"),
		14 => __("2 weeks old"),
		31 => __("1 month old"),
		60 => __("2 months old"),
		90 => __("3 months old"));

	$update_intervals = array(
		0   => __("Default interval"),
		-1  => __("Disable updates"),
		15  => __("Each 15 minutes"),
		30  => __("Each 30 minutes"),
		60  => __("Hourly"),
		240 => __("Each 4 hours"),
		720 => __("Each 12 hours"),
		1440 => __("Daily"),
		10080 => __("Weekly"));

	$update_intervals_nodefault = array(
		-1  => __("Disable updates"),
		15  => __("Each 15 minutes"),
		30  => __("Each 30 minutes"),
		60  => __("Hourly"),
		240 => __("Each 4 hours"),
		720 => __("Each 12 hours"),
		1440 => __("Daily"),
		10080 => __("Weekly"));

	$update_methods = array(
		0   => __("Default"),
		1   => __("Magpie"),
		2   => __("SimplePie"),
		3   => __("Twitter OAuth"));

	if (DEFAULT_UPDATE_METHOD == "1") {
		$update_methods[0] .= ' (SimplePie)';
	} else {
		$update_methods[0] .= ' (Magpie)';
	}

	$access_level_names = array(
		0 => __("User"),
		5 => __("Power User"),
		10 => __("Administrator"));

	$error = sanity_check($link);

	if ($error['code'] != 0 && $op != "logout") {
		print json_encode(array("error" => $error));
		return;
	}

	$op = str_replace("-", "_", $op);

	if (class_exists($op)) {
		$handler = new $op($link, $_REQUEST);

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
	}

	switch($op) { // Select action according to $op value.

		case "pref_filters":
			require_once "modules/pref-filters.php";
			module_pref_filters($link);
		break; // pref-filters

		case "pref_labels":
			require_once "modules/pref-labels.php";
			module_pref_labels($link);
		break; // pref-labels

		case "pref_users":
			require_once "modules/pref-users.php";
			module_pref_users($link);
		break; // prefs-users

		case "pref_instances":
			require_once "modules/pref-instances.php";
			module_pref_instances($link);
		break; // pref-instances

		default:
			header("Content-Type: text/plain");
			print json_encode(array("error" => array("code" => 7)));
		break; // fallback
	} // Select action according to $op value.

	// We close the connection to database.
	db_close($link);
?>

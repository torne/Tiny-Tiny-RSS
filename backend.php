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

	$op = $_REQUEST["op"];
	@$method = $_REQUEST['subop'] ? $_REQUEST['subop'] : $_REQUEST["method"];

	if (!$method)
		$method = 'index';
	else
		$method = strtolower($method);

	/* Public calls compatibility shim */

	$public_calls = array("globalUpdateFeeds", "rss", "getUnread", "getProfiles", "share",
		"fbexport", "logout", "pubsub");

	if (array_search($op, $public_calls) !== false) {
		header("Location: public.php?" . $_SERVER['QUERY_STRING']);
		return;
	}

	@$csrf_token = $_REQUEST['csrf_token'];

	require_once "autoload.php";
	require_once "sessions.php";
	require_once "functions.php";
	require_once "config.php";
	require_once "db.php";
	require_once "db-prefs.php";

	startup_gettext();

	$script_started = microtime(true);

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

	if (!init_plugins($link)) return;

	header("Content-Type: text/json; charset=utf-8");

	if (ENABLE_GZIP_OUTPUT && function_exists("ob_gzhandler")) {
		ob_start("ob_gzhandler");
	}

	if (SINGLE_USER_MODE) {
		authenticate_user($link, "admin", null);
	}

	if ($_SESSION["uid"]) {
		if (!validate_session($link)) {
			header("Content-Type: text/json");
			print json_encode(array("error" => array("code" => 6)));
			return;
		}
		load_user_plugins($link, $_SESSION["uid"]);
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

	$access_level_names = array(
		0 => __("User"),
		5 => __("Power User"),
		10 => __("Administrator"));

	#$error = sanity_check($link);

	#if ($error['code'] != 0 && $op != "logout") {
	#	print json_encode(array("error" => $error));
	#	return;
	#}

	$op = str_replace("-", "_", $op);

	global $pluginhost;
	$override = $pluginhost->lookup_handler($op, $method);

	if (class_exists($op) || $override) {

		if ($override) {
			$handler = $override;
		} else {
			$handler = new $op($link, $_REQUEST);
		}

		if ($handler && implements_interface($handler, 'IHandler')) {
			if (validate_csrf($csrf_token) || $handler->csrf_ignore($method)) {
				if ($handler->before($method)) {
					if ($method && method_exists($handler, $method)) {
						$handler->$method();
					} else {
						if (method_exists($handler, "catchall")) {
							$handler->catchall($method);
						}
					}
					$handler->after();
					return;
				} else {
					header("Content-Type: text/json");
					print json_encode(array("error" => array("code" => 6)));
					return;
				}
			} else {
				header("Content-Type: text/json");
				print json_encode(array("error" => array("code" => 6)));
				return;
			}
		}
	}

	header("Content-Type: text/json");
	print json_encode(array("error" => array("code" => 7)));

	// We close the connection to database.
	db_close($link);
?>

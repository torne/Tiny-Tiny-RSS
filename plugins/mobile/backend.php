<?php
	error_reporting(E_ERROR | E_WARNING | E_PARSE);

	header('Content-Type: text/html; charset=utf-8');

	$basedir = dirname(dirname(dirname(__FILE__)));

	set_include_path(
		dirname(__FILE__) . PATH_SEPARATOR .
		$basedir . PATH_SEPARATOR .
		"$basedir/include" . PATH_SEPARATOR .
		get_include_path());

	define('MOBILE_VERSION', true);

	require_once "config.php";
	require_once "mobile-functions.php";

	require_once "functions.php";
	require_once "sessions.php";
	require_once "version.php";
	require_once "db-prefs.php";

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

	init_plugins($link);

	if (!$_SESSION["uid"]) return;

	$op = $_REQUEST["op"];

	switch ($op) {
	case "toggleMarked":
		$cmode = db_escape_string($_REQUEST["mark"]);
		$id = db_escape_string($_REQUEST["id"]);

		markArticlesById(array($id), $cmode);
		break;
	case "togglePublished":
		$cmode = db_escape_string($_REQUEST["pub"]);
		$id = db_escape_string($_REQUEST["id"]);

		publishArticlesById(array($id), $cmode);
		break;
	case "toggleUnread":
		$cmode = db_escape_string($_REQUEST["unread"]);
		$id = db_escape_string($_REQUEST["id"]);

		catchupArticlesById(array($id), $cmode);
		break;

	case "setPref":
		$id = db_escape_string($_REQUEST["id"]);
		$value = db_escape_string($_REQUEST["to"]);
		mobile_set_pref($id, $value);
		print_r($_SESSION);
		break;
	default:
		print json_encode(array("error", "UNKNOWN_METHOD"));
		break;
	}
?>


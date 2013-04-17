<?php
	error_reporting(E_ERROR | E_WARNING | E_PARSE);

	header('Content-Type: text/html; charset=utf-8');

	define('MOBILE_VERSION', true);

	$basedir = dirname(dirname(dirname(__FILE__)));

	set_include_path(
		dirname(__FILE__) . PATH_SEPARATOR .
		$basedir . PATH_SEPARATOR .
		"$basedir/include" . PATH_SEPARATOR .
		get_include_path());

	require_once "config.php";
	require_once "mobile-functions.php";

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

	init_plugins($link);

	login_sequence( true);

	$feed_id = db_escape_string( $_REQUEST["id"]);
	$cat_id = db_escape_string( $_REQUEST["cat"]);
	$offset = (int) db_escape_string( $_REQUEST["skip"]);
	$search = db_escape_string( $_REQUEST["search"]);
	$is_cat = (bool) db_escape_string( $_REQUEST["is_cat"]);

  	render_headlines_list( $feed_id, $cat_id, $offset, $search, $is_cat);
?>


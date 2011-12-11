<?php
	error_reporting(E_ERROR | E_WARNING | E_PARSE);

	header('Content-Type: text/html; charset=utf-8');

	define('MOBILE_VERSION', true);

	require_once "../config.php";
	require_once "mobile-functions.php";

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

	init_connection($link);

	login_sequence($link, true);

	$feed_id = db_escape_string($_REQUEST["id"]);
	$cat_id = db_escape_string($_REQUEST["cat"]);
	$offset = (int) db_escape_string($_REQUEST["skip"]);
	$search = db_escape_string($_REQUEST["search"]);
	$is_cat = (bool) db_escape_string($_REQUEST["is_cat"]);

  	render_headlines_list($link, $feed_id, $cat_id, $offset, $search, $is_cat);
?>


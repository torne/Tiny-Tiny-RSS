<?php
	error_reporting(E_ERROR | E_WARNING | E_PARSE);

	header('Content-Type: text/html; charset=utf-8');

	define('MOBILE_VERSION', true);

	require_once "../config.php";
	require_once "mobile-functions.php";

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

	init_connection($link);

	login_sequence($link, true);

	$id = db_escape_string($_REQUEST["id"]);
	$feed_id = db_escape_string($_REQUEST["feed"]);
	$cat_id = db_escape_string($_REQUEST["cat"]);
	$is_cat = db_escape_string($_REQUEST["is_cat"]);

  	render_article($link, $id, $feed_id, $cat_id, $is_cat);
?>


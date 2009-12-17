<?php
	error_reporting(E_ERROR | E_WARNING | E_PARSE);

	header('Content-Type: text/html; charset=utf-8');

	define('MOBILE_VERSION', true);

	require_once "../config.php";
	require_once "functions.php";
	require_once "../functions.php"; 

	require_once "../sessions.php";

	require_once "../version.php"; 
	require_once "../db-prefs.php";

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

	init_connection($link);

	login_sequence($link, true);

	$feed_id = db_escape_string($_REQUEST["id"]);
	$cat_id = db_escape_string($_REQUEST["cat"]);

  	render_headlines_list($link, $feed_id, $cat_id);
?>


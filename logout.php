<?
	session_start();

	require_once "config.php";
	require_once "functions.php";

	logout_user();

	if (!USE_HTTP_AUTH) {
		header("Location: login.php");
	} else {
		header("Location: tt-rss.php");
	}
?>

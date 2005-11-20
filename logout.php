<?
	session_start();

	require_once "config.php";

	$_SESSION["uid"] = null;
	$_SESSION["name"] = null;
	$_SESSION["access_level"] = null;

	session_destroy();

	if (!USE_HTTP_AUTH) {
		header("Location: login.php");
	} else {
		header("Location: tt-rss.php");
	}
?>

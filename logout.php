<?
	session_start();

	$_SESSION["uid"] = null;
	$_SESSION["name"] = null;
	$_SESSION["access_level"] = null;

	session_destroy();

	header("Location: login.php");

?>

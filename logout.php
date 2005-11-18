<?
	session_start();

	$_SESSION["uid"] = null;
	$_SESSION["name"] = null;
	
	session_destroy();

	header("Location: tt-rss.php");

?>

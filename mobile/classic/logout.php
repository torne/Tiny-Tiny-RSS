<?php	
	require_once "functions.php";
	require_once "../../sessions.php";
	require_once "../../functions.php";

	logout_user();

	header("Location: index.php");
?>

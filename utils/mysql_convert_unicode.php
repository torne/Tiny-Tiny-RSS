<?php
	error_reporting(E_ERROR | E_WARNING | E_PARSE);

	require_once "../sessions.php";
	
	require_once "../sanity_check.php";
	require_once "../functions.php";
	require_once "../config.php";
	require_once "../db.php";
	
	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);	

	init_connection($link);	

	login_sequence($link);
	
	$owner_uid = $_SESSION["uid"];
	
	if (!SINGLE_USER_MODE && $_SESSION["access_level"] < 10) { 
		$_SESSION["login_error_msg"] = __("Your access level is insufficient to run this script.");
		render_login_form($link);
		exit;
	}


?>

<html>
<head>
<title>MySQL Charset Converter</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<link rel="stylesheet" type="text/css" href="utility.css">
<script type="text/javascript" src="localized_js.php"></script>
</head>

<body>

<script type='text/javascript'>
function confirmOP() {
	return confirm(__("Update the database?"));
}
</script>

<div class="floatingLogo"><img src="images/ttrss_logo.png"></div>

<h1><?php echo __("MySQL Charset Updater") ?></h1>

<?php

	$op = $_POST["op"];

	if (DB_TYPE != "mysql") {
		print_warning(__("This script is for Tiny Tiny RSS installations with MySQL backend only."));

		print "<form method=\"GET\" action=\"logout.php\">
			<input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\">
			</form>";

	} else if (!$op) {

		print_warning(__("Please backup your database before proceeding."));

		print "<p>" . __("This script will convert your Tiny Tiny RSS database to UTF-8. 
			Depending on current database charset you may experience data corruption (lost accent characters, etc.). 
			After update, you'll have to set <b>MYSQL_CHARSET</b> option in config.php to 'utf8'.") . "</p>";
	
		print "<form method='POST'>
			<input type='hidden' name='op' value='do'>
			<input type='submit' onclick='return confirmOP()' value='".__("Perform updates")."'>
			</form>";
	
	} else if ($op == "do") {
	
		print "<p>".__("Converting database...")."</p>";

		db_query($link, "BEGIN");
		db_query($link, "SET FOREIGN_KEY_CHECKS=0");

		$result = db_query($link, "SHOW TABLES LIKE 'ttrss%'");

		while ($line = db_fetch_assoc($result)) {
			$vals = array_values($line);
			$table = $vals[0];

			$query = "ALTER TABLE $table CONVERT TO
				CHARACTER SET 'utf8'";

			print "<p class='query'>$query</p>";

			db_query($link, $query);
		}

		db_query($link, "SET FOREIGN_KEY_CHECKS=1");
		db_query($link, "COMMIT");

		print "<form method=\"GET\" action=\"logout.php\">
			<input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\">
			</form>";

	}
	
?>

</body>
</html>


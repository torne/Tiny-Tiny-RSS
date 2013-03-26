<html>
<head>
	<title>Tiny Tiny RSS - Installer</title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<link rel="stylesheet" type="text/css" href="../utility.css">
	<style type="text/css">
	textarea { font-size : 12px; }
	</style>
</head>
<body>

<?
	function sanity_check($db_type) {
		$errors = array();

		if (version_compare(PHP_VERSION, '5.3.0', '<')) {
			array_push($errors, "PHP version 5.3.0 or newer required.");
		}

		if (ini_get("open_basedir")) {
			array_push($errors, "PHP configuration option open_basedir is not supported. Please disable this in PHP settings file (php.ini).");
		}

		if (!function_exists("curl_init") && !ini_get("allow_url_fopen")) {
			array_push($errors, "PHP configuration option allow_url_fopen is disabled, and CURL functions are not present. Either enable allow_url_fopen or install PHP extension for CURL.");
		}

		if (!function_exists("json_encode")) {
			array_push($errors, "PHP support for JSON is required, but was not found.");
		}

		if ($db_type == "mysql" && !function_exists("mysql_connect")) {
			array_push($errors, "PHP support for MySQL is required for configured $db_type in config.php.");
		}

		if ($db_type == "pgsql" && !function_exists("pg_connect")) {
			array_push($errors, "PHP support for PostgreSQL is required for configured $db_type in config.php");
		}

		if (!function_exists("mb_strlen")) {
			array_push($errors, "PHP support for mbstring functions is required but was not found.");
		}

		if (!function_exists("hash")) {
			array_push($errors, "PHP support for hash() function is required but was not found.");
		}

		if (!function_exists("ctype_lower")) {
			array_push($errors, "PHP support for ctype functions are required by HTMLPurifier.");
		}

		if (!function_exists("iconv")) {
			array_push($errors, "PHP support for iconv is required to handle multiple charsets.");
		}

		/* if (ini_get("safe_mode")) {
			array_push($errors, "PHP safe mode setting is not supported.");
		} */

		if ((PUBSUBHUBBUB_HUB || PUBSUBHUBBUB_ENABLED) && !function_exists("curl_init")) {
			array_push($errors, "PHP support for CURL is required for PubSubHubbub.");
		}

		if (!class_exists("DOMDocument")) {
			array_push($errors, "PHP support for DOMDocument is required, but was not found.");
		}

		return $errors;
	}

	function print_error($msg) {
		print "<div class='error'><img src='../images/sign_excl.svg'> $msg</div>";
	}

	function print_notice($msg) {
		print "<div class=\"notice\">
			<img src=\"../images/sign_info.svg\">$msg</div>";
	}

	function db_connect($host, $user, $pass, $db, $type) {
		if ($type == "pgsql") {

			$string = "dbname=$db user=$user";

			if ($pass) {
				$string .= " password=$pass";
			}

			if ($host) {
				$string .= " host=$host";
			}

			if (defined('DB_PORT')) {
				$string = "$string port=" . DB_PORT;
			}

			$link = pg_connect($string);

			return $link;

		} else if ($type == "mysql") {
			$link = mysql_connect($host, $user, $pass);
			if ($link) {
				$result = mysql_select_db($db, $link);
				return $link;
			}
		}
	}

	function db_query($link, $query, $type, $die_on_error = true) {
		if ($type == "pgsql") {
			$result = pg_query($link, $query);
			if (!$result) {
				$query = htmlspecialchars($query); // just in case
				if ($die_on_error) {
					die("Query <i>$query</i> failed [$result]: " . ($link ? pg_last_error($link) : "No connection"));
				}
			}
			return $result;
		} else if ($type == "mysql") {
			$result = mysql_query($query, $link);
			if (!$result) {
				$query = htmlspecialchars($query);
				if ($die_on_error) {
					die("Query <i>$query</i> failed: " . ($link ? mysql_error($link) : "No connection"));
				}
			}
			return $result;
		}
	}

?>

<div class="floatingLogo"><img src="../images/logo_wide.png"></div>

<h1>Tiny Tiny RSS Installer</h1>

<?php
	if (file_exists("../config.php")) {
		require "../config.php";

		if (!defined('_INSTALLER_IGNORE_CONFIG_CHECK')) {
			print_error("Error: config.php already exists; aborting.");
			exit;
		}
	}

	@$op = $_REQUEST['op'];

	@$DB_HOST = strip_tags($_POST['DB_HOST']);
	@$DB_TYPE = strip_tags($_POST['DB_TYPE']);
	@$DB_USER = strip_tags($_POST['DB_USER']);
	@$DB_NAME = strip_tags($_POST['DB_NAME']);
	@$DB_PASS = strip_tags($_POST['DB_PASS']);
	@$DB_PORT = strip_tags($_POST['DB_PORT']);

?>

<h2>Database settings</h2>

<form action="" method="post">
<input type="hidden" name="op" value="testconfig">

<?php
	$issel_pgsql = $DB_TYPE == "pgsql" ? "selected" : "";
	$issel_mysql = $DB_TYPE == "mysql" ? "selected" : "";
?>

<fieldset>
	<label>Database type</label>
	<select name="DB_TYPE">
		<option <?php echo $issel_pgsql ?> value="pgsql">PostgreSQL</option>
		<option <?php echo $issel_mysql ?> value="mysql">MySQL</option>
	</select>
</fieldset>

<fieldset>
	<label>Username</label>
	<input required name="DB_USER" size="20" value="<?php echo $DB_USER ?>"/>
</fieldset>

<fieldset>
	<label>Password</label>
	<input required name="DB_PASS" size="20" type="password" value="<?php echo $DB_PASS ?>"/>
</fieldset>

<fieldset>
	<label>Database name</label>
	<input name="DB_NAME" size="20" value="<?php echo $DB_NAME ?>"/>
</fieldset>

<fieldset>
	<label>Host name</label>
	<input  name="DB_HOST" placeholder="if needed" size="20" value="<?php echo $DB_HOST ?>"/>
</fieldset>

<fieldset>
	<label>Port</label>
	<input name="DB_PORT" placeholder="if needed, PgSQL only" size="20" value="<?php echo $DB_PORT ?>"/>
</fieldset>

<p><input type="submit" value="Test configuration"></p>

</form>

<?php if ($op == 'testconfig') { ?>

	<h2>Checking configuration</h2>

	<?php
		$errors = sanity_check($DB_TYPE);

		if (count($errors) > 0) {
			print "<p>Some configuration tests failed. Please correct them before continuing.</p>";

			print "<ul>";

			foreach ($errors as $error) {
				print "<li style='color : red'>$error</li>";
			}

			print "</ul>";

			exit;
		}

	?>

	<h2>Checking database</h2>

	<?php
		$link = db_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_TYPE);

		if (!$link) {
			print_error("Unable to connect to database using specified parameters.");
			exit;
		}

		print_notice("Database test succeeded."); ?>

			<h2>Initialize database</h2>

			<p>Before you can start using tt-rss, database needs to be initialized. Click on the button below to do that now.</p>

			<?php
				$result = db_query($link, "SELECT true FROM ttrss_feeds", $DB_TYPE, false);

				if ($result) {
					print_error("Existing tt-rss tables will be removed from the database. If you would like to keep your data, skip database initialization.");
					$need_confirm = true;
				} else {
					$need_confirm = false;
				}
			?>

			<table><tr><td>
			<form method="post">
				<input type="hidden" name="op" value="installschema">

				<input type="hidden" name="DB_USER" value="<?php echo $DB_USER ?>"/>
				<input type="hidden" name="DB_PASS" value="<?php echo $DB_PASS ?>"/>
				<input type="hidden" name="DB_NAME" value="<?php echo $DB_NAME ?>"/>
				<input type="hidden" name="DB_HOST" value="<?php echo $DB_HOST ?>"/>
				<input type="hidden" name="DB_PORT" value="<?php echo $DB_PORT ?>"/>
				<input type="hidden" name="DB_TYPE" value="<?php echo $DB_TYPE ?>"/>

				<?php if ($need_confirm) { ?>
					<p><input onclick="return confirm('Please read the warning above. Continue?')" type="submit" value="Initialize database" style="color : red"></p>
				<?php } else { ?>
					<p><input type="submit" value="Initialize database" style="color : red"></p>
				<?php } ?>
			</form>

			</td><td>
			<form method="post">
				<input type="hidden" name="DB_USER" value="<?php echo $DB_USER ?>"/>
				<input type="hidden" name="DB_PASS" value="<?php echo $DB_PASS ?>"/>
				<input type="hidden" name="DB_NAME" value="<?php echo $DB_NAME ?>"/>
				<input type="hidden" name="DB_HOST" value="<?php echo $DB_HOST ?>"/>
				<input type="hidden" name="DB_PORT" value="<?php echo $DB_PORT ?>"/>
				<input type="hidden" name="DB_TYPE" value="<?php echo $DB_TYPE ?>"/>

				<input type="hidden" name="op" value="skipschema">
				<p><input type="submit" value="Skip initialization"></p>
			</form>

			</td></tr></table>

			<?php

		} else if ($op == 'installschema' || $op == 'skipschema') {

			$link = db_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_TYPE);

			if (!$link) {
				print_error("Unable to connect to database using specified parameters.");
				exit;
			}

			if ($op == 'installschema') {

				print "<h2>Initializing database...</h2>";

				$lines = explode(";", preg_replace("/[\r\n]/", "", file_get_contents("../schema/ttrss_schema_".basename($DB_TYPE).".sql")));

				foreach ($lines as $line) {
					if (strpos($line, "--") !== 0 && $line) {
						db_query($link, $line, $DB_TYPE);
					}
				}

				print_notice("Database initialization completed.");

			} else {
				print_notice("Database initialization skipped.");
			}

			print "<h2>Generated configuration file</h2>";

			print "<p>Copy following text and save as <b>config.php</b> in tt-rss main directory. It is suggested to read through the file to the end in case you need any options changed fom default values.</p>";

			print "<textarea cols=\"80\" rows=\"20\" name=\"config\">";
			$data = explode("\n", file_get_contents("../config.php-dist"));

			foreach ($data as $line) {
				if (preg_match("/define\('DB_TYPE'/", $line)) {
					echo "\tdefine('DB_TYPE', '$DB_TYPE')\n";
				} else if (preg_match("/define\('DB_HOST'/", $line)) {
					echo "\tdefine('DB_HOST', '$DB_HOST')\n";
				} else if (preg_match("/define\('DB_USER'/", $line)) {
					echo "\tdefine('DB_USER', '$DB_USER')\n";
				} else if (preg_match("/define\('DB_NAME'/", $line)) {
					echo "\tdefine('DB_NAME', '$DB_NAME')\n";
				} else if (preg_match("/define\('DB_PASS'/", $line)) {
					echo "\tdefine('DB_PASS', '$DB_PASS')\n";
				} else if (preg_match("/define\('DB_PORT'/", $line)) {
					echo "\tdefine('DB_PORT', '$DB_PORT')\n";

				} else {
					print "$line\n";
				}
			}

			print "</textarea>";

			print "<p>You can generate the file again by changing the form above.</p>";
		}
	?>


</body>
</html>

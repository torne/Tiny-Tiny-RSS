<html>
<head>
	<title>Tiny Tiny RSS - Installer</title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<link rel="stylesheet" type="text/css" href="../css/utility.css">
	<link rel="stylesheet" type="text/css" href="../css/dijit.css">
	<style type="text/css">
	textarea { font-size : 12px; }
	</style>
</head>
<body>

<?php

	// could be needed because of existing config.php
	function define_default($param, $value) {
		//
	}

	function make_password($length = 8) {

		$password = "";
		$possible = "0123456789abcdfghjkmnpqrstvwxyzABCDFGHJKMNPQRSTVWXYZ*%+^";

   	$i = 0;

		while ($i < $length) {
			$char = substr($possible, mt_rand(0, strlen($possible)-1), 1);

			if (!strstr($password, $char)) {
				$password .= $char;
				$i++;
			}
		}
		return $password;
	}


	function sanity_check($db_type) {
		$errors = array();

		if (version_compare(PHP_VERSION, '5.3.0', '<')) {
			array_push($errors, "PHP version 5.3.0 or newer required.");
		}

		if (!function_exists("curl_init") && !ini_get("allow_url_fopen")) {
			array_push($errors, "PHP configuration option allow_url_fopen is disabled, and CURL functions are not present. Either enable allow_url_fopen or install PHP extension for CURL.");
		}

		if (!function_exists("json_encode")) {
			array_push($errors, "PHP support for JSON is required, but was not found.");
		}

		if ($db_type == "mysql" && !function_exists("mysql_connect") && !function_exists("mysqli_connect")) {
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

		if (!class_exists("DOMDocument")) {
			array_push($errors, "PHP support for DOMDocument is required, but was not found.");
		}

		return $errors;
	}

	function print_error($msg) {
		print "<div class='error'><span><img src='../images/alert.png'></span>
			<span>$msg</span></div>";
	}

	function print_notice($msg) {
		print "<div class=\"notice\">
			<span><img src=\"../images/information.png\"></span><span>$msg</span></div>";
	}

	function db_connect($host, $user, $pass, $db, $type, $port = false) {
		if ($type == "pgsql") {

			$string = "dbname=$db user=$user";

			if ($pass) {
				$string .= " password=$pass";
			}

			if ($host) {
				$string .= " host=$host";
			}

			if ($port) {
				$string = "$string port=" . $port;
			}

			$link = pg_connect($string);

			return $link;

		} else if ($type == "mysql") {
			if (function_exists("mysqli_connect")) {
				if ($port)
					return mysqli_connect($host, $user, $pass, $db, $port);
				else
					return mysqli_connect($host, $user, $pass, $db);

			} else {
				$link = mysql_connect($host, $user, $pass);
				if ($link) {
					$result = mysql_select_db($db, $link);
					if ($result) return $link;
				}
			}
		}
	}

	function make_config($DB_TYPE, $DB_HOST, $DB_USER, $DB_NAME, $DB_PASS,
			$DB_PORT, $SELF_URL_PATH) {

		$data = explode("\n", file_get_contents("../config.php-dist"));

		$rv = "";

		$finished = false;

		if (function_exists("mcrypt_decrypt")) {
			$crypt_key = make_password(24);
		} else {
			$crypt_key = "";
		}

		foreach ($data as $line) {
			if (preg_match("/define\('DB_TYPE'/", $line)) {
				$rv .= "\tdefine('DB_TYPE', '$DB_TYPE');\n";
			} else if (preg_match("/define\('DB_HOST'/", $line)) {
				$rv .= "\tdefine('DB_HOST', '$DB_HOST');\n";
			} else if (preg_match("/define\('DB_USER'/", $line)) {
				$rv .= "\tdefine('DB_USER', '$DB_USER');\n";
			} else if (preg_match("/define\('DB_NAME'/", $line)) {
				$rv .= "\tdefine('DB_NAME', '$DB_NAME');\n";
			} else if (preg_match("/define\('DB_PASS'/", $line)) {
				$rv .= "\tdefine('DB_PASS', '$DB_PASS');\n";
			} else if (preg_match("/define\('DB_PORT'/", $line)) {
				$rv .= "\tdefine('DB_PORT', '$DB_PORT');\n";
			} else if (preg_match("/define\('SELF_URL_PATH'/", $line)) {
				$rv .= "\tdefine('SELF_URL_PATH', '$SELF_URL_PATH');\n";
			} else if (preg_match("/define\('FEED_CRYPT_KEY'/", $line)) {
				$rv .= "\tdefine('FEED_CRYPT_KEY', '$crypt_key');\n";
			} else if (!$finished) {
				$rv .= "$line\n";
			}

			if (preg_match("/\?\>/", $line)) {
				$finished = true;
			}
		}

		return $rv;
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

			if (function_exists("mysqli_connect")) {
				$result = mysqli_query($link, $query);
			} else {
				$result = mysql_query($query, $link);
			}
			if (!$result) {
				$query = htmlspecialchars($query);
				if ($die_on_error) {
					die("Query <i>$query</i> failed: " . ($link ? function_exists("mysqli_connect") ? mysqli_error($link) : mysql_error($link) : "No connection"));
				}
			}
			return $result;
		}
	}

	function make_self_url_path() {
		$url_path = ((!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != "on") ? 'http://' :  'https://') . $_SERVER["HTTP_HOST"] . parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

		return $url_path;
	}

?>

<div class="floatingLogo"><img src="../images/logo_small.png"></div>

<h1>Tiny Tiny RSS Installer</h1>

<div class='content'>

<?php

	if (file_exists("../config.php")) {
		require "../config.php";

		if (!defined('_INSTALLER_IGNORE_CONFIG_CHECK')) {
			print_error("Error: config.php already exists in tt-rss directory; aborting.");
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
	@$SELF_URL_PATH = strip_tags($_POST['SELF_URL_PATH']);

	if (!$SELF_URL_PATH) {
		$SELF_URL_PATH = preg_replace("/\/install\/$/", "/", make_self_url_path());
	}
?>

<form action="" method="post">
<input type="hidden" name="op" value="testconfig">

<h2>Database settings</h2>

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
	<input name="DB_PASS" size="20" type="password" value="<?php echo $DB_PASS ?>"/>
</fieldset>

<fieldset>
	<label>Database name</label>
	<input required name="DB_NAME" size="20" value="<?php echo $DB_NAME ?>"/>
</fieldset>

<fieldset>
	<label>Host name</label>
	<input name="DB_HOST" size="20" value="<?php echo $DB_HOST ?>"/>
	<span class="hint">If needed</span>
</fieldset>

<fieldset>
	<label>Port</label>
	<input name="DB_PORT" type="number" size="20" value="<?php echo $DB_PORT ?>"/>
	<span class="hint">Usually 3306 for MySQL or 5432 for PostgreSQL</span>
</fieldset>

<h2>Other settings</h2>

<p>This should be set to the location your Tiny Tiny RSS will be available on.</p>

<fieldset>
	<label>Tiny Tiny RSS URL</label>
	<input type="url" name="SELF_URL_PATH" placeholder="<?php echo $SELF_URL_PATH; ?>" size="60" value="<?php echo $SELF_URL_PATH ?>"/>
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

		$notices = array();

		if (!function_exists("curl_init")) {
			array_push($notices, "It is highly recommended to enable support for CURL in PHP.");
		}

		if (count($notices) > 0) {
			print_notice("Configuration check succeeded with minor problems:");

			print "<ul>";

			foreach ($notices as $notice) {
				print "<li>$notice</li>";
			}

			print "</ul>";
		} else {
			print_notice("Configuration check succeeded.");
		}

	?>

	<h2>Checking database</h2>

	<?php
		$link = db_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_TYPE, $DB_PORT);

		if (!$link) {
			print_error("Unable to connect to database using specified parameters.");
			exit;
		}

		print_notice("Database test succeeded."); ?>

			<h2>Initialize database</h2>

			<p>Before you can start using tt-rss, database needs to be initialized. Click on the button below to do that now.</p>

			<?php
				$result = @db_query($link, "SELECT true FROM ttrss_feeds", $DB_TYPE, false);

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
				<input type="hidden" name="SELF_URL_PATH" value="<?php echo $SELF_URL_PATH ?>"/>

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
				<input type="hidden" name="SELF_URL_PATH" value="<?php echo $SELF_URL_PATH ?>"/>

				<input type="hidden" name="op" value="skipschema">
				<p><input type="submit" value="Skip initialization"></p>
			</form>

			</td></tr></table>

			<?php

		} else if ($op == 'installschema' || $op == 'skipschema') {

			$link = db_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_TYPE, $DB_PORT);

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

			print "<p>Copy following text and save as <code>config.php</code> in tt-rss main directory. It is suggested to read through the file to the end in case you need any options changed fom default values.</p>";

			print "<p>After copying the file, you will be able to login with default username and password combination: <code>admin</code> and <code>password</code>. Don't forget to change the password immediately!</p>"; ?>

			<form action="" method="post">
				<input type="hidden" name="op" value="saveconfig">
				<input type="hidden" name="DB_USER" value="<?php echo $DB_USER ?>"/>
				<input type="hidden" name="DB_PASS" value="<?php echo $DB_PASS ?>"/>
				<input type="hidden" name="DB_NAME" value="<?php echo $DB_NAME ?>"/>
				<input type="hidden" name="DB_HOST" value="<?php echo $DB_HOST ?>"/>
				<input type="hidden" name="DB_PORT" value="<?php echo $DB_PORT ?>"/>
				<input type="hidden" name="DB_TYPE" value="<?php echo $DB_TYPE ?>"/>
				<input type="hidden" name="SELF_URL_PATH" value="<?php echo $SELF_URL_PATH ?>"/>
			<?php print "<textarea cols=\"80\" rows=\"20\">";
			echo make_config($DB_TYPE, $DB_HOST, $DB_USER, $DB_NAME, $DB_PASS,
				$DB_PORT, $SELF_URL_PATH);
			print "</textarea>"; ?>

			<?php if (is_writable("..")) { ?>
				<p>We can also try saving the file automatically now.</p>

				<p><input type="submit" value="Save configuration"></p>
				</form>
			<?php } else {
				print_error("Unfortunately, parent directory is not writable, so we're unable to save config.php automatically.");
			}

		   print_notice("You can generate the file again by changing the form above.");

		} else if ($op == "saveconfig") {

			print "<h2>Saving configuration file to parent directory...</h2>";

			if (!file_exists("../config.php")) {

				$fp = fopen("../config.php", "w");

				if ($fp) {
					$written = fwrite($fp, make_config($DB_TYPE, $DB_HOST,
						$DB_USER, $DB_NAME, $DB_PASS,
						$DB_PORT, $SELF_URL_PATH));

					if ($written > 0) {
						print_notice("Successfully saved config.php. You can try <a href=\"..\">loading tt-rss now</a>.");

					} else {
						print_notice("Unable to write into config.php in tt-rss directory.");
					}

					fclose($fp);
				} else {
					print_error("Unable to open config.php in tt-rss directory for writing.");
				}
			} else {
				print_error("config.php already present in tt-rss directory, refusing to overwrite.");
			}
		}
	?>

</div>

</body>
</html>

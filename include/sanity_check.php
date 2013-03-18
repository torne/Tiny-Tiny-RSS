<?php
	// WARNING: Don't ask for help on tt-rss.org forums or the bugtracker if you have
	// modified this file.

	function initial_sanity_check($link) {

		$errors = array();

		if (!file_exists("config.php")) {
			array_push($errors, "Configuration file not found. Looks like you forgot to copy config.php-dist to config.php and edit it.");
		} else {

			require_once "sanity_config.php";

			if (strpos(PLUGINS, "auth_") === FALSE) {
				array_push($errors, "Please enable at least one authentication module via PLUGINS constant in config.php");
			}

			if (function_exists('posix_getuid') && posix_getuid() == 0) {
				array_push($errors, "Please don't run this script as root.");
			}

			if (version_compare(PHP_VERSION, '5.3.0', '<')) {
				array_push($errors, "PHP version 5.3.0 or newer required.");
			}

			if (CONFIG_VERSION != EXPECTED_CONFIG_VERSION) {
				array_push($errors, "Configuration file (config.php) has incorrect version. Update it with new options from config.php-dist and set CONFIG_VERSION to the correct value.");
			}

			if (!is_writable(CACHE_DIR . "/images")) {
				array_push($errors, "Image cache is not writable (chmod -R 777 ".CACHE_DIR."/images)");
			}

			if (!is_writable(CACHE_DIR . "/export")) {
				array_push($errors, "Data export cache is not writable (chmod -R 777 ".CACHE_DIR."/export)");
			}

			if (!is_writable(CACHE_DIR . "/js")) {
				array_push($errors, "Javascript cache is not writable (chmod -R 777 ".CACHE_DIR."/js)");
			}

			if (GENERATED_CONFIG_CHECK != EXPECTED_CONFIG_VERSION) {
				array_push($errors,
					"Configuration option checker sanity_config.php is outdated, please recreate it using ./utils/regen_config_checks.sh");
			}

			foreach ($requred_defines as $d) {
				if (!defined($d)) {
					array_push($errors,
						"Required configuration file parameter $d is not defined in config.php. You might need to copy it from config.php-dist.");
				}
			}

			if (SESSION_EXPIRE_TIME < 60) {
				array_push($errors, "SESSION_EXPIRE_TIME set in config.php is too low, please set it to an integer value >= 60");
			}

			if (SESSION_EXPIRE_TIME < SESSION_COOKIE_LIFETIME) {
				array_push($errors, "SESSION_EXPIRE_TIME set in config.php should be >= to SESSION_COOKIE_LIFETIME");
			}

			if (SINGLE_USER_MODE) {
				$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

				if ($link) {
					$result = db_query($link, "SELECT id FROM ttrss_users WHERE id = 1");

					if (db_num_rows($result) != 1) {
						array_push($errors, "SINGLE_USER_MODE is enabled in config.php but default admin account is not found.");
					}
				}
			}

			if (SELF_URL_PATH == "http://yourserver/tt-rss/") {
				if ($_SERVER['HTTP_REFERER']) {
					array_push($errors,
						"Please set SELF_URL_PATH to the correct value for your server (possible value: <b>" . $_SERVER['HTTP_REFERER'] . "</b>)");
				} else {
					array_push($errors, "Please set SELF_URL_PATH to the correct value for your server.");
				}
			}

			if (!is_writable(ICONS_DIR)) {
				array_push($errors, "ICONS_DIR defined in config.php is not writable (chmod -R 777 ".ICONS_DIR.").\n");
			}

			if (!is_writable(LOCK_DIRECTORY)) {
				array_push($errors, "LOCK_DIRECTORY defined in config.php is not writable (chmod -R 777 ".LOCK_DIRECTORY.").\n");
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

			if (DB_TYPE == "mysql" && !function_exists("mysql_connect")) {
				array_push($errors, "PHP support for MySQL is required for configured DB_TYPE in config.php.");
			}

			if (DB_TYPE == "pgsql" && !function_exists("pg_connect")) {
				array_push($errors, "PHP support for PostgreSQL is required for configured DB_TYPE in config.php");
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
		}

		if (count($errors) > 0 && $_SERVER['REQUEST_URI']) { ?>
			<html>
			<head>
			<title>Startup failed</title>
				<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
				<link rel="stylesheet" type="text/css" href="utility.css">
			</head>
		<body>
		<div class="floatingLogo"><img src="images/logo_wide.png"></div>

			<h1>Startup failed</h1>

			<p>Tiny Tiny RSS was unable to start properly. This usually means a misconfiguration or an incomplete upgrade. Please fix
			errors indicated by the following messages:</p>

			<?php foreach ($errors as $error) { echo format_error($error); } ?>

			<p>You might want to check tt-rss <a href="http://tt-rss.org/wiki">wiki</a> or the
				<a href="http://tt-rss.org/forum">forums</a> for more information. Please search the forums before creating new topic
				for your question.</p>
		</body>
		</html>

		<?php
			die;
		} else if (count($errors) > 0) {
			echo "Tiny Tiny RSS was unable to start properly. This usually means a misconfiguration or an incomplete upgrade.\n";
			echo "Please fix errors indicated by the following messages:\n\n";

			foreach ($errors as $error) {
				echo " * $error\n";
			}

			echo "\nYou might want to check tt-rss wiki or the forums for more information.\n";
			echo "Please search the forums before creating new topic for your question.\n";

			exit(-1);
		}
	}

	initial_sanity_check($link);

?>

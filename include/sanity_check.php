<?php
	require_once "functions.php";

	if (!file_exists("config.php")) {
		$err_msg = "Configuration file not found. Looks like you forgot to copy config.php-dist to config.php and edit it.";
	} else {

		define('EXPECTED_CONFIG_VERSION', 25);
		define('SCHEMA_VERSION', 89);

		require_once "config.php";
		require_once "sanity_config.php";

		if (function_exists('posix_getuid') && posix_getuid() == 0) {
			$err_msg = "Please don't run this script as root.";
		}

		if (CONFIG_VERSION != EXPECTED_CONFIG_VERSION) {
			$err_msg = "Configuration file (config.php) has incorrect version. Update it with new options from config.php-dist and set CONFIG_VERSION to the correct value.";
		}

		$purifier_cache_dir = CACHE_DIR . "/htmlpurifier";

		if (!is_writable($purifier_cache_dir)) {
			$err_msg = "HTMLPurifier cache directory should be writable by anyone (chmod -R 777 $purifier_cache_dir)";
		}

		if (!is_writable(CACHE_DIR . "/images")) {
			$err_msg = "Image cache is not writable (chmod -R 777 ".CACHE_DIR."/images)";
		}

		if (!is_writable(CACHE_DIR . "/export")) {
			$err_msg = "Data export cache is not writable (chmod -R 777 ".CACHE_DIR."/export)";
		}

		if (GENERATED_CONFIG_CHECK != EXPECTED_CONFIG_VERSION) {
			$err_msg = "Configuration option checker sanity_config.php is outdated, please recreate it using ./utils/regen_config_checks.sh";
		}

		foreach ($requred_defines as $d) {
			if (!defined($d)) {
				$err_msg = "Required configuration file parameter $d is not defined in config.php. You might need to copy it from config.php-dist.";
			}
		}

		if (SESSION_EXPIRE_TIME < 60) {
			$err_msg = "SESSION_EXPIRE_TIME set in config.php is too low, please set it to an integer value >= 60";
		}

		if (SESSION_EXPIRE_TIME < SESSION_COOKIE_LIFETIME) {
			$err_msg = "SESSION_EXPIRE_TIME set in config.php should be >= to SESSION_COOKIE_LIFETIME";
		}

		if (SINGLE_USER_MODE) {
			$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

			if ($link) {
				$result = db_query($link, "SELECT id FROM ttrss_users WHERE id = 1");

				if (db_num_rows($result) != 1) {
					$err_msg = "SINGLE_USER_MODE is enabled in config.php but default admin account is not found.";
				}
			}
		}

		if (SELF_URL_PATH == "http://yourserver/tt-rss/") {
			if ($_SERVER['HTTP_REFERER']) {
				$err_msg = "Please set SELF_URL_PATH to the correct value for your server (possible value: <b>" . $_SERVER['HTTP_REFERER'] . "</b>)";
			} else {
				$err_msg = "Please set SELF_URL_PATH to the correct value for your server.";
			}
		}

		if (!is_writable(ICONS_DIR)) {
			$err_msg = "ICONS_DIR defined in config.php is not writable (chmod -R 777 ".ICONS_DIR.").\n";
		}

		if (!is_writable(LOCK_DIRECTORY)) {
			$err_msg = "LOCK_DIRECTORY defined in config.php is not writable (chmod -R 777 ".LOCK_DIRECTORY.").\n";
		}

		if (ini_get("open_basedir")) {
			$err_msg = "PHP configuration option open_basedir is not supported. Please disable this in PHP settings file (php.ini).";
		}

		if (!function_exists("curl_init") && !ini_get("allow_url_fopen")) {
			$err_msg = "PHP configuration option allow_url_fopen is disabled, and CURL functions are not present. Either enable allow_url_fopen or install PHP extension for CURL.";
		}

		if (!function_exists("json_encode")) {
			$err_msg = "PHP support for JSON is required, but was not found.";
		}

		if (DB_TYPE == "mysql" && !function_exists("mysql_connect")) {
			$err_msg = "PHP support for MySQL is required for configured DB_TYPE in config.php.";
		}

		if (DB_TYPE == "pgsql" && !function_exists("pg_connect")) {
			$err_msg = "PHP support for PostgreSQL is required for configured DB_TYPE in config.php";
		}

		if (!function_exists("mb_strlen")) {
			$err_msg = "PHP support for mbstring functions is required, but was not found.";
		}

		if (!function_exists("ctype_lower")) {
			$err_msg = "PHP support for ctype functions are required by HTMLPurifier.";
		}

		if (ini_get("safe_mode")) {
			$err_msg = "PHP safe mode setting is not supported.";
		}

		if ((PUBSUBHUBBUB_HUB || PUBSUBHUBBUB_ENABLED) && !function_exists("curl_init")) {
			$err_msg = "PHP support for CURL is required for PubSubHubbub.";
		}

		if (!class_exists("DOMDocument")) {
			$err_msg = "PHP support for DOMDocument is required, but was not found.";
		}
	}

	if ($err_msg && defined($_SERVER['REQUEST_URI'])) { ?>
		<html>
		<head>
		<title>Fatal error</title>
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
			<link rel="stylesheet" type="text/css" href="utility.css">
		</head>

	<div class="floatingLogo"><img src="images/logo_wide.png"></div>

		<h1>Fatal error</h1>

		<p>Tiny Tiny RSS was unable to initialize properly. This usually means a misconfiguration or an incomplete upgrade. Please fix
		the error indicated by the following message:</p>

		<p>You might want to check tt-rss <a href="http://tt-rss.org/wiki">wiki</a> or the
			<a href="http://tt-rss.org/forum">forums</a> for more information. Please search the forums before creating new topic
			for your question.</p>

		<body>
			<?php echo format_error($err_msg) ?>
		</body>
		</html>

	<?php
		die;
	} else if ($err_msg) {
		die("[sanity_check] $err_msg\n");
	}

?>

<?php
	require_once "functions.php";

	define('EXPECTED_CONFIG_VERSION', 19);
	define('SCHEMA_VERSION', 67);

	if (!file_exists("config.php")) {
		print "<b>Fatal Error</b>: You forgot to copy 
		<b>config.php-dist</b> to <b>config.php</b> and edit it.\n";
		exit;
	}

	require_once "config.php";

	if (CONFIG_VERSION != EXPECTED_CONFIG_VERSION) {
		$err_msg = "config: your config file version is incorrect. See config.php-dist.\n";
	}

	if (defined('RSS_BACKEND_TYPE')) {
		print "<b>Fatal error</b>: RSS_BACKEND_TYPE is deprecated. Please remove this
			option from config.php\n";
		exit;
	}

	if (file_exists("xml-export.php") || file_exists("xml-import.php")) {
		print "<b>Fatal Error</b>: XML Import/Export tools (<b>xml-export.php</b>
		and <b>xml-import.php</b>) could be used maliciously. Please remove them 
		from your TT-RSS instance.\n";
		exit;
	}

	if (SINGLE_USER_MODE && DAEMON_UPDATE_LOGIN_LIMIT > 0) {
		print "<b>Fatal Error</b>: Please set DAEMON_UPDATE_LOGIN_LIMIT
			to 0 in single user mode.\n";
		exit;
	}

	if (USE_CURL_FOR_ICONS && ! function_exists("curl_init")) {
		print "<b>Fatal Error</b>: You have enabled USE_CURL_FOR_ICONS, but your PHP 
			doesn't seem to support CURL functions.";
		exit;
	} 

	if (!defined('SESSION_EXPIRE_TIME')) {
		$err_msg = "config: SESSION_EXPIRE_TIME is undefined";
	}

	if (SESSION_EXPIRE_TIME < 60) {
		$err_msg = "config: SESSION_EXPIRE_TIME is too low (less than 60)";
	}

	if (SESSION_EXPIRE_TIME < SESSION_COOKIE_LIFETIME) {
		$err_msg = "config: SESSION_EXPIRE_TIME should be greater or equal to" .
			"SESSION_COOKIE_LIFETIME";
	}

/*	if (defined('DISABLE_SESSIONS')) {
		$err_msg = "config: you have enabled DISABLE_SESSIONS. Please disable this option.";
} */

	if (DATABASE_BACKED_SESSIONS && SINGLE_USER_MODE) {
		$err_msg = "config: DATABASE_BACKED_SESSIONS is incompatible with SINGLE_USER_MODE";
	}

	if (DATABASE_BACKED_SESSIONS && DB_TYPE == "mysql") {
		$err_msg = "config: DATABASE_BACKED_SESSIONS are currently broken with MySQL";
	}

	if (SINGLE_USER_MODE) {
		$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

		if ($link) {
			$result = db_query($link, "SELECT id FROM ttrss_users WHERE id = 1");

			if (db_num_rows($result) != 1) {	
				$err_msg = "config: SINGLE_USER_MODE is enabled but default admin account (UID=1) is not found.";
			}
		}
	}

	if (defined('MAIL_FROM')) {
		$err_msg = "config: MAIL_FROM has been split into DIGEST_FROM_NAME and DIGEST_FROM_ADDRESS";
	}

	if (!defined('COUNTERS_MAX_AGE')) {
		$err_msg = "config: option COUNTERS_MAX_AGE expected, but not defined";
	}

	if (defined('DAEMON_REFRESH_ONLY')) {
		$err_msg = "config: option DAEMON_REFRESH_ONLY is obsolete. Please remove this option and read about other ways to update feeds on the <a href='http://tt-rss.spb.ru/trac/wiki/UpdatingFeeds'>wiki</a>.";

	}

	if (defined('ENABLE_SIMPLEPIE')) {
		$err_msg = "config: ENABLE_SIMPLEPIE is obsolete and replaced with DEFAULT_UPDATE_METHOD. Please adjust your config.php.";
	}

	if (!defined('DEFAULT_UPDATE_METHOD') || (DEFAULT_UPDATE_METHOD != 0 &&
			DEFAULT_UPDATE_METHOD != 1)) {
		$err_msg = "config: DEFAULT_UPDATE_METHOD should be either 0 or 1.";		
	}

	if (!is_writable(ICONS_DIR)) {
		$err_msg = "config: your ICONS_DIR (" . ICONS_DIR . ") is not writable.\n";
	}

	if ($err_msg) {
		print "<b>Fatal Error</b>: $err_msg\n";
		exit;
	}

?>

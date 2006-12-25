<?php
	define('EXPECTED_CONFIG_VERSION', 5);

	if (!file_exists("config.php")) {
		print _("<b>Fatal Error</b>: You forgot to copy 
		<b>config.php-dist</b> to <b>config.php</b> and edit it.\n");
		exit;
	}

	require_once "config.php";

	if (CONFIG_VERSION != EXPECTED_CONFIG_VERSION) {
		return _("config: your config file version is incorrect. See config.php-dist.\n");
	}

	if (defined('RSS_BACKEND_TYPE')) {
		print _("<b>Fatal error</b>: RSS_BACKEND_TYPE is deprecated. Please remove this
			option from config.php\n");
		exit;
	}

	if (file_exists("xml-export.php") || file_exists("xml-import.php")) {
		print _("<b>Fatal Error</b>: XML Import/Export tools (<b>xml-export.php</b>
		and <b>xml-import.php</b>) could be used maliciously. Please remove them 
		from your TT-RSS instance.\n");
		exit;
	}

	if (SINGLE_USER_MODE && DAEMON_UPDATE_LOGIN_LIMIT > 0) {
		print _("<b>Fatal Error</b>: Please set DAEMON_UPDATE_LOGIN_LIMIT
			to 0 in single user mode.\n");
		exit;
	}

	if (USE_CURL_FOR_ICONS && ! function_exists("curl_init")) {
		print _("<b>Fatal Error</b>: You have enabled USE_CURL_FOR_ICONS, but your PHP 
			doesn't seem to support CURL functions.");
		exit;
	} 

	if (!defined('SESSION_EXPIRE_TIME')) {
		$err_msg = _("config: SESSION_EXPIRE_TIME is undefined");
	}

	if (SESSION_EXPIRE_TIME < 60) {
		$err_msg = _("config: SESSION_EXPIRE_TIME is too low (less than 60)");
	}

	if (SESSION_EXPIRE_TIME < SESSION_COOKIE_LIFETIME_REMEMBER) {
		$err_msg = _("config: SESSION_EXPIRE_TIME should be greater or equal to" .
			"SESSION_COOKIE_LIFETIME_REMEMBER");
	}

/*	if (defined('DISABLE_SESSIONS')) {
		$err_msg = "config: you have enabled DISABLE_SESSIONS. Please disable this option.";
} */

	if (DATABASE_BACKED_SESSIONS && SINGLE_USER_MODE) {
		$err_msg = _("config: DATABASE_BACKED_SESSIONS is incompatible with SINGLE_USER_MODE");
	}

	if (DATABASE_BACKED_SESSIONS && DB_TYPE == "mysql") {
		$err_msg = _("config: DATABASE_BACKED_SESSIONS are currently broken with MySQL");
	}

	if ($err_msg) {
		print "<b>Fatal Error</b>: $err_msg\n";
		exit;
	}

?>

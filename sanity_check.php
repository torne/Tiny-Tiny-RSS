<?php
	define('EXPECTED_CONFIG_VERSION', 5);

	if (!file_exists("config.php")) {
		print "<b>Fatal Error</b>: You forgot to copy 
		<b>config.php-dist</b> to <b>config.php</b> and edit it.\n";
		exit;
	}

	require_once "config.php";

	if (CONFIG_VERSION != EXPECTED_CONFIG_VERSION) {
			print "<b>Fatal Error</b>: Your configuration file has
			wrong version. Please copy new options from <b>config.php-dist</b> and
			update CONFIG_VERSION directive.\n";
		exit;	
	}

	if (!defined('RSS_BACKEND_TYPE')) {
		print "<b>Fatal error</b>: RSS backend type is not defined
			(config variable <b>RSS_BACKEND_TYPE</b>) - please check your
			configuration file.\n";
		exit;
	}

	if (RSS_BACKEND_TYPE == "magpie" && !file_exists("magpierss/rss_fetch.inc")) {
		print "<b>Fatal Error</b>: You forgot to place 
		<a href=\"http://magpierss.sourceforge.net\">MagpieRSS</a>
		distribution in <b>magpierss/</b>
		subdirectory of TT-RSS tree.\n";
		exit;
	}

	if (RSS_BACKEND_TYPE == "simplepie" && !file_exists("simplepie/simplepie.inc")) {
		print "<b>Fatal Error</b>: You forgot to place 
		<a href=\"http://simplepie.org\">SimplePie</a>
		distribution in <b>simplepie/</b>
		subdirectory of TT-RSS tree.\n";
		exit;
	}

	if (RSS_BACKEND_TYPE != "simplepie" && RSS_BACKEND_TYPE != "magpie") {
		print "<b>Fatal Error</b>: Invalid RSS_BACKEND_TYPE\n";
		exit;
	}

	if (CONFIG_VERSION != EXPECTED_CONFIG_VERSION) {
		return "config: your config file version is incorrect. See config.php-dist.\n";
	}

	if (file_exists("xml-export.php") || file_exists("xml-import.php")) {
		print "<b>Fatal Error</b>: XML Import/Export tools (<b>xml-export.php</b>
		and <b>xml-import.php</b>) could be used maliciously. Please remove them 
		from your TT-RSS instance.\n";
		exit;
	}

	if (RSS_BACKEND_TYPE != "magpie") {
		print "<b>Fatal Error</b>: RSS backends other than magpie are not
		supported now.\n";
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
?>

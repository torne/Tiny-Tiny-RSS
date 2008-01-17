#!/usr/bin/php
<?php
	// this script is probably run not from your httpd-user, so cache
	// directory defined in config.php won't be accessible
	define('MAGPIE_CACHE_DIR', '/var/tmp/magpie-ttrss-cache-cli');
	define('SIMPLEPIE_CACHE_DIR',	'/var/tmp/simplepie-ttrss-cache-cli');
	define('DEFAULT_ERROR_LEVEL', E_ERROR | E_WARNING | E_PARSE);
	define('DISABLE_SESSIONS', true);

	error_reporting(DEFAULT_ERROR_LEVEL);

	require_once "sanity_check.php";
	require_once "config.php";
	require_once "db.php";
	require_once "db-prefs.php";
	require_once "functions.php";

	$lock_filename = "update_feeds.lock";

	$lock_handle = make_lockfile($lock_filename);

	if (!$lock_handle) {
		die("error: Can't create lockfile ($lock_filename). ".
			"Maybe another process is already running.\n");
	}

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);	

	if (!$link) {
		if (DB_TYPE == "mysql") {
			print mysql_error();
		}
		// PG seems to display its own errors just fine by default.		
		return;
	}

	if (DB_TYPE == "pgsql") {
		pg_query("set client_encoding = 'utf-8'");
		pg_set_client_encoding("UNICODE");
	} else {
		if (defined('MYSQL_CHARSET') && MYSQL_CHARSET) {
			db_query($link, "SET NAMES " . MYSQL_CHARSET);
//			db_query($link, "SET CHARACTER SET " . MYSQL_CHARSET);
		}
	}

	$result = db_query($link, "SELECT id FROM ttrss_users");

	while ($line = db_fetch_assoc($result)) {
			$user_id = $line["id"];
			initialize_user_prefs($link, $user_id);
			update_all_feeds($link, false, $user_id, true);
	}

	if (DAEMON_SENDS_DIGESTS) send_headlines_digests($link);

	db_close($link);

	unlink(LOCK_DIRECTORY . "/$lock_filename");
?>

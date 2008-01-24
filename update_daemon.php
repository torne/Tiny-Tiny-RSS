#!/usr/bin/php
<?php
	// this daemon runs in the background and updates all feeds
	// continuously

	// define('DEFAULT_ERROR_LEVEL', E_ALL);
	define('DEFAULT_ERROR_LEVEL', E_ERROR | E_WARNING | E_PARSE);

	declare(ticks = 1);

	define('MAGPIE_CACHE_DIR', '/var/tmp/magpie-ttrss-cache-daemon');
	define('SIMPLEPIE_CACHE_DIR',	'/var/tmp/simplepie-ttrss-cache-daemon');
	define('DISABLE_SESSIONS', true);
	define('PHP_EXECUTABLE', '/usr/bin/php');

	require_once "version.php";

	if (strpos(VERSION, ".99") !== false || getenv('DAEMON_XDEBUG')) {
		define('DAEMON_EXTENDED_DEBUG', true);
	}

	define('PURGE_INTERVAL', 3600); // seconds

	require_once "sanity_check.php";
	require_once "config.php";

	if (!ENABLE_UPDATE_DAEMON) {
		die("Please enable option ENABLE_UPDATE_DAEMON in config.php\n");
	}
	
	require_once "db.php";
	require_once "db-prefs.php";
	require_once "functions.php";
	require_once "magpierss/rss_fetch.inc";

	error_reporting(DEFAULT_ERROR_LEVEL);

	function sigint_handler() {
		unlink(LOCK_DIRECTORY . "/update_daemon.lock");
		die("Received SIGINT. Exiting.\n");
	}

	function sigalrm_handler() {
		die("received SIGALRM, hang in feed update?\n");
	}

	pcntl_signal(SIGINT, sigint_handler);
	pcntl_signal(SIGALRM, sigalrm_handler);

	$lock_handle = make_lockfile("update_daemon.lock");

	if (!$lock_handle) {
		die("error: Can't create lockfile ($lock_filename). ".
			"Maybe another daemon is already running.\n");
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

	db_close($link);

	$last_purge = 0;

	while (true) {

		passthru(PHP_EXECUTABLE . " update_daemon_loop.php SRV_RUN_OK");

		_debug("Sleeping for " . DAEMON_SLEEP_INTERVAL . " seconds...");
		
		sleep(DAEMON_SLEEP_INTERVAL);
	}


?>

#!/usr/bin/php
<?php
	define('DISABLE_SESSIONS', true);

	chdir(dirname($_SERVER['SCRIPT_NAME']));
	require_once "functions.php";
	require_once "sanity_check.php";
	require_once "config.php";
	require_once "db.php";
	require_once "db-prefs.php";

	if (!defined('PHP_EXECUTABLE'))
		define('PHP_EXECUTABLE', '/usr/bin/php');

	$op = $argv[1];

	if (!$op || $op == "-help") {
		print "Tiny Tiny RSS data update script.\n\n";
		print "Options:\n";
		print "  -feeds         - update feeds\n";
		print "  -feedbrowser   - update feedbrowser\n";
		print "  -daemon        - start single-process update daemon\n";
		print "  -cleanup-tags  - perform tags table maintenance\n";
		print "  -help          - show this help\n";
		return;
	}

	if ($op != "-daemon") {
		$lock_filename = "update.lock";
	} else {
		$lock_filename = "update_daemon.lock";
	}

	$lock_handle = make_lockfile($lock_filename);
	$must_exit = false;

	// Try to lock a file in order to avoid concurrent update.
	if (!$lock_handle) {
		die("error: Can't create lockfile ($lock_filename). ".
			"Maybe another update process is already running.\n");
	}

	// Create a database connection.
	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

	if (!$link) {
		if (DB_TYPE == "mysql") {
			print mysql_error();
		}
		// PG seems to display its own errors just fine by default.
		return;
	}

	init_connection($link);

	if ($op == "-feeds") {
		// Update all feeds needing a update.
		update_daemon_common($link);
	}

	if ($op == "-feedbrowser") {
		$count = update_feedbrowser_cache($link);
		print "Finished, $count feeds processed.\n";
	}

	if ($op == "-daemon") {
		while (true) {
			passthru(PHP_EXECUTABLE . " " . $argv[0] . " -daemon-loop");
			_debug("Sleeping for " . DAEMON_SLEEP_INTERVAL . " seconds...");
			sleep(DAEMON_SLEEP_INTERVAL);
		}
	}

	if ($op == "-daemon-loop") {
		if (!make_stampfile('update_daemon.stamp')) {
			die("error: unable to create stampfile\n");
		}

		// Call to the feed batch update function
		// or regenerate feedbrowser cache

		if (rand(0,100) > 30) {
			update_daemon_common($link);
		} else {
			$count = update_feedbrowser_cache($link);
			_debug("Feedbrowser updated, $count feeds processed.");

			purge_orphans($link, true);

			$rc = cleanup_tags($link, 14, 50000);

			_debug("Cleaned $rc cached tags.");
		}

	}

	if ($op == "-cleanup-tags") {
		$rc = cleanup_tags($link, 14, 50000);
		print "$rc tags deleted.\n";
	}

	db_close($link);

	unlink(LOCK_DIRECTORY . "/$lock_filename");
?>

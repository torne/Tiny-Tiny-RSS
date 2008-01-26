#!/usr/bin/php
<?php
	// this daemon runs in the background and updates all feeds
	// continuously

	if ($argv[1] != "SRV_RUN_OK") {
		die("This script should be run by update_daemon.php\n");
	}

	// define('DEFAULT_ERROR_LEVEL', E_ALL);
	define('DEFAULT_ERROR_LEVEL', E_ERROR | E_WARNING | E_PARSE);

	declare(ticks = 1);

	define('MAGPIE_CACHE_DIR', '/var/tmp/magpie-ttrss-cache-daemon');
	define('SIMPLEPIE_CACHE_DIR',	'/var/tmp/simplepie-ttrss-cache-daemon');
	define('DISABLE_SESSIONS', true);

	require_once "version.php";

	if (strpos(VERSION, ".99") !== false) {
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

	function sigalrm_handler() {
		die("received SIGALRM, hang in feed update?\n");
	}

	pcntl_signal(SIGALRM, sigalrm_handler);

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

	$last_purge = 0;

	if (!make_stampfile('update_daemon.stamp')) {
		print "error: unable to create stampfile";
		die;
	}

	if (time() - $last_purge > PURGE_INTERVAL) {
		_debug("Purging old posts (random 30 feeds)...");
		global_purge_old_posts($link, true, 30);
		$last_purge = time();
	}

	// FIXME: get all scheduled updates w/forced refetch
	// Stub, until I figure out if it is really needed.

#		$result = db_query($link, "SELECT * FROM ttrss_scheduled_updates ORDER BY id");
#		while ($line = db_fetch_assoc($result)) {
#			print "Scheduled feed update: " . $line["feed_id"] . ", UID: " . 
#				$line["owner_uid"] . "\n";
#		}

	// Process all other feeds using last_updated and interval parameters

//	$random_qpart = sql_random_function();

/*		
				ttrss_entries.date_entered < NOW() - INTERVAL '$purge_interval days'");
		}

		$rows = pg_affected_rows($result);
		
	} else {

		$result = db_query($link, "DELETE FROM ttrss_user_entries 
			USING ttrss_user_entries, ttrss_entries 
			WHERE ttrss_entries.id = ref_id AND 
			marked = false AND 
			feed_id = '$feed_id' AND 
			ttrss_entries.date_entered < DATE_SUB(NOW(), INTERVAL $purge_interval DAY)"); */		

	// Call to the feed batch update function
	update_daemon_common($link);

	db_close($link);

?>

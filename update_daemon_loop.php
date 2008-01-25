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

	$random_qpart = sql_random_function();

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
	
	if (DAEMON_UPDATE_LOGIN_LIMIT > 0) {
		if (DB_TYPE == "pgsql") {
			$login_thresh_qpart = "AND ttrss_users.last_login >= NOW() - INTERVAL '".DAEMON_UPDATE_LOGIN_LIMIT." days'";
		} else {
			$login_thresh_qpart = "AND ttrss_users.last_login >= DATE_SUB(NOW(), INTERVAL ".DAEMON_UPDATE_LOGIN_LIMIT." DAY)";
		}			
	} else {
		$login_thresh_qpart = "";
	}

	if (DB_TYPE == "pgsql") {
		$update_limit_qpart = "AND ((
				ttrss_feeds.update_interval = 0
				AND ttrss_feeds.last_updated < NOW() - CAST((ttrss_user_prefs.value || ' minutes') AS INTERVAL)
			) OR (
				ttrss_feeds.update_interval > 0
				AND ttrss_feeds.last_updated < NOW() - CAST((ttrss_feeds.update_interval || ' minutes') AS INTERVAL)
			))";
	} else {
		$update_limit_qpart = "AND ((
				ttrss_feeds.update_interval = 0
				AND ttrss_feeds.last_updated < DATE_SUB(NOW(), INTERVAL CONVERT(ttrss_user_prefs.value, SIGNED INTEGER) MINUTE)
			) OR (
				ttrss_feeds.update_interval > 0
				AND ttrss_feeds.last_updated < DATE_SUB(NOW(), INTERVAL ttrss_feeds.update_interval MINUTE)
			))";
	}

	if (DB_TYPE == "pgsql") {
		$updstart_thresh_qpart = "AND (ttrss_feeds.last_update_started IS NULL OR ttrss_feeds.last_update_started < NOW() - INTERVAL '120 seconds')";
	} else {
		$updstart_thresh_qpart = "AND (ttrss_feeds.last_update_started IS NULL OR ttrss_feeds.last_update_started < DATE_SUB(NOW(), INTERVAL 120 SECOND))";
	}			

	$result = db_query($link, "SELECT ttrss_feeds.feed_url,ttrss_feeds.id, ttrss_feeds.owner_uid,
			SUBSTRING(ttrss_feeds.last_updated,1,19) AS last_updated,
			ttrss_feeds.update_interval 
		FROM 
			ttrss_feeds, ttrss_users, ttrss_user_prefs
		WHERE
			ttrss_feeds.owner_uid = ttrss_users.id
			AND ttrss_users.id = ttrss_user_prefs.owner_uid
			AND ttrss_user_prefs.pref_name='DEFAULT_UPDATE_INTERVAL'
			$login_thresh_qpart $update_limit_qpart
			 $updstart_thresh_qpart
		ORDER BY $random_qpart DESC LIMIT " . DAEMON_FEED_LIMIT);

	$user_prefs_cache = array();

	_debug(sprintf("Scheduled %d feeds to update...\n", db_num_rows($result)));

	// Here is a little cache magic in order to minimize risk of double feed updates.
	$feeds_to_update = array();
	while ($line = db_fetch_assoc($result)) {
		$feeds_to_update[$line['id']] = $line;
	}

	// We update the feed last update started date before anything else.
	// There is no lag due to feed contents downloads
	// It prevent an other process to update the same feed (for exemple, forced update by user).
	$feed_ids = array_keys($feeds_to_update);
	if($feed_ids) {
		db_query($link, sprintf("UPDATE ttrss_feeds SET last_update_started = NOW()
		WHERE id IN (%s)", implode(',', $feed_ids)));
	}

	while ($line = array_pop($feeds_to_update)) {

		_debug("Feed: " . $line["feed_url"] . ", " . $line["last_updated"]);

		pcntl_alarm(300);
		update_rss_feed($link, $line["feed_url"], $line["id"], true);	
		pcntl_alarm(0);

		sleep(1); // prevent flood (FIXME make this an option?)
	}

	if (DAEMON_SENDS_DIGESTS) send_headlines_digests($link);

	db_close($link);

?>

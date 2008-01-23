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

	$last_purge = 0;

	while (true) {

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
			$update_limit_qpart = "AND ttrss_feeds.last_updated < NOW() - INTERVAL '".(DAEMON_SLEEP_INTERVAL*2)." seconds'";
		} else {
			$update_limit_qpart = "AND ttrss_feeds.last_updated < DATE_SUB(NOW(), INTERVAL ".(DAEMON_SLEEP_INTERVAL*2)." SECOND)";
		}

		if (DB_TYPE == "pgsql") {
				$updstart_thresh_qpart = "AND (ttrss_feeds.last_update_started IS NULL OR ttrss_feeds.last_update_started >= NOW() - INTERVAL '120 seconds')";
			} else {
				$updstart_thresh_qpart = "AND (ttrss_feeds.last_update_started IS NULL OR ttrss_feeds.last_update_started >= DATE_SUB(NOW(), INTERVAL 120 SECOND))";
			}			

		$result = db_query($link, "SELECT feed_url,ttrss_feeds.id,owner_uid,
				SUBSTRING(last_updated,1,19) AS last_updated,
				update_interval 
			FROM 
				ttrss_feeds,ttrss_users 
			WHERE 
				ttrss_users.id = owner_uid $login_thresh_qpart $update_limit_qpart 
				$updstart_thresh_qpart
			ORDER BY $random_qpart DESC LIMIT " . DAEMON_FEED_LIMIT);

		$user_prefs_cache = array();

		_debug(sprintf("Scheduled %d feeds to update...\n", db_num_rows($result)));
		
		while ($line = db_fetch_assoc($result)) {
	
			$upd_intl = $line["update_interval"];
			$user_id = $line["owner_uid"];
	
			if (!$upd_intl || $upd_intl == 0) {
				if (!$user_prefs_cache[$user_id]['DEFAULT_UPDATE_INTERVAL']) {			
					$upd_intl = get_pref($link, 'DEFAULT_UPDATE_INTERVAL', $user_id);
					$user_prefs_cache[$user_id]['DEFAULT_UPDATE_INTERVAL'] = $upd_intl;
				} else {
					$upd_intl = $user_prefs_cache[$user_id]['DEFAULT_UPDATE_INTERVAL'];
				}
			}

			if ($upd_intl < 0) { 
#				print "Updates disabled.\n";
				continue; 
			}
	
			_debug("Feed: " . $line["feed_url"] . ", " . $line["last_updated"]);

//			_debug(sprintf("\tLU: %d, INTL: %d, UID: %d) ", 
//				time() - strtotime($line["last_updated"]), $upd_intl*60, $user_id));
	
			if (!$line["last_updated"] || 
				time() - strtotime($line["last_updated"]) > ($upd_intl * 60)) {

				_debug("Updating...");

				pcntl_alarm(300);

				update_rss_feed($link, $line["feed_url"], $line["id"], true);	

				pcntl_alarm(0);

				sleep(1); // prevent flood (FIXME make this an option?)
			} else {
				_debug("Update not needed.");
			}
		}

		if (DAEMON_SENDS_DIGESTS) send_headlines_digests($link);

		_debug("Sleeping for " . DAEMON_SLEEP_INTERVAL . " seconds...");
		
		sleep(DAEMON_SLEEP_INTERVAL);
	}

	db_close($link);

?>

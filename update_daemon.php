#!/usr/bin/php4
<?
	// this daemon runs in the background and updates all feeds
	// continuously

	declare(ticks = 1);

	define('MAGPIE_CACHE_DIR', '/var/tmp/magpie-ttrss-cache-daemon');
	define('DISABLE_SESSIONS', true);

	define('PURGE_INTERVAL', 3600); // seconds

	require_once "sanity_check.php";
	require_once "config.php";

	if (!ENABLE_UPDATE_DAEMON) {
		die("Please enable option ENABLE_UPDATE_DAEMON in config.php");
	}
	
	require_once "db.php";
	require_once "db-prefs.php";
	require_once "functions.php";
	require_once "magpierss/rss_fetch.inc";

	function sigint_handler() {
		unlink("update_daemon.lock");
		die("Received SIGINT. Exiting.\n");
	}

	pcntl_signal(SIGINT, sigint_handler);

	$lock_handle = make_lockfile("update_daemon.lock");

	if (!$lock_handle) {
		die("error: Can't create lockfile ($lock_filename). ".
			"Maybe another daemon is already running.");
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
	}

	$last_purge = 0;

	while (true) {

		if (time() - $last_purge > PURGE_INTERVAL) {
			print "Purging old posts...\n";
			global_purge_old_posts($link, true);
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

		$result = db_query($link, "SELECT feed_url,id,owner_uid,
			SUBSTRING(last_updated,1,19) AS last_updated,
			update_interval FROM ttrss_feeds ORDER BY last_updated DESC");
	
		while ($line = db_fetch_assoc($result)) {
	
			$upd_intl = $line["update_interval"];
			$user_id = $line["owner_uid"];
	
			if (!$upd_intl || $upd_intl == 0) {
				$upd_intl = get_pref($link, 'DEFAULT_UPDATE_INTERVAL', $user_id);
			}

			if ($upd_intl < 0) { 
#				print "Updates disabled.\n";
				continue; 
			}
	
			print "Feed: " . $line["feed_url"] . ": ";

			printf("(%d/%d, %d) ", time() - strtotime($line["last_updated"]),
				$upd_intl*60, $owner_uid);
	
			if (!$line["last_updated"] || 
				time() - strtotime($line["last_updated"]) > ($upd_intl * 60)) {
	
				print "Updating...\n";	
				update_rss_feed($link, $line["feed_url"], $line["id"], true);	
				sleep(3); // prevent flood (FIXME make this an option?)
			} else {
				print "Update not needed.\n";
			}
		}

		print "Sleeping for " . DAEMON_SLEEP_INTERVAL . " seconds...\n";
		
		sleep(DAEMON_SLEEP_INTERVAL);
	}

	db_close($link);

?>

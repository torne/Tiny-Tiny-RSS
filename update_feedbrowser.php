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

	$lock_filename = "update_feedbrowser.lock";

	$lock_handle = make_lockfile($lock_filename);

		// Try to lock a file in order to avoid concurrent update.
	if (!$lock_handle) {
		die("error: Can't create lockfile ($lock_filename). ".
			"Maybe another process is already running.\n");
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

	$result = db_query($link, "SELECT feed_url,COUNT(id) AS subscribers
  		FROM ttrss_feeds WHERE (SELECT COUNT(id) = 0 FROM ttrss_feeds AS tf 
			WHERE tf.feed_url = ttrss_feeds.feed_url 
			AND (private IS true OR feed_url LIKE '%:%@%/%')) 
			GROUP BY feed_url ORDER BY subscribers DESC");

	db_query($link, "BEGIN");

	db_query($link, "DELETE FROM ttrss_feedbrowser_cache");

	$count = 0;

	while ($line = db_fetch_assoc($result)) {
		$subscribers = db_escape_string($line["subscribers"]);
		$feed_url = db_escape_string($line["feed_url"]);

		db_query($link, "INSERT INTO ttrss_feedbrowser_cache 
			(feed_url, subscribers) VALUES ('$feed_url', '$subscribers')");

		++$count;
	}

	db_query($link, "COMMIT");

	print "Finished, $count feeds processed.\n";

	db_close($link);

	unlink(LOCK_DIRECTORY . "/$lock_filename");

?>

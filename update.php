#!/usr/bin/env php
<?php
	set_include_path(get_include_path() . PATH_SEPARATOR .
		dirname(__FILE__) . "/include");

	define('DISABLE_SESSIONS', true);

	chdir(dirname(__FILE__));

	require_once "functions.php";
	require_once "rssfuncs.php";
	require_once "sanity_check.php";
	require_once "config.php";
	require_once "db.php";
	require_once "db-prefs.php";

	if (!defined('STDIN')) {
		?> <html>
		<head>
		<title>Database Updater</title>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
		<link rel="stylesheet" type="text/css" href="utility.css">
		</head>

		<body>
		<div class="floatingLogo"><img src="images/logo_wide.png"></div>
		<h1><?php echo __("Update") ?></h1>

		<?php print_error("Please run this script from the command line."); ?>

		</body></html>
	<?php
		exit;
	}

	if (!defined('PHP_EXECUTABLE'))
		define('PHP_EXECUTABLE', '/usr/bin/php');

	$op = $argv;

	if (count($argv) == 1 || in_array("-help", $op) ) {
		print "Tiny Tiny RSS data update script.\n\n";
		print "Options:\n";
		print "  -feeds              - update feeds\n";
		print "  -feedbrowser        - update feedbrowser\n";
		print "  -daemon             - start single-process update daemon\n";
		print "  -cleanup-tags       - perform tags table maintenance\n";
		print "  -get-feeds          - receive popular feeds from linked instances\n";
		print "  -import USER FILE   - import articles from XML\n";
		print "  -update-self        - update tt-rss installation to latest version\n";
		print "  -quiet              - don't show messages\n";
		print "  -indexes            - recreate missing schema indexes\n";
		print "  -help               - show this help\n";
		return;
	}

	define('QUIET', in_array("-quiet", $op));

	if (!in_array("-daemon", $op)) {
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

	init_connection($link);

	if (in_array("-feeds", $op)) {
		// Update all feeds needing a update.
		update_daemon_common($link);

		// Update feedbrowser
		$count = update_feedbrowser_cache($link);
		_debug("Feedbrowser updated, $count feeds processed.");

		// Purge orphans and cleanup tags
		purge_orphans($link, true);

		$rc = cleanup_tags($link, 14, 50000);
		_debug("Cleaned $rc cached tags.");

		get_linked_feeds($link);
	}

	if (in_array("-feedbrowser", $op)) {
		$count = update_feedbrowser_cache($link);
		print "Finished, $count feeds processed.\n";
	}

	if (in_array("-daemon", $op)) {
		$op = array_diff($op, array("-daemon"));
		while (true) {
			passthru(PHP_EXECUTABLE . " " . implode(' ', $op) . " -daemon-loop");
			_debug("Sleeping for " . DAEMON_SLEEP_INTERVAL . " seconds...");
			sleep(DAEMON_SLEEP_INTERVAL);
		}
	}

	if (in_array("-daemon-loop", $op)) {
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

			get_linked_feeds($link);
		}

	}

	if (in_array("-cleanup-tags", $op)) {
		$rc = cleanup_tags($link, 14, 50000);
		_debug("$rc tags deleted.\n");
	}

	if (in_array("-get-feeds", $op)) {
		get_linked_feeds($link);
	}

	if (in_array("-import",$op)) {
		$username = $argv[count($argv) - 2];
		$filename = $argv[count($argv) - 1];

		if (!$username) {
			print "error: please specify username.\n";
			return;
		}

		if (!is_file($filename)) {
			print "error: input filename ($filename) doesn't exist.\n";
			return;
		}

		_debug("importing $filename for user $username...\n");

		$result = db_query($link, "SELECT id FROM ttrss_users WHERE login = '$username'");

		if (db_num_rows($result) == 0) {
			print "error: could not find user $username.\n";
			return;
		}

		$owner_uid = db_fetch_result($result, 0, "id");

		perform_data_import($link, $filename, $owner_uid);

	}

	if (in_array("-indexes", $op)) {
		_debug("PLEASE BACKUP YOUR DATABASE BEFORE PROCEEDING!");
		_debug("Type 'yes' to continue.");

		if (read_stdin() != 'yes')
			exit;

		_debug("clearing existing indexes...");

		if (DB_TYPE == "pgsql") {
			$result = db_query($link, "SELECT relname FROM
				pg_catalog.pg_class WHERE relname LIKE 'ttrss_%'
					AND relname NOT LIKE '%_pkey'
				AND relkind = 'i'");
		} else {
			$result = db_query($link, "SELECT index_name,table_name FROM
				information_schema.statistics WHERE index_name LIKE 'ttrss_%'");
		}

		while ($line = db_fetch_assoc($result)) {
			if (DB_TYPE == "pgsql") {
				$statement = "DROP INDEX " . $line["relname"];
				_debug($statement);
			} else {
				$statement = "ALTER TABLE ".
					$line['table_name']." DROP INDEX ".$line['index_name'];
				_debug($statement);
			}
			db_query($link, $statement, false);
		}

		_debug("reading indexes from schema for: " . DB_TYPE);

		$fp = fopen("schema/ttrss_schema_" . DB_TYPE . ".sql", "r");
		if ($fp) {
			while ($line = fgets($fp)) {
				$matches = array();

				if (preg_match("/^create index ([^ ]+) on ([^ ]+)$/i", $line, $matches)) {
					$index = $matches[1];
					$table = $matches[2];

					$statement = "CREATE INDEX $index ON $table";

					_debug($statement);
					db_query($link, $statement);
				}
			}
			fclose($fp);
		} else {
			_debug("unable to open schema file.");
		}
		_debug("all done.");
	}

	if (in_array("-update-self", $op)) {
		_debug("Warning: self-updating is experimental. Use at your own risk.");
		_debug("Please backup your tt-rss directory before continuing. Your database will not be modified.");
		_debug("Type 'yes' to continue.");

		if (read_stdin() != 'yes')
			exit;

		$work_dir = dirname(__FILE__);
		$parent_dir = dirname($work_dir);

		if (!is_writable($work_dir) && !is_writable("$parent_dir")) {
			_debug("Both current and parent directories should be writable as current user.");
			exit;
		}

		if (!is_writable(sys_get_temp_dir())) {
			_debug("System temporary directory should be writable as current user.");
			exit;
		}

		_debug("Checking for tar...");

		$system_rc = 0;
		system("tar --version >/dev/null", $system_rc);

		if ($system_rc != 0) {
			_debug("Could not run tar executable (RC=$system_rc).");
			exit;
		}

		_debug("Checking for latest version...");

		$version_info = json_decode(fetch_file_contents("http://tt-rss.org/version.php"),
			true);

		if (!is_array($version_info)) {
			_debug("Unable to fetch version information.");
			exit;
		}

		$target_version = $version_info["version"];
		$target_dir = "$parent_dir/tt-rss-$target_version";

		_debug("Target version: $target_version");

		if (version_compare(VERSION, $target_version) != -1 && !in_array("-force", $op)) {
			_debug("You are on the latest version. Update not needed.");
			exit;
		}
		if (file_exists($target_dir)) {
			_debug("Target directory $target_dir already exists.");
			exit;
		}

		_debug("Downloading checksums...");
		$md5sum_data = fetch_file_contents("http://tt-rss.org/download/md5sum.txt");

		if (!$md5sum_data) {
			_debug("Could not download checksums.");
			exit;
		}

		$md5sum_data = explode("\n", $md5sum_data);

		$tarball_url = "http://tt-rss.org/download/tt-rss-$target_version.tar.gz";
		$data = fetch_file_contents($tarball_url);

		if (!$data) {
			_debug("Could not download distribution tarball ($tarball_url).");
			exit;
		}

		_debug("Verifying tarball checksum...");

		$target_md5sum = false;

		foreach ($md5sum_data as $line) {
			$pair = explode("  ", $line);

			if ($pair[1] == "tt-rss-$target_version.tar.gz") {
				$target_md5sum = $pair[0];
				break;
			}
		}

		if (!$target_md5sum) {
			_debug("Unable to locate checksum for target version.");
			exit;
		}

		$test_md5sum = md5($data);

		if ($test_md5sum != $target_md5sum) {
			_debug("Downloaded checksum doesn't match (got $test_md5sum, expected $target_md5sum).");
			exit;
		}

		$tmp_file = tempnam(sys_get_temp_dir(), 'tt-rss');
		_debug("Saving download to $tmp_file");

		if (!file_put_contents($tmp_file, $data)) {
			_debug("Unable to save download.");
			exit;
		}

		if (!chdir($parent_dir)) {
			_debug("Unable to change into parent directory.");
			exit;
		}

		$old_dir = tmpdirname($parent_dir, "tt-rss-old");

		_debug("Renaming tt-rss directory to ".basename($old_dir));
		if (!rename($work_dir, $old_dir)) {
			_debug("Unable to rename tt-rss directory.");
			exit;
		}

		_debug("Extracting tarball...");
		system("tar zxf $tmp_file", $system_rc);

		if ($system_rc != 0) {
			_debug("Error while extracting tarball (RC=$system_rc).");
			exit;
		}

		_debug("Renaming target directory...");
		if (!rename($target_dir, $work_dir)) {
			_debug("Unable to rename target directory.");
			exit;
		}

		chdir($work_dir);

		_debug("Copying config.php...");
		if (!copy("$old_dir/config.php", "$work_dir/config.php")) {
			_debug("Unable to copy config.php to $work_dir.");
			exit;
		}

		_debug("Cleaning up...");
		unlink($tmp_file);

		_debug("Fixing permissions...");

		$directories = array(
			CACHE_DIR,
			CACHE_DIR . "/htmlpurifier",
			CACHE_DIR . "/export",
			CACHE_DIR . "/images",
			CACHE_DIR . "/magpie",
			CACHE_DIR . "/simplepie",
			ICONS_DIR,
			LOCK_DIRECTORY);

		foreach ($directories as $dir) {
			_debug("-> $dir");
			chmod($dir, 0777);
		}

		_debug("Upgrade completed.");
		_debug("Your old tt-rss directory is saved at $old_dir. ".
			"Please migrate locally modified files (if any) and remove it.");
		_debug("You might need to re-enter current directory in shell to see new files.");
	}

	db_close($link);

	if ($lock_handle != false) {
		fclose($lock_handle);
	}

	if (file_exists(LOCK_DIRECTORY . "/$lock_filename"))
		unlink(LOCK_DIRECTORY . "/$lock_filename");
?>

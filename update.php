#!/usr/bin/env php
<?php
	set_include_path(dirname(__FILE__) ."/include" . PATH_SEPARATOR .
		get_include_path());

	define('DISABLE_SESSIONS', true);

	chdir(dirname(__FILE__));

	require_once "functions.php";
	require_once "rssfuncs.php";
	require_once "sanity_check.php";
	require_once "config.php";
	require_once "db.php";
	require_once "db-prefs.php";

	if (!defined('PHP_EXECUTABLE'))
		define('PHP_EXECUTABLE', '/usr/bin/php');

	// Create a database connection.
	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

	init_connection($link);


	$op = $argv;

	if (count($argv) == 0 && !defined('STDIN')) {
		?> <html>
		<head>
		<title>Tiny Tiny RSS data update script.</title>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
		<link rel="stylesheet" type="text/css" href="utility.css">
		</head>

		<body>
		<div class="floatingLogo"><img src="images/logo_wide.png"></div>
		<h1><?php echo __("Tiny Tiny RSS data update script.") ?></h1>

		<?php print_error("Please run this script from the command line. Use option \"-help\" to display command help if this error is displayed erroneously."); ?>

		</body></html>
	<?php
		exit;
	}

	if (count($argv) == 1 || in_array("-help", $op) ) {
		print "Tiny Tiny RSS data update script.\n\n";
		print "Options:\n";
		print "  -feeds              - update feeds\n";
		print "  -feedbrowser        - update feedbrowser\n";
		print "  -daemon             - start single-process update daemon\n";
		print "  -cleanup-tags       - perform tags table maintenance\n";
		print "  -import USER FILE   - import articles from XML\n";
		print "  -quiet              - don't show messages\n";
		print "  -indexes            - recreate missing schema indexes\n";
		print "  -convert-filters    - convert type1 filters to type2\n";
		print "  -force-update       - force update of all feeds\n";
		print "  -help               - show this help\n";
		print "Plugin options:\n";

		foreach ($pluginhost->get_commands() as $command => $data) {
			printf("  %-19s - %s\n", "$command", $data["description"]);
		}

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

		global $pluginhost;
		$pluginhost->run_hooks($pluginhost::HOOK_UPDATE_TASK, "hook_update_task", $op);
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

			global $pluginhost;
			$pluginhost->run_hooks($pluginhost::HOOK_UPDATE_TASK, "hook_update_task", $op);
		}

	}

	if (in_array("-cleanup-tags", $op)) {
		$rc = cleanup_tags($link, 14, 50000);
		_debug("$rc tags deleted.\n");
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

	if (in_array("-convert-filters", $op)) {
		_debug("WARNING: this will remove all existing type2 filters.");
		_debug("Type 'yes' to continue.");

		if (read_stdin() != 'yes')
			exit;

		_debug("converting filters...");

		db_query($link, "DELETE FROM ttrss_filters2");

		$result = db_query($link, "SELECT * FROM ttrss_filters ORDER BY id");

		while ($line = db_fetch_assoc($result)) {
			$owner_uid = $line["owner_uid"];

			// date filters are removed
			if ($line["filter_type"] != 5) {
				$filter = array();

				if (sql_bool_to_bool($line["cat_filter"])) {
					$feed_id = "CAT:" . (int)$line["cat_id"];
				} else {
					$feed_id = (int)$line["feed_id"];
				}

				$filter["enabled"] = $line["enabled"] ? "on" : "off";
				$filter["rule"] = array(
					json_encode(array(
						"reg_exp" => $line["reg_exp"],
						"feed_id" => $feed_id,
						"filter_type" => $line["filter_type"])));

				$filter["action"] = array(
					json_encode(array(
						"action_id" => $line["action_id"],
						"action_param_label" => $line["action_param"],
						"action_param" => $line["action_param"])));

				// Oh god it's full of hacks

				$_REQUEST = $filter;
				$_SESSION["uid"] = $owner_uid;

				$filters = new Pref_Filters($link, $_REQUEST);
				$filters->add();
			}
		}

	}

	if (in_array("-force-update", $op)) {
		_debug("marking all feeds as needing update...");

		db_query($link, "UPDATE ttrss_feeds SET last_update_started = '1970-01-01',
				last_updated = '1970-01-01'");
	}

	$pluginhost->run_commands($op);

	db_close($link);

	if ($lock_handle != false) {
		fclose($lock_handle);
	}

	if (file_exists(LOCK_DIRECTORY . "/$lock_filename"))
		unlink(LOCK_DIRECTORY . "/$lock_filename");
?>

#!/usr/bin/env php
<?php
	set_include_path(dirname(__FILE__) ."/include" . PATH_SEPARATOR .
		get_include_path());

	define('DISABLE_SESSIONS', true);

	chdir(dirname(__FILE__));

	require_once "autoload.php";
	require_once "functions.php";
	require_once "rssfuncs.php";
	require_once "config.php";
	require_once "sanity_check.php";
	require_once "db.php";
	require_once "db-prefs.php";

	if (!defined('PHP_EXECUTABLE'))
		define('PHP_EXECUTABLE', '/usr/bin/php');

	init_plugins();

	$longopts = array("feeds",
			"feedbrowser",
			"daemon",
			"daemon-loop",
			"task:",
			"cleanup-tags",
			"quiet",
			"log:",
			"indexes",
			"pidlock:",
			"update-schema",
			"convert-filters",
			"force-update",
			"list-plugins",
			"help");

	foreach (PluginHost::getInstance()->get_commands() as $command => $data) {
		array_push($longopts, $command . $data["suffix"]);
	}

	$options = getopt("", $longopts);

	if (count($options) == 0 && !defined('STDIN')) {
		?> <html>
		<head>
		<title>Tiny Tiny RSS data update script.</title>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
		<link rel="stylesheet" type="text/css" href="css/utility.css">
		</head>

		<body>
		<div class="floatingLogo"><img src="images/logo_small.png"></div>
		<h1><?php echo __("Tiny Tiny RSS data update script.") ?></h1>

		<?php print_error("Please run this script from the command line. Use option \"-help\" to display command help if this error is displayed erroneously."); ?>

		</body></html>
	<?php
		exit;
	}

	if (count($options) == 0 || isset($options["help"]) ) {
		print "Tiny Tiny RSS data update script.\n\n";
		print "Options:\n";
		print "  --feeds              - update feeds\n";
		print "  --feedbrowser        - update feedbrowser\n";
		print "  --daemon             - start single-process update daemon\n";
		print "  --task N             - create lockfile using this task id\n";
		print "  --cleanup-tags       - perform tags table maintenance\n";
		print "  --quiet              - don't output messages to stdout\n";
		print "  --log FILE           - log messages to FILE\n";
		print "  --indexes            - recreate missing schema indexes\n";
		print "  --update-schema      - update database schema\n";
		print "  --convert-filters    - convert type1 filters to type2\n";
		print "  --force-update       - force update of all feeds\n";
		print "  --list-plugins       - list all available plugins\n";
		print "  --help               - show this help\n";
		print "Plugin options:\n";

		foreach (PluginHost::getInstance()->get_commands() as $command => $data) {
			$args = $data['arghelp'];
			printf(" --%-19s - %s\n", "$command $args", $data["description"]);
		}

		return;
	}

	if (!isset($options['daemon'])) {
		require_once "errorhandler.php";
	}

	if (!isset($options['update-schema'])) {
		$schema_version = get_schema_version();

		if ($schema_version != SCHEMA_VERSION) {
			die("Schema version is wrong, please upgrade the database.\n");
		}
	}

	define('QUIET', isset($options['quiet']));

	if (isset($options["log"])) {
		_debug("Logging to " . $options["log"]);
		define('LOGFILE', $options["log"]);
	}

	if (!isset($options["daemon"])) {
		$lock_filename = "update.lock";
	} else {
		$lock_filename = "update_daemon.lock";
	}

	if (isset($options["task"])) {
		_debug("Using task id " . $options["task"]);
		$lock_filename = $lock_filename . "-task_" . $options["task"];
	}

	if (isset($options["pidlock"])) {
		$my_pid = $options["pidlock"];
		$lock_filename = "update_daemon-$my_pid.lock";

	}

	_debug("Lock: $lock_filename");

	$lock_handle = make_lockfile($lock_filename);
	$must_exit = false;

	if (isset($options["task"]) && isset($options["pidlock"])) {
		$waits = $options["task"] * 5;
		_debug("Waiting before update ($waits)");
		sleep($waits);
	}

	// Try to lock a file in order to avoid concurrent update.
	if (!$lock_handle) {
		die("error: Can't create lockfile ($lock_filename). ".
			"Maybe another update process is already running.\n");
	}

	if (isset($options["force-update"])) {
		_debug("marking all feeds as needing update...");

		db_query( "UPDATE ttrss_feeds SET last_update_started = '1970-01-01',
				last_updated = '1970-01-01'");
	}

	if (isset($options["feeds"])) {
		update_daemon_common();
		housekeeping_common(true);

		PluginHost::getInstance()->run_hooks(PluginHost::HOOK_UPDATE_TASK, "hook_update_task", $op);
	}

	if (isset($options["feedbrowser"])) {
		$count = update_feedbrowser_cache();
		print "Finished, $count feeds processed.\n";
	}

	if (isset($options["daemon"])) {
		while (true) {
			$quiet = (isset($options["quiet"])) ? "--quiet" : "";

			passthru(PHP_EXECUTABLE . " " . $argv[0] ." --daemon-loop $quiet");
			_debug("Sleeping for " . DAEMON_SLEEP_INTERVAL . " seconds...");
			sleep(DAEMON_SLEEP_INTERVAL);
		}
	}

	if (isset($options["daemon-loop"])) {
		if (!make_stampfile('update_daemon.stamp')) {
			_debug("warning: unable to create stampfile\n");
		}

		update_daemon_common(isset($options["pidlock"]) ? 50 : DAEMON_FEED_LIMIT);

		if (!isset($options["pidlock"]) || $options["task"] == 0)
			housekeeping_common(true);

		PluginHost::getInstance()->run_hooks(PluginHost::HOOK_UPDATE_TASK, "hook_update_task", $op);
	}

	if (isset($options["cleanup-tags"])) {
		$rc = cleanup_tags( 14, 50000);
		_debug("$rc tags deleted.\n");
	}

	if (isset($options["indexes"])) {
		_debug("PLEASE BACKUP YOUR DATABASE BEFORE PROCEEDING!");
		_debug("Type 'yes' to continue.");

		if (read_stdin() != 'yes')
			exit;

		_debug("clearing existing indexes...");

		if (DB_TYPE == "pgsql") {
			$result = db_query( "SELECT relname FROM
				pg_catalog.pg_class WHERE relname LIKE 'ttrss_%'
					AND relname NOT LIKE '%_pkey'
				AND relkind = 'i'");
		} else {
			$result = db_query( "SELECT index_name,table_name FROM
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
			db_query( $statement, false);
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
					db_query( $statement);
				}
			}
			fclose($fp);
		} else {
			_debug("unable to open schema file.");
		}
		_debug("all done.");
	}

	if (isset($options["convert-filters"])) {
		_debug("WARNING: this will remove all existing type2 filters.");
		_debug("Type 'yes' to continue.");

		if (read_stdin() != 'yes')
			exit;

		_debug("converting filters...");

		db_query( "DELETE FROM ttrss_filters2");

		$result = db_query( "SELECT * FROM ttrss_filters ORDER BY id");

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

				$filters = new Pref_Filters($_REQUEST);
				$filters->add();
			}
		}

	}

	if (isset($options["update-schema"])) {
		_debug("checking for updates (" . DB_TYPE . ")...");

		$updater = new DbUpdater(Db::get(), DB_TYPE, SCHEMA_VERSION);

		if ($updater->isUpdateRequired()) {
			_debug("schema update required, version " . $updater->getSchemaVersion() . " to " . SCHEMA_VERSION);
			_debug("WARNING: please backup your database before continuing.");
			_debug("Type 'yes' to continue.");

			if (read_stdin() != 'yes')
				exit;

			for ($i = $updater->getSchemaVersion() + 1; $i <= SCHEMA_VERSION; $i++) {
				_debug("performing update up to version $i...");

				$result = $updater->performUpdateTo($i);

				_debug($result ? "OK!" : "FAILED!");

				if (!$result) return;

			}
		} else {
			_debug("update not required.");
		}

	}

	if (isset($options["list-plugins"])) {
		$tmppluginhost = new PluginHost();
		$tmppluginhost->load_all($tmppluginhost::KIND_ALL);
		$enabled = array_map("trim", explode(",", PLUGINS));

		echo "List of all available plugins:\n";

		foreach ($tmppluginhost->get_plugins() as $name => $plugin) {
			$about = $plugin->about();

			$status = $about[3] ? "system" : "user";

			if (in_array($name, $enabled)) $name .= "*";

			printf("%-50s %-10s v%.2f (by %s)\n%s\n\n",
				$name, $status, $about[0], $about[2], $about[1]);
		}

		echo "Plugins marked by * are currently enabled for all users.\n";

	}

	PluginHost::getInstance()->run_commands($options);

	if (file_exists(LOCK_DIRECTORY . "/$lock_filename"))
		unlink(LOCK_DIRECTORY . "/$lock_filename");
?>

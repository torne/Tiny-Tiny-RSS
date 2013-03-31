#!/usr/bin/env php
<?php
	set_include_path(dirname(__FILE__) ."/include" . PATH_SEPARATOR .
		get_include_path());

	declare(ticks = 1);
	chdir(dirname(__FILE__));

	define('DISABLE_SESSIONS', true);

	require_once "version.php";

	if (strpos(VERSION, ".99") !== false || getenv('DAEMON_XDEBUG')) {
		define('DAEMON_EXTENDED_DEBUG', true);
	}

	require_once "functions.php";
	require_once "rssfuncs.php";
	require_once "sanity_check.php";
	require_once "config.php";
	require_once "db.php";
	require_once "db-prefs.php";

	// defaults
	define('PURGE_INTERVAL', 3600); // seconds
	define('MAX_CHILD_RUNTIME', 600); // seconds
	define('MAX_JOBS', 2);
	define('SPAWN_INTERVAL', DAEMON_SLEEP_INTERVAL); // seconds

	if (!function_exists('pcntl_fork')) {
		die("error: This script requires PHP compiled with PCNTL module.\n");
	}

	$master_handlers_installed = false;

	$children = array();
	$ctimes = array();

	$last_checkpoint = -1;

	function reap_children() {
		global $children;
		global $ctimes;

		$tmp = array();

		foreach ($children as $pid) {
			if (pcntl_waitpid($pid, $status, WNOHANG) != $pid) {

				if (file_is_locked("update_daemon-$pid.lock")) {
					array_push($tmp, $pid);
				} else {
					_debug("[reap_children] child $pid seems active but lockfile is unlocked.");
					unset($ctimes[$pid]);

				}
			} else {
				_debug("[reap_children] child $pid reaped.");
				unset($ctimes[$pid]);
			}
		}

		$children = $tmp;

		return count($tmp);
	}

	function check_ctimes() {
		global $ctimes;

		foreach (array_keys($ctimes) as $pid) {
			$started = $ctimes[$pid];

			if (time() - $started > MAX_CHILD_RUNTIME) {
				_debug("[MASTER] child process $pid seems to be stuck, aborting...");
				posix_kill($pid, SIGKILL);
			}
		}
	}

	function sigchld_handler($signal) {
		$running_jobs = reap_children();

		_debug("[SIGCHLD] jobs left: $running_jobs");

		pcntl_waitpid(-1, $status, WNOHANG);
	}

	function shutdown($caller_pid) {
		if ($caller_pid == posix_getpid()) {
			if (file_exists(LOCK_DIRECTORY . "/update_daemon.lock")) {
				_debug("removing lockfile (master)...");
				unlink(LOCK_DIRECTORY . "/update_daemon.lock");
			}
		}
	}

	function task_shutdown() {
		$pid = posix_getpid();

		if (file_exists(LOCK_DIRECTORY . "/update_daemon-$pid.lock")) {
			_debug("removing lockfile ($pid)...");
			unlink(LOCK_DIRECTORY . "/update_daemon-$pid.lock");
		}
	}

	function sigint_handler() {
		_debug("[MASTER] SIG_INT received.\n");
		shutdown(posix_getpid());
		die;
	}

	function task_sigint_handler() {
		_debug("[TASK] SIG_INT received.\n");
		task_shutdown();
		die;
	}

	pcntl_signal(SIGCHLD, 'sigchld_handler');

	$longopts = array("log:",
			"tasks:",
			"interval",
			"quiet",
			"help");

	$options = getopt("", $longopts);

	if (isset($options["help"]) ) {
		print "Tiny Tiny RSS update daemon.\n\n";
		print "Options:\n";
		print "  --log FILE           - log messages to FILE\n";
		print "  --tasks N            - amount of update tasks to spawn\n";
		print "                         default: " . MAX_JOBS . "\n";
		print "  --interval N         - task spawn interval\n";
		print "                         default: " . SPAWN_INTERVAL . " seconds.\n";
		print "  --quiet              - don't output messages to stdout\n";
		return;
	}

	define('QUIET', isset($options['quiet']));

	if (isset($options["tasks"])) {
		_debug("Set to spawn " . $options["tasks"] . " children.");
		$max_jobs = $option["tasks"];
	} else {
		$max_jobs = MAX_JOBS;
	}

	if (isset($options["interval"])) {
		_debug("Spawn interval: " . $options["interval"] . " seconds.");
		$spawn_interval = $option["interval"];
	} else {
		$spawn_interval = SPAWN_INTERVAL;
	}

	if (isset($options["log"])) {
		_debug("Logging to " . $options["log"]);
		define('LOGFILE', $options["log"]);
	}

	if (file_is_locked("update_daemon.lock")) {
		die("error: Can't create lockfile. ".
			"Maybe another daemon is already running.\n");
	}

	// Try to lock a file in order to avoid concurrent update.
	$lock_handle = make_lockfile("update_daemon.lock");

	if (!$lock_handle) {
		die("error: Can't create lockfile. ".
			"Maybe another daemon is already running.\n");
	}

	// Testing database connection.
	// It is unnecessary to start the fork loop if database is not ok.
	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

	if (!init_connection($link)) return;

	db_close($link);

	while (true) {

		// Since sleep is interupted by SIGCHLD, we need another way to
		// respect the spawn interval
		$next_spawn = $last_checkpoint + $spawn_interval - time();

		if ($next_spawn % 60 == 0) {
			$running_jobs = count($children);
			_debug("[MASTER] active jobs: $running_jobs, next spawn at $next_spawn sec.");
		}

		if ($last_checkpoint + $spawn_interval < time()) {

			check_ctimes();
			reap_children();

			for ($j = count($children); $j < $max_jobs; $j++) {
				$pid = pcntl_fork();
				if ($pid == -1) {
					die("fork failed!\n");
				} else if ($pid) {

					if (!$master_handlers_installed) {
						_debug("[MASTER] installing shutdown handlers");
						pcntl_signal(SIGINT, 'sigint_handler');
						register_shutdown_function('shutdown', posix_getpid());
						$master_handlers_installed = true;
					}

					_debug("[MASTER] spawned client $j [PID:$pid]...");
					array_push($children, $pid);
					$ctimes[$pid] = time();
				} else {
					pcntl_signal(SIGCHLD, SIG_IGN);
					pcntl_signal(SIGINT, 'task_sigint_handler');

					register_shutdown_function('task_shutdown');

					$my_pid = posix_getpid();
					$lock_filename = "update_daemon-$my_pid.lock";

					$lock_handle = make_lockfile($lock_filename);

					if (!$lock_handle) {
						die("error: Can't create lockfile ($lock_filename). ".
						"Maybe another daemon is already running.\n");
					}

					// ****** Updating RSS code *******
					// Only run in fork process.

					$start_timestamp = time();

					$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

					if (!init_connection($link)) return;

					// We disable stamp file, since it is of no use in a multiprocess update.
					// not really, tho for the time being -fox
					if (!make_stampfile('update_daemon.stamp')) {
						_debug("warning: unable to create stampfile\n");
					}

					// Call to the feed batch update function
					// and maybe regenerate feedbrowser cache

					$nf = 0;

					_debug("Waiting before update [$j]..");
					sleep($j*5);
					$nf = update_daemon_common($link);

					if (rand(0,100) > 50) {
						$count = update_feedbrowser_cache($link);
						_debug("Feedbrowser updated, $count feeds processed.");

						purge_orphans($link, true);

						$rc = cleanup_tags($link, 14, 50000);

						_debug("Cleaned $rc cached tags.");

						global $pluginhost;
						$pluginhost->run_hooks($pluginhost::HOOK_UPDATE_TASK, "hook_update_task", $op);
					}

					_debug("Elapsed time: " . (time() - $start_timestamp) . " second(s)");

					if ($nf > 0) {
						_debug("Feeds processed: $nf; feeds/minute: " . sprintf("%.2d", $nf/((time()-$start_timestamp)/60)));
					}

					db_close($link);

					// We are in a fork.
					// We wait a little before exiting to avoid to be faster than our parent process.
					sleep(1);

					unlink(LOCK_DIRECTORY . "/$lock_filename");

					// We exit in order to avoid fork bombing.
					exit(0);
				}
			}
			$last_checkpoint = time();
		}
		sleep(1);
	}

?>

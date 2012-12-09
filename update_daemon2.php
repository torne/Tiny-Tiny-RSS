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

	define('PURGE_INTERVAL', 3600); // seconds
	define('MAX_CHILD_RUNTIME', 600); // seconds

	require_once "functions.php";
	require_once "rssfuncs.php";
	require_once "sanity_check.php";
	require_once "config.php";
	require_once "db.php";
	require_once "db-prefs.php";
	require_once "lib/magpierss/rss_fetch.inc";

	define('MAX_JOBS', 2);
	define('SPAWN_INTERVAL', DAEMON_SLEEP_INTERVAL);

	if (!function_exists('pcntl_fork')) {
		die("error: This script requires PHP compiled with PCNTL module.\n");
	}

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

	function shutdown() {
		if (file_exists(LOCK_DIRECTORY . "/update_daemon.lock"))
			unlink(LOCK_DIRECTORY . "/update_daemon.lock");
	}

	function task_shutdown() {
		$pid = posix_getpid();

		if (file_exists(LOCK_DIRECTORY . "/update_daemon-$pid.lock"))
			unlink(LOCK_DIRECTORY . "/update_daemon-$pid.lock");
	}

	function sigint_handler() {
		shutdown();
		die("[SIGINT] removing lockfile and exiting.\n");
	}

	function task_sigint_handler() {
		task_shutdown();
		die("[SIGINT] removing lockfile and exiting.\n");
	}

	pcntl_signal(SIGCHLD, 'sigchld_handler');

	if (file_is_locked("update_daemon.lock")) {
		die("error: Can't create lockfile. ".
			"Maybe another daemon is already running.\n");
	}

	if (!pcntl_fork()) {
		pcntl_signal(SIGINT, 'sigint_handler');
		register_shutdown_function('shutdown');

		// Try to lock a file in order to avoid concurrent update.
		$lock_handle = make_lockfile("update_daemon.lock");

		if (!$lock_handle) {
			die("error: Can't create lockfile. ".
				"Maybe another daemon is already running.\n");
		}

		while (true) { sleep(100); }
	}

	// Testing database connection.
	// It is unnecessary to start the fork loop if database is not ok.
	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

	if (!init_connection($link)) return;

	db_close($link);

	while (true) {

		// Since sleep is interupted by SIGCHLD, we need another way to
		// respect the SPAWN_INTERVAL
		$next_spawn = $last_checkpoint + SPAWN_INTERVAL - time();

		if ($next_spawn % 10 == 0) {
			$running_jobs = count($children);
			_debug("[MASTER] active jobs: $running_jobs, next spawn at $next_spawn sec.");
		}

		if ($last_checkpoint + SPAWN_INTERVAL < time()) {

			check_ctimes();
			reap_children();

			for ($j = count($children); $j < MAX_JOBS; $j++) {
				$pid = pcntl_fork();
				if ($pid == -1) {
					die("fork failed!\n");
				} else if ($pid) {
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
						print "warning: unable to create stampfile";
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

						_debug("Updating linked feeds...");
						get_linked_feeds($link);

					}

					_debug("Elapsed time: " . (time() - $start_timestamp) . " second(s)");

					db_close($link);

					// We are in a fork.
					// We wait a little before exiting to avoid to be faster than our parent process.
					sleep(1);

					unlink(LOCK_DIRECTORY . "/$lock_filename");

					// We exit in order to avoid fork bombing.
					exit(0);
				}

				// We wait a little time before the next fork, in order to let the first fork
				// mark the feeds it update :
				sleep(1);
			}
			$last_checkpoint = time();
		}
		sleep(1);
	}

?>

#!/usr/bin/php
<?php
	// This is an experimental multiprocess update daemon.
	// Some configurable variable may be found below.

	// define('DEFAULT_ERROR_LEVEL', E_ALL);
	define('DEFAULT_ERROR_LEVEL', E_ERROR | E_WARNING | E_PARSE);

	declare(ticks = 1);

	define('MAGPIE_CACHE_DIR', '/var/tmp/magpie-ttrss-cache-daemon');
	define('SIMPLEPIE_CACHE_DIR',	'/var/tmp/simplepie-ttrss-cache-daemon');
	define('DISABLE_SESSIONS', true);

	require_once "version.php";

	if (strpos(VERSION, ".99") !== false || getenv('DAEMON_XDEBUG')) {
		define('DAEMON_EXTENDED_DEBUG', true);
	}

	define('PURGE_INTERVAL', 3600); // seconds

	require_once "sanity_check.php";
	require_once "config.php";

	define('MAX_JOBS', 2);

	define('SPAWN_INTERVAL', DAEMON_SLEEP_INTERVAL);

	if (!ENABLE_UPDATE_DAEMON) {
		die("Please enable option ENABLE_UPDATE_DAEMON in config.php\n");
	}
	
	require_once "db.php";
	require_once "db-prefs.php";
	require_once "functions.php";
	require_once "magpierss/rss_fetch.inc";

	error_reporting(DEFAULT_ERROR_LEVEL);

	$children = array();

	$last_checkpoint = -1;

	function reap_children() {
		global $children;

		$tmp = array();

		foreach ($children as $pid) {
			if (pcntl_waitpid($pid, $status, WNOHANG) != $pid) {
				array_push($tmp, $pid);
			} else {
				_debug("[SIGCHLD] child $pid reaped.");
			}
		}

		$children = $tmp;

		return count($tmp);
	}

	function sigalrm_handler() {
		die("[SIGALRM] hang in feed update?\n");
	}

	function sigchld_handler($signal) {
		$running_jobs = reap_children();

		_debug("[SIGCHLD] jobs left: $running_jobs");

		pcntl_waitpid(-1, $status, WNOHANG);
	}

	function sigint_handler() {
		unlink(LOCK_DIRECTORY . "/update_daemon.lock");
		die("[SIGINT] removing lockfile and exiting.\n");
	}

	pcntl_signal(SIGALRM, 'sigalrm_handler');
	pcntl_signal(SIGCHLD, 'sigchld_handler');

	if (file_is_locked("update_daemon.lock")) {
		die("error: Can't create lockfile. ".
			"Maybe another daemon is already running.\n");
	}

	if (!pcntl_fork()) {
		pcntl_signal(SIGINT, 'sigint_handler');

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

	if (!$link) {
		if (DB_TYPE == "mysql") {
			print mysql_error();
		}
		// PG seems to display its own errors just fine by default.		
		return;
	}

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

			reap_children();

			for ($j = count($children); $j < MAX_JOBS; $j++) {
				$pid = pcntl_fork();
				if ($pid == -1) {
					die("fork failed!\n");
				} else if ($pid) {
					_debug("[MASTER] spawned client $j [PID:$pid]...");
					array_push($children, $pid);
				} else {
					pcntl_signal(SIGCHLD, SIG_IGN);
					pcntl_signal(SIGINT, SIG_DFL);

					// ****** Updating RSS code *******
					// Only run in fork process.

					$start_timestamp = time();

					$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);	

					if (!$link) {
						if (DB_TYPE == "mysql") {
							print mysql_error();
						}
						// PG seems to display its own errors just fine by default.		
						return;
					}

					init_connection($link);

					// We disable stamp file, since it is of no use in a multiprocess update.
					// not really, tho for the time being -fox
					if (!make_stampfile('update_daemon.stamp')) {
						print "warning: unable to create stampfile";
					}	

					// $last_purge = 0;

					// if (time() - $last_purge > PURGE_INTERVAL) {

					// FIXME : $last_purge is of no use in a multiprocess update.
					// FIXME : We ALWAYS purge old posts.
					_debug("Purging old posts (random 30 feeds)...");
					global_purge_old_posts($link, true, 30);

					// 	$last_purge = time();
					// }

					// Call to the feed batch update function
					update_daemon_common($link);

					_debug("Elapsed time: " . (time() - $start_timestamp) . " second(s)");
 
					db_close($link);

					// We are in a fork.
					// We wait a little before exiting to avoid to be faster than our parent process.
					sleep(1);
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

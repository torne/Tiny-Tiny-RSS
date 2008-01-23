#!/usr/bin/php
<?php
	// This is an experimental multiprocess update daemon
	// It consists of the master server (this file) and
	// client batch script (update_daemon2_client.php) which
	// should only be run by the server process

	declare(ticks = 1);

	require_once "config.php";
	require_once "functions.php";

	define('MAX_JOBS', 2);
	define('CLIENT_PROCESS', './update_daemon2_client.php SRV_RUN_OK');
	define('SPAWN_INTERVAL', DAEMON_SLEEP_INTERVAL);

	$running_jobs = 0;
	$last_checkpoint = -1;

	function sigchld_handler($signal) {
		global $running_jobs;
		if ($running_jobs > 0) $running_jobs--;
		print posix_getpid() . ": SIGCHLD received, jobs left: $running_jobs\n";
		pcntl_waitpid(-1, $status, WNOHANG);
	}

	function sigint_handler() {
		unlink(LOCK_DIRECTORY . "/update_daemon.lock");
		die("Received SIGINT. Exiting.\n");
	}

	pcntl_signal(SIGCHLD, 'sigchld_handler');
	pcntl_signal(SIGINT, 'sigint_handler');

	$lock_handle = make_lockfile("update_daemon.lock");

	if (!$lock_handle) {
		die("error: Can't create lockfile ($lock_filename). ".
			"Maybe another daemon is already running.\n");
	}

	while (true) {

		$next_spawn = $last_checkpoint + SPAWN_INTERVAL - time();

		print "[MASTER] active jobs: $running_jobs, next spawn at $next_spawn sec\n";

		if ($last_checkpoint + SPAWN_INTERVAL < time()) {

			for ($j = $running_jobs; $j < MAX_JOBS; $j++) {
				print "[MASTER] spawning client $j...";
				$pid = pcntl_fork();
				if ($pid == -1) {
					die("fork failed!\n");
				} else if ($pid) {
					$running_jobs++;
					print "OK [$running_jobs]\n";
				} else {
					pcntl_signal(SIGCHLD, SIG_IGN);
					pcntl_signal(SIGINT, SIG_DFL);
					passthru(CLIENT_PROCESS);
					exit(0);
				}
			}
			$last_checkpoint = time();
		}
		sleep(1);
	}

?>

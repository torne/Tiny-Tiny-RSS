<?php
	define('VERSION_STATIC', '15.7');

	function get_version() {
		date_default_timezone_set('UTC');
		$root_dir = dirname(dirname(__FILE__));

		if (is_dir("$root_dir/.git") && file_exists("$root_dir/.git/refs/heads/master")) {

			$suffix = substr(trim(file_get_contents("$root_dir/.git/refs/heads/master")), 0, 7);
			$timestamp = filemtime("$root_dir/.git/refs/heads/master");

			define("GIT_VERSION_HEAD", $suffix);
			define("GIT_VERSION_TIMESTAMP", $timestamp);

			return VERSION_STATIC . " ($suffix)";
		} else {
			return VERSION_STATIC;
		}
	}

	define('VERSION', get_version());
?>

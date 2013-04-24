<?php
	define('VERSION_STATIC', '1.7.8');

	function get_version() {
		date_default_timezone_set('UTC');
		$root_dir = dirname(dirname(__FILE__));

		if (is_dir("$root_dir/.git") && file_exists("$root_dir/.git/ORIG_HEAD")) {

			$suffix = date("Ymd", filemtime("$root_dir/.git/ORIG_HEAD"));

			return VERSION_STATIC . ".$suffix";
		} else {
			return VERSION_STATIC;
		}
	}

	define('VERSION', get_version());
?>

<?php
	define('VERSION_STATIC', '1.8');

	function get_version() {
		date_default_timezone_set('UTC');
		$root_dir = dirname(dirname(__FILE__));

		if (is_dir("$root_dir/.git") && file_exists("$root_dir/.git/ORIG_HEAD")) {

			$suffix = substr(trim(file_get_contents("$root_dir/.git/ORIG_HEAD")), 0, 7);

			return VERSION_STATIC . ".$suffix";
		} else {
			return VERSION_STATIC;
		}
	}

	define('VERSION', get_version());
?>

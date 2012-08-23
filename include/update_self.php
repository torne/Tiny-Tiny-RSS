<?php
	function update_self($link, $force = false) {
		// __FILE__ is in include/ so we need to go one level up
		$work_dir = dirname(dirname(__FILE__));
		$parent_dir = dirname($work_dir);

		if (!is_writable($work_dir) && !is_writable("$parent_dir")) {
			_debug("Both current and parent directories should be writable as current user.");
			exit;
		}

		if (!file_exists("$work_dir/config.php") || !file_exists("$work_dir/include/sanity_check.php")) {
			_debug("Work directory $work_dir doesn't look like tt-rss installation.");
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

		if (version_compare(VERSION, $target_version) != -1 && !$force) {
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
?>

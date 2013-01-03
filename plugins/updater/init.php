<?php
class Updater extends Plugin {

	private $link;
	private $host;

	function about() {
		return array(1.0,
			"Updates tt-rss installation to latest version.",
			"fox",
			true);
	}

	function init($host) {
		$this->link = $host->get_link();
		$this->host = $host;

		$host->add_hook($host::HOOK_PREFS_TAB, $this);

		$host->add_command("update-self",
			"update tt-rss installation to latest version",
			$this);
	}

	function update_self_step($link, $step, $params, $force = false) {
		// __FILE__ is in plugins/updater so we need to go one level up
		$work_dir = dirname(dirname(dirname(__FILE__)));
		$parent_dir = dirname($work_dir);

		if (!chdir($work_dir)) {
			array_push($log, "Unable to change to work directory: $work_dir");
			$stop = true; break;
		}

		$stop = false;
		$log = array();
		if (!is_array($params)) $params = array();

		switch ($step) {
		case 0:
			array_push($log, "Work directory: $work_dir");

			if (!is_writable($work_dir) && !is_writable("$parent_dir")) {
				$user = posix_getpwuid(posix_geteuid());
				$user = $user["name"];
				array_push($log, "Both tt-rss and parent directories should be writable as current user ($user).");
				$stop = true; break;
			}

			if (!file_exists("$work_dir/config.php") || !file_exists("$work_dir/include/sanity_check.php")) {
				array_push($log, "Work directory $work_dir doesn't look like tt-rss installation.");
				$stop = true; break;
			}

			if (!is_writable(sys_get_temp_dir())) {
				array_push($log, "System temporary directory should be writable as current user.");
				$stop = true; break;
			}

			array_push($log, "Checking for tar...");

			$system_rc = 0;
			system("which tar >/dev/null", $system_rc);

			if ($system_rc != 0) {
				array_push($log, "Could not run tar executable (RC=$system_rc).");
				$stop = true; break;
			}

			array_push($log, "Checking for gunzip...");

			$system_rc = 0;
			system("which gunzip >/dev/null", $system_rc);

			if ($system_rc != 0) {
				array_push($log, "Could not run gunzip executable (RC=$system_rc).");
				$stop = true; break;
			}


			array_push($log, "Checking for latest version...");

			$version_info = json_decode(fetch_file_contents("http://tt-rss.org/version.php"),
				true);

			if (!is_array($version_info)) {
				array_push($log, "Unable to fetch version information.");
				$stop = true; break;
			}

			$target_version = $version_info["version"];
			$target_dir = "$parent_dir/tt-rss-$target_version";

			array_push($log, "Target version: $target_version");
			$params["target_version"] = $target_version;

			if (version_compare(VERSION, $target_version) != -1 && !$force) {
				array_push($log, "Your Tiny Tiny RSS installation is up to date.");
				$stop = true; break;
			}

			if (file_exists($target_dir)) {
				array_push($log, "Target directory $target_dir already exists.");
				$stop = true; break;
			}

			break;
		case 1:
			$target_version = $params["target_version"];

			array_push($log, "Downloading checksums...");
			$md5sum_data = fetch_file_contents("http://tt-rss.org/download/md5sum.txt");

			if (!$md5sum_data) {
				array_push($log, "Could not download checksums.");
				$stop = true; break;
			}

			$md5sum_data = explode("\n", $md5sum_data);

			foreach ($md5sum_data as $line) {
				$pair = explode("  ", $line);

				if ($pair[1] == "tt-rss-$target_version.tar.gz") {
					$target_md5sum = $pair[0];
					break;
				}
			}

			if (!$target_md5sum) {
				array_push($log, "Unable to locate checksum for target version.");
				$stop = true; break;
			}

			$params["target_md5sum"] = $target_md5sum;

			break;
		case 2:
			$target_version = $params["target_version"];
			$target_md5sum = $params["target_md5sum"];

			array_push($log, "Downloading distribution tarball...");

			$tarball_url = "http://tt-rss.org/download/tt-rss-$target_version.tar.gz";
			$data = fetch_file_contents($tarball_url);

			if (!$data) {
				array_push($log, "Could not download distribution tarball ($tarball_url).");
				$stop = true; break;
			}

			array_push($log, "Verifying tarball checksum...");

			$test_md5sum = md5($data);

			if ($test_md5sum != $target_md5sum) {
				array_push($log, "Downloaded checksum doesn't match (got $test_md5sum, expected $target_md5sum).");
				$stop = true; break;
			}

			$tmp_file = tempnam(sys_get_temp_dir(), 'tt-rss');
			array_push($log, "Saving download to $tmp_file");

			if (!file_put_contents($tmp_file, $data)) {
				array_push($log, "Unable to save download.");
				$stop = true; break;
			}

			$params["tmp_file"] = $tmp_file;

			break;
		case 3:
			$tmp_file = $params["tmp_file"];
			$target_version = $params["target_version"];

			if (!chdir($parent_dir)) {
				array_push($log, "Unable to change into parent directory.");
				$stop = true; break;
			}

			$old_dir = tmpdirname($parent_dir, "tt-rss-old");

			array_push($log, "Renaming tt-rss directory to ".basename($old_dir));
			if (!rename($work_dir, $old_dir)) {
				array_push($log, "Unable to rename tt-rss directory.");
				$stop = true; break;
			}

			array_push($log, "Extracting tarball...");
			system("tar zxf $tmp_file", $system_rc);

			if ($system_rc != 0) {
				array_push($log, "Error while extracting tarball (RC=$system_rc).");
				$stop = true; break;
			}

			$target_dir = "$parent_dir/tt-rss-$target_version";

			array_push($log, "Renaming target directory...");
			if (!rename($target_dir, $work_dir)) {
				array_push($log, "Unable to rename target directory.");
				$stop = true; break;
			}

			if (!chdir($work_dir)) {
				array_push($log, "Unable to change to work directory: $work_dir");
				$stop = true; break;
			}

			array_push($log, "Copying config.php...");
			if (!copy("$old_dir/config.php", "$work_dir/config.php")) {
				array_push($log, "Unable to copy config.php to $work_dir.");
				$stop = true; break;
			}

			array_push($log, "Cleaning up...");
			unlink($tmp_file);

			array_push($log, "Fixing permissions...");

			$directories = array(
				CACHE_DIR,
				CACHE_DIR . "/export",
				CACHE_DIR . "/images",
				CACHE_DIR . "/simplepie",
				ICONS_DIR,
				LOCK_DIRECTORY);

			foreach ($directories as $dir) {
				array_push($log, "-> $dir");
				chmod($dir, 0777);
			}

			array_push($log, "Upgrade completed.");
			array_push($log, "Your old tt-rss directory is saved at $old_dir. ".
				"Please migrate locally modified files (if any) and remove it.");
			array_push($log, "You might need to re-enter current directory in shell to see new files.");

			$stop = true;
			break;
		default:
			$stop = true;
		}

		return array("step" => $step, "stop" => $stop, "params" => $params, "log" => $log);
	}

	function update_self_cli($link, $force = false) {
		$step = 0;
		$stop = false;
		$params = array();

		while (!$stop) {
			$rc = $this->update_self_step($link, $step, $params, $force);

			$params = $rc['params'];
			$stop = $rc['stop'];

			foreach ($rc['log'] as $line) {
				_debug($line);
			}
			++$step;
		}
	}

	function update_self($args) {
		_debug("Warning: self-updating is experimental. Use at your own risk.");
		_debug("Please backup your tt-rss directory before continuing. Your database will not be modified.");
		_debug("Type 'yes' to continue.");

		if (read_stdin() != 'yes')
			exit;

		$this->update_self_cli($link, in_array("-force", $args));
	}

	function get_prefs_js() {
		return file_get_contents(dirname(__FILE__) . "/updater.js");
	}

	function hook_prefs_tab($args) {
		if ($args != "prefPrefs") return;

		if (($_SESSION["access_level"] >= 10 || SINGLE_USER_MODE) && CHECK_FOR_NEW_VERSION) {
			print "<div dojoType=\"dijit.layout.AccordionPane\" title=\"".__('Update Tiny Tiny RSS')."\">";

			if ($_SESSION["pref_last_version_check"] + 86400 + rand(-1000, 1000) < time()) {
				$_SESSION["version_data"] = @check_for_update($this->link);
				$_SESSION["pref_last_version_check"] = time();
			}

			if (is_array($_SESSION["version_data"])) {
				$version = $_SESSION["version_data"]["version"];
				print_notice(T_sprintf("New version of Tiny Tiny RSS is available (%s).", "<b>$version</b>"));

				print "<p><button dojoType=\"dijit.form.Button\" onclick=\"return updateSelf()\">".
					__('Update Tiny Tiny RSS')."</button></p>";

			} else {
				print_notice(__("Your Tiny Tiny RSS installation is up to date."));
			}

			print "</div>"; #pane
		}

	function updateSelf() {
		print "<form style='display : block' name='self_update_form' id='self_update_form'>";

		print "<div class='error'>".__("Do not close this dialog until updating is finished. Backup your tt-rss directory before continuing.")."</div>";

		print "<ul class='selfUpdateList' id='self_update_log'>";
		print "<li>" . __("Ready to update.") . "</li>";
		print "</ul>";

		print "<div class='dlgButtons'>";
		print "<button id=\"self_update_start_btn\" dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('updateSelfDlg').start()\" >".
			__("Start update")."</button>";
		print "<button id=\"self_update_stop_btn\" onclick=\"return dijit.byId('updateSelfDlg').close()\" dojoType=\"dijit.form.Button\">".
			__("Close this window")."</button>";
		print "</div>";
		print "</form>";
	}

	function performUpdate() {
		$step = (int) $_REQUEST["step"];
		$params = json_decode($_REQUEST["params"], true);
		$force = (bool) $_REQUEST["force"];

		if (($_SESSION["access_level"] >= 10 || SINGLE_USER_MODE) && CHECK_FOR_NEW_VERSION) {
			print	json_encode($this->update_self_step($this->link, $step, $params, $force));
		}
	}


	}
}
?>

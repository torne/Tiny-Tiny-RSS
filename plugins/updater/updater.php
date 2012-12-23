<?php
class Updater extends Plugin {

	private $link;
	private $host;

	function __construct($host) {
		$this->link = $host->get_link();
		$this->host = $host;

		$host->add_hook($host::HOOK_PREFS_TAB, $this);
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
			include "update_self.php";

			print	json_encode(update_self_step($this->link, $step, $params, $force));
		}
	}


	}
}
?>

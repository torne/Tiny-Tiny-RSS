<?php
class Mark_Button extends Plugin {
	private $host;

	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_ARTICLE_BUTTON, $this);
	}

	function about() {
		return array(1.0,
			"Bottom un/star button for the combined mode",
			"fox");
	}

	function hook_article_button($line) {
		$marked_pic = "";
		$id = $line["id"];

		if (get_pref("COMBINED_DISPLAY_MODE")) {
			if (sql_bool_to_bool($line["marked"])) {
				$marked_pic = "<img
					src=\"images/mark_set.png\"
					class=\"markedPic\" alt=\"Unstar article\"
					onclick='toggleMark($id)'>";
			} else {
				$marked_pic = "<img
					src=\"images/mark_unset.svg\"
					class=\"markedPic\" alt=\"Star article\"
					onclick='toggleMark($id)'>";
			}
		}

		return $marked_pic;
	}

	function api_version() {
		return 2;
	}

}
?>

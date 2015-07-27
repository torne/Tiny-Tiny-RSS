<?php
class Close_Button extends Plugin {
	private $host;

	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_ARTICLE_BUTTON, $this);
	}

	function about() {
		return array(1.0,
			"Adds a button to close article panel",
			"fox");
	}

	function hook_article_button($line) {
		if (!get_pref("COMBINED_DISPLAY_MODE")) {
			$rv = "<img src=\"plugins/close_button/button.png\"
				class='tagsPic' style=\"cursor : pointer\"
				onclick=\"closeArticlePanel()\"
				title='".__('Close article')."'>";
		}

		return $rv;
	}

	function api_version() {
		return 2;
	}

}
?>

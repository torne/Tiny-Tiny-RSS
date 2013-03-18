<?php
class GoogleReaderTheme extends Plugin {

	private $link;
	private $host;

	function about() {
		return array(1.0,
			"Make tt-rss look similar to Google Reader",
			"levito");
	}

	function init($host) {
		$this->link = $host->get_link();
		$this->host = $host;

		if ($_SESSION["uid"]) {
			// force-enable combined mode
			set_pref($this->link, "COMBINED_DISPLAY_MODE", true, $_SESSION["uid"]);
		}
	}

	function get_css() {
		return file_get_contents(dirname(__FILE__) . "/init.css");
	}
}
?>

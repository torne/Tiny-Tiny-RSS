<?php
class Plugin {
	private $link;
	private $host;

	function init($host) {
		$this->link = $host->get_link();
		$this->host = $host;
	}

	function about() {
		// version, name, description, author, is_system
		return array(1.0, "plugin", "No description", "No author", false);
	}

	function get_js() {
		return "";
	}

	function get_prefs_js() {
		return "";
	}
}
?>

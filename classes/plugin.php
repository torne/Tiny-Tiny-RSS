<?php
class Plugin {
	private $dbh;
	private $host;

	const API_VERSION_COMPAT = 1;

	function init($host) {
		$this->dbh = $host->get_dbh();
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

	function api_version() {
		return Plugin::API_VERSION_COMPAT;
	}
}
?>

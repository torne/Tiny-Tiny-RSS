<?php
class DbUpdater {

	private $dbh;
	private $db_type;
	private $need_version;

	function __construct($dbh, $db_type, $need_version) {
		$this->dbh = $dbh;
		$this->db_type = $db_type;
		$this->need_version = (int) $need_version;
	}

	function getSchemaVersion() {
		$result = db_query("SELECT schema_version FROM ttrss_version");
		return (int) db_fetch_result($result, 0, "schema_version");
	}

	function isUpdateRequired() {
		return $this->getSchemaVersion() < $this->need_version;
	}

	function getSchemaLines($version) {
		$filename = "schema/versions/".$this->db_type."/$version.sql";

		if (file_exists($filename)) {
			return explode(";", preg_replace("/[\r\n]/", "", file_get_contents($filename)));
		} else {
			return false;
		}
	}

	function performUpdateTo($version) {
		if ($this->getSchemaVersion() == $version - 1) {

			$lines = $this->getSchemaLines($version);

			if (is_array($lines)) {

				db_query("BEGIN");

				foreach ($lines as $line) {
					if (strpos($line, "--") !== 0 && $line) {
						db_query($line);
					}
				}

				$db_version = $this->getSchemaVersion();

				if ($db_version == $version) {
					db_query("COMMIT");
					return true;
				} else {
					db_query("ROLLBACK");
					return false;
				}
			} else {
				return true;
			}
		} else {
			return false;
		}
	}

} ?>

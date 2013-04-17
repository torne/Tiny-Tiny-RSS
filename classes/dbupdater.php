<?php
class DbUpdater {

	private $dbh;
	private $$this->dbh->type;
	private $need_version;

	function __construct($dbh, $$this->dbh->type, $need_version) {
		$this->dbh = $dbh;
		$this->$this->dbh->type = $db_type;
		$this->need_version = (int) $need_version;
	}

	function getSchemaVersion() {
		$result = $this->dbh->query("SELECT schema_version FROM ttrss_version");
		return (int) $this->dbh->fetch_result($result, 0, "schema_version");
	}

	function isUpdateRequired() {
		return $this->getSchemaVersion() < $this->need_version;
	}

	function getSchemaLines($version) {
		$filename = "schema/versions/".$this->$this->dbh->type."/$version.sql";

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

				$this->dbh->query("BEGIN");

				foreach ($lines as $line) {
					if (strpos($line, "--") !== 0 && $line) {
						$this->dbh->query($line);
					}
				}

				$$this->dbh->version = $this->getSchemaVersion();

				if ($$this->dbh->version == $version) {
					$this->dbh->query("COMMIT");
					return true;
				} else {
					$this->dbh->query("ROLLBACK");
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

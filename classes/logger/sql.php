<?php
class Logger_SQL {

	private $link;

	function __construct() {
		$this->link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
	}

	function log_error($errno, $errstr, $file, $line, $context) {

		if ($errno == E_NOTICE) return false;

		if ($this->link) {
			$errno = db_escape_string($this->link, $errno);
			$errstr = db_escape_string($this->link, $errstr);
			$file = db_escape_string($this->link, $file);
			$line = db_escape_string($this->link, $line);
			$context = db_escape_string($this->link, json_encode($context));

			$owner_uid = $_SESSION["uid"] ? $_SESSION["uid"] : "NULL";

			$result = db_query($this->link,
				"INSERT INTO ttrss_error_log
				(errno, errstr, filename, lineno, context, owner_uid, created_at) VALUES
				($errno, '$errstr', '$file', '$line', '$context', $owner_uid, NOW())");

			return db_affected_rows($this->link, $result) != 0;

		}
		return false;
	}

}
?>

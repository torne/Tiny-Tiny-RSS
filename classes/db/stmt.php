<?php
class Db_Stmt {
	private $stmt;
	private $cache;

	function __construct($stmt) {
		$this->stmt = $stmt;
		$this->cache = false;
	}

	function fetch_result($row, $param) {
		if (!$this->cache) {
			$this->cache = $this->stmt->fetchAll();
		}

		if (isset($this->cache[$row])) {
			return $this->cache[$row][$param];
		} else {
			user_error("Unable to jump to row $row", E_USER_WARNING);
			return false;
		}
	}

	function rowCount() {
		return $this->stmt->rowCount();
	}

	function fetch() {
		return $this->stmt->fetch();
	}
}
?>

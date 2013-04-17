<?php
class Db implements IDb {
	private static $instance;
	private $adapter;

	private function __construct() {
		switch (DB_TYPE) {
		case "mysql":
			$this->adapter = new Db_Mysql();
			break;
		case "pgsql":
			$this->adapter = new Db_Pgsql();
			break;
		default:
			die("Unknown DB_TYPE: " . DB_TYPE);
		}

		$this->adapter->connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
		$this->adapter->init();
	}

	private function __clone() {
		//
	}

	public static function get() {
		if (self::$instance == null)
			self::$instance = new self();

		return self::$instance;
	}

	static function quote($str){
		return("'$str'");
	}

	function init() {
		//
	}

	function connect($host, $user, $pass, $db, $port) {
		//return $this->adapter->connect($host, $user, $pass, $db, $port);
	}

	function escape_string($s, $strip_tags = true) {
		return $this->adapter->escape_string($s, $strip_tags);
	}

	function query($query, $die_on_error = true) {
		return $this->adapter->query($query, $die_on_error);
	}

	function fetch_assoc($result) {
		return $this->adapter->fetch_assoc($result);
	}

	function num_rows($result) {
		return $this->adapter->num_rows($result);
	}

	function fetch_result($result, $row, $param) {
		return $this->adapter->fetch_result($result, $row, $param);
	}

	function close() {
		return $this->adapter->close();
	}

	function affected_rows($result) {
		return $this->adapter->affected_rows($result);
	}

	function last_error() {
		return $this->adapter->last_error();
	}

}
?>

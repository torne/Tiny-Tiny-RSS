<?php
class Db_PDO implements IDb {
	private $pdo;

	function connect($host, $user, $pass, $db, $port) {
		$connstr = DB_TYPE . ":host=$host;dbname=$db";

		if (DB_TYPE == "mysql") $connstr .= ";charset=utf8";

		try {
			$this->pdo = new PDO($connstr, $user, $pass);
			$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$this->init();
		} catch (PDOException $e) {
			die($e->getMessage());
		}

		return $this->pdo;
	}

	function escape_string($s, $strip_tags = true) {
		if ($strip_tags) $s = strip_tags($s);

		$qs = $this->pdo->quote($s);

		return mb_substr($qs, 1, mb_strlen($qs)-2);
	}

	function query($query, $die_on_error = true) {
		try {
			return new Db_Stmt($this->pdo->query($query));
		} catch (PDOException $e) {
			user_error($e->getMessage(), $die_on_error ? E_USER_ERROR : E_USER_WARNING);
		}
	}

	function fetch_assoc($result) {
		try {
			if ($result) {
				return $result->fetch();
			} else {
				return null;
			}
		} catch (PDOException $e) {
			user_error($e->getMessage(), E_USER_WARNING);
		}
	}

	function num_rows($result) {
		try {
			if ($result) {
				return $result->rowCount();
			} else {
				return false;
			}
		} catch (PDOException $e) {
			user_error($e->getMessage(), E_USER_WARNING);
		}
	}

	function fetch_result($result, $row, $param) {
		return $result->fetch_result($row, $param);
	}

	function close() {
		$this->pdo = null;
	}

	function affected_rows($result) {
		try {
			if ($result) {
				return $result->rowCount();
			} else {
				return null;
			}
		} catch (PDOException $e) {
			user_error($e->getMessage(), E_USER_WARNING);
		}
	}

	function last_error() {
		return join(" ", $this->pdo->errorInfo());
	}

	function init() {
		switch (DB_TYPE) {
		case "pgsql":
			$this->query("set client_encoding = 'UTF-8'");
			$this->query("set datestyle = 'ISO, european'");
			$this->query("set TIME ZONE 0");
            return;
		case "mysql":
			$this->query("SET time_zone = '+0:0'");
			return;
		}

		return true;
	}

}
?>

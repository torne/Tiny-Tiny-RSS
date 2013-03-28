<?php
	// Original from http://www.daniweb.com/code/snippet43.html

	require_once "config.php";
	require_once "db.php";

	$session_expire = SESSION_EXPIRE_TIME; //seconds
	$session_name = (!defined('TTRSS_SESSION_NAME')) ? "ttrss_sid" : TTRSS_SESSION_NAME;

	if (@$_SERVER['HTTPS'] == "on") {
		$session_name .= "_ssl";
		ini_set("session.cookie_secure", true);
	}

	ini_set("session.gc_probability", 50);
	ini_set("session.name", $session_name);
	ini_set("session.use_only_cookies", true);
	ini_set("session.gc_maxlifetime", SESSION_EXPIRE_TIME);

	function ttrss_open ($s, $n) {

		global $session_connection;

		$session_connection = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

		return true;
	}

	function ttrss_read ($id){

		global $session_connection,$session_read;

		$query = "SELECT data FROM ttrss_sessions WHERE id='$id'";

		$res = db_query($session_connection, $query);

		if (db_num_rows($res) != 1) {
		 	return "";
		} else {
			$session_read = db_fetch_assoc($res);
			$session_read["data"] = base64_decode($session_read["data"]);
			return $session_read["data"];
		}
	}

	function ttrss_write ($id, $data) {

		if (! $data) {
			return false;
		}

		global $session_connection, $session_read, $session_expire;

		$expire = time() + $session_expire;

		$data = db_escape_string($session_connection, base64_encode($data), false);

		if ($session_read) {
		 	$query = "UPDATE ttrss_sessions SET data='$data',
					expire='$expire' WHERE id='$id'";
		} else {
		 	$query = "INSERT INTO ttrss_sessions (id, data, expire)
					VALUES ('$id', '$data', '$expire')";
		}

		db_query($session_connection, $query);
		return true;
	}

	function ttrss_close () {

		global $session_connection;

		//db_close($session_connection);

		return true;
	}

	function ttrss_destroy ($id) {

		global $session_connection;

		$query = "DELETE FROM ttrss_sessions WHERE id = '$id'";

		db_query($session_connection, $query);

		return true;
	}

	function ttrss_gc ($expire) {

		global $session_connection;

		$query = "DELETE FROM ttrss_sessions WHERE expire < " . time();

		db_query($session_connection, $query);
	}

	if (!SINGLE_USER_MODE /* && DB_TYPE == "pgsql" */) {
		session_set_save_handler("ttrss_open",
			"ttrss_close", "ttrss_read", "ttrss_write",
			"ttrss_destroy", "ttrss_gc");
	}

	session_set_cookie_params(SESSION_COOKIE_LIFETIME);

	if (!defined('TTRSS_SESSION_NAME') || TTRSS_SESSION_NAME != 'ttrss_api_sid') {
		if ($_COOKIE[$session_name]) {
			@session_start();
		}
	}
?>

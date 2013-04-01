<?php
	// Original from http://www.daniweb.com/code/snippet43.html

	require_once "config.php";
	require_once "db.php";
	require_once "lib/accept-to-gettext.php";
	require_once "lib/gettext/gettext.inc";

	$session_expire = max(SESSION_COOKIE_LIFETIME, 86400);
	$session_name = (!defined('TTRSS_SESSION_NAME')) ? "ttrss_sid" : TTRSS_SESSION_NAME;

	if (@$_SERVER['HTTPS'] == "on") {
		$session_name .= "_ssl";
		ini_set("session.cookie_secure", true);
	}

	ini_set("session.gc_probability", 50);
	ini_set("session.name", $session_name);
	ini_set("session.use_only_cookies", true);
	ini_set("session.gc_maxlifetime", $session_expire);

	function session_get_schema_version($link, $nocache = false) {
		global $schema_version;

		if (!$schema_version) {
			$result = db_query($link, "SELECT schema_version FROM ttrss_version");
			$version = db_fetch_result($result, 0, "schema_version");
			$schema_version = $version;
			return $version;
		} else {
			return $schema_version;
		}
	}

	function validate_session($link) {
		if (SINGLE_USER_MODE) return true;

		$check_ip = $_SESSION['ip_address'];

		switch (SESSION_CHECK_ADDRESS) {
		case 0:
			$check_ip = '';
			break;
		case 1:
			$check_ip = substr($check_ip, 0, strrpos($check_ip, '.')+1);
			break;
		case 2:
			$check_ip = substr($check_ip, 0, strrpos($check_ip, '.'));
			$check_ip = substr($check_ip, 0, strrpos($check_ip, '.')+1);
			break;
		};

		if ($check_ip && strpos($_SERVER['REMOTE_ADDR'], $check_ip) !== 0) {
			$_SESSION["login_error_msg"] =
				__("Session failed to validate (incorrect IP)");
			return false;
		}

		if ($_SESSION["ref_schema_version"] != session_get_schema_version($link, true))
			return false;

		if (sha1($_SERVER['HTTP_USER_AGENT']) != $_SESSION["user_agent"])
			return false;

		if ($_SESSION["uid"]) {
			$result = db_query($link,
				"SELECT pwd_hash FROM ttrss_users WHERE id = '".$_SESSION["uid"]."'");

			// user not found
			if (db_num_rows($result) == 0) {
				return false;
			} else {
				$pwd_hash = db_fetch_result($result, 0, "pwd_hash");

				if ($pwd_hash != $_SESSION["pwd_hash"]) {
					return false;
				}
			}
		}

/*		if ($_SESSION["cookie_lifetime"] && $_SESSION["uid"]) {

			//print_r($_SESSION);

			if (time() > $_SESSION["cookie_lifetime"]) {
				return false;
			}
		} */

		return true;
	}


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

	if (!defined('TTRSS_SESSION_NAME') || TTRSS_SESSION_NAME != 'ttrss_api_sid') {
		if (isset($_COOKIE[$session_name])) {
			@session_start();

			if (!isset($_SESSION["uid"]) || !$_SESSION["uid"] || !validate_session($session_connection)) {
				session_destroy();
			   setcookie(session_name(), '', time()-42000, '/');
			}
		}
	}
?>

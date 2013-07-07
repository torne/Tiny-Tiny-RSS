<?php
	// Original from http://www.daniweb.com/code/snippet43.html

	require_once "config.php";
	require_once "classes/db.php";
	require_once "autoload.php";
	require_once "errorhandler.php";
	require_once "lib/accept-to-gettext.php";
	require_once "lib/gettext/gettext.inc";
	require_once "version.php";

	$session_expire = max(SESSION_COOKIE_LIFETIME, 86400);
	$session_name = (!defined('TTRSS_SESSION_NAME')) ? "ttrss_sid" : TTRSS_SESSION_NAME;

	if (@$_SERVER['HTTPS'] == "on") {
		$session_name .= "_ssl";
		ini_set("session.cookie_secure", true);
	}

	ini_set("session.gc_probability", 75);
	ini_set("session.name", $session_name);
	ini_set("session.use_only_cookies", true);
	ini_set("session.gc_maxlifetime", $session_expire);
	ini_set("session.cookie_lifetime", min(0, SESSION_COOKIE_LIFETIME));

	function session_get_schema_version($nocache = false) {
		global $schema_version;

		if (!$schema_version) {
			$result = Db::get()->query("SELECT schema_version FROM ttrss_version");
			$version = Db::get()->fetch_result($result, 0, "schema_version");
			$schema_version = $version;
			return $version;
		} else {
			return $schema_version;
		}
	}

	function validate_session() {
		if (SINGLE_USER_MODE) return true;

		if (VERSION_STATIC != $_SESSION["version"]) return false;

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

		if ($_SESSION["ref_schema_version"] != session_get_schema_version(true)) {
			$_SESSION["login_error_msg"] =
				__("Session failed to validate (schema version changed)");
			return false;
		}

		if (sha1($_SERVER['HTTP_USER_AGENT']) != $_SESSION["user_agent"]) {
			$_SESSION["login_error_msg"] =
				__("Session failed to validate (user agent changed)");
			return false;
		}

		if ($_SESSION["uid"]) {
			$result = Db::get()->query(
				"SELECT pwd_hash FROM ttrss_users WHERE id = '".$_SESSION["uid"]."'");

			// user not found
			if (Db::get()->num_rows($result) == 0) {

				$_SESSION["login_error_msg"] =
					__("Session failed to validate (user not found)");

				return false;
			} else {
				$pwd_hash = Db::get()->fetch_result($result, 0, "pwd_hash");

				if ($pwd_hash != $_SESSION["pwd_hash"]) {

					$_SESSION["login_error_msg"] =
						__("Session failed to validate (password changed)");

					return false;
				}
			}
		}

		return true;
	}


	function ttrss_open ($s, $n) {
		return true;
	}

	function ttrss_read ($id){
		global $session_expire;

		$res = Db::get()->query("SELECT data FROM ttrss_sessions WHERE id='$id'");

		if (Db::get()->num_rows($res) != 1) {

			$expire = time() + $session_expire;

			Db::get()->query("INSERT INTO ttrss_sessions (id, data, expire)
					VALUES ('$id', '', '$expire')");

		 	return "";
		} else {
			return base64_decode(Db::get()->fetch_result($res, 0, "data"));
		}

	}

	function ttrss_write ($id, $data) {
		global $session_expire;

		$data = base64_encode($data);
		$expire = time() + $session_expire;

		Db::get()->query("UPDATE ttrss_sessions SET data='$data', expire='$expire' WHERE id='$id'");

		return true;
	}

	function ttrss_close () {
		return true;
	}

	function ttrss_destroy($id) {
		Db::get()->query("DELETE FROM ttrss_sessions WHERE id = '$id'");

		return true;
	}

	function ttrss_gc ($expire) {
		Db::get()->query("DELETE FROM ttrss_sessions WHERE expire < " . time());
	}

	if (!SINGLE_USER_MODE /* && DB_TYPE == "pgsql" */) {
		session_set_save_handler("ttrss_open",
			"ttrss_close", "ttrss_read", "ttrss_write",
			"ttrss_destroy", "ttrss_gc");
		register_shutdown_function('session_write_close');
	}

	if (!defined('NO_SESSION_AUTOSTART')) {
		if (isset($_COOKIE[session_name()])) {
			@session_start();
		}
	}
?>

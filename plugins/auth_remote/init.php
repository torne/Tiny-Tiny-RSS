<?php
class Auth_Remote extends Plugin implements IAuthModule {

	private $host;
	private $base;

	function about() {
		return array(1.0,
			"Authenticates against remote password (e.g. supplied by Apache)",
			"fox",
			true);
	}

	function init($host) {
		$this->host = $host;
		$this->base = new Auth_Base();

		$host->add_hook($host::HOOK_AUTH_USER, $this);
	}

	function get_login_by_ssl_certificate() {
		$cert_serial = db_escape_string(get_ssl_certificate_id());

		if ($cert_serial) {
			$result = db_query("SELECT login FROM ttrss_user_prefs, ttrss_users
				WHERE pref_name = 'SSL_CERT_SERIAL' AND value = '$cert_serial' AND
				owner_uid = ttrss_users.id");

			if (db_num_rows($result) != 0) {
				return db_escape_string(db_fetch_result($result, 0, "login"));
			}
		}

		return "";
	}


	function authenticate($login, $password) {
		$try_login = db_escape_string($_SERVER["REMOTE_USER"]);

		// php-cgi
		if (!$try_login) $try_login = db_escape_string($_SERVER["REDIRECT_REMOTE_USER"]);

		if (!$try_login) $try_login = $this->get_login_by_ssl_certificate();
#	  	if (!$try_login) $try_login = "test_qqq";

		if ($try_login) {
			$user_id = $this->base->auto_create_user($try_login);

			if ($user_id) {
				$_SESSION["fake_login"] = $try_login;
				$_SESSION["fake_password"] = "******";
				$_SESSION["hide_hello"] = true;
				$_SESSION["hide_logout"] = true;

				// LemonLDAP can send user informations via HTTP HEADER
				if (defined('AUTH_AUTO_CREATE') && AUTH_AUTO_CREATE){
					// update user name
					$fullname = $_SERVER['HTTP_USER_NAME'] ? $_SERVER['HTTP_USER_NAME'] : $_SERVER['AUTHENTICATE_CN'];
					if ($fullname){
						$fullname = db_escape_string($fullname);
						db_query("UPDATE ttrss_users SET full_name = '$fullname' WHERE id = " .
							$user_id);
					}
					// update user mail
					$email = $_SERVER['HTTP_USER_MAIL'] ? $_SERVER['HTTP_USER_MAIL'] : $_SERVER['AUTHENTICATE_MAIL'];
					if ($email){
						$email = db_escape_string($email);
						db_query("UPDATE ttrss_users SET email = '$email' WHERE id = " .
							$user_id);
					}
				}

				return $user_id;
			}
		}

		return false;
	}
}

?>

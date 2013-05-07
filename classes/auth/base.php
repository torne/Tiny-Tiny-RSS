<?php
class Auth_Base {
	private $dbh;

	function __construct() {
		$this->dbh = Db::get();
	}

	function check_password($owner_uid, $password) {
		return false;
	}

	function authenticate($login, $password) {
		return false;
	}

	// Auto-creates specified user if allowed by system configuration
	// Can be used instead of find_user_by_login() by external auth modules
	function auto_create_user($login, $password) {
		if ($login && defined('AUTH_AUTO_CREATE') && AUTH_AUTO_CREATE) {
			$user_id = $this->find_user_by_login($login);

			if (!$user_id) {
				$login = $this->dbh->escape_string($login);
				$salt = substr(bin2hex(get_random_bytes(125)), 0, 250);
				$pwd_hash = encrypt_password($password, $salt, true);

				$query = "INSERT INTO ttrss_users
						(login,access_level,last_login,created,pwd_hash,salt)
						VALUES ('$login', 0, null, NOW(), '$pwd_hash','$salt')";

				$this->dbh->query($query);

				return $this->find_user_by_login($login);

			} else {
				return $user_id;
			}
		}

		return $this->find_user_by_login($login);
	}

	function find_user_by_login($login) {
		$login = $this->dbh->escape_string($login);

		$result = $this->dbh->query("SELECT id FROM ttrss_users WHERE
			login = '$login'");

		if ($this->dbh->num_rows($result) > 0) {
			return $this->dbh->fetch_result($result, 0, "id");
		} else {
			return false;
		}

	}
}

?>

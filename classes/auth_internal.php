<?php
class Auth_Internal extends Auth_Base {

	function authenticate($login, $password) {

		$pwd_hash1 = encrypt_password($password);
		$pwd_hash2 = encrypt_password($password, $login);
		$login = db_escape_string($login);

		if (get_schema_version($this->link) > 87) {

			$result = db_query($this->link, "SELECT salt FROM ttrss_users WHERE
				login = '$login'");

			if (db_num_rows($result) != 1) {
				return false;
			}

			$salt = db_fetch_result($result, 0, "salt");

			if ($salt == "") {

				$query = "SELECT id
	            FROM ttrss_users WHERE
					login = '$login' AND (pwd_hash = '$pwd_hash1' OR
					pwd_hash = '$pwd_hash2')";

				// verify and upgrade password to new salt base

				$result = db_query($this->link, $query);

				if (db_num_rows($result) == 1) {
					// upgrade password to MODE2

					$salt = substr(bin2hex(get_random_bytes(125)), 0, 250);
					$pwd_hash = encrypt_password($password, $salt, true);

					db_query($this->link, "UPDATE ttrss_users SET
						pwd_hash = '$pwd_hash', salt = '$salt' WHERE login = '$login'");

					$query = "SELECT id
		            FROM ttrss_users WHERE
						login = '$login' AND pwd_hash = '$pwd_hash'";

				} else {
					return false;
				}

			} else {

				$pwd_hash = encrypt_password($password, $salt, true);

				$query = "SELECT id
		         FROM ttrss_users WHERE
					login = '$login' AND pwd_hash = '$pwd_hash'";

			}

		} else {
			$query = "SELECT id
	         FROM ttrss_users WHERE
				login = '$login' AND (pwd_hash = '$pwd_hash1' OR
					pwd_hash = '$pwd_hash2')";
		}

		$result = db_query($this->link, $query);

		if (db_num_rows($result) == 1) {
			return db_fetch_result($result, 0, "id");
		}

		return false;
	}
}
?>

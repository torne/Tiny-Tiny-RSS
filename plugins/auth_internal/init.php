<?php
class Auth_Internal extends Plugin implements IAuthModule {
	private $host;

	function about() {
		return array(1.0,
			"Authenticates against internal tt-rss database",
			"fox",
			true);
	}

	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_AUTH_USER, $this);
	}

	function authenticate($login, $password) {

		$pwd_hash1 = encrypt_password($password);
		$pwd_hash2 = encrypt_password($password, $login);
		$login = db_escape_string($login);
		$otp = db_escape_string($_REQUEST["otp"]);

		if (get_schema_version() > 96) {
			if (!defined('AUTH_DISABLE_OTP') || !AUTH_DISABLE_OTP) {

				$result = db_query("SELECT otp_enabled,salt FROM ttrss_users WHERE
					login = '$login'");

				if (db_num_rows($result) > 0) {

					require_once "lib/otphp/vendor/base32.php";
					require_once "lib/otphp/lib/otp.php";
					require_once "lib/otphp/lib/totp.php";

					$base32 = new Base32();

					$otp_enabled = sql_bool_to_bool(db_fetch_result($result, 0, "otp_enabled"));
					$secret = $base32->encode(sha1(db_fetch_result($result, 0, "salt")));

					$topt = new \OTPHP\TOTP($secret);
					$otp_check = $topt->now();

					if ($otp_enabled) {
						if ($otp) {
							if ($otp != $otp_check) {
								return false;
							}
						} else {
							$return = urlencode($_REQUEST["return"]);
							?><html>
								<head><title>Tiny Tiny RSS</title></head>
								<?php echo stylesheet_tag("css/utility.css") ?>
							<body class="otp"><div class="content">
							<form action="public.php?return=<?php echo $return ?>"
									method="POST" class="otpform">
								<input type="hidden" name="op" value="login">
								<input type="hidden" name="login" value="<?php echo htmlspecialchars($login) ?>">
								<input type="hidden" name="password" value="<?php echo htmlspecialchars($password) ?>">

								<label><?php echo __("Please enter your one time password:") ?></label>
								<input autocomplete="off" size="6" name="otp" value=""/>
								<input type="submit" value="Continue"/>
							</form></div>
							<script type="text/javascript">
								document.forms[0].otp.focus();
							</script>
							<?php
							exit;
						}
					}
				}
			}
		}

		if (get_schema_version() > 87) {

			$result = db_query("SELECT salt FROM ttrss_users WHERE
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

				$result = db_query($query);

				if (db_num_rows($result) == 1) {
					// upgrade password to MODE2

					$salt = substr(bin2hex(get_random_bytes(125)), 0, 250);
					$pwd_hash = encrypt_password($password, $salt, true);

					db_query("UPDATE ttrss_users SET
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

		$result = db_query($query);

		if (db_num_rows($result) == 1) {
			return db_fetch_result($result, 0, "id");
		}

		return false;
	}

	function check_password($owner_uid, $password) {
		$owner_uid = db_escape_string($owner_uid);

		$result = db_query("SELECT salt,login FROM ttrss_users WHERE
			id = '$owner_uid'");

		$salt = db_fetch_result($result, 0, "salt");
		$login = db_fetch_result($result, 0, "login");

		if (!$salt) {
			$password_hash1 = encrypt_password($password);
			$password_hash2 = encrypt_password($password, $login);

			$query = "SELECT id FROM ttrss_users WHERE
				id = '$owner_uid' AND (pwd_hash = '$password_hash1' OR
				pwd_hash = '$password_hash2')";

		} else {
			$password_hash = encrypt_password($password, $salt, true);

			$query = "SELECT id FROM ttrss_users WHERE
				id = '$owner_uid' AND pwd_hash = '$password_hash'";
		}

		$result = db_query($query);

		return db_num_rows($result) != 0;
	}

	function change_password($owner_uid, $old_password, $new_password) {
		$owner_uid = db_escape_string($owner_uid);

		if ($this->check_password($owner_uid, $old_password)) {

			$new_salt = substr(bin2hex(get_random_bytes(125)), 0, 250);
			$new_password_hash = encrypt_password($new_password, $new_salt, true);

			db_query("UPDATE ttrss_users SET
				pwd_hash = '$new_password_hash', salt = '$new_salt', otp_enabled = false
					WHERE id = '$owner_uid'");

			$_SESSION["pwd_hash"] = $new_password_hash;

			return __("Password has been changed.");
		} else {
			return "ERROR: ".__('Old password is incorrect.');
		}
	}

	function api_version() {
		return 2;
	}

}
?>

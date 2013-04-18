<?php
class Db_Prefs {
	private $dbh;
	private static $instance;
	private $cache;

	function __construct() {
		$this->dbh = Db::get();
		$this->cache = array();

		if ($_SESSION["uid"]) $this->cache();
	}

	private function __clone() {
		//
	}

	public static function get() {
		if (self::$instance == null)
			self::$instance = new self();

		return self::$instance;
	}

	function cache() {
		$profile = false;

		$user_id = $_SESSION["uid"];
		@$profile = $_SESSION["profile"];

		if ($profile) {
			$profile_qpart = "profile = '$profile' AND";
		} else {
			$profile_qpart = "profile IS NULL AND";
		}

		if (get_schema_version() < 63) $profile_qpart = "";

		$result = db_query("SELECT
			value,ttrss_prefs_types.type_name as type_name,ttrss_prefs.pref_name AS pref_name
			FROM
				ttrss_user_prefs,ttrss_prefs,ttrss_prefs_types
			WHERE
				$profile_qpart
				ttrss_prefs.pref_name NOT LIKE '_MOBILE%' AND
				ttrss_prefs_types.id = type_id AND
				owner_uid = '$user_id' AND
				ttrss_user_prefs.pref_name = ttrss_prefs.pref_name");

		while ($line = db_fetch_assoc($result)) {
			if ($user_id == $_SESSION["uid"]) {
				$pref_name = $line["pref_name"];

				$this->cache[$pref_name]["type"] = $line["type_name"];
				$this->cache[$pref_name]["value"] = $line["value"];
			}
		}
	}

	function read($pref_name, $user_id = false, $die_on_error = false) {

		$pref_name = db_escape_string($pref_name);
		$profile = false;

		if (!$user_id) {
			$user_id = $_SESSION["uid"];
			@$profile = $_SESSION["profile"];
		} else {
			$user_id = sprintf("%d", $user_id);
		}

		if (isset($this->cache[$pref_name])) {
			$tuple = $this->cache[$pref_name];
			return $this->convert($tuple["value"], $tuple["type"]);
		}

		if ($profile) {
			$profile_qpart = "profile = '$profile' AND";
		} else {
			$profile_qpart = "profile IS NULL AND";
		}

		if (get_schema_version() < 63) $profile_qpart = "";

		$result = db_query("SELECT
			value,ttrss_prefs_types.type_name as type_name
			FROM
				ttrss_user_prefs,ttrss_prefs,ttrss_prefs_types
			WHERE
				$profile_qpart
				ttrss_user_prefs.pref_name = '$pref_name' AND
				ttrss_prefs_types.id = type_id AND
				owner_uid = '$user_id' AND
				ttrss_user_prefs.pref_name = ttrss_prefs.pref_name");

		if (db_num_rows($result) > 0) {
			$value = db_fetch_result($result, 0, "value");
			$type_name = db_fetch_result($result, 0, "type_name");

			if ($user_id == $_SESSION["uid"]) {
				$this->cache[$pref_name]["type"] = $type_name;
				$this->cache[$pref_name]["value"] = $value;
			}

			return $this->convert($value, $type_name);

		} else {
			user_error("Fatal error, unknown preferences key: $pref_name", $die_on_error ? E_USER_ERROR : E_USER_WARNING);
			return null;
		}
	}

	function convert($value, $type_name) {
		if ($type_name == "bool") {
			return $value == "true";
		} else if ($type_name == "integer") {
			return (int)$value;
		} else {
			return $value;
		}
	}

	function write($pref_name, $value, $user_id = false, $strip_tags = true) {
		$pref_name = db_escape_string($pref_name);
		$value = db_escape_string($value, $strip_tags);

		if (!$user_id) {
			$user_id = $_SESSION["uid"];
			@$profile = $_SESSION["profile"];
		} else {
			$user_id = sprintf("%d", $user_id);
			$prefs_cache = false;
		}

		if ($profile) {
			$profile_qpart = "AND profile = '$profile'";
		} else {
			$profile_qpart = "AND profile IS NULL";
		}

		if (get_schema_version() < 63) $profile_qpart = "";

		$type_name = "";
		$current_value = "";

		if (isset($this->cache[$pref_name])) {
			$type_name = $this->cache[$pref_name]["type"];
			$current_value = $this->cache[$pref_name]["value"];
		}

		if (!$type_name) {
			$result = db_query("SELECT type_name
				FROM ttrss_prefs,ttrss_prefs_types
				WHERE pref_name = '$pref_name' AND type_id = ttrss_prefs_types.id");

			if (db_num_rows($result) > 0)
				$type_name = db_fetch_result($result, 0, "type_name");
		} else if ($current_value == $value) {
			return;
		}

		if ($type_name) {
			if ($type_name == "bool") {
				if ($value == "1" || $value == "true") {
					$value = "true";
				} else {
					$value = "false";
				}
			} else if ($type_name == "integer") {
				$value = sprintf("%d", $value);
			}

			if ($pref_name == 'USER_TIMEZONE' && $value == '') {
				$value = 'UTC';
			}

			db_query("UPDATE ttrss_user_prefs SET
				value = '$value' WHERE pref_name = '$pref_name'
					$profile_qpart
					AND owner_uid = " . $_SESSION["uid"]);

			if ($user_id == $_SESSION["uid"]) {
				$this->cache[$pref_name]["type"] = $type_name;
				$this->cache[$pref_name]["value"] = $value;
			}
		}
	}

}
?>

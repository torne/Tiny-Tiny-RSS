<?php
	require_once "config.php";
	require_once "db.php";

	if (!defined('DISABLE_SESSIONS')) {
		if (!$_SESSION["prefs_cache"])
			$_SESSION["prefs_cache"] = array();
	}

	function get_pref($link, $pref_name, $user_id = false, $die_on_error = false) {

		$pref_name = db_escape_string($pref_name);
		$prefs_cache = true;

		if (!$user_id) {
			$user_id = $_SESSION["uid"];
			$profile = $_SESSION["profile"];
		} else {
			$user_id = sprintf("%d", $user_id);
			$prefs_cache = false;
		}

		if ($profile) {
			$profile_qpart = "profile = '$profile' AND";
		} else {
			$profile_qpart = "profile IS NULL AND";
		}

		if (get_schema_version($link) < 63) $profile_qpart = "";

		if ($prefs_cache && !defined('DISABLE_SESSIONS') && !SINGLE_USER_MODE) {	
			if ($_SESSION["prefs_cache"] && $_SESSION["prefs_cache"][$pref_name]) {
				$tuple = $_SESSION["prefs_cache"][$pref_name];
				return convert_pref_type($tuple["value"], $tuple["type"]);
			}
		}

		$result = db_query($link, "SELECT 
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

			if (!defined('DISABLE_SESSIONS') && !SINGLE_USER_MODE) {	
				if ($user_id = $_SESSION["uid"]) {
					$_SESSION["prefs_cache"][$pref_name]["type"] = $type_name;
					$_SESSION["prefs_cache"][$pref_name]["value"] = $value;
				}
			}

			return convert_pref_type($value, $type_name);
			
		} else {		
			if ($die_on_error) {
				die("Fatal error, unknown preferences key: $pref_name");
			} else {
				return null;
			}
		}
	}

	function convert_pref_type($value, $type_name) {
		if ($type_name == "bool") {			
			return $value == "true";				
		} else if ($type_name == "integer") {			
			return sprintf("%d", $value);				
		} else {
			return $value;
		}
	}

	function set_pref($link, $key, $value, $user_id = false) {
		$key = db_escape_string($key);
		$value = db_escape_string($value);

		if (!$user_id) {
			$user_id = $_SESSION["uid"];
			$profile = $_SESSION["profile"];
		} else {
			$user_id = sprintf("%d", $user_id);
			$prefs_cache = false;
		}

		if ($profile) {
			$profile_qpart = "AND profile = '$profile'";
		} else {
			$profile_qpart = "AND profile IS NULL";
		}

		if (get_schema_version($link) < 63) $profile_qpart = "";

		$result = db_query($link, "SELECT type_name 
			FROM ttrss_prefs,ttrss_prefs_types 
			WHERE pref_name = '$key' AND type_id = ttrss_prefs_types.id");

		if (db_num_rows($result) > 0) {

			$type_name = db_fetch_result($result, 0, "type_name");

			if ($type_name == "bool") {
				if ($value == "1" || $value == "true") {
					$value = "true";
				} else {
					$value = "false";
				}
			} else if ($type_name == "integer") {
				$value = sprintf("%d", $value);
			}

			if ($key == 'DEFAULT_ARTICLE_LIMIT' && $value == 0) {
				$value = 30;
			}

			db_query($link, "UPDATE ttrss_user_prefs SET 
				value = '$value' WHERE pref_name = '$key' 
					$profile_qpart
					AND owner_uid = " . $_SESSION["uid"]);

			$_SESSION["prefs_cache"] = array();

		}
	}
?>

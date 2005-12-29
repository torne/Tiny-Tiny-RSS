<?
	require_once "config.php";
	require_once "db.php";

	session_start();

	if (!$_SESSION["prefs_cache"])
		$_SESSION["prefs_cache"] = array();

	function get_pref($link, $pref_name, $user_id = false) {

		$pref_name = db_escape_string($pref_name);

		if (!$user_id) {
			$user_id = $_SESSION["uid"];
		} else {
			$user_id = sprintf("%d", $user_id);
			$prefs_cache = false;
		}
	
		if ($_SESSION["prefs_cache"] && $_SESSION["prefs_cache"][$pref_name]) {
			$tuple = $_SESSION["prefs_cache"][$pref_name];
			return convert_pref_type($tuple["value"], $tuple["type"]);
		}

		$result = db_query($link, "SELECT 
			value,ttrss_prefs_types.type_name as type_name 
			FROM 
				ttrss_user_prefs,ttrss_prefs,ttrss_prefs_types
			WHERE 
				ttrss_user_prefs.pref_name = '$pref_name' AND 
				ttrss_prefs_types.id = type_id AND
				owner_uid = '$user_id' AND
				ttrss_user_prefs.pref_name = ttrss_prefs.pref_name");

		if (db_num_rows($result) > 0) {
			$value = db_fetch_result($result, 0, "value");
			$type_name = db_fetch_result($result, 0, "type_name");

			if ($user_id = $_SESSION["uid"]) {
				$_SESSION["prefs_cache"][$pref_name]["type"] = $type_name;
				$_SESSION["prefs_cache"][$pref_name]["value"] = $value;
			}
			return convert_pref_type($value, $type_name);
			
		} else {		
			die("Fatal error, unknown preferences key: $pref_name");			
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
?>

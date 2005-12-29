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
			return $_SESSION["prefs_cache"][$pref_name];
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

			if ($type_name == "bool") {			
				$retv = $value == "true";				
			} else if ($type_name == "integer") {			
				$retv = sprintf("%d", $value);				
			} else {
				$retv = $value;
			}

			if ($user_id = $_SESSION["uid"]) {
				$_SESSION["prefs_cache"][$pref_name] = $value;
			}
			return $value;
			
		} else {		
			die("Fatal error, unknown preferences key: $pref_name");			
		}
	}

?>

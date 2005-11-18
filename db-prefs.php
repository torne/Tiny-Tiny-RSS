<?
	// TODO cache last query results

	require_once "config.php";
	require_once "db.php";

	function get_pref($link, $pref_name) {

		$pref_name = db_escape_string($pref_name);

		$result = db_query($link, "SELECT 
			value,ttrss_prefs_types.type_name as type_name 
			FROM 
				ttrss_user_prefs,ttrss_prefs,ttrss_prefs_types
			WHERE 
				ttrss_user_prefs.pref_name = '$pref_name' AND 
				ttrss_prefs_types.id = type_id AND
				owner_uid = ".$_SESSION["uid"]." AND
				ttrss_user_prefs.pref_name = ttrss_prefs.pref_name");

		if (db_num_rows($result) > 0) {
			$value = db_fetch_result($result, 0, "value");
			$type_name = db_fetch_result($result, 0, "type_name");

			if ($type_name == "bool") {			
				return $value == "true";				
			} else if ($type_name == "integer") {			
				return sprintf("%d", $value);				
			} else {
				return $value;
			}
			
		} else {		
			die("Fatal error, unknown preferences key: $pref_name");			
		}
	}

?>

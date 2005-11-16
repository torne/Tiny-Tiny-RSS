<?

	require_once "config.php";
	require_once "db.php";

	global $dbprefs_link;

	function get_pref($pref_name) {

		$pref_name = db_escape_string($pref_name);

		$result = db_query($dbprefs_link, "SELECT 
			value,ttrss_prefs_types.id as type_name 
			FROM ttrss_prefs,ttrss_prefs_types
			WHERE pref_name = '$pref_name' AND ttrss_prefs_types.id = type_id");

		if (db_num_rows($result) > 0) {
			$value = db_fetch_result($result, 0, "value");
			return $value;
		} else {		
			die("Fatal error, unknown preferences key: $pref_name");			
		}
	}

?>

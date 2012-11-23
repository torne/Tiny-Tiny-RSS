<?php
	set_include_path(get_include_path() . PATH_SEPARATOR .
		dirname(__FILE__) . "/include");

	require_once "functions.php";
	require_once "sessions.php";
	require_once "sanity_check.php";
	require_once "config.php";
	require_once "db.php";

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

	if (!init_connection($link)) return;
	login_sequence($link);

	$owner_uid = $_SESSION["uid"];

	if (!SINGLE_USER_MODE && $_SESSION["access_level"] < 10) {
		$_SESSION["login_error_msg"] = __("Your access level is insufficient to run this script.");
		render_login_form($link);
		exit;
	}


?>

<html>
<head>
<title>Database Updater</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<link rel="stylesheet" type="text/css" href="utility.css">
</head>

<body>

<script type='text/javascript'>
function confirmOP() {
	return confirm(__("Update the database?"));
}
</script>

<div class="floatingLogo"><img src="images/logo_wide.png"></div>

<h1><?php echo __("Database Updater") ?></h1>

<?php
	function getline($fp, $delim) {
		$result = "";
		while(!feof($fp)) {
			$tmp = fgetc($fp);

			if($tmp == $delim) {
				return $result;
			}
			$result .= $tmp;
		}
		return $result;
	}

	$op = $_POST["op"];

	$result = db_query($link, "SELECT schema_version FROM ttrss_version");
	$version = db_fetch_result($result, 0, "schema_version");

	$update_files = glob("schema/versions/".DB_TYPE."/*sql");
	$update_versions = array();

	foreach ($update_files as $f) {
		$m = array();
		preg_match_all("/schema\/versions\/".DB_TYPE."\/(\d*)\.sql/", $f, $m,
			PREG_PATTERN_ORDER);

		if ($m[1][0]) {
			$update_versions[$m[1][0]] = $f;
		}
	}

	ksort($update_versions, SORT_NUMERIC);

	$latest_version = max(array_keys($update_versions));

	if ($version == $latest_version) {

		if ($version != SCHEMA_VERSION) {
			print_error(__("Could not update database"));

			print "<p>" .
				__("Could not find necessary schema file, need version:") .
				" " . SCHEMA_VERSION . __(", found: ") . $latest_version . "</p>";

		} else {
			print_notice(__("Tiny Tiny RSS database is up to date."));
			print "<form method=\"GET\" action=\"index.php\">
				<input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\">
				</form>";
		}

	} else if ($version <= $latest_version && !$op) {

		print_warning(__("Please backup your database before proceeding."));

		print "<p>" . T_sprintf("Your Tiny Tiny RSS database needs update to the latest version (<b>%d</b> to <b>%d</b>).", $version, $latest_version) . "</p>";

	/*		print "<p>Available incremental updates:";

		foreach (array_keys($update_versions) as $v) {
			if ($v > $version) {
				print " <a href='$update_versions[$v]'>$v</a>";
			}
		} */

		print "</p>";

		print "<form method='POST'>
			<input type='hidden' name='op' value='do'>
			<input type='submit' onclick='return confirmOP()' value='".__("Perform updates")."'>
			</form>";

	} else if ($op == "do") {

		print "<p>".__("Performing updates...")."</p>";

		$num_updates = 0;

		foreach (array_keys($update_versions) as $v) {
			if ($v == $version + 1) {
				print "<p>".T_sprintf("Updating to version %d...", $v)."</p>";
				db_query($link, "BEGIN");
				$fp = fopen($update_versions[$v], "r");
				if ($fp) {
					while (!feof($fp)) {
						$query = trim(getline($fp, ";"));
						if ($query != "") {
							print "<p class='query'>$query</p>";
							db_query($link, $query);
						}
					}
				}
				fclose($fp);
				db_query($link, "COMMIT");

				print "<p>".__("Checking version... ");

				$result = db_query($link, "SELECT schema_version FROM ttrss_version");
				$version = db_fetch_result($result, 0, "schema_version");

				if ($version == $v) {
					print __("OK!");
				} else {
					print "<b>".__("ERROR!")."</b>";
					return;
				}

				$num_updates++;
			}
		}

		print "<p>".T_sprintf("Finished. Performed <b>%d</b> update(s) up to schema
			version <b>%d</b>.", $num_updates, $version)."</p>";

		print "<form method=\"GET\" action=\"backend.php\">
			<input type=\"hidden\" name=\"op\" value=\"logout\">
			<input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\">
			</form>";

	} else if ($version >= $latest_version) {

		print_error(__("Your database schema is from a newer version of Tiny Tiny RSS."));

		print "<p>" . T_sprintf("Found schema version: <b>%d</b>, required: <b>%d</b>.", $version, $latest_version) . "</p>";

		print "<p>" . __("Schema upgrade impossible. Please update Tiny Tiny RSS files to the newer version and continue.") . "</p>";

		print "<form method=\"GET\" action=\"backend.php\">
			<input type=\"hidden\" name=\"op\" value=\"logout\">
			<input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\">
			</form>";


	}

?>

</body>
</html>


<?php
require_once "sessions.php";

require_once "sanity_check.php";
require_once "functions.php";
require_once "config.php";
require_once "db.php";

$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);	

if (DB_TYPE == "pgsql") {
	pg_query($link, "set client_encoding = 'utf-8'");
	pg_set_client_encoding("UNICODE");
}

login_sequence($link);

$owner_uid = $_SESSION["uid"];

if ($_SESSION["access_level"] < 10) { 
	header("Location: login.php"); die;
}

define('SCHEMA_VERSION', 13);

?>

<html>
<head>
<title>Database Updater</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<link rel="stylesheet" type="text/css" href="update.css">
</head>

<body>

<script type='text/javascript'>
function confirmOP() {
	return confirm("Update the database?");
}
</script>

<h1>Database Updater</h1>

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
	print "<p>Tiny Tiny RSS database is up to date (version $version).</p>";
	print "<p><a href='tt-rss.php'>Return to Tiny Tiny RSS</a></p>";
	return;
}

if (!$op) {
	print "<p class='warning'><b>Warning:</b> Please backup your database before proceeding.</p>";

	print "<p>Your Tiny Tiny RSS database needs update to the latest 
		version ($version &mdash;&gt; $latest_version).</p>";

/*		print "<p>Available incremental updates:";

	foreach (array_keys($update_versions) as $v) {
		if ($v > $version) {
			print " <a href='$update_versions[$v]'>$v</a>";
		}
	} */

	print "</p>";

	print "<form method='POST'>
		<input type='hidden' name='op' value='do'>
		<input type='submit' onclick='return confirmOP()' value='Perform updates'>
		</form>";

} else if ($op == "do") {

	print "<p>Performing updates (from version $version)...</p>";

	$num_updates = 0;

	foreach (array_keys($update_versions) as $v) {
		if ($v == $version + 1) {
			print "<p>Updating to version $v...</p>";
			$fp = fopen($update_versions[$v], "r");
			if ($fp) {
				while (!feof($fp)) {
					$query = trim(getline($fp, ";"));
					if ($query != "") {
						print "<p class='query'><b>QUERY:</b> $query</p>";
						db_query($link, $query);
					}
				}
			}
			fclose($fp);

			print "<p>Checking version... ";

			$result = db_query($link, "SELECT schema_version FROM ttrss_version");
			$version = db_fetch_result($result, 0, "schema_version");

			if ($version == $v) {
				print "OK! ($version)";
			} else {
				print "<b>ERROR!</b>";
				return;
			}

			$num_updates++;
		}
	}

	print "<p>Finished. Performed $num_updates updates up to schema
		version $version.</p>";

	print "<p><a href='tt-rss.php'>Return to Tiny Tiny RSS</a></p>";

}

?>


</body>
</html>


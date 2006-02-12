<?
	session_start();

	define('MAX_SCHEMA_VERSION', 5);

	require_once "config.php";
	require_once "functions.php";
	require_once "db.php";

	if ($_GET["export"]) {
		header("Content-Type: application/xml");
	}

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);	

	if (!$link) {
		if (DB_TYPE == "mysql") {
			print mysql_error();
		}
		// PG seems to display its own errors just fine by default.		
		return;
	}

	if (DB_TYPE == "pgsql") {
		pg_query("set client_encoding = 'utf-8'");
	}

	$result = db_query($link, "SELECT schema_version FROM ttrss_version");

	$schema_version = db_fetch_result($result, 0, "schema_version");

	if ($schema_version > 1) login_sequence($link);

?>

<? if (!$_GET["export"]) { ?>

<html>
<head>
	<title>XML Export</title>
	<link rel="stylesheet" href="opml.css" type="text/css">
</head>
<body>
<h1><img src="images/ttrss_logo.png"></h1>

<div class="opmlBody">
	<h2>XML Export</h2>
	<form method="GET">
		Limit to: <input type="checkbox" checked name="marked"> starred,
		<input type="checkbox" name="unread"> unread.<br>
		<p><input type="submit" class="button" name="export" value="Export"></p>
	</form>
</div>

</body>
</html>

<? } else { ?>

<xmldb>

<?

/*	if ($schema_version != SCHEMA_VERSION) {
		print "<error>Source database schema is invalid
			(got version $schema_version; expected ".SCHEMA_VERSION.")</error>";
		print "</xmldb>";
		return;
	} */

	print "<schema_version>$schema_version</schema_version>";

	if ($schema_version > 1) {
		$owner_uid = $_SESSION["uid"];
		print "<owner_uid>$owner_uid</owner_uid>";
	}

	print "<exported>" . time() . "</exported>";
?>

<?
	if ($_GET["marked"]) {
		$marked_qpart = "AND marked = true";
	}

	if ($_GET["unread"]) {
		$unread_qpart = "AND unread = true";
	}

	if ($schema_version == 1) {

		$result = db_query($link, "SELECT
				ttrss_entries.title AS title,
				content,
				marked,
				unread,
				updated,
				guid,
				link,
				SUBSTRING(date_entered,1,16) AS date_entered,
				SUBSTRING(last_read,1,16) AS last_read, 
				comments,
				ttrss_feeds.feed_url AS feed_url,
				ttrss_feeds.title AS feed_title
			FROM 
				ttrss_entries,ttrss_feeds
			WHERE
				feed_id = ttrss_feeds.id $marked_qpart $unread_qpart 
			ORDER BY ttrss_entries.id");
				
	} else if ($schema_version >= 2 && $schema_version <= MAX_SCHEMA_VERSION) {

		$result = db_query($link, "SELECT
				ttrss_entries.title AS title,
				content,
				marked,
				unread,
				updated,
				guid,
				link,
				SUBSTRING(date_entered,1,16) AS date_entered,
				SUBSTRING(last_read,1,16) AS last_read, 
				comments,
				ttrss_feeds.feed_url AS feed_url,
				ttrss_feeds.title AS feed_title
			FROM 
				ttrss_entries,ttrss_feeds,ttrss_user_entries
			WHERE
				ttrss_user_entries.owner_uid = '$owner_uid' AND
				ref_id = ttrss_entries.id AND
				feed_id = ttrss_feeds.id $marked_qpart $unread_qpart
			ORDER BY ttrss_entries.id");

	} else {

		// BAD SCHEMA, NO COOKIE

		print "<error>Source database schema is invalid
			(got version $schema_version)</error>";
	}

	print "<total_articles>" . db_num_rows($result) . "</total_articles>";

?>

<articles>

<?	
	while ($line = db_fetch_assoc($result)) {
		print "<article>";

		foreach (array_keys($line) as $key) {
			$line[$key] = str_replace("<![CDATA[", "", $line[$key]);
			$line[$key] = str_replace("]]>", "", $line[$key]);
	
			print "<$key><![CDATA[".$line[$key]."]]></$key>";

		}

		print "</article>";
	}

?>
</articles>

</xmldb>

<? } ?>

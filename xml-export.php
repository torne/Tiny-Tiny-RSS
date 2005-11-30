<?
	/*
		Exports your starred articles in schema-neutral XML format.
	*/

	require_once "config.php";
	require_once "functions.php";
	require_once "db.php";

	define('SCHEMA_VERSION', 1);
	
	header("Content-Type: application/xml");
?>

<xmldb>

<?
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

	if ($schema_version != SCHEMA_VERSION) {
		print "Error: database schema is invalid
			(got version $schema_version; expected ".SCHEMA_VERSION.")";
		return;
	}

	print "<schema_version>$schema_version</schema_version>";

?>

<articles>

<?
	$result = db_query($link, "SELECT
			ttrss_entries.title AS title,
			content,
			marked,
			unread,
			updated,
			guid,
			link,
			date_entered,
			last_read,
			comments,
			ttrss_feeds.feed_url AS feed_url,
			ttrss_feeds.title AS feed_title
		FROM 
			ttrss_entries,ttrss_feeds
		WHERE
			feed_id = ttrss_feeds.id AND marked = true");


	while ($line = db_fetch_assoc($result)) {
		print "<article>";

		foreach (array_keys($line) as $key) {
			print "<$key><![CDATA[".$line[$key]."]]></$key>";

		}

		print "</article>";
	}

?>
</articles>

</xmldb>

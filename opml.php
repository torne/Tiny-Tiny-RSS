<?
	// FIXME there are some brackets issues here

	$op = $_GET["op"];
	if ($op == "export") {
		header("Content-type: application/xml");
	}

	require_once "config.php";
	require_once "functions.php";

	$link = pg_connect(DB_CONN);
	
	pg_query($link, "set client_encoding = 'utf-8'");

	if ($op == "export") {
		print "<?xml version=\"1.0\"?>";
		print "<opml version=\"1.0\">";
		print "<head><dateCreated>" . date("r", time()) . "</dateCreated></head>"; 
		print "<body>";

		$result = pg_query("SELECT * FROM ttrss_feeds ORDER BY title");

		while ($line = pg_fetch_assoc($result)) {
			$title = $line["title"];
			$url = $line["feed_url"];

			print "<outline text=\"$title\" xmlUrl=\"$url\"/>";
		}

		print "</body></opml>";
	}

?>

<?
	session_start();

	require_once "sanity_check.php";

	// FIXME there are some brackets issues here

	$op = $_REQUEST["op"];
	if ($op == "Export") {
		header("Content-type: application/xml");
		print "<?xml version=\"1.0\"?>";
	}

	require_once "config.php";
	require_once "db.php";
	require_once "db-prefs.php";

//	$_SESSION["uid"] = PLACEHOLDER_UID; // FIXME: placeholder

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);	

	if (DB_TYPE == "pgsql") {
		pg_query($link, "set client_encoding = 'utf-8'");
	}

	if ($op == "Export") {
		print "<opml version=\"1.0\">";
		print "<head><dateCreated>" . date("r", time()) . "</dateCreated></head>"; 
		print "<body>";

		$cat_mode = false;

		if (get_pref($link, 'ENABLE_FEED_CATS')) {
			$cat_mode = true;
			$result = db_query($link, "SELECT 
					ttrss_feeds.feed_url AS feed_url,
					ttrss_feeds.title AS title,
					ttrss_feed_categories.title AS cat_title
				FROM ttrss_feeds,ttrss_feed_categories 
				WHERE
					cat_id = ttrss_feed_categories.id
				ORDER BY ttrss_feed_categories.title,ttrss_feeds.title");
		} else {
			$result = db_query($link, "SELECT * FROM ttrss_feeds 
				ORDER BY title");
		}

		$old_cat_title = "";

		while ($line = db_fetch_assoc($result)) {
			$title = htmlspecialchars($line["title"]);
			$url = htmlspecialchars($line["feed_url"]);

			if ($cat_mode) {
				$cat_title = htmlspecialchars($line["cat_title"]);

				if ($old_cat_title != $cat_title) {
					if ($old_cat_title) {
						print "</outline>";	
					}

					print "<outline title=\"$cat_title\">";

					$old_cat_title = $cat_title;
				}
			}

			print "<outline text=\"$title\" xmlUrl=\"$url\"/>";
		}

		if ($cat_mode && $old_cat_title) {
			print "</outline>";	
		}

		print "</body></opml>";
	}

	function startElement($parser, $name, $attrs) {

		if ($name == "OUTLINE") {
			if ($name == "OUTLINE") {

				$title = $attrs["TEXT"];
				$url = $attrs["XMLURL"];

				if (!$title) {
					$title = $attrs['TITLE'];
				}
			}

			/* this is suboptimal */

			$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);	

			if (!$link) return;

			$title = db_escape_string_2($title, $link);
			$url = db_escape_string_2($url, $link);

			if (!$title || !$url) return;

			print "Feed <b>$title</b> ($url)... ";

			$result = db_query($link, "SELECT id FROM ttrss_feeds WHERE
				(title = '$title' OR feed_url = '$url') AND owner_uid = ".$_SESSION["uid"]);

			if ($result && db_num_rows($result) > 0) {
				
				print " Already imported.<br>";

			} else {
					  
				$result = db_query($link, "INSERT INTO ttrss_feeds (title, feed_url,owner_uid) VALUES
					('$title', '$url', '".$_SESSION["uid"]."')");

				print "<b>Done.</b><br>";

			}

			if ($link) db_close($link);

		}
	}

	function endElement($parser, $name) {


	}

	if ($op == "Import") {

		print "<html>
			<head>
				<link rel=\"stylesheet\" href=\"opml.css\" type=\"text/css\">
			</head>
			<body><h1>Importing OPML...</h1>
			<div>";

		if (WEB_DEMO_MODE) {
			print "OPML import is disabled in demo-mode.";
			print "<p><a class=\"button\" href=\"prefs.php\">
			Return to preferences</a></div></body></html>";

			return;
		}

		if (is_file($_FILES['opml_file']['tmp_name'])) {
		 
			$xml_parser = xml_parser_create();

			xml_set_element_handler($xml_parser, "startElement", "endElement");

			$fp = fopen($_FILES['opml_file']['tmp_name'], "r");

			if ($fp) {

				while ($data = fread($fp, 4096)) {

					if (!xml_parse($xml_parser, $data, feof($fp))) {
						
						print sprintf("Unable to parse OPML file, XML error: %s at line %d",
							xml_error_string(xml_get_error_code($xml_parser)),
							xml_get_current_line_number($xml_parser));

						print "<p><a class=\"button\" href=\"prefs.php\">
							Return to preferences</a>";

						return;

					}
				}

				xml_parser_free($xml_parser);
				fclose($fp);

			} else {
				print("Error: Could not open OPML input.");
			}

		} else {	
			print "Error: please upload OPML file.";
		}

		print "<p><a class=\"button\" href=\"prefs.php\">
			Return to preferences</a>";

		print "</div></body></html>";

	}

//	if ($link) db_close($link);

?>

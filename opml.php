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

	$owner_uid = $_SESSION["uid"];

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
					(SELECT title FROM ttrss_feed_categories WHERE id = cat_id) as cat_title
					FROM ttrss_feeds
				ORDER BY cat_title,title");
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
			$dom = domxml_open_file($_FILES['opml_file']['tmp_name']);

			if ($dom) {
				$root = $dom->document_element();

				$body = $root->get_elements_by_tagname('body');

				if ($body[0]) {			
					$body = $body[0];

					$outlines = $body->get_elements_by_tagname('outline');

					$active_category = '';

					foreach ($outlines as $outline) {
						$feed_title = $outline->get_attribute('text');
						$cat_title = $outline->get_attribute('title');
						$feed_url = $outline->get_attribute('xmlUrl');

						if ($cat_title) {
							$active_category = $cat_title;

							db_query($link, "BEGIN");
							
							$result = db_query($link, "SELECT id FROM
								ttrss_feed_categories WHERE title = '$cat_title' AND
								owner_uid = '$owner_uid' LIMIT 1");

							if (db_num_rows($result) == 0) {

								print "Adding category <b>$cat_title</b>...<br>";

								db_query($link, "INSERT INTO ttrss_feed_categories
									(title,owner_uid) VALUES ('$cat_title', '$owner_uid')");
							}

							db_query($link, "COMMIT");
						}

//						print "$active_category : $feed_title : $xmlurl<br>";

						if (!$feed_title || !$feed_url) continue;

						db_query($link, "BEGIN");

						$cat_id = null;

						if ($active_category) {

							$result = db_query($link, "SELECT id FROM
									ttrss_feed_categories WHERE title = '$active_category' AND
									owner_uid = '$owner_uid' LIMIT 1");								

							if (db_num_rows($result) == 1) {	
								$cat_id = db_fetch_result($result, 0, "id");
							}
						}								

						$result = db_query($link, "SELECT id FROM ttrss_feeds WHERE
							(title = '$feed_title' OR feed_url = '$feed_url') 
							AND owner_uid = '$owner_uid'");

						print "Feed <b>$feed_title</b> ($feed_url)... ";

						if (db_num_rows($result) > 0) {
							print " Already imported.<br>";
						} else {

							if ($cat_id) {
								$add_query = "INSERT INTO ttrss_feeds 
									(title, feed_url, owner_uid, cat_id) VALUES
									('$feed_title', '$feed_url', '$owner_uid', '$cat_id')";

							} else {
								$add_query = "INSERT INTO ttrss_feeds 
									(title, feed_url, owner_uid) VALUES
									('$feed_title', '$feed_url', '$owner_uid')";

							}
							
							db_query($link, $add_query);
							
							print "<b>Done.</b><br>";
						}
						
						db_query($link, "COMMIT");
					}

				} else {
					print "Error: can't find body element.";
				}
			} else {
				print "Error while parsing document.";
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

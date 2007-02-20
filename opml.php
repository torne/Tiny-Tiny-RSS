<?php
	require_once "sessions.php";

	require_once "sanity_check.php";
	require_once "functions.php";
	require_once "config.php";
	require_once "db.php";
	require_once "db-prefs.php";

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);	

	if (DB_TYPE == "pgsql") {
		pg_query($link, "set client_encoding = 'utf-8'");
		pg_set_client_encoding("UNICODE");
	}

	login_sequence($link);

	$owner_uid = $_SESSION["uid"];

	// FIXME there are some brackets issues here

	$op = $_REQUEST["op"];
	
	if (!$op) $op = "Export";
	
	if ($op == "Export") {
		header("Content-type: application/xml+opml");
		print "<?phpxml version=\"1.0\"?>";
	}

	if ($op == "Export") {
		print "<opml version=\"1.0\">";
		print "<head>
			<dateCreated>" . date("r", time()) . "</dateCreated>
			<title>Tiny Tiny RSS Feed Export</title>
		</head>"; 
		print "<body>";

		$cat_mode = false;

		if (get_pref($link, 'ENABLE_FEED_CATS')) {
			$cat_mode = true;
			$result = db_query($link, "SELECT 
					title,feed_url,site_url,
					(SELECT title FROM ttrss_feed_categories WHERE id = cat_id) as cat_title
					FROM ttrss_feeds
				WHERE
					owner_uid = '$owner_uid'
				ORDER BY cat_title,title");
		} else {
			$result = db_query($link, "SELECT * FROM ttrss_feeds 
				WHERE owner_uid = '$owner_uid' ORDER BY title");
		}

		$old_cat_title = "";

		while ($line = db_fetch_assoc($result)) {
			$title = htmlspecialchars($line["title"]);
			$url = htmlspecialchars($line["feed_url"]);
			$site_url = htmlspecialchars($line["site_url"]);

			if ($cat_mode) {
				$cat_title = htmlspecialchars($line["cat_title"]);

				if ($old_cat_title != $cat_title) {
					if ($old_cat_title) {
						print "</outline>\n";	
					}

					if ($cat_title) {
						print "<outline title=\"$cat_title\">\n";
					}

					$old_cat_title = $cat_title;
				}
			}

			if ($site_url) {
				$html_url_qpart = "htmlUrl=\"$site_url\"";
			} else {
				$html_url_qpart = "";
			}

			print "<outline text=\"$title\" xmlUrl=\"$url\" $html_url_qpart/>\n";
		}

		if ($cat_mode && $old_cat_title) {
			print "</outline>\n";	
		}

		print "</body></opml>";
	}

	if ($op == "Import") {

		print "<html>
			<head>
				<link rel=\"stylesheet\" href=\"opml.css\" type=\"text/css\">
			</head>
			<body>
			<h1><img src=\"images/ttrss_logo.png\"></h1>
			<div class=\"opmlBody\">
			<h2>"._('Importing OPML...')."</h2>";

		if (is_file($_FILES['opml_file']['tmp_name'])) {
			$dom = domxml_open_file($_FILES['opml_file']['tmp_name']);

			if ($dom) {
				$root = $dom->document_element();

				$body = $root->get_elements_by_tagname('body');

				if ($body[0]) {			
					$body = $body[0];

					$outlines = $body->get_elements_by_tagname('outline');

					print "<table>";

					foreach ($outlines as $outline) {

						$feed_title = db_escape_string($outline->get_attribute('text'));

						if (!$feed_title) {
							$feed_title = db_escape_string($outline->get_attribute('title'));
						}

						$cat_title = db_escape_string($outline->get_attribute('title'));

						if (!$cat_title) {
							$cat_title = db_escape_string($outline->get_attribute('text'));
						}
	
						$feed_url = db_escape_string($outline->get_attribute('xmlUrl'));
						$site_url = db_escape_string($outline->get_attribute('htmlUrl'));

						if ($cat_title && !$feed_url) {

							db_query($link, "BEGIN");
							
							$result = db_query($link, "SELECT id FROM
								ttrss_feed_categories WHERE title = '$cat_title' AND
								owner_uid = '$owner_uid' LIMIT 1");

							if (db_num_rows($result) == 0) {

								printf(_("Adding category <b>%s</b>..."), $cat_title);
								print "<br>";

								db_query($link, "INSERT INTO ttrss_feed_categories
									(title,owner_uid) 
								VALUES ('$cat_title', '$owner_uid')");
							}

							db_query($link, "COMMIT");
						}

//						print "$active_category : $feed_title : $feed_url<br>";

						if (!$feed_title || !$feed_url) continue;

						db_query($link, "BEGIN");

						$cat_id = null;

						$parent_node = $outline->parent_node();

						if ($parent_node && $parent_node->node_name() == "outline") {
							$element_category = $parent_node->get_attribute('title');
						} else {
							$element_category = '';
						}

						if ($element_category) {

							$result = db_query($link, "SELECT id FROM
									ttrss_feed_categories WHERE title = '$element_category' AND
									owner_uid = '$owner_uid' LIMIT 1");								

							if (db_num_rows($result) == 1) {	
								$cat_id = db_fetch_result($result, 0, "id");
							}
						}								

						$result = db_query($link, "SELECT id FROM ttrss_feeds WHERE
							(title = '$feed_title' OR feed_url = '$feed_url') 
							AND owner_uid = '$owner_uid'");

						print "<tr><td><a href='$site_url'><b>$feed_title</b></a></b> 
							(<a href=\"$feed_url\">rss</a>)</td>";

						if (db_num_rows($result) > 0) {
							print "<td>"._("Already imported.")."</td>";
						} else {

							if ($cat_id) {
								$add_query = "INSERT INTO ttrss_feeds 
									(title, feed_url, owner_uid, cat_id, site_url) VALUES
									('$feed_title', '$feed_url', '$owner_uid', 
										'$cat_id', '$site_url')";

							} else {
								$add_query = "INSERT INTO ttrss_feeds 
									(title, feed_url, owner_uid, site_url) VALUES
									('$feed_title', '$feed_url', '$owner_uid', '$site_url')";

							}

							db_query($link, $add_query);
							
							print "<td><b>"._('Done.')."</b></td>";
						}

						print "</tr>";
						
						db_query($link, "COMMIT");
					}

					print "</table>";

				} else {
					print "<div class=\"error\">"._("Error: can't find body element.")."</div>";
				}
			} else {
				print "<div class=\"error\">"._("Error while parsing document.")."</div>";
			}

		} else {
			print "<div class=\"error\">"._("Error: please upload OPML file.")."</div>";
		}

		print "<p><a class=\"button\" href=\"prefs.php\">
			"._("Return to preferences")."</a>";

		print "</div></body></html>";

	}

//	if ($link) db_close($link);

?>

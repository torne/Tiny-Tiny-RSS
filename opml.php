<?php
	set_include_path(get_include_path() . PATH_SEPARATOR . "include");

	require_once "functions.php";
	require_once "sessions.php";
	require_once "sanity_check.php";
	require_once "config.php";
	require_once "db.php";
	require_once "db-prefs.php";

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

	if (!init_connection($link)) return;

	function opml_import_domdoc($link, $owner_uid) {

		if (is_file($_FILES['opml_file']['tmp_name'])) {
			$doc = DOMDocument::load($_FILES['opml_file']['tmp_name']);

			$result = db_query($link, "SELECT id FROM
				ttrss_feed_categories WHERE title = 'Imported feeds' AND
				owner_uid = '$owner_uid' LIMIT 1");

			if (db_num_rows($result) == 1) {
				$default_cat_id = db_fetch_result($result, 0, "id");
			} else {
				$default_cat_id = 0;
			}

			if ($doc) {
				$body = $doc->getElementsByTagName('body');

				$xpath = new DOMXpath($doc);
				$query = "/opml/body//outline";

				$outlines = $xpath->query($query);

				foreach ($outlines as $outline) {

					$feed_title = db_escape_string($outline->attributes->getNamedItem('text')->nodeValue);

					if (!$feed_title) {
						$feed_title = db_escape_string($outline->attributes->getNamedItem('title')->nodeValue);
					}

					$cat_title = db_escape_string($outline->attributes->getNamedItem('title')->nodeValue);

					if (!$cat_title) {
						$cat_title = db_escape_string($outline->attributes->getNamedItem('text')->nodeValue);
					}

					$feed_url = db_escape_string($outline->attributes->getNamedItem('xmlUrl')->nodeValue);

					if (!$feed_url)
						$feed_url = db_escape_string($outline->attributes->getNamedItem('xmlURL')->nodeValue);

					$site_url = db_escape_string($outline->attributes->getNamedItem('htmlUrl')->nodeValue);

					$pref_name = db_escape_string($outline->attributes->getNamedItem('pref-name')->nodeValue);

					if ($cat_title && !$feed_url) {

						if ($cat_title != "tt-rss-prefs") {

							db_query($link, "BEGIN");

							$result = db_query($link, "SELECT id FROM
									ttrss_feed_categories WHERE title = '$cat_title' AND
									owner_uid = '$owner_uid' LIMIT 1");

							if (db_num_rows($result) == 0) {

								printf(__("<li>Adding category <b>%s</b>.</li>"), $cat_title);

								db_query($link, "INSERT INTO ttrss_feed_categories
										(title,owner_uid)
										VALUES ('$cat_title', '$owner_uid')");
							}

							db_query($link, "COMMIT");
						}
					}

					//						print "$active_category : $feed_title : $feed_url<br>";

					if ($pref_name) {
						$parent_node = $outline->parentNode;

						if ($parent_node && $parent_node->nodeName == "outline") {
							$cat_check = $parent_node->attributes->getNamedItem('title')->nodeValue;
							if ($cat_check == "tt-rss-prefs") {
								$pref_value = db_escape_string($outline->attributes->getNamedItem('value')->nodeValue);

								printf("<li>".
									__("Setting preference key %s to %s")."</li>",
										$pref_name, $pref_value);

								set_pref($link, $pref_name, $pref_value);

							}
						}
					}

					if (!$feed_title || !$feed_url) continue;

					db_query($link, "BEGIN");

					$cat_id = null;

					$parent_node = $outline->parentNode;

					if ($parent_node && $parent_node->nodeName == "outline") {
						$element_category = $parent_node->attributes->getNamedItem('title')->nodeValue;
						if (!$element_category) $element_category = $parent_node->attributes->getNamedItem('text')->nodeValue;

					} else {
						$element_category = '';
					}

					if ($element_category) {

						$element_category = db_escape_string($element_category);

						$result = db_query($link, "SELECT id FROM
								ttrss_feed_categories WHERE title = '$element_category' AND
								owner_uid = '$owner_uid' LIMIT 1");

							if (db_num_rows($result) == 1) {
								$cat_id = db_fetch_result($result, 0, "id");
							}
					}

					$result = db_query($link, "SELECT id FROM ttrss_feeds WHERE
							feed_url = '$feed_url'
							AND owner_uid = '$owner_uid'");

					print "<li><a target='_blank' href='$site_url'><b>$feed_title</b></a></b>
						(<a target='_blank' href=\"$feed_url\">rss</a>)&nbsp;";

					if (db_num_rows($result) > 0) {
						print __('is already imported.');
					} else {

						if ($cat_id) {
							$add_query = "INSERT INTO ttrss_feeds
								(title, feed_url, owner_uid, cat_id, site_url) VALUES
								('$feed_title', '$feed_url', '$owner_uid',
								 '$cat_id', '$site_url')";

						} else {
							$add_query = "INSERT INTO ttrss_feeds
								(title, feed_url, owner_uid, cat_id, site_url) VALUES
								('$feed_title', '$feed_url', '$owner_uid', '$default_cat_id',
									'$site_url')";

						}

						//print $add_query;
						db_query($link, $add_query);

						print __('OK');
					}

					print "</li>";

					db_query($link, "COMMIT");
				}

			} else {
				print_error(__('Error while parsing document.'));
			}

		} else {
			print_error(__('Error: please upload OPML file.'));
		}


	}

	function opml_export($link, $name, $owner_uid, $hide_private_feeds=false, $include_settings=true) {
		if (!$_REQUEST["debug"]) {
			header("Content-type: application/xml+opml");
		} else {
			header("Content-type: text/xml");
		}
        header("Content-Disposition: attachment; filename=" . $name );

		print "<?xml version=\"1.0\" encoding=\"utf-8\"?>";

		print "<opml version=\"1.0\">";
		print "<head>
			<dateCreated>" . date("r", time()) . "</dateCreated>
			<title>Tiny Tiny RSS Feed Export</title>
		</head>";
		print "<body>";

		$cat_mode = false;

                $select = "SELECT * ";
                $where = "WHERE owner_uid = '$owner_uid'";
                $orderby = "ORDER BY title";
		if ($hide_private_feeds){
			$where = "WHERE owner_uid = '$owner_uid' AND private IS false AND
				auth_login = '' AND auth_pass = ''";
		}



		if (get_pref($link, 'ENABLE_FEED_CATS', $owner_uid) == true) {
			$cat_mode = true;
                        $select = "SELECT
				title, feed_url, site_url,
				(SELECT title FROM ttrss_feed_categories WHERE id = cat_id) as cat_title";
			$orderby = "ORDER BY cat_title, title";

		}
		else{
			$cat_feed = get_pref($link, 'ENABLE_FEED_CATS');
			print "<!-- feeding cats is not enabled -->";
			print "<!-- $cat_feed -->";

		}


 		$result = db_query($link, $select." FROM ttrss_feeds ".$where." ".$orderby);

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
						print "<outline title=\"$cat_title\" text=\"$cat_title\" >\n";
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

		# export tt-rss settings

		if ($include_settings) {
			print "<outline title=\"tt-rss-prefs\" schema-version=\"".SCHEMA_VERSION."\">";

			$result = db_query($link, "SELECT pref_name, value FROM ttrss_user_prefs WHERE
			   profile IS NULL AND owner_uid = " . $_SESSION["uid"]);

			while ($line = db_fetch_assoc($result)) {

				$name = $line["pref_name"];
				$value = htmlspecialchars($line["value"]);

				print "<outline pref-name=\"$name\" value=\"$value\">";

				print "</outline>";

			}

			print "</outline>";
		}

		print "</body></opml>";
	}

	// FIXME there are some brackets issues here

	$op = $_REQUEST["op"];
    if (!$op) $op = "Export";

    $output_name = $_REQUEST["filename"];
	if (!$output_name) $output_name = "TinyTinyRSS.opml";

    $show_settings = $_REQUEST["settings"];

	if ($op == "Export") {

		login_sequence($link);
		$owner_uid = $_SESSION["uid"];
		return opml_export($link, $output_name, $owner_uid, false, ($show_settings == 1));
	}

	if ($op == "publish"){
		$key = db_escape_string($_REQUEST["key"]);

		$result = db_query($link, "SELECT owner_uid
				FROM ttrss_access_keys WHERE
				access_key = '$key' AND feed_id = 'OPML:Publish'");

		if (db_num_rows($result) == 1) {
			$owner_uid = db_fetch_result($result, 0, "owner_uid");
			return opml_export($link, "", $owner_uid, true, false);
		} else {
			print "<error>User not found</error>";
		}
	}

	if ($op == "Import") {

		login_sequence($link);
		$owner_uid = $_SESSION["uid"];

		header('Content-Type: text/html; charset=utf-8');

		print "<html>
			<head>
				<link rel=\"stylesheet\" href=\"utility.css\" type=\"text/css\">
				<title>".__("OPML Utility")."</title>
				<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\"/>
			</head>
			<body>
			<div class=\"floatingLogo\"><img src=\"images/logo_wide.png\"></div>
			<h1>".__('OPML Utility')."</h1>";

		db_query($link, "BEGIN");

		/* create Imported feeds category just in case */

		$result = db_query($link, "SELECT id FROM
			ttrss_feed_categories WHERE title = 'Imported feeds' AND
			owner_uid = '$owner_uid' LIMIT 1");

		if (db_num_rows($result) == 0) {
				db_query($link, "INSERT INTO ttrss_feed_categories
					(title,owner_uid)
						VALUES ('Imported feeds', '$owner_uid')");
		}

		db_query($link, "COMMIT");

		print "<p>".__("Importing OPML...")."</p>";
		opml_import_domdoc($link, $owner_uid);

		print "<br><form method=\"GET\" action=\"prefs.php\">
			<input type=\"submit\" value=\"".__("Return to preferences")."\">
			</form>";

		print "</body></html>";

	}

//	if ($link) db_close($link);

?>

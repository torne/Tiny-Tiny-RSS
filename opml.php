<?php
	error_reporting(E_ERROR | E_WARNING | E_PARSE);

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

	function opml_export($link, $owner_uid) {
		header("Content-type: application/xml+opml");
		print "<?xml version=\"1.0\"?>";

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

	// FIXME there are some brackets issues here

	$op = $_REQUEST["op"];
	
	if (!$op) $op = "Export";
	
	if ($op == "Export") {
		return opml_export($link, $owner_uid);
	}

	if ($op == "Import") {

		print "<html>
			<head>
				<link rel=\"stylesheet\" href=\"utility.css\" type=\"text/css\">
				<title>OPML Utility</title>
			</head>
			<body>
			<div class=\"floatingLogo\"><img src=\"images/ttrss_logo.png\"></div>
			<h1>".__('OPML Utility')."</h1>";

		if (function_exists('domxml_open_file')) {
			print "<p>Importing OPML (using DOMXML extension)...</p>";
			require_once "modules/opml_domxml.php";
			opml_import_domxml($link, $owner_uid);
		} else {
			print "<p>Importing OPML (using DOMDocument extension)...</p>";
			require_once "modules/opml_domdoc.php";
			opml_import_domdoc($link, $owner_uid);
		}

		print "<br><form method=\"GET\" action=\"prefs.php\">
			<input type=\"submit\" value=\"Return to preferences\">
			</form>";

		print "</body></html>";

	}

//	if ($link) db_close($link);

?>

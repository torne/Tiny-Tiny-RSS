<?php
	error_reporting(E_ERROR | E_WARNING | E_PARSE);

	require_once "sessions.php";
	require_once "sanity_check.php";
	require_once "functions.php";
	require_once "config.php";
	require_once "db.php";
	require_once "db-prefs.php";

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);	

	init_connection($link);
	login_sequence($link);

	$owner_uid = $_SESSION["uid"];

	function opml_export($link, $owner_uid, $hide_private_feeds=False) {
		header("Content-type: application/xml+opml");
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
			$where = "WHERE owner_uid = '$owner_uid' AND private IS false";
		}

		if (get_pref($link, 'ENABLE_FEED_CATS')) {
			$cat_mode = true;
                        $select = "SELECT 
				title, feed_url, site_url,
				(SELECT title FROM ttrss_feed_categories WHERE id = cat_id) as cat_title";
			$orderby = "ORDER BY cat_title, title";

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
        if ($op == "publish"){
		$key = db_escape_string($_REQUEST["key"]);

		$result = db_query($link, "SELECT login, owner_uid 
				FROM ttrss_user_prefs, ttrss_users WHERE
				pref_name = '_PREFS_PUBLISH_KEY' AND 
				value = '$key' AND 
				ttrss_users.id = owner_uid");

		if (db_num_rows($result) == 1) {
			$owner = db_fetch_result($result, 0, "owner_uid");
			return opml_export($link, $owner, True);
		} else {
			print "<error>User not found</error>";
		}
	}

	if ($op == "Import") {

		print "<html>
			<head>
				<link rel=\"stylesheet\" href=\"utility.css\" type=\"text/css\">
				<title>".__("OPML Utility")."</title>
			</head>
			<body>
			<div class=\"floatingLogo\"><img src=\"images/ttrss_logo.png\"></div>
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

		/* Handle OPML import by DOMXML/DOMDocument */

		if (function_exists('domxml_open_file')) {
			print "<p>".__("Importing OPML (using DOMXML extension)...")."</p>";
			require_once "modules/opml_domxml.php";
			opml_import_domxml($link, $owner_uid);
		} else if (PHP_VERSION >= 5) {
			print "<p>".__("Importing OPML (using DOMDocument extension)...")."</p>";
			require_once "modules/opml_domdoc.php";
			opml_import_domdoc($link, $owner_uid);
		} else {
			print_error(__("DOMXML extension is not found. It is required for PHP versions below 5."));
		}

		print "<br><form method=\"GET\" action=\"prefs.php\">
			<input type=\"submit\" value=\"".__("Return to preferences")."\">
			</form>";

		print "</body></html>";

	}

//	if ($link) db_close($link);

?>

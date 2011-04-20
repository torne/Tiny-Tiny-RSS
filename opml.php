<?php
	require_once "functions.php";
	require_once "sessions.php";
	require_once "sanity_check.php";
	require_once "config.php";
	require_once "db.php";
	require_once "db-prefs.php";

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

	init_connection($link);

	function opml_export($link, $owner_uid, $hide_private_feeds=false, $include_settings=true) {
		if (!$_REQUEST["debug"]) {
			header("Content-type: application/xml+opml");
		} else {
			header("Content-type: text/xml");
		}
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

	if ($op == "Export") {

		login_sequence($link);
		$owner_uid = $_SESSION["uid"];
		return opml_export($link, $owner_uid);
	}

	if ($op == "publish"){
		$key = db_escape_string($_REQUEST["key"]);

		$result = db_query($link, "SELECT owner_uid
				FROM ttrss_access_keys WHERE
				access_key = '$key' AND feed_id = 'OPML:Publish'");

		if (db_num_rows($result) == 1) {
			$owner_uid = db_fetch_result($result, 0, "owner_uid");
			return opml_export($link, $owner_uid, true, false);
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
		require_once "modules/opml_domdoc.php";
		opml_import_domdoc($link, $owner_uid);

		print "<br><form method=\"GET\" action=\"prefs.php\">
			<input type=\"submit\" value=\"".__("Return to preferences")."\">
			</form>";

		print "</body></html>";

	}

//	if ($link) db_close($link);

?>

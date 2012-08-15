<?php
	set_include_path(get_include_path() . PATH_SEPARATOR .
		dirname(__FILE__) . "/include");

	require_once "functions.php";
	require_once "sessions.php";
	require_once "sanity_check.php";
	require_once "config.php";
	require_once "db.php";
	require_once "db-prefs.php";

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

	if (!init_connection($link)) return;

	function opml_import_feed($link, $doc, $node, $cat_id, $owner_uid) {
		$attrs = $node->attributes;

		$feed_title = db_escape_string($attrs->getNamedItem('text')->nodeValue);
		if (!$feed_title) $feed_title = db_escape_string($attrs->getNamedItem('title')->nodeValue);

		$feed_url = db_escape_string($attrs->getNamedItem('xmlUrl')->nodeValue);
		if (!$feed_url) $feed_url = db_escape_string($attrs->getNamedItem('xmlURL')->nodeValue);

		$site_url = db_escape_string($attrs->getNamedItem('htmlUrl')->nodeValue);

		if ($feed_url && $feed_title) {
			$result = db_query($link, "SELECT id FROM ttrss_feeds WHERE
				feed_url = '$feed_url' AND owner_uid = '$owner_uid'");

			if (db_num_rows($result) == 0) {
				#opml_notice("[FEED] [$feed_title/$feed_url] dst_CAT=$cat_id");
				opml_notice(T_sprintf("Adding feed: %s", $feed_title));

				$query = "INSERT INTO ttrss_feeds
					(title, feed_url, owner_uid, cat_id, site_url, order_id) VALUES
					('$feed_title', '$feed_url', '$owner_uid',
					'$cat_id', '$site_url', 0)";
				db_query($link, $query);

			} else {
				opml_notice(T_sprintf("Duplicate feed: %s", $feed_title));
			}
		}
	}

	function opml_import_label($link, $doc, $node, $owner_uid) {
		$attrs = $node->attributes;
		$label_name = db_escape_string($attrs->getNamedItem('label-name')->nodeValue);

		if ($label_name) {
			$fg_color = db_escape_string($attrs->getNamedItem('label-fg-color')->nodeValue);
			$bg_color = db_escape_string($attrs->getNamedItem('label-bg-color')->nodeValue);

			if (!label_find_id($link, $label_name, $_SESSION['uid'])) {
				opml_notice(T_sprintf("Adding label %s", htmlspecialchars($label_name)));
				label_create($link, $label_name, $fg_color, $bg_color);
			} else {
				opml_notice(T_sprintf("Duplicate label: %s", htmlspecialchars($label_name)));
			}
		}
	}

	function opml_import_preference($link, $doc, $node, $owner_uid) {
		$attrs = $node->attributes;
		$pref_name = db_escape_string($attrs->getNamedItem('pref-name')->nodeValue);

		if ($pref_name) {
			$pref_value = db_escape_string($attrs->getNamedItem('value')->nodeValue);

			opml_notice(T_sprintf("Setting preference key %s to %s",
				$pref_name, $pref_value));

			set_pref($link, $pref_name, $pref_value);
		}
	}

	function opml_import_filter($link, $doc, $node, $owner_uid) {
		$attrs = $node->attributes;

		$filter_name = db_escape_string($attrs->getNamedItem('filter-name')->nodeValue);

		if ($filter_name) {

		$filter = json_decode($node->nodeValue, true);

			if ($filter) {
				$reg_exp = db_escape_string($filter['reg_exp']);
				$filter_type = (int)$filter['filter_type'];
				$action_id = (int)$filter['action_id'];

				$result = db_query($link, "SELECT id FROM ttrss_filters WHERE
					reg_exp = '$reg_exp' AND
					filter_type = '$filter_type' AND
					action_id = '$action_id' AND
					owner_uid = " .$_SESSION['uid']);

				if (db_num_rows($result) == 0) {
					$enabled = bool_to_sql_bool($filter['enabled']);
					$action_param = db_escape_string($filter['action_param']);
					$inverse = bool_to_sql_bool($filter['inverse']);
					$filter_param = db_escape_string($filter['filter_param']);
					$cat_filter = bool_to_sql_bool($filter['cat_filter']);

					$feed_url = db_escape_string($filter['feed_url']);
					$cat_title = db_escape_string($filter['cat_title']);

					$result = db_query($link, "SELECT id FROM ttrss_feeds WHERE
						feed_url = '$feed_url' AND owner_uid = ".$_SESSION['uid']);

					if (db_num_rows($result) != 0) {
						$feed_id = db_fetch_result($result, 0, "id");
					} else {
						$feed_id = "NULL";
					}

					$result = db_query($link, "SELECT id FROM ttrss_feed_categories WHERE
						title = '$cat_title' AND  owner_uid = ".$_SESSION['uid']);

					if (db_num_rows($result) != 0) {
						$cat_id = db_fetch_result($result, 0, "id");
					} else {
						$cat_id = "NULL";
					}

					opml_notice(T_sprintf("Adding filter %s", htmlspecialchars($reg_exp)));

					$query = "INSERT INTO ttrss_filters (filter_type, action_id,
							enabled, inverse, action_param, filter_param,
							cat_filter, feed_id,
							cat_id, reg_exp,
							owner_uid)
						VALUES ($filter_type, $action_id,
							$enabled, $inverse, '$action_param', '$filter_param',
							$cat_filter, $feed_id,
							$cat_id, '$reg_exp', ".
							$_SESSION['uid'].")";

					db_query($link, $query);

				} else {
					opml_notice(T_sprintf("Duplicate filter %s", htmlspecialchars($reg_exp)));
				}
			}
		}
	}

	function opml_import_category($link, $doc, $root_node, $owner_uid, $parent_id) {
		$body = $doc->getElementsByTagName('body');

		$default_cat_id = (int) get_feed_category($link, 'Imported feeds', false);

		if ($root_node) {
			$cat_title = db_escape_string($root_node->attributes->getNamedItem('title')->nodeValue);

			if (!in_array($cat_title, array("tt-rss-filters", "tt-rss-labels", "tt-rss-prefs"))) {
				$cat_id = get_feed_category($link, $cat_title, $parent_id);
				db_query($link, "BEGIN");
				if ($cat_id === false) {
					add_feed_category($link, $cat_title, $parent_id);
					$cat_id = get_feed_category($link, $cat_title, $parent_id);
				}
				db_query($link, "COMMIT");
			} else {
				$cat_id = 0;
			}

			$outlines = $root_node->childNodes;

		} else {
			$xpath = new DOMXpath($doc);
			$outlines = $xpath->query("//opml/body/outline");

			$cat_id = 0;
		}

		#opml_notice("[CAT] $cat_title id: $cat_id P_id: $parent_id");
		opml_notice(T_sprintf("Processing category: %s", $cat_title ? $cat_title : __("Uncategorized")));

		foreach ($outlines as $node) {
			if ($node->hasAttributes() && strtolower($node->tagName) == "outline") {
				$attrs = $node->attributes;
				$node_cat_title = db_escape_string($attrs->getNamedItem('title')->nodeValue);

				if ($node->hasChildNodes() && $node_cat_title) {
					opml_import_category($link, $doc, $node, $owner_uid, $cat_id);
				} else {

					if (!$cat_id) {
						$dst_cat_id = $default_cat_id;
					} else {
						$dst_cat_id = $cat_id;
					}

					switch ($cat_title) {
					case "tt-rss-prefs":
						opml_import_preference($link, $doc, $node, $owner_uid);
						break;
					case "tt-rss-labels":
						opml_import_label($link, $doc, $node, $owner_uid);
						break;
					case "tt-rss-filters":
						opml_import_filter($link, $doc, $node, $owner_uid);
						break;
					default:
						opml_import_feed($link, $doc, $node, $dst_cat_id, $owner_uid);
					}
				}
			}
		}
	}

	function opml_import_domdoc($link, $owner_uid) {

		$debug = isset($_REQUEST["debug"]);
		$doc = false;

		if ($debug) $doc = DOMDocument::load("/tmp/test.opml");

		if (is_file($_FILES['opml_file']['tmp_name'])) {
			$doc = DOMDocument::load($_FILES['opml_file']['tmp_name']);
		} else if (!$doc) {
			print_error(__('Error: please upload OPML file.'));
			return;
		}

		if ($doc) {
			opml_import_category($link, $doc, false, $owner_uid);
		} else {
			print_error(__('Error while parsing document.'));
		}
	}

	function opml_export_category($link, $owner_uid, $cat_id, $hide_private_feeds=false) {

		if ($cat_id) {
			$cat_qpart = "parent_cat = '$cat_id'";
			$feed_cat_qpart = "cat_id = '$cat_id'";
		} else {
			$cat_qpart = "parent_cat IS NULL";
			$feed_cat_qpart = "cat_id IS NULL";
		}

		if ($hide_private_feeds)
			$hide_qpart = "(private IS false AND auth_login = '' AND auth_pass = '')";
		else
			$hide_qpart = "true";

		$out = "";

		if ($cat_id) {
			$result = db_query($link, "SELECT title FROM ttrss_feed_categories WHERE id = '$cat_id'
				AND owner_uid = '$owner_uid'");
			$cat_title = db_fetch_result($result, 0, "title");
		}

		if ($cat_title) $out .= "<outline title=\"$cat_title\">\n";

		$result = db_query($link, "SELECT id,title
			FROM ttrss_feed_categories WHERE
			$cat_qpart AND owner_uid = '$owner_uid' ORDER BY order_id, title");

		while ($line = db_fetch_assoc($result)) {
			$title = htmlspecialchars($line["title"]);
			$out .= opml_export_category($link, $owner_uid, $line["id"], $hide_private_feeds);
		}

		$feeds_result = db_query($link, "select title, feed_url, site_url
				from ttrss_feeds where $feed_cat_qpart AND owner_uid = '$owner_uid' AND $hide_qpart
				order by order_id, title");

			while ($fline = db_fetch_assoc($feeds_result)) {
				$title = htmlspecialchars($fline["title"]);
				$url = htmlspecialchars($fline["feed_url"]);
				$site_url = htmlspecialchars($fline["site_url"]);

				if ($site_url) {
					$html_url_qpart = "htmlUrl=\"$site_url\"";
				} else {
					$html_url_qpart = "";
				}

				$out .= "<outline text=\"$title\" xmlUrl=\"$url\" $html_url_qpart/>\n";
			}

		if ($cat_title) $out .= "</outline>\n";

		return $out;
	}

	function opml_export($link, $name, $owner_uid, $hide_private_feeds=false, $include_settings=true) {
		if (!isset($_REQUEST["debug"])) {
			header("Content-type: application/xml+opml");
			header("Content-Disposition: attachment; filename=" . $name );
		} else {
			header("Content-type: text/xml");
		}

		$out = "<?xml version=\"1.0\" encoding=\"utf-8\"?".">";

		$out .= "<opml version=\"1.0\">";
		$out .= "<head>
			<dateCreated>" . date("r", time()) . "</dateCreated>
			<title>Tiny Tiny RSS Feed Export</title>
		</head>";
		$out .= "<body>";

		$out .= opml_export_category($link, $owner_uid, false, $hide_private_feeds);

		# export tt-rss settings

		if ($include_settings) {
			$out .= "<outline title=\"tt-rss-prefs\" schema-version=\"".SCHEMA_VERSION."\">";

			$result = db_query($link, "SELECT pref_name, value FROM ttrss_user_prefs WHERE
			   profile IS NULL AND owner_uid = " . $_SESSION["uid"] . " ORDER BY pref_name");

			while ($line = db_fetch_assoc($result)) {

				$name = $line["pref_name"];
				$value = htmlspecialchars($line["value"]);

				$out .= "<outline pref-name=\"$name\" value=\"$value\">";

				$out .= "</outline>";

			}

			$out .= "</outline>";

			$out .= "<outline title=\"tt-rss-labels\" schema-version=\"".SCHEMA_VERSION."\">";

			$result = db_query($link, "SELECT * FROM ttrss_labels2 WHERE
				owner_uid = " . $_SESSION['uid']);

			while ($line = db_fetch_assoc($result)) {
				$name = htmlspecialchars($line['caption']);
				$fg_color = htmlspecialchars($line['fg_color']);
				$bg_color = htmlspecialchars($line['bg_color']);

				$out .= "<outline label-name=\"$name\" label-fg-color=\"$fg_color\" label-bg-color=\"$bg_color\"/>";

			}

			$out .= "</outline>";

			$out .= "<outline title=\"tt-rss-filters\" schema-version=\"".SCHEMA_VERSION."\">";

			$result = db_query($link, "SELECT filter_type,
					reg_exp,
					action_id,
					enabled,
					action_param,
					inverse,
					filter_param,
					cat_filter,
					ttrss_feeds.feed_url AS feed_url,
					ttrss_feed_categories.title AS cat_title
					FROM ttrss_filters
						LEFT JOIN ttrss_feeds ON (feed_id = ttrss_feeds.id)
						LEFT JOIN ttrss_feed_categories ON (ttrss_filters.cat_id = ttrss_feed_categories.id)
					WHERE
						ttrss_filters.owner_uid = " . $_SESSION['uid']);

			while ($line = db_fetch_assoc($result)) {
				$name = htmlspecialchars($line['reg_exp']);

				foreach (array('enabled', 'inverse', 'cat_filter') as $b) {
					$line[$b] = sql_bool_to_bool($line[$b]);
				}

				$filter = json_encode($line);

				$out .= "<outline filter-name=\"$name\">$filter</outline>";

			}


			$out .= "</outline>";
		}

		$out .= "</body></opml>";

		// Format output.
		$doc = new DOMDocument();
		$doc->formatOutput = true;
		$doc->preserveWhiteSpace = false;
		$doc->loadXML($out);
		$res = $doc->saveXML();

		// saveXML uses a two-space indent.  Change to tabs.
		$res = preg_replace_callback('/^(?:  )+/mu',
			create_function(
				'$matches',
				'return str_repeat("\t", intval(strlen($matches[0])/2));'),
			$res);

		print $res;
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

		opml_notice(__("Importing OPML..."));

		opml_import_domdoc($link, $owner_uid);

		print "<br><form method=\"GET\" action=\"prefs.php\">
			<input type=\"submit\" value=\"".__("Return to preferences")."\">
			</form>";

		print "</body></html>";

	}

//	if ($link) db_close($link);

	function opml_notice($msg) {
		print "$msg<br/>";
	}

?>

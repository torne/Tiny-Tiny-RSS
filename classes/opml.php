<?php
class Opml extends Handler_Protected {

	function csrf_ignore($method) {
		$csrf_ignored = array("export", "import");

		return array_search($method, $csrf_ignored) !== false;
	}

	function export() {
		$output_name = $_REQUEST["filename"];
		if (!$output_name) $output_name = "TinyTinyRSS.opml";

		$show_settings = $_REQUEST["settings"];

		$owner_uid = $_SESSION["uid"];
		return $this->opml_export($output_name, $owner_uid, false, ($show_settings == 1));
	}

	function import() {
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

		add_feed_category($this->link, "Imported feeds");

		$this->opml_notice(__("Importing OPML..."));
		$this->opml_import($owner_uid);

		print "<br><form method=\"GET\" action=\"prefs.php\">
			<input type=\"submit\" value=\"".__("Return to preferences")."\">
			</form>";

		print "</body></html>";


	}

	// Export

	private function opml_export_category($owner_uid, $cat_id, $hide_private_feeds=false) {

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
			$result = db_query($this->link, "SELECT title FROM ttrss_feed_categories WHERE id = '$cat_id'
				AND owner_uid = '$owner_uid'");
			$cat_title = htmlspecialchars(db_fetch_result($result, 0, "title"));
		}

		if ($cat_title) $out .= "<outline text=\"$cat_title\">\n";

		$result = db_query($this->link, "SELECT id,title
			FROM ttrss_feed_categories WHERE
			$cat_qpart AND owner_uid = '$owner_uid' ORDER BY order_id, title");

		while ($line = db_fetch_assoc($result)) {
			$title = htmlspecialchars($line["title"]);
			$out .= $this->opml_export_category($owner_uid, $line["id"], $hide_private_feeds);
		}

		$feeds_result = db_query($this->link, "select title, feed_url, site_url
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

	function opml_export($name, $owner_uid, $hide_private_feeds=false, $include_settings=true) {
		if (!$owner_uid) return;

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

		$out .= $this->opml_export_category($owner_uid, false, $hide_private_feeds);

		# export tt-rss settings

		if ($include_settings) {
			$out .= "<outline text=\"tt-rss-prefs\" schema-version=\"".SCHEMA_VERSION."\">";

			$result = db_query($this->link, "SELECT pref_name, value FROM ttrss_user_prefs WHERE
			   profile IS NULL AND owner_uid = " . $_SESSION["uid"] . " ORDER BY pref_name");

			while ($line = db_fetch_assoc($result)) {
				$name = $line["pref_name"];
				$value = htmlspecialchars($line["value"]);

				$out .= "<outline pref-name=\"$name\" value=\"$value\"/>";
			}

			$out .= "</outline>";

			$out .= "<outline text=\"tt-rss-labels\" schema-version=\"".SCHEMA_VERSION."\">";

			$result = db_query($this->link, "SELECT * FROM ttrss_labels2 WHERE
				owner_uid = " . $_SESSION['uid']);

			while ($line = db_fetch_assoc($result)) {
				$name = htmlspecialchars($line['caption']);
				$fg_color = htmlspecialchars($line['fg_color']);
				$bg_color = htmlspecialchars($line['bg_color']);

				$out .= "<outline label-name=\"$name\" label-fg-color=\"$fg_color\" label-bg-color=\"$bg_color\"/>";

			}

			$out .= "</outline>";

			$out .= "<outline text=\"tt-rss-filters\" schema-version=\"".SCHEMA_VERSION."\">";

			$result = db_query($this->link, "SELECT * FROM ttrss_filters2
				WHERE owner_uid = ".$_SESSION["uid"]." ORDER BY id");

			while ($line = db_fetch_assoc($result)) {
				foreach (array('enabled', 'match_any_rule') as $b) {
					$line[$b] = sql_bool_to_bool($line[$b]);
				}

				$line["rules"] = array();
				$line["actions"] = array();

				$tmp_result = db_query($this->link, "SELECT * FROM ttrss_filters2_rules
					WHERE filter_id = ".$line["id"]);

				while ($tmp_line = db_fetch_assoc($tmp_result)) {
					unset($tmp_line["id"]);
					unset($tmp_line["filter_id"]);

					$cat_filter = sql_bool_to_bool($tmp_line["cat_filter"]);

					if ($cat_filter && $tmp_line["cat_id"] || $tmp_line["feed_id"]) {
						$tmp_line["feed"] = getFeedTitle($this->link,
							$cat_filter ? $tmp_line["cat_id"] : $tmp_line["feed_id"],
							$cat_filter);
					} else {
						$tmp_line["feed"] = "";
					}

					$tmp_line["cat_filter"] = sql_bool_to_bool($tmp_line["cat_filter"]);

					unset($tmp_line["feed_id"]);
					unset($tmp_line["cat_id"]);

					array_push($line["rules"], $tmp_line);
				}

				$tmp_result = db_query($this->link, "SELECT * FROM ttrss_filters2_actions
					WHERE filter_id = ".$line["id"]);

				while ($tmp_line = db_fetch_assoc($tmp_result)) {
					unset($tmp_line["id"]);
					unset($tmp_line["filter_id"]);

					array_push($line["actions"], $tmp_line);
				}

				unset($line["id"]);
				unset($line["owner_uid"]);
				$filter = json_encode($line);

				$out .= "<outline filter-type=\"2\"><![CDATA[$filter]]></outline>";

			}


			$out .= "</outline>";
		}

		$out .= "</body></opml>";

		// Format output.
		$doc = new DOMDocument();
		$doc->formatOutput = true;
		$doc->preserveWhiteSpace = false;
		$doc->loadXML($out);

		$xpath = new DOMXpath($doc);
		$outlines = $xpath->query("//outline[@title]");

		// cleanup empty categories
		foreach ($outlines as $node) {
			if ($node->getElementsByTagName('outline')->length == 0)
				$node->parentNode->removeChild($node);
		}

		$res = $doc->saveXML();

/*		// saveXML uses a two-space indent.  Change to tabs.
		$res = preg_replace_callback('/^(?:  )+/mu',
			create_function(
				'$matches',
				'return str_repeat("\t", intval(strlen($matches[0])/2));'),
			$res); */

		print $res;
	}

	// Import

	private function opml_import_feed($doc, $node, $cat_id, $owner_uid) {
		$attrs = $node->attributes;

		$feed_title = db_escape_string($attrs->getNamedItem('text')->nodeValue);
		if (!$feed_title) $feed_title = db_escape_string($attrs->getNamedItem('title')->nodeValue);

		$feed_url = db_escape_string($attrs->getNamedItem('xmlUrl')->nodeValue);
		if (!$feed_url) $feed_url = db_escape_string($attrs->getNamedItem('xmlURL')->nodeValue);

		$site_url = db_escape_string($attrs->getNamedItem('htmlUrl')->nodeValue);

		if ($feed_url && $feed_title) {
			$result = db_query($this->link, "SELECT id FROM ttrss_feeds WHERE
				feed_url = '$feed_url' AND owner_uid = '$owner_uid'");

			if (db_num_rows($result) == 0) {
				#$this->opml_notice("[FEED] [$feed_title/$feed_url] dst_CAT=$cat_id");
				$this->opml_notice(T_sprintf("Adding feed: %s", $feed_title));

				if (!$cat_id) $cat_id = 'NULL';

				$query = "INSERT INTO ttrss_feeds
					(title, feed_url, owner_uid, cat_id, site_url, order_id) VALUES
					('$feed_title', '$feed_url', '$owner_uid',
					$cat_id, '$site_url', 0)";
				db_query($this->link, $query);

			} else {
				$this->opml_notice(T_sprintf("Duplicate feed: %s", $feed_title));
			}
		}
	}

	private function opml_import_label($doc, $node, $owner_uid) {
		$attrs = $node->attributes;
		$label_name = db_escape_string($attrs->getNamedItem('label-name')->nodeValue);

		if ($label_name) {
			$fg_color = db_escape_string($attrs->getNamedItem('label-fg-color')->nodeValue);
			$bg_color = db_escape_string($attrs->getNamedItem('label-bg-color')->nodeValue);

			if (!label_find_id($this->link, $label_name, $_SESSION['uid'])) {
				$this->opml_notice(T_sprintf("Adding label %s", htmlspecialchars($label_name)));
				label_create($this->link, $label_name, $fg_color, $bg_color, $owner_uid);
			} else {
				$this->opml_notice(T_sprintf("Duplicate label: %s", htmlspecialchars($label_name)));
			}
		}
	}

	private function opml_import_preference($doc, $node, $owner_uid) {
		$attrs = $node->attributes;
		$pref_name = db_escape_string($attrs->getNamedItem('pref-name')->nodeValue);

		if ($pref_name) {
			$pref_value = db_escape_string($attrs->getNamedItem('value')->nodeValue);

			$this->opml_notice(T_sprintf("Setting preference key %s to %s",
				$pref_name, $pref_value));

			set_pref($this->link, $pref_name, $pref_value);
		}
	}

	private function opml_import_filter($doc, $node, $owner_uid) {
		$attrs = $node->attributes;

		$filter_type = db_escape_string($attrs->getNamedItem('filter-type')->nodeValue);

		if ($filter_type == '2') {
			$filter = json_decode($node->nodeValue, true);

			if ($filter) {
				$match_any_rule = bool_to_sql_bool($filter["match_any_rule"]);
				$enabled = bool_to_sql_bool($filter["enabled"]);

				db_query($this->link, "BEGIN");

				db_query($this->link, "INSERT INTO ttrss_filters2 (match_any_rule,enabled,owner_uid)
					VALUES ($match_any_rule, $enabled,".$_SESSION["uid"].")");

				$result = db_query($this->link, "SELECT MAX(id) AS id FROM ttrss_filters2 WHERE
					owner_uid = ".$_SESSION["uid"]);
				$filter_id = db_fetch_result($result, 0, "id");

				if ($filter_id) {
					$this->opml_notice(T_sprintf("Adding filter..."));

					foreach ($filter["rules"] as $rule) {
						$feed_id = "NULL";
						$cat_id = "NULL";

						if (!$rule["cat_filter"]) {
							$tmp_result = db_query($this->link, "SELECT id FROM ttrss_feeds
								WHERE title = '".db_escape_string($rule["feed"])."' AND owner_uid = ".$_SESSION["uid"]);
							if (db_num_rows($tmp_result) > 0) {
								$feed_id = db_fetch_result($tmp_result, 0, "id");
							}
						} else {
							$tmp_result = db_query($this->link, "SELECT id FROM ttrss_feed_categories
								WHERE title = '".db_escape_string($rule["feed"])."' AND owner_uid = ".$_SESSION["uid"]);

							if (db_num_rows($tmp_result) > 0) {
								$cat_id = db_fetch_result($tmp_result, 0, "id");
							}
						}

						$cat_filter = bool_to_sql_bool($rule["cat_filter"]);
						$reg_exp = db_escape_string($rule["reg_exp"]);
						$filter_type = (int)$rule["filter_type"];

						db_query($this->link, "INSERT INTO ttrss_filters2_rules (feed_id,cat_id,filter_id,filter_type,reg_exp,cat_filter)
							VALUES ($feed_id, $cat_id, $filter_id, $filter_type, '$reg_exp', $cat_filter)");
					}

					foreach ($filter["actions"] as $action) {

						$action_id = (int)$action["action_id"];
						$action_param = db_escape_string($action["action_param"]);

						db_query($this->link, "INSERT INTO ttrss_filters2_actions (filter_id,action_id,action_param)
							VALUES ($filter_id, $action_id, '$action_param')");
					}
				}

				db_query($this->link, "COMMIT");
			}
		}
	}

	private function opml_import_category($doc, $root_node, $owner_uid, $parent_id) {
		$body = $doc->getElementsByTagName('body');

		$default_cat_id = (int) get_feed_category($this->link, 'Imported feeds', false);

		if ($root_node) {
			$cat_title = db_escape_string($root_node->attributes->getNamedItem('text')->nodeValue);

			if (!$cat_title)
				$cat_title = db_escape_string($root_node->attributes->getNamedItem('title')->nodeValue);

			if (!in_array($cat_title, array("tt-rss-filters", "tt-rss-labels", "tt-rss-prefs"))) {
				$cat_id = get_feed_category($this->link, $cat_title, $parent_id);
				db_query($this->link, "BEGIN");
				if ($cat_id === false) {
					add_feed_category($this->link, $cat_title, $parent_id);
					$cat_id = get_feed_category($this->link, $cat_title, $parent_id);
				}
				db_query($this->link, "COMMIT");
			} else {
				$cat_id = 0;
			}

			$outlines = $root_node->childNodes;

		} else {
			$xpath = new DOMXpath($doc);
			$outlines = $xpath->query("//opml/body/outline");

			$cat_id = 0;
		}

		#$this->opml_notice("[CAT] $cat_title id: $cat_id P_id: $parent_id");
		$this->opml_notice(T_sprintf("Processing category: %s", $cat_title ? $cat_title : __("Uncategorized")));

		foreach ($outlines as $node) {
			if ($node->hasAttributes() && strtolower($node->tagName) == "outline") {
				$attrs = $node->attributes;
				$node_cat_title = db_escape_string($attrs->getNamedItem('text')->nodeValue);

				if (!$node_cat_title)
					$node_cat_title = db_escape_string($attrs->getNamedItem('title')->nodeValue);

				$node_feed_url = db_escape_string($attrs->getNamedItem('xmlUrl')->nodeValue);

				if ($node_cat_title && !$node_feed_url) {
					$this->opml_import_category($doc, $node, $owner_uid, $cat_id);
				} else {

					if (!$cat_id) {
						$dst_cat_id = $default_cat_id;
					} else {
						$dst_cat_id = $cat_id;
					}

					switch ($cat_title) {
					case "tt-rss-prefs":
						$this->opml_import_preference($doc, $node, $owner_uid);
						break;
					case "tt-rss-labels":
						$this->opml_import_label($doc, $node, $owner_uid);
						break;
					case "tt-rss-filters":
						$this->opml_import_filter($doc, $node, $owner_uid);
						break;
					default:
						$this->opml_import_feed($doc, $node, $dst_cat_id, $owner_uid);
					}
				}
			}
		}
	}

	function opml_import($owner_uid) {
		if (!$owner_uid) return;

		$debug = isset($_REQUEST["debug"]);
		$doc = false;

#		if ($debug) $doc = DOMDocument::load("/tmp/test.opml");

		if (is_file($_FILES['opml_file']['tmp_name'])) {
			$doc = DOMDocument::load($_FILES['opml_file']['tmp_name']);
		} else if (!$doc) {
			print_error(__('Error: please upload OPML file.'));
			return;
		}

		if ($doc) {
			$this->opml_import_category($doc, false, $owner_uid, false);
		} else {
			print_error(__('Error while parsing document.'));
		}
	}

	private function opml_notice($msg) {
		print "$msg<br/>";
	}

}
?>

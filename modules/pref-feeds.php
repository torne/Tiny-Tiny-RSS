<?php

	function module_pref_feeds($link) {

		global $update_intervals;
		global $purge_intervals;

		$subop = $_REQUEST["subop"];
		$quiet = $_REQUEST["quiet"];

		if ($subop == "massSubscribe") {
			$ids = split(",", db_escape_string($_GET["ids"]));

			$subscribed = array();

			foreach ($ids as $id) {
				$result = db_query($link, "SELECT feed_url,title FROM ttrss_feeds
					WHERE id = '$id'");

				$feed_url = db_escape_string(db_fetch_result($result, 0, "feed_url"));
				$title = db_escape_string(db_fetch_result($result, 0, "title"));

				$title_orig = db_fetch_result($result, 0, "title");

				$result = db_query($link, "SELECT id FROM ttrss_feeds WHERE
					feed_url = '$feed_url' AND owner_uid = " . $_SESSION["uid"]);

				if (db_num_rows($result) == 0) {			
					$result = db_query($link,
						"INSERT INTO ttrss_feeds (owner_uid,feed_url,title,cat_id) 
						VALUES ('".$_SESSION["uid"]."', '$feed_url', '$title', NULL)");

					array_push($subscribed, $title_orig);
				}
			}

			if (count($subscribed) > 0) {
				$msg = "<b>Subscribed to feeds:</b>".
					"<ul class=\"nomarks\">";

				foreach ($subscribed as $title) {
					$msg .= "<li>$title</li>";
				}
				$msg .= "</ul>";

				print format_notice($msg);
			}
		}		

		if ($subop == "browse") {

			if (!ENABLE_FEED_BROWSER) {
				print "Feed browser is administratively disabled.";
				return;
			}

			print "<div id=\"infoBoxTitle\">Other feeds: Top 25</div>";
			
			print "<div class=\"infoBoxContents\">";

			print "<p>Showing top 25 registered feeds, sorted by popularity:</p>";

#			$result = db_query($link, "SELECT feed_url,count(id) AS subscribers 
#				FROM ttrss_feeds 
#				WHERE auth_login = '' AND auth_pass = '' AND private = false
#				GROUP BY feed_url ORDER BY subscribers DESC LIMIT 25");

			$owner_uid = $_SESSION["uid"];

			$result = db_query($link, "SELECT feed_url,COUNT(id) AS subscribers
		  		FROM ttrss_feeds WHERE (SELECT COUNT(id) = 0 FROM ttrss_feeds AS tf 
					WHERE tf.feed_url = ttrss_feeds.feed_url 
						AND owner_uid = '$owner_uid') GROUP BY feed_url 
							ORDER BY subscribers DESC LIMIT 25");

			print "<ul class='browseFeedList' id='browseFeedList'>";

			$feedctr = 0;
			
			while ($line = db_fetch_assoc($result)) {
				$feed_url = $line["feed_url"];
				$subscribers = $line["subscribers"];

				$det_result = db_query($link, "SELECT site_url,title,id 
					FROM ttrss_feeds WHERE feed_url = '$feed_url' LIMIT 1");

				$details = db_fetch_assoc($det_result);
			
				$icon_file = ICONS_DIR . "/" . $details["id"] . ".ico";

				if (file_exists($icon_file) && filesize($icon_file) > 0) {
						$feed_icon = "<img class=\"tinyFeedIcon\"	src=\"" . ICONS_URL . 
							"/".$details["id"].".ico\">";
				} else {
					$feed_icon = "<img class=\"tinyFeedIcon\" src=\"images/blank_icon.gif\">";
				}

				$check_box = "<input onclick='toggleSelectListRow(this)' class='feedBrowseCB' 
					type=\"checkbox\" id=\"FBCHK-" . $details["id"] . "\">";

				$class = ($feedctr % 2) ? "even" : "odd";

				print "<li class='$class' id=\"FBROW-".$details["id"]."\">$check_box".
					"$feed_icon " . db_unescape_string($details["title"]) . 
					"&nbsp;<span class='subscribers'>($subscribers)</span></li>";

					++$feedctr;
			}

			if ($feedctr == 0) {
				print "<li>No feeds found to subscribe.</li>";
			}

			print "</ul>";

			print "<div align='center'>
				<input type=\"submit\" class=\"button\" 
				onclick=\"feedBrowserSubscribe()\" value=\"Subscribe\">
				<input type='submit' class='button'			
				onclick=\"closeInfoBox()\" value=\"Cancel\"></div>";

			print "</div>";
			return;
		}

		if ($subop == "editfeed") {
			$feed_id = db_escape_string($_REQUEST["id"]);

			$result = db_query($link, 
				"SELECT * FROM ttrss_feeds WHERE id = '$feed_id' AND
					owner_uid = " . $_SESSION["uid"]);

			$title = htmlspecialchars(db_unescape_string(db_fetch_result($result,
				0, "title")));

			$icon_file = ICONS_DIR . "/$feed_id.ico";
	
			if (file_exists($icon_file) && filesize($icon_file) > 0) {
					$feed_icon = "<img width=\"16\" height=\"16\"
						src=\"" . ICONS_URL . "/$feed_id.ico\">";
			} else {
				$feed_icon = "";
			}

			print "<div id=\"infoBoxTitle\">Feed editor</div>";

			print "<div class=\"infoBoxContents\">";

			print "<form id=\"edit_feed_form\">";	

			print "<input type=\"hidden\" name=\"id\" value=\"$feed_id\">";
			print "<input type=\"hidden\" name=\"op\" value=\"pref-feeds\">";
			print "<input type=\"hidden\" name=\"subop\" value=\"editSave\">";

			print "<table width='100%'>";

			print "<tr><td>Title:</td>";
			print "<td><input class=\"iedit\" onkeypress=\"return filterCR(event, feedEditSave)\"
				name=\"title\" value=\"$title\"></td></tr>";

			$feed_url = db_fetch_result($result, 0, "feed_url");
			$feed_url = htmlspecialchars(db_unescape_string(db_fetch_result($result,
				0, "feed_url")));
				
			print "<tr><td>Feed URL:</td>";
			print "<td><input class=\"iedit\" onkeypress=\"return filterCR(event, feedEditSave)\"
				name=\"feed_url\" value=\"$feed_url\"></td></tr>";

			if (get_pref($link, 'ENABLE_FEED_CATS')) {

				$cat_id = db_fetch_result($result, 0, "cat_id");

				print "<tr><td>Category:</td>";
				print "<td>";

				$parent_feed = db_fetch_result($result, 0, "parent_feed");

				if (sprintf("%d", $parent_feed) > 0) {
					$disabled = "disabled";
				} else {
					$disabled = "";
				}

				print_feed_cat_select($link, "cat_id", $cat_id, "class=\"iedit\" $disabled");

				print "</td>";
				print "</td></tr>";
	
			}

			$update_interval = db_fetch_result($result, 0, "update_interval");

			print "<tr><td>Update Interval:</td>";

			print "<td>";

			print_select_hash("update_interval", $update_interval, $update_intervals,
				"class=\"iedit\"");

			print "</td>";

			print "<tr><td>Link to:</td><td>";

			$tmp_result = db_query($link, "SELECT COUNT(id) AS count
				FROM ttrss_feeds WHERE parent_feed = '$feed_id'");

			$linked_count = db_fetch_result($tmp_result, 0, "count");

			$parent_feed = db_fetch_result($result, 0, "parent_feed");

			if ($linked_count > 0) {
				$disabled = "disabled";
			} else {
				$disabled = "";
			}

			print "<select class=\"iedit\" $disabled name=\"parent_feed\">";
			
			print "<option value=\"0\">Not linked</option>";

			if (get_pref($link, 'ENABLE_FEED_CATS')) {
				if ($cat_id) {
					$cat_qpart = "AND cat_id = '$cat_id'";
				} else {
					$cat_qpart = "AND cat_id IS NULL";
				}
			}

			$tmp_result = db_query($link, "SELECT id,title FROM ttrss_feeds
				WHERE id != '$feed_id' AND owner_uid = ".$_SESSION["uid"]." AND
			  		(SELECT COUNT(id) FROM ttrss_feeds AS T2 WHERE T2.id = ttrss_feeds.parent_feed) = 0
					$cat_qpart ORDER BY title");

				if (db_num_rows($tmp_result) > 0) {
					print "<option disabled>--------</option>";
				}

				while ($tmp_line = db_fetch_assoc($tmp_result)) {
					if ($tmp_line["id"] == $parent_feed) {
						$is_selected = "selected";
					} else {
						$is_selected = "";
					}
					printf("<option $is_selected value='%d'>%s</option>", 
						$tmp_line["id"], $tmp_line["title"]);
				}

			print "</select>";
			print "</td></tr>";

			$purge_interval = db_fetch_result($result, 0, "purge_interval");

			print "<tr><td>Article purging:</td>";

			print "<td>";

			print_select_hash("purge_interval", $purge_interval, $purge_intervals, 
				"class=\"iedit\"");
			
			print "</td>";

			$auth_login = escape_for_form(db_fetch_result($result, 0, "auth_login"));

			print "<tr><td>Login:</td>";
			print "<td><input class=\"iedit\" onkeypress=\"return filterCR(event, feedEditSave)\"
				name=\"auth_login\" value=\"$auth_login\"></td></tr>";

			$auth_pass = escape_for_form(db_fetch_result($result, 0, "auth_pass"));

			print "<tr><td>Password:</td>";
			print "<td><input class=\"iedit\" type=\"password\" name=\"auth_pass\" 
				onkeypress=\"return filterCR(event, feedEditSave)\"
				value=\"$auth_pass\"></td></tr>";

			$private = sql_bool_to_bool(db_fetch_result($result, 0, "private"));

			if ($private) {
				$checked = "checked";
			} else {
				$checked = "";
			}

			print "<tr><td valign='top'>Options:</td>";
			print "<td><input type=\"checkbox\" name=\"private\" id=\"private\" 
				$checked><label for=\"private\">Hide from \"Other Feeds\"</label>";

			$rtl_content = sql_bool_to_bool(db_fetch_result($result, 0, "rtl_content"));

			if ($rtl_content) {
				$checked = "checked";
			} else {
				$checked = "";
			}

			print "<br><input type=\"checkbox\" id=\"rtl_content\" name=\"rtl_content\"
				$checked><label for=\"rtl_content\">Right-to-left content</label>";

			$hidden = sql_bool_to_bool(db_fetch_result($result, 0, "hidden"));

			if ($hidden) {
				$checked = "checked";
			} else {
				$checked = "";
			}

			print "<br><input type=\"checkbox\" id=\"hidden\" name=\"hidden\"
				$checked><label for=\"hidden\">Hide from my feed list</label>";

			$include_in_digest = sql_bool_to_bool(db_fetch_result($result, 0, "include_in_digest"));

			if ($include_in_digest) {
				$checked = "checked";
			} else {
				$checked = "";
			}

			print "<br><input type=\"checkbox\" id=\"include_in_digest\" 
				name=\"include_in_digest\"
				$checked><label for=\"include_in_digest\">Include in e-mail digest</label>";

			print "</td></tr>";

			print "</table>";

			print "</form>";

			print "<div align='right'>
				<input type=\"submit\" class=\"button\" 
				onclick=\"return feedEditSave()\" value=\"Save\">
				<input type='submit' class='button'			
				onclick=\"return feedEditCancel()\" value=\"Cancel\"></div>";

			print "</div>";

			return;
		}

		if ($subop == "editSave") {

			$feed_title = db_escape_string(trim($_POST["title"]));
			$feed_link = db_escape_string(trim($_POST["feed_url"]));
			$upd_intl = db_escape_string($_POST["update_interval"]);
			$purge_intl = db_escape_string($_POST["purge_interval"]);
			$feed_id = db_escape_string($_POST["id"]);
			$cat_id = db_escape_string($_POST["cat_id"]);
			$auth_login = db_escape_string(trim($_POST["auth_login"]));
			$auth_pass = db_escape_string(trim($_POST["auth_pass"]));
			$parent_feed = db_escape_string($_POST["parent_feed"]);
			$private = checkbox_to_sql_bool(db_escape_string($_POST["private"]));
			$rtl_content = checkbox_to_sql_bool(db_escape_string($_POST["rtl_content"]));
			$hidden = checkbox_to_sql_bool(db_escape_string($_POST["hidden"]));
			$include_in_digest = checkbox_to_sql_bool(
				db_escape_string($_POST["include_in_digest"]));

			if (get_pref($link, 'ENABLE_FEED_CATS')) {			
				if ($cat_id && $cat_id != 0) {
					$category_qpart = "cat_id = '$cat_id',";
					$category_qpart_nocomma = "cat_id = '$cat_id'";
				} else {
					$category_qpart = 'cat_id = NULL,';
					$category_qpart_nocomma = 'cat_id = NULL';
				}
			} else {
				$category_qpart = "";
				$category_qpart_nocomma = "";
			}

			if ($parent_feed && $parent_feed != 0) {
				$parent_qpart = "parent_feed = '$parent_feed'";
			} else {
				$parent_qpart = 'parent_feed = NULL';
			}

			$result = db_query($link, "UPDATE ttrss_feeds SET 
				$category_qpart $parent_qpart,
				title = '$feed_title', feed_url = '$feed_link',
				update_interval = '$upd_intl',
				purge_interval = '$purge_intl',
				auth_login = '$auth_login',
				auth_pass = '$auth_pass',
				private = $private,
				rtl_content = $rtl_content,
				hidden = $hidden,
				include_in_digest = $include_in_digest
				WHERE id = '$feed_id' AND owner_uid = " . $_SESSION["uid"]);

			if (get_pref($link, 'ENABLE_FEED_CATS')) {
				# update linked feed categories
				$result = db_query($link, "UPDATE ttrss_feeds SET
					$category_qpart_nocomma WHERE parent_feed = '$feed_id' AND
					owner_uid = " . $_SESSION["uid"]);
			}
		}

		if ($subop == "remove") {

			if (!WEB_DEMO_MODE) {

				$ids = split(",", db_escape_string($_GET["ids"]));

				foreach ($ids as $id) {

					if ($id > 0) {

						db_query($link, "DELETE FROM ttrss_feeds 
							WHERE id = '$id' AND owner_uid = " . $_SESSION["uid"]);

						$icons_dir = ICONS_DIR;
					
						if (file_exists($icons_dir . "/$id.ico")) {
							unlink($icons_dir . "/$id.ico");
						}
					} else if ($id < -10) {

						$label_id = -$id - 11;

						db_query($link, "DELETE FROM ttrss_labels
						  	WHERE	id = '$label_id' AND owner_uid = " . $_SESSION["uid"]);
					}
				}
			}
		}

		if ($subop == "add") {
		
			if (!WEB_DEMO_MODE) {

				$feed_url = db_escape_string(trim($_GET["feed_url"]));
				$cat_id = db_escape_string($_GET["cat_id"]);
				$p_from = db_escape_string($_GET["from"]);

				if ($p_from != 'tt-rss') {
					print "<html>
						<head>
							<title>Tiny Tiny RSS - Subscribe to feed...</title>
							<link rel=\"stylesheet\" type=\"text/css\" href=\"quicksub.css\">
						</head>
						<body>
						<img class=\"logo\" src=\"images/ttrss_logo.png\"
					  		alt=\"Tiny Tiny RSS\"/>	
						<h1>Subscribe to feed...</h1>
						<div class=\"content\">";
				}

				if (subscribe_to_feed($link, $feed_url, $cat_id)) {
					print format_notice("Subscribed to <b>$feed_url</b>.");
				} else {
					print format_warning("Already subscribed to <b>$feed_url</b>.");
				}

				if ($p_from != 'tt-rss') {
					$tt_uri = 'http://' . $_SERVER['SERVER_NAME'] . 
						preg_replace('/backend\.php.*$/', 
							'tt-rss.php', $_SERVER["REQUEST_URI"]);

					$tp_uri = 'http://' . $_SERVER['SERVER_NAME'] . 
						preg_replace('/backend\.php.*$/', 
							'prefs.php', $_SERVER["REQUEST_URI"]);

					print "<p><a href='$tt_uri'>Return to Tiny Tiny RSS</a> |";

					$result = db_query($link, "SELECT id FROM ttrss_feeds WHERE
						feed_url = '$feed_url' AND owner_uid = " . $_SESSION["uid"]);

					$feed_id = db_fetch_result($result, 0, "id");

					if ($feed_id) {
						print "<a href='$tp_uri?tab=feedConfig&subop=editFeed:$feed_id'>
							Edit subscription options</a> | ";
					}

					print "<a href='javascript:window.close()'>Close this window</a>.</p>";

					print "</div></body></html>";
					return;
				}
			}
		}

		if ($subop == "categorize") {

			if (!WEB_DEMO_MODE) {

				$ids = split(",", db_escape_string($_GET["ids"]));

				$cat_id = db_escape_string($_GET["cat_id"]);

				if ($cat_id == 0) {
					$cat_id_qpart = 'NULL';
				} else {
					$cat_id_qpart = "'$cat_id'";
				}

				db_query($link, "BEGIN");

				foreach ($ids as $id) {
				
					db_query($link, "UPDATE ttrss_feeds SET cat_id = $cat_id_qpart
						WHERE id = '$id' AND parent_feed IS NULL
					  	AND owner_uid = " . $_SESSION["uid"]);

					# update linked feed categories
					db_query($link, "UPDATE ttrss_feeds SET
						cat_id = $cat_id_qpart WHERE parent_feed = '$id' AND 
						owner_uid = " . $_SESSION["uid"]);

				}

				db_query($link, "COMMIT");
			}

		}

		if ($subop == "editCats") {

			print "<div id=\"infoBoxTitle\">Category editor</div>";
			
			print "<div class=\"infoBoxContents\">";

			$action = $_REQUEST["action"];

			if ($action == "save") {

				$cat_title = db_escape_string(trim($_GET["title"]));
				$cat_id = db_escape_string($_GET["id"]);
	
				$result = db_query($link, "UPDATE ttrss_feed_categories SET
					title = '$cat_title' WHERE id = '$cat_id' AND owner_uid = ".$_SESSION["uid"]);
	
			}

			if ($action == "add") {

				if (!WEB_DEMO_MODE) {
	
					$feed_cat = db_escape_string(trim($_GET["cat"]));
	
					$result = db_query($link,
						"SELECT id FROM ttrss_feed_categories
						WHERE title = '$feed_cat' AND owner_uid = ".$_SESSION["uid"]);
	
					if (db_num_rows($result) == 0) {
						
						$result = db_query($link,
							"INSERT INTO ttrss_feed_categories (owner_uid,title) 
							VALUES ('".$_SESSION["uid"]."', '$feed_cat')");
	
					} else {
	
						print format_warning("Category <b>$feed_cat</b> already exists in the database.");
					}

				}
			}

			if ($action == "remove") {
	
				if (!WEB_DEMO_MODE) {
	
					$ids = split(",", db_escape_string($_GET["ids"]));
	
					foreach ($ids as $id) {
	
						db_query($link, "BEGIN");
	
						$result = db_query($link, 
							"SELECT count(id) as num_feeds FROM ttrss_feeds 
								WHERE cat_id = '$id'");
	
						$num_feeds = db_fetch_result($result, 0, "num_feeds");
	
						if ($num_feeds == 0) {
							db_query($link, "DELETE FROM ttrss_feed_categories
								WHERE id = '$id' AND owner_uid = " . $_SESSION["uid"]);
						} else {
	
							print format_warning("Unable to delete non empty feed categories.");
								
						}
	
						db_query($link, "COMMIT");
					}
				}
			}

			print "<div class=\"prefGenericAddBox\">
				<input id=\"fadd_cat\" 
					onkeypress=\"return filterCR(event, addFeedCat)\"
					onkeyup=\"toggleSubmitNotEmpty(this, 'catadd_submit_btn')\"
					onchange=\"toggleSubmitNotEmpty(this, 'catadd_submit_btn')\"
					size=\"40\">&nbsp;
				<input 
					type=\"submit\" class=\"button\" disabled=\"true\" id=\"catadd_submit_btn\"
					onclick=\"javascript:addFeedCat()\" value=\"Create category\"></div>";
	
			$result = db_query($link, "SELECT title,id FROM ttrss_feed_categories
				WHERE owner_uid = ".$_SESSION["uid"]."
				ORDER BY title");

			print "<p>";

			if (db_num_rows($result) != 0) {

				print "<table width=\"100%\" class=\"prefFeedCatList\" 
					cellspacing=\"0\">";

				print "<tr><td class=\"selectPrompt\" colspan=\"8\">
				Select: 
					<a href=\"javascript:selectPrefRows('fcat', true)\">All</a>,
					<a href=\"javascript:selectPrefRows('fcat', false)\">None</a>
					</td></tr>";

				print "</table>";

				print "<div class=\"prefFeedCatHolder\">";

				print "<form id=\"feed_cat_edit_form\">";

				print "<table width=\"100%\" class=\"prefFeedCatList\" 
					cellspacing=\"0\" id=\"prefFeedCatList\">";

/*				print "<tr class=\"title\">
							<td width=\"5%\">&nbsp;</td><td width=\"80%\">Title</td>
							</tr>"; */
						
				$lnum = 0;
				
				while ($line = db_fetch_assoc($result)) {
		
					$class = ($lnum % 2) ? "even" : "odd";
		
					$cat_id = $line["id"];
		
					$edit_cat_id = $_GET["id"];

					if ($action == "edit" && $cat_id == $edit_cat_id) {
						$class .= "Selected";
						$this_row_id = "";
					} else if ($action == "edit" && $cat_id != $edit_cat_id) {
						$class .= "Grayed";
						$this_row_id = "";
					} else {
						$this_row_id = "id=\"FCATR-$cat_id\"";
					}
		
					print "<tr class=\"$class\" $this_row_id>";
		
					$edit_title = htmlspecialchars(db_unescape_string($line["title"]));
		
					if (!$edit_cat_id || $action != "edit") {
		
						print "<td width='5%' align='center'><input 
							onclick='toggleSelectPrefRow(this, \"fcat\");' 
							type=\"checkbox\" id=\"FCCHK-".$line["id"]."\"></td>";
		
						print "<td><a href=\"javascript:editFeedCat($cat_id);\">" . 
							$edit_title . "</a></td>";		
		
					} else if ($cat_id != $edit_cat_id) {
		
						print "<td width='5%' align='center'><input disabled=\"true\" type=\"checkbox\" 
							id=\"FRCHK-".$line["id"]."\"></td>";
		
						print "<td>$edit_title</td>";		
		
					} else {
		
						print "<td width='5%' align='center'><input disabled=\"true\" type=\"checkbox\" checked>";
						
						print "<input type=\"hidden\" name=\"id\" value=\"$cat_id\">";
						print "<input type=\"hidden\" name=\"op\" value=\"pref-feeds\">";
						print "<input type=\"hidden\" name=\"subop\" value=\"editCats\">";
						print "<input type=\"hidden\" name=\"action\" value=\"save\">";
				
						print "</td>";
		
						print "<td><input onkeypress=\"return filterCR(event, feedCatEditSave)\"
							name=\"title\" size=\"40\" value=\"$edit_title\"></td>";
						
					}
					
					print "</tr>";
		
					++$lnum;
				}
	
				print "</table>";

				print "</form>";

				print "</div>";

			} else {
				print "<p>No feed categories defined.</p>";
			}

			print "<div style='float : right'>
				<input type='submit' class='button'			
				onclick=\"selectTab('feedConfig')\" value=\"Close this window\"></div>";

			print "<div id=\"catOpToolbar\">";
	
			if ($action == "edit") {
				print "<input type=\"submit\" class=\"button\"
						onclick=\"return feedCatEditSave()\" value=\"Save\">
					<input type=\"submit\" class=\"button\"
						onclick=\"return feedCatEditCancel()\" value=\"Cancel\">";
			} else {

				print "
				<input type=\"submit\" class=\"button\" disabled=\"true\"
					onclick=\"return editSelectedFeedCat()\" value=\"Edit\">
				<input type=\"submit\" class=\"button\" disabled=\"true\"
					onclick=\"return removeSelectedFeedCats()\" value=\"Remove\">";
			}

			print "</div>";

			print "</div>";

			return;

		}

		if ($quiet) return;

		$result = db_query($link, "SELECT COUNT(id) AS num_errors
			FROM ttrss_feeds WHERE last_error != '' AND owner_uid = ".$_SESSION["uid"]);

		$num_errors = db_fetch_result($result, 0, "num_errors");

		if ($num_errors > 0) {

			print format_notice("<a href=\"javascript:showFeedsWithErrors()\">
				Some feeds have update errors (click for details)</a>");
		}

		$feed_search = db_escape_string($_GET["search"]);

		if (array_key_exists("search", $_GET)) {
			$_SESSION["prefs_feed_search"] = $feed_search;
		} else {
			$feed_search = $_SESSION["prefs_feed_search"];
		}

		print "<div class=\"feedEditSearch\">
			<input id=\"feed_search\" size=\"20\"  
				onchange=\"javascript:updateFeedList()\" value=\"$feed_search\">
			<input type=\"submit\" class=\"button\" 
				onclick=\"javascript:updateFeedList()\" value=\"Search\">
			</div>";

		print "<div class=\"prefGenericAddBox\">
			<input id=\"fadd_link\" 
				onkeyup=\"toggleSubmitNotEmpty(this, 'fadd_submit_btn')\"
				onchange=\"toggleSubmitNotEmpty(this, 'fadd_submit_btn')\"
				size=\"40\">
			<input type=\"submit\" class=\"button\"
				disabled=\"true\" id=\"fadd_submit_btn\"
				onclick=\"addFeed()\" value=\"Subscribe\">";

		if (ENABLE_FEED_BROWSER && !SINGLE_USER_MODE) {
			print " <input type=\"submit\" class=\"button\"
				onclick=\"javascript:browseFeeds()\" value=\"Top 25\">";
		}

		print "</div>";

		$feeds_sort = db_escape_string($_GET["sort"]);

		if (!$feeds_sort || $feeds_sort == "undefined") {
			$feeds_sort = $_SESSION["pref_sort_feeds"];			
			if (!$feeds_sort) $feeds_sort = "title";
		}

		$_SESSION["pref_sort_feeds"] = $feeds_sort;

		if ($feed_search) {
			$search_qpart = "(UPPER(F1.title) LIKE UPPER('%$feed_search%') OR
				UPPER(F1.feed_url) LIKE UPPER('%$feed_search%')) AND";
		} else {
			$search_qpart = "";
		}

		if (get_pref($link, 'ENABLE_FEED_CATS')) {
			$order_by_qpart = "category,$feeds_sort,title";
		} else {
			$order_by_qpart = "$feeds_sort,title";
		}

		$result = db_query($link, "SELECT 
				F1.id,
				F1.title,
				F1.feed_url,
				substring(F1.last_updated,1,16) AS last_updated,
				F1.parent_feed,
				F1.update_interval,
				F1.purge_interval,
				F1.cat_id,
				F2.title AS parent_title,
				C1.title AS category,
				F1.hidden,
				F1.include_in_digest,
				(SELECT SUBSTRING(MAX(updated),1,16) FROM ttrss_user_entries, 
					ttrss_entries WHERE ref_id = ttrss_entries.id 
					AND feed_id = F1.id) AS last_article
			FROM 
				ttrss_feeds AS F1 
				LEFT JOIN ttrss_feeds AS F2
					ON (F1.parent_feed = F2.id)
				LEFT JOIN ttrss_feed_categories AS C1
					ON (F1.cat_id = C1.id)
			WHERE 
				$search_qpart F1.owner_uid = '".$_SESSION["uid"]."' 			
			ORDER by $order_by_qpart");

		if (db_num_rows($result) != 0) {

//			print "<div id=\"infoBoxShadow\"><div id=\"infoBox\">PLACEHOLDER</div></div>";

			print "<p><table width=\"100%\" cellspacing=\"0\" 
				class=\"prefFeedList\" id=\"prefFeedList\">";
			print "<tr><td class=\"selectPrompt\" colspan=\"8\">
				Select: 
					<a href=\"javascript:selectPrefRows('feed', true)\">All</a>,
					<a href=\"javascript:selectPrefRows('feed', false)\">None</a>
				</td</tr>";

			if (!get_pref($link, 'ENABLE_FEED_CATS')) {
				print "<tr class=\"title\">
					<td width='5%' align='center'>&nbsp;</td>";

				if (get_pref($link, 'ENABLE_FEED_ICONS')) {
					print "<td width='3%'>&nbsp;</td>";
				}

				print "
					<td width='35%'><a href=\"javascript:updateFeedList('title')\">Title</a></td>
					<td width='35%'><a href=\"javascript:updateFeedList('feed_url')\">Feed</a></td>
					<td width='15%'><a href=\"javascript:updateFeedList('last_article')\">Last&nbsp;Article</a></td>
					<td width='15%' align='right'><a href=\"javascript:updateFeedList('last_updated')\">Updated</a></td>";
			}
			
			$lnum = 0;

			$cur_cat_id = -1;
			
			while ($line = db_fetch_assoc($result)) {
	
				$feed_id = $line["id"];
				$cat_id = $line["cat_id"];

				$edit_title = htmlspecialchars(db_unescape_string($line["title"]));
				$edit_link = htmlspecialchars(db_unescape_string($line["feed_url"]));
				$edit_cat = htmlspecialchars(db_unescape_string($line["category"]));

				$hidden = sql_bool_to_bool($line["hidden"]);

				if (!$edit_cat) $edit_cat = "Uncategorized";

				$last_updated = $line["last_updated"];

				if (get_pref($link, 'HEADLINES_SMART_DATE')) {
					$last_updated = smart_date_time(strtotime($last_updated));
				} else {
					$short_date = get_pref($link, 'SHORT_DATE_FORMAT');
					$last_updated = date($short_date, strtotime($last_updated));
				}

				$last_article = $line["last_article"];

				if (get_pref($link, 'HEADLINES_SMART_DATE')) {
					$last_article = smart_date_time(strtotime($last_article));
				} else {
					$short_date = get_pref($link, 'SHORT_DATE_FORMAT');
					$last_article = date($short_date, strtotime($last_article));
				}

				if (get_pref($link, 'ENABLE_FEED_CATS') && $cur_cat_id != $cat_id) {
					$lnum = 0;
				
					print "<tr><td colspan=\"6\" class=\"feedEditCat\">$edit_cat</td></tr>";

					print "<tr class=\"title\">
						<td width='5%'>&nbsp;</td>";

					if (get_pref($link, 'ENABLE_FEED_ICONS')) {
						print "<td width='3%'>&nbsp;</td>";
					}

					print "<td width='35%'><a href=\"javascript:updateFeedList('title')\">Title</a></td>
						<td width='35%'><a href=\"javascript:updateFeedList('feed_url')\">Feed</a></td>
						<td width='15%'><a href=\"javascript:updateFeedList('last_article')\">Last&nbsp;Article</a></td>
						<td width='15%' align='right'><a href=\"javascript:updateFeedList('last_updated')\">Updated</a></td>";

					$cur_cat_id = $cat_id;
				}

				$class = ($lnum % 2) ? "even" : "odd";
				$this_row_id = "id=\"FEEDR-$feed_id\"";

				print "<tr class=\"$class\" $this_row_id>";
	
				$icon_file = ICONS_DIR . "/$feed_id.ico";
	
				if (file_exists($icon_file) && filesize($icon_file) > 0) {
						$feed_icon = "<img class=\"tinyFeedIcon\"	src=\"" . ICONS_URL . "/$feed_id.ico\">";
				} else {
					$feed_icon = "<img class=\"tinyFeedIcon\" src=\"images/blank_icon.gif\">";
				}
				
				print "<td class='feedSelect'><input onclick='toggleSelectPrefRow(this, \"feed\");' 
				type=\"checkbox\" id=\"FRCHK-".$line["id"]."\"></td>";

				if (get_pref($link, 'ENABLE_FEED_ICONS')) {
					print "<td class='feedIcon'>$feed_icon</td>";		
				}

				$edit_title = truncate_string($edit_title, 40);
				$edit_link = truncate_string($edit_link, 60);

				if ($hidden) {
					$edit_title = "<span class=\"insensitive\">$edit_title (Hidden)</span>";
					$edit_link = "<span class=\"insensitive\">$edit_link</span>";
					$last_updated = "<span class=\"insensitive\">$last_updated</span>";
					$last_article = "<span class=\"insensitive\">$last_article</span>";
				}

				$parent_title = $line["parent_title"];
				if ($parent_title) {
					$parent_title = "<span class='groupPrompt'>(linked to 
						$parent_title)</span>";
				}

				print "<td><a href=\"javascript:editFeed($feed_id);\">" . 
					"$edit_title $parent_title" . "</a></td>";		
					
				print "<td><a href=\"javascript:editFeed($feed_id);\">" . 
					$edit_link . "</a></td>";		

				print "<td><a href=\"javascript:editFeed($feed_id);\">" . 
					"$last_article</a></td>";

				print "<td align='right'><a href=\"javascript:editFeed($feed_id);\">" . 
					"$last_updated</a></td>";

				print "</tr>";
	
				++$lnum;
			}
	
			print "</table>";

			print "<p><span id=\"feedOpToolbar\">";
	
			if ($subop == "edit") {
				print "Edit feed:&nbsp;
					<input type=\"submit\" class=\"button\" 
						onclick=\"javascript:feedEditCancel()\" value=\"Cancel\">
					<input type=\"submit\" class=\"button\" 
						onclick=\"javascript:feedEditSave()\" value=\"Save\">";
			} else {
	
				print "
					Selection:&nbsp;
				<input type=\"submit\" class=\"button\" disabled=\"true\"
					onclick=\"javascript:editSelectedFeed()\" value=\"Edit\">
				<input type=\"submit\" class=\"button\" disabled=\"true\"
					onclick=\"javascript:removeSelectedFeeds()\" value=\"Unsubscribe\">";

				if (get_pref($link, 'ENABLE_FEED_CATS')) {

					print "&nbsp;|&nbsp;";				

					print_feed_cat_select($link, "sfeed_set_fcat", "", "disabled");

					print " <input type=\"submit\" class=\"button\" disabled=\"true\"
					onclick=\"javascript:categorizeSelectedFeeds()\" value=\"Recategorize\">";

				}
				
				print "</span>";

				if (get_pref($link, 'ENABLE_FEED_CATS')) {

					print " <input type=\"submit\" class=\"button\"
						onclick=\"javascript:editFeedCats()\" value=\"Edit categories\">";

#					print "&nbsp;|&nbsp;";				

				}

				}
		} else {

//			print "<p>No feeds defined.</p>";

		}

		print "<h3>OPML</h3>

		<div style='float : left'>
		<form	enctype=\"multipart/form-data\" method=\"POST\" action=\"opml.php\">
			File: <input id=\"opml_file\" name=\"opml_file\" type=\"file\">&nbsp;
			<input class=\"button\" name=\"op\" onclick=\"return validateOpmlImport();\"
				type=\"submit\" value=\"Import\">
				</form></div>";

		print "&nbsp; or &nbsp;";				

		print "<input type=\"submit\" 
			class=\"button\" onclick=\"gotoExportOpml()\" 
				value=\"Export OPML\">";			

	}
?>

<?php

	function batch_edit_cbox($elem, $label = false) {
		print "<input type=\"checkbox\" title=\"".__("Check to enable field")."\"
			onchange=\"batchFeedsToggleField(this, '$elem', '$label')\">";
	}

	function module_pref_feeds($link) {

		global $update_intervals;
		global $purge_intervals;
		global $update_methods;

		$subop = $_REQUEST["subop"];
		$quiet = $_REQUEST["quiet"];
		$mode = $_REQUEST["mode"];

		if ($subop == "removeicon") {			
			$feed_id = db_escape_string($_REQUEST["feed_id"]);

			$result = db_query($link, "SELECT id FROM ttrss_feeds
				WHERE id = '$feed_id' AND owner_uid = ". $_SESSION["uid"]);

			if (db_num_rows($result) != 0) {
				unlink(ICONS_DIR . "/$feed_id.ico");
			}

			return;
		}

		if ($subop == "uploadicon") {
			$icon_file = $_FILES['icon_file']['tmp_name'];
			$feed_id = db_escape_string($_REQUEST["feed_id"]);

			if (is_file($icon_file) && $feed_id) {
				if (filesize($icon_file) < 20000) {
					
					$result = db_query($link, "SELECT id FROM ttrss_feeds
						WHERE id = '$feed_id' AND owner_uid = ". $_SESSION["uid"]);

					if (db_num_rows($result) != 0) {
						unlink(ICONS_DIR . "/$feed_id.ico");
						move_uploaded_file($icon_file, ICONS_DIR . "/$feed_id.ico");
						$rc = 0;
					} else {
						$rc = 2;
					}
				} else {
					$rc = 1;
				}
			} else {
				$rc = 2;
			}

			print "<script type=\"text/javascript\">";
			print "parent.uploadIconHandler($rc);";
			print "</script>";
			return;
		}

/*		if ($subop == "massSubscribe") {
			$ids = split(",", db_escape_string($_REQUEST["ids"]));

			$subscribed = array();

			foreach ($ids as $id) {

				if ($mode == 1) {
					$result = db_query($link, "SELECT feed_url,title FROM ttrss_feeds
						WHERE id = '$id'");
				} else if ($mode == 2) {
					$result = db_query($link, "SELECT * FROM ttrss_archived_feeds
						WHERE id = '$id' AND owner_uid = " . $_SESSION["uid"]);
					$orig_id = db_escape_string(db_fetch_result($result, 0, "id"));
					$site_url = db_escape_string(db_fetch_result($result, 0, "site_url"));
				}
	
				$feed_url = db_escape_string(db_fetch_result($result, 0, "feed_url"));
				$title = db_escape_string(db_fetch_result($result, 0, "title"));
	
				$title_orig = db_fetch_result($result, 0, "title");
	
				$result = db_query($link, "SELECT id FROM ttrss_feeds WHERE
						feed_url = '$feed_url' AND owner_uid = " . $_SESSION["uid"]);
	
				if (db_num_rows($result) == 0) {			
					if ($mode == 1) {
						$result = db_query($link,
							"INSERT INTO ttrss_feeds (owner_uid,feed_url,title,cat_id) 
							VALUES ('".$_SESSION["uid"]."', '$feed_url', '$title', NULL)");
					} else if ($mode == 2) {
						$result = db_query($link,
							"INSERT INTO ttrss_feeds (id,owner_uid,feed_url,title,cat_id,site_url) 
							VALUES ('$orig_id','".$_SESSION["uid"]."', '$feed_url', '$title', NULL, '$site_url')");
					}
					array_push($subscribed, $title_orig);
				}
			}

			if (count($subscribed) > 0) {
				$msg = "<b>".__('Subscribed to feeds:')."</b>".
					"<ul class=\"nomarks\">";

				foreach ($subscribed as $title) {
					$msg .= "<li>$title</li>";
				}
				$msg .= "</ul>";

				print format_notice($msg);
			}

			return;
		} */

/*		if ($subop == "browse") {

			print "<div id=\"infoBoxTitle\">".__('Feed Browser')."</div>";
			
			print "<div class=\"infoBoxContents\">";

			$browser_search = db_escape_string($_REQUEST["search"]);

			//print "<p>".__("Showing top 25 registered feeds, sorted by popularity:")."</p>";

			print "<form onsubmit='return false;' display='inline' name='feed_browser' id='feed_browser'>";

			print "
				<div style='float : right'>
				<img style='display : none' 
					id='feed_browser_spinner' src='images/indicator_white.gif'>
				<input name=\"search\" size=\"20\" type=\"search\"
					onchange=\"javascript:updateFeedBrowser()\" value=\"$browser_search\">
				<button onclick=\"javascript:updateFeedBrowser()\">".__('Search')."</button>
			</div>";

			print " <select name=\"mode\" onchange=\"updateFeedBrowser()\">
				<option value='1'>" . __('Popular feeds') . "</option>
				<option value='2'>" . __('Feed archive') . "</option>
				</select> ";

			print __("limit:");

			print " <select name=\"limit\" onchange='updateFeedBrowser()'>";

			foreach (array(25, 50, 100, 200) as $l) {
				$issel = ($l == $limit) ? "selected" : "";
				print "<option $issel>$l</option>";
			}
			
			print "</select> ";

			print "<p>";

			$owner_uid = $_SESSION["uid"];

			print "<ul class='browseFeedList' id='browseFeedList'>";
			print_feed_browser($link, $search, 25);
			print "</ul>";

			print "<div align='center'>
				<button onclick=\"feedBrowserSubscribe()\">".__('Subscribe')."</button>
				<button onclick=\"closeInfoBox()\" >".__('Cancel')."</button></div>";

			print "</div>";
			return;
		} */

		if ($subop == "editfeed") {
			$feed_id = db_escape_string($_REQUEST["id"]);

			$result = db_query($link, 
				"SELECT * FROM ttrss_feeds WHERE id = '$feed_id' AND
					owner_uid = " . $_SESSION["uid"]);

			$title = htmlspecialchars(db_fetch_result($result,
				0, "title"));

			$icon_file = ICONS_DIR . "/$feed_id.ico";
	
			if (file_exists($icon_file) && filesize($icon_file) > 0) {
					$feed_icon = "<img width=\"16\" height=\"16\"
						src=\"" . ICONS_URL . "/$feed_id.ico\">";
			} else {
				$feed_icon = "";
			}

			print "<div id=\"infoBoxTitle\">".__('Feed Editor')."</div>";

			print "<div class=\"infoBoxContents\">";

			print "<form id=\"edit_feed_form\" onsubmit=\"return false\">";	

			print "<input type=\"hidden\" name=\"id\" value=\"$feed_id\">";
			print "<input type=\"hidden\" name=\"op\" value=\"pref-feeds\">";
			print "<input type=\"hidden\" name=\"subop\" value=\"editSave\">";

			print "<div class=\"dlgSec\">".__("Feed")."</div>";
			print "<div class=\"dlgSecCont\">";

			/* Title */

			print "<input style=\"font-size : 16px\" size=\"40\" onkeypress=\"return filterCR(event, feedEditSave)\"
				            name=\"title\" value=\"$title\">";

			/* Feed URL */

			$feed_url = db_fetch_result($result, 0, "feed_url");
			$feed_url = htmlspecialchars(db_fetch_result($result,
				0, "feed_url"));

			print "<br/>";

			print __('URL:') . " ";
			print "<input size=\"40\" onkeypress=\"return filterCR(event, feedEditSave)\"
				name=\"feed_url\" value=\"$feed_url\">";

			/* Category */

			if (get_pref($link, 'ENABLE_FEED_CATS')) {

				$cat_id = db_fetch_result($result, 0, "cat_id");

				print "<br/>";

				print __('Place in category:') . " ";

				$parent_feed = db_fetch_result($result, 0, "parent_feed");

				if (sprintf("%d", $parent_feed) > 0) {
					$disabled = "disabled";
				} else {
					$disabled = "";
				}

				print_feed_cat_select($link, "cat_id", $cat_id, $disabled);
			}

			/* Link to */

			print "<br/>";

			print __('Link to feed:') . " ";

			$tmp_result = db_query($link, "SELECT COUNT(id) AS count
				FROM ttrss_feeds WHERE parent_feed = '$feed_id'");

			$linked_count = db_fetch_result($tmp_result, 0, "count");

			$parent_feed = db_fetch_result($result, 0, "parent_feed");

			if ($linked_count > 0) {
				$disabled = "disabled";
			} else {
				$disabled = "";
			}

			print "<select $disabled name=\"parent_feed\">";
			
			print "<option value=\"0\">".__('Not linked')."</option>";

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

					$linked_title = truncate_string(htmlspecialchars($tmp_line["title"]), 40);

					printf("<option $is_selected value='%d'>%s</option>", 
						$tmp_line["id"], $linked_title);
				}

			print "</select>";

	
			print "</div>";

			print "<div class=\"dlgSec\">".__("Update")."</div>";
			print "<div class=\"dlgSecCont\">";

			/* Update Interval */

			$update_interval = db_fetch_result($result, 0, "update_interval");

			print_select_hash("update_interval", $update_interval, $update_intervals);

			/* Update method */

			$update_method = db_fetch_result($result, 0, "update_method");

			print " " . __('using') . " ";
			print_select_hash("update_method", $update_method, $update_methods);			

			$purge_interval = db_fetch_result($result, 0, "purge_interval");

			if (FORCE_ARTICLE_PURGE == 0) {

				/* Purge intl */

				print "<br/>";

				print __('Article purging:') . " ";

				print_select_hash("purge_interval", $purge_interval, $purge_intervals);

			} else {
				print "<input type='hidden' name='purge_interval' value='$purge_interval'>";

			}

			print "</div>";
			print "<div class=\"dlgSec\">".__("Authentication")."</div>";
			print "<div class=\"dlgSecCont\">";

			$auth_login = htmlspecialchars(db_fetch_result($result, 0, "auth_login"));

			print "<table>";

			print "<tr><td>" . __('Login:') . "</td><td>";

			print "<input size=\"20\" onkeypress=\"return filterCR(event, feedEditSave)\"
				name=\"auth_login\" value=\"$auth_login\">";

			print "</tr><tr><td>" . __("Password:") . "</td><td>";

			$auth_pass = htmlspecialchars(db_fetch_result($result, 0, "auth_pass"));

			print "<input size=\"20\" type=\"password\" name=\"auth_pass\" 
				onkeypress=\"return filterCR(event, feedEditSave)\"
				value=\"$auth_pass\">";

			print "</td></tr></table>";

			print "</div>";
			print "<div class=\"dlgSec\">".__("Options")."</div>";
			print "<div class=\"dlgSecCont\">";

			print "<div style=\"line-height : 100%\">";

			$private = sql_bool_to_bool(db_fetch_result($result, 0, "private"));

			if ($private) {
				$checked = "checked";
			} else {
				$checked = "";
			}

			print "<input type=\"checkbox\" name=\"private\" id=\"private\" 
				$checked>&nbsp;<label for=\"private\">".__('Hide from Popular feeds')."</label>";

			$rtl_content = sql_bool_to_bool(db_fetch_result($result, 0, "rtl_content"));

			if ($rtl_content) {
				$checked = "checked";
			} else {
				$checked = "";
			}

			print "<br/><input type=\"checkbox\" id=\"rtl_content\" name=\"rtl_content\"
				$checked>&nbsp;<label for=\"rtl_content\">".__('Right-to-left content')."</label>";

			$include_in_digest = sql_bool_to_bool(db_fetch_result($result, 0, "include_in_digest"));

			if ($include_in_digest) {
				$checked = "checked";
			} else {
				$checked = "";
			}

			print "<br/><input type=\"checkbox\" id=\"include_in_digest\" 
				name=\"include_in_digest\"
				$checked>&nbsp;<label for=\"include_in_digest\">".__('Include in e-mail digest')."</label>";


			$always_display_enclosures = sql_bool_to_bool(db_fetch_result($result, 0, "always_display_enclosures"));

			if ($always_display_enclosures) {
				$checked = "checked";
			} else {
				$checked = "";
			}

			print "<br/><input type=\"checkbox\" id=\"always_display_enclosures\" 
				name=\"always_display_enclosures\"
				$checked>&nbsp;<label for=\"always_display_enclosures\">".__('Always display image attachments')."</label>";


			$cache_images = sql_bool_to_bool(db_fetch_result($result, 0, "cache_images"));

			if ($cache_images) {
				$checked = "checked";
			} else {
				$checked = "";
			}

			if (SIMPLEPIE_CACHE_IMAGES) {
				$disabled = "";
				$label_class = "";
			} else {
				$disabled = "disabled";
				$label_class = "class='insensitive'";
			}

			print "<br/><input type=\"checkbox\" id=\"cache_images\" 
				name=\"cache_images\" $disabled
				$checked>&nbsp;<label $label_class for=\"cache_images\">".
				__('Cache images locally')."</label>";


			print "</div>";
			print "</div>";

			print "</form>";

			/* Icon */

			print "<br/>";

			print "<div class=\"dlgSec\">".__("Icon")."</div>";
			print "<div class=\"dlgSecCont\">";

			print "<iframe name=\"icon_upload_iframe\"
				style=\"width: 400px; height: 100px; display: none;\"></iframe>";

			print "<form style='display : block' target=\"icon_upload_iframe\"
				enctype=\"multipart/form-data\" method=\"POST\" 
				action=\"backend.php\">
				<input id=\"icon_file\" size=\"10\" name=\"icon_file\" type=\"file\">
				<input type=\"hidden\" name=\"op\" value=\"pref-feeds\">
				<input type=\"hidden\" name=\"feed_id\" value=\"$feed_id\">
				<input type=\"hidden\" name=\"subop\" value=\"uploadicon\">
				<button onclick=\"return uploadFeedIcon();\"
					type=\"submit\">".__('Replace')."</button>
				<button onclick=\"return removeFeedIcon($feed_id);\"
					type=\"submit\">".__('Remove')."</button>
				</form>";

			print "</div>";

			$title = htmlspecialchars($title, ENT_QUOTES);

			print "<div class='dlgButtons'>
				<div style=\"float : left\">
				<button onclick='return unsubscribeFeed($feed_id, \"$title\")'>".
					__('Unsubscribe')."</button>
				</div>
				<button onclick=\"return feedEditSave()\">".__('Save')."</button>
				<button onclick=\"return feedEditCancel()\">".__('Cancel')."</button>
				</div>";

			return;
		}

		if ($subop == "editfeeds") {

			$feed_ids = db_escape_string($_REQUEST["ids"]);

			print "<div id=\"infoBoxTitle\">".__('Multiple Feed Editor')."</div>";

			print "<div class=\"infoBoxContents\">";

			print "<form id=\"batch_edit_feed_form\" onsubmit=\"return false\">";	

			print "<input type=\"hidden\" name=\"ids\" value=\"$feed_ids\">";
			print "<input type=\"hidden\" name=\"op\" value=\"pref-feeds\">";
			print "<input type=\"hidden\" name=\"subop\" value=\"batchEditSave\">";

			print "<div class=\"dlgSec\">".__("Feed")."</div>";
			print "<div class=\"dlgSecCont\">";

			/* Title */

			print "<input disabled style=\"font-size : 16px\" size=\"35\" onkeypress=\"return filterCR(event, feedEditSave)\"
				            name=\"title\" value=\"$title\">";

			batch_edit_cbox("title");

			/* Feed URL */

			print "<br/>";

			print __('URL:') . " ";
			print "<input disabled size=\"40\" onkeypress=\"return filterCR(event, feedEditSave)\"
				name=\"feed_url\" value=\"$feed_url\">";

			batch_edit_cbox("feed_url");

			/* Category */

			if (get_pref($link, 'ENABLE_FEED_CATS')) {

				print "<br/>";

				print __('Place in category:') . " ";

				print_feed_cat_select($link, "cat_id", $cat_id, "disabled");

				batch_edit_cbox("cat_id");

			}

			print "</div>";

			print "<div class=\"dlgSec\">".__("Update")."</div>";
			print "<div class=\"dlgSecCont\">";

			/* Update Interval */

			print_select_hash("update_interval", $update_interval, $update_intervals, 
				"disabled");

			batch_edit_cbox("update_interval");

			/* Update method */

			print " " . __('using') . " ";
			print_select_hash("update_method", $update_method, $update_methods, 
				"disabled");			
			batch_edit_cbox("update_method");

			/* Purge intl */

			if (FORCE_ARTICLE_PURGE != 0) {

				print "<br/>";

				print __('Article purging:') . " ";

				print_select_hash("purge_interval", $purge_interval, $purge_intervals,
					"disabled");

				batch_edit_cbox("purge_interval");
			}

			print "</div>";
			print "<div class=\"dlgSec\">".__("Authentication")."</div>";
			print "<div class=\"dlgSecCont\">";

			print __('Login:') . " ";
			print "<input disabled size=\"15\" onkeypress=\"return filterCR(event, feedEditSave)\"
				name=\"auth_login\" value=\"$auth_login\">";

			batch_edit_cbox("auth_login");

			print " " . __("Password:") . " ";

			print "<input disabled size=\"15\" type=\"password\" name=\"auth_pass\" 
				onkeypress=\"return filterCR(event, feedEditSave)\"
				value=\"$auth_pass\">";

			batch_edit_cbox("auth_pass");

			print "</div>";
			print "<div class=\"dlgSec\">".__("Options")."</div>";
			print "<div class=\"dlgSecCont\">";

			print "<div style=\"line-height : 100%\">";

			print "<input disabled type=\"checkbox\" name=\"private\" id=\"private\" 
				$checked>&nbsp;<label id=\"private_l\" class='insensitive' for=\"private\">".__('Hide from Popular feeds')."</label>";

			print "&nbsp;"; batch_edit_cbox("private", "private_l");

			print "<br/><input disabled type=\"checkbox\" id=\"rtl_content\" name=\"rtl_content\"
				$checked>&nbsp;<label class='insensitive' id=\"rtl_content_l\" for=\"rtl_content\">".__('Right-to-left content')."</label>";

			print "&nbsp;"; batch_edit_cbox("rtl_content", "rtl_content_l");

			print "<br/><input disabled type=\"checkbox\" id=\"include_in_digest\" 
				name=\"include_in_digest\" 
				$checked>&nbsp;<label id=\"include_in_digest_l\" class='insensitive' for=\"include_in_digest\">".__('Include in e-mail digest')."</label>";

			print "&nbsp;"; batch_edit_cbox("include_in_digest", "include_in_digest_l");

			print "<br/><input disabled type=\"checkbox\" id=\"always_display_enclosures\" 
				name=\"always_display_enclosures\" 
				$checked>&nbsp;<label id=\"always_display_enclosures_l\" class='insensitive' for=\"always_display_enclosures\">".__('Always display image attachments')."</label>";

			print "&nbsp;"; batch_edit_cbox("always_display_enclosures", "always_display_enclosures_l");

			print "<br/><input disabled type=\"checkbox\" id=\"cache_images\" 
				name=\"cache_images\" 
				$checked>&nbsp;<label class='insensitive' id=\"cache_images_l\" 
					for=\"cache_images\">".
				__('Cache images locally')."</label>";


			if (SIMPLEPIE_CACHE_IMAGES) {
				print "&nbsp;"; batch_edit_cbox("cache_images", "cache_images_l");
			}

			print "</div>";
			print "</div>";

			print "</form>";

			print "<div class='dlgButtons'>
				<input type=\"submit\" class=\"button\" 
				onclick=\"return feedsEditSave()\" value=\"".__('Save')."\">
				<input type='submit' class='button'			
				onclick=\"return feedEditCancel()\" value=\"".__('Cancel')."\">
				</div>";

			return;
		}

		if ($subop == "editSave" || $subop == "batchEditSave") {

			$feed_title = db_escape_string(trim($_POST["title"]));
			$feed_link = db_escape_string(trim($_POST["feed_url"]));
			$upd_intl = db_escape_string($_POST["update_interval"]);
			$purge_intl = db_escape_string($_POST["purge_interval"]);
			$feed_id = db_escape_string($_POST["id"]); /* editSave */
			$feed_ids = db_escape_string($_POST["ids"]); /* batchEditSave */
			$cat_id = db_escape_string($_POST["cat_id"]);
			$auth_login = db_escape_string(trim($_POST["auth_login"]));
			$auth_pass = db_escape_string(trim($_POST["auth_pass"]));
			$parent_feed = db_escape_string($_POST["parent_feed"]);
			$private = checkbox_to_sql_bool(db_escape_string($_POST["private"]));
			$rtl_content = checkbox_to_sql_bool(db_escape_string($_POST["rtl_content"]));
			$include_in_digest = checkbox_to_sql_bool(
				db_escape_string($_POST["include_in_digest"]));
			$cache_images = checkbox_to_sql_bool(
				db_escape_string($_POST["cache_images"]));
			$update_method = (int) db_escape_string($_POST["update_method"]);

			$always_display_enclosures = checkbox_to_sql_bool(
				db_escape_string($_POST["always_display_enclosures"]));

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

			if (SIMPLEPIE_CACHE_IMAGES) {
				$cache_images_qpart = "cache_images = $cache_images,";
			} else {
				$cache_images_qpart = "";
			}

			if ($subop == "editSave") {

				$result = db_query($link, "UPDATE ttrss_feeds SET 
					$category_qpart $parent_qpart,
					title = '$feed_title', feed_url = '$feed_link',
					update_interval = '$upd_intl',
					purge_interval = '$purge_intl',
					auth_login = '$auth_login',
					auth_pass = '$auth_pass',
					private = $private,
					rtl_content = $rtl_content,
					$cache_images_qpart
					include_in_digest = $include_in_digest,
					always_display_enclosures = $always_display_enclosures,
					update_method = '$update_method'
					WHERE id = '$feed_id' AND owner_uid = " . $_SESSION["uid"]);

				if (get_pref($link, 'ENABLE_FEED_CATS')) {
					# update linked feed categories
					$result = db_query($link, "UPDATE ttrss_feeds SET
						$category_qpart_nocomma WHERE parent_feed = '$feed_id' AND
						owner_uid = " . $_SESSION["uid"]);
				}
			} else if ($subop == "batchEditSave") {
				$feed_data = array();

				foreach (array_keys($_POST) as $k) {
					if ($k != "op" && $k != "subop" && $k != "ids") {
						$feed_data[$k] = $_POST[$k];
					}
				}

				db_query($link, "BEGIN");

				foreach (array_keys($feed_data) as $k) {

					$qpart = "";

					switch ($k) {
						case "title":							
							$qpart = "title = '$feed_title'";
							break;

						case "feed_url":
							$qpart = "feed_url = '$feed_link'";
							break;

						case "update_interval":
							$qpart = "update_interval = '$upd_intl'";
							break;

						case "purge_interval":
							$qpart = "purge_interval = '$purge_intl'";
							break;

						case "auth_login":
							$qpart = "auth_login = '$auth_login'";
							break;

						case "auth_pass":
							$qpart = "auth_pass = '$auth_pass'";
							break;

						case "private":
							$qpart = "private = '$private'";
							break;

						case "include_in_digest":
							$qpart = "include_in_digest = '$include_in_digest'";
							break;

						case "always_display_enclosures":
							$qpart = "always_display_enclosures = '$always_display_enclosures'";
							break;

						case "cache_images":
							$qpart = "cache_images = '$cache_images'";
							break;

						case "rtl_content":
							$qpart = "rtl_content = '$rtl_content'";
							break;

						case "update_method":
							$qpart = "update_method = '$update_method'";
							break;

						case "cat_id":
							$qpart = $category_qpart_nocomma;
							break;

					}

					if ($qpart) {
						db_query($link,
							"UPDATE ttrss_feeds SET $qpart WHERE id IN ($feed_ids)
							AND owner_uid = " . $_SESSION["uid"]);
						print "<br/>";
					}
				}

				db_query($link, "COMMIT");
			}

		}

		if ($subop == "remove") {

			$ids = split(",", db_escape_string($_REQUEST["ids"]));

			foreach ($ids as $id) {
				remove_feed($link, $id, $_SESSION["uid"]);
			}

			return;
		}

		if ($subop == "clear") {
			$id = db_escape_string($_REQUEST["id"]);
			clear_feed_articles($link, $id);
		}

		if ($subop == "rescore") {
			$ids = split(",", db_escape_string($_REQUEST["ids"]));

			foreach ($ids as $id) {

				$filters = load_filters($link, $id, $_SESSION["uid"], 6);

				$result = db_query($link, "SELECT title, content, link, ref_id FROM
						ttrss_user_entries, ttrss_entries 
						WHERE ref_id = id AND feed_id = '$id' AND 
							owner_uid = " .$_SESSION['uid']."
						");

				$scores = array();

				while ($line = db_fetch_assoc($result)) {

					$article_filters = get_article_filters($filters, $line['title'], 
						$line['content'], $line['link']);
					
					$new_score = calculate_article_score($article_filters);

					if (!$scores[$new_score]) $scores[$new_score] = array();

					array_push($scores[$new_score], $line['ref_id']);
				}

				foreach (array_keys($scores) as $s) {
					if ($s > 1000) {
						db_query($link, "UPDATE ttrss_user_entries SET score = '$s', 
							marked = true WHERE
							ref_id IN (" . join(',', $scores[$s]) . ")");
					} else if ($s < -500) {
						db_query($link, "UPDATE ttrss_user_entries SET score = '$s', 
							unread = false WHERE
							ref_id IN (" . join(',', $scores[$s]) . ")");
					} else {
						db_query($link, "UPDATE ttrss_user_entries SET score = '$s' WHERE
							ref_id IN (" . join(',', $scores[$s]) . ")");
					}
				}
			}

			print __("All done.");

		}

		if ($subop == "rescoreAll") {

			$result = db_query($link, 
				"SELECT id FROM ttrss_feeds WHERE owner_uid = " . $_SESSION['uid']);

			while ($feed_line = db_fetch_assoc($result)) {

				$id = $feed_line["id"];

				$filters = load_filters($link, $id, $_SESSION["uid"], 6);

				$tmp_result = db_query($link, "SELECT title, content, link, ref_id FROM
						ttrss_user_entries, ttrss_entries 
						WHERE ref_id = id AND feed_id = '$id' AND 
							owner_uid = " .$_SESSION['uid']."
						");

				$scores = array();

				while ($line = db_fetch_assoc($tmp_result)) {

					$article_filters = get_article_filters($filters, $line['title'], 
						$line['content'], $line['link']);
					
					$new_score = calculate_article_score($article_filters);

					if (!$scores[$new_score]) $scores[$new_score] = array();

					array_push($scores[$new_score], $line['ref_id']);
				}

				foreach (array_keys($scores) as $s) {
					if ($s > 1000) {
						db_query($link, "UPDATE ttrss_user_entries SET score = '$s', 
							marked = true WHERE
							ref_id IN (" . join(',', $scores[$s]) . ")");
					} else {
						db_query($link, "UPDATE ttrss_user_entries SET score = '$s' WHERE
							ref_id IN (" . join(',', $scores[$s]) . ")");
					}
				}
			}

			print __("All done.");

		}

		if ($subop == "add") {

			$feed_url = db_escape_string(trim($_REQUEST["feed_url"]));
			$cat_id = db_escape_string($_REQUEST["cat_id"]);
			$p_from = db_escape_string($_REQUEST["from"]);

			/* only read authentication information from POST */

			$auth_login = db_escape_string(trim($_POST["auth_login"]));
			$auth_pass = db_escape_string(trim($_POST["auth_pass"]));

			if ($p_from != 'tt-rss') {
				print "<html>
					<head>
						<title>Tiny Tiny RSS</title>
						<link rel=\"stylesheet\" type=\"text/css\" href=\"utility.css\">
					</head>
					<body>
					<img class=\"floatingLogo\" src=\"images/ttrss_logo.png\"
				  		alt=\"Tiny Tiny RSS\"/>	
					<h1>Subscribe to feed...</h1>";
			}

			$rc = subscribe_to_feed($link, $feed_url, $cat_id, $auth_login, $auth_pass);

			switch ($rc) {
			case 1: 
				print_notice(T_sprintf("Subscribed to <b>%s</b>.", $feed_url));
				break;
			case 2:
				print_error(T_sprintf("Could not subscribe to <b>%s</b>.", $feed_url));
				break;
			case 0:
				print_warning(T_sprintf("Already subscribed to <b>%s</b>.", $feed_url));
				break;
			}

			if ($p_from != 'tt-rss') {
				$tt_uri = ($_SERVER['HTTPS'] != "on" ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . preg_replace('/backend\.php.*$/', 'tt-rss.php', $_SERVER["REQUEST_URI"]);


				$tp_uri = ($_SERVER['HTTPS'] != "on" ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . preg_replace('/backend\.php.*$/', 'prefs.php', $_SERVER["REQUEST_URI"]);

				$result = db_query($link, "SELECT id FROM ttrss_feeds WHERE
					feed_url = '$feed_url' AND owner_uid = " . $_SESSION["uid"]);

				$feed_id = db_fetch_result($result, 0, "id");

				print "<p>";

				if ($feed_id) {
					print "<form method=\"GET\" style='display: inline' 
						action=\"$tp_uri\">
						<input type=\"hidden\" name=\"tab\" value=\"feedConfig\">
						<input type=\"hidden\" name=\"subop\" value=\"editFeed\">
						<input type=\"hidden\" name=\"subopparam\" value=\"$feed_id\">
						<input type=\"submit\" value=\"".__("Edit subscription options")."\">
						</form>";
				}

				print "<form style='display: inline' method=\"GET\" action=\"$tt_uri\">
					<input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\">
					</form></p>";

				print "</body></html>";
				return;
			}
		}

		if ($subop == "categorize") {

			if (!WEB_DEMO_MODE) {

				$ids = split(",", db_escape_string($_REQUEST["ids"]));

				$cat_id = db_escape_string($_REQUEST["cat_id"]);

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

			$action = $_REQUEST["action"];

			if ($action == "save") {

				$cat_title = db_escape_string(trim($_REQUEST["value"]));
				$cat_id = db_escape_string($_REQUEST["cid"]);

				db_query($link, "BEGIN");

				$result = db_query($link, "SELECT title FROM ttrss_feed_categories
					WHERE id = '$cat_id' AND owner_uid = ".$_SESSION["uid"]);

				if (db_num_rows($result) == 1) {

					$old_title = db_fetch_result($result, 0, "title");
					
					if ($cat_title != "") {
						$result = db_query($link, "UPDATE ttrss_feed_categories SET
							title = '$cat_title' WHERE id = '$cat_id' AND 
							owner_uid = ".$_SESSION["uid"]);

						print $cat_title;
					} else {
						print $old_title;
					}
				} else {
					print $_REQUEST["value"];
				}

				db_query($link, "COMMIT");

				return;

			}

			print "<div id=\"infoBoxTitle\">".__('Category editor')."</div>";
			
			print "<div class=\"infoBoxContents\">";


			if ($action == "add") {

				if (!WEB_DEMO_MODE) {
	
					$feed_cat = db_escape_string(trim($_REQUEST["cat"]));
	
					$result = db_query($link,
						"SELECT id FROM ttrss_feed_categories
						WHERE title = '$feed_cat' AND owner_uid = ".$_SESSION["uid"]);
	
					if (db_num_rows($result) == 0) {
						
						$result = db_query($link,
							"INSERT INTO ttrss_feed_categories (owner_uid,title) 
							VALUES ('".$_SESSION["uid"]."', '$feed_cat')");
	
					} else {
	
						print_warning(T_sprintf("Category <b>$%s</b> already exists in the database.", 
							$feed_cat));
					}

				}
			}

			if ($action == "remove") {
	
				$ids = split(",", db_escape_string($_REQUEST["ids"]));
	
				foreach ($ids as $id) {
					remove_feed_category($link, $id, $_SESSION["uid"]);
				}
			}

			print "<div>
				<input id=\"fadd_cat\" 
					onkeypress=\"return filterCR(event, addFeedCat)\"
					size=\"40\">
					<button onclick=\"javascript:addFeedCat()\">".
					__('Create category')."</button></div>";
	
			$result = db_query($link, "SELECT title,id FROM ttrss_feed_categories
				WHERE owner_uid = ".$_SESSION["uid"]."
				ORDER BY title");

			print "<p>";

			if (db_num_rows($result) != 0) {

				print	__('Select:')." 
					<a href=\"javascript:selectPrefRows('fcat', true)\">".__('All')."</a>,
					<a href=\"javascript:selectPrefRows('fcat', false)\">".__('None')."</a>";

				print "<div class=\"prefFeedCatHolder\">";

				print "<form id=\"feed_cat_edit_form\" onsubmit=\"return false\">";

				print "<table width=\"100%\" class=\"prefFeedCatList\" 
					cellspacing=\"0\" id=\"prefFeedCatList\">";
						
				$lnum = 0;
				
				while ($line = db_fetch_assoc($result)) {
		
					$class = ($lnum % 2) ? "even" : "odd";
		
					$cat_id = $line["id"];
					$this_row_id = "id=\"FCATR-$cat_id\"";
		
					print "<tr class=\"$class\" $this_row_id>";
		
					$edit_title = htmlspecialchars($line["title"]);
		
					print "<td width='5%' align='center'><input 
						onclick='toggleSelectPrefRow(this, \"fcat\");' 
						type=\"checkbox\" id=\"FCCHK-$cat_id\"></td>";
	
					print "<td><span id=\"FCATT-$cat_id\">" . 
						$edit_title . "</span></td>";		
					
					print "</tr>";
		
					++$lnum;
				}
	
				print "</table>";

				print "</form>";

				print "</div>";

			} else {
				print "<p>".__('No feed categories defined.')."</p>";
			}

			print "<div class='dlgButtons'>
				<div style='float : left'>
				<button onclick=\"return removeSelectedFeedCats()\">".
				__('Remove')."</button>
				</div>";

			print "<button onclick=\"selectTab('feedConfig')\">".
				__('Close this window')."</button></div>";

			print "</div>";

			return;

		}

		if ($quiet) return;

		set_pref($link, "_PREFS_ACTIVE_TAB", "feedConfig");

		$result = db_query($link, "SELECT COUNT(id) AS num_errors
			FROM ttrss_feeds WHERE last_error != '' AND owner_uid = ".$_SESSION["uid"]);

		$num_errors = db_fetch_result($result, 0, "num_errors");

		if ($num_errors > 0) {

			print format_notice("<a href=\"javascript:showFeedsWithErrors()\">".
				__('Some feeds have update errors (click for details)')."</a>");
		}

		$feed_search = db_escape_string($_REQUEST["search"]);

		if (array_key_exists("search", $_REQUEST)) {
			$_SESSION["prefs_feed_search"] = $feed_search;
		} else {
			$feed_search = $_SESSION["prefs_feed_search"];
		}

		print "<div style='float : right'> 
			<input id=\"feed_search\" size=\"20\" type=\"search\"
				onfocus=\"javascript:disableHotkeys();\" 
				onblur=\"javascript:enableHotkeys();\"
				onchange=\"javascript:updateFeedList()\" value=\"$feed_search\">
			<button onclick=\"javascript:updateFeedList()\">".
				__('Search')."</button>
			</div>";
		
		print "<button onclick=\"quickAddFeed()\">"
			.__('Subscribe to feed')."</button> ";

		print "<button onclick=\"editSelectedFeed()\">".
			__('Edit feeds')."</button> ";

		if (get_pref($link, 'ENABLE_FEED_CATS')) {

			print "<button onclick=\"javascript:editFeedCats()\">".
				__('Edit categories')."</button> ";
		}

		print "<button onclick=\"javascript:removeSelectedFeeds()\">"
			.__('Unsubscribe')."</button> ";

		if (defined('_ENABLE_FEED_DEBUGGING')) {

			print "<select id=\"feedActionChooser\" onchange=\"feedActionChange()\">
				<option value=\"facDefault\" selected>".__('More actions...')."</option>";
	
			if (FORCE_ARTICLE_PURGE == 0) {
				print 
					"<option value=\"facPurge\">".__('Manual purge')."</option>";
			}
	
			print "
				<option value=\"facClear\">".__('Clear feed data')."</option>
				<option value=\"facRescore\">".__('Rescore articles')."</option>";
	
			print "</select>";

		}

		$feeds_sort = db_escape_string($_REQUEST["sort"]);

		if (!$feeds_sort || $feeds_sort == "undefined") {
			$feeds_sort = $_SESSION["pref_sort_feeds"];			
			if (!$feeds_sort) $feeds_sort = "title";
		}

		$_SESSION["pref_sort_feeds"] = $feeds_sort;

		if ($feed_search) {

			$feed_search = split(" ", $feed_search);
			$tokens = array();

			foreach ($feed_search as $token) {

				$token = trim($token);

				array_push($tokens, "(UPPER(F1.title) LIKE UPPER('%$token%') OR
					UPPER(C1.title) LIKE UPPER('%$token%') OR
					UPPER(F1.feed_url) LIKE UPPER('%$token%'))");
			}

			$search_qpart = "(" . join($tokens, " AND ") . ") AND ";

		} else {
			$search_qpart = "";
		}

		$show_last_article_info = false;
		$show_last_article_checked = "";
		$show_last_article_qpart = "";

		if ($_REQUEST["slat"] == "true") {
			$show_last_article_info = true;
			$show_last_article_checked = "checked";
			$show_last_article_qpart = ", (SELECT ".SUBSTRING_FOR_DATE."(MAX(updated),1,16) FROM ttrss_user_entries,
				ttrss_entries WHERE ref_id = ttrss_entries.id
				AND feed_id = F1.id) AS last_article";
		} else if ($feeds_sort == "last_article") {
			$feeds_sort = "title";
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
				".SUBSTRING_FOR_DATE."(F1.last_updated,1,16) AS last_updated,
				F1.parent_feed,
				F1.update_interval,
				F1.last_error,
				F1.purge_interval,
				F1.cat_id,
				F2.title AS parent_title,
				C1.title AS category,
				F1.include_in_digest
				$show_last_article_qpart
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
			print "<tr><td class=\"selectPrompt\" colspan=\"8\">".
				"<div style='float : right'>".
				"<input id='show_last_article_times' type='checkbox' onchange='feedlistToggleSLAT()'
				$show_last_article_checked><label 
					for='show_last_article_times'>".__('Show last article times')."</label></div>".
				__('Select:')."
					<a href=\"javascript:selectPrefRows('feed', true)\">".__('All')."</a>,
					<a href=\"javascript:selectPrefRows('feed', false)\">".__('None')."</a>
				</td</tr>";

			if (!get_pref($link, 'ENABLE_FEED_CATS')) {
				print "<tr class=\"title\">
					<td width='5%' align='center'>&nbsp;</td>";

				if (get_pref($link, 'ENABLE_FEED_ICONS')) {
					print "<td width='3%'>&nbsp;</td>";
				}

				print "<td width='60%'><a href=\"javascript:updateFeedList('title')\">".__('Title')."</a></td>";

				if ($show_last_article_info) {
					print "<td width='20%' align='right'><a href=\"javascript:updateFeedList('last_article')\">".__('Last&nbsp;Article')."</a></td>";
				}

				print "<td width='20%' align='right'><a href=\"javascript:updateFeedList('last_updated')\">".__('Updated')."</a></td>";
			}
			
			$lnum = 0;

			$cur_cat_id = -1;
			
			while ($line = db_fetch_assoc($result)) {
	
				$feed_id = $line["id"];
				$cat_id = $line["cat_id"];

				$edit_title = htmlspecialchars($line["title"]);
				$edit_cat = htmlspecialchars($line["category"]);

				$last_error = $line["last_error"];

				if (!$edit_cat) $edit_cat = __("Uncategorized");

				$last_updated = $line["last_updated"];

				if (!$last_updated) {
					$last_updated = "&mdash;";
				} else if (get_pref($link, 'HEADLINES_SMART_DATE')) {
					$last_updated = smart_date_time(strtotime($last_updated));
				} else {
					$short_date = get_pref($link, 'SHORT_DATE_FORMAT');
					$last_updated = date($short_date, strtotime($last_updated));
				}

				$last_article = $line["last_article"];

				if (!$last_article) {
					$last_article = "&mdash;";	
				} else if (get_pref($link, 'HEADLINES_SMART_DATE')) {
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

					print "<td width='60%'><a href=\"javascript:updateFeedList('title')\">".__('Title')."</a></td>";

					if ($show_last_article_info) {
						print "<td width='20%' align='right'>
							<a href=\"javascript:updateFeedList('last_article')\">".__('Last&nbsp;Article')."</a></td>";
					}

					print "<td width='20%' align='right'>
						<a href=\"javascript:updateFeedList('last_updated')\">".__('Updated')."</a></td>";

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

				$onclick = "onclick='editFeed($feed_id)' title='".__('Click to edit')."'";

				if (get_pref($link, 'ENABLE_FEED_ICONS')) {
					print "<td $onclick class='feedIcon'>$feed_icon</td>";		
				}

				if ($last_error) {
					$edit_title = "<span class=\"feed_error\">$edit_title</span>";
					$last_updated = "<span class=\"feed_error\">$last_updated</span>";
					$last_article = "<span class=\"feed_error\">$last_article</span>";
				}

				$parent_title = $line["parent_title"];
				if ($parent_title) {
					$linked_to = sprintf(__("(linked to %s)"), $parent_title);
					$parent_title = "<span class='groupPrompt'>$linked_to</span>";
				}

				print "<td $onclick>" . "$edit_title $parent_title" . "</td>";

				if ($show_last_article_info) {
					print "<td align='right' $onclick>" . 
						"$last_article</td>";
				}

				print "<td $onclick align='right'>$last_updated</td>";

				print "</tr>";
	
				++$lnum;
			}
	
			print "</table>";

			print "<p>";

		} else {

			print "<p>";

			if (!$feed_search) { 
				print_warning(__("You don't have any subscribed feeds."));
			} else {
				print_warning(__('No matching feeds found.'));
			}
			print "</p>";

		}

		print "<h3>".__('OPML')."</h3>";

/*		print "<div style='float : left'>
		<form	enctype=\"multipart/form-data\" method=\"POST\" action=\"opml.php\">
		".__('File:')." <input id=\"opml_file\" name=\"opml_file\" type=\"file\">&nbsp;
			<input type=\"hidden\" name=\"op\" value=\"Import\">
			<button onclick=\"return validateOpmlImport();\"
				type=\"submit\">".__('Import')."</button>
				</form></div>";

		print "&nbsp;"; */

		print "<p>" . __("Using OPML you can export and import your feeds and Tiny Tiny RSS settings.");

		print "<div class=\"insensitive\">" . __("Note: Only main settings profile can be migrated using OPML.") . "</div>";

		print "</p>";

		print "<iframe name=\"upload_iframe\"
			style=\"width: 400px; height: 100px; display: none;\"></iframe>";

		print "<div style='float : left'>";
		print "<form style='display : block' target=\"upload_iframe\"
			enctype=\"multipart/form-data\" method=\"POST\" 
				action=\"backend.php\">
			<input id=\"opml_file\" name=\"opml_file\" type=\"file\">&nbsp;
			<input type=\"hidden\" name=\"op\" value=\"dlg\">
			<input type=\"hidden\" name=\"id\" value=\"importOpml\">
			<button onclick=\"return opmlImport();\"
				type=\"submit\">".__('Import')."</button>
			</form>";
		print "</div>&nbsp;";

		print "<button onclick=\"gotoExportOpml()\">".
			__('Export OPML')."</button>";

		if (!get_pref($link, "_PREFS_OPML_PUBLISH_KEY")){
			set_pref($link, "_PREFS_OPML_PUBLISH_KEY", generate_publish_key());
		}

		print "<p>".__('Your OPML can be published publicly and can be subscribed by anyone who knows the URL below.');

		print "<div class=\"insensitive\">" . __("Note: Published OPML does not include your Tiny Tiny RSS settings, feeds that require authentication or feeds hidden from Popular feeds.") . 			"</div>" . "</p>";

		print "<button onclick=\"return displayDlg('pubOPMLUrl')\">".
			__('Display URL')."</button> ";


		if (strpos($_SERVER['HTTP_USER_AGENT'], "Firefox") !== false) {
	
			print "<h3>" . __("Firefox Integration") . "</h3>";
                
			print "<p>" . __('This Tiny Tiny RSS site can be used as a Firefox Feed Reader by clicking the link below.') . "</p>";

			print "<p";

			print "<button onclick='window.navigator.registerContentHandler(" .
                      "\"application/vnd.mozilla.maybe.feed\", " .
                      "\"" . add_feed_url() . "\", " . " \"Tiny Tiny RSS\")'>" .
							 __('Click here to register this site as a feed reader.') . 
				"</button>";

			print "</p>";
		}

		print "<h3>".__("Published articles")."</h3>";

		if (!get_pref($link, "_PREFS_PUBLISH_KEY")) {
			set_pref($link, "_PREFS_PUBLISH_KEY", generate_publish_key());
		}
		
		print "<p>".__('Published articles are exported as a public RSS feed and can be subscribed by anyone who knows the URL specified below.')."</p>";

		print "<button onclick=\"return displayDlg('pubUrl')\">".
			__('Display URL')."</button> ";
               

	}

	function print_feed_browser($link, $search, $limit, $mode = 1) {

			$owner_uid = $_SESSION["uid"];

			if ($search) {
				$search_qpart = "AND (UPPER(feed_url) LIKE UPPER('%$search%') OR 
					UPPER(title) LIKE UPPER('%$search%'))";
			} else {
				$search_qpart = "";
			}

			if ($mode == 1) {
				$result = db_query($link, "SELECT feed_url, subscribers FROM
					ttrss_feedbrowser_cache WHERE (SELECT COUNT(id) = 0 FROM ttrss_feeds AS tf
					WHERE tf.feed_url = ttrss_feedbrowser_cache.feed_url
					AND owner_uid = '$owner_uid') $search_qpart 
					ORDER BY subscribers DESC LIMIT $limit");
			} else if ($mode == 2) {
				$result = db_query($link, "SELECT *,
					(SELECT COUNT(*) FROM ttrss_user_entries WHERE
				 		orig_feed_id = ttrss_archived_feeds.id) AS articles_archived
					FROM
						ttrss_archived_feeds
					WHERE 
					(SELECT COUNT(*) FROM ttrss_feeds 
						WHERE ttrss_feeds.feed_url = ttrss_archived_feeds.feed_url AND
							owner_uid = '$owner_uid') = 0	AND					
					owner_uid = '$owner_uid' $search_qpart 
					ORDER BY id DESC LIMIT $limit");
			}

			$feedctr = 0;
			
			while ($line = db_fetch_assoc($result)) {

				if ($mode == 1) {

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
	
					$check_box = "<input onclick='toggleSelectListRow(this)' 
						class='feedBrowseCB' 
						type=\"checkbox\" id=\"FBCHK-" . $details["id"] . "\">";
	
					$class = ($feedctr % 2) ? "even" : "odd";
	
					$feed_url = htmlspecialchars($line["feed_url"]);

					if ($details["site_url"]) {
						$site_url = "<a target=\"_blank\" href=\"".
							htmlspecialchars($details["site_url"])."\">
							<img style='border-width : 0px' src='images/www.png' alt='www'></a>";
					} else {
						$site_url = "";
					}

					$feed_url = "<a target=\"_blank\" href=\"$feed_url\"><img 
						style='border-width : 0px; vertical-align : middle' 
						src='images/feed-icon-12x12.png'></a>";

					print "<li title=\"".htmlspecialchars($details["site_url"])."\" 
						class='$class' id=\"FBROW-".$details["id"]."\">$check_box".
						"$feed_icon $feed_url " . htmlspecialchars($details["title"]) . 
						"&nbsp;<span class='subscribers'>($subscribers)</span>
						$site_url</li>";
	
				} else if ($mode == 2) {
					$feed_url = htmlspecialchars($line["feed_url"]);
					$site_url = htmlspecialchars($line["site_url"]); 
					$title = htmlspecialchars($line["title"]);

					$icon_file = ICONS_DIR . "/" . $line["id"] . ".ico";
	
					if (file_exists($icon_file) && filesize($icon_file) > 0) {
							$feed_icon = "<img class=\"tinyFeedIcon\"	src=\"" . ICONS_URL . 
								"/".$line["id"].".ico\">";
					} else {
						$feed_icon = "<img class=\"tinyFeedIcon\" src=\"images/blank_icon.gif\">";
					}

					$check_box = "<input onclick='toggleSelectListRow(this)' class='feedBrowseCB' 
						type=\"checkbox\" id=\"FBCHK-" . $line["id"] . "\">";
	
					$class = ($feedctr % 2) ? "even" : "odd";

					if ($line['articles_archived'] > 0) {
						$archived = sprintf(__("%d archived articles"), $line['articles_archived']);
						$archived = "&nbsp;<span class='subscribers'>($archived)</span>";
					} else {
						$archived = '';
					}

					if ($line["site_url"]) {
						$site_url = "<a target=\"_blank\" href=\"$site_url\">
							<img style='border-width : 0px' src='images/www.png' alt='www'></a>";
					} else {
						$site_url = "";
					}

					$feed_url = "<a target=\"_blank\" href=\"$feed_url\"><img 
						style='border-width : 0px; vertical-align : middle' 
						src='images/feed-icon-12x12.png'></a>";

					print "<li title='".$line['site_url']."' class='$class' 
						id=\"FBROW-".$line["id"]."\">".
						$check_box . "$feed_icon $feed_url " . $title . 
						$archived . $site_url . "</li>";


				}

				++$feedctr;
			}

			if ($feedctr == 0) {
				print "<li style=\"text-align : center\"><p>".__('No feeds found.')."</p></li>";
			}

		return $feedctr;

	}

?>

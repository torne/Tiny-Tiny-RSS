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
				$msg = "<b>".__('Subscribed to feeds:')."</b>".
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
				print __("Feed browser is administratively disabled.");
				return;
			}

			print "<div id=\"infoBoxTitle\">".__('Other feeds: Top 25')."</div>";
			
			print "<div class=\"infoBoxContents\">";

			print "<p>".__("Showing top 25 registered feeds, sorted by popularity:")."</p>";

			$owner_uid = $_SESSION["uid"];

/*			$result = db_query($link, "SELECT feed_url,COUNT(id) AS subscribers
		  		FROM ttrss_feeds WHERE (SELECT COUNT(id) = 0 FROM ttrss_feeds AS tf 
					WHERE tf.feed_url = ttrss_feeds.feed_url 
						AND owner_uid = '$owner_uid') GROUP BY feed_url 
						ORDER BY subscribers DESC LIMIT 25"); */

			$result = db_query($link, "SELECT feed_url, subscribers FROM
				ttrss_feedbrowser_cache WHERE (SELECT COUNT(id) = 0 FROM ttrss_feeds AS tf
				WHERE tf.feed_url = ttrss_feedbrowser_cache.feed_url 
				AND owner_uid = '$owner_uid') ORDER BY subscribers DESC LIMIT 25");

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
					"$feed_icon " . $details["title"] . 
					"&nbsp;<span class='subscribers'>($subscribers)</span></li>";

					++$feedctr;
			}

			if ($feedctr == 0) {
				print "<li style=\"text-align : center\"><p>".__('No feeds found.')."</p></li>";
				$subscribe_btn_disabled = "disabled";
			} else {
				$subscribe_btn_disabled = "";
			}

			print "</ul>";

			print "<div align='center'>
				<input type=\"submit\" class=\"button\" 
				$subscribe_btn_disabled
				onclick=\"feedBrowserSubscribe()\" value=\"".__('Subscribe')."\">
				<input type='submit' class='button'			
				onclick=\"closeInfoBox()\" value=\"".__('Cancel')."\"></div>";

			print "</div>";
			return;
		}

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
					printf("<option $is_selected value='%d'>%s</option>", 
						$tmp_line["id"], $tmp_line["title"]);
				}

			print "</select>";


			print "</div>";

			print "<div class=\"dlgSec\">".__("Update")."</div>";
			print "<div class=\"dlgSecCont\">";

			/* Update Interval */

			$update_interval = db_fetch_result($result, 0, "update_interval");

			print_select_hash("update_interval", $update_interval, $update_intervals);

			/* Update method */

			if (ALLOW_SELECT_UPDATE_METHOD) {
				$update_method = db_fetch_result($result, 0, "update_method");

				print " " . __('using') . " ";
				print_select_hash("update_method", $update_method, $update_methods);			
			}

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

			print __('Login:') . " ";
			print "<input size=\"20\" onkeypress=\"return filterCR(event, feedEditSave)\"
				name=\"auth_login\" value=\"$auth_login\">";

			print " " . __("Password:") . " ";

			$auth_pass = htmlspecialchars(db_fetch_result($result, 0, "auth_pass"));

			print "<input size=\"20\" type=\"password\" name=\"auth_pass\" 
				onkeypress=\"return filterCR(event, feedEditSave)\"
				value=\"$auth_pass\">";

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
				$checked>&nbsp;<label for=\"private\">".__('Hide from "Other Feeds"')."</label>";

			$rtl_content = sql_bool_to_bool(db_fetch_result($result, 0, "rtl_content"));

			if ($rtl_content) {
				$checked = "checked";
			} else {
				$checked = "";
			}

			print "<br/><input type=\"checkbox\" id=\"rtl_content\" name=\"rtl_content\"
				$checked>&nbsp;<label for=\"rtl_content\">".__('Right-to-left content')."</label>";

			$hidden = sql_bool_to_bool(db_fetch_result($result, 0, "hidden"));

			if ($hidden) {
				$checked = "checked";
			} else {
				$checked = "";
			}

			print "<br/><input type=\"checkbox\" id=\"hidden\" name=\"hidden\"
				$checked>&nbsp;<label for=\"hidden\">".__('Hide from my feed list')."</label>";

			$include_in_digest = sql_bool_to_bool(db_fetch_result($result, 0, "include_in_digest"));

			if ($include_in_digest) {
				$checked = "checked";
			} else {
				$checked = "";
			}

			print "<br/><input type=\"checkbox\" id=\"include_in_digest\" 
				name=\"include_in_digest\"
				$checked>&nbsp;<label for=\"include_in_digest\">".__('Include in e-mail digest')."</label>";

			$cache_images = sql_bool_to_bool(db_fetch_result($result, 0, "cache_images"));

			if ($cache_images) {
				$checked = "checked";
			} else {
				$checked = "";
			}

			if (ENABLE_SIMPLEPIE && SIMPLEPIE_CACHE_IMAGES) {
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

			$title = htmlspecialchars($title, ENT_QUOTES);

			print "<div class='dlgButtons'>
				<div style=\"float : left\">
					<input type='submit' class='button'			
					onclick='return unsubscribeFeed($feed_id, \"$title\")' value=\"".__('Unsubscribe')."\">
				</div>
				<input type=\"submit\" class=\"button\" 
				onclick=\"return feedEditSave()\" value=\"".__('Save')."\">
				<input type='submit' class='button'			
				onclick=\"return feedEditCancel()\" value=\"".__('Cancel')."\">
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

			if (ALLOW_SELECT_UPDATE_METHOD) {
				print " " . __('using') . " ";
				print_select_hash("update_method", $update_method, $update_methods, 
					"disabled");			
				batch_edit_cbox("update_method");
			}

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
				$checked>&nbsp;<label id=\"private_l\" class='insensitive' for=\"private\">".__('Hide from "Other Feeds"')."</label>";

			print "&nbsp;"; batch_edit_cbox("private", "private_l");

			print "<br/><input disabled type=\"checkbox\" id=\"rtl_content\" name=\"rtl_content\"
				$checked>&nbsp;<label class='insensitive' id=\"rtl_content_l\" for=\"rtl_content\">".__('Right-to-left content')."</label>";

			print "&nbsp;"; batch_edit_cbox("rtl_content", "rtl_content_l");

			print "<br/><input disabled type=\"checkbox\" id=\"hidden\" name=\"hidden\"
				$checked>&nbsp;<label class='insensitive' id=\"hidden_l\" for=\"hidden\">".__('Hide from my feed list')."</label>";

			print "&nbsp;"; batch_edit_cbox("hidden", "hidden_l");

			print "<br/><input disabled type=\"checkbox\" id=\"include_in_digest\" 
				name=\"include_in_digest\" 
				$checked>&nbsp;<label id=\"include_in_digest_l\" class='insensitive' for=\"include_in_digest\">".__('Include in e-mail digest')."</label>";

			print "&nbsp;"; batch_edit_cbox("include_in_digest", "include_in_digest_l");

			print "<br/><input disabled type=\"checkbox\" id=\"cache_images\" 
				name=\"cache_images\" 
				$checked>&nbsp;<label class='insensitive' id=\"cache_images_l\" 
					for=\"cache_images\">".
				__('Cache images locally')."</label>";


			if (ENABLE_SIMPLEPIE && SIMPLEPIE_CACHE_IMAGES) {
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
			$hidden = checkbox_to_sql_bool(db_escape_string($_POST["hidden"]));
			$include_in_digest = checkbox_to_sql_bool(
				db_escape_string($_POST["include_in_digest"]));
			$cache_images = checkbox_to_sql_bool(
				db_escape_string($_POST["cache_images"]));
			$update_method = (int) db_escape_string($_POST["update_method"]);

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

			if (ENABLE_SIMPLEPIE && SIMPLEPIE_CACHE_IMAGES) {
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
					hidden = $hidden,
					$cache_images_qpart
					include_in_digest = $include_in_digest,
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

						case "hidden":
							$qpart = "hidden = '$hidden'";
							break;

						case "include_in_digest":
							$qpart = "include_in_digest = '$include_in_digest'";
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

			$ids = split(",", db_escape_string($_GET["ids"]));

			foreach ($ids as $id) {

				if ($id > 0) {

					db_query($link, "DELETE FROM ttrss_feeds 
						WHERE id = '$id' AND owner_uid = " . $_SESSION["uid"]);

					$icons_dir = ICONS_DIR;
					
					if (file_exists($icons_dir . "/$id.ico")) {
						unlink($icons_dir . "/$id.ico");
					}
				} else {
					label_remove($link, -11-$id, $_SESSION["uid"]);
				}
			}
		}

		if ($subop == "clear") {
			$id = db_escape_string($_GET["id"]);
			clear_feed_articles($link, $id);
		}

		if ($subop == "rescore") {
			$ids = split(",", db_escape_string($_GET["ids"]));

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
		
			if (!WEB_DEMO_MODE) {

				$feed_url = db_escape_string(trim($_REQUEST["feed_url"]));
				$cat_id = db_escape_string($_REQUEST["cat_id"]);
				$p_from = db_escape_string($_REQUEST["from"]);

				/* only read authentication information from POST */

				$auth_login = db_escape_string(trim($_POST["auth_login"]));
				$auth_pass = db_escape_string(trim($_POST["auth_pass"]));

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

				if (subscribe_to_feed($link, $feed_url, $cat_id, $auth_login, $auth_pass)) {
					print_notice(T_sprintf("Subscribed to <b>%s</b>.", $feed_url));
				} else {
					print_warning(T_sprintf("Already subscribed to <b>%s</b>.", $feed_url));
				}

				if ($p_from != 'tt-rss') {
					$tt_uri = ($_SERVER['HTTPS'] != "on" ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . preg_replace('/backend\.php.*$/', 'tt-rss.php', $_SERVER["REQUEST_URI"]);


					$tp_uri = ($_SERVER['HTTPS'] != "on" ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . preg_replace('/backend\.php.*$/', 'prefs.php', $_SERVER["REQUEST_URI"]);

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

			$action = $_REQUEST["action"];

			if ($action == "save") {

				$cat_title = db_escape_string(trim($_REQUEST["value"]));
				$cat_id = db_escape_string($_GET["cid"]);

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
	
					$feed_cat = db_escape_string(trim($_GET["cat"]));
	
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
	
							print format_warning(__("Unable to delete non empty feed categories."));
								
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
					onclick=\"javascript:addFeedCat()\" value=\"".__('Create category')."\"></div>";
	
			$result = db_query($link, "SELECT title,id FROM ttrss_feed_categories
				WHERE owner_uid = ".$_SESSION["uid"]."
				ORDER BY title");

			print "<p>";

			if (db_num_rows($result) != 0) {

				print "<table width=\"100%\" class=\"prefFeedCatList\" 
					cellspacing=\"0\">";

				print "<tr><td class=\"selectPrompt\" colspan=\"8\">
				".__('Select:')." 
					<a href=\"javascript:selectPrefRows('fcat', true)\">".__('All')."</a>,
					<a href=\"javascript:selectPrefRows('fcat', false)\">".__('None')."</a>
					</td></tr>";

				print "</table>";

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

			print "<div style='float : right'>
				<input type='submit' class='button'			
				onclick=\"selectTab('feedConfig')\" value=\"".__('Close this window')."\"></div>";

			print "<div id=\"catOpToolbar\">";
	
			print "
				<input type=\"submit\" class=\"button\" disabled=\"true\"
					onclick=\"return removeSelectedFeedCats()\" value=\"".__('Remove')."\">";
	
			print "</div>";

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

		$feed_search = db_escape_string($_GET["search"]);

		if (array_key_exists("search", $_GET)) {
			$_SESSION["prefs_feed_search"] = $feed_search;
		} else {
			$feed_search = $_SESSION["prefs_feed_search"];
		}

		print "<div class=\"feedEditSearch\">
			<input id=\"feed_search\" size=\"20\" type=\"search\"
				onfocus=\"javascript:disableHotkeys();\" 
				onblur=\"javascript:enableHotkeys();\"
				onchange=\"javascript:updateFeedList()\" value=\"$feed_search\">
			<input type=\"submit\" class=\"button\" 
				onclick=\"javascript:updateFeedList()\" value=\"".__('Search')."\">
			</div>";
		
		print "<input onclick=\"javascript:displayDlg('quickAddFeed')\"
			type=\"submit\" id=\"subscribe_to_feed_btn\" 
			class=\"button\" value=\"".__('Subscribe to feed')."\">"; 

		if (ENABLE_FEED_BROWSER && !SINGLE_USER_MODE) {
			print " <input type=\"submit\" class=\"button\"
				id=\"top25_feeds_btn\"
				onclick=\"javascript:browseFeeds()\" value=\"".__('Top 25')."\">";
		}

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

		$show_last_article_info = false;
		$show_last_article_checked = "";
		$show_last_article_qpart = "";

		if ($_GET["slat"] == "true") {
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
				F1.hidden,
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

				$hidden = sql_bool_to_bool($line["hidden"]);

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

				if ($hidden) {
					$edit_title = "<span class=\"insensitive\">$edit_title (Hidden)</span>";
					$last_updated = "<span class=\"insensitive\">$last_updated</span>";
					$last_article = "<span class=\"insensitive\">$last_article</span>";
				}

				if ($last_error) {
					$edit_title = "<span class=\"feed_error\">$edit_title</span>";
					$last_updated = "<span class=\"feed_error\">$last_updated</span>";
					$last_article = "<span class=\"feed_error\">$last_article</span>";
				}

				$parent_title = $line["parent_title"];
				if ($parent_title) {
					$parent_title = "<span class='groupPrompt'>(linked to 
						$parent_title)</span>";
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

			print "<div id=\"feedOpToolbar\">";

			if (get_pref($link, 'ENABLE_FEED_CATS')) {

				print __('Selection:') . " ";

				print_feed_cat_select($link, "sfeed_set_fcat", "", "disabled");

				print " <input type=\"submit\" class=\"button\" disabled=\"true\"
					onclick=\"javascript:categorizeSelectedFeeds()\" value=\"".
					__('Recategorize')."\">";
			}
				
			print "</div>";

			print "<select id=\"feedActionChooser\" onchange=\"feedActionChange()\">
				<option value=\"facDefault\" selected>".__('Actions...')."</option>
				<option disabled>--------</option>
				<option style=\"color : #5050aa\" disabled>".__('Selection:')."</option>
				<option value=\"facEdit\">&nbsp;&nbsp;".__('Edit')."</option>";

			if (FORCE_ARTICLE_PURGE == 0) {
				print 
					"<option value=\"facPurge\">&nbsp;&nbsp;".__('Manual purge')."</option>";
			}

			print "
				<option value=\"facClear\">&nbsp;&nbsp;".__('Clear feed data')."</option>
				<option value=\"facRescore\">&nbsp;&nbsp;".__('Rescore articles')."</option>
				<option value=\"facUnsubscribe\">&nbsp;&nbsp;".__('Unsubscribe')."</option>";

				if (get_pref($link, 'ENABLE_FEED_CATS')) {

					print "<option disabled>--------</option>
					<option style=\"color : #5050aa\" disabled>".__('Other:')."</option>
					<option value=\"facEditCats\">&nbsp;&nbsp;".__('Edit categories')."
					</option>";
				}

			print "</select>";
		}

		print "<h3>".__('OPML')."</h3>

		<div style='float : left'>
		<form	enctype=\"multipart/form-data\" method=\"POST\" action=\"opml.php\">
		".__('File:')." <input id=\"opml_file\" name=\"opml_file\" type=\"file\">&nbsp;
			<input type=\"hidden\" name=\"op\" value=\"Import\">
			<input class=\"button\" onclick=\"return validateOpmlImport();\"
				type=\"submit\" value=\"".__('Import')."\">
				</form></div>";

		print "&nbsp;";

		print "<input type=\"submit\" 
			class=\"button\" onclick=\"gotoExportOpml()\" 
				value=\"".__('Export OPML')."\">";			


		print "<h3>" . __("Firefox Integration") . "</h3>";
                
                print "<p>" . __('This Tiny Tiny RSS site can be used as a Firefox Feed Reader by clicking the link below.');
		print "</p><p> <a class='visibleLinkB' href='javascript:window.navigator.registerContentHandler(" .
                      "\"application/vnd.mozilla.maybe.feed\", " .
                      "\"" . add_feed_url() . "\", " . " \"Tiny Tiny RSS\")'>" .
                      __('Click here to register this site as a feed reader.') . "</a></p>";


		print "<h3>".__("Published articles")."</h3>";

		if (!get_pref($link, "_PREFS_PUBLISH_KEY")) {
			set_pref($link, "_PREFS_PUBLISH_KEY", generate_publish_key());
		}
		
		print "<p>".__('Published articles are exported as a public RSS feed and can be subscribed by anyone who knows the URL specified below.')."</p>";

		$url_path = article_publish_url($link);

		print "<p><a class=\"visibleLinkB\" id=\"pubGenAddress\" target=\"_blank\" href=\"$url_path\">".__("Link to published articles feed.")."</a></p>";

		print "<p><input type=\"submit\" onclick=\"return pubRegenKey()\" class=\"button\"
			value=\"".__('Generate another link')."\">";
		/* print " <input type=\"submit\" onclick=\"return pubToClipboard()\" class=\"button\"
			value=\"".__('Copy link to clipboard')."\">"; */
		print "</p>";

	}
?>

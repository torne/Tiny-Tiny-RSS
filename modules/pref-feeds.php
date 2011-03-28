<?php

	function batch_edit_cbox($elem, $label = false) {
		print "<input type=\"checkbox\" title=\"".__("Check to enable field")."\"
			onchange=\"dijit.byId('feedEditDlg').toggleField(this, '$elem', '$label')\">";
	}

	function module_pref_feeds($link) {

		global $update_intervals;
		global $purge_intervals;
		global $update_methods;

		$subop = $_REQUEST["subop"];
		$quiet = $_REQUEST["quiet"];
		$mode = $_REQUEST["mode"];

		if ($subop == "renamecat") {
			$title = db_escape_string($_REQUEST['title']);
			$id = db_escape_string($_REQUEST['id']);

			if ($title) {
				db_query($link, "UPDATE ttrss_feed_categories SET
					title = '$title' WHERE id = '$id' AND owner_uid = " . $_SESSION["uid"]);
			}
			return;
		}

		if ($subop == "remtwitterinfo") {

			db_query($link, "UPDATE ttrss_users SET twitter_oauth = NULL
				WHERE id = " . $_SESSION['uid']);

			return;
		}

		if ($subop == "getfeedtree") {

			$root = array();
			$root['id'] = 'root';
			$root['name'] = __('Feeds');
			$root['items'] = array();
			$root['type'] = 'category';

			if (get_pref($link, 'ENABLE_FEED_CATS')) {

				$result = db_query($link, "SELECT id, title FROM ttrss_feed_categories
					WHERE owner_uid = " . $_SESSION["uid"] . " ORDER BY order_id, title");

				while ($line = db_fetch_assoc($result)) {
					$cat = array();
					$cat['id'] = 'CAT:' . $line['id'];
					$cat['bare_id'] = $feed_id;
					$cat['name'] = $line['title'];
					$cat['items'] = array();
					$cat['type'] = 'category';

					$feed_result = db_query($link, "SELECT id, title, last_error,
						".SUBSTRING_FOR_DATE."(last_updated,1,19) AS last_updated
						FROM ttrss_feeds
						WHERE cat_id = '".$line['id']."' AND owner_uid = ".$_SESSION["uid"].
						" ORDER BY order_id, title");

					while ($feed_line = db_fetch_assoc($feed_result)) {
						$feed = array();
						$feed['id'] = 'FEED:' . $feed_line['id'];
						$feed['bare_id'] = $feed_line['id'];
						$feed['name'] = $feed_line['title'];
						$feed['checkbox'] = false;
						$feed['error'] = $feed_line['last_error'];
						$feed['icon'] = getFeedIcon($feed_line['id']);
						$feed['param'] = make_local_datetime($link,
							$feed_line['last_updated'], true);

						array_push($cat['items'], $feed);
					}

					array_push($root['items'], $cat);
				}

				/* Uncategorized is a special case */

				$cat = array();
				$cat['id'] = 'CAT:0';
				$cat['bare_id'] = 0;
				$cat['name'] = __("Uncategorized");
				$cat['items'] = array();
				$cat['type'] = 'category';

				$feed_result = db_query($link, "SELECT id, title,last_error,
					".SUBSTRING_FOR_DATE."(last_updated,1,19) AS last_updated
					FROM ttrss_feeds
					WHERE cat_id IS NULL AND owner_uid = ".$_SESSION["uid"].
					" ORDER BY order_id, title");

				while ($feed_line = db_fetch_assoc($feed_result)) {
					$feed = array();
					$feed['id'] = 'FEED:' . $feed_line['id'];
					$feed['bare_id'] = $feed_line['id'];
					$feed['name'] = $feed_line['title'];
					$feed['checkbox'] = false;
					$feed['error'] = $feed_line['last_error'];
					$feed['icon'] = getFeedIcon($feed_line['id']);
					$feed['param'] = make_local_datetime($link,
						$feed_line['last_updated'], true);

					array_push($cat['items'], $feed);
				}

				array_push($root['items'], $cat);
			} else {
				$feed_result = db_query($link, "SELECT id, title, last_error,
					".SUBSTRING_FOR_DATE."(last_updated,1,19) AS last_updated
					FROM ttrss_feeds
					WHERE owner_uid = ".$_SESSION["uid"].
					" ORDER BY order_id, title");

				while ($feed_line = db_fetch_assoc($feed_result)) {
					$feed = array();
					$feed['id'] = 'FEED:' . $feed_line['id'];
					$feed['bare_id'] = $feed_line['id'];
					$feed['name'] = $feed_line['title'];
					$feed['checkbox'] = false;
					$feed['error'] = $feed_line['last_error'];
					$feed['icon'] = getFeedIcon($feed_line['id']);
					$feed['param'] = make_local_datetime($link,
						$feed_line['last_updated'], true);

					array_push($root['items'], $feed);
				}
			}

			$fl = array();
			$fl['identifier'] = 'id';
			$fl['label'] = 'name';
			$fl['items'] = array($root);

			print json_encode($fl);
			return;
		}

		if ($subop == "catsortreset") {
			db_query($link, "UPDATE ttrss_feed_categories
					SET order_id = 0 WHERE owner_uid = " . $_SESSION["uid"]);
			return;
		}

		if ($subop == "feedsortreset") {
			db_query($link, "UPDATE ttrss_feeds
					SET order_id = 0 WHERE owner_uid = " . $_SESSION["uid"]);
			return;
		}

		if ($subop == "savefeedorder") {
#			if ($_POST['payload']) {
#				file_put_contents("/tmp/blahblah.txt", $_POST['payload']);
#				$data = json_decode($_POST['payload'], true);
#			} else {
#				$data = json_decode(file_get_contents("/tmp/blahblah.txt"), true);
#			}

			$data = json_decode($_POST['payload'], true);

			if (is_array($data) && is_array($data['items'])) {
				$cat_order_id = 0;

				$data_map = array();

				foreach ($data['items'] as $item) {

					if ($item['id'] != 'root') {
						if (is_array($item['items'])) {
							if (isset($item['items']['_reference'])) {
								$data_map[$item['id']] = array($item['items']);
							} else {
								$data_map[$item['id']] =& $item['items'];
							}
						}
					}
				}

				foreach ($data['items'][0]['items'] as $item) {
					$id = $item['_reference'];
					$bare_id = substr($id, strpos($id, ':')+1);

					++$cat_order_id;

					if ($bare_id > 0) {
						db_query($link, "UPDATE ttrss_feed_categories
							SET order_id = '$cat_order_id' WHERE id = '$bare_id' AND
							owner_uid = " . $_SESSION["uid"]);
					}

					$feed_order_id = 0;

					if (is_array($data_map[$id])) {
						foreach ($data_map[$id] as $feed) {
							$id = $feed['_reference'];
							$feed_id = substr($id, strpos($id, ':')+1);

							if ($bare_id != 0)
								$cat_query = "cat_id = '$bare_id'";
							else
								$cat_query = "cat_id = NULL";

							db_query($link, "UPDATE ttrss_feeds
								SET order_id = '$feed_order_id',
								$cat_query
								WHERE id = '$feed_id' AND
									owner_uid = " . $_SESSION["uid"]);

							++$feed_order_id;
						}
					}
				}
			}

			return;
		}

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

		if ($subop == "editfeed") {

			$feed_id = db_escape_string($_REQUEST["id"]);

			$result = db_query($link,
				"SELECT * FROM ttrss_feeds WHERE id = '$feed_id' AND
					owner_uid = " . $_SESSION["uid"]);

			$title = htmlspecialchars(db_fetch_result($result,
				0, "title"));

			print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"id\" value=\"$feed_id\">";
			print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"pref-feeds\">";
			print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"subop\" value=\"editSave\">";

			print "<div class=\"dlgSec\">".__("Feed")."</div>";
			print "<div class=\"dlgSecCont\">";

			/* Title */

			print "<input dojoType=\"dijit.form.ValidationTextBox\" required=\"1\"
				placeHolder=\"".__("Feed Title")."\"
				style=\"font-size : 16px; width: 20em\" name=\"title\" value=\"$title\">";

			/* Feed URL */

			$feed_url = db_fetch_result($result, 0, "feed_url");
			$feed_url = htmlspecialchars(db_fetch_result($result,
				0, "feed_url"));

			print "<hr/>";

			print __('URL:') . " ";
			print "<input dojoType=\"dijit.form.ValidationTextBox\" required=\"1\"
				placeHolder=\"".__("Feed URL")."\"
				regExp='^(http|https)://.*' style=\"width : 20em\"
				name=\"feed_url\" value=\"$feed_url\">";

			/* Category */

			if (get_pref($link, 'ENABLE_FEED_CATS')) {

				$cat_id = db_fetch_result($result, 0, "cat_id");

				print "<hr/>";

				print __('Place in category:') . " ";

				print_feed_cat_select($link, "cat_id", $cat_id,
					'dojoType="dijit.form.Select"');
			}

			print "</div>";

			print "<div class=\"dlgSec\">".__("Update")."</div>";
			print "<div class=\"dlgSecCont\">";

			/* Update Interval */

			$update_interval = db_fetch_result($result, 0, "update_interval");

			print_select_hash("update_interval", $update_interval, $update_intervals,
				'dojoType="dijit.form.Select"');

			/* Update method */

			$update_method = db_fetch_result($result, 0, "update_method",
				'dojoType="dijit.form.Select"');

			print " " . __('using') . " ";
			print_select_hash("update_method", $update_method, $update_methods,
				'dojoType="dijit.form.Select"');

			$purge_interval = db_fetch_result($result, 0, "purge_interval");

			if (FORCE_ARTICLE_PURGE == 0) {

				/* Purge intl */

				print "<hr/>";

				print __('Article purging:') . " ";

				print_select_hash("purge_interval", $purge_interval, $purge_intervals,
					'dojoType="dijit.form.Select"');

			} else {
				print "<input style=\"display : none\" name='purge_interval'
					dojoType=\"dijit.form.TextBox\" value='$purge_interval'>";

			}

			print "</div>";
			print "<div class=\"dlgSec\">".__("Authentication")."</div>";
			print "<div class=\"dlgSecCont\">";

			$auth_login = htmlspecialchars(db_fetch_result($result, 0, "auth_login"));

#			print "<table>";

#			print "<tr><td>" . __('Login:') . "</td><td>";

			print "<input dojoType=\"dijit.form.TextBox\" id=\"feedEditDlg_login\"
				placeHolder=\"".__("Login")."\"
				name=\"auth_login\" value=\"$auth_login\"><hr/>";

#			print "</tr><tr><td>" . __("Password:") . "</td><td>";

			$auth_pass = htmlspecialchars(db_fetch_result($result, 0, "auth_pass"));

			print "<input dojoType=\"dijit.form.TextBox\" type=\"password\" name=\"auth_pass\"
				placeHolder=\"".__("Password")."\"
				value=\"$auth_pass\">";

			print "<div dojoType=\"dijit.Tooltip\" connectId=\"feedEditDlg_login\" position=\"below\">
				".__('<b>Hint:</b> you need to fill in your login information if your feed requires authentication, except for Twitter feeds.')."
				</div>";

#			print "</td></tr></table>";

			print "</div>";
			print "<div class=\"dlgSec\">".__("Options")."</div>";
			print "<div class=\"dlgSecCont\">";

#			print "<div style=\"line-height : 100%\">";

			$private = sql_bool_to_bool(db_fetch_result($result, 0, "private"));

			if ($private) {
				$checked = "checked=\"1\"";
			} else {
				$checked = "";
			}

			print "<input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" name=\"private\" id=\"private\"
				$checked>&nbsp;<label for=\"private\">".__('Hide from Popular feeds')."</label>";

			$rtl_content = sql_bool_to_bool(db_fetch_result($result, 0, "rtl_content"));

			if ($rtl_content) {
				$checked = "checked=\"1\"";
			} else {
				$checked = "";
			}

			print "<hr/><input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" id=\"rtl_content\" name=\"rtl_content\"
				$checked>&nbsp;<label for=\"rtl_content\">".__('Right-to-left content')."</label>";

			$include_in_digest = sql_bool_to_bool(db_fetch_result($result, 0, "include_in_digest"));

			if ($include_in_digest) {
				$checked = "checked=\"1\"";
			} else {
				$checked = "";
			}

			print "<hr/><input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" id=\"include_in_digest\"
				name=\"include_in_digest\"
				$checked>&nbsp;<label for=\"include_in_digest\">".__('Include in e-mail digest')."</label>";


			$always_display_enclosures = sql_bool_to_bool(db_fetch_result($result, 0, "always_display_enclosures"));

			if ($always_display_enclosures) {
				$checked = "checked";
			} else {
				$checked = "";
			}

			print "<hr/><input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" id=\"always_display_enclosures\"
				name=\"always_display_enclosures\"
				$checked>&nbsp;<label for=\"always_display_enclosures\">".__('Always display image attachments')."</label>";


			$cache_images = sql_bool_to_bool(db_fetch_result($result, 0, "cache_images"));

			if ($cache_images) {
				$checked = "checked=\"1\"";
			} else {
				$checked = "";
			}

			if (SIMPLEPIE_CACHE_IMAGES) {
				print "<hr/><input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" id=\"cache_images\"
				name=\"cache_images\"
				$checked>&nbsp;<label for=\"cache_images\">".
				__('Cache images locally (SimplePie only)')."</label>";
			}

#			print "</div>";
			print "</div>";

			/* Icon */

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
				<button dojoType=\"dijit.form.Button\" onclick=\"return uploadFeedIcon();\"
					type=\"submit\">".__('Replace')."</button>
				<button dojoType=\"dijit.form.Button\" onclick=\"return removeFeedIcon($feed_id);\"
					type=\"submit\">".__('Remove')."</button>
				</form>";

			print "</div>";

			$title = htmlspecialchars($title, ENT_QUOTES);

			print "<div class='dlgButtons'>
				<div style=\"float : left\">
				<button dojoType=\"dijit.form.Button\" onclick='return unsubscribeFeed($feed_id, \"$title\")'>".
					__('Unsubscribe')."</button>
				</div>
				<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('feedEditDlg').execute()\">".__('Save')."</button>
				<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('feedEditDlg').hide()\">".__('Cancel')."</button>
			</div>";

			return;
		}

		if ($subop == "editfeeds") {

			$feed_ids = db_escape_string($_REQUEST["ids"]);

			print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"ids\" value=\"$feed_ids\">";
			print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"pref-feeds\">";
			print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"subop\" value=\"batchEditSave\">";

			print "<div class=\"dlgSec\">".__("Feed")."</div>";
			print "<div class=\"dlgSecCont\">";

			/* Title */

			print "<input dojoType=\"dijit.form.ValidationTextBox\"
				disabled=\"1\" style=\"font-size : 16px; width : 20em;\" required=\"1\"
				name=\"title\" value=\"$title\">";

			batch_edit_cbox("title");

			/* Feed URL */

			print "<br/>";

			print __('URL:') . " ";
			print "<input dojoType=\"dijit.form.ValidationTextBox\" disabled=\"1\"
				required=\"1\" regExp='^(http|https)://.*' style=\"width : 20em\"
				name=\"feed_url\" value=\"$feed_url\">";

			batch_edit_cbox("feed_url");

			/* Category */

			if (get_pref($link, 'ENABLE_FEED_CATS')) {

				print "<br/>";

				print __('Place in category:') . " ";

				print_feed_cat_select($link, "cat_id", $cat_id,
					'disabled="1" dojoType="dijit.form.Select"');

				batch_edit_cbox("cat_id");

			}

			print "</div>";

			print "<div class=\"dlgSec\">".__("Update")."</div>";
			print "<div class=\"dlgSecCont\">";

			/* Update Interval */

			print_select_hash("update_interval", $update_interval, $update_intervals,
				'disabled="1" dojoType="dijit.form.Select"');

			batch_edit_cbox("update_interval");

			/* Update method */

			print " " . __('using') . " ";
			print_select_hash("update_method", $update_method, $update_methods,
				'disabled="1" dojoType="dijit.form.Select"');
			batch_edit_cbox("update_method");

			/* Purge intl */

			if (FORCE_ARTICLE_PURGE == 0) {

				print "<br/>";

				print __('Article purging:') . " ";

				print_select_hash("purge_interval", $purge_interval, $purge_intervals,
					'disabled="1" dojoType="dijit.form.Select"');

				batch_edit_cbox("purge_interval");
			}

			print "</div>";
			print "<div class=\"dlgSec\">".__("Authentication")."</div>";
			print "<div class=\"dlgSecCont\">";

			print "<input dojoType=\"dijit.form.TextBox\"
				placeHolder=\"".__("Login")."\" disabled=\"1\"
				name=\"auth_login\" value=\"$auth_login\">";

			batch_edit_cbox("auth_login");

			print "<br/><input dojoType=\"dijit.form.TextBox\" type=\"password\" name=\"auth_pass\"
				placeHolder=\"".__("Password")."\" disabled=\"1\"
				value=\"$auth_pass\">";

			batch_edit_cbox("auth_pass");

			print "</div>";
			print "<div class=\"dlgSec\">".__("Options")."</div>";
			print "<div class=\"dlgSecCont\">";

			print "<input disabled=\"1\" type=\"checkbox\" name=\"private\" id=\"private\"
				dojoType=\"dijit.form.CheckBox\">&nbsp;<label id=\"private_l\" class='insensitive' for=\"private\">".__('Hide from Popular feeds')."</label>";

			print "&nbsp;"; batch_edit_cbox("private", "private_l");

			print "<br/><input disabled=\"1\" type=\"checkbox\" id=\"rtl_content\" name=\"rtl_content\"
				dojoType=\"dijit.form.CheckBox\">&nbsp;<label class='insensitive' id=\"rtl_content_l\" for=\"rtl_content\">".__('Right-to-left content')."</label>";

			print "&nbsp;"; batch_edit_cbox("rtl_content", "rtl_content_l");

			print "<br/><input disabled=\"1\" type=\"checkbox\" id=\"include_in_digest\"
				name=\"include_in_digest\"
				dojoType=\"dijit.form.CheckBox\">&nbsp;<label id=\"include_in_digest_l\" class='insensitive' for=\"include_in_digest\">".__('Include in e-mail digest')."</label>";

			print "&nbsp;"; batch_edit_cbox("include_in_digest", "include_in_digest_l");

			print "<br/><input disabled=\"1\" type=\"checkbox\" id=\"always_display_enclosures\"
				name=\"always_display_enclosures\"
				dojoType=\"dijit.form.CheckBox\">&nbsp;<label id=\"always_display_enclosures_l\" class='insensitive' for=\"always_display_enclosures\">".__('Always display image attachments')."</label>";

			print "&nbsp;"; batch_edit_cbox("always_display_enclosures", "always_display_enclosures_l");

			if (SIMPLEPIE_CACHE_IMAGES) {
				print "<br/><input disabled=\"1\" type=\"checkbox\" id=\"cache_images\"
					name=\"cache_images\"
					dojoType=\"dijit.form.CheckBox\">&nbsp;<label class='insensitive' id=\"cache_images_l\"
					for=\"cache_images\">".
				__('Cache images locally')."</label>";


				print "&nbsp;"; batch_edit_cbox("cache_images", "cache_images_l");
			}

			print "</div>";

			print "<div class='dlgButtons'>
				<button dojoType=\"dijit.form.Button\"
					onclick=\"return dijit.byId('feedEditDlg').execute()\">".
					__('Save')."</button>
				<button dojoType=\"dijit.form.Button\"
				onclick=\"return dijit.byId('feedEditDlg').hide()\">".
					__('Cancel')."</button>
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

			if (SIMPLEPIE_CACHE_IMAGES) {
				$cache_images_qpart = "cache_images = $cache_images,";
			} else {
				$cache_images_qpart = "";
			}

			if ($subop == "editSave") {

				$result = db_query($link, "UPDATE ttrss_feeds SET
					$category_qpart
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
			return;
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

				$result = db_query($link, "SELECT
					title, content, link, ref_id, author,".
					SUBSTRING_FOR_DATE."(updated, 1, 19) AS updated
				  	FROM
						ttrss_user_entries, ttrss_entries
						WHERE ref_id = id AND feed_id = '$id' AND
							owner_uid = " .$_SESSION['uid']."
						");

				$scores = array();

				while ($line = db_fetch_assoc($result)) {

					$tags = get_article_tags($link, $line["ref_id"]);

					$article_filters = get_article_filters($filters, $line['title'],
						$line['content'], $line['link'], strtotime($line['updated']),
						$line['author'], $tags);

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

				$tmp_result = db_query($link, "SELECT
					title, content, link, ref_id, author,".
					  	SUBSTRING_FOR_DATE."(updated, 1, 19) AS updated
						FROM
						ttrss_user_entries, ttrss_entries
						WHERE ref_id = id AND feed_id = '$id' AND
							owner_uid = " .$_SESSION['uid']."
						");

				$scores = array();

				while ($line = db_fetch_assoc($tmp_result)) {

					$tags = get_article_tags($link, $line["ref_id"]);

					$article_filters = get_article_filters($filters, $line['title'],
						$line['content'], $line['link'], strtotime($line['updated']),
						$line['author'], $tags);

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
			case 4:
				print_notice("Multiple feed URLs found.");

				$feed_urls = get_feeds_from_html($feed_url);
				break;
			case 5:
				print_error(T_sprintf("Could not subscribe to <b>%s</b>.<br>Can't download the Feed URL.", $feed_url));
				break;
			}

			if ($p_from != 'tt-rss') {

				if ($feed_urls) {

					print "<form action=\"backend.php\">";
					print "<input type=\"hidden\" name=\"op\" value=\"pref-feeds\">";
					print "<input type=\"hidden\" name=\"quiet\" value=\"1\">";
					print "<input type=\"hidden\" name=\"subop\" value=\"add\">";

					print "<select name=\"feed_url\">";

					foreach ($feed_urls as $url => $name) {
						$url = htmlspecialchars($url);
						$name = htmlspecialchars($name);

						print "<option value=\"$url\">$name</option>";
					}

					print "<input type=\"submit\" value=\"".__("Subscribe to selected feed").
						"\">";

					print "</form>";
				}

				$tp_uri = get_self_url_prefix() . "/prefs.php";
				$tt_uri = get_self_url_prefix();

				if ($rc <= 2){
					$result = db_query($link, "SELECT id FROM ttrss_feeds WHERE
						feed_url = '$feed_url' AND owner_uid = " . $_SESSION["uid"]);

					$feed_id = db_fetch_result($result, 0, "id");
				} else {
					$feed_id = 0;
				}
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
					WHERE id = '$id'
				  	AND owner_uid = " . $_SESSION["uid"]);

			}

			db_query($link, "COMMIT");

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

			if ($action == "add") {

				$feed_cat = db_escape_string(trim($_REQUEST["cat"]));

				if (!add_feed_category($link, $feed_cat))
					print_warning(T_sprintf("Category <b>$%s</b> already exists in the database.", $feed_cat));

			}

			if ($action == "remove") {

				$ids = split(",", db_escape_string($_REQUEST["ids"]));

				foreach ($ids as $id) {
					remove_feed_category($link, $id, $_SESSION["uid"]);
				}
			}

			print "<div dojoType=\"dijit.Toolbar\">
				<input dojoType=\"dijit.form.ValidationTextBox\" required=\"1\" name=\"newcat\">
					<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('feedCatEditDlg').addCategory()\">".
						__('Create category')."</button></div>";

			$result = db_query($link, "SELECT title,id FROM ttrss_feed_categories
				WHERE owner_uid = ".$_SESSION["uid"]."
				ORDER BY title");

#			print "<p>";

			if (db_num_rows($result) != 0) {

				print "<div class=\"prefFeedCatHolder\">";

#				print "<form id=\"feed_cat_edit_form\" onsubmit=\"return false\">";

				print "<table width=\"100%\" class=\"prefFeedCatList\"
					cellspacing=\"0\" id=\"prefFeedCatList\">";

				$lnum = 0;

				while ($line = db_fetch_assoc($result)) {

					$class = ($lnum % 2) ? "even" : "odd";

					$cat_id = $line["id"];
					$this_row_id = "id=\"FCATR-$cat_id\"";

					print "<tr class=\"\" $this_row_id>";

					$edit_title = htmlspecialchars($line["title"]);

					print "<td width='5%' align='center'><input
						onclick='toggleSelectRow2(this);' dojoType=\"dijit.form.CheckBox\"
						type=\"checkbox\"></td>";

					print "<td>";

#					print "<span id=\"FCATT-$cat_id\">" .
#						$edit_title . "</span>";

					print "<span dojoType=\"dijit.InlineEditBox\"
						width=\"300px\" autoSave=\"false\"
						cat-id=\"$cat_id\">" . $edit_title .
						"<script type=\"dojo/method\" event=\"onChange\" args=\"item\">
							var elem = this;
							dojo.xhrPost({
								url: 'backend.php',
								content: {op: 'pref-feeds', subop: 'editCats',
									action: 'save',
									value: this.value,
									cid: this.srcNodeRef.getAttribute('cat-id')},
									load: function(response) {
										elem.attr('value', response);
										updateFeedList();
								}
							});
						</script>
					</span>";

					print "</td></tr>";

					++$lnum;
				}

				print "</table>";

#				print "</form>";

				print "</div>";

			} else {
				print "<p>".__('No feed categories defined.')."</p>";
			}

			print "<div class='dlgButtons'>
				<div style='float : left'>
				<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('feedCatEditDlg').removeSelected()\">".
				__('Remove selected categories')."</button>
				</div>";

			print "<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('feedCatEditDlg').hide()\">".
				__('Close this window')."</button></div>";

			return;

		}

		if ($quiet) return;

		print "<div dojoType=\"dijit.layout.AccordionContainer\" region=\"center\">";
		print "<div id=\"pref-feeds-feeds\" dojoType=\"dijit.layout.AccordionPane\" title=\"".__('Feeds')."\">";

		$result = db_query($link, "SELECT COUNT(id) AS num_errors
			FROM ttrss_feeds WHERE last_error != '' AND owner_uid = ".$_SESSION["uid"]);

		$num_errors = db_fetch_result($result, 0, "num_errors");

		if ($num_errors > 0) {

			$error_button = "<button dojoType=\"dijit.form.Button\"
			  		onclick=\"showFeedsWithErrors()\" id=\"errorButton\">" .
				__("Feeds with errors") . "</button>";

//			print format_notice("<a href=\"javascript:showFeedsWithErrors()\">".
//				__('Some feeds have update errors (click for details)')."</a>");
		}

		if (DB_TYPE == "pgsql") {
			$interval_qpart = "NOW() - INTERVAL '3 months'";
		} else {
			$interval_qpart = "DATE_SUB(NOW(), INTERVAL 3 MONTH)";
		}

		$result = db_query($link, "SELECT COUNT(*) AS num_inactive FROM ttrss_feeds WHERE
					(SELECT MAX(updated) FROM ttrss_entries, ttrss_user_entries WHERE
						ttrss_entries.id = ref_id AND
							ttrss_user_entries.feed_id = ttrss_feeds.id) < $interval_qpart AND
			ttrss_feeds.owner_uid = ".$_SESSION["uid"]);

		$num_inactive = db_fetch_result($result, 0, "num_inactive");

		if ($num_inactive > 0) {
			$inactive_button = "<button dojoType=\"dijit.form.Button\"
			  		onclick=\"showInactiveFeeds()\">" .
					__("Inactive feeds") . "</button>";
		}

		$feed_search = db_escape_string($_REQUEST["search"]);

		if (array_key_exists("search", $_REQUEST)) {
			$_SESSION["prefs_feed_search"] = $feed_search;
		} else {
			$feed_search = $_SESSION["prefs_feed_search"];
		}

		print "<div dojoType=\"dijit.Toolbar\">";

		print "<div dojoType=\"dijit.form.DropDownButton\">".
				"<span>" . __('Select')."</span>";
		print "<div dojoType=\"dijit.Menu\" style=\"display: none;\">";
		print "<div onclick=\"dijit.byId('feedTree').model.setAllChecked(true)\"
			dojoType=\"dijit.MenuItem\">".__('All')."</div>";
		print "<div onclick=\"dijit.byId('feedTree').model.setAllChecked(false)\"
			dojoType=\"dijit.MenuItem\">".__('None')."</div>";
		print "</div></div>";

		print "<div dojoType=\"dijit.form.DropDownButton\">".
				"<span>" . __('Feeds')."</span>";
		print "<div dojoType=\"dijit.Menu\" style=\"display: none;\">";
		print "<div onclick=\"quickAddFeed()\"
			dojoType=\"dijit.MenuItem\">".__('Subscribe to feed')."</div>";
		print "<div onclick=\"editSelectedFeed()\"
			dojoType=\"dijit.MenuItem\">".__('Edit selected feeds')."</div>";
		print "<div onclick=\"resetFeedOrder()\"
			dojoType=\"dijit.MenuItem\">".__('Reset sort order')."</div>";
		print "</div></div>";

		if (get_pref($link, 'ENABLE_FEED_CATS')) {
			print "<div dojoType=\"dijit.form.DropDownButton\">".
					"<span>" . __('Categories')."</span>";
			print "<div dojoType=\"dijit.Menu\" style=\"display: none;\">";
			print "<div onclick=\"editFeedCats()\"
				dojoType=\"dijit.MenuItem\">".__('Edit categories')."</div>";
			print "<div onclick=\"resetCatOrder()\"
				dojoType=\"dijit.MenuItem\">".__('Reset sort order')."</div>";
			print "</div></div>";

		}

		print $error_button;
		print $inactive_button;

		print "<button dojoType=\"dijit.form.Button\" onclick=\"removeSelectedFeeds()\">"
			.__('Unsubscribe')."</button dojoType=\"dijit.form.Button\"> ";

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

		print "</div>"; # toolbar

		print "<div id=\"feedlistLoading\">
		<img src='images/indicator_tiny.gif'>".
		 __("Loading, please wait...")."</div>";

		print "<div dojoType=\"fox.PrefFeedStore\" jsId=\"feedStore\"
			url=\"backend.php?op=pref-feeds&subop=getfeedtree\">
		</div>
		<div dojoType=\"lib.CheckBoxStoreModel\" jsId=\"feedModel\" store=\"feedStore\"
		query=\"{id:'root'}\" rootId=\"root\" rootLabel=\"Feeds\"
			childrenAttrs=\"items\" checkboxStrict=\"false\" checkboxAll=\"false\">
		</div>
		<div dojoType=\"fox.PrefFeedTree\" id=\"feedTree\"
			dndController=\"dijit.tree.dndSource\"
			betweenThreshold=\"5\"
			model=\"feedModel\" openOnClick=\"false\">
		<script type=\"dojo/method\" event=\"onClick\" args=\"item\">
			var id = String(item.id);
			var bare_id = id.substr(id.indexOf(':')+1);

			if (id.match('FEED:')) {
				editFeed(bare_id);
			} else if (id.match('CAT:')) {
				editCat(bare_id, item);
			}
		</script>
		<script type=\"dojo/method\" event=\"onLoad\" args=\"item\">
			Element.hide(\"feedlistLoading\");
		</script>
		</div>";

		print "<div dojoType=\"dijit.Tooltip\" connectId=\"feedTree\" position=\"below\">
			".__('<b>Hint:</b> you can drag feeds and categories around.')."
			</div>";

		print "</div>"; # feeds pane

		print "<div dojoType=\"dijit.layout.AccordionPane\" title=\"".__('OPML')."\">";

		print "<p>" . __("Using OPML you can export and import your feeds and Tiny Tiny RSS settings.");

		print "<div class=\"insensitive\">" . __("Note: Only main settings profile can be migrated using OPML.") . "</div>";

		print "</p>";

		print "<iframe id=\"upload_iframe\"
			name=\"upload_iframe\" onload=\"opmlImportComplete(this)\"
			style=\"width: 400px; height: 100px; display: none;\"></iframe>";

		print "<form style='display : block' target=\"upload_iframe\"
			enctype=\"multipart/form-data\" method=\"POST\"
				action=\"backend.php\">
			<input id=\"opml_file\" name=\"opml_file\" type=\"file\">&nbsp;
			<input type=\"hidden\" name=\"op\" value=\"dlg\">
			<input type=\"hidden\" name=\"id\" value=\"importOpml\">
			<button dojoType=\"dijit.form.Button\" onclick=\"return opmlImport();\"
				type=\"submit\">".__('Import')."</button>
			<button dojoType=\"dijit.form.Button\" onclick=\"gotoExportOpml()\">".__('Export OPML')."</button>
			</form>";

		print "<p>".__('Your OPML can be published publicly and can be subscribed by anyone who knows the URL below.');

		print "<div class=\"insensitive\">" . __("Note: Published OPML does not include your Tiny Tiny RSS settings, feeds that require authentication or feeds hidden from Popular feeds.") . 			"</div>" . "</p>";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return displayDlg('pubOPMLUrl')\">".
			__('Display URL')."</button> ";


		print "</div>"; # pane

		if (strpos($_SERVER['HTTP_USER_AGENT'], "Firefox") !== false) {

			print "<div dojoType=\"dijit.layout.AccordionPane\" title=\"".__('Firefox integration')."\">";

			print "<p>" . __('This Tiny Tiny RSS site can be used as a Firefox Feed Reader by clicking the link below.') . "</p>";

			print "<p>";

			print "<button onclick='window.navigator.registerContentHandler(" .
                      "\"application/vnd.mozilla.maybe.feed\", " .
                      "\"" . add_feed_url() . "\", " . " \"Tiny Tiny RSS\")'>" .
							 __('Click here to register this site as a feed reader.') .
				"</button>";

			print "</p>";

			print "</div>"; # pane
		}

		print "<div dojoType=\"dijit.layout.AccordionPane\" title=\"".__('Subscribing using bookmarklet')."\">";

		print "<p>" . __("Drag the link below to your browser toolbar, open the feed you're interested in in your browser and click on the link to subscribe to it.") . "</p>";

		$bm_subscribe_url = str_replace('%s', '', add_feed_url());

		$confirm_str = __('Subscribe to %s in Tiny Tiny RSS?');

		$bm_url = htmlspecialchars("javascript:{if(confirm('$confirm_str'.replace('%s',window.location.href)))window.location.href='$bm_subscribe_url'+window.location.href}");

		print "<a href=\"$bm_url\" class='bookmarklet'>" . __('Subscribe in Tiny Tiny RSS'). "</a>";

		print "</div>"; #pane

		print "<div dojoType=\"dijit.layout.AccordionPane\" title=\"".__('Published articles and generated feeds')."\">";

		print "<p>".__('Published articles are exported as a public RSS feed and can be subscribed by anyone who knows the URL specified below.')."</p>";

		$rss_url = '-2::' . htmlspecialchars(get_self_url_prefix() .
				"/backend.php?op=rss&id=-2&view-mode=all_articles");;

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return displayDlg('generatedFeed', '$rss_url')\">".
			__('Display URL')."</button> ";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return clearFeedAccessKeys()\">".
			__('Clear all generated URLs')."</button> ";

		print "</div>"; #pane

		if (defined('CONSUMER_KEY') && CONSUMER_KEY != '') {

			print "<div id=\"pref-feeds-twitter\" dojoType=\"dijit.layout.AccordionPane\" title=\"".__('Twitter')."\">";

			$result = db_query($link, "SELECT COUNT(*) AS cid FROM ttrss_users
				WHERE twitter_oauth IS NOT NULL AND twitter_oauth != '' AND
				id = " . $_SESSION['uid']);

			$is_registered = db_fetch_result($result, 0, "cid") != 0;

			if (!$is_registered) {
				print_notice(__('Before you can update your Twitter feeds, you must register this instance of Tiny Tiny RSS with Twitter.com.'));
			} else {
				print_notice(__('You have been successfully registered with Twitter.com and should be able to access your Twitter feeds.'));
			}

			print "<button dojoType=\"dijit.form.Button\" onclick=\"window.location.href = 'twitter.php?op=register'\">".
				__("Register with Twitter.com")."</button>";

			print " ";

			print "<button dojoType=\"dijit.form.Button\"
				onclick=\"return clearTwitterCredentials()\">".
				__("Clear stored credentials")."</button>";

			print "</div>"; # pane

		}

		print "</div>"; #container

	}

	function make_feed_browser($link, $search, $limit, $mode = 1) {

		$owner_uid = $_SESSION["uid"];
		$rv = '';

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
							$feed_icon = "<img style=\"vertical-align : middle\" class=\"tinyFeedIcon\"	src=\"" . ICONS_URL .
								"/".$details["id"].".ico\">";
					} else {
						$feed_icon = "<img class=\"tinyFeedIcon\" src=\"images/blank_icon.gif\">";
					}

					$check_box = "<input onclick='toggleSelectListRow2(this)'
						dojoType=\"dijit.form.CheckBox\"
						type=\"checkbox\" \">";

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

					$rv .= "<li title=\"".htmlspecialchars($details["site_url"])."\"
						id=\"FBROW-".$details["id"]."\">$check_box".
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

					$check_box = "<input onclick='toggleSelectListRow2(this)' dojoType=\"dijit.form.CheckBox\"
						type=\"checkbox\">";

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

					$rv .= "<li title='".$line['site_url']."' class='$class'
						id=\"FBROW-".$line["id"]."\">".
						$check_box . "$feed_icon $feed_url " . $title .
						$archived . $site_url . "</li>";


				}

				++$feedctr;
			}

			if ($feedctr == 0) {
				$rv .= "<li style=\"text-align : center\"><p>".__('No feeds found.')."</p></li>";
			}

		return $rv;

	}

?>

<?php
class Pref_Feeds extends Protected_Handler {

	function csrf_ignore($method) {
		$csrf_ignored = array("index", "getfeedtree", "add", "editcats", "editfeed");

		return array_search($method, $csrf_ignored) !== false;
	}

	function batch_edit_cbox($elem, $label = false) {
		print "<input type=\"checkbox\" title=\"".__("Check to enable field")."\"
			onchange=\"dijit.byId('feedEditDlg').toggleField(this, '$elem', '$label')\">";
	}

	function renamecat() {
		$title = db_escape_string($_REQUEST['title']);
		$id = db_escape_string($_REQUEST['id']);

		if ($title) {
			db_query($this->link, "UPDATE ttrss_feed_categories SET
				title = '$title' WHERE id = '$id' AND owner_uid = " . $_SESSION["uid"]);
		}
		return;
	}

	function remtwitterinfo() {

		db_query($this->link, "UPDATE ttrss_users SET twitter_oauth = NULL
			WHERE id = " . $_SESSION['uid']);

		return;
	}

	function getfeedtree() {

		$search = $_SESSION["prefs_feed_search"];

		if ($search) $search_qpart = " AND LOWER(title) LIKE LOWER('%$search%')";

		$root = array();
		$root['id'] = 'root';
		$root['name'] = __('Feeds');
		$root['items'] = array();
		$root['type'] = 'category';

		if (get_pref($this->link, 'ENABLE_FEED_CATS')) {

			$result = db_query($this->link, "SELECT id, title FROM ttrss_feed_categories
				WHERE owner_uid = " . $_SESSION["uid"] . " ORDER BY order_id, title");

			while ($line = db_fetch_assoc($result)) {
				$cat = array();
				$cat['id'] = 'CAT:' . $line['id'];
				$cat['bare_id'] = $feed_id;
				$cat['name'] = $line['title'];
				$cat['items'] = array();
				$cat['checkbox'] = false;
				$cat['type'] = 'category';

				$feed_result = db_query($this->link, "SELECT id, title, last_error,
					".SUBSTRING_FOR_DATE."(last_updated,1,19) AS last_updated
					FROM ttrss_feeds
					WHERE cat_id = '".$line['id']."' AND owner_uid = ".$_SESSION["uid"].
					"$search_qpart ORDER BY order_id, title");

				while ($feed_line = db_fetch_assoc($feed_result)) {
					$feed = array();
					$feed['id'] = 'FEED:' . $feed_line['id'];
					$feed['bare_id'] = $feed_line['id'];
					$feed['name'] = $feed_line['title'];
					$feed['checkbox'] = false;
					$feed['error'] = $feed_line['last_error'];
					$feed['icon'] = getFeedIcon($feed_line['id']);
					$feed['param'] = make_local_datetime($this->link,
						$feed_line['last_updated'], true);

					array_push($cat['items'], $feed);
				}

				$cat['param'] = T_sprintf('(%d feeds)', count($cat['items']));

				if (count($cat['items']) > 0)
					array_push($root['items'], $cat);

				$root['param'] += count($cat['items']);
			}

			/* Uncategorized is a special case */

			$cat = array();
			$cat['id'] = 'CAT:0';
			$cat['bare_id'] = 0;
			$cat['name'] = __("Uncategorized");
			$cat['items'] = array();
			$cat['type'] = 'category';
			$cat['checkbox'] = false;

			$feed_result = db_query($this->link, "SELECT id, title,last_error,
				".SUBSTRING_FOR_DATE."(last_updated,1,19) AS last_updated
				FROM ttrss_feeds
				WHERE cat_id IS NULL AND owner_uid = ".$_SESSION["uid"].
				"$search_qpart ORDER BY order_id, title");

			while ($feed_line = db_fetch_assoc($feed_result)) {
				$feed = array();
				$feed['id'] = 'FEED:' . $feed_line['id'];
				$feed['bare_id'] = $feed_line['id'];
				$feed['name'] = $feed_line['title'];
				$feed['checkbox'] = false;
				$feed['error'] = $feed_line['last_error'];
				$feed['icon'] = getFeedIcon($feed_line['id']);
				$feed['param'] = make_local_datetime($this->link,
					$feed_line['last_updated'], true);

				array_push($cat['items'], $feed);
			}

			$cat['param'] = T_sprintf('(%d feeds)', count($cat['items']));

			if (count($cat['items']) > 0)
				array_push($root['items'], $cat);

			$root['param'] += count($cat['items']);
			$root['param'] = T_sprintf('(%d feeds)', $root['param']);

		} else {
			$feed_result = db_query($this->link, "SELECT id, title, last_error,
				".SUBSTRING_FOR_DATE."(last_updated,1,19) AS last_updated
				FROM ttrss_feeds
				WHERE owner_uid = ".$_SESSION["uid"].
				"$search_qpart ORDER BY order_id, title");

			while ($feed_line = db_fetch_assoc($feed_result)) {
				$feed = array();
				$feed['id'] = 'FEED:' . $feed_line['id'];
				$feed['bare_id'] = $feed_line['id'];
				$feed['name'] = $feed_line['title'];
				$feed['checkbox'] = false;
				$feed['error'] = $feed_line['last_error'];
				$feed['icon'] = getFeedIcon($feed_line['id']);
				$feed['param'] = make_local_datetime($this->link,
					$feed_line['last_updated'], true);

				array_push($root['items'], $feed);
			}

			$root['param'] = T_sprintf('(%d feeds)', count($root['items']));

		}

		$fl = array();
		$fl['identifier'] = 'id';
		$fl['label'] = 'name';
		$fl['items'] = array($root);

		print json_encode($fl);
		return;
	}

	function catsortreset() {
		db_query($this->link, "UPDATE ttrss_feed_categories
				SET order_id = 0 WHERE owner_uid = " . $_SESSION["uid"]);
		return;
	}

	function feedsortreset() {
		db_query($this->link, "UPDATE ttrss_feeds
				SET order_id = 0 WHERE owner_uid = " . $_SESSION["uid"]);
		return;
	}

	function savefeedorder() {
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
					db_query($this->link, "UPDATE ttrss_feed_categories
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

						db_query($this->link, "UPDATE ttrss_feeds
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

	function removeicon() {
		$feed_id = db_escape_string($_REQUEST["feed_id"]);

		$result = db_query($this->link, "SELECT id FROM ttrss_feeds
			WHERE id = '$feed_id' AND owner_uid = ". $_SESSION["uid"]);

		if (db_num_rows($result) != 0) {
			unlink(ICONS_DIR . "/$feed_id.ico");
		}

		return;
	}

	function uploadicon() {
		$icon_file = $_FILES['icon_file']['tmp_name'];
		$feed_id = db_escape_string($_REQUEST["feed_id"]);

		if (is_file($icon_file) && $feed_id) {
			if (filesize($icon_file) < 20000) {

				$result = db_query($this->link, "SELECT id FROM ttrss_feeds
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

	function editfeed() {
		global $purge_intervals;
		global $update_intervals;
		global $update_methods;

		$feed_id = db_escape_string($_REQUEST["id"]);

		$result = db_query($this->link,
			"SELECT * FROM ttrss_feeds WHERE id = '$feed_id' AND
				owner_uid = " . $_SESSION["uid"]);

		$title = htmlspecialchars(db_fetch_result($result,
			0, "title"));

		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"id\" value=\"$feed_id\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"pref-feeds\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"method\" value=\"editSave\">";

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

		$last_error = db_fetch_result($result, 0, "last_error");

		if ($last_error) {
			print "&nbsp;<span title=\"".htmlspecialchars($last_error)."\"
				class=\"feed_error\">(error)</span>";

		}

		/* Category */

		if (get_pref($this->link, 'ENABLE_FEED_CATS')) {

			$cat_id = db_fetch_result($result, 0, "cat_id");

			print "<hr/>";

			print __('Place in category:') . " ";

			print_feed_cat_select($this->link, "cat_id", $cat_id,
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


			/* Purge intl */

		print "<hr/>";
		print __('Article purging:') . " ";

		print_select_hash("purge_interval", $purge_interval, $purge_intervals,
			'dojoType="dijit.form.Select" ' .
				((FORCE_ARTICLE_PURGE == 0) ? "" : 'disabled="1"'));

		print "</div>";
		print "<div class=\"dlgSec\">".__("Authentication")."</div>";
		print "<div class=\"dlgSecCont\">";

		$auth_login = htmlspecialchars(db_fetch_result($result, 0, "auth_login"));

		print "<input dojoType=\"dijit.form.TextBox\" id=\"feedEditDlg_login\"
			placeHolder=\"".__("Login")."\"
			name=\"auth_login\" value=\"$auth_login\"><hr/>";

		$auth_pass = htmlspecialchars(db_fetch_result($result, 0, "auth_pass"));

		print "<input dojoType=\"dijit.form.TextBox\" type=\"password\" name=\"auth_pass\"
			placeHolder=\"".__("Password")."\"
			value=\"$auth_pass\">";

		print "<div dojoType=\"dijit.Tooltip\" connectId=\"feedEditDlg_login\" position=\"below\">
			".__('<b>Hint:</b> you need to fill in your login information if your feed requires authentication, except for Twitter feeds.')."
			</div>";

		print "</div>";
		print "<div class=\"dlgSec\">".__("Options")."</div>";
		print "<div class=\"dlgSecCont\">";

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

		print "<hr/><input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" id=\"cache_images\"
		name=\"cache_images\"
			$checked>&nbsp;<label for=\"cache_images\">".
		__('Cache images locally')."</label>";

		$mark_unread_on_update = sql_bool_to_bool(db_fetch_result($result, 0, "mark_unread_on_update"));

		if ($mark_unread_on_update) {
			$checked = "checked";
		} else {
			$checked = "";
		}

		print "<hr/><input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" id=\"mark_unread_on_update\"
			name=\"mark_unread_on_update\"
			$checked>&nbsp;<label for=\"mark_unread_on_update\">".__('Mark updated articles as unread')."</label>";

		$update_on_checksum_change = sql_bool_to_bool(db_fetch_result($result, 0, "update_on_checksum_change"));

		if ($update_on_checksum_change) {
			$checked = "checked";
		} else {
			$checked = "";
		}

		print "<hr/><input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" id=\"update_on_checksum_change\"
			name=\"update_on_checksum_change\"
			$checked>&nbsp;<label for=\"update_on_checksum_change\">".__('Mark posts as updated on content change')."</label>";

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
			<input type=\"hidden\" name=\"method\" value=\"uploadicon\">
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
				__('Unsubscribe')."</button>";

		if (PUBSUBHUBBUB_ENABLED) {
			$pubsub_state = db_fetch_result($result, 0, "pubsub_state");
			$pubsub_btn_disabled = ($pubsub_state == 2) ? "" : "disabled=\"1\"";

			print "<button dojoType=\"dijit.form.Button\" id=\"pubsubReset_Btn\" $pubsub_btn_disabled
					onclick='return resetPubSub($feed_id, \"$title\")'>".__('Resubscribe to push updates').
					"</button>";
		}

		print "</div>";

		print "<div dojoType=\"dijit.Tooltip\" connectId=\"pubsubReset_Btn\" position=\"below\">".
			__('Resets PubSubHubbub subscription status for push-enabled feeds.')."</div>";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('feedEditDlg').execute()\">".__('Save')."</button>
			<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('feedEditDlg').hide()\">".__('Cancel')."</button>
		</div>";

		return;
	}

	function editfeeds() {
		global $purge_intervals;
		global $update_intervals;
		global $update_methods;

		$feed_ids = db_escape_string($_REQUEST["ids"]);

		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"ids\" value=\"$feed_ids\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"pref-feeds\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"method\" value=\"batchEditSave\">";

		print "<div class=\"dlgSec\">".__("Feed")."</div>";
		print "<div class=\"dlgSecCont\">";

		/* Title */

		print "<input dojoType=\"dijit.form.ValidationTextBox\"
			disabled=\"1\" style=\"font-size : 16px; width : 20em;\" required=\"1\"
			name=\"title\" value=\"$title\">";

		$this->batch_edit_cbox("title");

		/* Feed URL */

		print "<br/>";

		print __('URL:') . " ";
		print "<input dojoType=\"dijit.form.ValidationTextBox\" disabled=\"1\"
			required=\"1\" regExp='^(http|https)://.*' style=\"width : 20em\"
			name=\"feed_url\" value=\"$feed_url\">";

		$this->batch_edit_cbox("feed_url");

		/* Category */

		if (get_pref($this->link, 'ENABLE_FEED_CATS')) {

			print "<br/>";

			print __('Place in category:') . " ";

			print_feed_cat_select($this->link, "cat_id", $cat_id,
				'disabled="1" dojoType="dijit.form.Select"');

			$this->batch_edit_cbox("cat_id");

		}

		print "</div>";

		print "<div class=\"dlgSec\">".__("Update")."</div>";
		print "<div class=\"dlgSecCont\">";

		/* Update Interval */

		print_select_hash("update_interval", $update_interval, $update_intervals,
			'disabled="1" dojoType="dijit.form.Select"');

		$this->batch_edit_cbox("update_interval");

		/* Update method */

		print " " . __('using') . " ";
		print_select_hash("update_method", $update_method, $update_methods,
			'disabled="1" dojoType="dijit.form.Select"');
		$this->batch_edit_cbox("update_method");

		/* Purge intl */

		if (FORCE_ARTICLE_PURGE == 0) {

			print "<br/>";

			print __('Article purging:') . " ";

			print_select_hash("purge_interval", $purge_interval, $purge_intervals,
				'disabled="1" dojoType="dijit.form.Select"');

			$this->batch_edit_cbox("purge_interval");
		}

		print "</div>";
		print "<div class=\"dlgSec\">".__("Authentication")."</div>";
		print "<div class=\"dlgSecCont\">";

		print "<input dojoType=\"dijit.form.TextBox\"
			placeHolder=\"".__("Login")."\" disabled=\"1\"
			name=\"auth_login\" value=\"$auth_login\">";

		$this->batch_edit_cbox("auth_login");

		print "<br/><input dojoType=\"dijit.form.TextBox\" type=\"password\" name=\"auth_pass\"
			placeHolder=\"".__("Password")."\" disabled=\"1\"
			value=\"$auth_pass\">";

		$this->batch_edit_cbox("auth_pass");

		print "</div>";
		print "<div class=\"dlgSec\">".__("Options")."</div>";
		print "<div class=\"dlgSecCont\">";

		print "<input disabled=\"1\" type=\"checkbox\" name=\"private\" id=\"private\"
			dojoType=\"dijit.form.CheckBox\">&nbsp;<label id=\"private_l\" class='insensitive' for=\"private\">".__('Hide from Popular feeds')."</label>";

		print "&nbsp;"; $this->batch_edit_cbox("private", "private_l");

		print "<br/><input disabled=\"1\" type=\"checkbox\" id=\"rtl_content\" name=\"rtl_content\"
			dojoType=\"dijit.form.CheckBox\">&nbsp;<label class='insensitive' id=\"rtl_content_l\" for=\"rtl_content\">".__('Right-to-left content')."</label>";

		print "&nbsp;"; $this->batch_edit_cbox("rtl_content", "rtl_content_l");

		print "<br/><input disabled=\"1\" type=\"checkbox\" id=\"include_in_digest\"
			name=\"include_in_digest\"
			dojoType=\"dijit.form.CheckBox\">&nbsp;<label id=\"include_in_digest_l\" class='insensitive' for=\"include_in_digest\">".__('Include in e-mail digest')."</label>";

		print "&nbsp;"; $this->batch_edit_cbox("include_in_digest", "include_in_digest_l");

		print "<br/><input disabled=\"1\" type=\"checkbox\" id=\"always_display_enclosures\"
			name=\"always_display_enclosures\"
			dojoType=\"dijit.form.CheckBox\">&nbsp;<label id=\"always_display_enclosures_l\" class='insensitive' for=\"always_display_enclosures\">".__('Always display image attachments')."</label>";

		print "&nbsp;"; $this->batch_edit_cbox("always_display_enclosures", "always_display_enclosures_l");

		print "<br/><input disabled=\"1\" type=\"checkbox\" id=\"cache_images\"
			name=\"cache_images\"
			dojoType=\"dijit.form.CheckBox\">&nbsp;<label class='insensitive' id=\"cache_images_l\"
			for=\"cache_images\">".
		__('Cache images locally')."</label>";

		print "&nbsp;"; $this->batch_edit_cbox("cache_images", "cache_images_l");

		print "<br/><input disabled=\"1\" type=\"checkbox\" id=\"mark_unread_on_update\"
			name=\"mark_unread_on_update\"
			dojoType=\"dijit.form.CheckBox\">&nbsp;<label id=\"mark_unread_on_update_l\" class='insensitive' for=\"mark_unread_on_update\">".__('Mark updated articles as unread')."</label>";

		print "&nbsp;"; $this->batch_edit_cbox("mark_unread_on_update", "mark_unread_on_update_l");

		print "<br/><input disabled=\"1\" type=\"checkbox\" id=\"update_on_checksum_change\"
			name=\"update_on_checksum_change\"
			dojoType=\"dijit.form.CheckBox\">&nbsp;<label id=\"update_on_checksum_change_l\" class='insensitive' for=\"update_on_checksum_change\">".__('Mark posts as updated on content change')."</label>";

		print "&nbsp;"; $this->batch_edit_cbox("update_on_checksum_change", "update_on_checksum_change_l");

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

	function batchEditSave() {
		return $this->editsaveops(true);
	}

	function editSave() {
		return $this->editsaveops(false);
	}

	function editsaveops($batch) {

		$feed_title = db_escape_string(trim($_POST["title"]));
		$feed_link = db_escape_string(trim($_POST["feed_url"]));
		$upd_intl = (int) db_escape_string($_POST["update_interval"]);
		$purge_intl = (int) db_escape_string($_POST["purge_interval"]);
		$feed_id = (int) db_escape_string($_POST["id"]); /* editSave */
		$feed_ids = db_escape_string($_POST["ids"]); /* batchEditSave */
		$cat_id = (int) db_escape_string($_POST["cat_id"]);
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

		$mark_unread_on_update = checkbox_to_sql_bool(
			db_escape_string($_POST["mark_unread_on_update"]));

		$update_on_checksum_change = checkbox_to_sql_bool(
			db_escape_string($_POST["update_on_checksum_change"]));

		if (get_pref($this->link, 'ENABLE_FEED_CATS')) {
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

		$cache_images_qpart = "cache_images = $cache_images,";

		if (!$batch) {

			$result = db_query($this->link, "UPDATE ttrss_feeds SET
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
				mark_unread_on_update = $mark_unread_on_update,
				update_on_checksum_change = $update_on_checksum_change,
				update_method = '$update_method'
				WHERE id = '$feed_id' AND owner_uid = " . $_SESSION["uid"]);

		} else {
			$feed_data = array();

			foreach (array_keys($_POST) as $k) {
				if ($k != "op" && $k != "method" && $k != "ids") {
					$feed_data[$k] = $_POST[$k];
				}
			}

			db_query($this->link, "BEGIN");

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

					case "mark_unread_on_update":
						$qpart = "mark_unread_on_update = '$mark_unread_on_update'";
						break;

					case "update_on_checksum_change":
						$qpart = "update_on_checksum_change = '$update_on_checksum_change'";
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
					db_query($this->link,
						"UPDATE ttrss_feeds SET $qpart WHERE id IN ($feed_ids)
						AND owner_uid = " . $_SESSION["uid"]);
					print "<br/>";
				}
			}

			db_query($this->link, "COMMIT");
		}
		return;
	}

	function resetPubSub() {

		$ids = db_escape_string($_REQUEST["ids"]);

		db_query($this->link, "UPDATE ttrss_feeds SET pubsub_state = 0 WHERE id IN ($ids)
			AND owner_uid = " . $_SESSION["uid"]);

		return;
	}

	function remove() {

		$ids = split(",", db_escape_string($_REQUEST["ids"]));

		foreach ($ids as $id) {
			remove_feed($this->link, $id, $_SESSION["uid"]);
		}

		return;
	}

	function clear() {
		$id = db_escape_string($_REQUEST["id"]);
		clear_feed_articles($this->link, $id);
	}

	function rescore() {
		$ids = split(",", db_escape_string($_REQUEST["ids"]));

		foreach ($ids as $id) {

			$filters = load_filters($this->link, $id, $_SESSION["uid"], 6);

			$result = db_query($this->link, "SELECT
				title, content, link, ref_id, author,".
				SUBSTRING_FOR_DATE."(updated, 1, 19) AS updated
			  	FROM
					ttrss_user_entries, ttrss_entries
					WHERE ref_id = id AND feed_id = '$id' AND
						owner_uid = " .$_SESSION['uid']."
					");

			$scores = array();

			while ($line = db_fetch_assoc($result)) {

				$tags = get_article_tags($this->link, $line["ref_id"]);

				$article_filters = get_article_filters($filters, $line['title'],
					$line['content'], $line['link'], strtotime($line['updated']),
					$line['author'], $tags);

				$new_score = calculate_article_score($article_filters);

				if (!$scores[$new_score]) $scores[$new_score] = array();

				array_push($scores[$new_score], $line['ref_id']);
			}

			foreach (array_keys($scores) as $s) {
				if ($s > 1000) {
					db_query($this->link, "UPDATE ttrss_user_entries SET score = '$s',
						marked = true WHERE
						ref_id IN (" . join(',', $scores[$s]) . ")");
				} else if ($s < -500) {
					db_query($this->link, "UPDATE ttrss_user_entries SET score = '$s',
						unread = false WHERE
						ref_id IN (" . join(',', $scores[$s]) . ")");
				} else {
					db_query($this->link, "UPDATE ttrss_user_entries SET score = '$s' WHERE
						ref_id IN (" . join(',', $scores[$s]) . ")");
				}
			}
		}

		print __("All done.");

	}

	function rescoreAll() {

		$result = db_query($this->link,
			"SELECT id FROM ttrss_feeds WHERE owner_uid = " . $_SESSION['uid']);

		while ($feed_line = db_fetch_assoc($result)) {

			$id = $feed_line["id"];

			$filters = load_filters($this->link, $id, $_SESSION["uid"], 6);

			$tmp_result = db_query($this->link, "SELECT
				title, content, link, ref_id, author,".
				  	SUBSTRING_FOR_DATE."(updated, 1, 19) AS updated
					FROM
					ttrss_user_entries, ttrss_entries
					WHERE ref_id = id AND feed_id = '$id' AND
						owner_uid = " .$_SESSION['uid']."
					");

			$scores = array();

			while ($line = db_fetch_assoc($tmp_result)) {

				$tags = get_article_tags($this->link, $line["ref_id"]);

				$article_filters = get_article_filters($filters, $line['title'],
					$line['content'], $line['link'], strtotime($line['updated']),
					$line['author'], $tags);

				$new_score = calculate_article_score($article_filters);

				if (!$scores[$new_score]) $scores[$new_score] = array();

				array_push($scores[$new_score], $line['ref_id']);
			}

			foreach (array_keys($scores) as $s) {
				if ($s > 1000) {
					db_query($this->link, "UPDATE ttrss_user_entries SET score = '$s',
						marked = true WHERE
						ref_id IN (" . join(',', $scores[$s]) . ")");
				} else {
					db_query($this->link, "UPDATE ttrss_user_entries SET score = '$s' WHERE
						ref_id IN (" . join(',', $scores[$s]) . ")");
				}
			}
		}

		print __("All done.");

	}

	function add() {
		$feed_url = db_escape_string(trim($_REQUEST["feed_url"]));
		$cat_id = db_escape_string($_REQUEST["cat_id"]);
		$p_from = db_escape_string($_REQUEST["from"]);

		/* only read authentication information from POST */

		$auth_login = db_escape_string(trim($_POST["auth_login"]));
		$auth_pass = db_escape_string(trim($_POST["auth_pass"]));

		if ($p_from != 'tt-rss') {
			header("Content-Type: text/html");
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

		$rc = subscribe_to_feed($this->link, $feed_url, $cat_id, $auth_login, $auth_pass);

		switch ($rc) {
		case 1:
			print_notice(T_sprintf("Subscribed to <b>%s</b>.", $feed_url));
			break;
		case 2:
			print_error(T_sprintf("Could not subscribe to <b>%s</b>.", $feed_url));
			break;
		case 3:
			print_error(T_sprintf("No feeds found in <b>%s</b>.", $feed_url));
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
				print "<input type=\"hidden\" name=\"method\" value=\"add\">";

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
				$result = db_query($this->link, "SELECT id FROM ttrss_feeds WHERE
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
					<input type=\"hidden\" name=\"method\" value=\"editFeed\">
					<input type=\"hidden\" name=\"methodparam\" value=\"$feed_id\">
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

	function categorize() {
		$ids = split(",", db_escape_string($_REQUEST["ids"]));

		$cat_id = db_escape_string($_REQUEST["cat_id"]);

		if ($cat_id == 0) {
			$cat_id_qpart = 'NULL';
		} else {
			$cat_id_qpart = "'$cat_id'";
		}

		db_query($this->link, "BEGIN");

		foreach ($ids as $id) {

			db_query($this->link, "UPDATE ttrss_feeds SET cat_id = $cat_id_qpart
				WHERE id = '$id'
			  	AND owner_uid = " . $_SESSION["uid"]);

		}

		db_query($this->link, "COMMIT");
	}

	function editCats() {

		$action = $_REQUEST["action"];

		if ($action == "save") {

			$cat_title = db_escape_string(trim($_REQUEST["value"]));
			$cat_id = db_escape_string($_REQUEST["cid"]);

			db_query($this->link, "BEGIN");

			$result = db_query($this->link, "SELECT title FROM ttrss_feed_categories
				WHERE id = '$cat_id' AND owner_uid = ".$_SESSION["uid"]);

			if (db_num_rows($result) == 1) {

				$old_title = db_fetch_result($result, 0, "title");

				if ($cat_title != "") {
					$result = db_query($this->link, "UPDATE ttrss_feed_categories SET
						title = '$cat_title' WHERE id = '$cat_id' AND
						owner_uid = ".$_SESSION["uid"]);

					print $cat_title;
				} else {
					print $old_title;
				}
			} else {
				print $_REQUEST["value"];
			}

			db_query($this->link, "COMMIT");

			return;

		}

		if ($action == "add") {

			$feed_cat = db_escape_string(trim($_REQUEST["cat"]));

			if (!add_feed_category($this->link, $feed_cat))
				print_warning(T_sprintf("Category <b>$%s</b> already exists in the database.", $feed_cat));

		}

		if ($action == "remove") {

			$ids = split(",", db_escape_string($_REQUEST["ids"]));

			foreach ($ids as $id) {
				remove_feed_category($this->link, $id, $_SESSION["uid"]);
			}
		}

		print "<div dojoType=\"dijit.Toolbar\">
			<input dojoType=\"dijit.form.ValidationTextBox\" required=\"1\" name=\"newcat\">
				<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('feedCatEditDlg').addCategory()\">".
					__('Create category')."</button></div>";

		$result = db_query($this->link, "SELECT title,id FROM ttrss_feed_categories
			WHERE owner_uid = ".$_SESSION["uid"]."
			ORDER BY title");

		if (db_num_rows($result) != 0) {

			print "<div class=\"prefFeedCatHolder\">";

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

				print "<span dojoType=\"dijit.InlineEditBox\"
					width=\"300px\" autoSave=\"false\"
					cat-id=\"$cat_id\">" . $edit_title .
					"<script type=\"dojo/method\" event=\"onChange\" args=\"item\">
						var elem = this;
						dojo.xhrPost({
							url: 'backend.php',
							content: {op: 'pref-feeds', method: 'editCats',
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

	function index() {

		print "<div dojoType=\"dijit.layout.AccordionContainer\" region=\"center\">";
		print "<div id=\"pref-feeds-feeds\" dojoType=\"dijit.layout.AccordionPane\" title=\"".__('Feeds')."\">";

		$result = db_query($this->link, "SELECT COUNT(id) AS num_errors
			FROM ttrss_feeds WHERE last_error != '' AND owner_uid = ".$_SESSION["uid"]);

		$num_errors = db_fetch_result($result, 0, "num_errors");

		if ($num_errors > 0) {

			$error_button = "<button dojoType=\"dijit.form.Button\"
			  		onclick=\"showFeedsWithErrors()\" id=\"errorButton\">" .
				__("Feeds with errors") . "</button>";
		}

		if (DB_TYPE == "pgsql") {
			$interval_qpart = "NOW() - INTERVAL '3 months'";
		} else {
			$interval_qpart = "DATE_SUB(NOW(), INTERVAL 3 MONTH)";
		}

		$result = db_query($this->link, "SELECT COUNT(*) AS num_inactive FROM ttrss_feeds WHERE
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

		print '<div dojoType="dijit.layout.BorderContainer" gutters="false">';

		print "<div region='top' dojoType=\"dijit.Toolbar\">"; #toolbar

		print "<div style='float : right; padding-right : 4px;'>
			<input dojoType=\"dijit.form.TextBox\" id=\"feed_search\" size=\"20\" type=\"search\"
				value=\"$feed_search\">
			<button dojoType=\"dijit.form.Button\" onclick=\"updateFeedList()\">".
				__('Search')."</button>
			</div>";

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

		if (get_pref($this->link, 'ENABLE_FEED_CATS')) {
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

		//print '</div>';
		print '<div dojoType="dijit.layout.ContentPane" region="center">';

		print "<div id=\"feedlistLoading\">
		<img src='images/indicator_tiny.gif'>".
		 __("Loading, please wait...")."</div>";

		print "<div dojoType=\"fox.PrefFeedStore\" jsId=\"feedStore\"
			url=\"backend.php?op=pref-feeds&method=getfeedtree\">
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

		print '</div>';
		print '</div>';

		print "</div>"; # feeds pane

		print "<div dojoType=\"dijit.layout.AccordionPane\" title=\"".__('OPML')."\">";

		print "<p>" . __("Using OPML you can export and import your feeds, filters, labels and Tiny Tiny RSS settings.") . " ";

		print "<span class=\"insensitive\">" . __("Note: Only main settings profile can be migrated using OPML.") . "</span>";

		print "</p>";

		print "<h3>" . __("Import") . "</h3>";

		print "<br/><iframe id=\"upload_iframe\"
			name=\"upload_iframe\" onload=\"opmlImportComplete(this)\"
			style=\"width: 400px; height: 100px; display: none;\"></iframe>";

		print "<form  name=\"opml_form\" style='display : block' target=\"upload_iframe\"
			enctype=\"multipart/form-data\" method=\"POST\"
				action=\"backend.php\">
			<input id=\"opml_file\" name=\"opml_file\" type=\"file\">&nbsp;
			<input type=\"hidden\" name=\"op\" value=\"dlg\">
			<input type=\"hidden\" name=\"method\" value=\"importOpml\">
			<button dojoType=\"dijit.form.Button\" onclick=\"return opmlImport();\" type=\"submit\">" .
			__('Import') . "</button>";

		print "<h3>" . __("Export") . "</h3>";

		print "<p>" . __('Filename:') .
            " <input type=\"text\" id=\"filename\" value=\"TinyTinyRSS.opml\" />&nbsp;" .
            __('Include settings') . "<input type=\"checkbox\" id=\"settings\" CHECKED />" .

			"<button dojoType=\"dijit.form.Button\"
			onclick=\"gotoExportOpml(document.opml_form.filename.value, document.opml_form.settings.checked)\" >" .
              __('Export') . "</button></p></form>";

		print "<h3>" . __("Publish") . "</h3>";

		print "<p>".__('Your OPML can be published publicly and can be subscribed by anyone who knows the URL below.') . " ";

		print "<span class=\"insensitive\">" . __("Note: Published OPML does not include your Tiny Tiny RSS settings, feeds that require authentication or feeds hidden from Popular feeds.") . 			"</span>" . "</p>";

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

		print "<div dojoType=\"dijit.layout.AccordionPane\" title=\"".__('Published & shared articles and generated feeds')."\">";

		print "<h3>" . __("Published articles and generated feeds") . "</h3>";

		print "<p>".__('Published articles are exported as a public RSS feed and can be subscribed by anyone who knows the URL specified below.')."</p>";

		$rss_url = '-2::' . htmlspecialchars(get_self_url_prefix() .
				"/public.php?op=rss&id=-2&view-mode=all_articles");;

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return displayDlg('generatedFeed', '$rss_url')\">".
			__('Display URL')."</button> ";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return clearFeedAccessKeys()\">".
			__('Clear all generated URLs')."</button> ";

		print "<h3>" . __("Articles shared by URL") . "</h3>";

		print "<p>" . __("You can disable all articles shared by unique URLs here.") . "</p>";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return clearArticleAccessKeys()\">".
			__('Unshare all articles')."</button> ";

		print "</div>"; #pane

		if (defined('CONSUMER_KEY') && CONSUMER_KEY != '') {

			print "<div id=\"pref-feeds-twitter\" dojoType=\"dijit.layout.AccordionPane\" title=\"".__('Twitter')."\">";

			$result = db_query($this->link, "SELECT COUNT(*) AS cid FROM ttrss_users
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
}
?>

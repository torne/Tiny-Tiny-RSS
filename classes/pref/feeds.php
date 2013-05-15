<?php
class Pref_Feeds extends Handler_Protected {

	function csrf_ignore($method) {
		$csrf_ignored = array("index", "getfeedtree", "add", "editcats", "editfeed",
			"savefeedorder", "uploadicon", "feedswitherrors", "inactivefeeds",
			"batchsubscribe");

		return array_search($method, $csrf_ignored) !== false;
	}

	function batch_edit_cbox($elem, $label = false) {
		print "<input type=\"checkbox\" title=\"".__("Check to enable field")."\"
			onchange=\"dijit.byId('feedEditDlg').toggleField(this, '$elem', '$label')\">";
	}

	function renamecat() {
		$title = $this->dbh->escape_string($_REQUEST['title']);
		$id = $this->dbh->escape_string($_REQUEST['id']);

		if ($title) {
			$this->dbh->query("UPDATE ttrss_feed_categories SET
				title = '$title' WHERE id = '$id' AND owner_uid = " . $_SESSION["uid"]);
		}
		return;
	}

	private function get_category_items($cat_id) {

		if ($_REQUEST['mode'] != 2)
			$search = $_SESSION["prefs_feed_search"];
		else
			$search = "";

		if ($search) $search_qpart = " AND LOWER(title) LIKE LOWER('%$search%')";

		// first one is set by API
		$show_empty_cats = $_REQUEST['force_show_empty'] ||
			($_REQUEST['mode'] != 2 && !$search);

		$items = array();

		$result = $this->dbh->query("SELECT id, title FROM ttrss_feed_categories
				WHERE owner_uid = " . $_SESSION["uid"] . " AND parent_cat = '$cat_id' ORDER BY order_id, title");

		while ($line = $this->dbh->fetch_assoc($result)) {

			$cat = array();
			$cat['id'] = 'CAT:' . $line['id'];
			$cat['bare_id'] = (int)$line['id'];
			$cat['name'] = $line['title'];
			$cat['items'] = array();
			$cat['checkbox'] = false;
			$cat['type'] = 'category';
			$cat['unread'] = 0;
			$cat['child_unread'] = 0;
			$cat['auxcounter'] = 0;

			$cat['items'] = $this->get_category_items($line['id']);

			$cat['param'] = vsprintf(_ngettext('(%d feed)', '(%d feeds)', count($cat['items'])), count($cat['items']));

			if (count($cat['items']) > 0 || $show_empty_cats)
				array_push($items, $cat);

		}

		$feed_result = $this->dbh->query("SELECT id, title, last_error,
			".SUBSTRING_FOR_DATE."(last_updated,1,19) AS last_updated
			FROM ttrss_feeds
			WHERE cat_id = '$cat_id' AND owner_uid = ".$_SESSION["uid"].
			"$search_qpart ORDER BY order_id, title");

		while ($feed_line = $this->dbh->fetch_assoc($feed_result)) {
			$feed = array();
			$feed['id'] = 'FEED:' . $feed_line['id'];
			$feed['bare_id'] = (int)$feed_line['id'];
			$feed['auxcounter'] = 0;
			$feed['name'] = $feed_line['title'];
			$feed['checkbox'] = false;
			$feed['unread'] = 0;
			$feed['error'] = $feed_line['last_error'];
			$feed['icon'] = getFeedIcon($feed_line['id']);
			$feed['param'] = make_local_datetime(
				$feed_line['last_updated'], true);

			array_push($items, $feed);
		}

		return $items;
	}

	function getfeedtree() {
		print json_encode($this->makefeedtree());
	}

	function makefeedtree() {

		if ($_REQUEST['mode'] != 2)
			$search = $_SESSION["prefs_feed_search"];
		else
			$search = "";

		if ($search) $search_qpart = " AND LOWER(title) LIKE LOWER('%$search%')";

		$root = array();
		$root['id'] = 'root';
		$root['name'] = __('Feeds');
		$root['items'] = array();
		$root['type'] = 'category';

		$enable_cats = get_pref('ENABLE_FEED_CATS');

		if ($_REQUEST['mode'] == 2) {

			if ($enable_cats) {
				$cat = $this->feedlist_init_cat(-1);
			} else {
				$cat['items'] = array();
			}

			foreach (array(-4, -3, -1, -2, 0, -6) as $i) {
				array_push($cat['items'], $this->feedlist_init_feed($i));
			}

			/* Plugin feeds for -1 */

			$feeds = PluginHost::getInstance()->get_feeds(-1);

			if ($feeds) {
				foreach ($feeds as $feed) {
					$feed_id = PluginHost::pfeed_to_feed_id($feed['id']);

					$item = array();
					$item['id'] = 'FEED:' . $feed_id;
					$item['bare_id'] = (int)$feed_id;
					$item['auxcounter'] = 0;
					$item['name'] = $feed['title'];
					$item['checkbox'] = false;
					$item['error'] = '';
					$item['icon'] = $feed['icon'];

					$item['param'] = '';
					$item['unread'] = 0; //$feed['sender']->get_unread($feed['id']);
					$item['type'] = 'feed';

					array_push($cat['items'], $item);
				}
			}

			if ($enable_cats) {
				array_push($root['items'], $cat);
			} else {
				$root['items'] = array_merge($root['items'], $cat['items']);
			}

			$result = $this->dbh->query("SELECT * FROM
				ttrss_labels2 WHERE owner_uid = ".$_SESSION['uid']." ORDER by caption");

			if ($this->dbh->num_rows($result) > 0) {

				if (get_pref('ENABLE_FEED_CATS')) {
					$cat = $this->feedlist_init_cat(-2);
				} else {
					$cat['items'] = array();
				}

				while ($line = $this->dbh->fetch_assoc($result)) {

					$label_id = label_to_feed_id($line['id']);

					$feed = $this->feedlist_init_feed($label_id, false, 0);

					$feed['fg_color'] = $line['fg_color'];
					$feed['bg_color'] = $line['bg_color'];

					array_push($cat['items'], $feed);
				}

				if ($enable_cats) {
					array_push($root['items'], $cat);
				} else {
					$root['items'] = array_merge($root['items'], $cat['items']);
				}
			}
		}

		if ($enable_cats) {
			$show_empty_cats = $_REQUEST['force_show_empty'] ||
				($_REQUEST['mode'] != 2 && !$search);

			$result = $this->dbh->query("SELECT id, title FROM ttrss_feed_categories
				WHERE owner_uid = " . $_SESSION["uid"] . " AND parent_cat IS NULL ORDER BY order_id, title");

			while ($line = $this->dbh->fetch_assoc($result)) {
				$cat = array();
				$cat['id'] = 'CAT:' . $line['id'];
				$cat['bare_id'] = (int)$line['id'];
				$cat['auxcounter'] = 0;
				$cat['name'] = $line['title'];
				$cat['items'] = array();
				$cat['checkbox'] = false;
				$cat['type'] = 'category';
				$cat['unread'] = 0;
				$cat['child_unread'] = 0;

				$cat['items'] = $this->get_category_items($line['id']);

				$cat['param'] = vsprintf(_ngettext('(%d feed)', '(%d feeds)', count($cat['items'])), count($cat['items']));

				if (count($cat['items']) > 0 || $show_empty_cats)
					array_push($root['items'], $cat);

				$root['param'] += count($cat['items']);
			}

			/* Uncategorized is a special case */

			$cat = array();
			$cat['id'] = 'CAT:0';
			$cat['bare_id'] = 0;
			$cat['auxcounter'] = 0;
			$cat['name'] = __("Uncategorized");
			$cat['items'] = array();
			$cat['type'] = 'category';
			$cat['checkbox'] = false;
			$cat['unread'] = 0;
			$cat['child_unread'] = 0;

			$feed_result = $this->dbh->query("SELECT id, title,last_error,
				".SUBSTRING_FOR_DATE."(last_updated,1,19) AS last_updated
				FROM ttrss_feeds
				WHERE cat_id IS NULL AND owner_uid = ".$_SESSION["uid"].
				"$search_qpart ORDER BY order_id, title");

			while ($feed_line = $this->dbh->fetch_assoc($feed_result)) {
				$feed = array();
				$feed['id'] = 'FEED:' . $feed_line['id'];
				$feed['bare_id'] = (int)$feed_line['id'];
				$feed['auxcounter'] = 0;
				$feed['name'] = $feed_line['title'];
				$feed['checkbox'] = false;
				$feed['error'] = $feed_line['last_error'];
				$feed['icon'] = getFeedIcon($feed_line['id']);
				$feed['param'] = make_local_datetime(
					$feed_line['last_updated'], true);
				$feed['unread'] = 0;
				$feed['type'] = 'feed';

				array_push($cat['items'], $feed);
			}

			$cat['param'] = vsprintf(_ngettext('(%d feed)', '(%d feeds)', count($cat['items'])), count($cat['items']));

			if (count($cat['items']) > 0 || $show_empty_cats)
				array_push($root['items'], $cat);

			$root['param'] += count($cat['items']);
			$root['param'] = vsprintf(_ngettext('(%d feed)', '(%d feeds)', count($cat['items'])), count($cat['items']));

		} else {
			$feed_result = $this->dbh->query("SELECT id, title, last_error,
				".SUBSTRING_FOR_DATE."(last_updated,1,19) AS last_updated
				FROM ttrss_feeds
				WHERE owner_uid = ".$_SESSION["uid"].
				"$search_qpart ORDER BY order_id, title");

			while ($feed_line = $this->dbh->fetch_assoc($feed_result)) {
				$feed = array();
				$feed['id'] = 'FEED:' . $feed_line['id'];
				$feed['bare_id'] = (int)$feed_line['id'];
				$feed['auxcounter'] = 0;
				$feed['name'] = $feed_line['title'];
				$feed['checkbox'] = false;
				$feed['error'] = $feed_line['last_error'];
				$feed['icon'] = getFeedIcon($feed_line['id']);
				$feed['param'] = make_local_datetime(
					$feed_line['last_updated'], true);
				$feed['unread'] = 0;
				$feed['type'] = 'feed';

				array_push($root['items'], $feed);
			}

			$root['param'] = vsprintf(_ngettext('(%d feed)', '(%d feeds)', count($cat['items'])), count($cat['items']));
		}

		$fl = array();
		$fl['identifier'] = 'id';
		$fl['label'] = 'name';

		if ($_REQUEST['mode'] != 2) {
			$fl['items'] = array($root);
		} else {
			$fl['items'] =& $root['items'];
		}

		return $fl;
	}

	function catsortreset() {
		$this->dbh->query("UPDATE ttrss_feed_categories
				SET order_id = 0 WHERE owner_uid = " . $_SESSION["uid"]);
		return;
	}

	function feedsortreset() {
		$this->dbh->query("UPDATE ttrss_feeds
				SET order_id = 0 WHERE owner_uid = " . $_SESSION["uid"]);
		return;
	}

	private function process_category_order(&$data_map, $item_id, $parent_id = false, $nest_level = 0) {
		$debug = isset($_REQUEST["debug"]);

		$prefix = "";
		for ($i = 0; $i < $nest_level; $i++)
			$prefix .= "   ";

		if ($debug) _debug("$prefix C: $item_id P: $parent_id");

		$bare_item_id = substr($item_id, strpos($item_id, ':')+1);

		if ($item_id != 'root') {
			if ($parent_id && $parent_id != 'root') {
				$parent_bare_id = substr($parent_id, strpos($parent_id, ':')+1);
				$parent_qpart = $this->dbh->escape_string($parent_bare_id);
			} else {
				$parent_qpart = 'NULL';
			}

			$this->dbh->query("UPDATE ttrss_feed_categories
				SET parent_cat = $parent_qpart WHERE id = '$bare_item_id' AND
				owner_uid = " . $_SESSION["uid"]);
		}

		$order_id = 0;

		$cat = $data_map[$item_id];

		if ($cat && is_array($cat)) {
			foreach ($cat as $item) {
				$id = $item['_reference'];
				$bare_id = substr($id, strpos($id, ':')+1);

				if ($debug) _debug("$prefix [$order_id] $id/$bare_id");

				if ($item['_reference']) {

					if (strpos($id, "FEED") === 0) {

						$cat_id = ($item_id != "root") ?
							$this->dbh->escape_string($bare_item_id) : "NULL";

						$cat_qpart = ($cat_id != 0) ? "cat_id = '$cat_id'" :
							"cat_id = NULL";

						$this->dbh->query("UPDATE ttrss_feeds
							SET order_id = $order_id, $cat_qpart
							WHERE id = '$bare_id' AND
								owner_uid = " . $_SESSION["uid"]);

					} else if (strpos($id, "CAT:") === 0) {
						$this->process_category_order($data_map, $item['_reference'], $item_id,
							$nest_level+1);

						if ($item_id != 'root') {
							$parent_qpart = $this->dbh->escape_string($bare_id);
						} else {
							$parent_qpart = 'NULL';
						}

						$this->dbh->query("UPDATE ttrss_feed_categories
								SET order_id = '$order_id' WHERE id = '$bare_id' AND
								owner_uid = " . $_SESSION["uid"]);
					}
				}

				++$order_id;
			}
		}
	}

	function savefeedorder() {
		$data = json_decode($_POST['payload'], true);

		#file_put_contents("/tmp/saveorder.json", $_POST['payload']);
		#$data = json_decode(file_get_contents("/tmp/saveorder.json"), true);

		if (!is_array($data['items']))
			$data['items'] = json_decode($data['items'], true);

#		print_r($data['items']);

		if (is_array($data) && is_array($data['items'])) {
			$cat_order_id = 0;

			$data_map = array();
			$root_item = false;

			foreach ($data['items'] as $item) {

#				if ($item['id'] != 'root') {
					if (is_array($item['items'])) {
						if (isset($item['items']['_reference'])) {
							$data_map[$item['id']] = array($item['items']);
						} else {
							$data_map[$item['id']] =& $item['items'];
						}
					}
				if ($item['id'] == 'root') {
					$root_item = $item['id'];
				}
			}

			$this->process_category_order($data_map, $root_item);

			/* foreach ($data['items'][0]['items'] as $item) {
				$id = $item['_reference'];
				$bare_id = substr($id, strpos($id, ':')+1);

				++$cat_order_id;

				if ($bare_id > 0) {
					$this->dbh->query("UPDATE ttrss_feed_categories
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

						$this->dbh->query("UPDATE ttrss_feeds
							SET order_id = '$feed_order_id',
							$cat_query
							WHERE id = '$feed_id' AND
								owner_uid = " . $_SESSION["uid"]);

						++$feed_order_id;
					}
				}
			} */
		}

		return;
	}

	function removeicon() {
		$feed_id = $this->dbh->escape_string($_REQUEST["feed_id"]);

		$result = $this->dbh->query("SELECT id FROM ttrss_feeds
			WHERE id = '$feed_id' AND owner_uid = ". $_SESSION["uid"]);

		if ($this->dbh->num_rows($result) != 0) {
			@unlink(ICONS_DIR . "/$feed_id.ico");

			$this->dbh->query("UPDATE ttrss_feeds SET favicon_avg_color = NULL
				where id = '$feed_id'");
		}

		return;
	}

	function uploadicon() {
		header("Content-type: text/html");

		$tmp_file = false;

		if (is_uploaded_file($_FILES['icon_file']['tmp_name'])) {
			$tmp_file = tempnam(CACHE_DIR . '/upload', 'icon');

			$result = move_uploaded_file($_FILES['icon_file']['tmp_name'],
				$tmp_file);

			if (!$result) {
				return;
			}
		} else {
			return;
		}

		$icon_file = $tmp_file;
		$feed_id = $this->dbh->escape_string($_REQUEST["feed_id"]);

		if (is_file($icon_file) && $feed_id) {
			if (filesize($icon_file) < 20000) {

				$result = $this->dbh->query("SELECT id FROM ttrss_feeds
					WHERE id = '$feed_id' AND owner_uid = ". $_SESSION["uid"]);

				if ($this->dbh->num_rows($result) != 0) {
					@unlink(ICONS_DIR . "/$feed_id.ico");
					if (rename($icon_file, ICONS_DIR . "/$feed_id.ico")) {
						$this->dbh->query("UPDATE ttrss_feeds SET
							favicon_avg_color = ''
							WHERE id = '$feed_id'");

						$rc = 0;
					}
				} else {
					$rc = 2;
				}
			} else {
				$rc = 1;
			}
		} else {
			$rc = 2;
		}

		@unlink($icon_file);

		print "<script type=\"text/javascript\">";
		print "parent.uploadIconHandler($rc);";
		print "</script>";
		return;
	}

	function editfeed() {
		global $purge_intervals;
		global $update_intervals;

		$feed_id = $this->dbh->escape_string($_REQUEST["id"]);

		$result = $this->dbh->query(
			"SELECT * FROM ttrss_feeds WHERE id = '$feed_id' AND
				owner_uid = " . $_SESSION["uid"]);

		$auth_pass_encrypted = sql_bool_to_bool($this->dbh->fetch_result($result, 0,
			"auth_pass_encrypted"));

		$title = htmlspecialchars($this->dbh->fetch_result($result,
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

		$feed_url = $this->dbh->fetch_result($result, 0, "feed_url");
		$feed_url = htmlspecialchars($this->dbh->fetch_result($result,
			0, "feed_url"));

		print "<hr/>";

		print __('URL:') . " ";
		print "<input dojoType=\"dijit.form.ValidationTextBox\" required=\"1\"
			placeHolder=\"".__("Feed URL")."\"
			regExp='^(http|https)://.*' style=\"width : 20em\"
			name=\"feed_url\" value=\"$feed_url\">";

		$last_error = $this->dbh->fetch_result($result, 0, "last_error");

		if ($last_error) {
			print "&nbsp;<span title=\"".htmlspecialchars($last_error)."\"
				class=\"feed_error\">(error)</span>";

		}

		/* Category */

		if (get_pref('ENABLE_FEED_CATS')) {

			$cat_id = $this->dbh->fetch_result($result, 0, "cat_id");

			print "<hr/>";

			print __('Place in category:') . " ";

			print_feed_cat_select("cat_id", $cat_id,
				'dojoType="dijit.form.Select"');
		}

		print "</div>";

		print "<div class=\"dlgSec\">".__("Update")."</div>";
		print "<div class=\"dlgSecCont\">";

		/* Update Interval */

		$update_interval = $this->dbh->fetch_result($result, 0, "update_interval");

		print_select_hash("update_interval", $update_interval, $update_intervals,
			'dojoType="dijit.form.Select"');

		/* Purge intl */

		$purge_interval = $this->dbh->fetch_result($result, 0, "purge_interval");

		print "<hr/>";
		print __('Article purging:') . " ";

		print_select_hash("purge_interval", $purge_interval, $purge_intervals,
			'dojoType="dijit.form.Select" ' .
				((FORCE_ARTICLE_PURGE == 0) ? "" : 'disabled="1"'));

		print "</div>";
		print "<div class=\"dlgSec\">".__("Authentication")."</div>";
		print "<div class=\"dlgSecCont\">";

		$auth_login = htmlspecialchars($this->dbh->fetch_result($result, 0, "auth_login"));

		print "<input dojoType=\"dijit.form.TextBox\" id=\"feedEditDlg_login\"
			placeHolder=\"".__("Login")."\"
			name=\"auth_login\" value=\"$auth_login\"><hr/>";

		$auth_pass = $this->dbh->fetch_result($result, 0, "auth_pass");

		if ($auth_pass_encrypted) {
			require_once "crypt.php";
			$auth_pass = decrypt_string($auth_pass);
		}

		$auth_pass = htmlspecialchars($auth_pass);

		print "<input dojoType=\"dijit.form.TextBox\" type=\"password\" name=\"auth_pass\"
			placeHolder=\"".__("Password")."\"
			value=\"$auth_pass\">";

		print "<div dojoType=\"dijit.Tooltip\" connectId=\"feedEditDlg_login\" position=\"below\">
			".__('<b>Hint:</b> you need to fill in your login information if your feed requires authentication, except for Twitter feeds.')."
			</div>";

		print "</div>";
		print "<div class=\"dlgSec\">".__("Options")."</div>";
		print "<div class=\"dlgSecCont\">";

		$private = sql_bool_to_bool($this->dbh->fetch_result($result, 0, "private"));

		if ($private) {
			$checked = "checked=\"1\"";
		} else {
			$checked = "";
		}

		print "<input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" name=\"private\" id=\"private\"
			$checked>&nbsp;<label for=\"private\">".__('Hide from Popular feeds')."</label>";

		$include_in_digest = sql_bool_to_bool($this->dbh->fetch_result($result, 0, "include_in_digest"));

		if ($include_in_digest) {
			$checked = "checked=\"1\"";
		} else {
			$checked = "";
		}

		print "<hr/><input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" id=\"include_in_digest\"
			name=\"include_in_digest\"
			$checked>&nbsp;<label for=\"include_in_digest\">".__('Include in e-mail digest')."</label>";


		$always_display_enclosures = sql_bool_to_bool($this->dbh->fetch_result($result, 0, "always_display_enclosures"));

		if ($always_display_enclosures) {
			$checked = "checked";
		} else {
			$checked = "";
		}

		print "<hr/><input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" id=\"always_display_enclosures\"
			name=\"always_display_enclosures\"
			$checked>&nbsp;<label for=\"always_display_enclosures\">".__('Always display image attachments')."</label>";

		$hide_images = sql_bool_to_bool($this->dbh->fetch_result($result, 0, "hide_images"));

		if ($hide_images) {
			$checked = "checked=\"1\"";
		} else {
			$checked = "";
		}

		print "<hr/><input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" id=\"hide_images\"
		name=\"hide_images\"
			$checked>&nbsp;<label for=\"hide_images\">".
		__('Do not embed images')."</label>";

		$cache_images = sql_bool_to_bool($this->dbh->fetch_result($result, 0, "cache_images"));

		if ($cache_images) {
			$checked = "checked=\"1\"";
		} else {
			$checked = "";
		}

		print "<hr/><input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" id=\"cache_images\"
		name=\"cache_images\"
			$checked>&nbsp;<label for=\"cache_images\">".
		__('Cache images locally')."</label>";

		$mark_unread_on_update = sql_bool_to_bool($this->dbh->fetch_result($result, 0, "mark_unread_on_update"));

		if ($mark_unread_on_update) {
			$checked = "checked";
		} else {
			$checked = "";
		}

		print "<hr/><input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" id=\"mark_unread_on_update\"
			name=\"mark_unread_on_update\"
			$checked>&nbsp;<label for=\"mark_unread_on_update\">".__('Mark updated articles as unread')."</label>";

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

		PluginHost::getInstance()->run_hooks(PluginHost::HOOK_PREFS_EDIT_FEED,
			"hook_prefs_edit_feed", $feed_id);

		$title = htmlspecialchars($title, ENT_QUOTES);

		print "<div class='dlgButtons'>
			<div style=\"float : left\">
			<button dojoType=\"dijit.form.Button\" onclick='return unsubscribeFeed($feed_id, \"$title\")'>".
				__('Unsubscribe')."</button>";

		if (PUBSUBHUBBUB_ENABLED) {
			$pubsub_state = $this->dbh->fetch_result($result, 0, "pubsub_state");
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

		$feed_ids = $this->dbh->escape_string($_REQUEST["ids"]);

		print_notice("Enable the options you wish to apply using checkboxes on the right:");

		print "<p>";

		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"ids\" value=\"$feed_ids\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"pref-feeds\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"method\" value=\"batchEditSave\">";

		print "<div class=\"dlgSec\">".__("Feed")."</div>";
		print "<div class=\"dlgSecCont\">";

		/* Title */

		print "<input dojoType=\"dijit.form.ValidationTextBox\"
			disabled=\"1\" style=\"font-size : 16px; width : 20em;\" required=\"1\"
			name=\"title\" value=\"\">";

		$this->batch_edit_cbox("title");

		/* Feed URL */

		print "<br/>";

		print __('URL:') . " ";
		print "<input dojoType=\"dijit.form.ValidationTextBox\" disabled=\"1\"
			required=\"1\" regExp='^(http|https)://.*' style=\"width : 20em\"
			name=\"feed_url\" value=\"\">";

		$this->batch_edit_cbox("feed_url");

		/* Category */

		if (get_pref('ENABLE_FEED_CATS')) {

			print "<br/>";

			print __('Place in category:') . " ";

			print_feed_cat_select("cat_id", false,
				'disabled="1" dojoType="dijit.form.Select"');

			$this->batch_edit_cbox("cat_id");

		}

		print "</div>";

		print "<div class=\"dlgSec\">".__("Update")."</div>";
		print "<div class=\"dlgSecCont\">";

		/* Update Interval */

		print_select_hash("update_interval", "", $update_intervals,
			'disabled="1" dojoType="dijit.form.Select"');

		$this->batch_edit_cbox("update_interval");

		/* Purge intl */

		if (FORCE_ARTICLE_PURGE == 0) {

			print "<br/>";

			print __('Article purging:') . " ";

			print_select_hash("purge_interval", "", $purge_intervals,
				'disabled="1" dojoType="dijit.form.Select"');

			$this->batch_edit_cbox("purge_interval");
		}

		print "</div>";
		print "<div class=\"dlgSec\">".__("Authentication")."</div>";
		print "<div class=\"dlgSecCont\">";

		print "<input dojoType=\"dijit.form.TextBox\"
			placeHolder=\"".__("Login")."\" disabled=\"1\"
			name=\"auth_login\" value=\"\">";

		$this->batch_edit_cbox("auth_login");

		print "<br/><input dojoType=\"dijit.form.TextBox\" type=\"password\" name=\"auth_pass\"
			placeHolder=\"".__("Password")."\" disabled=\"1\"
			value=\"\">";

		$this->batch_edit_cbox("auth_pass");

		print "</div>";
		print "<div class=\"dlgSec\">".__("Options")."</div>";
		print "<div class=\"dlgSecCont\">";

		print "<input disabled=\"1\" type=\"checkbox\" name=\"private\" id=\"private\"
			dojoType=\"dijit.form.CheckBox\">&nbsp;<label id=\"private_l\" class='insensitive' for=\"private\">".__('Hide from Popular feeds')."</label>";

		print "&nbsp;"; $this->batch_edit_cbox("private", "private_l");

		print "<br/><input disabled=\"1\" type=\"checkbox\" id=\"include_in_digest\"
			name=\"include_in_digest\"
			dojoType=\"dijit.form.CheckBox\">&nbsp;<label id=\"include_in_digest_l\" class='insensitive' for=\"include_in_digest\">".__('Include in e-mail digest')."</label>";

		print "&nbsp;"; $this->batch_edit_cbox("include_in_digest", "include_in_digest_l");

		print "<br/><input disabled=\"1\" type=\"checkbox\" id=\"always_display_enclosures\"
			name=\"always_display_enclosures\"
			dojoType=\"dijit.form.CheckBox\">&nbsp;<label id=\"always_display_enclosures_l\" class='insensitive' for=\"always_display_enclosures\">".__('Always display image attachments')."</label>";

		print "&nbsp;"; $this->batch_edit_cbox("always_display_enclosures", "always_display_enclosures_l");

		print "<br/><input disabled=\"1\" type=\"checkbox\" id=\"hide_images\"
			name=\"hide_images\"
			dojoType=\"dijit.form.CheckBox\">&nbsp;<label class='insensitive' id=\"hide_images_l\"
			for=\"hide_images\">".
		__('Do not embed images')."</label>";

		print "&nbsp;"; $this->batch_edit_cbox("hide_images", "hide_images_l");

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

		$feed_title = $this->dbh->escape_string(trim($_POST["title"]));
		$feed_link = $this->dbh->escape_string(trim($_POST["feed_url"]));
		$upd_intl = (int) $this->dbh->escape_string($_POST["update_interval"]);
		$purge_intl = (int) $this->dbh->escape_string($_POST["purge_interval"]);
		$feed_id = (int) $this->dbh->escape_string($_POST["id"]); /* editSave */
		$feed_ids = $this->dbh->escape_string($_POST["ids"]); /* batchEditSave */
		$cat_id = (int) $this->dbh->escape_string($_POST["cat_id"]);
		$auth_login = $this->dbh->escape_string(trim($_POST["auth_login"]));
		$auth_pass = trim($_POST["auth_pass"]);
		$private = checkbox_to_sql_bool($this->dbh->escape_string($_POST["private"]));
		$include_in_digest = checkbox_to_sql_bool(
			$this->dbh->escape_string($_POST["include_in_digest"]));
		$cache_images = checkbox_to_sql_bool(
			$this->dbh->escape_string($_POST["cache_images"]));
		$hide_images = checkbox_to_sql_bool(
			$this->dbh->escape_string($_POST["hide_images"]));
		$always_display_enclosures = checkbox_to_sql_bool(
			$this->dbh->escape_string($_POST["always_display_enclosures"]));

		$mark_unread_on_update = checkbox_to_sql_bool(
			$this->dbh->escape_string($_POST["mark_unread_on_update"]));

		if (strlen(FEED_CRYPT_KEY) > 0) {
			require_once "crypt.php";
			$auth_pass = substr(encrypt_string($auth_pass), 0, 250);
			$auth_pass_encrypted = 'true';
		} else {
			$auth_pass_encrypted = 'false';
		}

		$auth_pass = $this->dbh->escape_string($auth_pass);

		if (get_pref('ENABLE_FEED_CATS')) {
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

		if (!$batch) {

			$result = $this->dbh->query("UPDATE ttrss_feeds SET
				$category_qpart
				title = '$feed_title', feed_url = '$feed_link',
				update_interval = '$upd_intl',
				purge_interval = '$purge_intl',
				auth_login = '$auth_login',
				auth_pass = '$auth_pass',
				auth_pass_encrypted = $auth_pass_encrypted,
				private = $private,
				cache_images = $cache_images,
				hide_images = $hide_images,
				include_in_digest = $include_in_digest,
				always_display_enclosures = $always_display_enclosures,
				mark_unread_on_update = $mark_unread_on_update
			WHERE id = '$feed_id' AND owner_uid = " . $_SESSION["uid"]);

			PluginHost::getInstance()->run_hooks(PluginHost::HOOK_PREFS_SAVE_FEED,
				"hook_prefs_save_feed", $feed_id);

		} else {
			$feed_data = array();

			foreach (array_keys($_POST) as $k) {
				if ($k != "op" && $k != "method" && $k != "ids") {
					$feed_data[$k] = $_POST[$k];
				}
			}

			$this->dbh->query("BEGIN");

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
						$qpart = "auth_pass = '$auth_pass' AND
							auth_pass_encrypted = $auth_pass_encrypted";
						break;

					case "private":
						$qpart = "private = $private";
						break;

					case "include_in_digest":
						$qpart = "include_in_digest = $include_in_digest";
						break;

					case "always_display_enclosures":
						$qpart = "always_display_enclosures = $always_display_enclosures";
						break;

					case "mark_unread_on_update":
						$qpart = "mark_unread_on_update = $mark_unread_on_update";
						break;

					case "cache_images":
						$qpart = "cache_images = $cache_images";
						break;

					case "hide_images":
						$qpart = "hide_images = $hide_images";
						break;

					case "cat_id":
						$qpart = $category_qpart_nocomma;
						break;

				}

				if ($qpart) {
					$this->dbh->query(
						"UPDATE ttrss_feeds SET $qpart WHERE id IN ($feed_ids)
						AND owner_uid = " . $_SESSION["uid"]);
					print "<br/>";
				}
			}

			$this->dbh->query("COMMIT");
		}
		return;
	}

	function resetPubSub() {

		$ids = $this->dbh->escape_string($_REQUEST["ids"]);

		$this->dbh->query("UPDATE ttrss_feeds SET pubsub_state = 0 WHERE id IN ($ids)
			AND owner_uid = " . $_SESSION["uid"]);

		return;
	}

	function remove() {

		$ids = explode(",", $this->dbh->escape_string($_REQUEST["ids"]));

		foreach ($ids as $id) {
			Pref_Feeds::remove_feed($id, $_SESSION["uid"]);
		}

		return;
	}

	function clear() {
		$id = $this->dbh->escape_string($_REQUEST["id"]);
		$this->clear_feed_articles($id);
	}

	function rescore() {
		require_once "rssfuncs.php";

		$ids = explode(",", $this->dbh->escape_string($_REQUEST["ids"]));

		foreach ($ids as $id) {

			$filters = load_filters($id, $_SESSION["uid"], 6);

			$result = $this->dbh->query("SELECT
				title, content, link, ref_id, author,".
				SUBSTRING_FOR_DATE."(updated, 1, 19) AS updated
			  	FROM
					ttrss_user_entries, ttrss_entries
					WHERE ref_id = id AND feed_id = '$id' AND
						owner_uid = " .$_SESSION['uid']."
					");

			$scores = array();

			while ($line = $this->dbh->fetch_assoc($result)) {

				$tags = get_article_tags($line["ref_id"]);

				$article_filters = get_article_filters($filters, $line['title'],
					$line['content'], $line['link'], strtotime($line['updated']),
					$line['author'], $tags);

				$new_score = calculate_article_score($article_filters);

				if (!$scores[$new_score]) $scores[$new_score] = array();

				array_push($scores[$new_score], $line['ref_id']);
			}

			foreach (array_keys($scores) as $s) {
				if ($s > 1000) {
					$this->dbh->query("UPDATE ttrss_user_entries SET score = '$s',
						marked = true WHERE
						ref_id IN (" . join(',', $scores[$s]) . ")");
				} else if ($s < -500) {
					$this->dbh->query("UPDATE ttrss_user_entries SET score = '$s',
						unread = false WHERE
						ref_id IN (" . join(',', $scores[$s]) . ")");
				} else {
					$this->dbh->query("UPDATE ttrss_user_entries SET score = '$s' WHERE
						ref_id IN (" . join(',', $scores[$s]) . ")");
				}
			}
		}

		print __("All done.");

	}

	function rescoreAll() {

		$result = $this->dbh->query(
			"SELECT id FROM ttrss_feeds WHERE owner_uid = " . $_SESSION['uid']);

		while ($feed_line = $this->dbh->fetch_assoc($result)) {

			$id = $feed_line["id"];

			$filters = load_filters($id, $_SESSION["uid"], 6);

			$tmp_result = $this->dbh->query("SELECT
				title, content, link, ref_id, author,".
				  	SUBSTRING_FOR_DATE."(updated, 1, 19) AS updated
					FROM
					ttrss_user_entries, ttrss_entries
					WHERE ref_id = id AND feed_id = '$id' AND
						owner_uid = " .$_SESSION['uid']."
					");

			$scores = array();

			while ($line = $this->dbh->fetch_assoc($tmp_result)) {

				$tags = get_article_tags($line["ref_id"]);

				$article_filters = get_article_filters($filters, $line['title'],
					$line['content'], $line['link'], strtotime($line['updated']),
					$line['author'], $tags);

				$new_score = calculate_article_score($article_filters);

				if (!$scores[$new_score]) $scores[$new_score] = array();

				array_push($scores[$new_score], $line['ref_id']);
			}

			foreach (array_keys($scores) as $s) {
				if ($s > 1000) {
					$this->dbh->query("UPDATE ttrss_user_entries SET score = '$s',
						marked = true WHERE
						ref_id IN (" . join(',', $scores[$s]) . ")");
				} else {
					$this->dbh->query("UPDATE ttrss_user_entries SET score = '$s' WHERE
						ref_id IN (" . join(',', $scores[$s]) . ")");
				}
			}
		}

		print __("All done.");

	}

	function categorize() {
		$ids = explode(",", $this->dbh->escape_string($_REQUEST["ids"]));

		$cat_id = $this->dbh->escape_string($_REQUEST["cat_id"]);

		if ($cat_id == 0) {
			$cat_id_qpart = 'NULL';
		} else {
			$cat_id_qpart = "'$cat_id'";
		}

		$this->dbh->query("BEGIN");

		foreach ($ids as $id) {

			$this->dbh->query("UPDATE ttrss_feeds SET cat_id = $cat_id_qpart
				WHERE id = '$id'
			  	AND owner_uid = " . $_SESSION["uid"]);

		}

		$this->dbh->query("COMMIT");
	}

	function removeCat() {
		$ids = explode(",", $this->dbh->escape_string($_REQUEST["ids"]));
		foreach ($ids as $id) {
			$this->remove_feed_category($id, $_SESSION["uid"]);
		}
	}

	function addCat() {
		$feed_cat = $this->dbh->escape_string(trim($_REQUEST["cat"]));

		add_feed_category($feed_cat);
	}

	function index() {

		print "<div dojoType=\"dijit.layout.AccordionContainer\" region=\"center\">";
		print "<div id=\"pref-feeds-feeds\" dojoType=\"dijit.layout.AccordionPane\" title=\"".__('Feeds')."\">";

		$result = $this->dbh->query("SELECT COUNT(id) AS num_errors
			FROM ttrss_feeds WHERE last_error != '' AND owner_uid = ".$_SESSION["uid"]);

		$num_errors = $this->dbh->fetch_result($result, 0, "num_errors");

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

		$result = $this->dbh->query("SELECT COUNT(*) AS num_inactive FROM ttrss_feeds WHERE
					(SELECT MAX(updated) FROM ttrss_entries, ttrss_user_entries WHERE
						ttrss_entries.id = ref_id AND
							ttrss_user_entries.feed_id = ttrss_feeds.id) < $interval_qpart AND
			ttrss_feeds.owner_uid = ".$_SESSION["uid"]);

		$num_inactive = $this->dbh->fetch_result($result, 0, "num_inactive");

		if ($num_inactive > 0) {
			$inactive_button = "<button dojoType=\"dijit.form.Button\"
			  		onclick=\"showInactiveFeeds()\">" .
					__("Inactive feeds") . "</button>";
		}

		$feed_search = $this->dbh->escape_string($_REQUEST["search"]);

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
		print "<div onclick=\"batchSubscribe()\"
			dojoType=\"dijit.MenuItem\">".__('Batch subscribe')."</div>";
		print "<div dojoType=\"dijit.MenuItem\" onclick=\"removeSelectedFeeds()\">"
			.__('Unsubscribe')."</div> ";
		print "</div></div>";

		if (get_pref('ENABLE_FEED_CATS')) {
			print "<div dojoType=\"dijit.form.DropDownButton\">".
					"<span>" . __('Categories')."</span>";
			print "<div dojoType=\"dijit.Menu\" style=\"display: none;\">";
			print "<div onclick=\"createCategory()\"
				dojoType=\"dijit.MenuItem\">".__('Add category')."</div>";
			print "<div onclick=\"resetCatOrder()\"
				dojoType=\"dijit.MenuItem\">".__('Reset sort order')."</div>";
			print "<div onclick=\"removeSelectedCategories()\"
				dojoType=\"dijit.MenuItem\">".__('Remove selected')."</div>";
			print "</div></div>";

		}

		print $error_button;
		print $inactive_button;

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

#		print "<div dojoType=\"dijit.Tooltip\" connectId=\"feedTree\" position=\"below\">
#			".__('<b>Hint:</b> you can drag feeds and categories around.')."
#			</div>";

		print '</div>';
		print '</div>';

		print "</div>"; # feeds pane

		print "<div dojoType=\"dijit.layout.AccordionPane\" title=\"".__('OPML')."\">";

		print_notice(__("Using OPML you can export and import your feeds, filters, labels and Tiny Tiny RSS settings.") . __("Only main settings profile can be migrated using OPML."));

		print "<iframe id=\"upload_iframe\"
			name=\"upload_iframe\" onload=\"opmlImportComplete(this)\"
			style=\"width: 400px; height: 100px; display: none;\"></iframe>";

		print "<form  name=\"opml_form\" style='display : block' target=\"upload_iframe\"
			enctype=\"multipart/form-data\" method=\"POST\"
			action=\"backend.php\">
			<input id=\"opml_file\" name=\"opml_file\" type=\"file\">&nbsp;
			<input type=\"hidden\" name=\"op\" value=\"dlg\">
			<input type=\"hidden\" name=\"method\" value=\"importOpml\">
			<button dojoType=\"dijit.form.Button\" onclick=\"return opmlImport();\" type=\"submit\">" .
			__('Import my OPML') . "</button>";

		print "<hr>";

		print "<p>" . __('Filename:') .
            " <input type=\"text\" id=\"filename\" value=\"TinyTinyRSS.opml\" />&nbsp;" .
				__('Include settings') . "<input type=\"checkbox\" id=\"settings\" checked=\"1\"/>";

		print "</p><button dojoType=\"dijit.form.Button\"
			onclick=\"gotoExportOpml(document.opml_form.filename.value, document.opml_form.settings.checked)\" >" .
              __('Export OPML') . "</button></p></form>";

		print "<hr>";

		print "<p>".__('Your OPML can be published publicly and can be subscribed by anyone who knows the URL below.') . " ";

		print __("Published OPML does not include your Tiny Tiny RSS settings, feeds that require authentication or feeds hidden from Popular feeds.") . "</p>";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return displayDlg('".__("Public OPML URL")."','pubOPMLUrl')\">".
			__('Display published OPML URL')."</button> ";

		PluginHost::getInstance()->run_hooks(PluginHost::HOOK_PREFS_TAB_SECTION,
			"hook_prefs_tab_section", "prefFeedsOPML");

		print "</div>"; # pane

		if (strpos($_SERVER['HTTP_USER_AGENT'], "Firefox") !== false) {

			print "<div dojoType=\"dijit.layout.AccordionPane\" title=\"".__('Firefox integration')."\">";

			print_notice(__('This Tiny Tiny RSS site can be used as a Firefox Feed Reader by clicking the link below.'));

			print "<p>";

			print "<button onclick='window.navigator.registerContentHandler(" .
                      "\"application/vnd.mozilla.maybe.feed\", " .
                      "\"" . add_feed_url() . "\", " . " \"Tiny Tiny RSS\")'>" .
							 __('Click here to register this site as a feed reader.') .
				"</button>";

			print "</p>";

			print "</div>"; # pane
		}

		print "<div dojoType=\"dijit.layout.AccordionPane\" title=\"".__('Published & shared articles / Generated feeds')."\">";

		print_notice(__('Published articles are exported as a public RSS feed and can be subscribed by anyone who knows the URL specified below.'));

		$rss_url = '-2::' . htmlspecialchars(get_self_url_prefix() .
				"/public.php?op=rss&id=-2&view-mode=all_articles");;

		print "<p>";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return displayDlg('".__("View as RSS")."','generatedFeed', '$rss_url')\">".
			__('Display URL')."</button> ";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return clearFeedAccessKeys()\">".
			__('Clear all generated URLs')."</button> ";

		print "</p>";

		print_warning(__("You can disable all articles shared by unique URLs here."));

		print "<p>";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return clearArticleAccessKeys()\">".
			__('Unshare all articles')."</button> ";

		print "</p>";

		PluginHost::getInstance()->run_hooks(PluginHost::HOOK_PREFS_TAB_SECTION,
			"hook_prefs_tab_section", "prefFeedsPublishedGenerated");

		print "</div>"; #pane

		PluginHost::getInstance()->run_hooks(PluginHost::HOOK_PREFS_TAB,
			"hook_prefs_tab", "prefFeeds");

		print "</div>"; #container
	}

	private function feedlist_init_cat($cat_id) {
		$obj = array();
		$cat_id = (int) $cat_id;

		if ($cat_id > 0) {
			$cat_unread = ccache_find($cat_id, $_SESSION["uid"], true);
		} else if ($cat_id == 0 || $cat_id == -2) {
			$cat_unread = getCategoryUnread($cat_id);
		}

		$obj['id'] = 'CAT:' . $cat_id;
		$obj['items'] = array();
		$obj['name'] = getCategoryTitle($cat_id);
		$obj['type'] = 'category';
		$obj['unread'] = (int) $cat_unread;
		$obj['bare_id'] = $cat_id;

		return $obj;
	}

	private function feedlist_init_feed($feed_id, $title = false, $unread = false, $error = '', $updated = '') {
		$obj = array();
		$feed_id = (int) $feed_id;

		if (!$title)
			$title = getFeedTitle($feed_id, false);

		if ($unread === false)
			$unread = getFeedUnread($feed_id, false);

		$obj['id'] = 'FEED:' . $feed_id;
		$obj['name'] = $title;
		$obj['unread'] = (int) $unread;
		$obj['type'] = 'feed';
		$obj['error'] = $error;
		$obj['updated'] = $updated;
		$obj['icon'] = getFeedIcon($feed_id);
		$obj['bare_id'] = $feed_id;
		$obj['auxcounter'] = 0;

		return $obj;
	}

	function inactiveFeeds() {

		if (DB_TYPE == "pgsql") {
			$interval_qpart = "NOW() - INTERVAL '3 months'";
		} else {
			$interval_qpart = "DATE_SUB(NOW(), INTERVAL 3 MONTH)";
		}

		$result = $this->dbh->query("SELECT ttrss_feeds.title, ttrss_feeds.site_url,
		  		ttrss_feeds.feed_url, ttrss_feeds.id, MAX(updated) AS last_article
			FROM ttrss_feeds, ttrss_entries, ttrss_user_entries WHERE
				(SELECT MAX(updated) FROM ttrss_entries, ttrss_user_entries WHERE
					ttrss_entries.id = ref_id AND
						ttrss_user_entries.feed_id = ttrss_feeds.id) < $interval_qpart
			AND ttrss_feeds.owner_uid = ".$_SESSION["uid"]." AND
				ttrss_user_entries.feed_id = ttrss_feeds.id AND
				ttrss_entries.id = ref_id
			GROUP BY ttrss_feeds.title, ttrss_feeds.id, ttrss_feeds.site_url, ttrss_feeds.feed_url
			ORDER BY last_article");

		print "<p" .__("These feeds have not been updated with new content for 3 months (oldest first):") . "</p>";

		print "<div dojoType=\"dijit.Toolbar\">";
		print "<div dojoType=\"dijit.form.DropDownButton\">".
				"<span>" . __('Select')."</span>";
		print "<div dojoType=\"dijit.Menu\" style=\"display: none;\">";
		print "<div onclick=\"selectTableRows('prefInactiveFeedList', 'all')\"
			dojoType=\"dijit.MenuItem\">".__('All')."</div>";
		print "<div onclick=\"selectTableRows('prefInactiveFeedList', 'none')\"
			dojoType=\"dijit.MenuItem\">".__('None')."</div>";
		print "</div></div>";
		print "</div>"; #toolbar

		print "<div class=\"inactiveFeedHolder\">";

		print "<table width=\"100%\" cellspacing=\"0\" id=\"prefInactiveFeedList\">";

		$lnum = 1;

		while ($line = $this->dbh->fetch_assoc($result)) {

			$feed_id = $line["id"];
			$this_row_id = "id=\"FUPDD-$feed_id\"";

			# class needed for selectTableRows()
			print "<tr class=\"placeholder\" $this_row_id>";

			$edit_title = htmlspecialchars($line["title"]);

			# id needed for selectTableRows()
			print "<td width='5%' align='center'><input
				onclick='toggleSelectRow2(this);' dojoType=\"dijit.form.CheckBox\"
				type=\"checkbox\" id=\"FUPDC-$feed_id\"></td>";
			print "<td>";

			print "<a class=\"visibleLink\" href=\"#\" ".
				"title=\"".__("Click to edit feed")."\" ".
				"onclick=\"editFeed(".$line["id"].")\">".
				htmlspecialchars($line["title"])."</a>";

			print "</td><td class=\"insensitive\" align='right'>";
			print make_local_datetime($line['last_article'], false);
			print "</td>";
			print "</tr>";

			++$lnum;
		}

		print "</table>";
		print "</div>";

		print "<div class='dlgButtons'>";
		print "<div style='float : left'>";
		print "<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('inactiveFeedsDlg').removeSelected()\">"
			.__('Unsubscribe from selected feeds')."</button> ";
		print "</div>";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('inactiveFeedsDlg').hide()\">".
			__('Close this window')."</button>";

		print "</div>";

	}

	function feedsWithErrors() {
		$result = $this->dbh->query("SELECT id,title,feed_url,last_error,site_url
		FROM ttrss_feeds WHERE last_error != '' AND owner_uid = ".$_SESSION["uid"]);

		print "<div dojoType=\"dijit.Toolbar\">";
		print "<div dojoType=\"dijit.form.DropDownButton\">".
				"<span>" . __('Select')."</span>";
		print "<div dojoType=\"dijit.Menu\" style=\"display: none;\">";
		print "<div onclick=\"selectTableRows('prefErrorFeedList', 'all')\"
			dojoType=\"dijit.MenuItem\">".__('All')."</div>";
		print "<div onclick=\"selectTableRows('prefErrorFeedList', 'none')\"
			dojoType=\"dijit.MenuItem\">".__('None')."</div>";
		print "</div></div>";
		print "</div>"; #toolbar

		print "<div class=\"inactiveFeedHolder\">";

		print "<table width=\"100%\" cellspacing=\"0\" id=\"prefErrorFeedList\">";

		$lnum = 1;

		while ($line = $this->dbh->fetch_assoc($result)) {

			$feed_id = $line["id"];
			$this_row_id = "id=\"FERDD-$feed_id\"";

			# class needed for selectTableRows()
			print "<tr class=\"placeholder\" $this_row_id>";

			$edit_title = htmlspecialchars($line["title"]);

			# id needed for selectTableRows()
			print "<td width='5%' align='center'><input
				onclick='toggleSelectRow2(this);' dojoType=\"dijit.form.CheckBox\"
				type=\"checkbox\" id=\"FERDC-$feed_id\"></td>";
			print "<td>";

			print "<a class=\"visibleLink\" href=\"#\" ".
				"title=\"".__("Click to edit feed")."\" ".
				"onclick=\"editFeed(".$line["id"].")\">".
				htmlspecialchars($line["title"])."</a>: ";

			print "<span class=\"insensitive\">";
			print htmlspecialchars($line["last_error"]);
			print "</span>";

			print "</td>";
			print "</tr>";

			++$lnum;
		}

		print "</table>";
		print "</div>";

		print "<div class='dlgButtons'>";
		print "<div style='float : left'>";
		print "<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('errorFeedsDlg').removeSelected()\">"
			.__('Unsubscribe from selected feeds')."</button> ";
		print "</div>";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('errorFeedsDlg').hide()\">".
			__('Close this window')."</button>";

		print "</div>";
	}

	/**
	 * Purge a feed contents, marked articles excepted.
	 *
	 * @param mixed $link The database connection.
	 * @param integer $id The id of the feed to purge.
	 * @return void
	 */
	private function clear_feed_articles($id) {

		if ($id != 0) {
			$result = $this->dbh->query("DELETE FROM ttrss_user_entries
			WHERE feed_id = '$id' AND marked = false AND owner_uid = " . $_SESSION["uid"]);
		} else {
			$result = $this->dbh->query("DELETE FROM ttrss_user_entries
			WHERE feed_id IS NULL AND marked = false AND owner_uid = " . $_SESSION["uid"]);
		}

		$result = $this->dbh->query("DELETE FROM ttrss_entries WHERE
			(SELECT COUNT(int_id) FROM ttrss_user_entries WHERE ref_id = id) = 0");

		ccache_update($id, $_SESSION['uid']);
	} // function clear_feed_articles

	private function remove_feed_category($id, $owner_uid) {

		$this->dbh->query("DELETE FROM ttrss_feed_categories
			WHERE id = '$id' AND owner_uid = $owner_uid");

		ccache_remove($id, $owner_uid, true);
	}

	static function remove_feed($id, $owner_uid) {

		if ($id > 0) {

			/* save starred articles in Archived feed */

			db_query("BEGIN");

			/* prepare feed if necessary */

			$result = db_query("SELECT feed_url FROM ttrss_feeds WHERE id = $id
				AND owner_uid = $owner_uid");

			$feed_url = db_escape_string(db_fetch_result($result, 0, "feed_url"));

			$result = db_query("SELECT id FROM ttrss_archived_feeds
				WHERE feed_url = '$feed_url' AND owner_uid = $owner_uid");

			if (db_num_rows($result) == 0) {
				$result = db_query("SELECT MAX(id) AS id FROM ttrss_archived_feeds");
				$new_feed_id = (int)db_fetch_result($result, 0, "id") + 1;

				db_query("INSERT INTO ttrss_archived_feeds
					(id, owner_uid, title, feed_url, site_url)
				SELECT $new_feed_id, owner_uid, title, feed_url, site_url from ttrss_feeds
				WHERE id = '$id'");

				$archive_id = $new_feed_id;
			} else {
				$archive_id = db_fetch_result($result, 0, "id");
			}

			db_query("UPDATE ttrss_user_entries SET feed_id = NULL,
				orig_feed_id = '$archive_id' WHERE feed_id = '$id' AND
					marked = true AND owner_uid = $owner_uid");

			/* Remove access key for the feed */

			db_query("DELETE FROM ttrss_access_keys WHERE
				feed_id = '$id' AND owner_uid = $owner_uid");

			/* remove the feed */

			db_query("DELETE FROM ttrss_feeds
					WHERE id = '$id' AND owner_uid = $owner_uid");

			db_query("COMMIT");

			if (file_exists(ICONS_DIR . "/$id.ico")) {
				unlink(ICONS_DIR . "/$id.ico");
			}

			ccache_remove($id, $owner_uid);

		} else {
			label_remove(feed_to_label_id($id), $owner_uid);
			//ccache_remove($id, $owner_uid); don't think labels are cached
		}
	}

	function batchSubscribe() {
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"pref-feeds\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"method\" value=\"batchaddfeeds\">";

		print "<table width='100%'><tr><td>
			".__("Add one valid RSS feed per line (no feed detection is done)")."
		</td><td align='right'>";
		if (get_pref('ENABLE_FEED_CATS')) {
			print __('Place in category:') . " ";
			print_feed_cat_select("cat", false, 'dojoType="dijit.form.Select"');
		}
		print "</td></tr><tr><td colspan='2'>";
		print "<textarea
			style='font-size : 12px; width : 100%; height: 200px;'
			placeHolder=\"".__("Feeds to subscribe, One per line")."\"
			dojoType=\"dijit.form.SimpleTextarea\" required=\"1\" name=\"feeds\"></textarea>";

		print "</td></tr><tr><td colspan='2'>";

		print "<div id='feedDlg_loginContainer' style='display : none'>
				" .
				" <input dojoType=\"dijit.form.TextBox\" name='login'\"
					placeHolder=\"".__("Login")."\"
					style=\"width : 10em;\"> ".
				" <input
					placeHolder=\"".__("Password")."\"
					dojoType=\"dijit.form.TextBox\" type='password'
					style=\"width : 10em;\" name='pass'\">".
				"</div>";

		print "</td></tr><tr><td colspan='2'>";

		print "<div style=\"clear : both\">
			<input type=\"checkbox\" name=\"need_auth\" dojoType=\"dijit.form.CheckBox\" id=\"feedDlg_loginCheck\"
					onclick='checkboxToggleElement(this, \"feedDlg_loginContainer\")'>
				<label for=\"feedDlg_loginCheck\">".
				__('Feeds require authentication.')."</div>";

		print "</form>";

		print "</td></tr></table>";

		print "<div class=\"dlgButtons\">
			<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('batchSubDlg').execute()\">".__('Subscribe')."</button>
			<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('batchSubDlg').hide()\">".__('Cancel')."</button>
			</div>";
	}

	function batchAddFeeds() {
		$cat_id = $this->dbh->escape_string($_REQUEST['cat']);
		$feeds = explode("\n", $_REQUEST['feeds']);
		$login = $this->dbh->escape_string($_REQUEST['login']);
		$pass = trim($_REQUEST['pass']);

		foreach ($feeds as $feed) {
			$feed = $this->dbh->escape_string(trim($feed));

			if (validate_feed_url($feed)) {

				$this->dbh->query("BEGIN");

				if ($cat_id == "0" || !$cat_id) {
					$cat_qpart = "NULL";
				} else {
					$cat_qpart = "'$cat_id'";
				}

				$result = $this->dbh->query(
					"SELECT id FROM ttrss_feeds
					WHERE feed_url = '$feed' AND owner_uid = ".$_SESSION["uid"]);

				if (strlen(FEED_CRYPT_KEY) > 0) {
					require_once "crypt.php";
					$pass = substr(encrypt_string($pass), 0, 250);
					$auth_pass_encrypted = 'true';
				} else {
					$auth_pass_encrypted = 'false';
				}

				$pass = $this->dbh->escape_string($pass);

				if ($this->dbh->num_rows($result) == 0) {
					$result = $this->dbh->query(
						"INSERT INTO ttrss_feeds
							(owner_uid,feed_url,title,cat_id,auth_login,auth_pass,update_method,auth_pass_encrypted)
						VALUES ('".$_SESSION["uid"]."', '$feed',
							'[Unknown]', $cat_qpart, '$login', '$pass', 0, $auth_pass_encrypted)");
				}

				$this->dbh->query("COMMIT");
			}
		}
	}

	function regenOPMLKey() {
		$this->update_feed_access_key('OPML:Publish',
		false, $_SESSION["uid"]);

		$new_link = Opml::opml_publish_url();

		print json_encode(array("link" => $new_link));
	}

	function regenFeedKey() {
		$feed_id = $this->dbh->escape_string($_REQUEST['id']);
		$is_cat = $this->dbh->escape_string($_REQUEST['is_cat']) == "true";

		$new_key = $this->update_feed_access_key($feed_id, $is_cat);

		print json_encode(array("link" => $new_key));
	}


	private function update_feed_access_key($feed_id, $is_cat, $owner_uid = false) {
		if (!$owner_uid) $owner_uid = $_SESSION["uid"];

		$sql_is_cat = bool_to_sql_bool($is_cat);

		$result = $this->dbh->query("SELECT access_key FROM ttrss_access_keys
			WHERE feed_id = '$feed_id'	AND is_cat = $sql_is_cat
			AND owner_uid = " . $owner_uid);

		if ($this->dbh->num_rows($result) == 1) {
			$key = $this->dbh->escape_string(sha1(uniqid(rand(), true)));

			$this->dbh->query("UPDATE ttrss_access_keys SET access_key = '$key'
				WHERE feed_id = '$feed_id' AND is_cat = $sql_is_cat
				AND owner_uid = " . $owner_uid);

			return $key;

		} else {
			return get_feed_access_key($feed_id, $is_cat, $owner_uid);
		}
	}

	// Silent
	function clearKeys() {
		$this->dbh->query("DELETE FROM ttrss_access_keys WHERE
			owner_uid = " . $_SESSION["uid"]);
	}


}
?>

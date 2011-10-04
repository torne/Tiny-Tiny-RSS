<?php
	function module_popup_dialog($link) {
		$id = $_REQUEST["id"];
		$param = db_escape_string($_REQUEST["param"]);

		print "<dlg id=\"$id\">";

		if ($id == "importOpml") {
			print "<div class=\"prefFeedOPMLHolder\">";
			header("Content-Type: text/html"); # required for iframe

			$owner_uid = $_SESSION["uid"];

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
				print "<ul class='nomarks'>";
				print "<li>".__("Importing using DOMXML.")."</li>";
				require_once "opml_domxml.php";
				opml_import_domxml($link, $owner_uid);
				print "</ul>";
			} else if (PHP_VERSION >= 5) {
				print "<ul class='nomarks'>";
				print "<li>".__("Importing using DOMDocument.")."</li>";
				require_once "opml_domdoc.php";
				opml_import_domdoc($link, $owner_uid);
				print "</ul>";
			} else {
				print_error(__("DOMXML extension is not found. It is required for PHP versions below 5."));
			}

			print "</div>";

			print "<div align='center'>";
			print "<button dojoType=\"dijit.form.Button\"
				onclick=\"dijit.byId('opmlImportDlg').hide()\">".
				__('Close this window')."</button>";
			print "</div>";

			print "</div>";

			//return;
		}

		if ($id == "editPrefProfiles") {

			print "<div dojoType=\"dijit.Toolbar\">";

#			TODO: depends on selectTableRows() being broken for this list
#			print "<div dojoType=\"dijit.form.DropDownButton\">".
#				"<span>" . __('Select')."</span>";
#			print "<div dojoType=\"dijit.Menu\" style=\"display: none;\">";
#			print "<div onclick=\"selectTableRows('prefFeedProfileList', 'all')\"
#				dojoType=\"dijit.MenuItem\">".__('All')."</div>";
#			print "<div onclick=\"selectTableRows('prefFeedProfileList', 'none')\"
#				dojoType=\"dijit.MenuItem\">".__('None')."</div>";
#			print "</div></div>";

#			print "<div style='float : right'>";
			print "<input name=\"newprofile\" dojoType=\"dijit.form.ValidationTextBox\"
					required=\"1\">
				<button dojoType=\"dijit.form.Button\"
				onclick=\"dijit.byId('profileEditDlg').addProfile()\">".
					__('Create profile')."</button></div>";

#			print "</div>";


			$result = db_query($link, "SELECT title,id FROM ttrss_settings_profiles
				WHERE owner_uid = ".$_SESSION["uid"]." ORDER BY title");

			print "<div class=\"prefFeedCatHolder\">";

			print "<form id=\"profile_edit_form\" onsubmit=\"return false\">";

			print "<table width=\"100%\" class=\"prefFeedProfileList\"
				cellspacing=\"0\" id=\"prefFeedProfileList\">";

			print "<tr class=\"\" id=\"FCATR-0\">"; #odd

			print "<td width='5%' align='center'><input
				onclick='toggleSelectRow2(this);'
				dojoType=\"dijit.form.CheckBox\"
				type=\"checkbox\"></td>";

			if (!$_SESSION["profile"]) {
				$is_active = __("(active)");
			} else {
				$is_active = "";
			}

			print "<td><span>" .
				__("Default profile") . " $is_active</span></td>";

			print "</tr>";

			$lnum = 1;

			while ($line = db_fetch_assoc($result)) {

				$class = ($lnum % 2) ? "even" : "odd";

				$profile_id = $line["id"];
				$this_row_id = "id=\"FCATR-$profile_id\"";

				print "<tr class=\"\" $this_row_id>";

				$edit_title = htmlspecialchars($line["title"]);

				print "<td width='5%' align='center'><input
					onclick='toggleSelectRow2(this);'
					dojoType=\"dijit.form.CheckBox\"
					type=\"checkbox\"></td>";

				if ($_SESSION["profile"] == $line["id"]) {
					$is_active = __("(active)");
				} else {
					$is_active = "";
				}

				print "<td><span dojoType=\"dijit.InlineEditBox\"
					width=\"300px\" autoSave=\"false\"
					profile-id=\"$profile_id\">" . $edit_title .
					"<script type=\"dojo/method\" event=\"onChange\" args=\"item\">
						var elem = this;
						dojo.xhrPost({
							url: 'backend.php',
							content: {op: 'rpc', subop: 'saveprofile',
								value: this.value,
								id: this.srcNodeRef.getAttribute('profile-id')},
								load: function(response) {
									elem.attr('value', response);
							}
						});
					</script>
				</span> $is_active</td>";

				print "</tr>";

				++$lnum;
			}

			print "</table>";
			print "</form>";
			print "</div>";

			print "<div class='dlgButtons'>
				<div style='float : left'>
				<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('profileEditDlg').removeSelected()\">".
				__('Remove selected profiles')."</button>
				<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('profileEditDlg').activateProfile()\">".
				__('Activate profile')."</button>
				</div>";

			print "<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('profileEditDlg').hide()\">".
				__('Close this window')."</button>";
			print "</div>";

		}

		if ($id == "pubOPMLUrl") {

			print "<title>".__('Public OPML URL')."</title>";
			print "<content><![CDATA[";

			$url_path = opml_publish_url($link);

			print __("Your Public OPML URL is:");

			print "<div class=\"tagCloudContainer\">";
			print "<a id='pub_opml_url' href='$url_path' target='_blank'>$url_path</a>";
			print "</div>";

			print "<div align='center'>";

			print "<button dojoType=\"dijit.form.Button\" onclick=\"return opmlRegenKey()\">".
				__('Generate new URL')."</button> ";

			print "<button dojoType=\"dijit.form.Button\" onclick=\"return closeInfoBox()\">".
				__('Close this window')."</button>";

			print "</div>";
			print "]]></content>";

			//return;
		}

		if ($id == "explainError") {

			print "<title>".__('Notice')."</title>";
			print "<content><![CDATA[";

			print "<div class=\"errorExplained\">";

			if ($param == 1) {
				print __("Update daemon is enabled in configuration, but daemon process is not running, which prevents all feeds from updating. Please start the daemon process or contact instance owner.");

				$stamp = (int) file_get_contents(LOCK_DIRECTORY . "/update_daemon.stamp");

				print "<p>" . __("Last update:") . " " . date("Y.m.d, G:i", $stamp);

			}

			if ($param == 3) {
				print __("Update daemon is taking too long to perform a feed update. This could indicate a problem like crash or a hang. Please check the daemon process or contact instance owner.");

				$stamp = (int) file_get_contents(LOCK_DIRECTORY . "/update_daemon.stamp");

				print "<p>" . __("Last update:") . " " . date("Y.m.d, G:i", $stamp);

			}

			print "</div>";

			print "<div align='center'>";

			print "<button onclick=\"return closeInfoBox()\">".
				__('Close this window')."</button>";

			print "</div>";
			print "]]></content>";

			//return;
		}

		if ($id == "quickAddFeed") {

			print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"rpc\">";
			print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"subop\" value=\"addfeed\">";

			print "<div class=\"dlgSec\">".__("Feed")."</div>";
			print "<div class=\"dlgSecCont\">";

			print "<input style=\"font-size : 16px; width : 20em;\"
				placeHolder=\"".__("Feed URL")."\"
				dojoType=\"dijit.form.ValidationTextBox\" required=\"1\" name=\"feed\" id=\"feedDlg_feedUrl\">";

			print "<hr/>";

			if (get_pref($link, 'ENABLE_FEED_CATS')) {
				print __('Place in category:') . " ";
				print_feed_cat_select($link, "cat", false, 'dojoType="dijit.form.Select"');
			}

			print "</div>";

			print '<div id="feedDlg_feedsContainer" style="display : none">

					<div class="dlgSec">' . __('Available feeds') . '</div>
					<div class="dlgSecCont">'.
					'<select id="feedDlg_feedContainerSelect"
						dojoType="dijit.form.Select" size="3">
						<script type="dojo/method" event="onChange" args="value">
							dijit.byId("feedDlg_feedUrl").attr("value", value);
						</script>
					</select>'.
					'</div></div>';

			print "<div id='feedDlg_loginContainer' style='display : none'>

					<div class=\"dlgSec\">".__("Authentication")."</div>
					<div class=\"dlgSecCont\">".

					" <input dojoType=\"dijit.form.TextBox\" name='login'\"
						placeHolder=\"".__("Login")."\"
						style=\"width : 10em;\"> ".
					" <input
						placeHolder=\"".__("Password")."\"
						dojoType=\"dijit.form.TextBox\" type='password'
						style=\"width : 10em;\" name='pass'\">
				</div></div>";


			print "<div style=\"clear : both\">
				<input type=\"checkbox\" dojoType=\"dijit.form.CheckBox\" id=\"feedDlg_loginCheck\"
						onclick='checkboxToggleElement(this, \"feedDlg_loginContainer\")'>
					<label for=\"feedDlg_loginCheck\">".
					__('This feed requires authentication.')."</div>";

			print "</form>";

			print "<div class=\"dlgButtons\">
				<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('feedAddDlg').execute()\">".__('Subscribe')."</button>
				<button dojoType=\"dijit.form.Button\" onclick=\"return feedBrowser()\">".__('More feeds')."</button>
				<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('feedAddDlg').hide()\">".__('Cancel')."</button>
				</div>";

			//return;
		}

		if ($id == "feedBrowser") {

			$browser_search = db_escape_string($_REQUEST["search"]);

#			print "<form onsubmit='return false;' display='inline'
#				name='feed_browser' id='feed_browser'>";

			print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"rpc\">";
			print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"subop\" value=\"updateFeedBrowser\">";

			print "<div dojoType=\"dijit.Toolbar\">
				<div style='float : right'>
				<img style='display : none'
					id='feed_browser_spinner' src='".
					theme_image($link, 'images/indicator_white.gif')."'>
				<input name=\"search\" dojoType=\"dijit.form.TextBox\" size=\"20\" type=\"search\"
					onchange=\"dijit.byId('feedBrowserDlg').update()\" value=\"$browser_search\">
				<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('feedBrowserDlg').update()\">".__('Search')."</button>
			</div>";

			print " <select name=\"mode\" dojoType=\"dijit.form.Select\" onchange=\"dijit.byId('feedBrowserDlg').update()\">
				<option value='1'>" . __('Popular feeds') . "</option>
				<option value='2'>" . __('Feed archive') . "</option>
				</select> ";

			print __("limit:");

			print " <select dojoType=\"dijit.form.Select\" name=\"limit\" onchange=\"dijit.byId('feedBrowserDlg').update()\">";

			foreach (array(25, 50, 100, 200) as $l) {
				$issel = ($l == $limit) ? "selected=\"1\"" : "";
				print "<option $issel value=\"$l\">$l</option>";
			}

			print "</select> ";

			print "</div>";

			$owner_uid = $_SESSION["uid"];

			print "<ul class='browseFeedList' id='browseFeedList'>";
			print make_feed_browser($link, $search, 25);
			print "</ul>";

			print "<div align='center'>
				<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('feedBrowserDlg').execute()\">".__('Subscribe')."</button>
				<button dojoType=\"dijit.form.Button\" style='display : none' id='feed_archive_remove' onclick=\"dijit.byId('feedBrowserDlg').removeFromArchive()\">".__('Remove')."</button>
				<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('feedBrowserDlg').hide()\" >".__('Cancel')."</button></div>";

		}

		if ($id == "search") {

			$params = explode(":", db_escape_string($_REQUEST["param"]), 2);

			$active_feed_id = sprintf("%d", $params[0]);
			$is_cat = $params[1] != "false";

			print "<div class=\"dlgSec\">".__('Look for')."</div>";

			print "<div class=\"dlgSecCont\">";

			if (!SPHINX_ENABLED) {

				print "<input dojoType=\"dijit.form.ValidationTextBox\"
					style=\"font-size : 16px; width : 12em;\"
					required=\"1\" name=\"query\" type=\"search\" value=''>";

				print " " . __('match on')." ";

				$search_fields = array(
					"title" => __("Title"),
						"content" => __("Content"),
					"both" => __("Title or content"));

				print_select_hash("match_on", 3, $search_fields,
					'dojoType="dijit.form.Select"');
			} else {
				print "<input dojoType=\"dijit.form.ValidationTextBox\"
					style=\"font-size : 16px; width : 20em;\"
					required=\"1\" name=\"query\" type=\"search\" value=''>";
			}


			print "<hr/>".__('Limit search to:')." ";

			print "<select name=\"search_mode\" dojoType=\"dijit.form.Select\">
				<option value=\"all_feeds\">".__('All feeds')."</option>";

			$feed_title = getFeedTitle($link, $active_feed_id);

			if (!$is_cat) {
				$feed_cat_title = getFeedCatTitle($link, $active_feed_id);
			} else {
				$feed_cat_title = getCategoryTitle($link, $active_feed_id);
			}

			if ($active_feed_id && !$is_cat) {
				print "<option selected=\"1\" value=\"this_feed\">$feed_title</option>";
			} else {
				print "<option disabled=\"1\" value=\"false\">".__('This feed')."</option>";
			}

			if ($is_cat) {
			  	$cat_preselected = "selected=\"1\"";
			}

			if (get_pref($link, 'ENABLE_FEED_CATS') && ($active_feed_id > 0 || $is_cat)) {
				print "<option $cat_preselected value=\"this_cat\">$feed_cat_title</option>";
			} else {
				//print "<option disabled>".__('This category')."</option>";
			}

			print "</select>";

			print "</div>";

			print "<div class=\"dlgButtons\">";

			if (!SPHINX_ENABLED) {
				print "<div style=\"float : left\">
					<a class=\"visibleLink\" target=\"_blank\" href=\"http://tt-rss.org/redmine/wiki/tt-rss/SearchSyntax\">Search syntax</a>
					</div>";
			}

			print "<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('searchDlg').execute()\">".__('Search')."</button>
			<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('searchDlg').hide()\">".__('Cancel')."</button>
			</div>";
		}

		if ($id == "quickAddFilter") {

			$active_feed_id = db_escape_string($_REQUEST["param"]);

			print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"pref-filters\">";
			print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"quiet\" value=\"1\">";
			print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"subop\" value=\"add\">";

			$result = db_query($link, "SELECT id,description
				FROM ttrss_filter_types ORDER BY description");

			$filter_types = array();

			while ($line = db_fetch_assoc($result)) {
				//array_push($filter_types, $line["description"]);
				$filter_types[$line["id"]] = __($line["description"]);
			}

			print "<div class=\"dlgSec\">".__("Match")."</div>";

			print "<div class=\"dlgSecCont\">";

			print "<span id=\"filterDlg_dateModBox\" style=\"display : none\">";

			$filter_params = array(
				"before" => __("before"),
				"after" => __("after"));

			print_select_hash("filter_date_modifier", "before",
				$filter_params, 'dojoType="dijit.form.Select"');

			print "&nbsp;</span>";

			print "<input dojoType=\"dijit.form.ValidationTextBox\"
				 required=\"true\" id=\"filterDlg_regExp\"
				 style=\"font-size : 16px\"
				 name=\"reg_exp\" value=\"$reg_exp\"/>";

			print "<span id=\"filterDlg_dateChkBox\" style=\"display : none\">";
			print "&nbsp;<button dojoType=\"dijit.form.Button\"
				onclick=\"return filterDlgCheckDate()\">".
				__('Check it')."</button>";
			print "</span>";

			print "<hr/>" .  __("on field") . " ";
			print_select_hash("filter_type", 1, $filter_types,
				'onchange="filterDlgCheckType(this)" dojoType="dijit.form.Select"');

			print "<hr/>";

			print __("in") . " ";
			print_feed_select($link, "feed_id", $active_feed_id,
				'dojoType="dijit.form.FilteringSelect"');

			print "</div>";

			print "<div class=\"dlgSec\">".__("Perform Action")."</div>";

			print "<div class=\"dlgSecCont\">";

			print "<select name=\"action_id\" dojoType=\"dijit.form.Select\"
				onchange=\"filterDlgCheckAction(this)\">";

			$result = db_query($link, "SELECT id,description FROM ttrss_filter_actions
				ORDER BY name");

			while ($line = db_fetch_assoc($result)) {
				printf("<option value='%d'>%s</option>", $line["id"], __($line["description"]));
			}

			print "</select>";

			print "<span id=\"filterDlg_paramBox\" style=\"display : none\">";
			print " " . __("with parameters:") . " ";
			print "<input dojoType=\"dijit.form.TextBox\"
				id=\"filterDlg_actionParam\"
				name=\"action_param\">";

			print_label_select($link, "action_param_label", $action_param,
			 'id="filterDlg_actionParamLabel" dojoType="dijit.form.Select"');

			print "</span>";

			print "&nbsp;"; // tiny layout hack

			print "</div>";

			print "<div class=\"dlgSec\">".__("Options")."</div>";
			print "<div class=\"dlgSecCont\">";

			print "<input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" name=\"enabled\" id=\"enabled\" checked=\"1\">
					<label for=\"enabled\">".__('Enabled')."</label><hr/>";

			print "<input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" name=\"inverse\" id=\"inverse\">
				<label for=\"inverse\">".__('Inverse match')."</label>";

			print "</div>";

			print "<div class=\"dlgButtons\">";

			print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').test()\">".
				__('Test')."</button> ";

			print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').execute()\">".
				__('Create')."</button> ";

			print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').hide()\">".
				__('Cancel')."</button>";

			print "</div>";

			//return;
		}

		if ($id == "inactiveFeeds") {

			if (DB_TYPE == "pgsql") {
				$interval_qpart = "NOW() - INTERVAL '3 months'";
			} else {
				$interval_qpart = "DATE_SUB(NOW(), INTERVAL 3 MONTH)";
			}

			$result = db_query($link, "SELECT ttrss_feeds.title, ttrss_feeds.site_url,
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

			print __("These feeds have not been updated with new content for 3 months (oldest first):");

			print "<div class=\"inactiveFeedHolder\">";

			print "<table width=\"100%\" cellspacing=\"0\" id=\"prefInactiveFeedList\">";

			$lnum = 1;

			while ($line = db_fetch_assoc($result)) {

				$class = ($lnum % 2) ? "even" : "odd";
				$feed_id = $line["id"];
				$this_row_id = "id=\"FUPDD-$feed_id\"";

				print "<tr class=\"\" $this_row_id>";

				$edit_title = htmlspecialchars($line["title"]);

				print "<td width='5%' align='center'><input
					onclick='toggleSelectRow2(this);' dojoType=\"dijit.form.CheckBox\"
					type=\"checkbox\"></td>";
				print "<td>";

				print "<a class=\"visibleLink\" href=\"#\" ".
					"title=\"".__("Click to edit feed")."\" ".
					"onclick=\"editFeed(".$line["id"].")\">".
					htmlspecialchars($line["title"])."</a>";

				print "</td><td class=\"insensitive\" align='right'>";
				print make_local_datetime($link, $line['last_article'], false);
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

		if ($id == "feedsWithErrors") {

#			print "<title>".__('Feeds with update errors')."</title>";
#			print "<content><![CDATA[";

			print __("These feeds have not been updated because of errors:");

			$result = db_query($link, "SELECT id,title,feed_url,last_error,site_url
			FROM ttrss_feeds WHERE last_error != '' AND owner_uid = ".$_SESSION["uid"]);

			print "<div class=\"inactiveFeedHolder\">";

			print "<table width=\"100%\" cellspacing=\"0\" id=\"prefErrorFeedList\">";

			$lnum = 1;

			while ($line = db_fetch_assoc($result)) {

				$class = ($lnum % 2) ? "even" : "odd";
				$feed_id = $line["id"];
				$this_row_id = "id=\"FUPDD-$feed_id\"";

				print "<tr class=\"\" $this_row_id>";

				$edit_title = htmlspecialchars($line["title"]);

				print "<td width='5%' align='center'><input
					onclick='toggleSelectRow2(this);' dojoType=\"dijit.form.CheckBox\"
					type=\"checkbox\"></td>";
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

		if ($id == "editArticleTags") {

#			print "<form id=\"tag_edit_form\" onsubmit='return false'>";

			print __("Tags for this article (separated by commas):")."<br>";

			$tags = get_article_tags($link, $param);

			$tags_str = join(", ", $tags);

			print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"id\" value=\"$param\">";
			print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"rpc\">";
			print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"subop\" value=\"setArticleTags\">";

			print "<table width='100%'><tr><td>";

			print "<textarea dojoType=\"dijit.form.SimpleTextarea\" rows='4'
				style='font-size : 12px; width : 100%' id=\"tags_str\"
				name='tags_str'>$tags_str</textarea>
			<div class=\"autocomplete\" id=\"tags_choices\"
					style=\"display:none\"></div>";

			print "</td></tr></table>";

#			print "</form>";

			print "<div class='dlgButtons'>";

			print "<button dojoType=\"dijit.form.Button\"
				onclick=\"dijit.byId('editTagsDlg').execute()\">".__('Save')."</button> ";
			print "<button dojoType=\"dijit.form.Button\"
				onclick=\"dijit.byId('editTagsDlg').hide()\">".__('Cancel')."</button>";
			print "</div>";

		}

		if ($id == "printTagCloud") {
			print "<title>".__('Tag Cloud')."</title>";
			print "<content><![CDATA[";

#			print __("Showing most popular tags ")." (<a
#			href='javascript:toggleTags(true)'>".__('more tags')."</a>):<br/>";

			print "<div class=\"tagCloudContainer\">";

			printTagCloud($link);

			print "</div>";

			print "<div align='center'>";
			print "<button dojoType=\"dijit.form.Button\"
				onclick=\"return closeInfoBox()\">".
				__('Close this window')."</button>";
			print "</div>";

			print "]]></content>";
		}

		if ($id == 'printTagSelect') {
			print "<title>" . __('Select item(s) by tags') . "</title>";
			print "<content><![CDATA[";

			print __("Match:"). "&nbsp;" .
				  "<input class=\"noborder\" dojoType=\"dijit.form.RadioButton\" type=\"radio\" checked value=\"any\" name=\"tag_mode\">&nbsp;Any&nbsp;";
			print "<input class=\"noborder\" dojoType=\"dijit.form.RadioButton\" type=\"radio\" value=\"all\" name=\"tag_mode\">&nbsp;All&nbsp;";
			print "&nbsp;tags.";

			print "<select id=\"all_tags\" name=\"all_tags\" title=\"" . __('Which Tags?') . "\" multiple=\"multiple\" size=\"10\" style=\"width : 100%\">";
			$result = db_query($link, "SELECT DISTINCT tag_name FROM ttrss_tags WHERE owner_uid = ".$_SESSION['uid']."
				AND LENGTH(tag_name) <= 30 ORDER BY tag_name ASC");

			while ($row = db_fetch_assoc($result)) {
				$tmp = htmlspecialchars($row["tag_name"]);
				print "<option value=\"" . str_replace(" ", "%20", $tmp) . "\">$tmp</option>";
			}

			print "</select>";

			print "<div align='right'>";
			print "<button dojoType=\"dijit.form.Button\" onclick=\"viewfeed(get_all_tags($('all_tags')),
				get_radio_checked($('tag_mode')));\">" . __('Display entries') . "</button>";
			print "&nbsp;";
			print "<button dojoType=\"dijit.form.Button\"
			onclick=\"return closeInfoBox()\">" .
				__('Close this window') . "</button>";
			print "</div>";

			print "]]></content>";
		}

		if ($id == "emailArticle") {

			$secretkey = sha1(uniqid(rand(), true));

			$_SESSION['email_secretkey'] = $secretkey;

			print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"secretkey\" value=\"$secretkey\">";
			print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"rpc\">";
			print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"subop\" value=\"sendEmail\">";

			$result = db_query($link, "SELECT email, full_name FROM ttrss_users WHERE
				id = " . $_SESSION["uid"]);

			$user_email = htmlspecialchars(db_fetch_result($result, 0, "email"));
			$user_name = htmlspecialchars(db_fetch_result($result, 0, "full_name"));

			if (!$user_name) $user_name = $_SESSION['name'];

			$_SESSION['email_replyto'] = $user_email;
			$_SESSION['email_fromname'] = $user_name;

			require_once "lib/MiniTemplator.class.php";

			$tpl = new MiniTemplator;
			$tpl_t = new MiniTemplator;

			$tpl->readTemplateFromFile("templates/email_article_template.txt");

			$tpl->setVariable('USER_NAME', $_SESSION["name"]);
			$tpl->setVariable('USER_EMAIL', $user_email);
			$tpl->setVariable('TTRSS_HOST', $_SERVER["HTTP_HOST"]);

//			$tpl->addBlock('header');

			$result = db_query($link, "SELECT link, content, title
				FROM ttrss_user_entries, ttrss_entries WHERE id = ref_id AND
				id IN ($param) AND owner_uid = " . $_SESSION["uid"]);

			if (db_num_rows($result) > 1) {
				$subject = __("[Forwarded]") . " " . __("Multiple articles");
			}

			while ($line = db_fetch_assoc($result)) {

				if (!$subject)
					$subject = __("[Forwarded]") . " " . htmlspecialchars($line["title"]);

				$tpl->setVariable('ARTICLE_TITLE', strip_tags($line["title"]));
				$tpl->setVariable('ARTICLE_URL', strip_tags($line["link"]));

				$tpl->addBlock('article');
			}

			$tpl->addBlock('email');

			$content = "";
			$tpl->generateOutputToString($content);

			print "<table width='100%'><tr><td>";

			print __('From:');

			print "</td><td>";

			print "<input dojoType=\"dijit.form.TextBox\" disabled=\"1\" style=\"width : 30em;\"
					value=\"$user_name <$user_email>\">";

			print "</td></tr><tr><td>";

			print __('To:');

			print "</td><td>";

			print "<input dojoType=\"dijit.form.ValidationTextBox\" required=\"true\"
					style=\"width : 30em;\"
					name=\"destination\" id=\"emailArticleDlg_destination\">";

			print "<div class=\"autocomplete\" id=\"emailArticleDlg_dst_choices\"
					style=\"z-index: 30; display : none\"></div>";

			print "</td></tr><tr><td>";

			print __('Subject:');

			print "</td><td>";

			print "<input dojoType=\"dijit.form.ValidationTextBox\" required=\"true\"
					style=\"width : 30em;\"
					name=\"subject\" value=\"$subject\" id=\"subject\">";

			print "</td></tr>";

			print "<tr><td colspan='2'><textarea dojoType=\"dijit.form.SimpleTextarea\" style='font-size : 12px; width : 100%' rows=\"20\"
				name='content'>$content</textarea>";

			print "</td></tr></table>";

			print "<div class='dlgButtons'>";
			print "<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('emailArticleDlg').execute()\">".__('Send e-mail')."</button> ";
			print "<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('emailArticleDlg').hide()\">".__('Cancel')."</button>";
			print "</div>";

			//return;
		}

		if ($id == "generatedFeed") {

			print "<title>".__('View as RSS')."</title>";
			print "<content><![CDATA[";

			$params = explode(":", $param, 3);
			$feed_id = db_escape_string($params[0]);
			$is_cat = (bool) $params[1];

			$key = get_feed_access_key($link, $feed_id, $is_cat);

			$url_path = htmlspecialchars($params[2]) . "&key=" . $key;

			print __("You can view this feed as RSS using the following URL:");

			print "<div class=\"tagCloudContainer\">";
			print "<a id='gen_feed_url' href='$url_path' target='_blank'>$url_path</a>";
			print "</div>";

			print "<div align='center'>";

			print "<button dojoType=\"dijit.form.Button\" onclick=\"return genUrlChangeKey('$feed_id', '$is_cat')\">".
				__('Generate new URL')."</button> ";

			print "<button dojoType=\"dijit.form.Button\" onclick=\"return closeInfoBox()\">".
				__('Close this window')."</button>";

			print "</div>";
			print "]]></content>";

			//return;
		}

		if ($id == "newVersion") {
			$version_data = check_for_update($link);
			$version = $version_data['version'];
			$id = $version_data['version_id'];

			print "<div class='tagCloudContainer'>";

			print T_sprintf("New version of Tiny Tiny RSS is available (%s).",
				"<b>$version</b>");

			print "</div>";

			$details = "http://tt-rss.org/redmine/versions/show/$id";
			$download = "http://tt-rss.org/#Download";

			print "<div style='text-align : center'>";
			print "<button dojoType=\"dijit.form.Button\"
				onclick=\"return window.open('$details')\">".__("Details")."</button>";
			print "<button dojoType=\"dijit.form.Button\"
				onclick=\"return window.open('$download')\">".__("Download")."</button>";
			print "<button dojoType=\"dijit.form.Button\"
				onclick=\"return dijit.byId('newVersionDlg').hide()\">".
				__('Close this window')."</button>";
			print "</div>";

		}

		if ($id == "customizeCSS") {

			$value = get_pref($link, "USER_STYLESHEET");

			$value = str_replace("<br/>", "\n", $value);

			print T_sprintf("You can override colors, fonts and layout of your currently selected theme with custom CSS declarations here. <a target=\"_blank\" class=\"visibleLink\" href=\"%s\">This file</a> can be used as a baseline.", "tt-rss.css");

			print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"rpc\">";
			print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"subop\" value=\"setpref\">";
			print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"key\" value=\"USER_STYLESHEET\">";

			print "<table width='100%'><tr><td>";
			print "<textarea dojoType=\"dijit.form.SimpleTextarea\"
				style='font-size : 12px; width : 100%; height: 200px;'
				placeHolder='body#ttrssMain { font-size : 14px; };'
				name='value'>$value</textarea>";
			print "</td></tr></table>";

			print "<div class='dlgButtons'>";
			print "<button dojoType=\"dijit.form.Button\"
				onclick=\"dijit.byId('cssEditDlg').execute()\">".__('Save')."</button> ";
			print "<button dojoType=\"dijit.form.Button\"
				onclick=\"dijit.byId('cssEditDlg').hide()\">".__('Cancel')."</button>";
			print "</div>";

		}

		if ($id == "editArticleNote") {

			$result = db_query($link, "SELECT note FROM ttrss_user_entries WHERE
				ref_id = '$param' AND owner_uid = " . $_SESSION['uid']);

			$note = db_fetch_result($result, 0, "note");

			print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"id\" value=\"$param\">";
			print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"rpc\">";
			print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"subop\" value=\"setNote\">";

			print "<table width='100%'><tr><td>";
			print "<textarea dojoType=\"dijit.form.SimpleTextarea\"
				style='font-size : 12px; width : 100%; height: 100px;'
				placeHolder='body#ttrssMain { font-size : 14px; };'
				name='note'>$note</textarea>";
			print "</td></tr></table>";

			print "<div class='dlgButtons'>";
			print "<button dojoType=\"dijit.form.Button\"
				onclick=\"dijit.byId('editNoteDlg').execute()\">".__('Save')."</button> ";
			print "<button dojoType=\"dijit.form.Button\"
				onclick=\"dijit.byId('editNoteDlg').hide()\">".__('Cancel')."</button>";
			print "</div>";

		}

		if ($id == "about") {
			print "<table width='100%'><tr><td align='center'>";
			print "<img src=\"images/logo_big.png\">";
			print "</td>";
			print "<td width='70%'>";

			print "<h1>Tiny Riny RSS</h1>
				<strong>Version ".VERSION."</strong>
				<p>Copyright &copy; 2005-".date('Y')."
				<a target=\"_blank\" class=\"visibleLink\"
				href=\"http://fakecake.org/\">Andrew Dolgov</a>
				and other contributors.</p>
				<p class=\"insensitive\">Licensed under GNU GPL version 2.</p>";

			print "<p class=\"insensitive\">
				<a class=\"visibleLink\" target=\"_blank\"
					href=\"http://tt-rss.org/\">Official site</a> &mdash;
				<a href=\"http://tt-rss.org/redmine/wiki/tt-rss/Donate\"
				target=\"_blank\" class=\"visibleLink\">
				Support the project.</a></p>";

			print "</td></tr>";
			print "</table>";

			print "<div align='center'>";
			print "<button dojoType=\"dijit.form.Button\"
				type=\"submit\">".
				__('Close this window')."</button>";
			print "</div>";
		}

		if ($id == "addInstance") {

			print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\"  name=\"op\" value=\"pref-instances\">";
			print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\"  name=\"subop\" value=\"add\">";

			print "<div class=\"dlgSec\">".__("Instance")."</div>";

			print "<div class=\"dlgSecCont\">";

			/* URL */

			print __("URL:") . " ";

			print "<input dojoType=\"dijit.form.ValidationTextBox\" required=\"1\"
				placeHolder=\"".__("Instance URL")."\"
				regExp='^(http|https)://.*'
				style=\"font-size : 16px; width: 20em\" name=\"access_url\">";

			print "<hr/>";

			$access_key = sha1(uniqid(rand(), true));

			/* Access key */

			print __("Access key:") . " ";

			print "<input dojoType=\"dijit.form.ValidationTextBox\" required=\"1\"
				placeHolder=\"".__("Access key")."\" regExp='\w{40}'
				style=\"width: 20em\" name=\"access_key\" id=\"instance_add_key\"
				value=\"$access_key\">";

			print "<p class='insensitive'>" . __("Use one access key for both linked instances.");

			print "</div>";

			print "<div class=\"dlgButtons\">
				<div style='float : left'>
					<button dojoType=\"dijit.form.Button\"
						onclick=\"return dijit.byId('instanceAddDlg').regenKey()\">".
						__('Generate new key')."</button>
				</div>
				<button dojoType=\"dijit.form.Button\"
					onclick=\"return dijit.byId('instanceAddDlg').execute()\">".
					__('Create link')."</button>
				<button dojoType=\"dijit.form.Button\"
					onclick=\"return dijit.byId('instanceAddDlg').hide()\"\">".
					__('Cancel')."</button></div>";

			return;
		}

		if ($id == "shareArticle") {

			$result = db_query($link, "SELECT uuid, ref_id FROM ttrss_user_entries WHERE int_id = '$param'
				AND owner_uid = " . $_SESSION['uid']);

			if (db_num_rows($result) == 0) {
				print "Article not found.";
			} else {

				$uuid = db_fetch_result($result, 0, "uuid");
				$ref_id = db_fetch_result($result, 0, "ref_id");

				if (!$uuid) {
					$uuid = db_escape_string(sha1(uniqid(rand(), true)));
					db_query($link, "UPDATE ttrss_user_entries SET uuid = '$uuid' WHERE int_id = '$param'
						AND owner_uid = " . $_SESSION['uid']);
				}

				print __("You can share this article by the following unique URL:");

				$url_path = get_self_url_prefix();
				$url_path .= "/backend.php?op=share&key=$uuid";

				print "<div class=\"tagCloudContainer\">";
				print "<a id='pub_opml_url' href='$url_path' target='_blank'>$url_path</a>";
				print "</div>";

				/* if (!label_find_id($link, __('Shared'), $_SESSION["uid"]))
					label_create($link, __('Shared'), $_SESSION["uid"]);

				label_add_article($link, $ref_id, __('Shared'), $_SESSION['uid']); */
			}

			print "<div align='center'>";

			print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('shareArticleDlg').hide()\">".
				__('Close this window')."</button>";

			print "</div>";

			return;
		}

		print "</dlg>";

	}
?>

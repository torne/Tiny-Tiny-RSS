<?php
class Dlg extends Handler_Protected {
	private $param;

	function before($method) {
		if (parent::before($method)) {
			header("Content-Type: text/xml; charset=utf-8");
			$this->param = db_escape_string($_REQUEST["param"]);
			print "<dlg>";
			return true;
		}
		return false;
	}

	function after() {
		print "</dlg>";
	}

	function importOpml() {
		header("Content-Type: text/html"); # required for iframe

		print __("If you have imported labels and/or filters, you might need to reload preferences to see your new data.") . "</p>";

		print "<div class=\"prefFeedOPMLHolder\">";
		$owner_uid = $_SESSION["uid"];

		db_query($this->link, "BEGIN");

		print "<ul class='nomarks'>";

		$opml = new Opml($this->link, $_REQUEST);

		$opml->opml_import($_SESSION["uid"]);

		db_query($this->link, "COMMIT");

		print "</ul>";
		print "</div>";

		print "<div align='center'>";
		print "<button dojoType=\"dijit.form.Button\"
			onclick=\"dijit.byId('opmlImportDlg').execute()\">".
			__('Close this window')."</button>";
		print "</div>";

		print "</div>";

		//return;
	}

	function editPrefProfiles() {
		print "<div dojoType=\"dijit.Toolbar\">";

		print "<div dojoType=\"dijit.form.DropDownButton\">".
				"<span>" . __('Select')."</span>";
		print "<div dojoType=\"dijit.Menu\" style=\"display: none;\">";
		print "<div onclick=\"selectTableRows('prefFeedProfileList', 'all')\"
			dojoType=\"dijit.MenuItem\">".__('All')."</div>";
		print "<div onclick=\"selectTableRows('prefFeedProfileList', 'none')\"
			dojoType=\"dijit.MenuItem\">".__('None')."</div>";
		print "</div></div>";

		print "<div style=\"float : right\">";

		print "<input name=\"newprofile\" dojoType=\"dijit.form.ValidationTextBox\"
				required=\"1\">
			<button dojoType=\"dijit.form.Button\"
			onclick=\"dijit.byId('profileEditDlg').addProfile()\">".
				__('Create profile')."</button></div>";

		print "</div>";

		$result = db_query($this->link, "SELECT title,id FROM ttrss_settings_profiles
			WHERE owner_uid = ".$_SESSION["uid"]." ORDER BY title");

		print "<div class=\"prefFeedCatHolder\">";

		print "<form id=\"profile_edit_form\" onsubmit=\"return false\">";

		print "<table width=\"100%\" class=\"prefFeedProfileList\"
			cellspacing=\"0\" id=\"prefFeedProfileList\">";

		print "<tr class=\"placeholder\" id=\"FCATR-0\">"; #odd

		print "<td width='5%' align='center'><input
			id='FCATC-0'
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

			print "<tr class=\"placeholder\" $this_row_id>";

			$edit_title = htmlspecialchars($line["title"]);

			print "<td width='5%' align='center'><input
				onclick='toggleSelectRow2(this);'
				id='FCATC-$profile_id'
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
						content: {op: 'rpc', method: 'saveprofile',
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

	function pubOPMLUrl() {
		print "<title>".__('Public OPML URL')."</title>";
		print "<content><![CDATA[";

		$url_path = opml_publish_url($this->link);

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

	function explainError() {
		print "<title>".__('Notice')."</title>";
		print "<content><![CDATA[";

		print "<div class=\"errorExplained\">";

		if ($this->param == 1) {
			print __("Update daemon is enabled in configuration, but daemon process is not running, which prevents all feeds from updating. Please start the daemon process or contact instance owner.");

			$stamp = (int) file_get_contents(LOCK_DIRECTORY . "/update_daemon.stamp");

			print "<p>" . __("Last update:") . " " . date("Y.m.d, G:i", $stamp);

		}

		if ($this->param == 3) {
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

	function quickAddFeed() {
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"rpc\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"method\" value=\"addfeed\">";

		print "<div class=\"dlgSec\">".__("Feed or site URL")."</div>";
		print "<div class=\"dlgSecCont\">";

		print "<div style='float : right'>
			<img style='display : none'
				id='feed_add_spinner' src='".
				theme_image($this->link, 'images/indicator_white.gif')."'></div>";

		print "<input style=\"font-size : 16px; width : 20em;\"
			placeHolder=\"".__("Feed or site URL")."\"
			dojoType=\"dijit.form.ValidationTextBox\" required=\"1\" name=\"feed\" id=\"feedDlg_feedUrl\">";

		print "<hr/>";

		if (get_pref($this->link, 'ENABLE_FEED_CATS')) {
			print __('Place in category:') . " ";
			print_feed_cat_select($this->link, "cat", false, 'dojoType="dijit.form.Select"');
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
			<input type=\"checkbox\" name=\"need_auth\" dojoType=\"dijit.form.CheckBox\" id=\"feedDlg_loginCheck\"
					onclick='checkboxToggleElement(this, \"feedDlg_loginContainer\")'>
				<label for=\"feedDlg_loginCheck\">".
				__('This feed requires authentication.')."</div>";

		print "</form>";

		print "<div class=\"dlgButtons\">
			<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('feedAddDlg').execute()\">".__('Subscribe')."</button>";

		if (!(defined('_DISABLE_FEED_BROWSER') && _DISABLE_FEED_BROWSER)) {
			print "<button dojoType=\"dijit.form.Button\" onclick=\"return feedBrowser()\">".__('More feeds')."</button>";
		}

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('feedAddDlg').hide()\">".__('Cancel')."</button>
			</div>";

		//return;
	}

	function feedBrowser() {
		if (defined('_DISABLE_FEED_BROWSER') && _DISABLE_FEED_BROWSER) return;

		$browser_search = db_escape_string($_REQUEST["search"]);

		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"rpc\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"method\" value=\"updateFeedBrowser\">";

		print "<div dojoType=\"dijit.Toolbar\">
			<div style='float : right'>
			<img style='display : none'
				id='feed_browser_spinner' src='".
				theme_image($this->link, 'images/indicator_white.gif')."'>
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

		require_once "feedbrowser.php";

		print "<ul class='browseFeedList' id='browseFeedList'>";
		print make_feed_browser($this->link, $search, 25);
		print "</ul>";

		print "<div align='center'>
			<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('feedBrowserDlg').execute()\">".__('Subscribe')."</button>
			<button dojoType=\"dijit.form.Button\" style='display : none' id='feed_archive_remove' onclick=\"dijit.byId('feedBrowserDlg').removeFromArchive()\">".__('Remove')."</button>
			<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('feedBrowserDlg').hide()\" >".__('Cancel')."</button></div>";

	}

	function search() {
		$this->params = explode(":", db_escape_string($_REQUEST["param"]), 2);

		$active_feed_id = sprintf("%d", $this->params[0]);
		$is_cat = $this->params[1] != "false";

		print "<div class=\"dlgSec\">".__('Look for')."</div>";

		print "<div class=\"dlgSecCont\">";

		print "<input dojoType=\"dijit.form.ValidationTextBox\"
			style=\"font-size : 16px; width : 20em;\"
			required=\"1\" name=\"query\" type=\"search\" value=''>";

		print "<hr/>".__('Limit search to:')." ";

		print "<select name=\"search_mode\" dojoType=\"dijit.form.Select\">
			<option value=\"all_feeds\">".__('All feeds')."</option>";

		$feed_title = getFeedTitle($this->link, $active_feed_id);

		if (!$is_cat) {
			$feed_cat_title = getFeedCatTitle($this->link, $active_feed_id);
		} else {
			$feed_cat_title = getCategoryTitle($this->link, $active_feed_id);
		}

		if ($active_feed_id && !$is_cat) {
			print "<option selected=\"1\" value=\"this_feed\">$feed_title</option>";
		} else {
			print "<option disabled=\"1\" value=\"false\">".__('This feed')."</option>";
		}

		if ($is_cat) {
		  	$cat_preselected = "selected=\"1\"";
		}

		if (get_pref($this->link, 'ENABLE_FEED_CATS') && ($active_feed_id > 0 || $is_cat)) {
			print "<option $cat_preselected value=\"this_cat\">$feed_cat_title</option>";
		} else {
			//print "<option disabled>".__('This category')."</option>";
		}

		print "</select>";

		print "</div>";

		print "<div class=\"dlgButtons\">";

		if (!SPHINX_ENABLED) {
			print "<div style=\"float : left\">
				<a class=\"visibleLink\" target=\"_blank\" href=\"http://tt-rss.org/wiki/SearchSyntax\">Search syntax</a>
				</div>";
		}

		print "<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('searchDlg').execute()\">".__('Search')."</button>
		<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('searchDlg').hide()\">".__('Cancel')."</button>
		</div>";
	}

	function editArticleTags() {

		print __("Tags for this article (separated by commas):")."<br>";

		$tags = get_article_tags($this->link, $this->param);

		$tags_str = join(", ", $tags);

		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"id\" value=\"$this->param\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"rpc\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"method\" value=\"setArticleTags\">";

		print "<table width='100%'><tr><td>";

		print "<textarea dojoType=\"dijit.form.SimpleTextarea\" rows='4'
			style='font-size : 12px; width : 100%' id=\"tags_str\"
			name='tags_str'>$tags_str</textarea>
		<div class=\"autocomplete\" id=\"tags_choices\"
				style=\"display:none\"></div>";

		print "</td></tr></table>";

		print "<div class='dlgButtons'>";

		print "<button dojoType=\"dijit.form.Button\"
			onclick=\"dijit.byId('editTagsDlg').execute()\">".__('Save')."</button> ";
		print "<button dojoType=\"dijit.form.Button\"
			onclick=\"dijit.byId('editTagsDlg').hide()\">".__('Cancel')."</button>";
		print "</div>";

	}

	function printTagCloud() {
		print "<title>".__('Tag Cloud')."</title>";
		print "<content><![CDATA[";

		print "<div class=\"tagCloudContainer\">";

		// from here: http://www.roscripts.com/Create_tag_cloud-71.html

		$query = "SELECT tag_name, COUNT(post_int_id) AS count
			FROM ttrss_tags WHERE owner_uid = ".$_SESSION["uid"]."
			GROUP BY tag_name ORDER BY count DESC LIMIT 50";

		$result = db_query($this->link, $query);

		$tags = array();

		while ($line = db_fetch_assoc($result)) {
			$tags[$line["tag_name"]] = $line["count"];
		}

        if( count($tags) == 0 ){ return; }

		ksort($tags);

		$max_size = 32; // max font size in pixels
		$min_size = 11; // min font size in pixels

		// largest and smallest array values
		$max_qty = max(array_values($tags));
		$min_qty = min(array_values($tags));

		// find the range of values
		$spread = $max_qty - $min_qty;
		if ($spread == 0) { // we don't want to divide by zero
				$spread = 1;
		}

		// set the font-size increment
		$step = ($max_size - $min_size) / ($spread);

		// loop through the tag array
		foreach ($tags as $key => $value) {
			// calculate font-size
			// find the $value in excess of $min_qty
			// multiply by the font-size increment ($size)
			// and add the $min_size set above
			$size = round($min_size + (($value - $min_qty) * $step));

			$key_escaped = str_replace("'", "\\'", $key);

			echo "<a href=\"javascript:viewfeed('$key_escaped') \" style=\"font-size: " .
				$size . "px\" title=\"$value articles tagged with " .
				$key . '">' . $key . '</a> ';
		}



		print "</div>";

		print "<div align='center'>";
		print "<button dojoType=\"dijit.form.Button\"
			onclick=\"return closeInfoBox()\">".
			__('Close this window')."</button>";
		print "</div>";

		print "]]></content>";
	}

	function printTagSelect() {

		print "<title>" . __('Select item(s) by tags') . "</title>";
		print "<content><![CDATA[";

		print __("Match:"). "&nbsp;" .
			"<input class=\"noborder\" dojoType=\"dijit.form.RadioButton\" type=\"radio\" checked value=\"any\" name=\"tag_mode\" id=\"tag_mode_any\">";
		print "<label for=\"tag_mode_any\">".__("Any")."</label>";
		print "&nbsp;";
		print "<input class=\"noborder\" dojoType=\"dijit.form.RadioButton\" type=\"radio\" value=\"all\" name=\"tag_mode\" id=\"tag_mode_all\">";
		print "<label for=\"tag_mode_all\">".__("All tags.")."</input>";

		print "<select id=\"all_tags\" name=\"all_tags\" title=\"" . __('Which Tags?') . "\" multiple=\"multiple\" size=\"10\" style=\"width : 100%\">";
		$result = db_query($this->link, "SELECT DISTINCT tag_name FROM ttrss_tags WHERE owner_uid = ".$_SESSION['uid']."
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

	function generatedFeed() {

		print "<title>".__('View as RSS')."</title>";
		print "<content><![CDATA[";

		$this->params = explode(":", $this->param, 3);
		$feed_id = db_escape_string($this->params[0]);
		$is_cat = (bool) $this->params[1];

		$key = get_feed_access_key($this->link, $feed_id, $is_cat);

		$url_path = htmlspecialchars($this->params[2]) . "&key=" . $key;

		print "<div class=\"dialogNotice\">" .	__("You can view this feed as RSS using the following URL:") . "</div>";

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

	function newVersion() {

		$version_data = check_for_update($this->link);
		$version = $version_data['version'];
		$id = $version_data['version_id'];

		print "<div class='tagCloudContainer'>";

		print T_sprintf("New version of Tiny Tiny RSS is available (%s).",
			"<b>$version</b>");

		print "</div>";

		$details = "http://tt-rss.org/redmine/versions/$id";
		$download = "http://tt-rss.org/#Download";

		print "<p align='center'>".__("You can update using built-in updater in the Preferences or by using update.php")."</p>";

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

	function customizeCSS() {
		$value = get_pref($this->link, "USER_STYLESHEET");

		$value = str_replace("<br/>", "\n", $value);

		print "<div class=\"dialogNotice\">";
		print T_sprintf("You can override colors, fonts and layout of your currently selected theme with custom CSS declarations here. <a target=\"_blank\" class=\"visibleLink\" href=\"%s\">This file</a> can be used as a baseline.", "tt-rss.css");
		print "</div>";

		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"rpc\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"method\" value=\"setpref\">";
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

	function addInstance() {
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\"  name=\"op\" value=\"pref-instances\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\"  name=\"method\" value=\"add\">";

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

	function batchSubscribe() {
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"rpc\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"method\" value=\"batchaddfeeds\">";

		print "<table width='100%'><tr><td>
			".__("Add one valid RSS feed per line (no feed detection is done)")."
		</td><td align='right'>";
		if (get_pref($this->link, 'ENABLE_FEED_CATS')) {
			print __('Place in category:') . " ";
			print_feed_cat_select($this->link, "cat", false, 'dojoType="dijit.form.Select"');
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

}
?>

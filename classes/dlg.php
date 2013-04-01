<?php
class Dlg extends Handler_Protected {
	private $param;

	function before($method) {
		if (parent::before($method)) {
			header("Content-Type: text/html"); # required for iframe

			$this->param = db_escape_string($this->link, $_REQUEST["param"]);
			return true;
		}
		return false;
	}

	function importOpml() {
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

		print "<div class=\"prefProfileHolder\">";

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
		$url_path = Opml::opml_publish_url($this->link);

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

		//return;
	}

	function explainError() {
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

		//return;
	}

	function printTagCloud() {
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

	}

	function printTagSelect() {

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

	}

	function generatedFeed() {

		$this->params = explode(":", $this->param, 3);
		$feed_id = db_escape_string($this->link, $this->params[0]);
		$is_cat = (bool) $this->params[1];

		$key = get_feed_access_key($this->link, $feed_id, $is_cat);

		$url_path = htmlspecialchars($this->params[2]) . "&key=" . $key;

		print "<h2>".__("You can view this feed as RSS using the following URL:")."</h2>";

		print "<div class=\"tagCloudContainer\">";
		print "<a id='gen_feed_url' href='$url_path' target='_blank'>$url_path</a>";
		print "</div>";

		print "<div align='center'>";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return genUrlChangeKey('$feed_id', '$is_cat')\">".
			__('Generate new URL')."</button> ";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return closeInfoBox()\">".
			__('Close this window')."</button>";

		print "</div>";

		//return;
	}

	function newVersion() {

		$version_data = check_for_update($this->link);
		$version = $version_data['version'];
		$id = $version_data['version_id'];

		if ($version && $id) {
			print "<div class='tagCloudContainer'>";

			print T_sprintf("New version of Tiny Tiny RSS is available (%s).",
				"<b>$version</b>");

			print "</div>";

			$details = "http://tt-rss.org/redmine/versions/$id";
			$download = "http://tt-rss.org/#Download";

			print "<p align='center'>".__("You can update using built-in updater in the Preferences or by using update.php")."</p>";

			print "<div style='text-align : center'>";
			print "<button dojoType=\"dijit.form.Button\"
				onclick=\"return window.open('$details')\">".__("See the release notes")."</button>";
			print "<button dojoType=\"dijit.form.Button\"
				onclick=\"return window.open('$download')\">".__("Download")."</button>";
			print "<button dojoType=\"dijit.form.Button\"
				onclick=\"return dijit.byId('newVersionDlg').hide()\">".
				__('Close this window')."</button>";

		} else {
			print "<div class='tagCloudContainer'>";

			print "<p align='center'>".__("Error receiving version information or no new version available.")."</p>";

			print "</div>";

			print "<div style='text-align : center'>";
			print "<button dojoType=\"dijit.form.Button\"
				onclick=\"return dijit.byId('newVersionDlg').hide()\">".
				__('Close this window')."</button>";
			print "</div>";

		}
		print "</div>";

	}

	function customizeCSS() {
		$value = get_pref($this->link, "USER_STYLESHEET");

		$value = str_replace("<br/>", "\n", $value);

		print_notice(T_sprintf("You can override colors, fonts and layout of your currently selected theme with custom CSS declarations here. <a target=\"_blank\" class=\"visibleLink\" href=\"%s\">This file</a> can be used as a baseline.", "tt-rss.css"));

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

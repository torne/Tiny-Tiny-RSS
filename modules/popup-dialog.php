<?php
	function module_popup_dialog($link) {
		$id = $_REQUEST["id"];
		$param = db_escape_string($_REQUEST["param"]);

		if ($id == "editPrefProfiles") {

			print "<div id=\"infoBoxTitle\">".__('Settings Profiles')."</div>";
			print "<div class=\"infoBoxContents\">";

			print "<div><input id=\"fadd_profile\" 
					onkeypress=\"return filterCR(event, addPrefProfile)\"
					size=\"40\">
					<button onclick=\"javascript:addPrefProfile()\">".
					__('Create profile')."</button></div>";

			print "<p>";

			$result = db_query($link, "SELECT title,id FROM ttrss_settings_profiles
				WHERE owner_uid = ".$_SESSION["uid"]." ORDER BY title");

			print	__('Select:')." 
				<a href=\"javascript:selectPrefRows('fcat', true)\">".__('All')."</a>,
				<a href=\"javascript:selectPrefRows('fcat', false)\">".__('None')."</a>";

			print "<div class=\"prefFeedCatHolder\">";

			print "<form id=\"profile_edit_form\" onsubmit=\"return false\">";

			print "<table width=\"100%\" class=\"prefFeedCatList\" 
				cellspacing=\"0\" id=\"prefFeedCatList\">";

			print "<tr class=\"odd\" id=\"FCATR-0\">";

			print "<td width='5%' align='center'><input 
				onclick='toggleSelectPrefRow(this, \"fcat\");' 
				type=\"checkbox\" id=\"FCCHK-0\"></td>";

			if (!$_SESSION["profile"]) {
				$is_active = __("(active)");
			} else {
				$is_active = "";
			}

			print "<td><span id=\"FCATT-0\">" . 
				__("Default profile") . " $is_active</span></td>";
				
			print "</tr>";

			$lnum = 1;
			
			while ($line = db_fetch_assoc($result)) {
	
				$class = ($lnum % 2) ? "even" : "odd";
	
				$cat_id = $line["id"];
				$this_row_id = "id=\"FCATR-$cat_id\"";
	
				print "<tr class=\"$class\" $this_row_id>";
	
				$edit_title = htmlspecialchars($line["title"]);
	
				print "<td width='5%' align='center'><input 
					onclick='toggleSelectPrefRow(this, \"fcat\");' 
					type=\"checkbox\" id=\"FCCHK-$cat_id\"></td>";

				if ($_SESSION["profile"] == $line["id"]) {
					$is_active = __("(active)");
				} else {
					$is_active = "";
				}

				print "<td><span id=\"FCATT-$cat_id\">" . 
					$edit_title . "</span> $is_active</td>";
				
				print "</tr>";
	
				++$lnum;
			}

			print "</table>";
			print "</form>";
			print "</div>";

			print "<div class='dlgButtons'>
				<div style='float : left'>
				<button onclick=\"return removeSelectedPrefProfiles()\">".
				__('Remove')."</button>
				<input class=\"button\"
				type=\"submit\" onclick=\"return activatePrefProfile()\" 
				value=\"".__('Activate')."\">
				</div>";

			print "<input class=\"button\"
				type=\"submit\" onclick=\"return closeInfoBox()\" 
				value=\"".__('Close this window')."\">";

			print "</div></div>";

			return;
		}

		if ($id == "pubUrl") {

			print "<div id=\"infoBoxTitle\">".__('Published Articles')."</div>";
			print "<div class=\"infoBoxContents\">";
	
			$url_path = article_publish_url($link);

			print __("Your Published articles feed URL is:");

			print "<div class=\"tagCloudContainer\">";
			print "<a id='pub_feed_url' href='$url_path' target='_blank'>$url_path</a>";
			print "</div>";

			print "<div align='center'>";

			print "<button onclick=\"return pubRegenKey()\">".
				__('Generate new URL')."</button> ";

			print "<input class=\"button\"
				type=\"submit\" onclick=\"return closeInfoBox()\" 
				value=\"".__('Close this window')."\">";

			print "</div></div>";

			return;
		}

		if ($id == "explainError") {

			print "<div id=\"infoBoxTitle\">".__('Notice')."</div>";
			print "<div class=\"infoBoxContents\">";

			print "<div class=\"errorExplained\">";

			if ($param == 1) {
				print __("Update daemon is enabled in configuration, but daemon process is not running, which prevents all feeds from updating. Please start the daemon process or contact instance owner.");

				$stamp = (int)read_stampfile("update_daemon.stamp");

				print "<p>" . __("Last update:") . " " . date("Y.m.d, G:i", $stamp); 

			}

			if ($param == 2) {
				$msg = check_for_update($link);

				if (!$msg) {
					print __("You are running the latest version of Tiny Tiny RSS. The fact that you are seeing this dialog is probably a bug.");
				} else {
					print $msg;
				}

			}

			if ($param == 3) {
				print __("Update daemon is taking too long to perform a feed update. This could indicate a problem like crash or a hang. Please check the daemon process or contact instance owner.");

				$stamp = (int)read_stampfile("update_daemon.stamp");

				print "<p>" . __("Last update:") . " " . date("Y.m.d, G:i", $stamp); 

			}

			print "</div>";
			
			print "<div align='center'>";

			print "<input class=\"button\"
				type=\"submit\" onclick=\"return visitOfficialSite()\" 
				value=\"".__('Visit official site')."\"> ";

			print "<input class=\"button\"
				type=\"submit\" onclick=\"return closeInfoBox()\" 
				value=\"".__('Close this window')."\">";

			print "</div></div>";

			return;
		}

		if ($id == "quickAddFeed") {

			print "<div id=\"infoBoxTitle\">".__('Subscribe to Feed')."</div>";
			print "<div class=\"infoBoxContents\">";

			print "<form id='feed_add_form' onsubmit='return false'>";

			print "<input type=\"hidden\" name=\"op\" value=\"rpc\">";
			print "<input type=\"hidden\" name=\"subop\" value=\"addfeed\">"; 
			//print "<input type=\"hidden\" name=\"from\" value=\"tt-rss\">"; 

			print "<div class=\"dlgSec\">".__("Feed")."</div>";
			print "<div class=\"dlgSecCont\">";

			print __("URL:") . " ";

			print "<input size=\"40\"
					onkeypress=\"return filterCR(event, subscribeToFeed)\"
					name=\"feed\" id=\"feed_url\"></td></tr>";

			print "<br/>";

			if (get_pref($link, 'ENABLE_FEED_CATS')) {
				print __('Place in category:') . " ";
				print_feed_cat_select($link, "cat");			
			}

			print "</div>";

			print "<div id='fadd_login_container' style='display:none'>
	
					<div class=\"dlgSec\">".__("Authentication")."</div>
					<div class=\"dlgSecCont\">".

					__('Login:') . " <input name='login' size=\"20\" 
							onkeypress=\"return filterCR(event, subscribeToFeed)\"> ".
					__('Password:') . "<input type='password'
							name='pass' size=\"20\" 
							onkeypress=\"return filterCR(event, subscribeToFeed)\">
				</div></div>";


			print "<div style=\"clear : both\">				
				<input type=\"checkbox\" id=\"fadd_login_check\" 
						onclick='checkboxToggleElement(this, \"fadd_login_container\")'>
					<label for=\"fadd_login_check\">".
					__('This feed requires authentication.')."</div>";

			print "</form>";

			print "<div class=\"dlgButtons\">
				<button class=\"button\" id=\"fadd_submit_btn\"
					onclick=\"return subscribeToFeed()\">".__('Subscribe')."</button>
				<button onclick=\"return browseFeeds()\">".__('More feeds')."</button>
				<button onclick=\"return closeInfoBox()\">".__('Cancel')."</button></div>";
			
			return;
		}

		if ($id == "feedBrowser") {

			print "<div id=\"infoBoxTitle\">".__('Feed Browser')."</div>";
			
			print "<div class=\"infoBoxContents\">";

			$browser_search = db_escape_string($_REQUEST["search"]);
			
			print "<form onsubmit='return false;' display='inline' 
				name='feed_browser' id='feed_browser'>";

			print "<input type=\"hidden\" name=\"op\" value=\"rpc\">";
			print "<input type=\"hidden\" name=\"subop\" value=\"updateFeedBrowser\">"; 

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

/*			print	__('Select:')." 
				<a href=\"javascript:selectPrefRows('fbrowse', true)\">".__('All')."</a>,
					<a href=\"javascript:selectPrefRows('fbrowse', false)\">".__('None')."</a>"; */

			print "<ul class='browseFeedList' id='browseFeedList'>";
			print_feed_browser($link, $search, 25);
			print "</ul>";

			print "<div align='center'>
				<button onclick=\"feedBrowserSubscribe()\">".__('Subscribe')."</button>
				<button style='display : none' id='feed_archive_remove' onclick=\"feedArchiveRemove()\">".__('Remove')."</button>
				<button onclick=\"closeInfoBox()\" >".__('Cancel')."</button></div>";

			print "</div>";
			return;
		}

		if ($id == "search") {

			print "<div id=\"infoBoxTitle\">".__('Search')."</div>";
			print "<div class=\"infoBoxContents\">";

			print "<form id='search_form'  onsubmit='return false'>";

			#$active_feed_id = db_escape_string($_REQUEST["param"]);

			$params = split(":", db_escape_string($_REQUEST["param"]));

			$active_feed_id = sprintf("%d", $params[0]);
			$is_cat = $params[1] == "true";

			print "<div class=\"dlgSec\">".__('Look for')."</div>";

			print "<div class=\"dlgSecCont\">";

			print "<input name=\"query\" size=\"20\" type=\"search\"	value=''>";

			print " " . __('match on')." ";

			$search_fields = array(
				"title" => __("Title"),
				"content" => __("Content"),
				"both" => __("Title or content"));

			print_select_hash("match_on", 3, $search_fields); 


			print "<br/>".__('Limit search to:')." ";
			
			print "<select name=\"search_mode\">
				<option value=\"all_feeds\">".__('All feeds')."</option>";
			
			$feed_title = getFeedTitle($link, $active_feed_id);

			if (!$is_cat) {
				$feed_cat_title = getFeedCatTitle($link, $active_feed_id);
			} else {
				$feed_cat_title = getCategoryTitle($link, $active_feed_id);
			}
			
			if ($active_feed_id && !$is_cat) {				
				print "<option selected value=\"this_feed\">$feed_title</option>";
			} else {
				print "<option disabled>".__('This feed')."</option>";
			}

			if ($is_cat) {
			  	$cat_preselected = "selected";
			}

			if (get_pref($link, 'ENABLE_FEED_CATS') && ($active_feed_id > 0 || $is_cat)) {
				print "<option $cat_preselected value=\"this_cat\">$feed_cat_title</option>";
			} else {
				//print "<option disabled>".__('This category')."</option>";
			}

			print "</select>"; 

			print "</div>";

			print "</form>";

			print "<div class=\"dlgButtons\">
			<button onclick=\"javascript:search()\">".__('Search')."</button>
			<button onclick=\"javascript:searchCancel()\">".__('Cancel')."</button>
			</div>";

			print "</div>";

			return;

		}

		if ($id == "quickAddFilter") {

			$active_feed_id = db_escape_string($_REQUEST["param"]);

			print "<div id=\"infoBoxTitle\">".__('Create Filter')."</div>";
			print "<div class=\"infoBoxContents\">";

			print "<form id=\"filter_add_form\" onsubmit='return false'>";

			print "<input type=\"hidden\" name=\"op\" value=\"pref-filters\">";
			print "<input type=\"hidden\" name=\"quiet\" value=\"1\">";
			print "<input type=\"hidden\" name=\"subop\" value=\"add\">"; 
		
			$result = db_query($link, "SELECT id,description 
				FROM ttrss_filter_types ORDER BY description");
	
			$filter_types = array();
	
			while ($line = db_fetch_assoc($result)) {
				//array_push($filter_types, $line["description"]);
				$filter_types[$line["id"]] = __($line["description"]);
			}

			print "<div class=\"dlgSec\">".__("Match")."</div>";

			print "<div class=\"dlgSecCont\">";

			print "<span id=\"filter_dlg_date_mod_box\" style=\"display : none\">";
			print __("Date") . " ";

			$filter_params = array(
				"before" => __("before"),
				"after" => __("after"));

			print_select_hash("filter_date_modifier", "before", $filter_params);

			print "&nbsp;</span>";

			print "<input onkeypress=\"return filterCR(event, createFilter)\"
				 name=\"reg_exp\" size=\"30\" value=\"$reg_exp\">";

			print "<span id=\"filter_dlg_date_chk_box\" style=\"display : none\">";
			print "&nbsp;<input class=\"button\"
				type=\"submit\" onclick=\"return filterDlgCheckDate()\" 
				value=\"".__('Check it')."\">";
			print "</span>";

			print "<br/> " . __("on field") . " ";
			print_select_hash("filter_type", 1, $filter_types,
				'onchange="filterDlgCheckType(this)"');

			print "<br/>";

			print __("in") . " ";
			print_feed_select($link, "feed_id", $active_feed_id);

			print "</div>";

			print "<div class=\"dlgSec\">".__("Perform Action")."</div>";

			print "<div class=\"dlgSecCont\">";

			print "<select name=\"action_id\"
				onchange=\"filterDlgCheckAction(this)\">";
	
			$result = db_query($link, "SELECT id,description FROM ttrss_filter_actions 
				ORDER BY name");

			while ($line = db_fetch_assoc($result)) {
				printf("<option value='%d'>%s</option>", $line["id"], __($line["description"]));
			}
	
			print "</select>";

			print "<span id=\"filter_dlg_param_box\" style=\"display : none\">";
			print " " . __("with parameters:") . " ";
			print "<input size=\"20\"
					onkeypress=\"return filterCR(event, createFilter)\"
					name=\"action_param\">";

			print_label_select($link, "action_param_label", $action_param);

			print "</span>";

			print "&nbsp;"; // tiny layout hack

			print "</div>";

			print "<div class=\"dlgSec\">".__("Options")."</div>";
			print "<div class=\"dlgSecCont\">";

			print "<div style=\"line-height : 100%\">";

			print "<input type=\"checkbox\" name=\"enabled\" id=\"enabled\" checked=\"1\">
					<label for=\"enabled\">".__('Enabled')."</label><br/>";

			print "<input type=\"checkbox\" name=\"inverse\" id=\"inverse\">
				<label for=\"inverse\">".__('Inverse match')."</label>";

			print "</div>";
			print "</div>";

			print "</form>";

			print "<div class=\"dlgButtons\">";

			print "<button onclick=\"return createFilter()\">".
				__('Create')."</button> ";

			print "<button onclick=\"return closeInfoBox()\">".__('Cancel').
				"</button>";

			print "</div>";

//			print "</td></tr></table>"; 

			return;
		}

		if ($id == "feedUpdateErrors") {

			print "<div id=\"infoBoxTitle\">".__('Update Errors')."</div>";
			print "<div class=\"infoBoxContents\">";

			print __("These feeds have not been updated because of errors:");

			$result = db_query($link, "SELECT id,title,feed_url,last_error
			FROM ttrss_feeds WHERE last_error != '' AND owner_uid = ".$_SESSION["uid"]);

			print "<ul class='feedErrorsList'>";

			while ($line = db_fetch_assoc($result)) {
				print "<li><b>" . $line["title"] . "</b> (" . $line["feed_url"] . "): " . 
					"<em>" . $line["last_error"] . "</em>";
			}

			print "</ul>";

			print "<div align='center'>";

			print "<input class=\"button\"
				type=\"submit\" onclick=\"return closeInfoBox()\" 
				value=\"".__('Close')."\">";

			print "</div>";

			return;
		}

		if ($id == "editArticleTags") {

			print "<div id=\"infoBoxTitle\">".__('Edit Tags')."</div>";
			print "<div class=\"infoBoxContents\">";

			print "<form id=\"tag_edit_form\" onsubmit='return false'>";

			print __("Tags for this article (separated by commas):")."<br>";

			$tags = get_article_tags($link, $param);

			$tags_str = join(", ", $tags);

			print "<table width='100%'>";

			print "<tr><td colspan='2'><input type=\"hidden\" name=\"id\" value=\"$param\"></td></tr>";

			print "<tr><td colspan='2'><textarea rows='4' class='iedit' id='tags_str' 
				name='tags_str'>$tags_str</textarea>
			<div class=\"autocomplete\" id=\"tags_choices\" 
					style=\"display:none\"></div>	
			</td></tr>";

			print "</table>";

			print "</form>";

			print "<div align='right'>";

			print "<button onclick=\"return editTagsSave()\">".__('Save')."</button> ";
			print "<button onclick=\"return closeInfoBox()\">".__('Cancel')."</button>";

			print "</div>";

			return;
		}

		if ($id == "printTagCloud") {
			print "<div id=\"infoBoxTitle\">".__('Tag Cloud')."</div>";
			print "<div class=\"infoBoxContents\">";

			print __("Showing most popular tags ")." (<a 
			href='javascript:toggleTags(true)'>".__('more tags')."</a>):<br/>"; 

			print "<div class=\"tagCloudContainer\">";

			printTagCloud($link);

			print "</div>";

			print "<div align='center'>";
			print "<button onclick=\"return closeInfoBox()\">".
				__('Close this window')."</button>";
			print "</div>";

			print "</div>";

			return;
		}

/*		if ($id == "offlineDownload") {
			print "<div id=\"infoBoxTitle\">".__('Download articles')."</div>";
			print "<div class=\"infoBoxContents\">";

			print "<form name='download_ops_form' id='download_ops_form'>";

			print "<div class=\"dlgSec\">".__("Download")."</div>";

			print "<div class=\"dlgSecCont\">";

			$amount = array(
				50  => 50,
				100 => 100,
				250 => 250,
				500 => 500);

			print_select_hash("amount", 50, $amount);

			print " " . __("latest articles for offline reading.");

			print "<br/>";

			print "<input checked='yes' type='checkbox' name='unread_only' id='unread_only'>";
			print "<label for='unread_only'>".__('Only include unread articles')."</label>";

			print "</div>";

			print "</form>";

			print "<div class=\"dlgButtons\">
				<input class=\"button\"
					type=\"submit\" onclick=\"return initiate_offline_download(0, this)\" value=\"".__('Download')."\">
				<input class=\"button\"
					type=\"submit\" onclick=\"return closeInfoBox()\" 
					value=\"".__('Cancel')."\"></div>";

			print "</div>";

			return;
		} */


		print "<div id='infoBoxTitle'>Internal Error</div>
			<div id='infoBoxContents'>
			<p>Unknown dialog <b>$id</b></p>
			</div></div>";
	
	}
?>

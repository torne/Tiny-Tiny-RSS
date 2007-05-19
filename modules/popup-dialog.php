<?php
	function module_popup_dialog($link) {
		$id = $_GET["id"];
		$param = db_escape_string($_GET["param"]);

		if ($id == "explainError") {

			print "<div id=\"infoBoxTitle\">".__('Notice')."</div>";
			print "<div class=\"infoBoxContents\">";

			if ($param == 1) {
				print __("Update daemon is enabled in configuration, but daemon
					process is not running, which prevents all feeds from updating. Please
					start the daemon process or contact instance owner.");
			}

			if ($param == 2) {
				$msg = check_for_update($link, false);

				if (!$msg) {
					print __("You are running the latest version of Tiny Tiny RSS. The
						fact that you are seeing this dialog is probably a bug.");
				} else {
					print $msg;
				}

			}

			print "</div>";

			print "<div align='center'>";

			print "<input class=\"button\"
				type=\"submit\" onclick=\"return closeInfoBox()\" 
				value=\"".__('Close this window')."\">";

			print "</div>";

			return;
		}

		if ($id == "quickAddFeed") {

			print "<div id=\"infoBoxTitle\">".__('Subscribe to feed')."</div>";
			print "<div class=\"infoBoxContents\">";

			print "<form id='feed_add_form' onsubmit='return false'>";

			print "<input type=\"hidden\" name=\"op\" value=\"pref-feeds\">";
			/* print "<input type=\"hidden\" name=\"quiet\" value=\"1\">"; */
			print "<input type=\"hidden\" name=\"subop\" value=\"add\">"; 
			print "<input type=\"hidden\" name=\"from\" value=\"tt-rss\">"; 

			print "<table width='100%'>
			<tr><td>Feed URL:</td><td>
				<input class=\"iedit\" onblur=\"javascript:enableHotkeys()\" 
					onkeypress=\"return filterCR(event, qaddFeed)\"
					onkeyup=\"toggleSubmitNotEmpty(this, 'fadd_submit_btn')\"
					onchange=\"toggleSubmitNotEmpty(this, 'fadd_submit_btn')\"
					onfocus=\"javascript:disableHotkeys()\" name=\"feed_url\"></td></tr>";
		
			if (get_pref($link, 'ENABLE_FEED_CATS')) {
				print "<tr><td>".__('Category:')."</td><td>";
				print_feed_cat_select($link, "cat_id");			
				print "</td></tr>";
			}

/*			print "<tr><td colspan='2'><div class='insensitive'>";

			print __("Some feeds require authentication. If you subscribe to such
				feed, you will have to enter your login and password in Feed Editor");

			print "</div></td></tr>"; */

			print "</table>";

			print "<div id='fadd_login_prompt'><br/>
				<a href='javascript:showBlockElement(\"fadd_login_container\", 
					\"fadd_login_prompt\")'>Click here if this feed requires authentication.</a></div>";

			print "<div id='fadd_login_container'>
				<table width='100%'>
					<tr><td>Login:</td><td><input name='auth_login' class='iedit'></td></tr>
					<tr><td>Password:</td><td><input type='password'
						name='auth_pass' class='iedit'></td></tr>
				</table>
				</div>";

			print "</form>";

			print "<div align='right'>
				<input class=\"button\"
					id=\"fadd_submit_btn\" disabled=\"true\"
					type=\"submit\" onclick=\"return qaddFeed()\" value=\"".__('Subscribe')."\">
				<input class=\"button\"
					type=\"submit\" onclick=\"return closeInfoBox()\" 
					value=\"".__('Cancel')."\"></div>";

			return;
		}

		if ($id == "search") {

			print "<div id=\"infoBoxTitle\">".__('Search')."</div>";
			print "<div class=\"infoBoxContents\">";

			print "<form id='search_form'  onsubmit='return false'>";

			#$active_feed_id = db_escape_string($_GET["param"]);

			$params = split(":", db_escape_string($_GET["param"]));

			$active_feed_id = sprintf("%d", $params[0]);
			$is_cat = $params[1] == "true";

			print "<table width='100%'><tr><td>".__('Search:')."</td><td>";
			
			print "<input name=\"query\" class=\"iedit\" 
				onkeypress=\"return filterCR(event, search)\"
				onchange=\"toggleSubmitNotEmpty(this, 'search_submit_btn')\"
				onkeyup=\"toggleSubmitNotEmpty(this, 'search_submit_btn')\"
				value=\"\">
			</td></tr>";
			
			print "<tr><td>".__('Where:')."</td><td>";
			
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

			print "</select></td></tr>"; 

			print "<tr><td>".__('Match on:')."</td><td>";

			$search_fields = array(
				"title" => __("Title"),
				"content" => __("Content"),
				"both" => __("Title or content"));

			print_select_hash("match_on", 3, $search_fields); 
				
			print "</td></tr></table>";

			print "</form>";

			print "<div align=\"right\">
			<input type=\"submit\" 
				class=\"button\" onclick=\"javascript:search()\" 
				id=\"search_submit_btn\" disabled=\"true\"
				value=\"".__('Search')."\">
			<input class=\"button\"
				type=\"submit\" onclick=\"javascript:searchCancel()\" 
				value=\"".__('Cancel')."\"></div>";

			print "</div>";

			return;

		}

		if ($id == "quickAddLabel") {
			print "<div id=\"infoBoxTitle\">".__('Create label')."</div>";
			print "<div class=\"infoBoxContents\">";

			print "<form id=\"label_edit_form\" onsubmit='return false'>";

			print "<input type=\"hidden\" name=\"op\" value=\"pref-labels\">";
			print "<input type=\"hidden\" name=\"subop\" value=\"add\">"; 

			print "<table width='100%'>";

			print "<tr><td>".__('Caption:')."</td>
				<td><input onkeypress=\"return filterCR(event, addLabel)\"
					onkeyup=\"toggleSubmitNotEmpty(this, 'infobox_submit')\"
					onchange=\"toggleSubmitNotEmpty(this, 'infobox_submit')\"
					name=\"description\" class=\"iedit\">";

			print "</td></tr>";

			print "<tr><td colspan=\"2\">
				<p>".__('SQL Expression:')."</p>";

			print "<textarea onkeyup=\"toggleSubmitNotEmpty(this, 'infobox_submit')\"
					 rows=\"4\" name=\"sql_exp\" class=\"iedit\"></textarea>";

			print "</td></tr></table>";

			print "</form>";

			print "<div style=\"display : none\" id=\"label_test_result\"></div>";

			print "<div align='right'>";

			print "<input type=\"submit\" onclick=\"labelTest()\" value=\"".__('Test')."\">
				";

			print "<input type=\"submit\" 
				id=\"infobox_submit\"
				disabled=\"true\"
				class=\"button\" onclick=\"return addLabel()\" 
				value=\"".__('Create')."\"> ";

			print "<input class=\"button\"
				type=\"submit\" onclick=\"return labelEditCancel()\" 
				value=\"".__('Cancel')."\">";

			return;
		}

		if ($id == "quickAddFilter") {

			$active_feed_id = db_escape_string($_GET["param"]);

			print "<div id=\"infoBoxTitle\">".__('Create filter')."</div>";
			print "<div class=\"infoBoxContents\">";

			print "<form id=\"filter_add_form\" onsubmit='return false'>";

			print "<input type=\"hidden\" name=\"op\" value=\"pref-filters\">";
			print "<input type=\"hidden\" name=\"quiet\" value=\"1\">";
			print "<input type=\"hidden\" name=\"subop\" value=\"add\">"; 

//			print "<div class=\"notice\"><b>Note:</b> filter will only apply to new articles.</div>";
		
			$result = db_query($link, "SELECT id,description 
				FROM ttrss_filter_types ORDER BY description");
	
			$filter_types = array();
	
			while ($line = db_fetch_assoc($result)) {
				//array_push($filter_types, $line["description"]);
				$filter_types[$line["id"]] = $line["description"];
			}

			print "<table width='100%'>";

			print "<tr><td>".__('Match:')."</td>
				<td><input onkeypress=\"return filterCR(event, qaddFilter)\"
					onkeyup=\"toggleSubmitNotEmpty(this, 'infobox_submit')\"
 					onchange=\"toggleSubmitNotEmpty(this, 'infobox_submit')\"
					 name=\"reg_exp\" class=\"iedit\">";		

			print "</td></tr><tr><td>".__('On field:')."</td><td>";

			print_select_hash("filter_type", 1, $filter_types, "class=\"_iedit\"");	
	
			print "</td></tr>";
			print "<tr><td>".__('Feed:')."</td><td colspan='2'>";

			print_feed_select($link, "feed_id", $active_feed_id);
			
			print "</td></tr>";
	
			print "<tr><td>".__('Action:')."</td>";
	
			print "<td colspan='2'><select name=\"action_id\" 
				onchange=\"filterDlgCheckAction(this)\">";
	
			$result = db_query($link, "SELECT id,description FROM ttrss_filter_actions 
				ORDER BY name");

			while ($line = db_fetch_assoc($result)) {
				printf("<option value='%d'>%s</option>", $line["id"], $line["description"]);
			}
	
			print "</select>";

			print "</td></tr>";

			print "<tr><td>".__('Params:')."</td>";

			print "<td><input disabled class='iedit' name='action_param'></td></tr>";

			print "<tr><td valign='top'>".__('Options:')."</td><td>";

			print "<input type=\"checkbox\" name=\"inverse\" id=\"inverse\">
				<label for=\"inverse\">".__('Inverse match')."</label></td></tr>";

			print "</table>";

			print "</form>";

			print "<div align='right'>";

			print "<input type=\"submit\" 
				id=\"infobox_submit\"
				class=\"button\" onclick=\"return addFilter()\" 
				disabled=\"true\" value=\"".__('Create')."\"> ";

			print "<input class=\"button\"
				type=\"submit\" onclick=\"return closeInfoBox()\" 
				value=\"".__('Cancel')."\">";

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
			print "</div>";

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

			print "<tr><td colspan='2'><textarea rows='4' class='iedit' name='tags_str'>$tags_str</textarea></td></tr>";

			print "<tr><td>".__('Add existing tag:')."</td>";

			$result = db_query($link, "SELECT DISTINCT tag_name FROM ttrss_tags 
				WHERE owner_uid = '".$_SESSION["uid"]."' ORDER BY tag_name");

			$found_tags = array();

			array_push($found_tags, '');

			while ($line = db_fetch_assoc($result)) {
				array_push($found_tags, truncate_string($line["tag_name"], 20));
			}

			print "<td align='right'>";

			print_select("found_tags", '', $found_tags, "onchange=\"javascript:editTagsInsert()\"");

			print "</td>";

			print "</tr>";

			print "</table>";

			print "</form>";

			print "<div align='right'>";

			print "<input class=\"button\"
				type=\"submit\" onclick=\"return editTagsSave()\" 
				value=\"".__('Save')."\"> ";

			print "<input class=\"button\"
				type=\"submit\" onclick=\"return closeInfoBox()\" 
				value=\"".__('Cancel')."\">";


			print "</div>";

			return;
		}

		if ($id == "printTagCloud") {
			print "<div id=\"infoBoxTitle\">".__('Tag cloud')."</div>";
			print "<div class=\"infoBoxContents\">";

			print __("Showing most popular tags ")." (<a 
				href='javascript:toggleTags(true)'>".__('browse all')."</a>):<br/>";

			print "<div class=\"tagCloudContainer\">";

			printTagCloud($link);

			print "</div>";

			print "<div align='center'>";
			print "<input class=\"button\"
				type=\"submit\" onclick=\"return closeInfoBox()\" 
				value=\"".__('Close')."\">";
			print "</div>";

			print "</div>";

			return;
		}

		print "<div id='infoBoxTitle'>Internal Error</div>
			<div id='infoBoxContents'>
			<p>Unknown dialog <b>$id</b></p>
			</div></div>";
	
	}
?>

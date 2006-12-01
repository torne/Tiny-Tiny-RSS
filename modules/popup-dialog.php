<?php
	function module_popup_dialog($link) {
		$id = $_GET["id"];
		$param = $_GET["param"];

		if ($id == "quickAddFeed") {

			print "<div id=\"infoBoxTitle\">Subscribe to feed</div>";
			print "<div class=\"infoBoxContents\">";

			print "<form id='feed_add_form'>";

			print "<input type=\"hidden\" name=\"op\" value=\"pref-feeds\">";
			print "<input type=\"hidden\" name=\"quiet\" value=\"1\">";
			print "<input type=\"hidden\" name=\"subop\" value=\"add\">"; 

			print "<table width='100%'>
			<tr><td>Feed URL:</td><td>
				<input class=\"iedit\" onblur=\"javascript:enableHotkeys()\" 
					onkeypress=\"return filterCR(event, qafAdd)\"
					onkeyup=\"toggleSubmitNotEmpty(this, 'fadd_submit_btn')\"
					onfocus=\"javascript:disableHotkeys()\" name=\"feed_url\"></td></tr>";
		
			if (get_pref($link, 'ENABLE_FEED_CATS')) {
				print "<tr><td>Category:</td><td>";
				print_feed_cat_select($link, "cat_id");			
				print "</td></tr>";
			}

			print "</table>";
			print "</form>";

			print "<div align='right'>
				<input class=\"button\"
					id=\"fadd_submit_btn\" disabled=\"true\"
					type=\"submit\" onclick=\"return qafAdd()\" value=\"Subscribe\">
				<input class=\"button\"
					type=\"submit\" onclick=\"return closeInfoBox()\" 
					value=\"Cancel\"></div>";
		}

		if ($id == "search") {

			print "<div id=\"infoBoxTitle\">Search</div>";
			print "<div class=\"infoBoxContents\">";

			print "<form id='search_form'>";

			#$active_feed_id = db_escape_string($_GET["param"]);

			$params = split(":", db_escape_string($_GET["param"]));

			$active_feed_id = sprintf("%d", $params[0]);
			$is_cat = $params[1] == "true";

			print "<table width='100%'><tr><td>Search:</td><td>";
			
			print "<input name=\"query\" class=\"iedit\" 
				onkeypress=\"return filterCR(event, search)\"
				onkeyup=\"toggleSubmitNotEmpty(this, 'search_submit_btn')\"
				value=\"\">
			</td></tr>";
			
			print "<tr><td>Where:</td><td>";
			
			print "<select name=\"search_mode\">
				<option value=\"all_feeds\">All feeds</option>";
			
			$feed_title = getFeedTitle($link, $active_feed_id);

			if (!$is_cat) {
				$feed_cat_title = getFeedCatTitle($link, $active_feed_id);
			} else {
				$feed_cat_title = getCategoryTitle($link, $active_feed_id);
			}
			
			if ($active_feed_id && !$is_cat) {				
				print "<option selected value=\"this_feed\">This feed ($feed_title)</option>";
			} else {
				print "<option disabled>This feed</option>";
			}

			if ($is_cat) {
			  	$cat_preselected = "selected";
			}

			if (get_pref($link, 'ENABLE_FEED_CATS') && ($active_feed_id > 0 || $is_cat)) {
				print "<option $cat_preselected value=\"this_cat\">This category ($feed_cat_title)</option>";
			} else {
				print "<option disabled>This category</option>";
			}

			print "</select></td></tr>"; 

			print "<tr><td>Match on:</td><td>";

			$search_fields = array(
				"title" => "Title",
				"content" => "Content",
				"both" => "Title or content");

			print_select_hash("match_on", 3, $search_fields); 
				
			print "</td></tr></table>";

			print "</form>";

			print "<div align=\"right\">
			<input type=\"submit\" 
				class=\"button\" onclick=\"javascript:search()\" 
				id=\"search_submit_btn\" disabled=\"true\"
				value=\"Search\">
			<input class=\"button\"
				type=\"submit\" onclick=\"javascript:searchCancel()\" 
				value=\"Cancel\"></div>";

			print "</div>";

		}

		if ($id == "quickAddLabel") {
			print "<div id=\"infoBoxTitle\">Create label</div>";
			print "<div class=\"infoBoxContents\">";

			print "<form id=\"label_edit_form\">";

			print "<input type=\"hidden\" name=\"op\" value=\"pref-labels\">";
			print "<input type=\"hidden\" name=\"subop\" value=\"add\">"; 

			print "<table width='100%'>";

			print "<tr><td>Caption:</td>
				<td><input onkeypress=\"return filterCR(event, addLabel)\"
					 onkeyup=\"toggleSubmitNotEmpty(this, 'infobox_submit')\"
					 name=\"description\" class=\"iedit\">";

			print "</td></tr>";

			print "<tr><td colspan=\"2\">
				<p>SQL Expression:</p>";

			print "<textarea onkeyup=\"toggleSubmitNotEmpty(this, 'infobox_submit')\"
					 rows=\"4\" name=\"sql_exp\" class=\"iedit\"></textarea>";

			print "</td></tr></table>";

			print "</form>";

			print "<div style=\"display : none\" id=\"label_test_result\"></div>";

			print "<div align='right'>";

			print "<input type=\"submit\" onclick=\"labelTest()\" value=\"Test\">
				";

			print "<input type=\"submit\" 
				id=\"infobox_submit\"
				disabled=\"true\"
				class=\"button\" onclick=\"return addLabel()\" 
				value=\"Create\"> ";

			print "<input class=\"button\"
				type=\"submit\" onclick=\"return labelEditCancel()\" 
				value=\"Cancel\">";
		}

		if ($id == "quickAddFilter") {

			$active_feed_id = db_escape_string($_GET["param"]);

			print "<div id=\"infoBoxTitle\">Create filter</div>";
			print "<div class=\"infoBoxContents\">";

			print "<form id=\"filter_add_form\">";

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

			print "<tr><td>Match:</td>
				<td><input onkeypress=\"return filterCR(event, qaddFilter)\"
					 onkeyup=\"toggleSubmitNotEmpty(this, 'infobox_submit')\"
					name=\"reg_exp\" class=\"iedit\">";		
			print "</td><td>";
		
			print_select_hash("filter_type", 1, $filter_types, "class=\"iedit\"");	
	
			print "</td></tr>";
			print "<tr><td>Feed:</td><td colspan='2'>";

			print_feed_select($link, "feed_id", $active_feed_id);
			
			print "</td></tr>";
	
			print "<tr><td>Action:</td>";
	
			print "<td colspan='2'><select name=\"action_id\">";
	
			$result = db_query($link, "SELECT id,description FROM ttrss_filter_actions 
				ORDER BY name");

			while ($line = db_fetch_assoc($result)) {
				printf("<option value='%d'>%s</option>", $line["id"], $line["description"]);
			}
	
			print "</select>";

			print "</td></tr></table>";

			print "</form>";

			print "<div align='right'>";

			print "<input type=\"submit\" 
				id=\"infobox_submit\"
				class=\"button\" onclick=\"return qaddFilter()\" 
				disabled=\"true\" value=\"Create\"> ";

			print "<input class=\"button\"
				type=\"submit\" onclick=\"return closeInfoBox()\" 
				value=\"Cancel\">";

			print "</div>";

//			print "</td></tr></table>"; 

		}

		if ($id == "feedUpdateErrors") {

			print "<div id=\"infoBoxTitle\">Update Errors</div>";
			print "<div class=\"infoBoxContents\">";

			print "These feeds have not been updated because of errors:";

			$result = db_query($link, "SELECT id,title,feed_url,last_error
			FROM ttrss_feeds WHERE last_error != '' AND owner_uid = ".$_SESSION["uid"]);

			print "<ul class='nomarks'>";

			while ($line = db_fetch_assoc($result)) {
				print "<li><b>" . $line["title"] . "</b> (" . $line["feed_url"] . "): " . 
					"<em>" . $line["last_error"] . "</em>";
			}

			print "</ul>";
			print "</div>";

			print "<div align='center'>";

			print "<input class=\"button\"
				type=\"submit\" onclick=\"return closeInfoBox()\" 
				value=\"Close\">";

			print "</div>";

		}

		print "</div>";
	}
?>

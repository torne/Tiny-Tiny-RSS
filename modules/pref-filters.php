<?php
	function module_pref_filters($link) {
		$subop = $_GET["subop"];
		$quiet = $_GET["quiet"];

		if ($subop == "edit") {

			$filter_id = db_escape_string($_GET["id"]);

			$result = db_query($link, 
				"SELECT * FROM ttrss_filters WHERE id = '$filter_id' AND owner_uid = " . $_SESSION["uid"]);

			$reg_exp = htmlspecialchars(db_fetch_result($result, 0, "reg_exp"));
			$filter_type = db_fetch_result($result, 0, "filter_type");
			$feed_id = db_fetch_result($result, 0, "feed_id");
			$action_id = db_fetch_result($result, 0, "action_id");
			$action_param = db_fetch_result($result, 0, "action_param");
			$filter_param = db_fetch_result($result, 0, "filter_param");

			$enabled = sql_bool_to_bool(db_fetch_result($result, 0, "enabled"));
			$inverse = sql_bool_to_bool(db_fetch_result($result, 0, "inverse"));

			print "<div id=\"infoBoxTitle\">".__('Filter Editor')."</div>";
			print "<div class=\"infoBoxContents\">";

			print "<form id=\"filter_edit_form\" onsubmit='return false'>";

			print "<input type=\"hidden\" name=\"op\" value=\"pref-filters\">";
			print "<input type=\"hidden\" name=\"id\" value=\"$filter_id\">";
			print "<input type=\"hidden\" name=\"subop\" value=\"editSave\">"; 
			
			$result = db_query($link, "SELECT id,description 
				FROM ttrss_filter_types ORDER BY description");
	
			$filter_types = array();
	
			while ($line = db_fetch_assoc($result)) {
				//array_push($filter_types, $line["description"]);
				$filter_types[$line["id"]] = __($line["description"]);
			}

			print "<div class=\"dlgSec\">".__("Match")."</div>";

			print "<div class=\"dlgSecCont\">";

			if ($filter_type != 5) {
				$date_ops_invisible = 'style=\"display : none\"';
			}

			print "<span id=\"filter_dlg_date_mod_box\" $date_ops_invisible>";
			print __("Date") . " ";

			$filter_params = array(
				"before" => __("before"),
				"after" => __("after"));

			print_select_hash("filter_date_modifier", $filter_param,
				$filter_params);

			print "&nbsp;</span>";

			print "<input onkeypress=\"return filterCR(event, filterEditSave)\"
					 onkeyup=\"toggleSubmitNotEmpty(this, 'infobox_submit')\"
					 onchange=\"toggleSubmitNotEmpty(this, 'infobox_submit')\"
					 name=\"reg_exp\" size=\"30\" value=\"$reg_exp\">";

			print "<span id=\"filter_dlg_date_chk_box\" $date_ops_invisible>";			
			print "&nbsp;<input class=\"button\"
				type=\"submit\" onclick=\"return filterDlgCheckDate()\" 
				value=\"".__('Check it')."\">";
			print "</span>";

			print "<br/> " . __("on field") . " ";
			print_select_hash("filter_type", $filter_type, $filter_types,
				'onchange="filterDlgCheckType(this)"');

			print "<br/>";

			print __("in") . " ";
			print_feed_select($link, "feed_id", $feed_id);

			print "</div>";

			print "<div class=\"dlgSec\">".__("Perform Action")."</div>";

			print "<div class=\"dlgSecCont\">";

			print "<select name=\"action_id\"
				onchange=\"filterDlgCheckAction(this)\">";
	
			$result = db_query($link, "SELECT id,description FROM ttrss_filter_actions 
				ORDER BY name");

			while ($line = db_fetch_assoc($result)) {
				$is_sel = ($line["id"] == $action_id) ? "selected" : "";			
				printf("<option value='%d' $is_sel>%s</option>", $line["id"], __($line["description"]));
			}
	
			print "</select>";

			$param_hidden = ($action_id == 4 || $action_id == 6) ? "" : "display : none";

			print "<span id=\"filter_dlg_param_box\" style=\"$param_hidden\">";
			print " " . __("with parameters:") . " ";
			print "<input size=\"20\"
					onkeypress=\"return filterCR(event, filterEditSave)\"		
					name=\"action_param\" value=\"$action_param\">";
			print "</span>";

			print "&nbsp;"; // tiny layout hack

			print "</div>";

			print "<div class=\"dlgSec\">".__("Options")."</div>";
			print "<div class=\"dlgSecCont\">";

			print "<div style=\"line-height : 100%\">";

			if ($enabled) {
				$checked = "checked";
			} else {
				$checked = "";
			}

			print "<input type=\"checkbox\" name=\"enabled\" id=\"enabled\" $checked>
					<label for=\"enabled\">".__('Enabled')."</label><br/>";

			if ($inverse) {
				$checked = "checked";
			} else {
				$checked = "";
			}

			print "<input type=\"checkbox\" name=\"inverse\" id=\"inverse\" $checked>
				<label for=\"inverse\">".__('Inverse match')."</label>";

			print "</div>";
			print "</div>";

			print "<div class=\"dlgButtons\">";

			$reg_exp = htmlspecialchars($reg_exp, ENT_QUOTES); // second escaping seems to be needed for javascript

			print "<div style=\"float : left\">";
			print "<input type=\"submit\" 
				class=\"button\" onclick='return removeFilter($filter_id, \"$reg_exp\")' 
				value=\"".__('Remove')."\"> ";
			print "</div>";

			print "<input type=\"submit\" 
				id=\"infobox_submit\"
				class=\"button\" onclick=\"return filterEditSave()\" 
				value=\"".__('Save')."\"> ";

			print "<input class=\"button\"
				type=\"submit\" onclick=\"return filterEditCancel()\" 
				value=\"".__('Cancel')."\">";

			print "</div>";

			return;
		}


		if ($subop == "editSave") {

			$reg_exp = db_escape_string(trim($_GET["reg_exp"]));
			$filter_type = db_escape_string(trim($_GET["filter_type"]));
			$filter_id = db_escape_string($_GET["id"]);
			$feed_id = db_escape_string($_GET["feed_id"]);
			$action_id = db_escape_string($_GET["action_id"]); 
			$action_param = db_escape_string($_GET["action_param"]); 
			$enabled = checkbox_to_sql_bool(db_escape_string($_GET["enabled"]));
			$inverse = checkbox_to_sql_bool(db_escape_string($_GET["inverse"]));

			# for the time being, no other filters use params anyway...
			$filter_param = db_escape_string($_GET["filter_date_modifier"]);

			if (!$feed_id) {
				$feed_id = 'NULL';
			} else {
				$feed_id = sprintf("'%s'", db_escape_string($feed_id));
			}
			
			$result = db_query($link, "UPDATE ttrss_filters SET 
					reg_exp = '$reg_exp', 
					feed_id = $feed_id,
					action_id = '$action_id',
					filter_type = '$filter_type',
					enabled = $enabled,
					inverse = $inverse,
					action_param = '$action_param',
					filter_param = '$filter_param'
					WHERE id = '$filter_id' AND owner_uid = " . $_SESSION["uid"]);

			if (db_affected_rows($link, $result) != 0) {
				print_notice(T_sprintf("Saved filter <b>%s</b>", htmlspecialchars($reg_exp)));
			}

		}

		if ($subop == "remove") {

			$ids = split(",", db_escape_string($_GET["ids"]));

			foreach ($ids as $id) {
				db_query($link, "DELETE FROM ttrss_filters WHERE id = '$id' AND owner_uid = ". $_SESSION["uid"]);
			}
		}

		if ($subop == "add") {
		
			$regexp = db_escape_string(trim($_GET["reg_exp"]));
			$filter_type = db_escape_string(trim($_GET["filter_type"]));
			$feed_id = db_escape_string($_GET["feed_id"]);
			$action_id = db_escape_string($_GET["action_id"]); 
			$action_param = db_escape_string($_GET["action_param"]); 
			$inverse = checkbox_to_sql_bool(db_escape_string($_GET["inverse"]));

			# for the time being, no other filters use params anyway...
			$filter_param = db_escape_string($_GET["filter_date_modifier"]);

			if (!$regexp) return;

			if (!$feed_id) {
				$feed_id = 'NULL';
			} else {
				$feed_id = sprintf("'%s'", db_escape_string($feed_id));
			}

			$result = db_query($link,
				"INSERT INTO ttrss_filters (reg_exp,filter_type,owner_uid,feed_id,
					action_id, action_param, inverse, filter_param) 
				VALUES 
					('$regexp', '$filter_type','".$_SESSION["uid"]."', 
						$feed_id, '$action_id', '$action_param', $inverse, '$filter_param')");

			if (db_affected_rows($link, $result) != 0) {
				print T_sprintf("Created filter <b>%s</b>", htmlspecialchars($regexp));
			}

			return;
		}

		if ($quiet) return;

		set_pref($link, "_PREFS_ACTIVE_TAB", "filterConfig");

		$sort = db_escape_string($_GET["sort"]);

		if (!$sort || $sort == "undefined") {
			$sort = "reg_exp";
		}

//		print "<div id=\"infoBoxShadow\"><div id=\"infoBox\">PLACEHOLDER</div></div>";

		$result = db_query($link, "SELECT id,description 
			FROM ttrss_filter_types ORDER BY description");

		$filter_types = array();

		while ($line = db_fetch_assoc($result)) {
			//array_push($filter_types, $line["description"]);
			$filter_types[$line["id"]] = $line["description"];
		}


		$filter_search = db_escape_string($_GET["search"]);

		if (array_key_exists("search", $_GET)) {
			$_SESSION["prefs_filter_search"] = $filter_search;
		} else {
			$filter_search = $_SESSION["prefs_filter_search"];
		}

		print "<div class=\"feedEditSearch\">
			<input id=\"filter_search\" size=\"20\" type=\"search\"
				onfocus=\"javascript:disableHotkeys();\" 
				onblur=\"javascript:enableHotkeys();\"
				onchange=\"javascript:updateFilterList()\" value=\"$filter_search\">
			<input type=\"submit\" class=\"button\" 
			onclick=\"javascript:updateFilterList()\" value=\"".__('Search')."\">
			<p<a class='helpLinkPic' href=\"javascript:displayHelpInfobox(2)\">
			<img src='images/sign_quest.gif'></a></p>
			</div>";


		print "<input type=\"submit\" 
			class=\"button\" 
			onclick=\"return displayDlg('quickAddFilter', false)\" 
			id=\"create_filter_btn\"
			value=\"".__('Create filter')."\">"; 

		print "&nbsp;";

		print "<input type=\"submit\" 
			class=\"button\" 
			onclick=\"rescore_all_feeds()\" 
			value=\"".__('Rescore articles')."\">"; 

		if ($filter_search) {
			$filter_search = db_escape_string($filter_search);
			$filter_search_query = "(
				UPPER(ttrss_filter_actions.description) LIKE UPPER('%$filter_search%') OR 
				UPPER(reg_exp) LIKE UPPER('%$filter_search%') OR 
				UPPER(ttrss_feeds.title) LIKE UPPER('%$filter_search%') OR
				UPPER(ttrss_filter_types.description) LIKE UPPER('%$filter_search%')) AND";
		} else {
			$filter_search_query = "";
		}

		$result = db_query($link, "SELECT 
				ttrss_filters.id AS id,reg_exp,
				ttrss_filter_types.name AS filter_type_name,
				ttrss_filter_types.description AS filter_type_descr,
				enabled,
				inverse,
				feed_id,
				ttrss_filter_actions.description AS action_description,
				ttrss_feeds.title AS feed_title,
				ttrss_filter_actions.name AS action_name,
				ttrss_filters.action_param AS action_param
			FROM 
				ttrss_filter_types,ttrss_filter_actions,ttrss_filters LEFT JOIN
					ttrss_feeds ON (ttrss_filters.feed_id = ttrss_feeds.id)
			WHERE
				filter_type = ttrss_filter_types.id AND
				$filter_search_query
				ttrss_filter_actions.id = action_id AND
				ttrss_filters.owner_uid = ".$_SESSION["uid"]."
			ORDER by action_description, $sort");

		if (db_num_rows($result) != 0) {

			print "<p><table width=\"100%\" cellspacing=\"0\" class=\"prefFilterList\" 
				id=\"prefFilterList\">";

			print "<tr><td class=\"selectPrompt\" colspan=\"8\">
				".__('Select:')." 
					<a href=\"javascript:selectPrefRows('filter', true)\">".__('All')."</a>,
					<a href=\"javascript:selectPrefRows('filter', false)\">".__('None')."</a>
				</td</tr>";

			$lnum = 0;

			$cur_action_description = "";

			while ($line = db_fetch_assoc($result)) {
	
				$class = ($lnum % 2) ? "even" : "odd";
	
				$filter_id = $line["id"];
				$edit_filter_id = $_GET["id"];

				$enabled = sql_bool_to_bool($line["enabled"]);
				$inverse = sql_bool_to_bool($line["inverse"]);

				if ($subop == "edit" && $filter_id != $edit_filter_id) {
					$class .= "Grayed";
					$this_row_id = "";
				} else {
					$this_row_id = "id=\"FILRR-$filter_id\"";
				}

				$line["filter_type_descr"] = __($line["filter_type_descr"]);
				$line["action_description"] = __($line["action_description"]);

				if ($line["action_description"] != $cur_action_description) {
					$cur_action_description = $line["action_description"];

					print "<tr><td class='filterEditCat' colspan='6'>$cur_action_description</td></tr>";

					print "<tr class=\"title\">
						<td align='center' width=\"5%\">&nbsp;</td>
						<td width=\"20%\"><a href=\"javascript:updateFilterList('reg_exp')\">".__('Match')."</a></td>
						<td width=\"\"><a href=\"javascript:updateFilterList('feed_title')\">".__('Feed')."</a></td>
						<td width=\"20%\"><a href=\"javascript:updateFilterList('filter_type')\">".__('Field')."</a></td>
						<td width=\"20%\"><a href=\"javascript:updateFilterList('action_param')\">".__('Params')."</a></td>"; 

				}

				print "<tr class=\"$class\" $this_row_id>";
	
				$line["reg_exp"] = htmlspecialchars($line["reg_exp"]);
	
				if (!$line["feed_title"]) $line["feed_title"] = __("All feeds");

				if (!$line["action_param"]) {
					$line["action_param"] = "&mdash;";
				} else if ($line["action_name"] == "score") {

					$score_pic = get_score_pic($line["action_param"]);

					$score_pic = "<img class='hlScorePic' src=\"images/$score_pic\">";

					$line["action_param"] = "$score_pic " . $line["action_param"];

				}

				$line["feed_title"] = htmlspecialchars($line["feed_title"]);

				print "<td align='center'><input onclick='toggleSelectPrefRow(this, \"filter\");' 
					type=\"checkbox\" id=\"FICHK-".$line["id"]."\"></td>";

				if (!$enabled) {
					$line["reg_exp"] = "<span class=\"insensitive\">" . 
						$line["reg_exp"] . " " .  __("(Disabled)")."</span>";
					$line["feed_title"] = "<span class=\"insensitive\">" . 
						$line["feed_title"] . "</span>";
					$line["filter_type_descr"] = "<span class=\"insensitive\">" . 
						$line["filter_type_descr"] . "</span>";
					$line["action_description"] = "<span class=\"insensitive\">" . 
						$line["action_description"] . "</span>";
					$line["action_param"] = "<span class=\"insensitive\">" . 
						$line["action_param"] . "</span>";
				}	

				$onclick = "onclick='editFilter($filter_id)' title='".__('Click to edit')."'";

				print "<td $onclick>" . $line["reg_exp"] . "</td>";		
				print "<td $onclick>" . $line["feed_title"] . "</td>";			

				$inverse_label = "";

				if ($inverse) {
					$inverse_label = " <span class='insensitive'>".__('(Inverse)')."</span>";
				}
	
				print "<td $onclick>" . $line["filter_type_descr"] . "$inverse_label</td>";
				print "<td $onclick>" . $line["action_param"] . "</td>";

				print "</tr>";
	
				++$lnum;
			}

			print "</table>";

			print "<p id=\"filterOpToolbar\">";

			print "<input type=\"submit\" class=\"button\" disabled=\"true\"
					onclick=\"return editSelectedFilter()\" value=\"".__('Edit')."\">
				<input type=\"submit\" class=\"button\" disabled=\"true\"
					onclick=\"return removeSelectedFilters()\" value=\"".__('Remove')."\">";

			print "</p>";

		} else {

			print "<p>";
			if (!$filter_search) {
				print __('No filters defined.');
			} else {
				print __('No matching filters found.');
			}
			print "</p>";

		}
	}
?>

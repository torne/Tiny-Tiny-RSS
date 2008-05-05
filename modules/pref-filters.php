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

			$enabled = sql_bool_to_bool(db_fetch_result($result, 0, "enabled"));
			$inverse = sql_bool_to_bool(db_fetch_result($result, 0, "inverse"));

			print "<div id=\"infoBoxTitle\">".__('Filter editor')."</div>";
			print "<div class=\"infoBoxContents\">";

			print "<form id=\"filter_edit_form\" onsubmit='return false'>";

			print "<input type=\"hidden\" name=\"op\" value=\"pref-filters\">";
			print "<input type=\"hidden\" name=\"id\" value=\"$filter_id\">";
			print "<input type=\"hidden\" name=\"subop\" value=\"editSave\">"; 

//			print "<div class=\"notice\"><b>Note:</b> filter will only apply to new articles.</div>";
			
			$result = db_query($link, "SELECT id,description 
				FROM ttrss_filter_types ORDER BY description");
	
			$filter_types = array();
	
			while ($line = db_fetch_assoc($result)) {
				//array_push($filter_types, $line["description"]);
				$filter_types[$line["id"]] = __($line["description"]);
			}

			print "<table width='100%'>";

			print "<tr><td>".__('Match:')."</td>
				<td><input onkeypress=\"return filterCR(event, filterEditSave)\"
					 onkeyup=\"toggleSubmitNotEmpty(this, 'infobox_submit')\"
					 onchange=\"toggleSubmitNotEmpty(this, 'infobox_submit')\"
					 name=\"reg_exp\" class=\"iedit\" value=\"$reg_exp\">";
			
			print "</td></tr><tr><td>".__('On field:')."</td><td>";
			
			print_select_hash("filter_type", $filter_type, $filter_types, "class=\"_iedit\"");	
	
			print "</td></tr>";
			print "<tr><td>".__('Feed:')."</td><td colspan='2'>";

			print_feed_select($link, "feed_id", $feed_id);
			
			print "</td></tr>";
	
			print "<tr><td>".__('Action:')."</td>";
	
			print "<td colspan='2'><select name=\"action_id\"
				onchange=\"filterDlgCheckAction(this)\">";
	
			$result = db_query($link, "SELECT id,description FROM ttrss_filter_actions 
				ORDER BY name");

			while ($line = db_fetch_assoc($result)) {
				$is_sel = ($line["id"] == $action_id) ? "selected" : "";			
				printf("<option value='%d' $is_sel>%s</option>", $line["id"], __($line["description"]));
			}
	
			print "</select>";

			print "</td></tr>";

			print "<tr><td>".__('Params:')."</td>";

			$param_disabled = ($action_id == 4 || $action_id == 6) ? "" : "disabled";

			print "<td><input $param_disabled class='iedit' 
				name=\"action_param\" value=\"$action_param\"></td></tr>";

			if ($enabled) {
				$checked = "checked";
			} else {
				$checked = "";
			}

			print "<tr><td valign='top'>Options:</td><td>
					<input type=\"checkbox\" name=\"enabled\" id=\"enabled\" $checked>
					<label for=\"enabled\">".__('Enabled')."</label><br/>";

			if ($inverse) {
				$checked = "checked";
			} else {
				$checked = "";
			}

			print "<input type=\"checkbox\" name=\"inverse\" id=\"inverse\" $checked>
				<label for=\"inverse\">".__('Inverse match')."</label>";

			print "</td></tr></table>";

			print "</form>";

			print "<div align='right'>";

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
					action_param = '$action_param'
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

			if (!$regexp) return;

			if (!$feed_id) {
				$feed_id = 'NULL';
			} else {
				$feed_id = sprintf("'%s'", db_escape_string($feed_id));
			}

			$result = db_query($link,
				"INSERT INTO ttrss_filters (reg_exp,filter_type,owner_uid,feed_id,
					action_id, action_param, inverse) 
				VALUES 
					('$regexp', '$filter_type','".$_SESSION["uid"]."', 
						$feed_id, '$action_id', '$action_param', $inverse)");

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

		print "<a class='helpLinkPic' href=\"javascript:displayHelpInfobox(2)\">
			<img src='images/sign_quest.gif'></a>";

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
						<td width=\"20%\"><a href=\"javascript:updateFilterList('reg_exp')\">".__('Filter expression')."</a></td>
						<td width=\"\"><a href=\"javascript:updateFilterList('feed_title')\">".__('Feed')."</a></td>
						<td width=\"20%\"><a href=\"javascript:updateFilterList('filter_type')\">".__('Match')."</a></td>
						<!-- <td width=\"15%\"><a href=\"javascript:updateFilterList('action_description')\">".__('Action')."</a></td> -->
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
	
				print "<td><a href=\"javascript:editFilter($filter_id);\">" . 
					$line["reg_exp"] . "</td>";		
	
				print "<td><a href=\"javascript:editFilter($filter_id);\">" . 
					$line["feed_title"] . "</td>";			

				$inverse_label = "";

				if ($inverse) {
					$inverse_label = " <span class='insensitive'>".__('(Inverse)')."</span>";
				}
	
				print "<td><a href=\"javascript:editFilter($filter_id);\">" . 
					$line["filter_type_descr"] . "$inverse_label</td>";		
		
/*				print "<td><a href=\"javascript:editFilter($filter_id);\">" . 
					$line["action_description"]."</td>"; */

				print "<td><a href=\"javascript:editFilter($filter_id);\">" . 
				$line["action_param"] . "</td>";

				print "</tr>";
	
				++$lnum;
			}
	
			if ($lnum == 0) {
				print "<tr><td colspan=\"4\" align=\"center\">".__('No filters defined.')."</td></tr>";
			}
	
			print "</table>";

			print "<p id=\"filterOpToolbar\">";

			print "<input type=\"submit\" class=\"button\" disabled=\"true\"
					onclick=\"return editSelectedFilter()\" value=\"".__('Edit')."\">
				<input type=\"submit\" class=\"button\" disabled=\"true\"
					onclick=\"return removeSelectedFilters()\" value=\"".__('Remove')."\">";

			print "</p>";


/*			print "<div class=\"insensitive\" style=\"float : right\">
				First matching filter is used, filtering is performed
				when importing articles from the feed.</div>"; */

		} else {

			print "<p>".__('No filters defined.')."</p>";

		}
	}
?>

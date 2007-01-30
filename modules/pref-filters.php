<?php
	function module_pref_filters($link) {
		$subop = $_GET["subop"];
		$quiet = $_GET["quiet"];

		if ($subop == "edit") {

			$filter_id = db_escape_string($_GET["id"]);

			$result = db_query($link, 
				"SELECT * FROM ttrss_filters WHERE id = '$filter_id' AND owner_uid = " . $_SESSION["uid"]);

			$reg_exp = htmlspecialchars(db_unescape_string(db_fetch_result($result, 0, "reg_exp")));
			$filter_type = db_fetch_result($result, 0, "filter_type");
			$feed_id = db_fetch_result($result, 0, "feed_id");
			$action_id = db_fetch_result($result, 0, "action_id");
			$action_param = db_fetch_result($result, 0, "action_param");

			$enabled = sql_bool_to_bool(db_fetch_result($result, 0, "enabled"));
			$inverse = sql_bool_to_bool(db_fetch_result($result, 0, "inverse"));

			print "<div id=\"infoBoxTitle\">Filter editor</div>";
			print "<div class=\"infoBoxContents\">";

			print "<form id=\"filter_edit_form\">";

			print "<input type=\"hidden\" name=\"op\" value=\"pref-filters\">";
			print "<input type=\"hidden\" name=\"id\" value=\"$filter_id\">";
			print "<input type=\"hidden\" name=\"subop\" value=\"editSave\">"; 

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
				<td><input onkeypress=\"return filterCR(event, filterEditSave)\"
					 onkeyup=\"toggleSubmitNotEmpty(this, 'infobox_submit')\"
					name=\"reg_exp\" class=\"iedit\" value=\"$reg_exp\">";
			
			print "</td></tr><tr><td>On field:</td><td>";
			
			print_select_hash("filter_type", $filter_type, $filter_types, "class=\"_iedit\"");	
	
			print "</td></tr>";
			print "<tr><td>Feed:</td><td colspan='2'>";

			print_feed_select($link, "feed_id", $feed_id);
			
			print "</td></tr>";
	
			print "<tr><td>Action:</td>";
	
			print "<td colspan='2'><select name=\"action_id\"
				onchange=\"filterDlgCheckAction(this)\">";
	
			$result = db_query($link, "SELECT id,description FROM ttrss_filter_actions 
				ORDER BY name");

			while ($line = db_fetch_assoc($result)) {
				$is_sel = ($line["id"] == $action_id) ? "selected" : "";			
				printf("<option value='%d' $is_sel>%s</option>", $line["id"], $line["description"]);
			}
	
			print "</select>";

			print "</td></tr>";

			print "<tr><td>Params:</td>";

			$param_disabled = ($action_id == 4) ? "" : "disabled";

			print "<td><input $param_disabled class='iedit' 
				name=\"action_param\" value=\"$action_param\"></td></tr>";

			if ($enabled) {
				$checked = "checked";
			} else {
				$checked = "";
			}

			print "<tr><td valign='top'>Options:</td><td>
					<input type=\"checkbox\" name=\"enabled\" id=\"enabled\" $checked>
					<label for=\"enabled\">Enabled</label><br/>";

			if ($inverse) {
				$checked = "checked";
			} else {
				$checked = "";
			}

			print "<input type=\"checkbox\" name=\"inverse\" id=\"inverse\" $checked>
				<label for=\"inverse\">Inverse match</label>";

			print "</td></tr></table>";

			print "</form>";

			print "<div align='right'>";

			print "<input type=\"submit\" 
				id=\"infobox_submit\"
				class=\"button\" onclick=\"return filterEditSave()\" 
				value=\"Save\"> ";

			print "<input class=\"button\"
				type=\"submit\" onclick=\"return filterEditCancel()\" 
				value=\"Cancel\">";

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
		}

		if ($subop == "remove") {

			if (!WEB_DEMO_MODE) {

				$ids = split(",", db_escape_string($_GET["ids"]));

				foreach ($ids as $id) {
					db_query($link, "DELETE FROM ttrss_filters WHERE id = '$id' AND owner_uid = ". $_SESSION["uid"]);
					
				}
			}
		}

		if ($subop == "add") {
		
			if (!WEB_DEMO_MODE) {

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
			} 
		}

		if ($quiet) return;

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

		print "<input type=\"submit\" 
			class=\"button\" 
			onclick=\"return displayDlg('quickAddFilter', false)\" 
			id=\"create_filter_btn\"
			value=\"Create filter\">"; 

		$result = db_query($link, "SELECT 
				ttrss_filters.id AS id,reg_exp,
				ttrss_filter_types.name AS filter_type_name,
				ttrss_filter_types.description AS filter_type_descr,
				enabled,
				inverse,
				feed_id,
				ttrss_filter_actions.description AS action_description,
				ttrss_feeds.title AS feed_title
			FROM 
				ttrss_filter_types,ttrss_filter_actions,ttrss_filters LEFT JOIN
					ttrss_feeds ON (ttrss_filters.feed_id = ttrss_feeds.id)
			WHERE
				filter_type = ttrss_filter_types.id AND
				ttrss_filter_actions.id = action_id AND
				ttrss_filters.owner_uid = ".$_SESSION["uid"]."
			ORDER by $sort");

		if (db_num_rows($result) != 0) {

			print "<form id=\"filter_edit_form\">";			

			print "<p><table width=\"100%\" cellspacing=\"0\" class=\"prefFilterList\" 
				id=\"prefFilterList\">";

			print "<tr><td class=\"selectPrompt\" colspan=\"8\">
				Select: 
					<a href=\"javascript:selectPrefRows('filter', true)\">All</a>,
					<a href=\"javascript:selectPrefRows('filter', false)\">None</a>
				</td</tr>";

			print "<tr class=\"title\">
						<td align='center' width=\"5%\">&nbsp;</td>
						<td width=\"20%\"><a href=\"javascript:updateFilterList('reg_exp')\">Filter expression</a></td>
						<td width=\"20%\"><a href=\"javascript:updateFilterList('feed_title')\">Feed</a></td>
						<td width=\"15%\"><a href=\"javascript:updateFilterList('filter_type')\">Match</a></td>
						<td width=\"15%\"><a href=\"javascript:updateFilterList('action_description')\">Action</a></td>";

			$lnum = 0;
			
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
	
				print "<tr class=\"$class\" $this_row_id>";
	
				$line["reg_exp"] = htmlspecialchars(db_unescape_string($line["reg_exp"]));
	
				if (!$line["feed_title"]) $line["feed_title"] = "All feeds";

				$line["feed_title"] = htmlspecialchars(db_unescape_string($line["feed_title"]));

				print "<td align='center'><input onclick='toggleSelectPrefRow(this, \"filter\");' 
					type=\"checkbox\" id=\"FICHK-".$line["id"]."\"></td>";

				if (!$enabled) {
					$line["reg_exp"] = "<span class=\"insensitive\">" . 
						$line["reg_exp"] . " (Disabled)</span>";
					$line["feed_title"] = "<span class=\"insensitive\">" . 
						$line["feed_title"] . "</span>";
					$line["filter_type_descr"] = "<span class=\"insensitive\">" . 
						$line["filter_type_descr"] . "</span>";
					$line["action_description"] = "<span class=\"insensitive\">" . 
						$line["action_description"] . "</span>";
				}	
	
				print "<td><a href=\"javascript:editFilter($filter_id);\">" . 
					$line["reg_exp"] . "</td>";		
	
				print "<td><a href=\"javascript:editFilter($filter_id);\">" . 
					$line["feed_title"] . "</td>";			

				$inverse_label = "";

				if ($inverse) {
					$inverse_label = " <span class='insensitive'>(Inverse)</span>";
				}
	
				print "<td><a href=\"javascript:editFilter($filter_id);\">" . 
					$line["filter_type_descr"] . "$inverse_label</td>";		
		
				print "<td><a href=\"javascript:editFilter($filter_id);\">" . 
					$line["action_description"] . "</td>";			
				
				print "</tr>";
	
				++$lnum;
			}
	
			if ($lnum == 0) {
				print "<tr><td colspan=\"4\" align=\"center\">No filters defined.</td></tr>";
			}
	
			print "</table>";

			print "</form>";

			print "<p id=\"filterOpToolbar\">";

			print "
					Selection:
				<input type=\"submit\" class=\"button\" disabled=\"true\"
					onclick=\"return editSelectedFilter()\" value=\"Edit\">
				<input type=\"submit\" class=\"button\" disabled=\"true\"
					onclick=\"return removeSelectedFilters()\" value=\"Remove\">";

			print "</p>";

/*			print "<div class=\"insensitive\" style=\"float : right\">
				First matching filter is used, filtering is performed
				when importing articles from the feed.</div>"; */

		} else {

			print "<p>No filters defined.</p>";

		}
	}
?>

<?php
	function module_pref_filters($link) {
		$subop = $_REQUEST["subop"];
		$quiet = $_REQUEST["quiet"];

		if ($subop == "edit") {

			$filter_id = db_escape_string($_REQUEST["id"]);

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
				$date_ops_invisible = 'style="display : none"';
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

			$param_hidden = ($action_id == 4 || $action_id == 6 || $action_id == 7) ? "" : "display : none";

			print "<span id=\"filter_dlg_param_box\" style=\"$param_hidden\">";
			print " " . __("with parameters:") . " ";

			$param_int_hidden = ($action_id != 7) ? "" : "display : none";

			print "<input size=\"20\" style=\"$param_int_hidden\"
					onkeypress=\"return filterCR(event, filterEditSave)\"		
					name=\"action_param\" value=\"$action_param\">";

			$param_int_hidden = ($action_id == 7) ? "" : "display : none";

			print_label_select($link, "action_param_label", $action_param, 
				$param_int_hidden);

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
			print "<button onclick='return removeFilter($filter_id, \"$reg_exp\")'>".
				__('Remove')."</button>";
			print "</div>";

			print "<button onclick=\"return filterEditSave()\">".
				__('Save')."</button> ";

			print "<button onclick=\"return filterEditCancel()\">".
				__('Cancel')."</button>";

			print "</div>";

			return;
		}


		if ($subop == "editSave") {

			global $memcache;

			if ($memcache) $memcache->flush();

			$reg_exp = db_escape_string(trim($_REQUEST["reg_exp"]));
			$filter_type = db_escape_string(trim($_REQUEST["filter_type"]));
			$filter_id = db_escape_string($_REQUEST["id"]);
			$feed_id = db_escape_string($_REQUEST["feed_id"]);
			$action_id = db_escape_string($_REQUEST["action_id"]); 
			$action_param = db_escape_string($_REQUEST["action_param"]); 
			$action_param_label = db_escape_string($_REQUEST["action_param_label"]); 
			$enabled = checkbox_to_sql_bool(db_escape_string($_REQUEST["enabled"]));
			$inverse = checkbox_to_sql_bool(db_escape_string($_REQUEST["inverse"]));

			# for the time being, no other filters use params anyway...
			$filter_param = db_escape_string($_REQUEST["filter_date_modifier"]);

			if (!$feed_id) {
				$feed_id = 'NULL';
			} else {
				$feed_id = sprintf("'%s'", db_escape_string($feed_id));
			}

			/* When processing 'assign label' filters, action_param_label dropbox
			 * overrides action_param */

			if ($action_id == 7) {
				$action_param = $action_param_label;
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
			
			if ($memcache) $memcache->flush();

			$ids = split(",", db_escape_string($_REQUEST["ids"]));

			foreach ($ids as $id) {
				db_query($link, "DELETE FROM ttrss_filters WHERE id = '$id' AND owner_uid = ". $_SESSION["uid"]);
			}
		}

		if ($subop == "add") {

			if ($memcache) $memcache->flush();

			$regexp = db_escape_string(trim($_REQUEST["reg_exp"]));
			$filter_type = db_escape_string(trim($_REQUEST["filter_type"]));
			$feed_id = db_escape_string($_REQUEST["feed_id"]);
			$action_id = db_escape_string($_REQUEST["action_id"]); 
			$action_param = db_escape_string($_REQUEST["action_param"]); 
			$action_param_label = db_escape_string($_REQUEST["action_param_label"]); 
			$inverse = checkbox_to_sql_bool(db_escape_string($_REQUEST["inverse"]));

			# for the time being, no other filters use params anyway...
			$filter_param = db_escape_string($_REQUEST["filter_date_modifier"]);

			if (!$regexp) return;

			if (!$feed_id) {
				$feed_id = 'NULL';
			} else {
				$feed_id = sprintf("'%s'", db_escape_string($feed_id));
			}

			/* When processing 'assign label' filters, action_param_label dropbox
			 * overrides action_param */

			if ($action_id == 7) {
				$action_param = $action_param_label;
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

		$sort = db_escape_string($_REQUEST["sort"]);

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


		$filter_search = db_escape_string($_REQUEST["search"]);

		if (array_key_exists("search", $_REQUEST)) {
			$_SESSION["prefs_filter_search"] = $filter_search;
		} else {
			$filter_search = $_SESSION["prefs_filter_search"];
		}

		print "<div style='float : right'>
			<input id=\"filter_search\" size=\"20\" type=\"search\"
				onfocus=\"javascript:disableHotkeys();\" 
				onblur=\"javascript:enableHotkeys();\"
				onchange=\"javascript:updateFilterList()\" value=\"$filter_search\">
			<button onclick=\"javascript:updateFilterList()\">".__('Search')."</button>
			&nbsp;
			<a class='helpLinkPic' href=\"javascript:displayHelpInfobox(2)\">
			<img style='vertical-align : top;' src='".theme_image($link, "images/sign_quest.png")."'></a>
		</div>";

		print "<button onclick=\"return quickAddFilter()\">".
			__('Create filter')."</button> "; 

		print "<button onclick=\"return editSelectedFilter()\">".
			__('Edit')."</button> ";

		print "<button onclick=\"return removeSelectedFilters()\">".
			__('Remove')."</button> ";

		print "<button onclick=\"rescore_all_feeds()\">".
			__('Rescore articles')."</button> "; 

		if ($filter_search) {
			$filter_search = split(' ', db_escape_string($filter_search));

			$tokens = array();

			foreach ($filter_search as $token) {
				$token = trim($token);

				array_push($tokens, "(
					UPPER(ttrss_filter_actions.description) LIKE UPPER('%$token%') OR 
					UPPER(reg_exp) LIKE UPPER('%$token%') OR 
					UPPER(action_param) LIKE UPPER('%$token%') OR 
					UPPER(ttrss_feeds.title) LIKE UPPER('%$token%') OR
					UPPER(ttrss_filter_types.description) LIKE UPPER('%$token%'))");
			}

			$filter_search_query = "(" . join($tokens, " AND ") . ") AND ";

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
				filter_param,
				filter_type,
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
	
				$filter_id = $line["id"];
				$edit_filter_id = $_REQUEST["id"];

				$enabled = sql_bool_to_bool($line["enabled"]);
				$inverse = sql_bool_to_bool($line["inverse"]);

				$this_row_id = "id=\"FILRR-$filter_id\"";

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

					$lnum = 0;
				}

				$class = ($lnum % 2) ? "even" : "odd";

				print "<tr class=\"$class\" $this_row_id>";
	
				$line["reg_exp"] = htmlspecialchars($line["reg_exp"]);
	
				if (!$line["feed_title"]) $line["feed_title"] = __("All feeds");

				if (!$line["action_param"]) {
					$line["action_param"] = "&mdash;";
				} else if ($line["action_name"] == "score") {

					$score_pic = theme_image($link,
						"images/" . get_score_pic($line["action_param"]));

					$score_pic = "<img class='hlScorePic' src=\"$score_pic\">";

					$line["action_param"] = "$score_pic " . $line["action_param"];

				}

				$line["feed_title"] = htmlspecialchars($line["feed_title"]);

				print "<td align='center'><input onclick='toggleSelectPrefRow(this, \"filter\");' 
					type=\"checkbox\" id=\"FICHK-".$line["id"]."\"></td>";

				$filter_params = array(
					"before" => __("before"),
					"after" => __("after"));

				if ($line["action_name"] == 'label') {

					$tmp_result = db_query($link, "SELECT fg_color, bg_color
						FROM ttrss_labels2 WHERE caption = '".
							db_escape_string($line["action_param"])."' AND
							owner_uid = " . $_SESSION["uid"]);

					$fg_color = db_fetch_result($tmp_result, 0, "fg_color");
					$bg_color = db_fetch_result($tmp_result, 0, "bg_color");

					$tmp = "<div class='labelColorIndicator' id='LICID-$id' 
						style='color : $fg_color; background-color : $bg_color'>
						&alpha;";
					$tmp .= "</div>";

					$line["action_param"] = "$tmp " . $line["action_param"];
				}

				if ($line["filter_type"] == 5) {

					if (!strtotime($line["reg_exp"])) {
						$line["reg_exp"] = "<span class=\"filterDateError\">" . 
							$line["reg_exp"] . "</span>";
					}

					$line["reg_exp"] = __("Date") . " " . 
						$filter_params[$line['filter_param']] . " " .
						$line["reg_exp"];
				}

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

				$inverse_label = "";

				if ($inverse) {
					$inverse_label = " <span class='insensitive'>".__('(Inverse)')."</span>";
				}

				print "<td $onclick>" . $line["reg_exp"] . "$inverse_label</td>";		
				print "<td $onclick>" . $line["feed_title"] . "</td>";			
	
				print "<td $onclick>" . $line["filter_type_descr"] . "</td>";
				print "<td $onclick>" . $line["action_param"] . "</td>";

				print "</tr>";
	
				++$lnum;
			}

			print "</table>";

		} else {

			print "<p>";
			if (!$filter_search) {
				print_warning(__('No filters defined.'));
			} else {
				print_warning(__('No matching filters found.'));
			}
			print "</p>";

		}
	}

	function print_label_select($link, $name, $value, $style = "") {

		$result = db_query($link, "SELECT caption FROM ttrss_labels2
			WHERE owner_uid = '".$_SESSION["uid"]."' ORDER BY caption");

		print "<select default=\"$value\" name=\"" . htmlspecialchars($name) . 
			"\" style=\"$style\" onchange=\"labelSelectOnChange(this)\" >";

		while ($line = db_fetch_assoc($result)) {

			$issel = ($line["caption"] == $value) ? "selected=\"1\"" : "";

			print "<option $issel>" . htmlspecialchars($line["caption"]) . "</option>";

		}

		print "<option value=\"ADD_LABEL\">" .__("Add label...") . "</option>";

		print "</select>";


	}
?>

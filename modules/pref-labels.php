<?php
	function module_pref_labels($link) {

		$subop = $_GET["subop"];

		if ($subop == "save") {

			$id = db_escape_string($_REQUEST["id"]);
			$caption = db_escape_string(trim($_REQUEST["value"]));

			db_query($link, "BEGIN");

			$result = db_query($link, "SELECT caption FROM ttrss_labels2
				WHERE id = '$id' AND owner_uid = ". $_SESSION["uid"]);

			if (db_num_rows($result) != 0) {
				$old_caption = db_fetch_result($result, 0, "caption");

				$result = db_query($link, "SELECT id FROM ttrss_labels2
					WHERE caption = '$caption' AND owner_uid = ". $_SESSION["uid"]);

				if (db_num_rows($result) == 0) {
					if ($caption) {
						$result = db_query($link, "UPDATE ttrss_labels2 SET
							caption = '$caption' WHERE id = '$id' AND
							owner_uid = " . $_SESSION["uid"]);

						/* Update filters that reference label being renamed */

						db_query($link, "UPDATE ttrss_filters SET
							action_param = '$caption' WHERE action_param = '$old_caption'
							AND action_id = 7
							AND owner_uid = " . $_SESSION["uid"]);

						print $_REQUEST["value"];
					} else {
						print $old_caption;
					}
				} else {
					print $old_caption;
				}
			}

			db_query($link, "COMMIT");

			return;
		}

		if ($subop == "remove") {

			$ids = split(",", db_escape_string($_GET["ids"]));

			foreach ($ids as $id) {
				label_remove($link, $id, $_SESSION["uid"]);
			}

		}

		if ($subop == "add") {

			$caption = db_escape_string($_GET["caption"]);

			if ($caption) {

				if (label_create($link, $caption)) {
					print T_sprintf("Created label <b>%s</b>", htmlspecialchars($caption));
				}

			}

			return;
		}

		set_pref($link, "_PREFS_ACTIVE_TAB", "labelConfig");

		$sort = db_escape_string($_GET["sort"]);

		if (!$sort || $sort == "undefined") {
			$sort = "caption";
		}

		$label_search = db_escape_string($_GET["search"]);

		if (array_key_exists("search", $_GET)) {
			$_SESSION["prefs_label_search"] = $label_search;
		} else {
			$label_search = $_SESSION["prefs_label_search"];
		}

		print "<div class=\"feedEditSearch\">
			<input id=\"label_search\" size=\"20\" type=\"search\"
				onfocus=\"javascript:disableHotkeys();\" 
				onblur=\"javascript:enableHotkeys();\"
				onchange=\"javascript:updateLabelList()\" value=\"$label_search\">
			<input type=\"submit\" class=\"button\" 
				onclick=\"javascript:updateLabelList()\" value=\"".__('Search')."\">
			</div>";

		print "<div class=\"prefGenericAddBox\">";

		print"<input type=\"submit\" class=\"button\" 
			id=\"label_create_btn\"
			onclick=\"return addLabel()\" 
			value=\"".__('Create label')."\"></div>";

		if ($label_search) {
			$label_search_query = "caption LIKE '%$label_search%' AND";
		} else {
			$label_search_query = "";
		}

		$result = db_query($link, "SELECT 
				*
			FROM 
				ttrss_labels2
			WHERE 
				$label_search_query
				owner_uid = ".$_SESSION["uid"]."
			ORDER BY $sort");

//		print "<div id=\"infoBoxShadow\"><div id=\"infoBox\">PLACEHOLDER</div></div>";

		if (db_num_rows($result) != 0) {

			print "<p><table width=\"100%\" cellspacing=\"0\" 
				class=\"prefLabelList\" id=\"prefLabelList\">";

			print "<tr><td class=\"selectPrompt\" colspan=\"8\">
				".__('Select:')." 
					<a href=\"javascript:selectPrefRows('label', true)\">".__('All')."</a>,
					<a href=\"javascript:selectPrefRows('label', false)\">".__('None')."</a>
				</td</tr>";

/*			print "<tr class=\"title\">
						<td width=\"5%\">&nbsp;</td>
						<td width=\"95%\"><a href=\"javascript:updateLabelList('caption')\">".__('Caption')."</a></td>
						</td>
						</tr>"; */
			
			$lnum = 0;
			
			while ($line = db_fetch_assoc($result)) {
	
				$class = ($lnum % 2) ? "even" : "odd";
	
				$label_id = $line["id"];
				$this_row_id = "id=\"LILRR-$label_id\"";

				print "<tr class=\"$class\" $this_row_id>";
	
				$line["caption"] = htmlspecialchars($line["caption"]);

				$fg_color = $line["fg_color"];
				$bg_color = $line["bg_color"];

				if (!$fg_color) $fg_color = "black";
				if (!$bg_color) $bg_color = "transparent";

				print "<td width='5%' align='center'><input 
					onclick='toggleSelectPrefRow(this, \"label\");' 
					type=\"checkbox\" id=\"LICHK-".$line["id"]."\"></td>";
	
				print "<td><span class='prefsLabelEntry' 
					style='color : $fg_color; background-color : $bg_color'
					id=\"LILT-".$line["id"]."\">" . $line["caption"] . 
					"</span></td>";

				print "</tr>";
	
				++$lnum;
			}

			print "</table>";
	
			print "<p id=\"labelOpToolbar\">";
			print "<input type=\"submit\" class=\"button\" disabled=\"true\"
				onclick=\"javascript:removeSelectedLabels()\" value=\"".__('Remove')."\">";
			print "</p>";

		} else {
			print "<p>";
			if (!$label_search) {
				print __('No labels defined.');
			} else {
				print __('No matching labels found.');
			}
			print "</p>";

		}
	}
?>

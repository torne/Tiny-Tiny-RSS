<?php
	function module_pref_labels($link) {

		$subop = $_REQUEST["subop"];

		if ($subop == "color-set") {
			$kind = db_escape_string($_REQUEST["kind"]);
			$ids = split(',', db_escape_string($_REQUEST["ids"]));
			$color = db_escape_string($_REQUEST["color"]);
			$fg = db_escape_string($_REQUEST["fg"]);
			$bg = db_escape_string($_REQUEST["bg"]);

			foreach ($ids as $id) {

				if ($kind == "fg" || $kind == "bg") {
					db_query($link, "UPDATE ttrss_labels2 SET
						${kind}_color = '$color' WHERE id = '$id'
						AND owner_uid = " . $_SESSION["uid"]);			
				} else {
					db_query($link, "UPDATE ttrss_labels2 SET
						fg_color = '$fg', bg_color = '$bg' WHERE id = '$id'
						AND owner_uid = " . $_SESSION["uid"]);			
				}
			}

			return;
		}

		if ($subop == "color-reset") {
			$ids = split(',', db_escape_string($_REQUEST["ids"]));

			foreach ($ids as $id) {
				db_query($link, "UPDATE ttrss_labels2 SET
					fg_color = '', bg_color = '' WHERE id = '$id'
					AND owner_uid = " . $_SESSION["uid"]);			
			}

		}

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

						$old_caption = db_escape_string($old_caption);

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

			$ids = split(",", db_escape_string($_REQUEST["ids"]));

			foreach ($ids as $id) {
				label_remove($link, $id, $_SESSION["uid"]);
			}

		}

		if ($subop == "add") {
			$caption = db_escape_string($_REQUEST["caption"]);
			$output = db_escape_string($_REQUEST["output"]);

			if ($caption) {

				if (label_create($link, $caption)) {
					if (!$output) {
						print T_sprintf("Created label <b>%s</b>", htmlspecialchars($caption));
					}
				}

				if ($output == "select") {
					header("Content-Type: text/xml");

					print "<rpc-reply><payload><![CDATA[";

					print_label_select($link, "select_label", 
						$caption, "");

					print "]]></payload></rpc-reply>";
				}
			}

			return;
		}

		set_pref($link, "_PREFS_ACTIVE_TAB", "labelConfig");

		$sort = db_escape_string($_REQUEST["sort"]);

		if (!$sort || $sort == "undefined") {
			$sort = "caption";
		}

		$label_search = db_escape_string($_REQUEST["search"]);

		if (array_key_exists("search", $_REQUEST)) {
			$_SESSION["prefs_label_search"] = $label_search;
		} else {
			$label_search = $_SESSION["prefs_label_search"];
		}

		print "<div style='float : right'>
			<input id=\"label_search\" size=\"20\" type=\"search\"
				onfocus=\"javascript:disableHotkeys();\" 
				onblur=\"javascript:enableHotkeys();\"
				onchange=\"javascript:updateLabelList()\" value=\"$label_search\">
			<button onclick=\"javascript:updateLabelList()\">".__('Search')."</button>
			</div>";

		print "<div class=\"prefGenericAddBox\">";

		print"<button onclick=\"return addLabel()\">".
			__('Create label')."</button> ";

		print "<button onclick=\"javascript:removeSelectedLabels()\">".
			__('Remove')."</button> ";

		print "<button onclick=\"labelColorReset()\">".
			__('Clear colors')."</button>";


		print "</div>";

		if ($label_search) {

			$label_search = split(" ", $label_search);
			$tokens = array();

			foreach ($label_search as $token) {

				$token = trim($token);
				array_push($tokens, "(UPPER(caption) LIKE UPPER('%$token%'))");

			}

			$label_search_query = "(" . join($tokens, " AND ") . ") AND ";
			
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

				if (!$fg_color) $fg_color = "";
				if (!$bg_color) $bg_color = "";

				print "<td width='5%' align='center'><input 
					onclick='toggleSelectPrefRow(this, \"label\");' 
					type=\"checkbox\" id=\"LICHK-".$line["id"]."\"></td>";
	
				$id = $line['id'];

				print "<td>";

				print "<div class='labelColorIndicator' id='LICID-$id' 
					style='color : $fg_color; background-color : $bg_color'
					title='".__('Click to change color')."'
					onclick=\"colorPicker('$id', '$fg_color', '$bg_color')\">&alpha;";
				print_color_picker($id);
				print "</div>";


				print "<span class='prefsLabelEntry' 
					id=\"LILT-".$line["id"]."\">" . $line["caption"] . 
					"</span>";

				print "</td>";

				print "</tr>";
	
				++$lnum;
			}

			print "</table>";
	

		} else {
			print "<p>";
			if (!$label_search) {
				print_warning(__('No labels defined.'));
			} else {
				print_warning(__('No matching labels found.'));
			}
			print "</p>";

		}
	}

	function print_color_picker($id) {

		print "<div id=\"colorPicker-$id\" 
			onmouseover=\"colorPickerActive(true)\"
			onmouseout=\"colorPickerActive(false)\"
			class=\"colorPicker\" style='display : none'>";

		$color_picker_pairs = array(
			array('#ff0000', '#ffffff'),
			array('#009000', '#ffffff'),
			array('#0000ff', '#ffffff'),	
			array('#ff00ff', '#ffffff'),				
			array('#009090', '#ffffff'),

			array('#ffffff', '#ff0000'),
			array('#000000', '#00ff00'),
			array('#ffffff', '#0000ff'),
			array('#ffffff', '#ff00ff'),
			array('#000000', '#00ffff'),

			array('#7b07e1', '#ffffff'),
			array('#0091b4', '#ffffff'),
			array('#00aa71', '#ffffff'),
			array('#7d9e01', '#ffffff'),
			array('#e14a00', '#ffffff'),

			array('#ffffff', '#7b07e1'),
			array('#ffffff', '#00b5e1'),
			array('#ffffff', '#00e196'),
			array('#ffffff', '#b3e100'),
			array('#ffffff', '#e14a00'),

			array('#000000', '#ffffff'),
			array('#ffffff', '#000000'),
			array('#ffffff', '#909000'),
			array('#063064', '#fff7d5'),
			array('#ffffff', '#4E4E90'),
		);

		foreach ($color_picker_pairs as $c) { 
			$fg_color = $c[0];
			$bg_color = $c[1];

			print "<div class='colorPickerEntry' 
				style='color : $fg_color; background-color : $bg_color;'
				onclick=\"colorPickerDo('$id', '$fg_color', '$bg_color')\">&alpha;</div>";

		}

		print "<br clear='both'>";

		print "<br/><b>".__('custom color:')."</b>";
		print "<div class=\"ccPrompt\" onclick=\"labelColorAsk('$id', 'fg')\">".__("foreground")."</div>";
		print "<div class=\"ccPrompt\" onclick=\"labelColorAsk('$id', 'bg')\">".__("background")."</div>";

		print "</div>";
	}

?>

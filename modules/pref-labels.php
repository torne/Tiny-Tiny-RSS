<?php
	// We need to accept raw SQL data in label queries, so not everything is escaped
	// here, this is by design. If you don't like it, disable labels
	// altogether with GLOBAL_ENABLE_LABELS = false

	function module_pref_labels($link) {
		if (!GLOBAL_ENABLE_LABELS) { 

			print __("Sorry, labels have been administratively disabled for this installation. Please contact instance owner or edit configuration file to enable this functionality.");
			return; 
		}

		$subop = $_GET["subop"];

		if ($subop == "edit") {

			$label_id = db_escape_string($_GET["id"]);

			$result = db_query($link, "SELECT sql_exp,description	FROM ttrss_labels WHERE 
				owner_uid = ".$_SESSION["uid"]." AND id = '$label_id' ORDER by description");

			$line = db_fetch_assoc($result);

			$sql_exp = htmlspecialchars($line["sql_exp"]);
			$description = htmlspecialchars($line["description"]);

			print "<div id=\"infoBoxTitle\">Label editor</div>";
			print "<div class=\"infoBoxContents\">";

			print "<form id=\"label_edit_form\" onsubmit='return false'>";

			print "<input type=\"hidden\" name=\"op\" value=\"pref-labels\">";
			print "<input type=\"hidden\" name=\"id\" value=\"$label_id\">";
			print "<input type=\"hidden\" name=\"subop\" value=\"editSave\">"; 

			print "<table width='100%'>";

			print "<tr><td>Caption:</td>
				<td><input onkeypress=\"return filterCR(event, labelEditSave)\"
					onkeyup=\"toggleSubmitNotEmpty(this, 'infobox_submit')\"
					onchange=\"toggleSubmitNotEmpty(this, 'infobox_submit')\"
					 name=\"description\" class=\"iedit\" value=\"$description\">";

			print "</td></tr>";

			print "<tr><td colspan=\"2\">
				<p>SQL Expression:</p>";

			print "<textarea onkeyup=\"toggleSubmitNotEmpty(this, 'infobox_submit')\"
					 rows=\"4\" name=\"sql_exp\" class=\"iedit\">$sql_exp</textarea>";
			print "</td></tr></table>";

			print "</form>";

			print "<div style=\"display : none\" id=\"label_test_result\"></div>";

			print "<div align='right'>";

			print "<div style='float : left'>";
			print "<input type=\"submit\" 
				class=\"button\" onclick=\"return displayHelpInfobox(1)\" 
				value=\"".__('Help')."\"> ";
			print "</div>";

			$is_disabled = (strpos($_SERVER['HTTP_USER_AGENT'], 'Opera') !== FALSE) ? "disabled" : "";

			print "<input $is_disabled type=\"submit\" onclick=\"return labelTest()\" value=\"Test\">
				";

			print "<input type=\"submit\" 
				id=\"infobox_submit\"
				class=\"button\" onclick=\"return labelEditSave()\" 
				value=\"Save\"> ";

			print "<input class=\"button\"
				type=\"submit\" onclick=\"return labelEditCancel()\" 
				value=\"Cancel\">";

			print "</div>";

			return;
		}

		if ($subop == "test") {

			// no escaping here on purpose
			$expr = trim($_GET["expr"]);
			$descr = db_escape_string(trim($_GET["descr"]));

			if (!$expr) {
				print "<div>Error: SQL expression is blank.</div>";
				return;
			}

			print "<div>";

			error_reporting(0);


			$result = db_query($link, 
				"SELECT count(ttrss_entries.id) AS num_matches
					FROM ttrss_entries,ttrss_user_entries,ttrss_feeds
					WHERE ($expr) AND 
						ttrss_user_entries.ref_id = ttrss_entries.id AND
						ttrss_user_entries.feed_id = ttrss_feeds.id AND
						ttrss_user_entries.owner_uid = " . $_SESSION["uid"], false);

			error_reporting (DEFAULT_ERROR_LEVEL);

			if (!$result) {
				print "<div class=\"labelTestError\">" . db_last_error($link) . "</div>";
				print "</div>";
				return;
			}

			$num_matches = db_fetch_result($result, 0, "num_matches");;
			
			if ($num_matches > 0) { 

				if ($num_matches > 10) {
					$showing_msg = ", showing first 10";
				}

				print "<p>Query returned <b>$num_matches</b> matches$showing_msg:</p>";

				$result = db_query($link, 
					"SELECT ttrss_entries.title, 
						(SELECT title FROM ttrss_feeds WHERE id = feed_id) AS feed_title
					FROM ttrss_entries,ttrss_user_entries,ttrss_feeds
							WHERE ($expr) AND 
							ttrss_user_entries.ref_id = ttrss_entries.id
							AND ttrss_user_entries.feed_id = ttrss_feeds.id
							AND ttrss_user_entries.owner_uid = " . $_SESSION["uid"] . " 
							ORDER BY date_entered LIMIT 10", false);

				print "<ul class=\"labelTestResults\">";

				$row_class = "even";
				
				while ($line = db_fetch_assoc($result)) {
					$row_class = toggleEvenOdd($row_class);
					
					print "<li class=\"$row_class\">".$line["title"].
						" <span class=\"insensitive\">(".$line["feed_title"].")</span></li>";
				}
				print "</ul>";

			} else {
				print "<p>Query didn't return any matches.</p>";
			}

			print "</div>";

			return;
		}

		if ($subop == "editSave") {

			$sql_exp = db_escape_string(trim($_GET["sql_exp"]));
			$descr = db_escape_string(trim($_GET["description"]));
			$label_id = db_escape_string($_GET["id"]);
			
			$result = db_query($link, "UPDATE ttrss_labels SET 
				sql_exp = '$sql_exp', 
				description = '$descr'
				WHERE id = '$label_id'");

			if (db_affected_rows($link, $result) != 0) {
				print_notice(T_sprintf("Saved label <b>%s</b>", htmlspecialchars($descr)));
			}

		}

		if ($subop == "remove") {

			if (!WEB_DEMO_MODE) {

				$ids = split(",", db_escape_string($_GET["ids"]));

				foreach ($ids as $id) {
					db_query($link, "DELETE FROM ttrss_labels WHERE id = '$id'");
					
				}
			}
		}

		if ($subop == "add") {

			$sql_exp = db_escape_string(trim($_GET["sql_exp"]));
			$description = db_escape_string($_GET["description"]);

			if (!$sql_exp || !$description) return;

			$result = db_query($link,
				"INSERT INTO ttrss_labels (sql_exp,description,owner_uid) 
				VALUES ('$sql_exp', '$description', '".$_SESSION["uid"]."')");

			if (db_affected_rows($link, $result) != 0) {
				print T_sprintf("Created label <b>%s</b>", htmlspecialchars($description));
			}

			return;
		}

		set_pref($link, "_PREFS_ACTIVE_TAB", "labelConfig");

		$sort = db_escape_string($_GET["sort"]);

		if (!$sort || $sort == "undefined") {
			$sort = "description";
		}

		print "<a class='helpLinkPic' href=\"javascript:displayHelpInfobox(1)\">
			<img src='images/sign_quest.gif'></a>";

		print "<div class=\"prefGenericAddBox\">";

		print"<input type=\"submit\" class=\"button\" 
			id=\"label_create_btn\"
			onclick=\"return displayDlg('quickAddLabel', false)\" 
			value=\"".__('Create label')."\"></div>";

		$result = db_query($link, "SELECT 
				id,sql_exp,description
			FROM 
				ttrss_labels 
			WHERE 
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

			print "<tr class=\"title\">
						<td width=\"5%\">&nbsp;</td>
						<td width=\"30%\"><a href=\"javascript:updateLabelList('description')\">".__('Caption')."</a></td>
						<td width=\"\"><a href=\"javascript:updateLabelList('sql_exp')\">".__('SQL Expression')."</a>
						</td>
						</tr>";
			
			$lnum = 0;
			
			while ($line = db_fetch_assoc($result)) {
	
				$class = ($lnum % 2) ? "even" : "odd";
	
				$label_id = $line["id"];
				$edit_label_id = $_GET["id"];
	
				if ($subop == "edit" && $label_id != $edit_label_id) {
					$class .= "Grayed";
					$this_row_id = "";
				} else {
					$this_row_id = "id=\"LILRR-$label_id\"";
				}
	
				print "<tr class=\"$class\" $this_row_id>";
	
				$line["sql_exp"] = htmlspecialchars($line["sql_exp"]);
				$line["description"] = htmlspecialchars($line["description"]);
	
				if (!$line["description"]) $line["description"] = "[No caption]";
	
				print "<td align='center'><input onclick='toggleSelectPrefRow(this, \"label\");' 
					type=\"checkbox\" id=\"LICHK-".$line["id"]."\"></td>";
	
				print "<td><a href=\"javascript:editLabel($label_id);\">" . 
					$line["description"] . "</td>";			

				print "<td><a href=\"javascript:editLabel($label_id);\">" . 
					$line["sql_exp"] . "</td>";		

				print "</tr>";
	
				++$lnum;
			}
	
			if ($lnum == 0) {
				print "<tr><td colspan=\"4\" align=\"center\">".__('No labels defined.')."</td></tr>";
			}
	
			print "</table>";
	
			print "<p id=\"labelOpToolbar\">";
	
			print "<input type=\"submit\" class=\"button\" disabled=\"true\"
					onclick=\"javascript:editSelectedLabel()\" value=\"".__('Edit')."\">
				<input type=\"submit\" class=\"button\" disabled=\"true\"
				onclick=\"javascript:removeSelectedLabels()\" value=\"".__('Remove')."\">";

		} else {
			print "<p>".__('No labels defined.')."</p>";
		}
	}
?>

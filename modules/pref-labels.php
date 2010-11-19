<?php
	function module_pref_labels($link) {

		$subop = $_REQUEST["subop"];

		if ($subop == "edit") {
			$label_id = db_escape_string($_REQUEST['id']);

			header("Content-Type: text/xml");
			print "<dlg id=\"$subop\">";
			print "<title>" . __("Label Editor") . "</title>";
			print "<content><![CDATA[";

			$result = db_query($link, "SELECT * FROM ttrss_labels2 WHERE
				id = '$label_id' AND owner_uid = " . $_SESSION["uid"]);

			$line = db_fetch_assoc($result);

			print "<form id=\"label_edit_form\" name=\"label_edit_form\"
				onsubmit=\"return false;\">";

			print "<input type=\"hidden\" name=\"id\" value=\"$label_id\">";
			print "<input type=\"hidden\" name=\"op\" value=\"pref-labels\">";
			print "<input type=\"hidden\" name=\"subop\" value=\"save\">";

			print "<div class=\"dlgSec\">".__("Caption")."</div>";

			print "<div class=\"dlgSecCont\">";

			$fg_color = $line['fg_color'];
			$bg_color = $line['bg_color'];

			print "<span class=\"labelColorIndicator\" id=\"label-editor-indicator\" style='color : $fg_color; background-color : $bg_color'>&alpha;</span>";

			print "<input style=\"font-size : 18px\" name=\"caption\" 
				onkeypress=\"return filterCR(event, editLabelSave)\"
				value=\"".htmlspecialchars($line['caption'])."\">";

			print "</div>";
			print "<div class=\"dlgSec\">" . __("Colors") . "</div>";
			print "<div class=\"dlgSecCont\">";

			print "<table cellspacing=\"0\">";

			print "<tr><td>".__("Foreground:")."</td><td>".__("Background:").
				"</td></tr>";

			print "<tr><td style='padding-right : 10px'>";

			print "<input type=\"hidden\" name=\"fg_color\" value=\"$fg_color\">";
			print "<input type=\"hidden\" name=\"bg_color\" value=\"$bg_color\">";

			print "<div dojoType=\"dijit.ColorPalette\">
				<script type=\"dojo/method\" event=\"onChange\" args=\"fg_color\">
					document.forms['label_edit_form'].fg_color.value = fg_color;
					$('label-editor-indicator').setStyle({color: fg_color});
				</script>
			</div>";
			print "</div>";

			print "</td><td>";

			print "<div dojoType=\"dijit.ColorPalette\">
				<script type=\"dojo/method\" event=\"onChange\" args=\"bg_color\">
					document.forms['label_edit_form'].bg_color.value = bg_color;
					$('label-editor-indicator').setStyle({backgroundColor: bg_color});
				</script>
			</div>";
			print "</div>";

			print "</td></tr></table>";
			print "</div>";

			print "</form>";

			print "<div class=\"dlgButtons\">";
			print "<button onclick=\"return editLabelSave()\">".
				__('Save')."</button>";
			print "<button onclick=\"return closeInfoBox()\">".
				__('Cancel')."</button>";
			print "</div>";

			print "]]></content></dlg>";
			return;
		}

		if ($subop == "getlabeltree") {
			$root = array();
			$root['id'] = 'root';
			$root['name'] = __('Labels');
			$root['items'] = array();

			$result = db_query($link, "SELECT *
				FROM ttrss_labels2
				WHERE owner_uid = ".$_SESSION["uid"]."
				ORDER BY caption");

			while ($line = db_fetch_assoc($result)) {
				$label = array();
				$label['id'] = 'LABEL:' . $line['id'];
				$label['bare_id'] = $line['id'];
				$label['name'] = $line['caption'];
				$label['fg_color'] = $line['fg_color'];
				$label['bg_color'] = $line['bg_color'];
				$label['type'] = 'label';
				$label['checkbox'] = false;

				array_push($root['items'], $label);
			}

			$fl = array();
			$fl['identifier'] = 'id';
			$fl['label'] = 'name';
			$fl['items'] = array($root);

			print json_encode($fl);
			return;
		}

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

				$caption = db_escape_string(label_find_caption($link, $id, $_SESSION["uid"]));

				/* Remove cached data */

				db_query($link, "UPDATE ttrss_user_entries SET label_cache = ''
					WHERE label_cache LIKE '%$caption%' AND owner_uid = " . $_SESSION["uid"]);

			}

			return;
		}

		if ($subop == "color-reset") {
			$ids = split(',', db_escape_string($_REQUEST["ids"]));

			foreach ($ids as $id) {
				db_query($link, "UPDATE ttrss_labels2 SET
					fg_color = '', bg_color = '' WHERE id = '$id'
					AND owner_uid = " . $_SESSION["uid"]);			

				$caption = db_escape_string(label_find_caption($link, $id, $_SESSION["uid"]));

				/* Remove cached data */

				db_query($link, "UPDATE ttrss_user_entries SET label_cache = ''
					WHERE label_cache LIKE '%$caption%' AND owner_uid = " . $_SESSION["uid"]);
			}

		}

		if ($subop == "save") {

			$id = db_escape_string($_REQUEST["id"]);
			$caption = db_escape_string(trim($_REQUEST["caption"]));

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

					print "<rpc-reply><payload>";

					print_label_select($link, "select_label", 
						$caption, "");

					print "</payload></rpc-reply>";
				}
			}

			return;
		}

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

		print "<div id=\"pref-label-wrap\" dojoType=\"dijit.layout.BorderContainer\" gutters=\"false\">";
		print "<div id=\"pref-label-header\" dojoType=\"dijit.layout.ContentPane\" region=\"top\">";
		print "<div id=\"pref-label-toolbar\" dojoType=\"dijit.Toolbar\">";

		print "<div dojoType=\"dijit.form.DropDownButton\">".
				"<span>" . __('Select')."</span>";
		print "<div dojoType=\"dijit.Menu\" style=\"display: none;\">";
		print "<div onclick=\"dijit.byId('labelTree').model.setAllChecked(true)\" 
			dojoType=\"dijit.MenuItem\">".__('All')."</div>";
		print "<div onclick=\"dijit.byId('labelTree').model.setAllChecked(false)\" 
			dojoType=\"dijit.MenuItem\">".__('None')."</div>";
		print "</div></div>";

		print"<button dojoType=\"dijit.form.Button\" onclick=\"return addLabel()\">".
			__('Create label')."</button dojoType=\"dijit.form.Button\"> ";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"removeSelectedLabels()\">".
			__('Remove')."</button dojoType=\"dijit.form.Button\"> ";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"labelColorReset()\">".
			__('Clear colors')."</button dojoType=\"dijit.form.Button\">";


		print "</div>"; #toolbar
		print "</div>"; #pane
		print "<div id=\"pref-label-content\" dojoType=\"dijit.layout.ContentPane\" region=\"center\">";

		print "<div id=\"labellistLoading\">
		<img src='images/indicator_tiny.gif'>".
		 __("Loading, please wait...")."</div>";

		print "<div dojoType=\"dojo.data.ItemFileWriteStore\" jsId=\"labelStore\" 
			url=\"backend.php?op=pref-labels&subop=getlabeltree\">
		</div>
		<div dojoType=\"lib.CheckBoxStoreModel\" jsId=\"labelModel\" store=\"labelStore\"
		query=\"{id:'root'}\" rootId=\"root\"
			childrenAttrs=\"items\" checkboxStrict=\"false\" checkboxAll=\"false\">
		</div>
		<div dojoType=\"fox.PrefLabelTree\" id=\"labelTree\" 
			model=\"labelModel\" openOnClick=\"true\">
		<script type=\"dojo/method\" event=\"onLoad\" args=\"item\">
			Element.hide(\"labellistLoading\");
		</script>
		</div>";

		print "</div>"; #pane
		print "</div>"; #container
	}

?>

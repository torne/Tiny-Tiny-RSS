<?php
class Pref_Labels extends Handler_Protected {

	function csrf_ignore($method) {
		$csrf_ignored = array("index", "getlabeltree", "edit");

		return array_search($method, $csrf_ignored) !== false;
	}

	function edit() {
		$label_id = db_escape_string($this->link, $_REQUEST['id']);

		$result = db_query($this->link, "SELECT * FROM ttrss_labels2 WHERE
			id = '$label_id' AND owner_uid = " . $_SESSION["uid"]);

		$line = db_fetch_assoc($result);

		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"id\" value=\"$label_id\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"pref-labels\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"method\" value=\"save\">";

		print "<div class=\"dlgSec\">".__("Caption")."</div>";

		print "<div class=\"dlgSecCont\">";

		$fg_color = $line['fg_color'];
		$bg_color = $line['bg_color'];

		print "<span class=\"labelColorIndicator\" id=\"label-editor-indicator\" style='color : $fg_color; background-color : $bg_color; margin-bottom : 4px; margin-right : 4px'>&alpha;</span>";

		print "<input style=\"font-size : 16px\" name=\"caption\"
			dojoType=\"dijit.form.ValidationTextBox\"
			required=\"true\"
			value=\"".htmlspecialchars($line['caption'])."\">";

		print "</div>";
		print "<div class=\"dlgSec\">" . __("Colors") . "</div>";
		print "<div class=\"dlgSecCont\">";

		print "<table cellspacing=\"0\">";

		print "<tr><td>".__("Foreground:")."</td><td>".__("Background:").
			"</td></tr>";

		print "<tr><td style='padding-right : 10px'>";

		print "<input dojoType=\"dijit.form.TextBox\"
			style=\"display : none\" id=\"labelEdit_fgColor\"
			name=\"fg_color\" value=\"$fg_color\">";
		print "<input dojoType=\"dijit.form.TextBox\"
			style=\"display : none\" id=\"labelEdit_bgColor\"
			name=\"bg_color\" value=\"$bg_color\">";

		print "<div dojoType=\"dijit.ColorPalette\">
			<script type=\"dojo/method\" event=\"onChange\" args=\"fg_color\">
				dijit.byId(\"labelEdit_fgColor\").attr('value', fg_color);
				$('label-editor-indicator').setStyle({color: fg_color});
			</script>
		</div>";
		print "</div>";

		print "</td><td>";

		print "<div dojoType=\"dijit.ColorPalette\">
			<script type=\"dojo/method\" event=\"onChange\" args=\"bg_color\">
				dijit.byId(\"labelEdit_bgColor\").attr('value', bg_color);
				$('label-editor-indicator').setStyle({backgroundColor: bg_color});
			</script>
		</div>";
		print "</div>";

		print "</td></tr></table>";
		print "</div>";

#			print "</form>";

		print "<div class=\"dlgButtons\">";
		print "<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('labelEditDlg').execute()\">".
			__('Save')."</button>";
		print "<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('labelEditDlg').hide()\">".
			__('Cancel')."</button>";
		print "</div>";

		return;
	}

	function getlabeltree() {
		$root = array();
		$root['id'] = 'root';
		$root['name'] = __('Labels');
		$root['items'] = array();

		$result = db_query($this->link, "SELECT *
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

	function colorset() {
		$kind = db_escape_string($this->link, $_REQUEST["kind"]);
		$ids = explode(',', db_escape_string($this->link, $_REQUEST["ids"]));
		$color = db_escape_string($this->link, $_REQUEST["color"]);
		$fg = db_escape_string($this->link, $_REQUEST["fg"]);
		$bg = db_escape_string($this->link, $_REQUEST["bg"]);

		foreach ($ids as $id) {

			if ($kind == "fg" || $kind == "bg") {
				db_query($this->link, "UPDATE ttrss_labels2 SET
					${kind}_color = '$color' WHERE id = '$id'
					AND owner_uid = " . $_SESSION["uid"]);
			} else {
				db_query($this->link, "UPDATE ttrss_labels2 SET
					fg_color = '$fg', bg_color = '$bg' WHERE id = '$id'
					AND owner_uid = " . $_SESSION["uid"]);
			}

			$caption = db_escape_string($this->link, label_find_caption($this->link, $id, $_SESSION["uid"]));

			/* Remove cached data */

			db_query($this->link, "UPDATE ttrss_user_entries SET label_cache = ''
				WHERE label_cache LIKE '%$caption%' AND owner_uid = " . $_SESSION["uid"]);

		}

		return;
	}

	function colorreset() {
		$ids = explode(',', db_escape_string($this->link, $_REQUEST["ids"]));

		foreach ($ids as $id) {
			db_query($this->link, "UPDATE ttrss_labels2 SET
				fg_color = '', bg_color = '' WHERE id = '$id'
				AND owner_uid = " . $_SESSION["uid"]);

			$caption = db_escape_string($this->link, label_find_caption($this->link, $id, $_SESSION["uid"]));

			/* Remove cached data */

			db_query($this->link, "UPDATE ttrss_user_entries SET label_cache = ''
				WHERE label_cache LIKE '%$caption%' AND owner_uid = " . $_SESSION["uid"]);
		}

	}

	function save() {

		$id = db_escape_string($this->link, $_REQUEST["id"]);
		$caption = db_escape_string($this->link, trim($_REQUEST["caption"]));

		db_query($this->link, "BEGIN");

		$result = db_query($this->link, "SELECT caption FROM ttrss_labels2
			WHERE id = '$id' AND owner_uid = ". $_SESSION["uid"]);

		if (db_num_rows($result) != 0) {
			$old_caption = db_fetch_result($result, 0, "caption");

			$result = db_query($this->link, "SELECT id FROM ttrss_labels2
				WHERE caption = '$caption' AND owner_uid = ". $_SESSION["uid"]);

			if (db_num_rows($result) == 0) {
				if ($caption) {
					$result = db_query($this->link, "UPDATE ttrss_labels2 SET
						caption = '$caption' WHERE id = '$id' AND
						owner_uid = " . $_SESSION["uid"]);

					/* Update filters that reference label being renamed */

					$old_caption = db_escape_string($this->link, $old_caption);

					db_query($this->link, "UPDATE ttrss_filters2_actions SET
						action_param = '$caption' WHERE action_param = '$old_caption'
						AND action_id = 7
						AND filter_id IN (SELECT id FROM ttrss_filters2 WHERE owner_uid = ".$_SESSION["uid"].")");

					print $_REQUEST["value"];
				} else {
					print $old_caption;
				}
			} else {
				print $old_caption;
			}
		}

		db_query($this->link, "COMMIT");

		return;
	}

	function remove() {

		$ids = explode(",", db_escape_string($this->link, $_REQUEST["ids"]));

		foreach ($ids as $id) {
			label_remove($this->link, $id, $_SESSION["uid"]);
		}

	}

	function add() {
		$caption = db_escape_string($this->link, $_REQUEST["caption"]);
		$output = db_escape_string($this->link, $_REQUEST["output"]);

		if ($caption) {

			if (label_create($this->link, $caption)) {
				if (!$output) {
					print T_sprintf("Created label <b>%s</b>", htmlspecialchars($caption));
				}
			}

			if ($output == "select") {
				header("Content-Type: text/xml");

				print "<rpc-reply><payload>";

				print_label_select($this->link, "select_label",
					$caption, "");

				print "</payload></rpc-reply>";
			}
		}

		return;
	}

	function index() {

		$sort = db_escape_string($this->link, $_REQUEST["sort"]);

		if (!$sort || $sort == "undefined") {
			$sort = "caption";
		}

		$label_search = db_escape_string($this->link, $_REQUEST["search"]);

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
			url=\"backend.php?op=pref-labels&method=getlabeltree\">
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
		<script type=\"dojo/method\" event=\"onClick\" args=\"item\">
			var id = String(item.id);
			var bare_id = id.substr(id.indexOf(':')+1);

			if (id.match('LABEL:')) {
				editLabel(bare_id);
			}
		</script>
		</div>";

		print "</div>"; #pane

		global $pluginhost;
		$pluginhost->run_hooks($pluginhost::HOOK_PREFS_TAB,
			"hook_prefs_tab", "prefLabels");

		print "</div>"; #container

	}
}

?>

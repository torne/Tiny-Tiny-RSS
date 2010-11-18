<?php
	function module_pref_filters($link) {
		$subop = $_REQUEST["subop"];
		$quiet = $_REQUEST["quiet"];

		if ($subop == "getfiltertree") {
			$root = array();
			$root['id'] = 'root';
			$root['name'] = __('Filters');
			$root['items'] = array();

			$result = db_query($link, "SELECT 
					ttrss_filters.id AS id,reg_exp,
					ttrss_filter_types.name AS filter_type_name,
					ttrss_filter_types.description AS filter_type_descr,
					enabled,
					inverse,
					feed_id,
					action_id,
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
					ttrss_filter_actions.id = action_id AND
					ttrss_filters.owner_uid = ".$_SESSION["uid"]."
				ORDER by action_description, reg_exp");

			$cat = false;
			$cur_action_description = "";

			while ($line = db_fetch_assoc($result)) {
				if ($cur_action_description != $line['action_description']) {

					if ($cat)
						array_push($root['items'], $cat);

					$cat = array();
					$cat['id'] = 'ACTION:' . $line['action_id'];
					$cat['name'] = $line['action_description'];
					$cat['items'] = array();

					$cur_action_description = $line['action_description'];
				}

				if (array_search($line["action_name"], 
					array("score", "tag", "label")) === false) {

						$line["action_param"] = '';
				} else {
					if ($line['action_name'] == 'label') {

						$tmp_result = db_query($link, "SELECT fg_color, bg_color
							FROM ttrss_labels2 WHERE caption = '".
								db_escape_string($line["action_param"])."' AND
								owner_uid = " . $_SESSION["uid"]);

						$fg_color = db_fetch_result($tmp_result, 0, "fg_color");
						$bg_color = db_fetch_result($tmp_result, 0, "bg_color");
	
						$tmp = "<span class=\"labelColorIndicator\" style='color : $fg_color; background-color : $bg_color'>&alpha;</span> " . $line['action_param'];

						$line['action_param'] = $tmp;
					}
				}

				$filter = array();
				$filter['id'] = 'FILTER:' . $line['id'];
				$filter['bare_id'] = $line['id'];
				$filter['name'] = $line['reg_exp'];
				$filter['type'] = $line['filter_type'];
				$filter['enabled'] = sql_bool_to_bool($line['enabled']);
				$filter['param'] = $line['action_param'];
				$filter['inverse'] = sql_bool_to_bool($line['inverse']);
				$filter['checkbox'] = false;

				if ($line['feed_id'])
					$filter['feed'] = $line['feed_title']; 

				array_push($cat['items'], $filter);
			}

			array_push($root['items'], $cat);

			$fl = array();
			$fl['identifier'] = 'id';
			$fl['label'] = 'name';
			$fl['items'] = array($root);

			print json_encode($fl);
			return;
		}

		if ($subop == "edit") {

			$filter_id = db_escape_string($_REQUEST["id"]);

			header("Content-Type: text/xml");
			print "<dlg id=\"$subop\">";
			print "<title>".__('Filter Editor')."</title>";
			print "<content><![CDATA[";

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
			print "&nbsp;<button onclick=\"return filterDlgCheckDate()\">".
				__('Check it')."</button>";
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

			print "]]></content></dlg>";

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
		
		print "<div id=\"pref-filter-wrap\" dojoType=\"dijit.layout.BorderContainer\" gutters=\"false\">";
		print "<div id=\"pref-filter-header\" dojoType=\"dijit.layout.ContentPane\" region=\"top\">";
		print "<div id=\"pref-filter-toolbar\" dojoType=\"dijit.Toolbar\">";

		print "<div style='float : right; padding-right : 4px;'>
			<input dojoType=\"dijit.form.TextBox\" id=\"filter_search\" size=\"20\" type=\"search\"
				onfocus=\"javascript:disableHotkeys();\" 
				onblur=\"javascript:enableHotkeys();\"
				onchange=\"javascript:updateFilterList()\" value=\"$filter_search\">
			<button dojoType=\"dijit.form.Button\" onclick=\"javascript:updateFilterList()\">".__('Search')."</button>
		</div>";

		print "<div dojoType=\"dijit.form.DropDownButton\">".
				"<span>" . __('Select')."</span>";
		print "<div dojoType=\"dijit.Menu\" style=\"display: none;\">";
		print "<div onclick=\"dijit.byId('filterTree').model.setAllChecked(true)\" 
			dojoType=\"dijit.MenuItem\">".__('All')."</div>";
		print "<div onclick=\"dijit.byId('filterTree').model.setAllChecked(false)\" 
			dojoType=\"dijit.MenuItem\">".__('None')."</div>";
		print "</div></div>";
		
		print "<button dojoType=\"dijit.form.Button\" onclick=\"return quickAddFilter()\">".
			__('Create filter')."</button dojoType=\"dijit.form.Button\"> "; 

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return editSelectedFilter()\">".
			__('Edit')."</button dojoType=\"dijit.form.Button\"> ";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return removeSelectedFilters()\">".
			__('Remove')."</button dojoType=\"dijit.form.Button\"> ";

		if (defined('_ENABLE_FEED_DEBUGGING')) {
			print "<button dojoType=\"dijit.form.Button\" onclick=\"rescore_all_feeds()\">".
				__('Rescore articles')."</button dojoType=\"dijit.form.Button\"> "; 
		}

		print "</div>"; # toolbar
		print "</div>"; # toolbar-frame
		print "<div id=\"pref-filter-content\" dojoType=\"dijit.layout.ContentPane\" region=\"center\">";

		print "<div id=\"filterlistLoading\">
		<img src='images/indicator_tiny.gif'>".
		 __("Loading, please wait...")."</div>";

		print "<div dojoType=\"dojo.data.ItemFileWriteStore\" jsId=\"filterStore\" 
			url=\"backend.php?op=pref-filters&subop=getfiltertree\">
		</div>
		<div dojoType=\"lib.CheckBoxStoreModel\" jsId=\"filterModel\" store=\"filterStore\"
		query=\"{id:'root'}\" rootId=\"root\" rootLabel=\"Feeds\"
			childrenAttrs=\"items\" checkboxStrict=\"false\" checkboxAll=\"false\">
		</div>
		<div dojoType=\"fox.PrefFilterTree\" id=\"filterTree\" 
			model=\"filterModel\" openOnClick=\"true\">
		<script type=\"dojo/method\" event=\"onLoad\" args=\"item\">
			Element.hide(\"filterlistLoading\");
		</script>
		</div>";

		print "</div>"; #pane
		print "</div>"; #container
	}

?>

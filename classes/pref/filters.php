<?php
class Pref_Filters extends Handler_Protected {

	function csrf_ignore($method) {
		$csrf_ignored = array("index", "getfiltertree", "edit");

		return array_search($method, $csrf_ignored) !== false;
	}

	function filter_test($filter_type, $reg_exp,
			$action_id, $action_param, $filter_param, $inverse, $feed_id, $cat_id,
			$cat_filter) {

		$result = db_query($this->link, "SELECT name FROM ttrss_filter_types WHERE
			id = " . $filter_type);
		$type_name = db_fetch_result($result, 0, "name");

		$result = db_query($this->link, "SELECT name FROM ttrss_filter_actions WHERE
			id = " . $action_id);
		$action_name = db_fetch_result($result, 0, "name");

		$filter["reg_exp"] = $reg_exp;
		$filter["action"] = $action_name;
		$filter["type"] = $type_name;
		$filter["action_param"] = $action_param;
		$filter["filter_param"] = $filter_param;
		$filter["inverse"] = $inverse;

		$filters[$type_name] = array($filter);

		if ($feed_id)
			$feed = $feed_id;
		else
			$feed = -4;

		$regexp_valid = preg_match('/' . $filter['reg_exp'] . '/',
			$filter['reg_exp']) !== FALSE;

		print __("Articles matching this filter:");

		print "<div class=\"filterTestHolder\">";
		print "<table width=\"100%\" cellspacing=\"0\" id=\"prefErrorFeedList\">";

		if ($regexp_valid) {

			$feed_title = getFeedTitle($this->link, $feed);

			$qfh_ret = queryFeedHeadlines($this->link, $cat_filter ? $cat_id : $feed,
				30, "", $cat_filter, false, false,
				false, "date_entered DESC", 0, $_SESSION["uid"], $filter);

			$result = $qfh_ret[0];

			$articles = array();
			$found = 0;

			while ($line = db_fetch_assoc($result)) {

				$entry_timestamp = strtotime($line["updated"]);
				$entry_tags = get_article_tags($this->link, $line["id"], $_SESSION["uid"]);

				$content_preview = truncate_string(
					strip_tags($line["content_preview"]), 100, '...');

				if ($line["feed_title"])
					$feed_title = $line["feed_title"];

				print "<tr>";

				print "<td width='5%' align='center'><input
					dojoType=\"dijit.form.CheckBox\" checked=\"1\"
					disabled=\"1\" type=\"checkbox\"></td>";
				print "<td>";

				print $line["title"];
				print "&nbsp;(";
				print "<b>" . $feed_title . "</b>";
				print "):&nbsp;";
				print "<span class=\"insensitive\">" . $content_preview . "</span>";
				print " " . mb_substr($line["date_entered"], 0, 16);

				print "</td></tr>";

				$found++;
			}

			if ($found == 0) {
				print "<tr><td align='center'>" .
					__("No articles matching this filter has been found.") . "</td></tr>";
			}
		} else {
			print "<tr><td align='center' class='error'>" .
				__("Invalid regular expression.") . "</td></tr>";

		}

		print "</table>";
		print "</div>";

	}

	function getfiltertree() {
		$root = array();
		$root['id'] = 'root';
		$root['name'] = __('Filters');
		$root['items'] = array();

		$search = $_SESSION["prefs_filter_search"];

		if ($search) $search_qpart = " (LOWER(reg_exp) LIKE LOWER('%$search%')
			OR LOWER(ttrss_feeds.title) LIKE LOWER('%$search%')
			OR LOWER(COALESCE(ttrss_feed_categories.title, '".__('Uncategorized')."'))
				LIKE LOWER('%$search%') AND cat_filter = true) AND ";

		$result = db_query($this->link, "SELECT
				ttrss_filters.id AS id,reg_exp,
				ttrss_filter_types.name AS filter_type_name,
				ttrss_filter_types.description AS filter_type_descr,
				enabled,
				inverse,
				cat_filter,
				feed_id,
				ttrss_filters.cat_id,
				action_id,
				filter_param,
				filter_type,
				ttrss_filter_actions.description AS action_description,
				ttrss_feeds.title AS feed_title,
				COALESCE(ttrss_feed_categories.title, '".__('Uncategorized')."') AS cat_title,
				ttrss_filter_actions.name AS action_name,
				ttrss_filters.action_param AS action_param
			FROM
				ttrss_filter_types,ttrss_filter_actions,ttrss_filters LEFT JOIN
					ttrss_feeds ON (ttrss_filters.feed_id = ttrss_feeds.id) LEFT JOIN
					ttrss_feed_categories ON (ttrss_filters.cat_id = ttrss_feed_categories.id)
			WHERE
				filter_type = ttrss_filter_types.id AND
				ttrss_filter_actions.id = action_id AND
				$search_qpart
				ttrss_filters.owner_uid = ".$_SESSION["uid"]."
			ORDER by action_description, reg_exp");

		$cat = false;
		$cur_action_description = "";

		if (db_num_rows($result) > 0) {

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

						$tmp_result = db_query($this->link, "SELECT fg_color, bg_color
							FROM ttrss_labels2 WHERE caption = '".
								db_escape_string($line["action_param"])."' AND
								owner_uid = " . $_SESSION["uid"]);

						if (db_num_rows($tmp_result) != 0) {
							$fg_color = db_fetch_result($tmp_result, 0, "fg_color");
							$bg_color = db_fetch_result($tmp_result, 0, "bg_color");

							$tmp = "<span class=\"labelColorIndicator\" style='color : $fg_color; background-color : $bg_color'>&alpha;</span> " . $line['action_param'];

							$line['action_param'] = $tmp;
						}
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

				if (sql_bool_to_bool($line['cat_filter']))
					if ($line['cat_id'] != 0) {
						$filter['feed'] = $line['cat_title'];
					} else {
						$filter['feed'] = __('Uncategorized');
					}
				else if ($line['feed_id'])
					$filter['feed'] = $line['feed_title'];

				array_push($cat['items'], $filter);
			}

			array_push($root['items'], $cat);
		}

		$fl = array();
		$fl['identifier'] = 'id';
		$fl['label'] = 'name';
		$fl['items'] = array($root);

		print json_encode($fl);
		return;
	}

	function edit() {

		$filter_id = db_escape_string($_REQUEST["id"]);

		$result = db_query($this->link,
			"SELECT * FROM ttrss_filters WHERE id = '$filter_id' AND owner_uid = " . $_SESSION["uid"]);

		$reg_exp = htmlspecialchars(db_fetch_result($result, 0, "reg_exp"));
		$filter_type = db_fetch_result($result, 0, "filter_type");
		$feed_id = (int) db_fetch_result($result, 0, "feed_id");
		$cat_id = (int) db_fetch_result($result, 0, "cat_id");
		$action_id = db_fetch_result($result, 0, "action_id");
		$action_param = db_fetch_result($result, 0, "action_param");
		$filter_param = db_fetch_result($result, 0, "filter_param");

		$enabled = sql_bool_to_bool(db_fetch_result($result, 0, "enabled"));
		$inverse = sql_bool_to_bool(db_fetch_result($result, 0, "inverse"));
		$cat_filter = sql_bool_to_bool(db_fetch_result($result, 0, "cat_filter"));

		print "<form id=\"filter_edit_form\" onsubmit='return false'>";

		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"pref-filters\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"id\" value=\"$filter_id\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"method\" value=\"editSave\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"csrf_token\" value=\"".$_SESSION['csrf_token']."\">";

		$result = db_query($this->link, "SELECT id,description
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

		print "<span id=\"filterDlg_dateModBox\" $date_ops_invisible>";
		print __("Date") . " ";

		$filter_params = array(
			"before" => __("before"),
			"after" => __("after"));

		print_select_hash("filter_date_modifier", $filter_param,
			$filter_params, 'dojoType="dijit.form.Select"');

		print "&nbsp;</span>";

		print "<input dojoType=\"dijit.form.ValidationTextBox\"
				 required=\"1\"
				 name=\"reg_exp\" style=\"font-size : 16px;\" value=\"$reg_exp\">";

		print "<span id=\"filterDlg_dateChkBox\" $date_ops_invisible>";
		print "&nbsp;<button dojoType=\"dijit.form.Button\" onclick=\"return filterDlgCheckDate()\">".
			__('Check it')."</button>";
		print "</span>";

		print "<hr/> " . __("on field") . " ";
		print_select_hash("filter_type", $filter_type, $filter_types,
			'onchange="filterDlgCheckType(this)" dojoType="dijit.form.Select"');

		print "<hr/>";

		print __("in") . " ";

		print "<span id='filterDlg_feeds'>";
		print_feed_select($this->link, "feed_id", ($cat_filter) ? "CAT:$cat_id" : $feed_id,
			'dojoType="dijit.form.FilteringSelect"');
		print "</span>";

		print "</div>";

		print "<div class=\"dlgSec\">".__("Perform Action")."</div>";

		print "<div class=\"dlgSecCont\">";

		print "<select name=\"action_id\" dojoType=\"dijit.form.Select\"
			onchange=\"filterDlgCheckAction(this)\">";

		$result = db_query($this->link, "SELECT id,description FROM ttrss_filter_actions
			ORDER BY name");

		while ($line = db_fetch_assoc($result)) {
			$is_sel = ($line["id"] == $action_id) ? "selected=\"1\"" : "";
			printf("<option value='%d' $is_sel>%s</option>", $line["id"], __($line["description"]));
		}

		print "</select>";

		$param_hidden = ($action_id == 4 || $action_id == 6 || $action_id == 7) ? "" : "display : none";

		print "<span id=\"filterDlg_paramBox\" style=\"$param_hidden\">";
		print " " . __("with parameters:") . " ";

		$param_int_hidden = ($action_id != 7) ? "" : "display : none";

		print "<input style=\"$param_int_hidden\"
				dojoType=\"dijit.form.TextBox\" id=\"filterDlg_actionParam\"
				name=\"action_param\" value=\"$action_param\">";

		$param_int_hidden = ($action_id == 7) ? "" : "display : none";

		print_label_select($this->link, "action_param_label", $action_param,
		 "style=\"$param_int_hidden\"" .
		 'id="filterDlg_actionParamLabel" dojoType="dijit.form.Select"');

		print "</span>";

		print "&nbsp;"; // tiny layout hack

		print "</div>";

		print "<div class=\"dlgSec\">".__("Options")."</div>";
		print "<div class=\"dlgSecCont\">";

		print "<div style=\"line-height : 100%\">";

		if ($enabled) {
			$checked = "checked=\"1\"";
		} else {
			$checked = "";
		}

		print "<input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" name=\"enabled\" id=\"enabled\" $checked>
				<label for=\"enabled\">".__('Enabled')."</label><hr/>";

		if ($inverse) {
			$checked = "checked=\"1\"";
		} else {
			$checked = "";
		}

		print "<input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" name=\"inverse\" id=\"inverse\" $checked>
			<label for=\"inverse\">".__('Inverse match')."</label><hr/>";

#		if ($cat_filter) {
#			$checked = "checked=\"1\"";
#		} else {
#			$checked = "";
#		}

#		print "<input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" name=\"cat_filter\" id=\"cat_filter\" onchange=\"filterDlgCheckCat(this)\" $checked>
#				<label for=\"cat_filter\">".__('Apply to category')."</label><hr/>";

		print "</div>";
		print "</div>";

		print "<div class=\"dlgButtons\">";

		print "<div style=\"float : left\">";
		print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').removeFilter()\">".
			__('Remove')."</button>";
		print "</div>";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').test()\">".
			__('Test')."</button> ";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').execute()\">".
			__('Save')."</button> ";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').hide()\">".
			__('Cancel')."</button>";

		print "</div>";
	}

	function editSave() {

		$savemode = db_escape_string($_REQUEST["savemode"]);
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

		if (strpos($feed_id, "CAT:") === 0) {
			$cat_filter = true;
			$cat_id = (int) substr($feed_id, 4);
			$feed_id = "NULL";
		} else {
			$cat_filter = false;
			$feed_id = (int) $feed_id;
			$cat_id = "NULL";
		}

		$raw_feed_id = (int) $feed_id;
		$raw_cat_id = (int) $cat_id;

		if (!$feed_id) $feed_id = "NULL";
		if (!$cat_id) $feed_id = "NULL";

		/* When processing 'assign label' filters, action_param_label dropbox
		 * overrides action_param */

		if ($action_id == 7) {
			$action_param = $action_param_label;
		}

		if ($action_id == 6) {
			$action_param = (int) str_replace("+", "", $action_param);
		}

		if ($savemode != "test") {
			$result = db_query($this->link, "UPDATE ttrss_filters SET
				reg_exp = '$reg_exp',
				feed_id = $feed_id,
				cat_id = $cat_id,
				action_id = '$action_id',
				filter_type = '$filter_type',
				enabled = $enabled,
				inverse = $inverse,
				cat_filter = ".bool_to_sql_bool($cat_filter).",
				action_param = '$action_param',
				filter_param = '$filter_param'
				WHERE id = '$filter_id' AND owner_uid = " . $_SESSION["uid"]);
		} else {

			$this->filter_test($filter_type, $reg_exp,
				$action_id, $action_param, $filter_param, sql_bool_to_bool($inverse),
				$raw_feed_id, $raw_cat_id, $cat_filter);

			print "<div align='center'>";
			print "<button dojoType=\"dijit.form.Button\"
				onclick=\"return dijit.byId('filterTestDlg').hide()\">".
				__('Close this window')."</button>";
			print "</div>";

		}
	}

	function remove() {

		$ids = split(",", db_escape_string($_REQUEST["ids"]));

		foreach ($ids as $id) {
			db_query($this->link, "DELETE FROM ttrss_filters WHERE id = '$id' AND owner_uid = ". $_SESSION["uid"]);
		}
	}

	function add() {

		$savemode = db_escape_string($_REQUEST["savemode"]);
		$regexp = db_escape_string(trim($_REQUEST["reg_exp"]));
		$filter_type = db_escape_string(trim($_REQUEST["filter_type"]));
		$feed_id = db_escape_string($_REQUEST["feed_id"]);
#		$cat_id = db_escape_string($_REQUEST["cat_id"]);
		$action_id = db_escape_string($_REQUEST["action_id"]);
		$action_param = db_escape_string($_REQUEST["action_param"]);
		$action_param_label = db_escape_string($_REQUEST["action_param_label"]);
		$inverse = checkbox_to_sql_bool(db_escape_string($_REQUEST["inverse"]));
#		$cat_filter = checkbox_to_sql_bool(db_escape_string($_REQUEST["cat_filter"]));

		# for the time being, no other filters use params anyway...
		$filter_param = db_escape_string($_REQUEST["filter_date_modifier"]);

		if (!$regexp) return;

		if (strpos($feed_id, "CAT:") === 0) {
			$cat_filter = true;
			$cat_id = (int) substr($feed_id, 4);
			$feed_id = "NULL";
		} else {
			$cat_filter = false;
			$feed_id = (int) $feed_id;
			$cat_id = "NULL";
		}

		$raw_feed_id = (int) $feed_id;
		$raw_cat_id = (int) $cat_id;

		if (!$feed_id) $feed_id = "NULL";
		if (!$cat_id) $feed_id = "NULL";

		/* When processing 'assign label' filters, action_param_label dropbox
		 * overrides action_param */

		if ($action_id == 7) {
			$action_param = $action_param_label;
		}

		if ($action_id == 6) {
			$action_param = (int) str_replace("+", "", $action_param);
		}

		if ($savemode != "test") {
			$result = db_query($this->link,
				"INSERT INTO ttrss_filters (reg_exp,filter_type,owner_uid,feed_id,
					action_id, action_param, inverse, filter_param, cat_id, cat_filter)
				VALUES
					('$regexp', '$filter_type','".$_SESSION["uid"]."',
					$feed_id, '$action_id', '$action_param', $inverse,
					'$filter_param', $cat_id, ".bool_to_sql_bool($cat_filter).")");

			if (db_affected_rows($this->link, $result) != 0) {
				print T_sprintf("Created filter <b>%s</b>", htmlspecialchars($regexp));
			}

		} else {

			$this->filter_test($filter_type, $regexp,
				$action_id, $action_param, $filter_param, sql_bool_to_bool($inverse),
				$raw_feed_id, $raw_cat_id,
				$cat_filter);

			print "<div align='center'>";
			print "<button dojoType=\"dijit.form.Button\"
				onclick=\"return dijit.byId('filterTestDlg').hide()\">".
				__('Close this window')."</button>";
			print "</div>";

		}
	}

	function index() {

		$sort = db_escape_string($_REQUEST["sort"]);

		if (!$sort || $sort == "undefined") {
			$sort = "reg_exp";
		}

		$result = db_query($this->link, "SELECT id,description
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

		$filter_search = db_escape_string($_REQUEST["search"]);

		if (array_key_exists("search", $_REQUEST)) {
			$_SESSION["prefs_filter_search"] = $filter_search;
		} else {
			$filter_search = $_SESSION["prefs_filter_search"];
		}

		print "<div style='float : right; padding-right : 4px;'>
			<input dojoType=\"dijit.form.TextBox\" id=\"filter_search\" size=\"20\" type=\"search\"
				value=\"$filter_search\">
			<button dojoType=\"dijit.form.Button\" onclick=\"updateFilterList()\">".
				__('Search')."</button>
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
			__('Create filter')."</button> ";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return editSelectedFilter()\">".
			__('Edit')."</button> ";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return removeSelectedFilters()\">".
			__('Remove')."</button> ";

		if (defined('_ENABLE_FEED_DEBUGGING')) {
			print "<button dojoType=\"dijit.form.Button\" onclick=\"rescore_all_feeds()\">".
				__('Rescore articles')."</button> ";
		}

		print "</div>"; # toolbar
		print "</div>"; # toolbar-frame
		print "<div id=\"pref-filter-content\" dojoType=\"dijit.layout.ContentPane\" region=\"center\">";

		print "<div id=\"filterlistLoading\">
		<img src='images/indicator_tiny.gif'>".
		 __("Loading, please wait...")."</div>";

		print "<div dojoType=\"dojo.data.ItemFileWriteStore\" jsId=\"filterStore\"
			url=\"backend.php?op=pref-filters&method=getfiltertree\">
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
		<script type=\"dojo/method\" event=\"onClick\" args=\"item\">
			var id = String(item.id);
			var bare_id = id.substr(id.indexOf(':')+1);

			if (id.match('FILTER:')) {
				editFilter(bare_id);
			}
		</script>

		</div>";

		print "</div>"; #pane
		print "</div>"; #container

	}
}
?>

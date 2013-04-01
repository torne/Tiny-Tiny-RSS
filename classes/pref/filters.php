<?php
class Pref_Filters extends Handler_Protected {

	function csrf_ignore($method) {
		$csrf_ignored = array("index", "getfiltertree", "edit", "newfilter", "newrule",
			"newaction", "savefilterorder");

		return array_search($method, $csrf_ignored) !== false;
	}

	function filtersortreset() {
		db_query($this->link, "UPDATE ttrss_filters2
				SET order_id = 0 WHERE owner_uid = " . $_SESSION["uid"]);
		return;
	}

	function savefilterorder() {
		$data = json_decode($_POST['payload'], true);

		#file_put_contents("/tmp/saveorder.json", $_POST['payload']);
		#$data = json_decode(file_get_contents("/tmp/saveorder.json"), true);

		if (!is_array($data['items']))
			$data['items'] = json_decode($data['items'], true);

		$index = 0;

		if (is_array($data) && is_array($data['items'])) {
			foreach ($data['items'][0]['items'] as $item) {
				$filter_id = (int) str_replace("FILTER:", "", $item['_reference']);

				if ($filter_id > 0) {

					db_query($this->link, "UPDATE ttrss_filters2 SET
						order_id = $index WHERE id = '$filter_id' AND
						owner_uid = " .$_SESSION["uid"]);

					++$index;
				}
			}
		}

		return;
	}


	function testFilter() {
		$filter = array();

		$filter["enabled"] = true;
		$filter["match_any_rule"] = sql_bool_to_bool(
			checkbox_to_sql_bool(db_escape_string($this->link, $_REQUEST["match_any_rule"])));
		$filter["inverse"] = sql_bool_to_bool(
			checkbox_to_sql_bool(db_escape_string($this->link, $_REQUEST["inverse"])));

		$filter["rules"] = array();

		$result = db_query($this->link, "SELECT id,name FROM ttrss_filter_types");

		$filter_types = array();
		while ($line = db_fetch_assoc($result)) {
			$filter_types[$line["id"]] = $line["name"];
		}

		$rctr = 0;
		foreach ($_REQUEST["rule"] AS $r) {
			$rule = json_decode($r, true);

			if ($rule && $rctr < 5) {
				$rule["type"] = $filter_types[$rule["filter_type"]];
				unset($rule["filter_type"]);

				if (strpos($rule["feed_id"], "CAT:") === 0) {
					$rule["cat_id"] = (int) substr($rule["feed_id"], 4);
					unset($rule["feed_id"]);
				}

				array_push($filter["rules"], $rule);

				++$rctr;
			} else {
				break;
			}
		}

		$feed_title = getFeedTitle($this->link, $feed);

		$qfh_ret = queryFeedHeadlines($this->link, -4, 30, "", false, false, false,
			"date_entered DESC", 0, $_SESSION["uid"], $filter);

		$result = $qfh_ret[0];

		$articles = array();
		$found = 0;

		print __("Articles matching this filter:");

		print "<div class=\"filterTestHolder\">";
		print "<table width=\"100%\" cellspacing=\"0\" id=\"prefErrorFeedList\">";

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
				__("No recent articles matching this filter have been found.");

			print "</td></tr><tr><td class='insensitive' align='center'>";

			print __("Complex expressions might not give results while testing due to issues with database server regexp implementation.");

			print "</td></tr>";

		}

		print "</table></div>";

		print "<div style='text-align : center'>";
		print "<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('filterTestDlg').hide()\">".
			__('Close this window')."</button>";
		print "</div>";

	}


	function getfiltertree() {
		$root = array();
		$root['id'] = 'root';
		$root['name'] = __('Filters');
		$root['items'] = array();

		$filter_search = $_SESSION["prefs_filter_search"];

		$result = db_query($this->link, "SELECT *,
			(SELECT action_param FROM ttrss_filters2_actions
				WHERE filter_id = ttrss_filters2.id ORDER BY id LIMIT 1) AS action_param,
			(SELECT action_id FROM ttrss_filters2_actions
				WHERE filter_id = ttrss_filters2.id ORDER BY id LIMIT 1) AS action_id,
			(SELECT description FROM ttrss_filter_actions
				WHERE id = (SELECT action_id FROM ttrss_filters2_actions
					WHERE filter_id = ttrss_filters2.id ORDER BY id LIMIT 1)) AS action_name,
			(SELECT reg_exp FROM ttrss_filters2_rules
				WHERE filter_id = ttrss_filters2.id ORDER BY id LIMIT 1) AS reg_exp
			FROM ttrss_filters2 WHERE
			owner_uid = ".$_SESSION["uid"]." ORDER BY order_id");


		$action_id = -1;
		$folder = array();
		$folder['items'] = array();

		while ($line = db_fetch_assoc($result)) {

			/* if ($action_id != $line["action_id"]) {
				if (count($folder['items']) > 0) {
					array_push($root['items'], $folder);
				}

				$folder = array();
				$folder['id'] = $line["action_id"];
				$folder['name'] = __($line["action_name"]);
				$folder['items'] = array();
				$action_id = $line["action_id"];
			} */

			$name = $this->getFilterName($line["id"]);

			$match_ok = false;
			if ($filter_search) {
				$rules_result = db_query($this->link,
					"SELECT reg_exp FROM ttrss_filters2_rules WHERE filter_id = ".$line["id"]);

				while ($rule_line = db_fetch_assoc($rules_result)) {
					if (mb_strpos($rule_line['reg_exp'], $filter_search) !== false) {
						$match_ok = true;
						break;
					}
				}
			}

			if ($line['action_id'] == 7) {
				$label_result = db_query($this->link, "SELECT fg_color, bg_color
					FROM ttrss_labels2 WHERE caption = '".db_escape_string($this->link, $line['action_param'])."' AND
						owner_uid = " . $_SESSION["uid"]);

				if (db_num_rows($label_result) > 0) {
					$fg_color = db_fetch_result($label_result, 0, "fg_color");
					$bg_color = db_fetch_result($label_result, 0, "bg_color");

					$name[1] = "<span class=\"labelColorIndicator\" id=\"label-editor-indicator\" style='color : $fg_color; background-color : $bg_color; margin-right : 4px'>&alpha;</span>" . $name[1];
				}
			}

			$filter = array();
			$filter['id'] = 'FILTER:' . $line['id'];
			$filter['bare_id'] = $line['id'];
			$filter['name'] = $name[0];
			$filter['param'] = $name[1];
			$filter['checkbox'] = false;
			$filter['enabled'] = sql_bool_to_bool($line["enabled"]);

			if (!$filter_search || $match_ok) {
				array_push($folder['items'], $filter);
			}
		}

		/* if (count($folder['items']) > 0) {
			array_push($root['items'], $folder);
		} */

		$root['items'] = $folder['items'];

		$fl = array();
		$fl['identifier'] = 'id';
		$fl['label'] = 'name';
		$fl['items'] = array($root);

		print json_encode($fl);
		return;
	}

	function edit() {

		$filter_id = db_escape_string($this->link, $_REQUEST["id"]);

		$result = db_query($this->link,
			"SELECT * FROM ttrss_filters2 WHERE id = '$filter_id' AND owner_uid = " . $_SESSION["uid"]);

		$enabled = sql_bool_to_bool(db_fetch_result($result, 0, "enabled"));
		$match_any_rule = sql_bool_to_bool(db_fetch_result($result, 0, "match_any_rule"));
		$inverse = sql_bool_to_bool(db_fetch_result($result, 0, "inverse"));
		$title = htmlspecialchars(db_fetch_result($result, 0, "title"));

		print "<form id=\"filter_edit_form\" onsubmit='return false'>";

		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"pref-filters\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"id\" value=\"$filter_id\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"method\" value=\"editSave\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"csrf_token\" value=\"".$_SESSION['csrf_token']."\">";

		print "<div class=\"dlgSec\">".__("Caption")."</div>";

		print "<input required=\"true\" dojoType=\"dijit.form.ValidationTextBox\" style=\"width : 20em;\" name=\"title\" value=\"$title\">";

		print "</div>";

		print "<div class=\"dlgSec\">".__("Match")."</div>";

		print "<div dojoType=\"dijit.Toolbar\">";

		print "<div dojoType=\"dijit.form.DropDownButton\">".
				"<span>" . __('Select')."</span>";
		print "<div dojoType=\"dijit.Menu\" style=\"display: none;\">";
		print "<div onclick=\"dijit.byId('filterEditDlg').selectRules(true)\"
			dojoType=\"dijit.MenuItem\">".__('All')."</div>";
		print "<div onclick=\"dijit.byId('filterEditDlg').selectRules(false)\"
			dojoType=\"dijit.MenuItem\">".__('None')."</div>";
		print "</div></div>";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').addRule()\">".
			__('Add')."</button> ";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').deleteRule()\">".
			__('Delete')."</button> ";

		print "</div>";

		print "<ul id='filterDlg_Matches'>";

		$rules_result = db_query($this->link, "SELECT * FROM ttrss_filters2_rules
			WHERE filter_id = '$filter_id' ORDER BY reg_exp, id");

		while ($line = db_fetch_assoc($rules_result)) {
			if (sql_bool_to_bool($line["cat_filter"])) {
				$line["feed_id"] = "CAT:" . (int)$line["cat_id"];
			}

			unset($line["cat_filter"]);
			unset($line["cat_id"]);
			unset($line["filter_id"]);
			unset($line["id"]);
			if (!sql_bool_to_bool($line["inverse"])) unset($line["inverse"]);

			$data = htmlspecialchars(json_encode($line));

			print "<li><input dojoType='dijit.form.CheckBox' type='checkbox' onclick='toggleSelectListRow2(this)'>".
				"<span onclick=\"dijit.byId('filterEditDlg').editRule(this)\">".$this->getRuleName($line)."</span>".
				"<input type='hidden' name='rule[]' value=\"$data\"/></li>";
		}

		print "</ul>";

		print "</div>";

		print "<div class=\"dlgSec\">".__("Apply actions")."</div>";

		print "<div dojoType=\"dijit.Toolbar\">";

		print "<div dojoType=\"dijit.form.DropDownButton\">".
				"<span>" . __('Select')."</span>";
		print "<div dojoType=\"dijit.Menu\" style=\"display: none;\">";
		print "<div onclick=\"dijit.byId('filterEditDlg').selectActions(true)\"
			dojoType=\"dijit.MenuItem\">".__('All')."</div>";
		print "<div onclick=\"dijit.byId('filterEditDlg').selectActions(false)\"
			dojoType=\"dijit.MenuItem\">".__('None')."</div>";
		print "</div></div>";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').addAction()\">".
			__('Add')."</button> ";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').deleteAction()\">".
			__('Delete')."</button> ";

		print "</div>";

		print "<ul id='filterDlg_Actions'>";

		$actions_result = db_query($this->link, "SELECT * FROM ttrss_filters2_actions
			WHERE filter_id = '$filter_id' ORDER BY id");

		while ($line = db_fetch_assoc($actions_result)) {
			$line["action_param_label"] = $line["action_param"];

			unset($line["filter_id"]);
			unset($line["id"]);

			$data = htmlspecialchars(json_encode($line));

			print "<li><input dojoType='dijit.form.CheckBox' type='checkbox' onclick='toggleSelectListRow2(this)'>".
				"<span onclick=\"dijit.byId('filterEditDlg').editAction(this)\">".$this->getActionName($line)."</span>".
				"<input type='hidden' name='action[]' value=\"$data\"/></li>";
		}

		print "</ul>";

		print "</div>";

		if ($enabled) {
			$checked = "checked=\"1\"";
		} else {
			$checked = "";
		}

		print "<input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" name=\"enabled\" id=\"enabled\" $checked>
				<label for=\"enabled\">".__('Enabled')."</label>";

		if ($match_any_rule) {
			$checked = "checked=\"1\"";
		} else {
			$checked = "";
		}

		print "<br/><input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" name=\"match_any_rule\" id=\"match_any_rule\" $checked>
				<label for=\"match_any_rule\">".__('Match any rule')."</label>";

		if ($inverse) {
			$checked = "checked=\"1\"";
		} else {
			$checked = "";
		}

		print "<br/><input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" name=\"inverse\" id=\"inverse\" $checked>
				<label for=\"inverse\">".__('Inverse matching')."</label>";

		print "<p/>";

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

	private function getRuleName($rule) {
		if (!$rule) $rule = json_decode($_REQUEST["rule"], true);

		$feed_id = $rule["feed_id"];

		if (strpos($feed_id, "CAT:") === 0) {
			$feed_id = (int) substr($feed_id, 4);
			$feed = getCategoryTitle($this->link, $feed_id);
		} else {
			$feed_id = (int) $feed_id;

			if ($rule["feed_id"])
				$feed = getFeedTitle($this->link, (int)$rule["feed_id"]);
			else
				$feed = __("All feeds");
		}

		$result = db_query($this->link, "SELECT description FROM ttrss_filter_types
			WHERE id = ".(int)$rule["filter_type"]);
		$filter_type = db_fetch_result($result, 0, "description");

		return T_sprintf("%s on %s in %s %s", strip_tags($rule["reg_exp"]),
			$filter_type, $feed, isset($rule["inverse"]) ? __("(inverse)") : "");
	}

	function printRuleName() {
		print $this->getRuleName(json_decode($_REQUEST["rule"], true));
	}

	private function getActionName($action) {
		$result = db_query($this->link, "SELECT description FROM
			ttrss_filter_actions WHERE id = " .(int)$action["action_id"]);

		$title = __(db_fetch_result($result, 0, "description"));

		if ($action["action_id"] == 4 || $action["action_id"] == 6 ||
			$action["action_id"] == 7)
				$title .= ": " . $action["action_param"];

		return $title;
	}

	function printActionName() {
		print $this->getActionName(json_decode($_REQUEST["action"], true));
	}

	function editSave() {
		if ($_REQUEST["savemode"] && $_REQUEST["savemode"] == "test") {
			return $this->testFilter();
		}

#		print_r($_REQUEST);

		$filter_id = db_escape_string($this->link, $_REQUEST["id"]);
		$enabled = checkbox_to_sql_bool(db_escape_string($this->link, $_REQUEST["enabled"]));
		$match_any_rule = checkbox_to_sql_bool(db_escape_string($this->link, $_REQUEST["match_any_rule"]));
		$inverse = checkbox_to_sql_bool(db_escape_string($this->link, $_REQUEST["inverse"]));
		$title = db_escape_string($this->link, $_REQUEST["title"]);

		$result = db_query($this->link, "UPDATE ttrss_filters2 SET enabled = $enabled,
			match_any_rule = $match_any_rule,
			inverse = $inverse,
			title = '$title'
			WHERE id = '$filter_id'
			AND owner_uid = ". $_SESSION["uid"]);

		$this->saveRulesAndActions($filter_id);

	}

	function remove() {

		$ids = split(",", db_escape_string($this->link, $_REQUEST["ids"]));

		foreach ($ids as $id) {
			db_query($this->link, "DELETE FROM ttrss_filters2 WHERE id = '$id' AND owner_uid = ". $_SESSION["uid"]);
		}
	}

	private function saveRulesAndActions($filter_id) {

		db_query($this->link, "DELETE FROM ttrss_filters2_rules WHERE filter_id = '$filter_id'");
		db_query($this->link, "DELETE FROM ttrss_filters2_actions WHERE filter_id = '$filter_id'");

		if ($filter_id) {
			/* create rules */

			$rules = array();
			$actions = array();

			foreach ($_REQUEST["rule"] as $rule) {
				$rule = json_decode($rule, true);
				unset($rule["id"]);

				if (array_search($rule, $rules) === false) {
					array_push($rules, $rule);
				}
			}

			foreach ($_REQUEST["action"] as $action) {
				$action = json_decode($action, true);
				unset($action["id"]);

				if (array_search($action, $actions) === false) {
					array_push($actions, $action);
				}
			}

			foreach ($rules as $rule) {
				if ($rule) {

					$reg_exp = strip_tags(db_escape_string($this->link, trim($rule["reg_exp"])));
					$inverse = isset($rule["inverse"]) ? "true" : "false";

					$filter_type = (int) db_escape_string($this->link, trim($rule["filter_type"]));
					$feed_id = db_escape_string($this->link, trim($rule["feed_id"]));

					if (strpos($feed_id, "CAT:") === 0) {

						$cat_filter = bool_to_sql_bool(true);
						$cat_id = (int) substr($feed_id, 4);
						$feed_id = "NULL";

						if (!$cat_id) $cat_id = "NULL"; // Uncategorized
					} else {
						$cat_filter = bool_to_sql_bool(false);
						$feed_id = (int) $feed_id;
						$cat_id = "NULL";

						if (!$feed_id) $feed_id = "NULL"; // Uncategorized
					}

					$query = "INSERT INTO ttrss_filters2_rules
						(filter_id, reg_exp,filter_type,feed_id,cat_id,cat_filter,inverse) VALUES
						('$filter_id', '$reg_exp', '$filter_type', $feed_id, $cat_id, $cat_filter, $inverse)";

					db_query($this->link, $query);
				}
			}

			foreach ($actions as $action) {
				if ($action) {

					$action_id = (int) db_escape_string($this->link, $action["action_id"]);
					$action_param = db_escape_string($this->link, $action["action_param"]);
					$action_param_label = db_escape_string($this->link, $action["action_param_label"]);

					if ($action_id == 7) {
						$action_param = $action_param_label;
					}

					if ($action_id == 6) {
						$action_param = (int) str_replace("+", "", $action_param);
					}

					$query = "INSERT INTO ttrss_filters2_actions
						(filter_id, action_id, action_param) VALUES
						('$filter_id', '$action_id', '$action_param')";

					db_query($this->link, $query);
				}
			}
		}


	}

	function add() {
		if ($_REQUEST["savemode"] && $_REQUEST["savemode"] == "test") {
			return $this->testFilter();
		}

#		print_r($_REQUEST);

		$enabled = checkbox_to_sql_bool($_REQUEST["enabled"]);
		$match_any_rule = checkbox_to_sql_bool($_REQUEST["match_any_rule"]);
		$title = db_escape_string($this->link, $_REQUEST["title"]);

		db_query($this->link, "BEGIN");

		/* create base filter */

		$result = db_query($this->link, "INSERT INTO ttrss_filters2
			(owner_uid, match_any_rule, enabled, title) VALUES
			(".$_SESSION["uid"].",$match_any_rule,$enabled, '$title')");

		$result = db_query($this->link, "SELECT MAX(id) AS id FROM ttrss_filters2
			WHERE owner_uid = ".$_SESSION["uid"]);

		$filter_id = db_fetch_result($result, 0, "id");

		$this->saveRulesAndActions($filter_id);

		db_query($this->link, "COMMIT");
	}

	function index() {

		$sort = db_escape_string($this->link, $_REQUEST["sort"]);

		if (!$sort || $sort == "undefined") {
			$sort = "reg_exp";
		}

		$filter_search = db_escape_string($this->link, $_REQUEST["search"]);

		if (array_key_exists("search", $_REQUEST)) {
			$_SESSION["prefs_filter_search"] = $filter_search;
		} else {
			$filter_search = $_SESSION["prefs_filter_search"];
		}

		print "<div id=\"pref-filter-wrap\" dojoType=\"dijit.layout.BorderContainer\" gutters=\"false\">";
		print "<div id=\"pref-filter-header\" dojoType=\"dijit.layout.ContentPane\" region=\"top\">";
		print "<div id=\"pref-filter-toolbar\" dojoType=\"dijit.Toolbar\">";

		$filter_search = db_escape_string($this->link, $_REQUEST["search"]);

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

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return joinSelectedFilters()\">".
			__('Combine')."</button> ";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return editSelectedFilter()\">".
			__('Edit')."</button> ";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return resetFilterOrder()\">".
			__('Reset sort order')."</button> ";


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

		print "<div dojoType=\"fox.PrefFilterStore\" jsId=\"filterStore\"
			url=\"backend.php?op=pref-filters&method=getfiltertree\">
		</div>
		<div dojoType=\"lib.CheckBoxStoreModel\" jsId=\"filterModel\" store=\"filterStore\"
			query=\"{id:'root'}\" rootId=\"root\" rootLabel=\"Filters\"
			childrenAttrs=\"items\" checkboxStrict=\"false\" checkboxAll=\"false\">
		</div>
		<div dojoType=\"fox.PrefFilterTree\" id=\"filterTree\"
			dndController=\"dijit.tree.dndSource\"
			betweenThreshold=\"5\"
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

		global $pluginhost;
		$pluginhost->run_hooks($pluginhost::HOOK_PREFS_TAB,
			"hook_prefs_tab", "prefFilters");

		print "</div>"; #container

	}

	function newfilter() {

		print "<form name='filter_new_form' id='filter_new_form'>";

		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"pref-filters\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"method\" value=\"add\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"csrf_token\" value=\"".$_SESSION['csrf_token']."\">";

		print "<div class=\"dlgSec\">".__("Caption")."</div>";

		print "<input required=\"true\" dojoType=\"dijit.form.ValidationTextBox\" style=\"width : 20em;\" name=\"title\" value=\"\">";

		print "<div class=\"dlgSec\">".__("Match")."</div>";

		print "<div dojoType=\"dijit.Toolbar\">";

		print "<div dojoType=\"dijit.form.DropDownButton\">".
				"<span>" . __('Select')."</span>";
		print "<div dojoType=\"dijit.Menu\" style=\"display: none;\">";
		print "<div onclick=\"dijit.byId('filterEditDlg').selectRules(true)\"
			dojoType=\"dijit.MenuItem\">".__('All')."</div>";
		print "<div onclick=\"dijit.byId('filterEditDlg').selectRules(false)\"
			dojoType=\"dijit.MenuItem\">".__('None')."</div>";
		print "</div></div>";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').addRule()\">".
			__('Add')."</button> ";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').deleteRule()\">".
			__('Delete')."</button> ";

		print "</div>";

		print "<ul id='filterDlg_Matches'>";
#		print "<li>No rules</li>";
		print "</ul>";

		print "</div>";

		print "<div class=\"dlgSec\">".__("Apply actions")."</div>";

		print "<div dojoType=\"dijit.Toolbar\">";

		print "<div dojoType=\"dijit.form.DropDownButton\">".
				"<span>" . __('Select')."</span>";
		print "<div dojoType=\"dijit.Menu\" style=\"display: none;\">";
		print "<div onclick=\"dijit.byId('filterEditDlg').selectActions(true)\"
			dojoType=\"dijit.MenuItem\">".__('All')."</div>";
		print "<div onclick=\"dijit.byId('filterEditDlg').selectActions(false)\"
			dojoType=\"dijit.MenuItem\">".__('None')."</div>";
		print "</div></div>";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').addAction()\">".
			__('Add')."</button> ";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').deleteAction()\">".
			__('Delete')."</button> ";

		print "</div>";

		print "<ul id='filterDlg_Actions'>";
#		print "<li>No actions</li>";
		print "</ul>";

/*		print "<div class=\"dlgSec\">".__("Options")."</div>";
		print "<div class=\"dlgSecCont\">"; */

		print "<input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" name=\"enabled\" id=\"enabled\" checked=\"1\">
				<label for=\"enabled\">".__('Enabled')."</label>";

		print "<br/><input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" name=\"match_any_rule\" id=\"match_any_rule\">
				<label for=\"match_any_rule\">".__('Match any rule')."</label>";

		print "<br/><input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" name=\"inverse\" id=\"inverse\">
				<label for=\"inverse\">".__('Inverse matching')."</label>";

//		print "</div>";

		print "<div class=\"dlgButtons\">";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').test()\">".
			__('Test')."</button> ";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').execute()\">".
			__('Create')."</button> ";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').hide()\">".
			__('Cancel')."</button>";

		print "</div>";

	}

	function newrule() {
		$rule = json_decode($_REQUEST["rule"], true);

		if ($rule) {
			$reg_exp = htmlspecialchars($rule["reg_exp"]);
			$filter_type = $rule["filter_type"];
			$feed_id = $rule["feed_id"];
			$inverse_checked = isset($rule["inverse"]) ? "checked" : "";
		} else {
			$reg_exp = "";
			$filter_type = 1;
			$feed_id = 0;
			$inverse_checked = "";
		}

		if (strpos($feed_id, "CAT:") === 0) {
			$feed_id = substr($feed_id, 4);
			$cat_filter = true;
		} else {
			$cat_filter = false;
		}


		print "<form name='filter_new_rule_form' id='filter_new_rule_form'>";

		$result = db_query($this->link, "SELECT id,description
			FROM ttrss_filter_types WHERE id != 5 ORDER BY description");

		$filter_types = array();

		while ($line = db_fetch_assoc($result)) {
			$filter_types[$line["id"]] = __($line["description"]);
		}

		print "<div class=\"dlgSec\">".__("Match")."</div>";

		print "<div class=\"dlgSecCont\">";

		print "<input dojoType=\"dijit.form.ValidationTextBox\"
			 required=\"true\" id=\"filterDlg_regExp\"
			 style=\"font-size : 16px; width : 20em;\"
			 name=\"reg_exp\" value=\"$reg_exp\"/>";

		print "<hr/>";
		print "<input id=\"filterDlg_inverse\" dojoType=\"dijit.form.CheckBox\"
			 name=\"inverse\" $inverse_checked/>";
		print "<label for=\"filterDlg_inverse\">".__("Inverse regular expression matching")."</label>";

		print "<hr/>" .  __("on field") . " ";
		print_select_hash("filter_type", $filter_type, $filter_types,
			'dojoType="dijit.form.Select"');

		print "<hr/>";

		print __("in") . " ";

		print "<span id='filterDlg_feeds'>";
		print_feed_select($this->link, "feed_id",
			$cat_filter ? "CAT:$feed_id" : $feed_id,
			'dojoType="dijit.form.FilteringSelect"');
		print "</span>";

		print "</div>";

		print "<div class=\"dlgButtons\">";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('filterNewRuleDlg').execute()\">".
			($rule ? __("Save rule") : __('Add rule'))."</button> ";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('filterNewRuleDlg').hide()\">".
			__('Cancel')."</button>";

		print "</div>";

		print "</form>";
	}

	function newaction() {
		$action = json_decode($_REQUEST["action"], true);

		if ($action) {
			$action_param = db_escape_string($this->link, $action["action_param"]);
			$action_id = (int)$action["action_id"];
		} else {
			$action_param = "";
			$action_id = 0;
		}

		print "<form name='filter_new_action_form' id='filter_new_action_form'>";

		print "<div class=\"dlgSec\">".__("Perform Action")."</div>";

		print "<div class=\"dlgSecCont\">";

		print "<select name=\"action_id\" dojoType=\"dijit.form.Select\"
			onchange=\"filterDlgCheckAction(this)\">";

		$result = db_query($this->link, "SELECT id,description FROM ttrss_filter_actions
			ORDER BY name");

		while ($line = db_fetch_assoc($result)) {
			$is_selected = ($line["id"] == $action_id) ? "selected='1'" : "";
			printf("<option $is_selected value='%d'>%s</option>", $line["id"], __($line["description"]));
		}

		print "</select>";

		$param_box_hidden = ($action_id == 7 || $action_id == 4 || $action_id == 6) ?
			"" : "display : none";

		$param_hidden = ($action_id == 4 || $action_id == 6) ?
			"" : "display : none";

		$label_param_hidden = ($action_id == 7) ?	"" : "display : none";

		print "<span id=\"filterDlg_paramBox\" style=\"$param_box_hidden\">";
		print " " . __("with parameters:") . " ";
		print "<input dojoType=\"dijit.form.TextBox\"
			id=\"filterDlg_actionParam\" style=\"$param_hidden\"
			name=\"action_param\" value=\"$action_param\">";

		print_label_select($this->link, "action_param_label", $action_param,
			"id=\"filterDlg_actionParamLabel\" style=\"$label_param_hidden\"
			dojoType=\"dijit.form.Select\"");

		print "</span>";

		print "&nbsp;"; // tiny layout hack

		print "</div>";

		print "<div class=\"dlgButtons\">";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('filterNewActionDlg').execute()\">".
			($action ? __("Save action") : __('Add action'))."</button> ";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('filterNewActionDlg').hide()\">".
			__('Cancel')."</button>";

		print "</div>";

		print "</form>";
	}

	private function getFilterName($id) {

		$result = db_query($this->link,
			"SELECT title FROM ttrss_filters2 WHERE id = '$id'");

		$title = db_fetch_result($result, 0, "title");

		if (!$title) {

			$result = db_query($this->link,
				"SELECT * FROM ttrss_filters2_rules WHERE filter_id = '$id' ORDER BY id
				LIMIT 3");

			$titles = array();
			$count = 0;

			while ($line = db_fetch_assoc($result)) {

				if (sql_bool_to_bool($line["cat_filter"])) {
					unset($line["cat_filter"]);
					$line["feed_id"] = "CAT:" . (int)$line["cat_id"];
					unset($line["cat_id"]);
				}

				if (!sql_bool_to_bool($line["inverse"])) unset($line["inverse"]);

				if ($count < 2) {
					array_push($titles, $this->getRuleName($line));
				} else {
					array_push($titles, "...");
					break;
				}
				++$count;
			}

			$title = truncate_string(join(", ", $titles), 64, "...");

		}

		$result = db_query($this->link,
			"SELECT * FROM ttrss_filters2_actions WHERE filter_id = '$id' ORDER BY id LIMIT 3");

		$actions = array();
		$count = 0;

		while ($line = db_fetch_assoc($result)) {
			if ($count < 2) {
				array_push($actions, $this->getActionName($line));
			} else {
				array_push($actions, "...");
				break;
			}
			++$count;
		}

		return array($title, join(", ", $actions));
	}

	function join() {
		$ids = explode(",", db_escape_string($this->link, $_REQUEST["ids"]));

		if (count($ids) > 1) {
			$base_id = array_shift($ids);
			$ids_str = join(",", $ids);

			db_query($this->link, "BEGIN");
			db_query($this->link, "UPDATE ttrss_filters2_rules
				SET filter_id = '$base_id' WHERE filter_id IN ($ids_str)");
			db_query($this->link, "UPDATE ttrss_filters2_actions
				SET filter_id = '$base_id' WHERE filter_id IN ($ids_str)");

			db_query($this->link, "DELETE FROM ttrss_filters2 WHERE id IN ($ids_str)");
			db_query($this->link, "UPDATE ttrss_filters2 SET match_any_rule = true WHERE id = '$base_id'");

			db_query($this->link, "COMMIT");

			$this->optimizeFilter($base_id);

		}
	}

	private function optimizeFilter($id) {
		db_query($this->link, "BEGIN");
		$result = db_query($this->link, "SELECT * FROM ttrss_filters2_actions
			WHERE filter_id = '$id'");

		$tmp = array();
		$dupe_ids = array();

		while ($line = db_fetch_assoc($result)) {
			$id = $line["id"];
			unset($line["id"]);

			if (array_search($line, $tmp) === false) {
				array_push($tmp, $line);
			} else {
				array_push($dupe_ids, $id);
			}
		}

		if (count($dupe_ids) > 0) {
			$ids_str = join(",", $dupe_ids);
			db_query($this->link, "DELETE FROM ttrss_filters2_actions
				WHERE id IN ($ids_str)");
		}

		$result = db_query($this->link, "SELECT * FROM ttrss_filters2_rules
			WHERE filter_id = '$id'");

		$tmp = array();
		$dupe_ids = array();

		while ($line = db_fetch_assoc($result)) {
			$id = $line["id"];
			unset($line["id"]);

			if (array_search($line, $tmp) === false) {
				array_push($tmp, $line);
			} else {
				array_push($dupe_ids, $id);
			}
		}

		if (count($dupe_ids) > 0) {
			$ids_str = join(",", $dupe_ids);
			db_query($this->link, "DELETE FROM ttrss_filters2_rules
				WHERE id IN ($ids_str)");
		}

		db_query($this->link, "COMMIT");
	}
}
?>

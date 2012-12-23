<?php
class Pref_Instances extends Handler_Protected {

	private $status_codes = array(
		0 	=> "Connection failed",
		1 	=> "Success",
		2 	=> "Invalid object received",
		16	=> "Access denied" );

	function csrf_ignore($method) {
		$csrf_ignored = array("index", "edit");

		return array_search($method, $csrf_ignored) !== false;
	}

	function before($method) {
		if (parent::before($method)) {
			if ($_SESSION["access_level"] < 10) {
				print __("Your access level is insufficient to open this tab.");
				return false;
			}
			return true;
		}
		return false;
	}

	function remove() {
		$ids = db_escape_string($_REQUEST['ids']);

		db_query($this->link, "DELETE FROM ttrss_linked_instances WHERE
			id IN ($ids)");
	}

	function add() {
		$id = db_escape_string($_REQUEST["id"]);
		$access_url = db_escape_string($_REQUEST["access_url"]);
		$access_key = db_escape_string($_REQUEST["access_key"]);

		db_query($this->link, "BEGIN");

		$result = db_query($this->link, "SELECT id FROM ttrss_linked_instances
			WHERE access_url = '$access_url'");

		if (db_num_rows($result) == 0) {
			db_query($this->link, "INSERT INTO ttrss_linked_instances
				(access_url, access_key, last_connected, last_status_in, last_status_out)
				VALUES
				('$access_url', '$access_key', '1970-01-01', -1, -1)");

		}

		db_query($this->link, "COMMIT");
	}

	function edit() {
		$id = db_escape_string($_REQUEST["id"]);

		$result = db_query($this->link, "SELECT * FROM ttrss_linked_instances WHERE
			id = '$id'");

		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\"  name=\"id\" value=\"$id\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\"  name=\"op\" value=\"pref-instances\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\"  name=\"method\" value=\"editSave\">";

		print "<div class=\"dlgSec\">".__("Instance")."</div>";

		print "<div class=\"dlgSecCont\">";

		/* URL */

		$access_url = htmlspecialchars(db_fetch_result($result, 0, "access_url"));

		print __("URL:") . " ";

		print "<input dojoType=\"dijit.form.ValidationTextBox\" required=\"1\"
			placeHolder=\"".__("Instance URL")."\"
			regExp='^(http|https)://.*'
			style=\"font-size : 16px; width: 20em\" name=\"access_url\"
			value=\"$access_url\">";

		print "<hr/>";

		$access_key = htmlspecialchars(db_fetch_result($result, 0, "access_key"));

		/* Access key */

		print __("Access key:") . " ";

		print "<input dojoType=\"dijit.form.ValidationTextBox\" required=\"1\"
			placeHolder=\"".__("Access key")."\" regExp='\w{40}'
			style=\"width: 20em\" name=\"access_key\" id=\"instance_edit_key\"
			value=\"$access_key\">";

		print "<p class='insensitive'>" . __("Use one access key for both linked instances.");

		print "</div>";

		print "<div class=\"dlgButtons\">
			<div style='float : left'>
				<button dojoType=\"dijit.form.Button\"
					onclick=\"return dijit.byId('instanceEditDlg').regenKey()\">".
					__('Generate new key')."</button>
			</div>
			<button dojoType=\"dijit.form.Button\"
				onclick=\"return dijit.byId('instanceEditDlg').execute()\">".
				__('Save')."</button>
			<button dojoType=\"dijit.form.Button\"
				onclick=\"return dijit.byId('instanceEditDlg').hide()\"\">".
				__('Cancel')."</button></div>";

	}

	function editSave() {
		$id = db_escape_string($_REQUEST["id"]);
		$access_url = db_escape_string($_REQUEST["access_url"]);
		$access_key = db_escape_string($_REQUEST["access_key"]);

		db_query($this->link, "UPDATE ttrss_linked_instances SET
			access_key = '$access_key', access_url = '$access_url',
			last_connected = '1970-01-01'
			WHERE id = '$id'");

	}

	function index() {

		if (!function_exists('curl_init')) {
			print "<div style='padding : 1em'>";
			print_error("This functionality requires CURL functions. Please enable CURL in your PHP configuration (you might also want to disable open_basedir in php.ini) and reload this page.");
			print "</div>";
		}

		print "<div id=\"pref-instance-wrap\" dojoType=\"dijit.layout.BorderContainer\" gutters=\"false\">";
		print "<div id=\"pref-instance-header\" dojoType=\"dijit.layout.ContentPane\" region=\"top\">";

		print "<div id=\"pref-instance-toolbar\" dojoType=\"dijit.Toolbar\">";

		$sort = db_escape_string($_REQUEST["sort"]);

		if (!$sort || $sort == "undefined") {
			$sort = "access_url";
		}

		print "<div dojoType=\"dijit.form.DropDownButton\">".
				"<span>" . __('Select')."</span>";
		print "<div dojoType=\"dijit.Menu\" style=\"display: none;\">";
		print "<div onclick=\"selectTableRows('prefInstanceList', 'all')\"
			dojoType=\"dijit.MenuItem\">".__('All')."</div>";
		print "<div onclick=\"selectTableRows('prefInstanceList', 'none')\"
			dojoType=\"dijit.MenuItem\">".__('None')."</div>";
		print "</div></div>";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"addInstance()\">".__('Link instance')."</button>";
		print "<button dojoType=\"dijit.form.Button\" onclick=\"editSelectedInstance()\">".__('Edit')."</button>";
		print "<button dojoType=\"dijit.form.Button\" onclick=\"removeSelectedInstances()\">".__('Remove')."</button>";

		print "</div>"; #toolbar

		$result = db_query($this->link, "SELECT *,
			(SELECT COUNT(*) FROM ttrss_linked_feeds
				WHERE instance_id = ttrss_linked_instances.id) AS num_feeds
			FROM ttrss_linked_instances
			ORDER BY $sort");

		print "<p class=\"insensitive\" style='margin-left : 1em;'>" . __("You can connect other instances of Tiny Tiny RSS to this one to share Popular feeds. Link to this instance of Tiny Tiny RSS by using this URL:");

		print " <a href=\"#\" onclick=\"alert('".htmlspecialchars(get_self_url_prefix())."')\">(display url)</a>";

		print "<p><table width='100%' id='prefInstanceList' class='prefInstanceList' cellspacing='0'>";

		print "<tr class=\"title\">
			<td align='center' width=\"5%\">&nbsp;</td>
			<td width=''><a href=\"#\" onclick=\"updateInstanceList('access_url')\">".__('Instance URL')."</a></td>
			<td width='20%'><a href=\"#\" onclick=\"updateInstanceList('access_key')\">".__('Access key')."</a></td>
			<td width='10%'><a href=\"#\" onclick=\"updateUsersList('last_connected')\">".__('Last connected')."</a></td>
			<td width='10%'><a href=\"#\" onclick=\"updateUsersList('last_status_out')\">".__('Status')."</a></td>
			<td width='10%'><a href=\"#\" onclick=\"updateUsersList('num_feeds')\">".__('Stored feeds')."</a></td>
			</tr>";

		$lnum = 0;

		while ($line = db_fetch_assoc($result)) {
			$class = ($lnum % 2) ? "even" : "odd";

			$id = $line['id'];
			$this_row_id = "id=\"LIRR-$id\"";

			$line["last_connected"] = make_local_datetime($this->link, $line["last_connected"], false);

			print "<tr class=\"$class\" $this_row_id>";

			print "<td align='center'><input onclick='toggleSelectRow(this);'
				type=\"checkbox\" id=\"LICHK-$id\"></td>";

			$onclick = "onclick='editInstance($id, event)' title='".__('Click to edit')."'";

			$access_key = mb_substr($line['access_key'], 0, 4) . '...' .
				mb_substr($line['access_key'], -4);

			print "<td $onclick>" . htmlspecialchars($line['access_url']) . "</td>";
			print "<td $onclick>" . htmlspecialchars($access_key) . "</td>";
			print "<td $onclick>" . htmlspecialchars($line['last_connected']) . "</td>";
			print "<td $onclick>" . $this->status_codes[$line['last_status_out']] . "</td>";
			print "<td $onclick>" . htmlspecialchars($line['num_feeds']) . "</td>";

			print "</tr>";

			++$lnum;
		}

		print "</table>";

		print "</div>"; #pane

		global $pluginhost;
		$pluginhost->run_hooks($pluginhost::HOOK_PREFS_TAB,
			"hook_prefs_tab", "prefInstances");

		print "</div>"; #container

	}
}
?>

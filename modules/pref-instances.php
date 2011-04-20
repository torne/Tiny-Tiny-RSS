<?php
	function module_pref_instances($link) {

		$subop = $_REQUEST['subop'];

		if ($subop == "edit") {

			print "TODO: function not implemented.";


			return;
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

		$result = db_query($link, "SELECT * FROM ttrss_linked_instances
			ORDER BY $sort");

		print "<p class=\"insensitive\" style='margin-left : 1em;'>" . __("You can connect other instances of Tiny Tiny RSS to this one to share Popular feeds. Link to this instance of Tiny Tiny RSS by using this URL:");

		print " <a href=\"#\" onclick=\"alert('".htmlspecialchars(get_self_url_prefix())."')\">(display url)</a>";

		print "<p><table width='100%' id='prefInstanceList' class='prefInstanceList' cellspacing='0'>";

		print "<tr class=\"title\">
			<td align='center' width=\"5%\">&nbsp;</td>
			<td width=''><a href=\"#\" onclick=\"updateInstanceList('access_url')\">".__('Instance URL')."</a></td>
			<td width='20%'><a href=\"#\" onclick=\"updateUsersList('last_connected')\">".__('Last connected')."</a></td>
			</tr>";

		$lnum = 0;

		while ($line = db_fetch_assoc($result)) {
			$class = ($lnum % 2) ? "even" : "odd";

			$id = $line['id'];
			$this_row_id = "id=\"LIRR-$id\"";

			$line["last_connected"] = make_local_datetime($link, $line["last_connected"], false);

			print "<tr class=\"$class\" $this_row_id>";

			print "<td align='center'><input onclick='toggleSelectRow(this);'
				type=\"checkbox\" id=\"LICHK-$id\"></td>";

			$onclick = "onclick='editInstance($id, event)' title='".__('Click to edit')."'";

			print "<td $onclick>" . htmlspecialchars($line['access_url']) . "</td>";
			print "<td $onclick>" . htmlspecialchars($line['last_connected']) . "</td>";

			print "</tr>";

			++$lnum;
		}

		print "</table>";

		print "</div>"; #pane
		print "</div>"; #container

	}
?>

var xmlhttp = false;

var active_feed = false;
var active_feed_cat = false;
var active_filter = false;
var active_label = false;
var active_user = false;

var active_tab = false;

/*@cc_on @*/
/*@if (@_jscript_version >= 5)
// JScript gives us Conditional compilation, we can cope with old IE versions.
// and security blocked creation of the objects.
try {
	xmlhttp = new ActiveXObject("Msxml2.XMLHTTP");
} catch (e) {
	try {
		xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
	} catch (E) {
		xmlhttp = false;
	}
}
@end @*/

if (!xmlhttp && typeof XMLHttpRequest!='undefined') {
	xmlhttp = new XMLHttpRequest();
}

function feedlist_callback() {
	if (xmlhttp.readyState == 4) {
		var container = document.getElementById('prefContent');	
		container.innerHTML=xmlhttp.responseText;
		if (active_feed) {
			var row = document.getElementById("FEEDR-" + active_feed);
			if (row) {
				if (!row.className.match("Selected")) {
					row.className = row.className + "Selected";
				}		
			}
			var checkbox = document.getElementById("FRCHK-" + active_feed);
			if (checkbox) {
				checkbox.checked = true;
			}
		}
		p_notify("");
	}
}

function filterlist_callback() {
	var container = document.getElementById('prefContent');
	if (xmlhttp.readyState == 4) {

		container.innerHTML=xmlhttp.responseText;

		if (active_filter) {
			var row = document.getElementById("FILRR-" + active_filter);
			if (row) {
				if (!row.className.match("Selected")) {
					row.className = row.className + "Selected";
				}		
			}
			var checkbox = document.getElementById("FICHK-" + active_filter);
			
			if (checkbox) {
				checkbox.checked = true;
			}
		}
		p_notify("");
	}
}

function labellist_callback() {
	var container = document.getElementById('prefContent');
	if (xmlhttp.readyState == 4) {
		container.innerHTML=xmlhttp.responseText;

		if (active_label) {
			var row = document.getElementById("LILRR-" + active_label);
			if (row) {
				if (!row.className.match("Selected")) {
					row.className = row.className + "Selected";
				}		
			}
			var checkbox = document.getElementById("LICHK-" + active_label);
			
			if (checkbox) {
				checkbox.checked = true;
			}
		}
		p_notify("");
	}
}

function userlist_callback() {
	var container = document.getElementById('prefContent');
	if (xmlhttp.readyState == 4) {
		container.innerHTML=xmlhttp.responseText;

		if (active_user) {
			var row = document.getElementById("UMRR-" + active_user);
			if (row) {
				if (!row.className.match("Selected")) {
					row.className = row.className + "Selected";
				}		
			}
			var checkbox = document.getElementById("UMCHK-" + active_user);
			
			if (checkbox) {
				checkbox.checked = true;
			}
		} 

		p_notify("");
	}
}

function infobox_callback() {
	if (xmlhttp.readyState == 4) {
		var box = document.getElementById('infoBox');
		var shadow = document.getElementById('infoBoxShadow');

		if (box) {
			box.innerHTML=xmlhttp.responseText;
			if (shadow) {
				shadow.style.display = "block";
			} else {
				box.style.display = "block";
			}
		}
	}
}


function prefslist_callback() {
	var container = document.getElementById('prefContent');
	if (xmlhttp.readyState == 4) {

		container.innerHTML=xmlhttp.responseText;

		p_notify("");
	}
}

function gethelp_callback() {
	var container = document.getElementById('prefHelpBox');
	if (xmlhttp.readyState == 4) {

		container.innerHTML = xmlhttp.responseText;
		container.style.display = "block";

	}
}


function notify_callback() {
	var container = document.getElementById('notify');
	if (xmlhttp.readyState == 4) {
		container.innerHTML=xmlhttp.responseText;
	}
}

function updateFeedList(sort_key) {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

//	document.getElementById("prefContent").innerHTML = "Loading feeds, please wait...";

	p_notify("Loading, please wait...");

	var feed_search = document.getElementById("feed_search");
	var search = "";
	if (feed_search) { search = feed_search.value; }

	xmlhttp.open("GET", "backend.php?op=pref-feeds" +
		"&sort=" + param_escape(sort_key) + 
		"&search=" + param_escape(search), true);
	xmlhttp.onreadystatechange=feedlist_callback;
	xmlhttp.send(null);

}

function updateUsersList() {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

//	document.getElementById("prefContent").innerHTML = "Loading feeds, please wait...";

	p_notify("Loading, please wait...");

	xmlhttp.open("GET", "backend.php?op=pref-users", true);
	xmlhttp.onreadystatechange=userlist_callback;
	xmlhttp.send(null);

}

function addLabel() {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	var sqlexp = document.getElementById("ladd_expr");

	if (sqlexp.value.length == 0) {
		notify("Missing SQL expression.");
	} else {
		notify("Adding label...");

		xmlhttp.open("GET", "backend.php?op=pref-labels&subop=add&exp=" +
			param_escape(sqlexp.value), true);			
			
		xmlhttp.onreadystatechange=labellist_callback;
		xmlhttp.send(null);

		sqlexp.value = "";
	}

}

function addFilter() {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	var regexp = document.getElementById("fadd_regexp");
	var match = document.getElementById("fadd_match");
	var feed = document.getElementById("fadd_feed");
	var action = document.getElementById("fadd_action");

	if (regexp.value.length == 0) {
		notify("Missing filter expression.");
	} else {
		notify("Adding filter...");

		var v_match = match[match.selectedIndex].text;
		var feed_id = feed[feed.selectedIndex].id;
		var action_id = action[action.selectedIndex].id;

		xmlhttp.open("GET", "backend.php?op=pref-filters&subop=add&regexp=" +
			param_escape(regexp.value) + "&match=" + v_match +
			"&fid=" + param_escape(feed_id) + "&aid=" + param_escape(action_id), true);
			
		xmlhttp.onreadystatechange=filterlist_callback;
		xmlhttp.send(null);

		regexp.value = "";
	}

}

function addFeed() {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	var link = document.getElementById("fadd_link");

	if (link.value.length == 0) {
		notify("Missing feed URL.");
	} else {
		notify("Adding feed...");

		xmlhttp.open("GET", "backend.php?op=pref-feeds&subop=add&link=" +
			param_escape(link.value), true);
		xmlhttp.onreadystatechange=feedlist_callback;
		xmlhttp.send(null);

		link.value = "";

	}

}

function addFeedCat() {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	var cat = document.getElementById("fadd_cat");

	if (cat.value.length == 0) {
		notify("Missing feed category.");
	} else {
		notify("Adding feed category...");

		xmlhttp.open("GET", "backend.php?op=pref-feeds&subop=addCat&cat=" +
			param_escape(cat.value), true);
		xmlhttp.onreadystatechange=feedlist_callback;
		xmlhttp.send(null);

		link.value = "";

	}

}
function addUser() {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	var sqlexp = document.getElementById("uadd_box");

	if (sqlexp.value.length == 0) {
		notify("Missing user login.");
	} else {
		notify("Adding user...");

		xmlhttp.open("GET", "backend.php?op=pref-users&subop=add&login=" +
			param_escape(sqlexp.value), true);			
			
		xmlhttp.onreadystatechange=userlist_callback;
		xmlhttp.send(null);

		sqlexp.value = "";
	}

}

function editLabel(id) {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	active_label = id;

	xmlhttp.open("GET", "backend.php?op=pref-labels&subop=edit&id=" +
		param_escape(id), true);
	xmlhttp.onreadystatechange=labellist_callback;
	xmlhttp.send(null);

}

function editUser(id) {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	active_user = id;

	xmlhttp.open("GET", "backend.php?op=pref-users&subop=edit&id=" +
		param_escape(id), true);
	xmlhttp.onreadystatechange=userlist_callback;
	xmlhttp.send(null);

}

function editFilter(id) {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	active_filter = id;

	xmlhttp.open("GET", "backend.php?op=pref-filters&subop=edit&id=" +
		param_escape(id), true);
	xmlhttp.onreadystatechange=filterlist_callback;
	xmlhttp.send(null);

}

function editFeed(feed) {

//	notify("Editing feed...");

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	active_feed = feed;

/*	xmlhttp.open("GET", "backend.php?op=pref-feeds&subop=edit&id=" +
		param_escape(feed), true);
	xmlhttp.onreadystatechange=feedlist_callback;
	xmlhttp.send(null); */

	selectTableRowsByIdPrefix('prefFeedList', 'FEEDR-', 'FRCHK-', false);
	selectTableRowsByIdPrefix('prefFeedList', 'FEEDR-'+feed, 'FRCHK-'+feed, true);

	xmlhttp.open("GET", "backend.php?op=pref-feeds&subop=editfeed&id=" +
		param_escape(active_feed), true);

	xmlhttp.onreadystatechange=infobox_callback;
	xmlhttp.send(null);

}

function editFeedCat(cat) {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	active_feed_cat = cat;

	xmlhttp.open("GET", "backend.php?op=pref-feeds&subop=editCat&id=" +
		param_escape(cat), true);
	xmlhttp.onreadystatechange=feedlist_callback;
	xmlhttp.send(null);

}

function getSelectedLabels() {
	return getSelectedTableRowIds("prefLabelList", "LILRR");
}

function getSelectedUsers() {
	return getSelectedTableRowIds("prefUserList", "UMRR");
}

function getSelectedFeeds() {
	return getSelectedTableRowIds("prefFeedList", "FEEDR");
}

function getSelectedFilters() {
	return getSelectedTableRowIds("prefFilterList", "FILRR");
}

function getSelectedFeedCats() {
	return getSelectedTableRowIds("prefFeedCatList", "FCATR");
}


function readSelectedFeeds(read) {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	var sel_rows = getSelectedFeeds();

	if (sel_rows.length > 0) {

		if (!read) {
			op = "unread";
		} else {
			op = "read";
		}

		notify("Marking selected feeds as " + op + "...");

		xmlhttp.open("GET", "backend.php?op=pref-rpc&subop=" + op + "&ids="+
			param_escape(sel_rows.toString()), true);
		xmlhttp.onreadystatechange=notify_callback;
		xmlhttp.send(null);

	} else {

		notify("Please select some feeds first.");

	}
}

function removeSelectedLabels() {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	var sel_rows = getSelectedLabels();

	if (sel_rows.length > 0) {

		notify("Removing selected labels...");

		xmlhttp.open("GET", "backend.php?op=pref-labels&subop=remove&ids="+
			param_escape(sel_rows.toString()), true);
		xmlhttp.onreadystatechange=labellist_callback;
		xmlhttp.send(null);

	} else {
		notify("Please select some labels first.");
	}
}

function removeSelectedUsers() {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	var sel_rows = getSelectedUsers();

	if (sel_rows.length > 0) {

		notify("Removing selected users...");

		xmlhttp.open("GET", "backend.php?op=pref-users&subop=remove&ids="+
			param_escape(sel_rows.toString()), true);
		xmlhttp.onreadystatechange=userlist_callback;
		xmlhttp.send(null);

	} else {
		notify("Please select some labels first.");
	}
}

function removeSelectedFilters() {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	var sel_rows = getSelectedFilters();

	if (sel_rows.length > 0) {

		notify("Removing selected filters...");

		xmlhttp.open("GET", "backend.php?op=pref-filters&subop=remove&ids="+
			param_escape(sel_rows.toString()), true);
		xmlhttp.onreadystatechange=filterlist_callback;
		xmlhttp.send(null);

	} else {
		notify("Please select some filters first.");
	}
}


function removeSelectedFeeds() {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	var sel_rows = getSelectedFeeds();

	if (sel_rows.length > 0) {

		notify("Removing selected feeds...");

		xmlhttp.open("GET", "backend.php?op=pref-feeds&subop=remove&ids="+
			param_escape(sel_rows.toString()), true);
		xmlhttp.onreadystatechange=feedlist_callback;
		xmlhttp.send(null);

	} else {

		notify("Please select some feeds first.");

	}

}

function removeSelectedFeedCats() {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	var sel_rows = getSelectedFeedCats();

	if (sel_rows.length > 0) {

		notify("Removing selected categories...");

		xmlhttp.open("GET", "backend.php?op=pref-feeds&subop=removeCats&ids="+
			param_escape(sel_rows.toString()), true);
		xmlhttp.onreadystatechange=feedlist_callback;
		xmlhttp.send(null);

	} else {

		notify("Please select some feeds first.");

	}

}

function feedEditCancel() {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	closeInfoBox();

	active_feed = false;

	notify("Operation cancelled.");

/*	xmlhttp.open("GET", "backend.php?op=pref-feeds", true);
	xmlhttp.onreadystatechange=feedlist_callback;
	xmlhttp.send(null); */

}

function feedCatEditCancel() {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	active_feed_cat = false;

	notify("Operation cancelled.");

	xmlhttp.open("GET", "backend.php?op=pref-feeds", true);
	xmlhttp.onreadystatechange=feedlist_callback;
	xmlhttp.send(null);

}

function feedEditSave() {

	var feed = active_feed;

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	var link = document.getElementById("iedit_link").value;
	var title = document.getElementById("iedit_title").value;
	var upd_intl = document.getElementById("iedit_updintl").value;
	var purge_intl = document.getElementById("iedit_purgintl").value;
	var fcat = document.getElementById("iedit_fcat");

	var fcat_id = fcat[fcat.selectedIndex].id;

//	notify("Saving feed.");

/*	if (upd_intl < 0) {
		notify("Update interval must be &gt;= 0 (0 = default)");
		return;
	}

	if (purge_intl < 0) {
		notify("Purge days must be &gt;= 0 (0 = default)");
		return;
	} */

	if (link.length == 0) {
		notify("Feed link cannot be blank.");
		return;
	}

	if (title.length == 0) {
		notify("Feed title cannot be blank.");
		return;
	}

	active_feed = false;

	notify("");

	xmlhttp.open("GET", "backend.php?op=pref-feeds&subop=editSave&id=" +
		feed + "&l=" + param_escape(link) + "&t=" + param_escape(title) +
		"&ui=" + param_escape(upd_intl) + "&pi=" + param_escape(purge_intl) +
		"&catid=" + param_escape(fcat_id), true);
	xmlhttp.onreadystatechange=feedlist_callback;
	xmlhttp.send(null);

}

function feedCatEditSave() {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	notify("");

	var cat_title = document.getElementById("iedit_title").value;

	xmlhttp.open("GET", "backend.php?op=pref-feeds&subop=saveCat&id=" +
		param_escape(active_feed_cat) + "&title=" + param_escape(cat_title), 
		true);
	xmlhttp.onreadystatechange=feedlist_callback;
	xmlhttp.send(null);

	active_feed_cat = false;

}


function labelTest() {

	var sqlexp = document.getElementById("iedit_expr").value;
	var descr = document.getElementById("iedit_descr").value;

	xmlhttp.open("GET", "backend.php?op=pref-labels&subop=test&expr=" +
		param_escape(sqlexp) + "&descr=" + param_escape(descr), true);

	xmlhttp.onreadystatechange=infobox_callback;
	xmlhttp.send(null);

}

function displayHelpInfobox(topic_id) {

	xmlhttp.open("GET", "backend.php?op=help&tid=" +
		param_escape(topic_id) + "&noheaders=1", true);

	xmlhttp.onreadystatechange=infobox_callback;
	xmlhttp.send(null);

}

function labelEditCancel() {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	active_label = false;

	notify("Operation cancelled.");

	xmlhttp.open("GET", "backend.php?op=pref-labels", true);
	xmlhttp.onreadystatechange=labellist_callback;
	xmlhttp.send(null);

}

function userEditCancel() {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	active_user = false;

	notify("Operation cancelled.");

	xmlhttp.open("GET", "backend.php?op=pref-users", true);
	xmlhttp.onreadystatechange=userlist_callback;
	xmlhttp.send(null);

}

function filterEditCancel() {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	active_filter = false;

	notify("Operation cancelled.");

	xmlhttp.open("GET", "backend.php?op=pref-filters", true);
	xmlhttp.onreadystatechange=filterlist_callback;
	xmlhttp.send(null);

}

function labelEditSave() {

	var label = active_label;

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	var sqlexp = document.getElementById("iedit_expr").value;
	var descr = document.getElementById("iedit_descr").value;

//	notify("Saving label " + sqlexp + ": " + descr);

	if (sqlexp.length == 0) {
		notify("SQL expression cannot be blank.");
		return;
	}

	if (descr.length == 0) {
		notify("Caption cannot be blank.");
		return;
	}

	notify("");

	active_label = false;

	xmlhttp.open("GET", "backend.php?op=pref-labels&subop=editSave&id=" +
		label + "&s=" + param_escape(sqlexp) + "&d=" + param_escape(descr),
		true);
		
	xmlhttp.onreadystatechange=labellist_callback;
	xmlhttp.send(null);

}

function userEditSave() {

	var user = active_user;

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	var login = document.getElementById("iedit_ulogin").value;
	var level = document.getElementById("iedit_ulevel").value;

	if (login.length == 0) {
		notify("Login cannot be blank.");
		return;
	}

	if (level.length == 0) {
		notify("User level cannot be blank.");
		return;
	}

	active_user = false;

	notify("");

	xmlhttp.open("GET", "backend.php?op=pref-users&subop=editSave&id=" +
		user + "&l=" + param_escape(login) + "&al=" + param_escape(level),
		true);
		
	xmlhttp.onreadystatechange=userlist_callback;
	xmlhttp.send(null);

}


function filterEditSave() {

	var filter = active_filter;

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	var regexp = document.getElementById("iedit_regexp").value;
	var descr = document.getElementById("iedit_descr").value;
	var match = document.getElementById("iedit_match");

	var v_match = match[match.selectedIndex].text;

	var feed = document.getElementById("iedit_feed");
	var feed_id = feed[feed.selectedIndex].id;

	var action = document.getElementById("iedit_filter_action");
	var action_id = action[action.selectedIndex].id;

//	notify("Saving filter " + filter + ": " + regexp + ", " + descr + ", " + match);

	if (regexp.length == 0) {
		notify("Filter expression cannot be blank.");
		return;
	}

	active_filter = false;

	xmlhttp.open("GET", "backend.php?op=pref-filters&subop=editSave&id=" +
		filter + "&r=" + param_escape(regexp) + "&d=" + param_escape(descr) +
		"&m=" + param_escape(v_match) + "&fid=" + param_escape(feed_id) + 
		"&aid=" + param_escape(action_id), true);

	notify("");

	xmlhttp.onreadystatechange=filterlist_callback;
	xmlhttp.send(null); 

}

function editSelectedLabel() {
	var rows = getSelectedLabels();

	if (rows.length == 0) {
		notify("No labels are selected.");
		return;
	}

	if (rows.length > 1) {
		notify("Please select one label.");
		return;
	}

	notify("");

	editLabel(rows[0]);

}

function editSelectedUser() {
	var rows = getSelectedUsers();

	if (rows.length == 0) {
		notify("No users are selected.");
		return;
	}

	if (rows.length > 1) {
		notify("Please select one user.");
		return;
	}

	notify("");

	editUser(rows[0]);
}

function resetSelectedUserPass() {
	var rows = getSelectedUsers();

	if (rows.length == 0) {
		notify("No users are selected.");
		return;
	}

	if (rows.length > 1) {
		notify("Please select one user.");
		return;
	}

	notify("Resetting password for selected user...");

	var id = rows[0];

	xmlhttp.open("GET", "backend.php?op=pref-users&subop=resetPass&id=" +
		param_escape(id), true);
	xmlhttp.onreadystatechange=userlist_callback;
	xmlhttp.send(null);

}

function selectedUserDetails() {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	var rows = getSelectedUsers();

	if (rows.length == 0) {
		notify("No users are selected.");
		return;
	}

	if (rows.length > 1) {
		notify("Please select one user.");
		return;
	}

	var id = rows[0];

	notify("");

	xmlhttp.open("GET", "backend.php?op=user-details&id=" + id, true);
	xmlhttp.onreadystatechange=infobox_callback;
	xmlhttp.send(null);

}

function selectedFeedDetails() {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	var rows = getSelectedFeeds();

	if (rows.length == 0) {
		notify("No feeds are selected.");
		return;
	}

	if (rows.length > 1) {
		notify("Please select one feed.");
		return;
	}

	var id = rows[0];

	notify("");

	xmlhttp.open("GET", "backend.php?op=feed-details&id=" + id, true);
	xmlhttp.onreadystatechange=infobox_callback;
	xmlhttp.send(null);

}

function editSelectedFilter() {
	var rows = getSelectedFilters();

	if (rows.length == 0) {
		notify("No filters are selected.");
		return;
	}

	if (rows.length > 1) {
		notify("Please select one filter.");
		return;
	}

	notify("");

	editFilter(rows[0]);

}


function editSelectedFeed() {
	var rows = getSelectedFeeds();

	if (rows.length == 0) {
		notify("No feeds are selected.");
		return;
	}

	if (rows.length > 1) {
		notify("Please select one feed.");
		return;
	}

	notify("");

	editFeed(rows[0]);

}

function editSelectedFeedCat() {
	var rows = getSelectedFeedCats();

	if (rows.length == 0) {
		notify("No categories are selected.");
		return;
	}

	if (rows.length > 1) {
		notify("Please select one category.");
		return;
	}

	notify("");

	editFeedCat(rows[0]);

}

function localPiggieFunction(enable) {
	if (enable) {
		piggie.style.display = "block";
		seq = "";
		notify("I loveded it!!!");
	} else {
		piggie.style.display = "none";
		notify("");
	}
}

function validateOpmlImport() {
	
	var opml_file = document.getElementById("opml_file");

	if (opml_file.value.length == 0) {
		notify("Please select OPML file to upload.");
		return false;
	} else {
		return true;
	}
}

function updateFilterList() {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

//	document.getElementById("prefContent").innerHTML = "Loading filters, please wait...";

	p_notify("Loading, please wait...");

	xmlhttp.open("GET", "backend.php?op=pref-filters", true);
	xmlhttp.onreadystatechange=filterlist_callback;
	xmlhttp.send(null);

}

function updateLabelList() {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	p_notify("Loading, please wait...");

//	document.getElementById("prefContent").innerHTML = "Loading labels, please wait...";

	xmlhttp.open("GET", "backend.php?op=pref-labels", true);
	xmlhttp.onreadystatechange=labellist_callback;
	xmlhttp.send(null);
}

function updatePrefsList() {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	p_notify("Loading, please wait...");

	xmlhttp.open("GET", "backend.php?op=pref-prefs", true);
	xmlhttp.onreadystatechange=prefslist_callback;
	xmlhttp.send(null);

}

function selectTab(id) {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	if (id == "feedConfig") {
		updateFeedList();
	} else if (id == "filterConfig") {
		updateFilterList();
	} else if (id == "labelConfig") {
		updateLabelList();
	} else if (id == "genConfig") {
		updatePrefsList();
	} else if (id == "userConfig") {
		updateUsersList();
	}

	var tab = document.getElementById(active_tab + "Tab");

	if (tab) {
		if (tab.className.match("Selected")) {
			tab.className = "prefsTab";
		}
	}

	tab = document.getElementById(id + "Tab");

	if (tab) {
		if (!tab.className.match("Selected")) {
			tab.className = tab.className + "Selected";
		}
	}

	active_tab = id;

	setCookie('ttrss_pref_acttab', active_tab);

}

function init() {

	try {
	
		// IE kludge
		if (!xmlhttp) {
			document.getElementById("prefContent").innerHTML = 
				"<b>Fatal error:</b> This program needs XmlHttpRequest " + 
				"to function properly. Your browser doesn't seem to support it.";
			return;
		}

		active_tab = getCookie("ttrss_pref_acttab");
		if (!active_tab) active_tab = "genConfig";
		selectTab(active_tab);
	
		document.onkeydown = hotkey_handler;
		notify("");
	} catch (e) {
		exception_error("init", e);
	}
}

function closeInfoBox() {
	var box = document.getElementById('infoBox');
	var shadow = document.getElementById('infoBoxShadow');

	if (shadow) {
		shadow.style.display = "none";
	} else if (box) {
		box.style.display = "none";
	}
}

function categorizeSelectedFeeds() {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	var sel_rows = getSelectedFeeds();

	var cat_sel = document.getElementById("sfeed_set_fcat");
	var cat_id = cat_sel[cat_sel.selectedIndex].id;

	if (sel_rows.length > 0) {

		notify("Changing category of selected feeds...");

		xmlhttp.open("GET", "backend.php?op=pref-feeds&subop=categorize&ids="+
			param_escape(sel_rows.toString()) + "&cat_id=" + param_escape(cat_id), true);
		xmlhttp.onreadystatechange=feedlist_callback;
		xmlhttp.send(null);

	} else {

		notify("Please select some feeds first.");

	}

}

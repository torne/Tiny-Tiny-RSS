var xmlhttp = false;

var active_feed = false;
var active_feed_cat = false;
var active_filter = false;
var active_label = false;
var active_user = false;
var active_tab = false;
var feed_to_expand = false;

var piggie_top = -400;
var piggie_fwd = true;

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

function expand_feed_callback() {
	if (xmlhttp.readyState == 4) {
		try {	
			var container = document.getElementById("BRDET-" + feed_to_expand);	
			container.innerHTML=xmlhttp.responseText;
			container.style.display = "block";
//			p_notify("");
		} catch (e) {
			exception_error("expand_feed_callback", e);
		}
	}
}

function feedlist_callback() {
	if (xmlhttp.readyState == 4) {
		try {	
			var container = document.getElementById('prefContent');	
			container.innerHTML=xmlhttp.responseText;
			selectTab("feedConfig", true);

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
			notify("");
		} catch (e) {
			exception_error("feedlist_callback", e);
		}
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
		notify("");
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
		notify("");
	}
}

function feed_browser_callback() {
	var container = document.getElementById('prefContent');
	if (xmlhttp.readyState == 4) {
		container.innerHTML=xmlhttp.responseText;
		notify("");
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
		notify("");
	}
}

function prefslist_callback() {
	var container = document.getElementById('prefContent');
	if (xmlhttp.readyState == 4) {

		container.innerHTML=xmlhttp.responseText;
		
		notify("");
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

//	p_notify("Loading, please wait...");

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

//	p_notify("Loading, please wait...");

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
		alert("Can't add label: missing SQL expression.");
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
		alert("Can't add filter: missing filter expression.");
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
		alert("Error: No feed URL given.");
	} else if (!isValidURL(link.value)) {
		alert("Error: Invalid feed URL.");
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
		alert("Can't add category: no name specified.");
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
		alert("Can't add user: no login specified.");
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

	// clean selection from all rows & select row being edited
	selectTableRowsByIdPrefix('prefFeedList', 'FEEDR-', 'FRCHK-', false);
	selectTableRowById('FEEDR-'+feed, 'FRCHK-'+feed, true);

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

function getSelectedFeedsFromBrowser() {

	var list = document.getElementById("browseFeedList");
	if (!list) list = document.getElementById("browseBigFeedList");

	var selected = new Array();
	
	for (i = 0; i < list.childNodes.length; i++) {
		var child = list.childNodes[i];
		if (child.id && child.id.match("FBROW-")) {
			var id = child.id.replace("FBROW-", "");
			
			var cb = document.getElementById("FBCHK-" + id);

			if (cb.checked) {
				selected.push(id);
			}
		}
	}

	return selected;
}


/*function readSelectedFeeds(read) {

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

		alert("No feeds are selected.");

	}
} */

function removeSelectedLabels() {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	var sel_rows = getSelectedLabels();

	if (sel_rows.length > 0) {

		var ok = confirm("Remove selected labels?");

		if (ok) {
			notify("Removing selected labels...");
	
			xmlhttp.open("GET", "backend.php?op=pref-labels&subop=remove&ids="+
				param_escape(sel_rows.toString()), true);
			xmlhttp.onreadystatechange=labellist_callback;
			xmlhttp.send(null);
		}
	} else {
		alert("No labels are selected.");
	}
}

function removeSelectedUsers() {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	var sel_rows = getSelectedUsers();

	if (sel_rows.length > 0) {

		var ok = confirm("Remove selected users?");

		if (ok) {
			notify("Removing selected users...");
	
			xmlhttp.open("GET", "backend.php?op=pref-users&subop=remove&ids="+
				param_escape(sel_rows.toString()), true);
			xmlhttp.onreadystatechange=userlist_callback;
			xmlhttp.send(null);
		}

	} else {
		alert("No users are selected.");
	}
}

function removeSelectedFilters() {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	var sel_rows = getSelectedFilters();

	if (sel_rows.length > 0) {

		var ok = confirm("Remove selected filters?");

		if (ok) {
			notify("Removing selected filters...");
	
			xmlhttp.open("GET", "backend.php?op=pref-filters&subop=remove&ids="+
				param_escape(sel_rows.toString()), true);
			xmlhttp.onreadystatechange=filterlist_callback;
			xmlhttp.send(null);
		}
	} else {
		alert("No filters are selected.");
	}
}


function removeSelectedFeeds() {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	var sel_rows = getSelectedFeeds();

	if (sel_rows.length > 0) {

		var ok = confirm("Unsubscribe from selected feeds?");

		if (ok) {

			notify("Unsubscribing from selected feeds...");
	
			xmlhttp.open("GET", "backend.php?op=pref-feeds&subop=remove&ids="+
				param_escape(sel_rows.toString()), true);
			xmlhttp.onreadystatechange=feedlist_callback;
			xmlhttp.send(null);
		}

	} else {

		alert("No feeds are selected.");

	}

}

function removeSelectedFeedCats() {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	var sel_rows = getSelectedFeedCats();

	if (sel_rows.length > 0) {

		var ok = confirm("Remove selected categories?");

		if (ok) {
			notify("Removing selected categories...");
	
			xmlhttp.open("GET", "backend.php?op=pref-feeds&subop=removeCats&ids="+
				param_escape(sel_rows.toString()), true);
			xmlhttp.onreadystatechange=feedlist_callback;
			xmlhttp.send(null);
		}

	} else {

		alert("No categories are selected.");

	}

}

function feedEditCancel() {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	closeInfoBox();

	selectPrefRows('feed', false); // cleanup feed selection

	active_feed = false;
}

function feedCatEditCancel() {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	active_feed_cat = false;

//	notify("Operation cancelled.");

	xmlhttp.open("GET", "backend.php?op=pref-feeds", true);
	xmlhttp.onreadystatechange=feedlist_callback;
	xmlhttp.send(null);

}

function feedEditSave() {

	try {

		var feed = active_feed;
	
		if (!xmlhttp_ready(xmlhttp)) {
			printLockingError();
			return
		}
	
		var link = document.getElementById("iedit_link").value;
		var title = document.getElementById("iedit_title").value;
		var upd_intl = document.getElementById("iedit_updintl");

		upd_intl = upd_intl[upd_intl.selectedIndex].id;
			
		var purge_intl = document.getElementById("iedit_purgintl");

		purge_intl = purge_intl[purge_intl.selectedIndex].id;
		
		var fcat = document.getElementById("iedit_fcat");

		var is_pvt = document.getElementById("iedit_private");
		var is_rtl = document.getElementById("iedit_rtl");

		if (is_pvt) {
			is_pvt = is_pvt.checked;
		}

		if (is_rtl) {
			is_rtl = is_rtl.checked;
		}

		var fcat_id = 0;
	
		if (fcat) {	
			fcat_id = fcat[fcat.selectedIndex].id;
		}

		var pfeed = document.getElementById("iedit_parent_feed");
		var parent_feed_id = pfeed[pfeed.selectedIndex].id;
	
		if (link.length == 0) {
			notify("Feed link cannot be blank.");
			return;
		}
	
		if (title.length == 0) {
			notify("Feed title cannot be blank.");
			return;
		}

		if (!isValidURL(link)) {
			alert("Feed URL is invalid.");
			return;
		}
	
		var auth_login = document.getElementById("iedit_login").value;
		var auth_pass = document.getElementById("iedit_pass").value;
	
		active_feed = false;
	
		notify("Saving feed...");

		var query = "op=pref-feeds&subop=editSave&id=" +
			feed + "&l=" + param_escape(link) + "&t=" + param_escape(title) +
			"&ui=" + param_escape(upd_intl) + "&pi=" + param_escape(purge_intl) +
			"&catid=" + param_escape(fcat_id) + "&login=" + param_escape(auth_login) +			
			"&pfeed=" + param_escape(parent_feed_id) + "&pass=" + param_escape(auth_pass) +
			"&is_pvt=" + param_escape(is_pvt) + "&is_rtl=" + param_escape(is_rtl);

		selectPrefRows('feed', false); // cleanup feed selection

		xmlhttp.open("POST", "backend.php", true);
		xmlhttp.onreadystatechange=feedlist_callback;
		xmlhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		xmlhttp.send(query); 
	
	} catch (e) {
		exception_error("feedEditSave", e);
	} 
}

function feedCatEditSave() {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	notify("Saving category...");

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

//	notify("Operation cancelled.");

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

//	notify("Operation cancelled.");

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

//	notify("Operation cancelled.");

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

	notify("Saving label...");

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
	var level = document.getElementById("iedit_ulevel");

	level = level[level.selectedIndex].id;
	
	var email = document.getElementById("iedit_email").value;

	if (login.length == 0) {
		notify("Login cannot be blank.");
		return;
	}

	if (level.length == 0) {
		notify("User level cannot be blank.");
		return;
	}

	active_user = false;

	notify("Saving user...");

	xmlhttp.open("GET", "backend.php?op=pref-users&subop=editSave&id=" +
		user + "&l=" + param_escape(login) + "&al=" + param_escape(level) +
		"&e=" + param_escape(email), true);
		
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
	var match = document.getElementById("iedit_match");

	var v_match = match[match.selectedIndex].text;

	var feed = document.getElementById("iedit_feed");
	var feed_id = feed[feed.selectedIndex].id;

	var action = document.getElementById("iedit_filter_action");
	var action_id = action[action.selectedIndex].id;

	if (regexp.length == 0) {
		alert("Can't save filter: match expression is blank.");
		return;
	}

	active_filter = false;

	xmlhttp.open("GET", "backend.php?op=pref-filters&subop=editSave&id=" +
		filter + "&r=" + param_escape(regexp) + "&m=" + param_escape(v_match) + 
		"&fid=" + param_escape(feed_id) + "&aid=" + param_escape(action_id), true);

	notify("Saving filter...");

	xmlhttp.onreadystatechange=filterlist_callback;
	xmlhttp.send(null); 

}

function editSelectedLabel() {
	var rows = getSelectedLabels();

	if (rows.length == 0) {
		alert("No labels are selected.");
		return;
	}

	if (rows.length > 1) {
		alert("Please select only one label.");
		return;
	}

	notify("");

	editLabel(rows[0]);

}

function editSelectedUser() {
	var rows = getSelectedUsers();

	if (rows.length == 0) {
		alert("No users are selected.");
		return;
	}

	if (rows.length > 1) {
		alert("Please select only one user.");
		return;
	}

	notify("");

	editUser(rows[0]);
}

function resetSelectedUserPass() {
	var rows = getSelectedUsers();

	if (rows.length == 0) {
		alert("No users are selected.");
		return;
	}

	if (rows.length > 1) {
		alert("Please select only one user.");
		return;
	}

	var ok = confirm("Reset password of selected user?");

	if (ok) {
		notify("Resetting password for selected user...");
	
		var id = rows[0];
	
		xmlhttp.open("GET", "backend.php?op=pref-users&subop=resetPass&id=" +
			param_escape(id), true);
		xmlhttp.onreadystatechange=userlist_callback;
		xmlhttp.send(null);
	}
}

function selectedUserDetails() {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	var rows = getSelectedUsers();

	if (rows.length == 0) {
		alert("No users are selected.");
		return;
	}

	if (rows.length > 1) {
		alert("Please select only one user.");
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
		alert("No feeds are selected.");
		return;
	}

	if (rows.length > 1) {
		notify("Please select only one feed.");
		return;
	}

//	var id = rows[0];

	notify("");

	xmlhttp.open("GET", "backend.php?op=feed-details&id=" + 
		param_escape(rows.toString()), true);
	xmlhttp.onreadystatechange=infobox_callback;
	xmlhttp.send(null);

}

function editSelectedFilter() {
	var rows = getSelectedFilters();

	if (rows.length == 0) {
		alert("No filters are selected.");
		return;
	}

	if (rows.length > 1) {
		alert("Please select only one filter.");
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
		alert("No categories are selected.");
		return;
	}

	if (rows.length > 1) {
		alert("Please select only one category.");
		return;
	}

	notify("");

	editFeedCat(rows[0]);

}

function piggie_callback() {
	var piggie = document.getElementById("piggie");

	piggie.style.top = piggie_top;
	piggie.style.backgroundColor = "white";
	piggie.style.borderWidth = "1px";

	if (piggie_fwd && piggie_top < 0) {
		setTimeout("piggie_callback()", 50);
		piggie_top = piggie_top + 10;
	} else if (piggie_fwd && piggie_top >= 0) {
		piggie_fwd = false;
		setTimeout("piggie_callback()", 50);
	} else if (!piggie_fwd && piggie_top > -400) {
		setTimeout("piggie_callback()", 50);
		piggie_top = piggie_top - 10;
	} else if (!piggie_fwd && piggie_top <= -400) {
		piggie.style.display = "none";
		piggie_fwd = true;
	}
}

var piggie_opacity = 0;

function piggie2_callback() {
	var piggie = document.getElementById("piggie");
	piggie.style.top = 0;
	piggie.style.opacity = piggie_opacity;
	piggie.style.backgroundColor = "transparent";
	piggie.style.borderWidth = "0px";

	if (piggie_fwd && piggie_opacity < 1) {
		setTimeout("piggie2_callback()", 50);
		piggie_opacity = piggie_opacity + 0.03;
	} else if (piggie_fwd && piggie_opacity >= 1) {
		piggie_fwd = false;
		setTimeout("piggie2_callback()", 50);
	} else if (!piggie_fwd && piggie_opacity > 0) {
		setTimeout("piggie2_callback()", 50);
		piggie_opacity = piggie_opacity - 0.03;
	} else if (!piggie_fwd && piggie_opacity <= 0) {
		piggie.style.display = "none";
		piggie_fwd = true;
	}
}

function localPiggieFunction(enable) {
	if (enable) {
		var piggie = document.getElementById("piggie");
		piggie.style.display = "block";

		if (navigator.userAgent.match("Gecko") && Math.random(1) > 0.5) {	
			piggie2_callback();
		} else {
			piggie_callback();
		}
	}
}

function validateOpmlImport() {
	
	var opml_file = document.getElementById("opml_file");

	if (opml_file.value.length == 0) {
		alert("No OPML file to upload.");
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

//	p_notify("Loading, please wait...");

	xmlhttp.open("GET", "backend.php?op=pref-filters", true);
	xmlhttp.onreadystatechange=filterlist_callback;
	xmlhttp.send(null);

}

function updateLabelList() {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

//	p_notify("Loading, please wait...");

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

//	p_notify("Loading, please wait...");

	xmlhttp.open("GET", "backend.php?op=pref-prefs", true);
	xmlhttp.onreadystatechange=prefslist_callback;
	xmlhttp.send(null);

}

function selectTab(id, noupdate) {

//	alert(id);

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	if (!noupdate) {

		notify("Loading, please wait...", true);

		// clean up all current selections, just in case
		active_feed = false;
		active_feed_cat = false;
		active_filter = false;
		active_label = false;
		active_user = false;

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
		} else if (id == "feedBrowser") {
			updateBigFeedBrowser();
		}
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
	
		if (arguments.callee.done) return;
		arguments.callee.done = true;		

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

		alert("No feeds are selected.");

	}

}

function validatePrefsReset() {
	return confirm("Reset to defaults?");
}

function browseFeeds(limit) {

	xmlhttp.open("GET", "backend.php?op=pref-feeds&subop=browse", true);
	xmlhttp.onreadystatechange=infobox_callback;
	xmlhttp.send(null);

}

function feedBrowserSubscribe() {
	try {

		var selected = getSelectedFeedsFromBrowser();

		if (selected.length > 0) {
			closeInfoBox();
			xmlhttp.open("GET", "backend.php?op=pref-feeds&subop=massSubscribe&ids="+
				param_escape(selected.toString()), true);
			xmlhttp.onreadystatechange=feedlist_callback;
			xmlhttp.send(null);
		} else {
			alert("No feeds are selected.");
		}

	} catch (e) {
		exception_error("feedBrowserSubscribe", e);
	}
}

function updateBigFeedBrowser(limit) {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

//	p_notify("Loading, please wait...");

	var query = "backend.php?op=pref-feed-browser";

	var limit_sel = document.getElementById("feedBrowserLimit");

	if (limit_sel) {
		var limit = limit_sel[limit_sel.selectedIndex].value;
		query = query + "&limit=" + param_escape(limit);
	}

	xmlhttp.open("GET", query, true);
	xmlhttp.onreadystatechange=feed_browser_callback;
	xmlhttp.send(null);
}

function browserToggleExpand(id) {
	try {
/*		if (feed_to_expand && feed_to_expand != id) {
			var d = document.getElementById("BRDET-" + feed_to_expand);
			d.style.display = "none";
		} */

		if (!xmlhttp_ready(xmlhttp)) {
			printLockingError();
			return
		}

		var d = document.getElementById("BRDET-" + id);

		if (d.style.display == "block") {		
			d.style.display = "none";
			
		} else {
	
			feed_to_expand = id;

			d.style.display = "block";
			d.innerHTML = "Loading, please wait...";

			xmlhttp.open("GET", "backend.php?op=pref-feed-browser&subop=details&id="
				+ param_escape(id), true);
			xmlhttp.onreadystatechange=expand_feed_callback;
			xmlhttp.send(null);
		}

	} catch (e) {
		exception_error("browserExpand", e);
	}
}

function validateNewPassword(form) {
	if (form.OLD_PASSWORD.value == "") {
		alert("Current password cannot be blank");
		return false;
	}
	if (form.NEW_PASSWORD.value == "") {
		alert("New password cannot be blank");
		return false;
	}
	return true;
}

function selectPrefRows(kind, select) {

	if (kind) {
		var opbarid = false;	
		var nchk = false;
		var nrow = false;
		var lname = false;

		if (kind == "feed") {
			opbarid = "feedOpToolbar";
			nrow = "FEEDR-";
			nchk = "FRCHK-";			
			lname = "prefFeedList";
		} else if (kind == "fcat") {
			opbarid = "catOpToolbar";
			nrow = "FCATR-";
			nchk = "FCHK-";
			lname = "prefFeedCatList";
		} else if (kind == "filter") {
			opbarid = "filterOpToolbar";
			nrow = "FILRR-";
			nchk = "FICHK-";
			lname = "prefFilterList";
		} else if (kind == "label") {
			opbarid = "labelOpToolbar";
			nrow = "LILRR-";
			nchk = "LCHK-";
			lname = "prefLabelList";
		} else if (kind == "user") {
			opbarid = "userOpToolbar";
			nrow = "UMRR-";
			nchk = "UMCHK-";
			lname = "prefUserList";
		}

		if (opbarid) {
			selectTableRowsByIdPrefix(lname, nrow, nchk, select);
			disableContainerChildren(opbarid, !select);
		}

	} 
}


function toggleSelectPrefRow(sender, kind) {

	toggleSelectRow(sender);

	if (kind) {
		var opbarid = false;	
		var nsel = -1;
		
		if (kind == "feed") {
			opbarid = "feedOpToolbar";
			nsel = getSelectedFeeds();
		} else if (kind == "fcat") {
			opbarid = "catOpToolbar";
			nsel = getSelectedFeedCats();
		} else if (kind == "filter") {
			opbarid = "filterOpToolbar";
			nsel = getSelectedFilters();
		} else if (kind == "label") {
			opbarid = "labelOpToolbar";
			nsel = getSelectedLabels();
		} else if (kind == "user") {
			opbarid = "userOpToolbar";
			nsel = getSelectedUsers();
		}

		if (opbarid && nsel != -1) {
			disableContainerChildren(opbarid, nsel == false);
		}

	} 
}

function toggleSelectFBListRow(sender) {
	toggleSelectListRow(sender);
	disableContainerChildren("fbrOpToolbar", getSelectedFeedsFromBrowser() == 0);
}

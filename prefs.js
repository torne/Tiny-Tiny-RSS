/*
	This program is Copyright (c) 2003-2005 Andrew Dolgov <cthulhoo@gmail.com>		
	Licensed under GPL v.2 or (at your preference) any later version.
*/

var xmlhttp = false;

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
	var container = document.getElementById('feeds');
	if (xmlhttp.readyState == 4) {
		container.innerHTML=xmlhttp.responseText;
	}
}

function notify_callback() {
	var container = document.getElementById('notify');
	if (xmlhttp.readyState == 4) {
		container.innerHTML=xmlhttp.responseText;
	}
}


function updateFeedList() {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	document.getElementById("feeds").innerHTML = "Loading feeds, please wait...";

	xmlhttp.open("GET", "backend.php?op=pref-feeds", true);
	xmlhttp.onreadystatechange=feedlist_callback;
	xmlhttp.send(null);

}

function toggleSelectRow(sender) {
	var parent_row = sender.parentNode.parentNode;

	if (sender.checked) {
		if (!parent_row.className.match("Selected")) {
			parent_row.className = parent_row.className + "Selected";
		}
	} else {
		if (parent_row.className.match("Selected")) {
			parent_row.className = parent_row.className.replace("Selected", "");
		}
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

function editFeed(feed) {

//	notify("Editing feed...");

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	xmlhttp.open("GET", "backend.php?op=pref-feeds&subop=edit&id=" +
		param_escape(feed), true);
	xmlhttp.onreadystatechange=feedlist_callback;
	xmlhttp.send(null);

}

function getSelectedFeeds() {

	var content = document.getElementById("prefFeedList");

	var sel_rows = new Array();

	for (i = 0; i < content.rows.length; i++) {
		if (content.rows[i].className.match("Selected")) {
			var row_id = content.rows[i].id.replace("FEEDR-", "");
			sel_rows.push(row_id);	
		}
	}

	return sel_rows;
}

function readSelectedFeeds() {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	var sel_rows = getSelectedFeeds();

	if (sel_rows.length > 0) {

		notify("Marking selected feeds as read...");

		xmlhttp.open("GET", "backend.php?op=pref-rpc&subop=unread&ids="+
			param_escape(sel_rows.toString()), true);
		xmlhttp.onreadystatechange=notify_callback;
		xmlhttp.send(null);

	} else {

		notify("Please select some feeds first.");

	}
}

function unreadSelectedFeeds() {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	var sel_rows = getSelectedFeeds();

	if (sel_rows.length > 0) {

		notify("Marking selected feeds as unread...");

		xmlhttp.open("GET", "backend.php?op=pref-rpc&subop=unread&ids="+
			param_escape(sel_rows.toString()), true);
		xmlhttp.onreadystatechange=notify_callback;
		xmlhttp.send(null);

	} else {

		notify("Please select some feeds first.");

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

function feedEditCancel() {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	notify("Operation cancelled.");

	xmlhttp.open("GET", "backend.php?op=pref-feeds", true);
	xmlhttp.onreadystatechange=feedlist_callback;
	xmlhttp.send(null);

}

function feedEditSave(feed) {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	notify("Saving feed.");

	var link = document.getElementById("fedit_link").value;
	var title = document.getElementById("fedit_title").value;

	if (link.length == 0) {
		notify("Feed link cannot be blank.");
		return;
	}

	if (title.length == 0) {
		notify("Feed title cannot be blank.");
		return;
	}

	xmlhttp.open("GET", "backend.php?op=pref-feeds&subop=editSave&id=" +
		feed + "&l=" + param_escape(link) + "&t=" + param_escape(title) ,true);
	xmlhttp.onreadystatechange=feedlist_callback;
	xmlhttp.send(null);

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

	editFeed(rows[0]);

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

function init() {
	
	updateFeedList();
	document.onkeydown = hotkey_handler;
	notify("");

}

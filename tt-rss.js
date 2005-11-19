/*
	This program is Copyright (c) 2003-2005 Andrew Dolgov <cthulhoo@gmail.com>		
	Licensed under GPL v.2 or (at your preference) any later version.
*/

var xmlhttp = false;

var total_unread = 0;
var first_run = true;

var display_tags = false;

var global_unread = 0;

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

function toggleTags() {
	display_tags = !display_tags;

	var p = document.getElementById("dispSwitchPrompt");

	if (display_tags) {
		p.innerHTML = "display feeds";
	} else {
		p.innerHTML = "display tags";
	}
	
	updateFeedList();
}

function dlg_frefresh_callback() {
	if (xmlhttp.readyState == 4) {
		updateFeedList(false, false);
		closeDlg();
	} 
}

function dialog_refresh_callback() {
	if (xmlhttp.readyState == 4) {
		var dlg = document.getElementById("userDlg");

		dlg.innerHTML = xmlhttp.responseText;
		dlg.style.display = "block";
	} 
}

function refetch_callback() {
	if (xmlhttp.readyState == 4) {

//		document.title = "Tiny Tiny RSS";

		if (!xmlhttp.responseXML) {
			notify("refetch_callback: backend did not return valid XML");
			return;
		}

		var reply = xmlhttp.responseXML.firstChild;

		if (!reply) {
			notify("refetch_callback: backend did not return expected XML object");
			return;
		} 

		var f_document = window.frames["feeds-frame"].document;

		for (var l = 0; l < reply.childNodes.length; l++) {
			var id = reply.childNodes[l].getAttribute("id");
			var ctr = reply.childNodes[l].getAttribute("counter");

			var feedctr = f_document.getElementById("FEEDCTR-" + id);
			var feedu = f_document.getElementById("FEEDU-" + id);
			var feedr = f_document.getElementById("FEEDR-" + id);

/*			TODO figure out how to update this from viewfeed.js->view()
			disabled for now...

			if (id == "global-unread") {
				global_unread = ctr;
			} */

			if (feedctr && feedu && feedr) {

				feedu.innerHTML = ctr;
	
				if (ctr > 0) {
					feedctr.className = "odd";
					if (!feedr.className.match("Unread")) {
						feedr.className = feedr.className + "Unread";
					}
				} else {
					feedctr.className = "invisible";
					feedr.className = feedr.className.replace("Unread", "");
				}
			}
		}  

		updateTitle("");
		notify("All feeds updated.");

	}
}

function backend_sanity_check_callback() {

	if (xmlhttp.readyState == 4) {
		
		if (!xmlhttp.responseXML) {
			fatalError(3);
			return;
		}

		var reply = xmlhttp.responseXML.firstChild;

		if (!reply) {
			fatalError(3);
			return;
		}

		var error_code = reply.getAttribute("code");
	
		if (error_code && error_code != 0) {
			return fatalError(error_code);
		}

		init_second_stage();
	} 
}

function updateFeed(feed_id) {

	var query_str = "backend.php?op=rpc&subop=updateFeed&feed=" + feed_id;

	if (xmlhttp_ready(xmlhttp)) {
		xmlhttp.open("GET", query_str, true);
		xmlhttp.onreadystatechange=feed_update_callback;
		xmlhttp.send(null);
	} else {
		printLockingError();
	}   

}

function scheduleFeedUpdate(force) {

	notify("Updating feeds in background...");

//	document.title = "Tiny Tiny RSS - Updating...";

	updateTitle("Updating...");

	var query_str = "backend.php?op=rpc&subop=";

	if (force) {
		query_str = query_str + "forceUpdateAllFeeds";
	} else {
		query_str = query_str + "updateAllFeeds";
	}

	var omode;

	if (display_tags) {
		omode = "t";
	} else {
		omode = "fl";
	}

	query_str = query_str + "&omode=" + omode;

	if (xmlhttp_ready(xmlhttp)) {
		xmlhttp.open("GET", query_str, true);
		xmlhttp.onreadystatechange=refetch_callback;
		xmlhttp.send(null);
	} else {
		printLockingError();
	}   
}

function updateFeedList(silent, fetch) {

//	if (silent != true) {
//		notify("Loading feed list...");
//	}

	var query_str = "backend.php?op=feeds";

	if (display_tags) {
		query_str = query_str + "&tags=1";
	}

	if (getActiveFeedId()) {
		query_str = query_str + "&actid=" + getActiveFeedId();
	}

	if (fetch) query_str = query_str + "&fetch=yes";

	var feeds_frame = document.getElementById("feeds-frame");

	feeds_frame.src = query_str;
}

function catchupAllFeeds() {

	var query_str = "backend.php?op=feeds&subop=catchupAll";

	notify("Marking all feeds as read...");

	var feeds_frame = document.getElementById("feeds-frame");

	feeds_frame.src = query_str;

	global_unread = 0;
	updateTitle();

}

function viewCurrentFeed(skip, subop) {

	if (getActiveFeedId()) {
		viewfeed(getActiveFeedId(), skip, subop);
	} else {
		disableContainerChildren("headlinesToolbar", false, document);
		viewfeed(-1, skip, subop); // FIXME
	}
}

function viewfeed(feed, skip, subop) {
	var f = window.frames["feeds-frame"];
	f.viewfeed(feed, skip, subop);
}

function timeout() {
	scheduleFeedUpdate(false);
	setTimeout("timeout()", 1800*1000);
}

function resetSearch() {
	var searchbox = document.getElementById("searchbox")

	if (searchbox.value != "" && getActiveFeedId()) {	
		searchbox.value = "";
		viewfeed(getActiveFeedId(), 0, "");
	}
}

function search() {
	viewCurrentFeed(0, "");
}

function localPiggieFunction(enable) {
	if (enable) {
		var query_str = "backend.php?op=feeds&subop=piggie";

		if (xmlhttp_ready(xmlhttp)) {

			xmlhttp.open("GET", query_str, true);
			xmlhttp.onreadystatechange=feedlist_callback;
			xmlhttp.send(null);
		}
	}
}

function localHotkeyHandler(keycode) {

/*	if (keycode == 78) {
		return moveToPost('next');
	}

	if (keycode == 80) {
		return moveToPost('prev');
	} */

	if (keycode == 82) { // r
		return scheduleFeedUpdate(true);
	}

	if (keycode == 85) { // u
		if (getActiveFeedId()) {
			return viewfeed(getActiveFeedId(), 0, "ForceUpdate");
		}
	}

	if (keycode == 65) { // a
		return toggleDispRead();
	}

//	notify("KC: " + keycode);

}

function updateTitle(s) {
	var tmp = "Tiny Tiny RSS";
	
	if (global_unread > 0) {
		tmp = tmp + " (" + global_unread + ")";
	}

	if (s) {
		tmp = tmp + " - " + s;
	}
	document.title = tmp;
}

function genericSanityCheck() {

	if (!xmlhttp) fatalError(1);

	setCookie("ttrss_vf_test", "TEST");
	
	if (getCookie("ttrss_vf_test") != "TEST") {
		fatalError(2);
	}

/*	if (!xmlhttp) {
		document.getElementById("headlines").innerHTML = 
			"<b>Fatal error:</b> This program requires XmlHttpRequest " + 
			"to function properly. Your browser doesn't seem to support it.";
		return false;
	}

	setCookie("ttrss_vf_test", "TEST");
	if (getCookie("ttrss_vf_test") != "TEST") {

		document.getElementById("headlines").innerHTML = 
			"<b>Fatal error:</b> This program requires cookies " + 
			"to function properly. Your browser doesn't seem to support them.";

		return false;
	} */

	return true;
}

function init() {

	disableContainerChildren("headlinesToolbar", true);

	if (!genericSanityCheck()) 
		return;

	xmlhttp.open("GET", "backend.php?op=rpc&subop=sanityCheck", true);
	xmlhttp.onreadystatechange=backend_sanity_check_callback;
	xmlhttp.send(null);

}

function init_second_stage() {

	setCookie("ttrss_vf_actfeed", "");

	updateFeedList(false, false);
	document.onkeydown = hotkey_handler;

	var content = document.getElementById("content");

	if (getCookie("ttrss_vf_vmode")) {
		var viewbox = document.getElementById("viewbox");
		viewbox.value = getCookie("ttrss_vf_vmode");
	}

	if (getCookie("ttrss_vf_limit")) {
		var limitbox = document.getElementById("limitbox");
		limitbox.value = getCookie("ttrss_vf_limit");
	}

//	if (getCookie("ttrss_vf_actfeed")) {
//		viewfeed(getCookie("ttrss_vf_actfeed"), 0, '');
//	}

//	setTimeout("timeout()", 2*1000);
//	scheduleFeedUpdate(true);

	var splash = document.getElementById("splash");

	if (splash) {
		splash.style.display = "none";
	}

}

function quickMenuGo() {

	var chooser = document.getElementById("quickMenuChooser");
	var opid = chooser[chooser.selectedIndex].id;

	if (opid == "qmcPrefs") {
		gotoPreferences();
	}

	if (opid == "qmcAdvSearch") {
		displayDlg("search");
		return;
	}

	if (opid == "qmcAddFeed") {
		displayDlg("quickAddFeed");
		return;
	}

	if (opid == "qmcRemoveFeed") {
		var actid = getActiveFeedId();

		if (!actid) {
			notify("Please select some feed first.");
			return;
		}
	
		displayDlg("quickDelFeed", actid);
		return;
	}

	if (opid == "qmcUpdateFeeds") {
		scheduleFeedUpdate(true);
		return;
	}

	if (opid == "qmcCatchupAll") {
		catchupAllFeeds();
		return;
	}

	if (opid == "qmcShowOnlyUnread") {
		toggleDispRead();
		return;
	}

}

function qafAdd() {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	var link = document.getElementById("qafInput");

	if (link.value.length == 0) {
		notify("Missing feed URL.");
	} else {
		notify("Adding feed...");
		
		var feeds_doc = window.frames["feeds-frame"].document;

		feeds_doc.location.href = "backend.php?op=error&msg=Loading,%20please wait...";

		xmlhttp.open("GET", "backend.php?op=pref-feeds&subop=add&link=" +
			param_escape(link.value), true);
		xmlhttp.onreadystatechange=dlg_frefresh_callback;
		xmlhttp.send(null);

		link.value = "";

	}
}

function displayDlg(id, param) {

	notify("");

	xmlhttp.open("GET", "backend.php?op=dlg&id=" +
		param_escape(id) + "&param=" + param_escape(param), true);
	xmlhttp.onreadystatechange=dialog_refresh_callback;
	xmlhttp.send(null);

}

function closeDlg() {
	var dlg = document.getElementById("userDlg");
	dlg.style.display = "none";
}

function qfdDelete(feed_id) {

	notify("Removing feed...");

	var feeds_doc = window.frames["feeds-frame"].document;
	feeds_doc.location.href = "backend.php?op=error&msg=Loading,%20please wait...";

	xmlhttp.open("GET", "backend.php?op=pref-feeds&subop=remove&ids=" + feed_id);
	xmlhttp.onreadystatechange=dlg_frefresh_callback;
	xmlhttp.send(null);
}


function allFeedsMenuGo() {
	var chooser = document.getElementById("allFeedsChooser");

	var opname = chooser[chooser.selectedIndex].text;

	if (opname == "Update") {
		scheduleFeedUpdate(true);
		return;
	}

	if (opname == "Mark as read") {
		catchupAllFeeds();
		return;
	}

	if (opname == "Show only read") {
		toggleDispRead();
		return;
	}

}

function toggleDispRead() {
	var hide_read_feeds = (getCookie("ttrss_vf_hreadf") == 1);

	hide_read_feeds = !hide_read_feeds;

	var feeds_doc = window.frames["feeds-frame"].document;

	hideOrShowFeeds(feeds_doc, hide_read_feeds);

	if (hide_read_feeds) {
		setCookie("ttrss_vf_hreadf", 1);
	} else {
		setCookie("ttrss_vf_hreadf", 0);
	}

}



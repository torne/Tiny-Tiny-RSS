/*
	This program is Copyright (c) 2003-2005 Andrew Dolgov <cthulhoo@gmail.com>		
	Licensed under GPL v.2 or (at your preference) any later version.
*/

var xmlhttp = false;
var xmlhttp_rpc = false;
var xmlhttp_view = false;

var total_unread = 0;
var first_run = true;

var active_feed_id = false;

var search_query = "";

/*@cc_on @*/
/*@if (@_jscript_version >= 5)
// JScript gives us Conditional compilation, we can cope with old IE versions.
// and security blocked creation of the objects.
try {
	xmlhttp = new ActiveXObject("Msxml2.XMLHTTP");
} catch (e) {
	try {
		xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
		xmlhttp_rpc = new ActiveXObject("Microsoft.XMLHTTP");
		xmlhttp_view = new ActiveXObject("Microsoft.XMLHTTP");
	} catch (E) {
		xmlhttp = false;
		xmlhttp_rpc = false;
		xmlhttp_view = false;
	}
}
@end @*/

if (!xmlhttp && typeof XMLHttpRequest!='undefined') {
	xmlhttp = new XMLHttpRequest();
	xmlhttp_rpc = new XMLHttpRequest();
	xmlhttp_view = new XMLHttpRequest();
}

/*
function feedlist_callback() {
	var container = document.getElementById('feeds');
	if (xmlhttp.readyState == 4) {
		container.innerHTML=xmlhttp.responseText;

		if (first_run) {
			scheduleFeedUpdate(false);
			if (getCookie("ttrss_vf_actfeed")) {
				viewfeed(getCookie("ttrss_vf_actfeed"), 0, "");
			}
			first_run = false;
		} else {
			notify("");
		} 
	} 
}
*/

function checkActiveFeedId() {

	var actfeedid = frames["feeds-frame"].document.getElementById("ACTFEEDID");

	if (actfeedid) {
		active_feed_id = actfeedid.innerHTML;
	}
}

function refetch_callback() {

	if (xmlhttp_rpc.readyState == 4) {

		document.title = "Tiny Tiny RSS";
		notify("All feeds updated.");

		updateFeedList();
		
	} 
}


function updateFeed(feed_id) {

	var query_str = "backend.php?op=rpc&subop=updateFeed&feed=" + feed_id;

	if (xmlhttp_ready(xmlhttp_rpc)) {
		xmlhttp_rpc.open("GET", query_str, true);
		xmlhttp_rpc.onreadystatechange=feed_update_callback;
		xmlhttp_rpc.send(null);
	} else {
		printLockingError();
	}   

}

function scheduleFeedUpdate(force) {

	notify("Updating feeds in background...");

	document.title = "Tiny Tiny RSS - Updating...";

	var query_str = "backend.php?op=rpc&subop=";

	if (force) {
		query_str = query_str + "forceUpdateAllFeeds";
	} else {
		query_str = query_str + "updateAllFeeds";
	}

	if (xmlhttp_ready(xmlhttp_rpc)) {
		xmlhttp_rpc.open("GET", query_str, true);
		xmlhttp_rpc.onreadystatechange=refetch_callback;
		xmlhttp_rpc.send(null);
	} else {
		printLockingError();
	}   
}

function updateFeedList(silent, fetch) {

//	if (silent != true) {
//		notify("Loading feed list...");
//	}

	var query_str = "backend.php?op=feeds";

	if (active_feed_id) {
		query_str = query_str + "&actid=" + active_feed_id;
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

}

function viewCurrentFeed(skip, subop) {

	active_feed_id = frames["feeds-frame"].document.getElementById("ACTFEEDID").innerHTML;

	if (active_feed_id) {
		viewfeed(active_feed_id, skip, subop);
	}
}

function viewfeed(feed, skip, subop) {

//	notify("Loading headlines...");

	enableHotkeys();

	var searchbox = document.getElementById("searchbox");

	if (searchbox) {
		search_query = searchbox.value;
	} else {
		search_query = "";
	} 

	var viewbox = document.getElementById("viewbox");

	var view_mode;

	if (viewbox) {
		view_mode = viewbox.value;
	} else {
		view_mode = "All Posts";
	}

	setCookie("ttrss_vf_vmode", view_mode);

	var limitbox = document.getElementById("limitbox");

	var limit;

	if (limitbox) {
		limit = limitbox.value;
		setCookie("ttrss_vf_limit", limit);
	} else {
		limit = "All";
	}

	active_feed_id = feed;

	var f_doc = frames["feeds-frame"].document;

	f_doc.getElementById("ACTFEEDID").innerHTML = feed;

	setCookie("ttrss_vf_actfeed", feed);

	if (subop == "MarkAllRead") {

		var feedr = f_doc.getElementById("FEEDR-" + feed);
		var feedt = f_doc.getElementById("FEEDT-" + feed);
		var feedu = f_doc.getElementById("FEEDU-" + feed);
		
		feedu.innerHTML = "0";

		if (feedr.className.match("Unread")) {
			feedr.className = feedr.className.replace("Unread", "");
		}
	}

	var query = "backend.php?op=viewfeed&feed=" + param_escape(feed) +
		"&skip=" + param_escape(skip) + "&subop=" + param_escape(subop) +
		"&view=" + param_escape(view_mode) + "&limit=" + limit;

	if (search_query != "") {
		query = query + "&search=" + param_escape(search_query);
	}
	
	var headlines_frame = parent.frames["headlines-frame"];

	headlines_frame.location.href = query + "&addheader=true";

	cleanSelected("feedsList");
	var feedr = document.getElementById("FEEDR-" + feed);
	if (feedr) {
		feedr.className = feedr.className + "Selected";
	}
	
	disableContainerChildren("headlinesToolbar", false, doc);

//	notify("");

}


function timeout() {
	scheduleFeedUpdate(true);
	setTimeout("timeout()", 1800*1000);
}

function resetSearch() {
	var searchbox = document.getElementById("searchbox")

	if (searchbox.value != "" && active_feed_id) {	
		searchbox.value = "";
		viewfeed(active_feed_id, 0, "");
	}
}

function search() {
	checkActiveFeedId();
	if (active_feed_id) {
		viewfeed(active_feed_id, 0, "");
	} else {
		notify("Please select some feed first.");
	}
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

	if (keycode == 82) {
		return scheduleFeedUpdate(true);
	}

	if (keycode == 85) {
		return viewfeed(active_feed_id, active_offset, "ForceUpdate");
	}

//	notify("KC: " + keycode);

}

function init() {

	disableContainerChildren("headlinesToolbar", true);

	// IE kludge

	if (xmlhttp && !xmlhttp_rpc) {
		xmlhttp_rpc = xmlhttp;
		xmlhttp_view = xmlhttp;
	}

	if (!xmlhttp || !xmlhttp_rpc || !xmlhttp_view) {
		document.getElementById("headlines").innerHTML = 
			"<b>Fatal error:</b> This program needs XmlHttpRequest " + 
			"to function properly. Your browser doesn't seem to support it.";
		return;
	}

	updateFeedList(false, false);
	document.onkeydown = hotkey_handler;

//	setTimeout("timeout()", 1800*1000);
//	scheduleFeedUpdate(true);

	var content = document.getElementById("content");

	if (getCookie("ttrss_vf_vmode")) {
		var viewbox = document.getElementById("viewbox");
		viewbox.value = getCookie("ttrss_vf_vmode");
	}

	if (getCookie("ttrss_vf_limit")) {
		var limitbox = document.getElementById("limitbox");
		limitbox.value = getCookie("ttrss_vf_limit");
	}
}



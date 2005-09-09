/*
	This program is Copyright (c) 2003-2005 Andrew Dolgov <cthulhoo@gmail.com>		
	Licensed under GPL v.2 or (at your preference) any later version.
*/

var xmlhttp = false;

var total_unread = 0;
var first_run = true;

var search_query = "";

var display_tags = false;

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

function refetch_callback() {
	if (xmlhttp.readyState == 4) {

		document.title = "Tiny Tiny RSS";
		notify("All feeds updated.");

		var reply = xmlhttp.responseXML.firstChild;

		var f_document = window.frames["feeds-frame"].document;

		for (var l = 0; l < reply.childNodes.length; l++) {
			var id = reply.childNodes[l].getAttribute("id");
			var ctr = reply.childNodes[l].getAttribute("counter");

			var feedctr = f_document.getElementById("FEEDCTR-" + id);
			var feedu = f_document.getElementById("FEEDU-" + id);
			var feedr = f_document.getElementById("FEEDR-" + id);

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

	document.title = "Tiny Tiny RSS - Updating...";

	var query_str = "backend.php?op=rpc&subop=";

	if (force) {
		query_str = query_str + "forceUpdateAllFeeds";
	} else {
		query_str = query_str + "updateAllFeeds";
	}

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

}

function viewCurrentFeed(skip, subop) {

	if (getActiveFeedId()) {
		viewfeed(getActiveFeedId(), skip, subop);
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
		view_mode = viewbox[viewbox.selectedIndex].text;
	} else {
		view_mode = "All Posts";
	}

	setCookie("ttrss_vf_vmode", view_mode);

	var limitbox = document.getElementById("limitbox");

	var limit;

	if (limitbox) {
		limit = limitbox[limitbox.selectedIndex].text;
		setCookie("ttrss_vf_limit", limit);
	} else {
		limit = "All";
	}

	setActiveFeedId(feed);

	var f_doc = frames["feeds-frame"].document;

//	f_doc.getElementById("ACTFEEDID").innerHTML = feed;

	if (subop == "MarkAllRead") {

		var feedr = f_doc.getElementById("FEEDR-" + feed);
		var feedctr = f_doc.getElementById("FEEDCTR-" + feed);
		
		feedctr.className = "invisible";

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

	if (searchbox.value != "" && getActiveFeedId()) {	
		searchbox.value = "";
		viewfeed(getActiveFeedId(), 0, "");
	}
}

function search() {
	checkActiveFeedId();
	if (getActiveFeedId()) {
		viewfeed(getActiveFeedId(), 0, "");
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
		return viewfeed(getActiveFeedId(), 0, "ForceUpdate");
	}

//	notify("KC: " + keycode);

}

function genericSanityCheck() {

	if (!xmlhttp) {
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
	}

	return true;
}

function init() {

	disableContainerChildren("headlinesToolbar", true);

	if (!genericSanityCheck()) 
		return;

	updateFeedList(false, false);
	document.onkeydown = hotkey_handler;

	setTimeout("timeout()", 1800*1000);
	scheduleFeedUpdate(true);

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

	setCookie("ttrss_vf_actfeed", "");

}



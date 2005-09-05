/*
	This program is Copyright (c) 2003-2005 Andrew Dolgov <cthulhoo@gmail.com>		
	Licensed under GPL v.2 or (at your preference) any later version.
*/

var xmlhttp = false;
var xmlhttp_rpc = false;
var xmlhttp_view = false;

var total_unread = 0;
var first_run = true;

var active_post_id = false;
var active_feed_id = false;
var active_offset = false;

var total_feed_entries = false;

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

function feedlist_callback() {
	var container = document.getElementById('feeds');
	if (xmlhttp.readyState == 4) {
		container.innerHTML=xmlhttp.responseText;

		if (first_run) {
			scheduleFeedUpdate(false);
			first_run = false;
		} else {
			notify("");
		} 
	} 
}

function refetch_callback() {

	if (xmlhttp_rpc.readyState == 4) {
		notify("All feeds updated");
		var container = document.getElementById('feeds');
		container.innerHTML = xmlhttp_rpc.responseText;
		document.title = "Tiny Tiny RSS";
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

	if (silent != true) {
		notify("Loading feed list...");
	}

	var query_str = "backend.php?op=feeds";

	if (fetch) query_str = query_str + "&fetch=yes";

	if (xmlhttp_ready(xmlhttp)) {
		xmlhttp.open("GET", query_str, true);
		xmlhttp.onreadystatechange=feedlist_callback;
		xmlhttp.send(null);
	} else {
		printLockingError();
	}
}

/*
function catchupPage(feed) {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	var content = document.getElementById("headlinesList");

	var rows = new Array();

	for (i = 0; i < content.rows.length; i++) {
		var row_id = content.rows[i].id.replace("RROW-", "");
		if (row_id.length > 0) {
			if (content.rows[i].className.match("Unread")) {
				rows.push(row_id);	
				content.rows[i].className = content.rows[i].className.replace("Unread", "");
			}

			var upd_img_pic = document.getElementById("FUPDPIC-" + row_id);
			if (upd_img_pic) {
				upd_img_pic.innerHTML = "";
			} 
		}
	}

	if (rows.length > 0) {

		var feedr = document.getElementById("FEEDR-" + feed);
		var feedu = document.getElementById("FEEDU-" + feed);
	
		feedu.innerHTML = feedu.innerHTML - rows.length;
	
		if (feedu.innerHTML > 0 && !feedr.className.match("Unread")) {
				feedr.className = feedr.className + "Unread";
		} else if (feedu.innerHTML <= 0) {	
				feedr.className = feedr.className.replace("Unread", "");
		} 

		var query_str = "backend.php?op=rpc&subop=catchupPage&ids=" + 
			param_escape(rows.toString());
	
		notify("Marking this page as read...");

		var button = document.getElementById("btnCatchupPage");

		if (button) {
			button.className = "disabledButton";
			button.href = "";
		}
	
		xmlhttp.open("GET", query_str, true);
		xmlhttp.onreadystatechange=notify_callback;
		xmlhttp.send(null);

	} else {
		notify("No unread items on this page.");

	}
} */

function catchupAllFeeds() {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}
	var query_str = "backend.php?op=feeds&subop=catchupAll";

	notify("Marking all feeds as read...");

	xmlhttp.open("GET", query_str, true);
	xmlhttp.onreadystatechange=feedlist_callback;
	xmlhttp.send(null);

}

function viewCurrentFeed(skip, subop) {
	if (active_feed_id) {
		viewfeed(active_feed_id, skip, subop);
	}
}

function viewfeed(feed, skip, subop) {

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

	if (skip < 0 || skip > total_feed_entries) {
		return;
	}

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	if (active_feed_id != feed || skip != active_offset) {
		active_post_id = false;
	}

	active_feed_id = feed;
	active_offset = skip;

	if (subop == "MarkAllRead") {

		var feedr = document.getElementById("FEEDR-" + feed);
		var feedt = document.getElementById("FEEDT-" + feed);
		var feedu = document.getElementById("FEEDU-" + feed);

		feedu.innerHTML = "0";

		if (feedr.className.match("Unread")) {
			feedr.className = feedr.className.replace("Unread", "");
		}
	}

	var query = "backend.php?op=viewfeed&feed=" + param_escape(feed) +
		"&skip=" + param_escape(skip) + "&subop=" + param_escape(subop) +
		"&view=" + param_escape(view_mode);

	if (search_query != "") {
		query = query + "&search=" + param_escape(search_query);
	}
	
	var headlines_frame = document.getElementById("headlines-frame");
	
	headlines_frame.src = query + "&addheader=true";

	var feedr = document.getElementById("FEEDR-" + feed);

	cleanSelected("feedsList");
	feedr.className = feedr.className + "Selected";

	var ftitle_d = document.getElementById("headlinesTitle");
	var ftitle_s = document.getElementById("FEEDN-" + feed);

	ftitle_d.innerHTML = ftitle_s.innerHTML;
	
	notify("");

}

function timeout() {
	scheduleFeedUpdate(true);
	setTimeout("timeout()", 1800*1000);
}

function resetSearch() {
	document.getElementById("searchbox").value = "";
	viewfeed(active_feed_id, 0, "");
}

function search() {
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

/*
function moveToPost(mode) {

	var rows = getVisibleHeadlineIds();

	var prev_id;
	var next_id;

	if (active_post_id == false) {
		next_id = getFirstVisibleHeadlineId();
		prev_id = getLastVisibleHeadlineId();
	} else {	
		for (var i = 0; i < rows.length; i++) {
			if (rows[i] == active_post_id) {
				prev_id = rows[i-1];
				next_id = rows[i+1];			
			}
		}
	}

	if (mode == "next") {
	 	if (next_id != undefined) {
			view(next_id, active_feed_id);
		} else {
			_viewfeed_autoselect_first = true;
			viewfeed(active_feed_id, active_offset+15);
		}
	}

	if (mode == "prev") {
		if ( prev_id != undefined) {
			view(prev_id, active_feed_id);
		} else {
			_viewfeed_autoselect_last = true;
			viewfeed(active_feed_id, active_offset-15);
		}
	}

}
*/

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
	setTimeout("timeout()", 1800*1000);

	var content = document.getElementById("content");
}

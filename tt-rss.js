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

var _viewfeed_autoselect_first = false;
var _viewfeed_autoselect_last = false;

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

function viewfeed_callback() {
	var container = document.getElementById('headlines');
	if (xmlhttp.readyState == 4) {
		container.innerHTML = xmlhttp.responseText;

		var factive = document.getElementById("FACTIVE");
		var funread = document.getElementById("FUNREAD");
		var ftotal = document.getElementById("FTOTAL");

		if (_viewfeed_autoselect_first == true) {
			_viewfeed_autoselect_first = false;
			view(getFirstVisibleHeadlineId(), active_feed_id);
		}

		if (_viewfeed_autoselect_last == true) {
			_viewfeed_autoselect_last = false;
			view(getLastVisibleHeadlineId(), active_feed_id);
		}

		if (ftotal && factive && funread) {
			var feed_id = factive.innerHTML;

			var feedr = document.getElementById("FEEDR-" + feed_id);
			var feedt = document.getElementById("FEEDT-" + feed_id);
			var feedu = document.getElementById("FEEDU-" + feed_id);

			feedt.innerHTML = ftotal.innerHTML;
			feedu.innerHTML = funread.innerHTML;

			total_feed_entries = ftotal.innerHTML;	

			if (feedu.innerHTML > 0 && !feedr.className.match("Unread")) {
					feedr.className = feedr.className + "Unread";
			} else if (feedu.innerHTML <= 0) {	
					feedr.className = feedr.className.replace("Unread", "");
			}

			cleanSelected("feedsList");

			feedr.className = feedr.className + "Selected";
		}

		var searchbox = document.getElementById("searchbox");
		searchbox.value = search_query;

		notify("");

	}	
}

function view_callback() {
	var container = document.getElementById('content');
	if (xmlhttp_view.readyState == 4) {
		container.innerHTML=xmlhttp_view.responseText;		
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
	
		xmlhttp.open("GET", query_str, true);
		xmlhttp.onreadystatechange=notify_callback;
		xmlhttp.send(null);

	} else {
		notify("No unread items on this page.");

	}
}

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

function viewfeed(feed, skip, subop) {

	enableHotkeys();

	var searchbox = document.getElementById("searchbox");

	if (searchbox) {
		search_query = searchbox.value;
	} else {
		search_query = "";
	} 

/*	if (active_feed_id == feed && subop != "ForceUpdate") {
		notify("This feed is currently selected.");
		return;
	} */

	if (skip < 0 || skip > total_feed_entries) {
		return;
	}

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	active_feed_id = feed;
	active_post_id = false;
	active_offset = skip;

	var query = "backend.php?op=viewfeed&feed=" + param_escape(feed) +
		"&skip=" + param_escape(skip) + "&subop=" + param_escape(subop);

	if (search_query != "") {
		query = query + "&search=" + param_escape(search_query);
	}
	
	xmlhttp.open("GET", query, true);
	xmlhttp.onreadystatechange=viewfeed_callback;
	xmlhttp.send(null);

	notify("Loading headlines...");

}

function cleanSelected(element) {
	var content = document.getElementById(element);

	var rows = new Array();

	for (i = 0; i < content.rows.length; i++) {
		content.rows[i].className = content.rows[i].className.replace("Selected", "");
	}

}

function view(id,feed_id) {

	enableHotkeys();

	if (!xmlhttp_ready(xmlhttp_view)) {
		printLockingError();
		return
	}

	var crow = document.getElementById("RROW-" + id);

	if (crow.className.match("Unread")) {
		var umark = document.getElementById("FEEDU-" + feed_id);
		umark.innerHTML = umark.innerHTML - 1;
		crow.className = crow.className.replace("Unread", "");

		if (umark.innerHTML == "0") {
			var feedr = document.getElementById("FEEDR-" + feed_id);
			feedr.className = feedr.className.replace("Unread", "");
		}

		total_unread--;
	}	

	cleanSelected("headlinesList");

	crow.className = crow.className + "Selected";

	var upd_img_pic = document.getElementById("FUPDPIC-" + id);

	if (upd_img_pic) {
		upd_img_pic.innerHTML = "";
	} 

	document.getElementById('content').innerHTML='Loading, please wait...';		

	active_post_id = id;

	xmlhttp_view.open("GET", "backend.php?op=view&id=" + param_escape(id), true);
	xmlhttp_view.onreadystatechange=view_callback;
	xmlhttp_view.send(null);


}

function timeout() {
	scheduleFeedUpdate(true);
	setTimeout("timeout()", 1800*1000);
}

function resetSearch() {
	document.getElementById("searchbox").value = "";
	viewfeed(active_feed_id, 0, "");
}

function search(feed) {
	viewfeed(feed, 0, "");
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

function relativeid_callback() {

	if (xmlhttp_rpc.readyState == 4) {
		notify(xmlhttp_rpc.responseText);
	}

}

function getVisibleHeadlineIds() {

	var content = document.getElementById("headlinesList");

	var rows = new Array();

	for (i = 0; i < content.rows.length; i++) {
		var row_id = content.rows[i].id.replace("RROW-", "");
		if (row_id.length > 0) {
				rows.push(row_id);	
		}
	}
	return rows;
}

function getFirstVisibleHeadlineId() {
	var rows = getVisibleHeadlineIds();
	return rows[0];
}

function getLastVisibleHeadlineId() {
	var rows = getVisibleHeadlineIds();
	return rows[rows.length-1];
}

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

	var content = document.getElementById("headlinesList");

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

function localHotkeyHandler(keycode) {

	if (keycode == 78) {
		return moveToPost('next');
	}

	if (keycode == 80) {
		return moveToPost('prev');
	}

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
}

/*
	This program is Copyright (c) 2003-2005 Andrew Dolgov <cthulhoo@gmail.com>		
	Licensed under GPL v.2 or (at your preference) any later version.
*/

var xmlhttp = false;
var xmlhttp_rpc = false;

var total_unread = 0;
var first_run = true;

var active_post_id = false;
var active_feed_id = false;

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
	} catch (E) {
		xmlhttp = false;
		xmlhttp_rpc = false;
	}
}
@end @*/

if (!xmlhttp && typeof XMLHttpRequest!='undefined') {
	xmlhttp = new XMLHttpRequest();
	xmlhttp_rpc = new XMLHttpRequest();

}

function feedlist_callback() {
	var container = document.getElementById('feeds');
	if (xmlhttp.readyState == 4) {
		container.innerHTML=xmlhttp.responseText;

//		var feedtu = document.getElementById("FEEDTU");
//		if (feedtu) {
//			total_unread = feedtu.innerHTML;
//			update_title();
//		}

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

		if (ftotal && factive && funread) {
			var feed_id = factive.innerHTML;

			var feedr = document.getElementById("FEEDR-" + feed_id);
			var feedt = document.getElementById("FEEDT-" + feed_id);
			var feedu = document.getElementById("FEEDU-" + feed_id);

			feedt.innerHTML = ftotal.innerHTML;
			feedu.innerHTML = funread.innerHTML;

			if (feedu.innerHTML > 0 && !feedr.className.match("Unread")) {
					feedr.className = feedr.className + "Unread";
			} else if (feedu.innerHTML <= 0) {	
					feedr.className = feedr.className.replace("Unread", "");
			}

		}

		notify("");

	}	
}

function view_callback() {
	var container = document.getElementById('content');
	if (xmlhttp.readyState == 4) {
		container.innerHTML=xmlhttp.responseText;		
	}
}

function refetch_callback() {
	if (xmlhttp_rpc.readyState == 4) {
		notify("All feeds updated");
		
		var container = document.getElementById('feeds');

		container.innerHTML = xmlhttp_rpc.responseText;

		document.title = "Tiny Tiny RSS";

		//updateFeedList(true, false);
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

	if (xmlhttp_rpc.readyState == 4 || xmlhttp_rpc.readyState == 0) {
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

	if (xmlhttp.readyState == 4 || xmlhttp.readyState == 0) {
		xmlhttp.open("GET", query_str, true);
		xmlhttp.onreadystatechange=feedlist_callback;
		xmlhttp.send(null);
	} else {
		printLockingError();
	}
}

function catchupPage(feed) {

	if (xmlhttp.readyState != 4 && xmlhttp.readyState != 0) {
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

	if (xmlhttp.readyState != 4 && xmlhttp.readyState != 0) {
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

//	document.getElementById('headlines').innerHTML='Loading headlines, please wait...';		
//	document.getElementById('content').innerHTML='&nbsp;';		

	if (xmlhttp.readyState != 4 && xmlhttp.readyState != 0) {
		printLockingError();
		return
	}
	
	active_feed_id = feed;
	active_post_id = false;

	xmlhttp.open("GET", "backend.php?op=viewfeed&feed=" + param_escape(feed) +
		"&skip=" + param_escape(skip) + "&subop=" + param_escape(subop) , true);
	xmlhttp.onreadystatechange=viewfeed_callback;
	xmlhttp.send(null);

	notify("Loading headlines...");

}

function cleanSelectedHeadlines() {
	var content = document.getElementById("headlinesList");

	var rows = new Array();

	for (i = 0; i < content.rows.length; i++) {
		content.rows[i].className = content.rows[i].className.replace("Selected", "");
	}

}

function view(id,feed_id) {

	if (xmlhttp.readyState != 4 && xmlhttp.readyState != 0) {
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

		update_title(); 
	}	

	cleanSelectedHeadlines();

	crow.className = crow.className + "Selected";

	var upd_img_pic = document.getElementById("FUPDPIC-" + id);

	if (upd_img_pic) {
		upd_img_pic.innerHTML = "";
	} 

	document.getElementById('content').innerHTML='Loading, please wait...';		

	active_post_id = id;

	xmlhttp.open("GET", "backend.php?op=view&id=" + param_escape(id), true);
	xmlhttp.onreadystatechange=view_callback;
	xmlhttp.send(null);

}

function timeout() {

	scheduleFeedUpdate(true);

	setTimeout("timeout()", 1800*1000);

}

function search(feed, sender) {

	if (xmlhttp.readyState != 4 && xmlhttp.readyState != 0) {
		printLockingError();
		return
	}

	notify("Search: " + feed + ", " + sender.value)

	document.getElementById('headlines').innerHTML='Loading headlines, please wait...';		
	document.getElementById('content').innerHTML='&nbsp;';		

	xmlhttp.open("GET", "backend.php?op=viewfeed&feed=" + param_escape(feed) +
		"&search=" + param_escape(sender.value) + "&subop=search", true);
	xmlhttp.onreadystatechange=viewfeed_callback;
	xmlhttp.send(null);

}

function update_title() {
	//document.title = "Tiny Tiny RSS (" + total_unread + " unread)";
}

function localPiggieFunction(enable) {
	if (enable) {
		var query_str = "backend.php?op=feeds&subop=piggie";

		if (xmlhttp.readyState == 4 || xmlhttp.readyState == 0) {

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

function moveToPost(mode) {

/*	var query_str = "backend.php?op=rpc&subop=getRelativeId&mode=" + mode + 
		"&feed=" + active_feed_id + "&id=" + active_post_id;

//	notify(query_str);

	if (xmlhttp_rpc.readyState == 4 || xmlhttp_rpc.readyState == 0) {
		xmlhttp_rpc.open("GET", query_str, true);
		xmlhttp_rpc.onreadystatechange=relativeid_callback;
		xmlhttp_rpc.send(null);
	} else {
		printLockingError();
	} */

	var rows = getVisibleHeadlineIds();

	var prev_id;
	var next_id;

	for (var i = 0; i < rows.length; i++) {
		if (rows[i] == active_post_id) {
			prev_id = rows[i-1];
			next_id = rows[i+1];			
		}
	}

	if (mode == "next" && next_id != undefined) {
		view(next_id, active_feed_id);
	}

	if (mode == "prev" && prev_id != undefined) {
		view(prev_id, active_feed_id);
	}

}

function localHotkeyHandler(keycode) {

//	notify(keycode);

	if (keycode == 78) {
		moveToPost('next');
	}

	if (keycode == 80) {
		moveToPost('prev');
	}
}


function init() {
	updateFeedList(false, false);
	document.onkeydown = hotkey_handler;
	setTimeout("timeout()", 1800*1000);
}

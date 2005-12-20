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

function viewfeed(feed, skip, subop, doc) {
	try {

		if (!doc) doc = parent.document;
	
		enableHotkeys();
	
		var searchbox = doc.getElementById("searchbox");
	
		if (searchbox) {
			search_query = searchbox.value;
		} else {
			search_query = "";
		} 
	
		var searchmodebox = doc.getElementById("searchmodebox");
	
		var search_mode;
		
		if (searchmodebox) {
			search_mode = searchmodebox[searchmodebox.selectedIndex].text;
		} else {
			search_mode = "";
		}
	
		setCookie("ttrss_vf_smode", search_mode);
	
		var viewbox = doc.getElementById("viewbox");
	
		var view_mode;
	
		if (viewbox) {
			view_mode = viewbox[viewbox.selectedIndex].text;
		} else {
			view_mode = "All Posts";
		}
	
		setCookie("ttrss_vf_vmode", view_mode);
	
		var limitbox = doc.getElementById("limitbox");
	
		var limit;
	
		if (limitbox) {
			limit = limitbox[limitbox.selectedIndex].text;
			setCookie("ttrss_vf_limit", limit);
		} else {
			limit = "All";
		}
	
	//	document.getElementById("ACTFEEDID").innerHTML = feed;
	
		setActiveFeedId(feed);
	
		if (subop == "MarkAllRead") {
	
			var feedr = document.getElementById("FEEDR-" + feed);
			var feedctr = document.getElementById("FEEDCTR-" + feed);
		
			feedctr.className = "invisible";
	
			if (feedr.className.match("Unread")) {
				feedr.className = feedr.className.replace("Unread", "");
			}
		}
	
		var query = "backend.php?op=viewfeed&feed=" + param_escape(feed) +
			"&skip=" + param_escape(skip) + "&subop=" + param_escape(subop) +
			"&view=" + param_escape(view_mode) + "&limit=" + limit + 
			"&smode=" + param_escape(search_mode);
	
		if (search_query != "") {
			query = query + "&search=" + param_escape(search_query);
		}
		
		var headlines_frame = parent.frames["headlines-frame"];
	
	//	alert(headlines_frame)
	
		headlines_frame.location.href = query + "&addheader=true";
	
		cleanSelectedList("feedList");
	
		var feedr = document.getElementById("FEEDR-" + feed);
		if (feedr && !feedr.className.match("Selected")) {	
			feedr.className = feedr.className + "Selected";
		} 
		
		disableContainerChildren("headlinesToolbar", false, doc);
	
	/*	var btnMarkAsRead = doc.getElementById("btnMarkFeedAsRead");
	
		if (btnMarkAsRead && !isNumeric(feed)) {
			btnMarkAsRead.disabled = true;
			btnMarkAsRead.className = "disabledButton";
		} */
	
	//	notify("");
	} catch (e) {
		exception_error("viewfeed", e);
	}		
}

function localHotkeyHandler(keycode) {

	if (keycode == 65) { // a
		return parent.toggleDispRead();
	}

	if (keycode == 85) { // u
		if (parent.getActiveFeedId()) {
			return viewfeed(parent.getActiveFeedId(), 0, "ForceUpdate");
		}
	}

	if (keycode == 82) { // r
		return parent.scheduleFeedUpdate(true);
	}

	var feedlist = document.getElementById('feedList');

	if (keycode == 74) { // j
		var feed = getActiveFeedId();
		var new_feed = getRelativeFeedId(feedlist, feed, 'prev');
		if (new_feed) viewfeed(new_feed, 0, '');
	}

	if (keycode == 75) { // k
		var feed = getActiveFeedId();
		var new_feed = getRelativeFeedId(feedlist, feed, 'next');
		if (new_feed) viewfeed(new_feed, 0, '');
	}

//	alert("KC: " + keycode);

}

function toggleCollapseCat(cat) {
	try {
		if (!xmlhttp_ready(xmlhttp)) {
			printLockingError();
			return;
		}
	
		var cat_elem = document.getElementById("FCAT-" + cat);
		var cat_list = cat_elem.nextSibling;
		var caption = cat_elem.lastChild;
		
		if (cat_list.className.match("invisible")) {
			cat_list.className = "";
			caption.innerHTML = caption.innerHTML.replace("...", "");
			if (cat == 0) {
				setCookie("ttrss_vf_uclps", "0");
			}
		} else {
			cat_list.className = "invisible";
			caption.innerHTML = caption.innerHTML + "...";
			if (cat == 0) {
				setCookie("ttrss_vf_uclps", "1");
			}
		}

		xmlhttp_rpc.open("GET", "backend.php?op=feeds&subop=collapse&cid=" + 
			param_escape(cat), true);
		xmlhttp_rpc.onreadystatechange=rpc_pnotify_callback;
		xmlhttp_rpc.send(null);

	} catch (e) {
		exception_error("toggleCollapseCat", e);
	}
}

function init() {
	try {
		hideOrShowFeeds(document, getCookie("ttrss_vf_hreadf") == 1);
		document.onkeydown = hotkey_handler;
		parent.setTimeout("timeout()", 1000);
	} catch (e) {
		exception_error("feedlist/init", e);
	}
}

var xmlhttp = false;
var total_unread = 0;
var first_run = true;
var display_tags = false;
var global_unread = -1;
var active_title_text = "";
var current_subtitle = "";

var _qfd_deleted_feed = 0;

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
		notify(xmlhttp.responseText);
		updateFeedList(false, false);
		if (_qfd_deleted_feed) {
			var hframe = document.getElementById("headlines-frame");
			if (hframe) {
				hframe.src = "backend.php?op=error&msg=No%20feed%20selected.";
			}
		}
		closeDlg();
	} 
}

function dlg_submit_callback() {
	if (xmlhttp.readyState == 4) {
		notify(xmlhttp.responseText);
		closeDlg();
	} 
}

function dlg_display_callback() {
	if (xmlhttp.readyState == 4) {
		var dlg = document.getElementById("userDlg");
		var dlg_s = document.getElementById("userDlgShadow");

		dlg.innerHTML = xmlhttp.responseText;
		dlg_s.style.display = "block";
	} 
}

function hide_unread_callback() {
	if (xmlhttp.readyState == 4) {

		try {

			var reply = xmlhttp.responseXML.firstChild.firstChild;
			var value = reply.getAttribute("value");
			var hide_read_feeds = (value != "false")
			var feeds_doc = window.frames["feeds-frame"].document;
	
			hideOrShowFeeds(feeds_doc, hide_read_feeds);
	
			if (hide_read_feeds) {
				setCookie("ttrss_vf_hreadf", 1);
			} else {
				setCookie("ttrss_vf_hreadf", 0);
			} 

		} catch (e) {
			exception_error("hide_unread_callback", e);
		}

	} 
}

function refetch_callback() {
	if (xmlhttp.readyState == 4) {
		try {

			if (!xmlhttp.responseXML) {
				notify("refetch_callback: backend did not return valid XML");
				return;
			}
		
			var reply = xmlhttp.responseXML.firstChild;
	
			if (!reply) {
				notify("refetch_callback: backend did not return expected XML object");
				return;
			} 
	
			var error_code = reply.getAttribute("error-code");
		
			if (error_code && error_code != 0) {
				return fatalError(error_code);
			}
	
			var f_document = window.frames["feeds-frame"].document;

			parse_counters(reply, f_document, window);
	
			updateTitle("");
			notify("All feeds updated.");
		} catch (e) {
			exception_error("refetch_callback", e);
		}
	}
}

function backend_sanity_check_callback() {

	if (xmlhttp.readyState == 4) {

		try {
		
			if (!xmlhttp.responseXML) {
				fatalError(3);
				return;
			}
	
			var reply = xmlhttp.responseXML.firstChild;
	
			if (!reply) {
				fatalError(3);
				return;
			}
	
			var error_code = reply.getAttribute("error-code");
		
			if (error_code && error_code != 0) {
				return fatalError(error_code);
			}
	
			init_second_stage();

		} catch (e) {
			exception_error("backend_sanity_check_callback", e);
		}
	} 
}

function scheduleFeedUpdate(force) {

	notify("Updating feeds, please wait.");

//	document.title = "Tiny Tiny RSS - Updating...";

	updateTitle("Updating");

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
	updateTitle("");

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

	var refresh_time = getCookie('ttrss_vf_refresh');

	if (!refresh_time) refresh_time = 600;

	setTimeout("timeout()", refresh_time*1000);
}

function resetSearch() {
	var searchbox = document.getElementById("searchbox")

	if (searchbox.value != "" && getActiveFeedId()) {	
		searchbox.value = "";
		viewfeed(getActiveFeedId(), 0, "");
	}
}

function search() {
	closeDlg();	
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

	var f_doc = window.frames["feeds-frame"].document;
	var feedlist = f_doc.getElementById('feedList');

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

//	notify("KC: " + keycode);

}

// if argument is undefined, current subtitle is not updated
// use blank string to clear subtitle
function updateTitle(s) {
	var tmp = "Tiny Tiny RSS";

	if (s && s.length > 0) {
		current_subtitle = s;
	}

	if (global_unread > 0) {
		tmp = tmp + " (" + global_unread + ")";
	}

	if (s) {
		tmp = tmp + " - " + current_subtitle;
	}

	if (active_title_text.length > 0) {
		tmp = tmp + " > " + active_title_text;
	}

	document.title = tmp;
}

function genericSanityCheck() {

	if (!xmlhttp) fatalError(1);

	setCookie("ttrss_vf_test", "TEST");
	
	if (getCookie("ttrss_vf_test") != "TEST") {
		fatalError(2);
	}

	return true;
}

function init() {

	try {

		disableContainerChildren("headlinesToolbar", true);

		if (!genericSanityCheck()) 
			return;

		xmlhttp.open("GET", "backend.php?op=rpc&subop=sanityCheck", true);
		xmlhttp.onreadystatechange=backend_sanity_check_callback;
		xmlhttp.send(null);

	} catch (e) {
		exception_error("init", e);
	}
}

function init_second_stage() {

	try {

		setCookie("ttrss_vf_actfeed", "");
	
		updateFeedList(false, false);
		document.onkeydown = hotkey_handler;
	
		var viewbox = document.getElementById("viewbox");			
		var limitbox = document.getElementById("limitbox");

		dropboxSelect(viewbox, getCookie("ttrss_vf_vmode"));
		dropboxSelect(limitbox, getCookie("ttrss_vf_limit"));
	
	} catch (e) {
		exception_error("init_second_stage", e);
	}
}

function quickMenuChange() {
	var chooser = document.getElementById("quickMenuChooser");
	var opid = chooser[chooser.selectedIndex].id;

	chooser.selectedIndex = 0;
	quickMenuGo(opid);
}

function quickMenuGo(opid) {


	if (opid == "qmcPrefs") {
		gotoPreferences();
	}

	if (opid == "qmcSearch") {
		displayDlg("search", getActiveFeedId());
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

		if (confirm("Remove current feed?")) {
			qfdDelete(actid);
		}
	
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

	if (opid == "qmcAddFilter") {
		displayDlg("quickAddFilter", getActiveFeedId());
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
	
		var cat = document.getElementById("qafCat");
		var cat_id = "";
		
		if (cat) {
			cat_id = cat[cat.selectedIndex].id;
		} else {
			cat_id = 0;
		}

		var feeds_doc = window.frames["feeds-frame"].document;

		feeds_doc.location.href = "backend.php?op=error&msg=Loading,%20please wait...";

		xmlhttp.open("GET", "backend.php?op=pref-feeds&quiet=1&subop=add&link=" +
			param_escape(link.value) + "&cid=" + param_escape(cat_id), true);
		xmlhttp.onreadystatechange=dlg_frefresh_callback;
		xmlhttp.send(null);

		link.value = "";

	}
}

function qaddFilter() {

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

		xmlhttp.open("GET", "backend.php?op=pref-filters&quiet=1&subop=add&regexp=" +
			param_escape(regexp.value) + "&match=" + v_match +
			"&fid=" + param_escape(feed_id) + "&aid=" + param_escape(action_id), true);
			
		xmlhttp.onreadystatechange=dlg_submit_callback;
		xmlhttp.send(null);

		regexp.value = "";
	}

}


function displayDlg(id, param) {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	notify("");

	xmlhttp.open("GET", "backend.php?op=dlg&id=" +
		param_escape(id) + "&param=" + param_escape(param), true);
	xmlhttp.onreadystatechange=dlg_display_callback;
	xmlhttp.send(null);

	disableHotkeys();
}

function closeDlg() {
	var dlg = document.getElementById("userDlgShadow");
	dlg.style.display = "none";
	enableHotkeys();
}

function qfdDelete(feed_id) {

	notify("Removing feed...");

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

//	var feeds_doc = window.frames["feeds-frame"].document;
//	feeds_doc.location.href = "backend.php?op=error&msg=Loading,%20please wait...";

	_qfd_deleted_feed = feed_id;

	xmlhttp.open("GET", "backend.php?op=pref-feeds&quiet=1&subop=remove&ids=" + feed_id);
	xmlhttp.onreadystatechange=dlg_frefresh_callback;
	xmlhttp.send(null);
}


function allFeedsMenuChange() {
	var chooser = document.getElementById("allFeedsChooser");

	var opname = chooser[chooser.selectedIndex].text;

	chooser.selectedIndex = 0;

	if (opname == "Update") {
		scheduleFeedUpdate(true);
		return;
	}

	if (opname == "Mark as read") {
		catchupAllFeeds();
		return;
	}

	if (opname == "Show only unread") {
		toggleDispRead();
		return;
	}

}

function updateFeedTitle(t) {
	active_title_text = t;
	updateTitle();
}

function toggleDispRead() {
	try {

		if (!xmlhttp_ready(xmlhttp)) {
			printLockingError();
			return
		}

		var hide_read_feeds = (getCookie("ttrss_vf_hreadf") == 1);

		hide_read_feeds = !hide_read_feeds;
	
		var query = "backend.php?op=rpc&subop=setpref" +
			"&key=HIDE_READ_FEEDS&value=" + param_escape(hide_read_feeds);

		xmlhttp.open("GET", query);
		xmlhttp.onreadystatechange=hide_unread_callback;
		xmlhttp.send(null);
		
	} catch (e) {
		exception_error("toggleDispRead", e);
	}
}


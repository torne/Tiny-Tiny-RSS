var total_unread = 0;
var first_run = true;
var display_tags = false;
var global_unread = -1;
var active_title_text = "";
var current_subtitle = "";
var daemon_enabled = false;
var daemon_refresh_only = false;
//var _qfd_deleted_feed = 0;
var firsttime_update = true;
var cookie_lifetime = 0;
var active_feed_id = 0;
var active_feed_is_cat = false;
var number_of_feeds = 0;
var sanity_check_done = false;

var init_params = new Object();

function tagsAreDisplayed() {
	return display_tags;
}

function toggleTags(show_all) {

	try {

	debug("toggleTags: " + show_all + "; " + display_tags);

	var p = document.getElementById("dispSwitchPrompt");

	if (!show_all && !display_tags) {
		displayDlg("printTagCloud");
	} else if (show_all) {
		closeInfoBox();
		display_tags = true;
		p.innerHTML = __("display feeds");
		notify_progress("Loading, please wait...", true);
		updateFeedList();
	} else if (display_tags) {
		display_tags = false;
		p.innerHTML = __("tag cloud");
		notify_progress("Loading, please wait...", true);
		updateFeedList();
	}

	} catch (e) {
		exception_error("toggleTags", e);
	}
}

function dlg_frefresh_callback(transport, deleted_feed) {
	if (getActiveFeedId() == deleted_feed) {
		var h = document.getElementById("headlines-frame");
		if (h) {
			h.innerHTML = "<div class='whiteBox'>" + __('No feed selected.') + "</div>";
		}
	}

	setTimeout('updateFeedList(false, false)', 50);
	closeInfoBox();
}

function refetch_callback2(transport) {
	try {

		var date = new Date();

		parse_counters_reply(transport, true);

		debug("refetch_callback2: done");

/*		if (!daemon_enabled && !daemon_refresh_only) {
			notify_info("All feeds updated.");
			updateTitle("");
		} else {
			//notify("");
		} */
	} catch (e) {
		exception_error("refetch_callback", e);
		updateTitle("");
	}
}

function backend_sanity_check_callback(transport) {

	try {

		if (sanity_check_done) {
			fatalError(11, "Sanity check request received twice. This can indicate "+
		      "presence of Firebug or some other disrupting extension. "+
				"Please disable it and try again.");
			return;
		}

		if (!transport.responseXML) {
			fatalError(3, "[D001, Received reply is not XML]: " + transport.responseText);
			return;
		}

		var reply = transport.responseXML.firstChild.firstChild;

		if (!reply) {
			fatalError(3, "[D002, Invalid RPC reply]: " + transport.responseText);
			return;
		}

		var error_code = reply.getAttribute("error-code");
	
		if (error_code && error_code != 0) {
			return fatalError(error_code, reply.getAttribute("error-msg"));
		}

		debug("sanity check ok");

		var params = reply.nextSibling;

		if (params) {
			debug('reading init-params...');
			var param = params.firstChild;

			while (param) {
				var k = param.getAttribute("key");
				var v = param.getAttribute("value");
				debug(k + " => " + v);
				init_params[k] = v;					
				param = param.nextSibling;
			}
		}

		sanity_check_done = true;

		init_second_stage();

	} catch (e) {
		exception_error("backend_sanity_check_callback", e);	
	} 
}

function scheduleFeedUpdate(force) {

	debug("in scheduleFeedUpdate");

/*	if (!daemon_enabled && !daemon_refresh_only) {
		notify_progress("Updating feeds...", true);
	} */

	var query_str = "backend.php?op=rpc&subop=";

	if (force) {
		query_str = query_str + "forceUpdateAllFeeds";
	} else {
		query_str = query_str + "updateAllFeeds";
	}

	var omode;

	if (firsttime_update && !navigator.userAgent.match("Opera")) {
		firsttime_update = false;
		omode = "T";
	} else {
		if (display_tags) {
			omode = "tl";
		} else {
			omode = "flc";
		}
	}
	
	query_str = query_str + "&omode=" + omode;
	query_str = query_str + "&uctr=" + global_unread;

	var date = new Date();
	var timestamp = Math.round(date.getTime() / 1000);
	query_str = query_str + "&ts=" + timestamp

	debug("REFETCH query: " + query_str);

	new Ajax.Request(query_str, {
		onComplete: function(transport) { 
				refetch_callback2(transport); 
			} });
}

function updateFeedList(silent, fetch) {

//	if (silent != true) {
//		notify("Loading feed list...");
//	}

	debug("<b>updateFeedList</b>");

	var query_str = "backend.php?op=feeds";

	if (display_tags) {
		query_str = query_str + "&tags=1";
	}

	if (getActiveFeedId() && !activeFeedIsCat()) {
		query_str = query_str + "&actid=" + getActiveFeedId();
	}

	var date = new Date();
	var timestamp = Math.round(date.getTime() / 1000);
	query_str = query_str + "&ts=" + timestamp
	
	if (fetch) query_str = query_str + "&fetch=yes";

//	var feeds_frame = document.getElementById("feeds-frame");
//	feeds_frame.src = query_str;

	debug("updateFeedList Q=" + query_str);

	new Ajax.Request(query_str, {
		onComplete: function(transport) { 
			feedlist_callback2(transport); 
		} });

}

function catchupAllFeeds() {

	var query_str = "backend.php?op=feeds&subop=catchupAll";

	notify_progress("Marking all feeds as read...");

	debug("catchupAllFeeds Q=" + query_str);

	new Ajax.Request(query_str, {
		onComplete: function(transport) { 
			feedlist_callback2(transport); 
		} });

	global_unread = 0;
	updateTitle("");

}

function viewCurrentFeed(subop) {

//	if (getActiveFeedId()) {
	if (getActiveFeedId() != undefined) {
		viewfeed(getActiveFeedId(), subop, active_feed_is_cat);
	} else {
		disableContainerChildren("headlinesToolbar", false, document);
//		viewfeed(-1, subop); // FIXME
	}
	return false; // block unneeded form submits
}

function viewfeed(feed, subop) {
	var f = window.frames["feeds-frame"];
	f.viewfeed(feed, subop);
}

function timeout() {
	scheduleFeedUpdate(false);

	var refresh_time = getInitParam("feeds_frame_refresh");

	if (!refresh_time) refresh_time = 600; 

	setTimeout("timeout()", refresh_time*1000);
}

function resetSearch() {
	var searchbox = document.getElementById("searchbox")

	if (searchbox.value != "" && getActiveFeedId()) {	
		searchbox.value = "";
		viewfeed(getActiveFeedId(), "");
	}
}

function searchCancel() {
	closeInfoBox(true);
}

function search() {
	closeInfoBox();	
	viewCurrentFeed(0, "");
}

// if argument is undefined, current subtitle is not updated
// use blank string to clear subtitle
function updateTitle(s) {
	var tmp = "Tiny Tiny RSS";

	if (s != undefined) {
		current_subtitle = s;
	}

	if (global_unread > 0) {
		tmp = tmp + " (" + global_unread + ")";
	}

	if (current_subtitle) {
		tmp = tmp + " - " + current_subtitle;
	}

	if (active_title_text.length > 0) {
		tmp = tmp + " > " + active_title_text;
	}

	document.title = tmp;
}

function genericSanityCheck() {

//	if (!Ajax.getTransport()) fatalError(1);

	setCookie("ttrss_vf_test", "TEST");
	
	if (getCookie("ttrss_vf_test") != "TEST") {
		fatalError(2);
	}

	return true;
}

function init() {

	try {

		// this whole shebang is based on http://www.birnamdesigns.com/misc/busted2.html

		if (arguments.callee.done) return;
		arguments.callee.done = true;		

		disableContainerChildren("headlinesToolbar", true);

		Form.disable("main_toolbar_form");

		if (!genericSanityCheck()) 
			return;

		if (getURLParam('debug')) {
			document.getElementById('debug_output').style.display = 'block';
			debug('debug mode activated');
		}

		var params = "&ua=" + param_escape(navigator.userAgent);

		new Ajax.Request("backend.php?op=rpc&subop=sanityCheck" + params,	{
			onComplete: function(transport) {
					backend_sanity_check_callback(transport);
				} });

	} catch (e) {
		exception_error("init", e);
	}
}

function resize_headlines() {

	var h_frame = document.getElementById("headlines-frame");
	var c_frame = document.getElementById("content-frame");
	var f_frame = document.getElementById("footer");

	if (!c_frame || !h_frame) return;

	if (getInitParam("theme") == "3pane") {
		debug("resize_headlines: HOR-mode");

		c_frame.style.width = '35%';
		h_frame.style.right = c_frame.offsetWidth - 1 + "px";

	} else {
		debug("resize_headlines: VER-mode");

		if (!is_msie()) {
			h_frame.style.height = 30 + "%";
			c_frame.style.top = h_frame.offsetTop + h_frame.offsetHeight + 1 + "px";
			h_frame.style.height = h_frame.offsetHeight + "px";
		} else {
			h_frame.style.height = document.documentElement.clientHeight * 0.3 + "px";
			c_frame.style.top = h_frame.offsetTop + h_frame.offsetHeight + 1 + "px";
	
			var c_bottom = document.documentElement.clientHeight;
	
			if (f_frame) {
				c_bottom = f_frame.offsetTop;
			}
	
			c_frame.style.height = c_bottom - (h_frame.offsetTop + 
				h_frame.offsetHeight + 1) + "px";
			h_frame.style.height = h_frame.offsetHeight + "px";
	
		}

	}

}

function init_second_stage() {

	try {

		cookie_lifetime = getCookie("ttrss_cltime");

		delCookie("ttrss_vf_test");

//		document.onresize = resize_headlines;
		resize_headlines();

		var toolbar = document.forms["main_toolbar_form"];

		dropboxSelect(toolbar.view_mode, getInitParam("default_view_mode"));
		dropboxSelect(toolbar.limit, getInitParam("default_view_limit"));

		daemon_enabled = getInitParam("daemon_enabled") == 1;
		daemon_refresh_only = getInitParam("daemon_refresh_only") == 1;

		setTimeout('updateFeedList(false, false)', 50);

		debug("second stage ok");
	
	} catch (e) {
		exception_error("init_second_stage", e);
	}
}

function quickMenuChange() {
	var chooser = document.getElementById("quickMenuChooser");
	var opid = chooser[chooser.selectedIndex].value;

	chooser.selectedIndex = 0;
	quickMenuGo(opid);
}

function quickMenuGo(opid) {
	try {

		if (opid == "qmcPrefs") {
			gotoPreferences();
		}
	
		if (opid == "qmcSearch") {
			displayDlg("search", getActiveFeedId() + ":" + activeFeedIsCat());
			return;
		}
	
		if (opid == "qmcAddFeed") {
			displayDlg("quickAddFeed");
			return;
		}

		if (opid == "qmcEditFeed") {
			editFeedDlg(getActiveFeedId());
		}
	
		if (opid == "qmcRemoveFeed") {
			var actid = getActiveFeedId();

			if (activeFeedIsCat()) {
				alert(__("You can't unsubscribe from the category."));
				return;
			}	

			if (!actid) {
				alert(__("Please select some feed first."));
				return;
			}

			var fn = getFeedName(actid);

			var pr = __("Unsubscribe from %s?").replace("%s", fn);

			if (confirm(pr)) {
				unsubscribeFeed(actid);
			}
		
			return;
		}

		if (opid == "qmcClearFeed") {
			var actid = getActiveFeedId();

			if (!actid) {
				alert(__("Please select some feed first."));
				return;
			}

			if (activeFeedIsCat() || actid < 0) {
				alert(__("You can't clear this type of feed."));
				return;
			}	

			var fn = getFeedName(actid);

			var pr = __("Erase all non-starred articles in %s?").replace("%s", fn);

			if (confirm(pr)) {
				clearFeedArticles(actid);
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

	} catch (e) {
		exception_error("quickMenuGo", e);
	}
}

function unsubscribeFeed(feed_id) {

	notify_progress("Removing feed...");

	var query = "backend.php?op=pref-feeds&quiet=1&subop=remove&ids=" + feed_id;

	new Ajax.Request(query,	{
		onComplete: function(transport) {
				dlg_frefresh_callback(transport, feed_id);
			} });


	return false;
}


function updateFeedTitle(t) {
	active_title_text = t;
	updateTitle();
}

function toggleDispRead() {
	try {

		var hide_read_feeds = (getInitParam("hide_read_feeds") == "1");

		hide_read_feeds = !hide_read_feeds;

		debug("toggle_disp_read => " + hide_read_feeds);

		hideOrShowFeeds(getFeedsContext().document, hide_read_feeds);

		storeInitParam("hide_read_feeds", hide_read_feeds, true);
				
	} catch (e) {
		exception_error("toggleDispRead", e);
	}
}

function parse_runtime_info(elem) {
	if (!elem) {
		debug("parse_runtime_info: elem is null, aborting");
		return;
	}

	var param = elem.firstChild;

	debug("parse_runtime_info: " + param);

	while (param) {
		var k = param.getAttribute("key");
		var v = param.getAttribute("value");

		debug("RI: " + k + " => " + v);

		if (k == "new_version_available") {
			var icon = document.getElementById("newVersionIcon");
			if (icon) {
				if (v == "1") {
					icon.style.display = "inline";
				} else {
					icon.style.display = "none";
				}
			}
		}

		var error_flag;

		if (k == "daemon_is_running" && v != 1) {
			notify_error("<span onclick=\"javascript:explainError(1)\">Update daemon is not running.</span>", true);
			error_flag = true;
		}

		if (k == "daemon_stamp_ok" && v != 1) {
			notify_error("<span onclick=\"javascript:explainError(3)\">Update daemon is not updating feeds.</span>", true);
			error_flag = true;
		}

		if (!error_flag) {
			notify('');
		}

/*		var w = document.getElementById("noDaemonWarning");
		
		if (w) {
			if (k == "daemon_is_running" && v != 1) {
				w.style.display = "block";
			} else {
				w.style.display = "none";
			}
		} */
		param = param.nextSibling;
	}
}

function catchupCurrentFeed() {

	var fn = getFeedName(getActiveFeedId(), active_feed_is_cat);
	
	var str = __("Mark all articles in %s as read?").replace("%s", fn);

/*	if (active_feed_is_cat) {
		str = "Mark all articles in this category as read?";
	} */

	if (getInitParam("confirm_feed_catchup") != 1 || confirm(str)) {
		return viewCurrentFeed('MarkAllRead')
	}
}

function editFeedDlg(feed) {
	try {

		disableHotkeys();
	
		if (!feed) {
			alert(__("Please select some feed first."));
			return;
		}
	
		if ((feed <= 0 && feed > -10) || activeFeedIsCat() || tagsAreDisplayed()) {
			alert(__("You can't edit this kind of feed."));
			return;
		}
	
		var query = "";
	
		if (feed > 0) {
			query = "backend.php?op=pref-feeds&subop=editfeed&id=" +	param_escape(feed);
		} else {
			query = "backend.php?op=pref-labels&subop=edit&id=" +	param_escape(-feed-11);
		}
	
		new Ajax.Request(query, {
			onComplete: function(transport) { 
				infobox_callback2(transport); 
			} });

	} catch (e) {
		exception_error("editFeedDlg", e);
	}
}

/* this functions duplicate those of prefs.js feed editor, with
	some differences because there is no feedlist */

function feedEditCancel() {
	closeInfoBox();
	return false;
}

function feedEditSave() {

	try {
	
		// FIXME: add parameter validation

		var query = Form.serialize("edit_feed_form");

		notify_progress("Saving feed...");

		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function(transport) { 
				dlg_frefresh_callback(transport); 
			} });


		closeInfoBox();

		return false;

	} catch (e) {
		exception_error("feedEditSave (main)", e);
	} 
}

function labelEditCancel() {
	closeInfoBox();
	return false;
}

function labelEditSave() {

	try {

		closeInfoBox();
	
		notify_progress("Saving label...");
	
		query = Form.serialize("label_edit_form");
	
		new Ajax.Request("backend.php?" + query, {
			onComplete: function(transport) { 
				dlg_frefresh_callback(transport); 
			} });

		return false;

	} catch (e) {
		exception_error("feedEditSave (main)", e);
	} 

}

function clearFeedArticles(feed_id) {

	notify_progress("Clearing feed...");

	var query = "backend.php?op=pref-feeds&quiet=1&subop=clear&id=" + feed_id;

	new Ajax.Request(query,	{
		onComplete: function(transport) {
				dlg_frefresh_callback(transport, feed_id);
			} });

	return false;
}



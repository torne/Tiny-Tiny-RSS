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
var active_feed_id = 0;
var active_feed_is_cat = false;
var number_of_feeds = 0;
var sanity_check_done = false;
var _hfd_scrolltop = 0;
var hotkey_prefix = false;
var init_params = new Object();
var ver_reflow_delta = 0;

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

	var str = __("Mark all articles as read?");

	if (getInitParam("confirm_feed_catchup") != 1 || confirm(str)) {

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
			Element.show("debug_output");
			debug('debug mode activated');
		}

		var params = "&ua=" + param_escape(navigator.userAgent);

		loading_set_progress(30);

		new Ajax.Request("backend.php?op=rpc&subop=sanityCheck" + params,	{
			onComplete: function(transport) {
					backend_sanity_check_callback(transport);
				} });

	} catch (e) {
		exception_error("init", e);
	}
}

function resize_headlines(delta_x, delta_y) {

	ver_reflow_delta = delta_y;

	if (getInitParam("cookie_lifetime") != 0) {
		setCookie("ttrss_reflow_ver", ver_reflow_delta, 
			getInitParam("cookie_lifetime"));
	} else {
		setCookie("ttrss_reflow_ver", ver_reflow_delta);
	}

	var h_frame = document.getElementById("headlines-frame");
	var c_frame = document.getElementById("content-frame");
	var f_frame = document.getElementById("footer");
	var feeds_frame = document.getElementById("feeds-holder");
	var resize_grab = document.getElementById("resize-grabber");

	if (!c_frame || !h_frame) return;

	if (feeds_frame && getInitParam("theme") == "compat") {
			feeds_frame.style.bottom = f_frame.offsetHeight + "px";		
	}

	if (getInitParam("theme") == "3pane") {
		debug("resize_headlines: HOR-mode");

		c_frame.style.width = '35%';
		h_frame.style.right = c_frame.offsetWidth - 1 + "px";

	} else {
		debug("resize_headlines: VER-mode");

		if (!is_msie()) {
			h_frame.style.height = (300 - ver_reflow_delta) + "px";

			c_frame.style.top = (h_frame.offsetTop + h_frame.offsetHeight + 1) + "px";
			h_frame.style.height = h_frame.offsetHeight + "px";

			resize_grab.style.top = (h_frame.offsetTop + h_frame.offsetHeight - 5) + "px";
			resize_grab.style.display = "block";

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

		loading_set_progress(60);

		ver_reflow_delta = getCookie("ttrss_reflow_ver");

		if (!ver_reflow_delta) ver_reflow_delta = 0;

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

		if (opid == "qmcRescoreFeed") {
			rescoreCurrentFeed();
		}

		if (opid == "qmcHKhelp") {
			//Element.show("hotkey_help_overlay");
			Effect.Appear("hotkey_help_overlay", {duration : 0.3});
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

function catchupFeedInGroup(id, title) {

	var str = __("Mark all articles in %s as read?").replace("%s", title);

	if (getInitParam("confirm_feed_catchup") != 1 || confirm(str)) {
		return viewCurrentFeed('MarkAllReadGR:' + id)
	}
}

function editFeedDlg(feed) {
	try {

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

		disableHotkeys();

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

/*
function toggle_feedlist() {
	try {
		debug("toggle_feedlist");

		var fl = document.getElementById("feeds-holder");

		if (!Element.visible(fl)) {
			Element.show(fl);
			fl.style.zIndex = 30;
			fl.scrollTop = _hfd_scrolltop;
		} else {
			_hfd_scrolltop = fl.scrollTop;
			Element.hide(fl);			
//			Effect.Fade(fl, {duration : 0.2, 
//				queue: { position: 'end', scope: 'FLFADEQ', limit: 1 }});
		}
	} catch (e) {
		exception_error("toggle_feedlist", e);
	}
} */

function collapse_feedlist() {
	try {
		debug("toggle_feedlist");
		
		var theme = getInitParam("theme");
		if (theme != "" && theme != "compact" && theme != "graycube" &&
				theme != "compat") return;

		var fl = document.getElementById("feeds-holder");
		var fh = document.getElementById("headlines-frame");
		var fc = document.getElementById("content-frame");
		var ft = document.getElementById("toolbar");
		var ff = document.getElementById("footer");
		var fhdr = document.getElementById("header");
		var fbtn = document.getElementById("collapse_feeds_btn");

		if (!Element.visible(fl)) {
			Element.show(fl);
			fbtn.value = "<<";

			if (theme != "graycube") {

				fh.style.left = fl.offsetWidth + "px";
				ft.style.left = fl.offsetWidth + "px";
				if (fc) fc.style.left = fl.offsetWidth + "px";
				if (ff && theme != "compat") ff.style.left = (fl.offsetWidth-1) + "px";

				if (theme == "compact") fhdr.style.left = (fl.offsetWidth + 10) + "px";
			} else {
				fh.style.left = fl.offsetWidth + 40 + "px";
				ft.style.left = fl.offsetWidth + 40 +"px";
				if (fc) fc.style.left = fl.offsetWidth + 40 + "px";
			}

			setCookie("ttrss_vf_fclps", "0");

		} else {
			Element.hide(fl);
			fbtn.value = ">>";

			if (theme != "graycube") {

				fh.style.left = "0px";
				ft.style.left = "0px";
				if (fc) fc.style.left = "0px";
				if (ff) ff.style.left = "0px";

				if (theme == "compact") fhdr.style.left = "10px";

			} else {
				fh.style.left = "20px";
				ft.style.left = "20px";
				if (fc) fc.style.left = "20px";

			}

			setCookie("ttrss_vf_fclps", "1");
		}
	} catch (e) {
		exception_error("toggle_feedlist", e);
	}
}

function viewModeChanged() {
	cache_empty();
	return viewCurrentFeed(0, '')
}

function viewLimitChanged() {
	cache_empty();
	return viewCurrentFeed(0, '')
}

function adjustArticleScore(id, score) {
	try {

		var pr = prompt(__("Assign score to article:"), score);

		if (pr != undefined) {
			var query = "backend.php?op=rpc&subop=setScore&id=" + id + "&score=" + pr;

			new Ajax.Request(query,	{
			onComplete: function(transport) {
					viewCurrentFeed();
				} });

		}
	} catch (e) {
		exception_error("adjustArticleScore", e);
	}
}	

function rescoreCurrentFeed() {

	var actid = getActiveFeedId();

	if (activeFeedIsCat() || actid < 0 || tagsAreDisplayed()) {
		alert(__("You can't rescore this kind of feed."));
		return;
	}	

	if (!actid) {
		alert(__("Please select some feed first."));
		return;
	}

	var fn = getFeedName(actid);
	var pr = __("Rescore articles in %s?").replace("%s", fn);

	if (confirm(pr)) {
		notify_progress("Rescoring articles...");

		var query = "backend.php?op=pref-feeds&subop=rescore&quiet=1&ids=" + actid;

		new Ajax.Request(query,	{
		onComplete: function(transport) {
			viewCurrentFeed();
		} });
	}
}

function hotkey_handler(e) {

	try {

		var keycode;
		var shift_key = false;

		var feedlist = document.getElementById('feedList');

		try {
			shift_key = e.shiftKey;
		} catch (e) {

		}
	
		if (window.event) {
			keycode = window.event.keyCode;
		} else if (e) {
			keycode = e.which;
		}

		var keychar = String.fromCharCode(keycode);

		if (keycode == 27) { // escape
			if (Element.visible("hotkey_help_overlay")) {
				Element.hide("hotkey_help_overlay");
			}
			hotkey_prefix = false;
			closeInfoBox();
		} 

		if (!hotkeys_enabled) {
			debug("hotkeys disabled");
			return;
		}

		if (keycode == 16) return; // ignore lone shift

		if ((keycode == 70 || keycode == 67 || keycode == 71) && !hotkey_prefix) {
			hotkey_prefix = keycode;
			debug("KP: PREFIX=" + keycode + " CHAR=" + keychar);
			return;
		}

		if (Element.visible("hotkey_help_overlay")) {
			Element.hide("hotkey_help_overlay");
		}

		/* Global hotkeys */

		if (!hotkey_prefix) {

			if (keycode == 68 && shift_key) { // d
				if (!Element.visible("debug_output")) {
					Element.show("debug_output");
					debug('debug mode activated');
				} else {
					Element.hide("debug_output");
				}
	
				return;
			}
	
			if ((keycode == 191 || keychar == '?') && shift_key) { // ?
				if (!Element.visible("hotkey_help_overlay")) {
					//Element.show("hotkey_help_overlay");
					Effect.Appear("hotkey_help_overlay", {duration : 0.3});
				} else {
					Element.hide("hotkey_help_overlay");
				}
				return false;
			}
	
			if (keycode == 191 || keychar == '/') { // /
				displayDlg("search", getActiveFeedId() + ":" + activeFeedIsCat());
				return false;
			}

			if (keycode == 82 && shift_key) { // R
				scheduleFeedUpdate(true);
				return;
			}

			if (keycode == 82) { // r
				if (getActiveFeedId()) {
					viewfeed(getActiveFeedId(), "ForceUpdate", activeFeedIsCat());
					return;
				}
			}

			if (keycode == 74) { // j
				var feed = getActiveFeedId();
				var new_feed = getRelativeFeedId(feedlist, feed, 'prev');
				if (new_feed) viewfeed(new_feed, '');
				return;
			}
	
			if (keycode == 75) { // k
				var feed = getActiveFeedId();
				var new_feed = getRelativeFeedId(feedlist, feed, 'next');
				if (new_feed) viewfeed(new_feed, '');
				return;
			}

			if (shift_key && (keycode == 78 || keycode == 40)) { // shift - n, down
				catchupRelativeToArticle(1);
				return;
			}

			if (shift_key && (keycode == 80 || keycode == 38)) { // shift - p, up
				catchupRelativeToArticle(0);
				return;
			}

			if (keycode == 78 || keycode == 40) { // n, down
				if (typeof moveToPost != 'undefined') {
					moveToPost('next');
					return;
				}
			}
	
			if (keycode == 80 || keycode == 38) { // p, up
				if (typeof moveToPost != 'undefined') {
					moveToPost('prev');
					return;
				}
			}

			if (keycode == 83 && shift_key) { // S
				var id = getActiveArticleId();
				if (id) {				
					togglePub(id);
				}
				return;
			}

			if (keycode == 83) { // s
				var id = getActiveArticleId();
				if (id) {				
					toggleMark(id);
				}
				return;
			}


			if (keycode == 85) { // u
				var id = getActiveArticleId();
				if (id) {				
					toggleUnread(id);
				}
				return;
			}

			if (keycode == 84 && shift_key) { // T
				var id = getActiveArticleId();
				if (id) {
					editArticleTags(id, getActiveFeedId(), isCdmMode());
					return;
				}
			}

			if (keycode == 9) { // tab
				var id = getArticleUnderPointer();
				if (id) {				
					var cb = document.getElementById("RCHK-" + id);

					if (cb) {
						cb.checked = !cb.checked;
						toggleSelectRowById(cb, "RROW-" + id);
						return false;
					}
				}
			}

			if (keycode == 79) { // o
				if (getActiveArticleId()) {
					openArticleInNewWindow(getActiveArticleId());
					return;
				}
			}

			if (keycode == 81 && shift_key) { // Q
				if (typeof catchupAllFeeds != 'undefined') {
					catchupAllFeeds();
					return;
				}
			}

			if (keycode == 81) { // q
				if (getActiveFeedId()) {
					catchupCurrentFeed();
					return;
				}
			}

			if (keycode == 220 && shift_key) { // shift + |
				if (document.getElementById("subtoolbar_search")) {
					if (Element.visible("subtoolbar_search")) {
						Element.hide("subtoolbar_search");
						Element.show("subtoolbar_ftitle");
						setTimeout("Element.focus('subtoolbar_search_box')", 100);
					} else {
							Element.show("subtoolbar_search");
						Element.hide("subtoolbar_ftitle");
					}
				}
			}
		}

		/* Prefix f */

		if (hotkey_prefix == 70) { // f 

			hotkey_prefix = false;

			if (keycode == 65) { // a
				toggleDispRead();
				return false;
			}

			if (keycode == 85 && shift_key) { // U
				scheduleFeedUpdate(true);
				return false;
			}

			if (keycode == 85) { // u
				if (getActiveFeedId()) {
					viewfeed(getActiveFeedId(), "ForceUpdate");
					return false;
				}
			}

			if (keycode == 69) { // e
				editFeedDlg(getActiveFeedId());
				return false;
			}

			if (keycode == 83) { // s
				displayDlg("quickAddFeed");
				return false;
			}

			if (keycode == 67 && shift_key) { // C
				if (typeof catchupAllFeeds != 'undefined') {
					catchupAllFeeds();
					return false;
				}
			}

			if (keycode == 67) { // c
				if (getActiveFeedId()) {
					catchupCurrentFeed();
					return false;
				}
			}

		}

		/* Prefix c */

		if (hotkey_prefix == 67) { // c
			hotkey_prefix = false;

			if (keycode == 70) { // f
				displayDlg("quickAddFilter", getActiveFeedId());
				return false;
			}

			if (keycode == 83) { // s
				if (typeof collapse_feedlist != 'undefined') {
					collapse_feedlist();
					return false;
				}
			}

		}

		/* Prefix g */

		if (hotkey_prefix == 71) { // g

			hotkey_prefix = false;

			if (keycode == 83) { // s
				viewfeed(-1);
				return false;
			}

			if (keycode == 80 && shift_key) { // P
				gotoPreferences();
				return false;
			}

			if (keycode == 80) { // p
				viewfeed(-2);
				return false;
			}

			if (keycode == 70) { // f
				viewfeed(-3);
				return false;
			}

			if (keycode == 84 && shift_key) { // T
				toggleTags();
				return false;
			}

		}

/*
		if (keycode == 48) { // 0
			return setHotkeyZone(0);
		}

		if (keycode == 49) { // 1
			return setHotkeyZone(1);
		}

		if (keycode == 50) { // 2
			return setHotkeyZone(2);
		}

		if (keycode == 51) { // 3
			return setHotkeyZone(3);
		}

		if (keycode == 82) { // r
			return scheduleFeedUpdate(true);
		}

		if (keycode == 83) { // s
			return displayDlg("search", getActiveFeedId());
		}

		if (keycode == 85) { // u
			if (getActiveFeedId()) {
				return viewfeed(getActiveFeedId(), "ForceUpdate");
			}
		}
	
		if (keycode == 65) { // a
			return toggleDispRead();
		}
	
		var feedlist = document.getElementById('feedList');
	
		if (keycode == 74) { // j
			var feed = getActiveFeedId();
			var new_feed = getRelativeFeedId(feedlist, feed, 'prev');
			if (new_feed) viewfeed(new_feed, '');
		}
	
		if (keycode == 75) { // k
			var feed = getActiveFeedId();
			var new_feed = getRelativeFeedId(feedlist, feed, 'next');
			if (new_feed) viewfeed(new_feed, '');
		}

		if (shift_key && (keycode == 78 || keycode == 40)) { // shift - n, down
			return catchupRelativeToArticle(1);
		}

		if (shift_key && (keycode == 80 || keycode == 38)) { // shift - p, up
			return catchupRelativeToArticle(0);			
		}

		if (keycode == 78 || keycode == 40) { // n, down
			if (typeof moveToPost != 'undefined') {
				return moveToPost('next');
			}
		}
	
		if (keycode == 80 || keycode == 38) { // p, up
			if (typeof moveToPost != 'undefined') {
				return moveToPost('prev');
			}
		}
		
		if (keycode == 68 && shift_key) { // d
			if (!debug_mode_enabled) {
				document.getElementById('debug_output').style.display = 'block';
				debug('debug mode activated');
			} else {
				document.getElementById('debug_output').style.display = 'none';
			}

			debug_mode_enabled = !debug_mode_enabled;
		}

		if (keycode == 191 && shift_key) { // ?
			if (!Element.visible("hotkey_help_overlay")) {
				Element.show("hotkey_help_overlay");
			} else {
				Element.hide("hotkey_help_overlay");
			}
		}

		if (keycode == 69 && shift_key) { // e
			return editFeedDlg(getActiveFeedId());
		}

		if (keycode == 70 && shift_key) { // f
			if (getActiveFeedId()) {
				return catchupCurrentFeed();
			}
		}

		if (keycode == 80 && shift_key) { // p 
			if (getActiveFeedId()) {
				return catchupPage();
			}
		}

		if (keycode == 86) { // v
			if (getActiveArticleId()) {
				openArticleInNewWindow(getActiveArticleId());
			}
		}

		if (keycode == 84) { // t

			var id = getActiveArticleId();

			if (id) {				

				var cb = document.getElementById("RCHK-" + id);

				if (cb) {
					cb.checked = !cb.checked;
					toggleSelectRowById(cb, "RROW-" + id);
				}
			}
		}

		if (keycode == 67) { // c
			var id = getActiveArticleId();

			if (id) {				
				toggleUnread(id, 0);
			}
		}

		if (keycode == 67 && shift_key) { // c
			if (typeof collapse_feedlist != 'undefined') {
				return collapse_feedlist();
			}
		}

		if (keycode == 81 && shift_key) { // shift + q
			if (typeof catchupAllFeeds != 'undefined') {
				return catchupAllFeeds();
			}
		}

		if (keycode == 73 && shift_key) { // shift + i
			if (document.getElementById("subtoolbar_search")) {
				if (Element.visible("subtoolbar_search")) {
					Element.hide("subtoolbar_search");
					Element.show("subtoolbar_ftitle");
					setTimeout("Element.focus('subtoolbar_search_box')", 100);
				} else {
					Element.show("subtoolbar_search");
					Element.hide("subtoolbar_ftitle");
				}
			}
		}

		if (keycode == 27) { // escape
			if (Element.visible("hotkey_help_overlay")) {
				Element.hide("hotkey_help_overlay");
			}
		} 

		if (typeof localHotkeyHandler != 'undefined') {
			try {
				return localHotkeyHandler(e);
			} catch (e) {
				exception_error("hotkey_handler, local:", e);
			}
		} */

		if (hotkey_prefix) {
			debug("KP: PREFIX=" + hotkey_prefix + " CODE=" + keycode + " CHAR=" + keychar);
		} else {
			debug("KP: CODE=" + keycode + " CHAR=" + keychar);
		}


	} catch (e) {
		exception_error("hotkey_handler", e);
	}
}

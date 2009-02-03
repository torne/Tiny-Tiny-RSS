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
var _active_feed_id = 0;
var _active_feed_is_cat = false;
var number_of_feeds = 0;
var sanity_check_done = false;
var _hfd_scrolltop = 0;
var hotkey_prefix = false;
var init_params = new Object();
var ver_offset = 0;
var hor_offset = 0;
var feeds_sort_by_unread = false;
var feedlist_sortable_enabled = false;
var offline_mode = false;
var store = false;
var localServer = false;
var db = false;
var download_progress_last = 0;
var offline_dl_max_id = 0;

function activeFeedIsCat() {
	return _active_feed_is_cat;
}

function getActiveFeedId() {
//	return getCookie("ttrss_vf_actfeed");
	try {
		debug("gAFID: " + _active_feed_id);
		return _active_feed_id;
	} catch (e) {
		exception_error("getActiveFeedId", e);
	}
}

function setActiveFeedId(id, is_cat) {
//	return setCookie("ttrss_vf_actfeed", id);
	try {
		debug("sAFID(" + id + ", " + is_cat + ")");
		_active_feed_id = id;

		if (is_cat != undefined) {
			_active_feed_is_cat = is_cat;
		}

	} catch (e) {
		exception_error("setActiveFeedId", e);
	}
}


function isFeedlistSortable() {
	return feedlist_sortable_enabled;
}

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
			if (!window.google && !google.gears) {
				fatalError(3, "Sanity check: Received reply is not XML", transport.responseText);
			} else {
				init_offline();
			}
			return;
		}

		if (getURLParam("offline")) {
			return init_offline();
		}

		var reply = transport.responseXML.firstChild.firstChild;

		if (!reply) {
			fatalError(3, "Sanity check: invalid RPC reply", transport.responseText);
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
		exception_error("backend_sanity_check_callback", e, transport);	
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
		viewfeed(getActiveFeedId(), subop, activeFeedIsCat());
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
	if (getInitParam("bw_limit") == "1") return;

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

		init_gears();

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

	try {

		debug("resize_headlines: " + delta_x + ":" + delta_y);
	
		var h_frame = document.getElementById("headlines-frame");
		var c_frame = document.getElementById("content-frame");
		var f_frame = document.getElementById("footer");
		var feeds_frame = document.getElementById("feeds-holder");
		var resize_grab = document.getElementById("resize-grabber");
		var resize_handle = document.getElementById("resize-handle");

		if (!c_frame || !h_frame) return;
	
		if (feeds_frame && getInitParam("theme") == "compat") {
				feeds_frame.style.bottom = f_frame.offsetHeight + "px";		
		}
	
		if (getInitParam("theme") == "3pane") {
	
			if (delta_x != undefined) {
				if (c_frame.offsetLeft - delta_x > feeds_frame.offsetWidth + feeds_frame.offsetLeft + 100 && c_frame.offsetWidth + delta_x > 100) {
					hor_offset = hor_offset + delta_x;
				}
			}
	
			debug("resize_headlines: HOR-mode: " + hor_offset);
	
			c_frame.style.width = (400 + hor_offset) + "px";
			h_frame.style.right = c_frame.offsetWidth - 1 + "px";
	
			resize_grab.style.top = (h_frame.offsetTop + h_frame.offsetHeight - 60) + "px";
			resize_grab.style.left = (h_frame.offsetLeft + h_frame.offsetWidth - 
				4) + "px";
			resize_grab.style.display = "block";

			resize_handle.src = "themes/3pane/images/resize_handle_vert.png";
			resize_handle.style.paddingTop = (resize_grab.offsetHeight / 2 - 7) + "px";
	
		} else {
	
			if (delta_y != undefined) {
				if (c_frame.offsetHeight + delta_y > 100 && h_frame.offsetHeight - delta_y > 100) {
					ver_offset = ver_offset + delta_y;
				}
			}
	
			debug("resize_headlines: VER-mode: " + ver_offset);
	
			h_frame.style.height = (300 - ver_offset) + "px";
	
			c_frame.style.top = (h_frame.offsetTop + h_frame.offsetHeight + 0) + "px";
			h_frame.style.height = h_frame.offsetHeight + "px";
	
			var theme_c = 0;
	
			if (getInitParam("theme") == "graycube") {
				theme_c = 1;
			}

			if (getInitParam("theme") == "graycube" || getInitParam("theme") == "compat") {
				resize_handle.src = "themes/graycube/images/resize_handle_horiz.png";
			}
	
/*			resize_grab.style.top = (h_frame.offsetTop + h_frame.offsetHeight - 
				4 - theme_c) + "px";
			resize_grab.style.display = "block"; */
	
		}
	
		if (getInitParam("cookie_lifetime") != 0) {
			setCookie("ttrss_offset_ver", ver_offset, 
				getInitParam("cookie_lifetime"));
			setCookie("ttrss_offset_hor", hor_offset, 
				getInitParam("cookie_lifetime"));
		} else {
			setCookie("ttrss_offset_ver", ver_offset);
			setCookie("ttrss_offset_hor", hor_offset);
		}

	} catch (e) {
		exception_error("resize_headlines", e);
	}

}

function init_second_stage() {

	try {

		delCookie("ttrss_vf_test");

//		document.onresize = resize_headlines;

		var toolbar = document.forms["main_toolbar_form"];

		dropboxSelect(toolbar.view_mode, getInitParam("default_view_mode"));
		dropboxSelect(toolbar.limit, getInitParam("default_view_limit"));
		dropboxSelect(toolbar.order_by, getInitParam("default_view_order_by"));

		daemon_enabled = getInitParam("daemon_enabled") == 1;
		daemon_refresh_only = getInitParam("daemon_refresh_only") == 1;
		feeds_sort_by_unread = getInitParam("feeds_sort_by_unread") == 1;

		var fl = cache_find_param("FEEDLIST", getInitParam("num_feeds"));

		if (fl) {
			render_feedlist(fl);
			if (document.getElementById("feedList")) {
				request_counters();
			} else {
				setTimeout('updateFeedList(false, false)', 50);
			}
		} else {
			setTimeout('updateFeedList(false, false)', 50);
		}

		debug("second stage ok");

		loading_set_progress(60);

		ver_offset = parseInt(getCookie("ttrss_offset_ver"));
		hor_offset = parseInt(getCookie("ttrss_offset_hor"));

		debug("got offsets from cookies: ver " + ver_offset + " hor " + hor_offset);

		/* fuck IE */

		if (isNaN(hor_offset)) hor_offset = 0;
		if (isNaN(ver_offset)) ver_offset = 0;

		debug("offsets from cookies [x:y]: " + hor_offset + ":" + ver_offset);

		resize_headlines();

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

		if (opid == "qmcAddLabel") {
			addLabel();
		}

		if (opid == "qmcRescoreFeed") {
			rescoreCurrentFeed();
		}

		if (opid == "qmcHKhelp") {
			//Element.show("hotkey_help_overlay");
			Effect.Appear("hotkey_help_overlay", {duration : 0.3});
		}

		if (opid == "qmcResetUI") {
			hor_offset = 0;
			ver_offset = 0;
			resize_headlines();
		}

		if (opid == "qmcDownload") {
			displayDlg("offlineDownload");
			return;
		}

		if (opid == "qmcResetCats") {

			if (confirm(__("Reset category order?"))) {

				var query = "backend.php?op=feeds&subop=catsortreset";

				notify_progress("Loading, please wait...", true);

				new Ajax.Request(query, {
					onComplete: function(transport) { 
						window.setTimeout('updateFeedList(false, false)', 50);
					} });
			}
		}

	} catch (e) {
		exception_error("quickMenuGo", e);
	}
}

function unsubscribeFeed(feed_id, title) {


	var msg = __("Unsubscribe from %s?").replace("%s", title);

	if (title == undefined || confirm(msg)) {
		notify_progress("Removing feed...");

		var query = "backend.php?op=pref-feeds&quiet=1&subop=remove&ids=" + feed_id;

		new Ajax.Request(query,	{
			onComplete: function(transport) {
					dlg_frefresh_callback(transport, feed_id);
				} });
	}

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

		hideOrShowFeeds(hide_read_feeds);

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

		if (k == "num_feeds") {
			init_params[k] = v;					
		}

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

	var fn = getFeedName(getActiveFeedId(), activeFeedIsCat());
	
	var str = __("Mark all articles in %s as read?").replace("%s", fn);

	if (getInitParam("confirm_feed_catchup") != 1 || confirm(str)) {
		return viewCurrentFeed('MarkAllRead')
	}
}

function catchupFeedInGroup(id) {

	try {

		var title = getFeedName(id);

		var str = __("Mark all articles in %s as read?").replace("%s", title);

		if (getInitParam("confirm_feed_catchup") != 1 || confirm(str)) {
			return viewCurrentFeed('MarkAllReadGR:' + id)
		}

	} catch (e) {
		exception_error("catchupFeedInGroup", e);
	}
}

function editFeedDlg(feed) {
	try {

		if (!feed) {
			alert(__("Please select some feed first."));
			return;
		}
	
		if ((feed <= 0) || activeFeedIsCat() || tagsAreDisplayed()) {
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

function clearFeedArticles(feed_id) {

	notify_progress("Clearing feed...");

	var query = "backend.php?op=pref-feeds&quiet=1&subop=clear&id=" + feed_id;

	new Ajax.Request(query,	{
		onComplete: function(transport) {
				dlg_frefresh_callback(transport, feed_id);
			} });

	return false;
}

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

/* function adjustArticleScore(id, score) {
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
} */

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

		if ((keycode == 70 || keycode == 67 || keycode == 71) 
				&& !hotkey_prefix) {

			hotkey_prefix = keycode;
			debug("KP: PREFIX=" + keycode + " CHAR=" + keychar);
			return true;
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

			if (keycode == 74) { // j
				var feed = getActiveFeedId();
				var new_feed = getRelativeFeedId2(feed, activeFeedIsCat(), 'prev');
//				alert(feed + " IC: " + activeFeedIsCat() + " => " + new_feed);
				if (new_feed) {
					var is_cat = new_feed.match("CAT:");
					if (is_cat) {
						new_feed = new_feed.replace("CAT:", "");
						viewCategory(new_feed);
					} else {
						viewfeed(new_feed, '', false);
					}
				}
				return;
			}
	
			if (keycode == 75) { // k
				var feed = getActiveFeedId();
				var new_feed = getRelativeFeedId2(feed, activeFeedIsCat(), 'next');
//				alert(feed + " IC: " + activeFeedIsCat() + " => " + new_feed);
				if (new_feed) {
					var is_cat = new_feed.match("CAT:");
					if (is_cat == "CAT:") {
						new_feed = new_feed.replace("CAT:", "");
						viewCategory(new_feed);
					} else {
						viewfeed(new_feed, '', false);
					}
				}
				return;
			}

			if (shift_key && keycode == 40) { // shift-down
				catchupRelativeToArticle(1);
				return;
			}

			if (shift_key && keycode == 38) { // shift-up
				catchupRelativeToArticle(0);
				return;
			}

			if (shift_key && keycode == 78) { // N
				scrollArticle(50);	
				return;
			}

			if (shift_key && keycode == 80) { // P
				scrollArticle(-50);	
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

			if (keycode == 88) { // x
				if (activeFeedIsCat()) {
					toggleCollapseCat(getActiveFeedId());
				}
			}
		}

		/* Prefix f */

		if (hotkey_prefix == 70) { // f 

			hotkey_prefix = false;

			if (keycode == 81) { // q
				if (getActiveFeedId()) {
					catchupCurrentFeed();
					return;
				}
			}

			if (keycode == 82) { // r
				if (getActiveFeedId()) {
					viewfeed(getActiveFeedId(), "ForceUpdate", activeFeedIsCat());
					return;
				}
			}

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

			if (keycode == 68 && shift_key) { // D
				initiate_offline_download();
				return false;
			}

			if (keycode == 68) { // d
				displayDlg("offlineDownload");
				return false;
			}

			if (keycode == 87) { // w
				feeds_sort_by_unread = !feeds_sort_by_unread;
				return resort_feedlist();
			}

			if (keycode == 72) { // h
				hideReadHeadlines();
				return;
			}

		}

		/* Prefix c */

		if (hotkey_prefix == 67) { // c
			hotkey_prefix = false;

			if (keycode == 70) { // f
				displayDlg("quickAddFilter", getActiveFeedId());
				return false;
			}

			if (keycode == 76) { // l
				addLabel();
				return false;
			}

			if (keycode == 83) { // s
				if (typeof collapse_feedlist != 'undefined') {
					collapse_feedlist();
					return false;
				}
			}

			if (keycode == 77) { // m
				feedlist_sortable_enabled = !feedlist_sortable_enabled;
				if (feedlist_sortable_enabled) {
					notify_info("Category reordering enabled");
					toggle_sortable_feedlist(true);
				} else {
					notify_info("Category reordering disabled");
					toggle_sortable_feedlist(false);
				}
			}

			if (keycode == 78) { // n
				catchupRelativeToArticle(1);
				return;
			}

			if (keycode == 80) { // p
				catchupRelativeToArticle(0);
				return;
			}


		}

		/* Prefix g */

		if (hotkey_prefix == 71) { // g

			hotkey_prefix = false;


			if (keycode == 65) { // a
				viewfeed(-4);
				return false;
			}

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

		/* Cmd */

		if (hotkey_prefix == 224 || hotkey_prefix == 91) { // f 
			hotkey_prefix = false;
			return;
		}

		if (hotkey_prefix) {
			debug("KP: PREFIX=" + hotkey_prefix + " CODE=" + keycode + " CHAR=" + keychar);
		} else {
			debug("KP: CODE=" + keycode + " CHAR=" + keychar);
		}


	} catch (e) {
		exception_error("hotkey_handler", e);
	}
}

function feedsSortByUnread() {
	return feeds_sort_by_unread;
}

function addLabel() {

	try {

		var caption = prompt(__("Please enter label caption:"), "");

		if (caption != undefined) {
	
			if (caption == "") {
				alert(__("Can't create label: missing caption."));
				return false;
			}

			var query = "backend.php?op=pref-labels&subop=add&caption=" + 
				param_escape(caption);

			notify_progress("Loading, please wait...", true);

			new Ajax.Request(query, {
				onComplete: function(transport) { 
					updateFeedList();
			} });

		}

	} catch (e) {
		exception_error("addLabel", e);
	}
}

function visitOfficialSite() {
	window.open("http://tt-rss.org/");
}


function feedBrowserSubscribe() {
	try {

		var selected = getSelectedFeedsFromBrowser();

		if (selected.length > 0) {
			closeInfoBox();

			notify_progress("Loading, please wait...", true);

			var query =  "backend.php?op=pref-feeds&subop=massSubscribe&ids="+
				param_escape(selected.toString());

			new Ajax.Request(query, {
				onComplete: function(transport) { 
					updateFeedList();
				} });

		} else {
			alert(__("No feeds are selected."));
		}

	} catch (e) {
		exception_error("feedBrowserSubscribe", e);
	}
}

function init_gears() {
	try {

		if (window.google && google.gears) {
			localServer = google.gears.factory.create("beta.localserver");
			store = localServer.createManagedStore("tt-rss");
			db = google.gears.factory.create('beta.database');
			db.open('tt-rss');

			db.execute("CREATE TABLE IF NOT EXISTS cache (id text, article text, param text, added text)");

			db.execute("CREATE TABLE if not exists feeds (id integer, title text, has_icon integer)");

			db.execute("CREATE TABLE if not exists articles (id integer, feed_id integer, title text, link text, guid text, updated text, content text, tags text, unread text, marked text)");

			var qmcDownload = document.getElementById("qmcDownload");
			if (qmcDownload) Element.show(qmcDownload);

		}	
	
		cache_expire();

	} catch (e) {
		exception_error("init_gears", e);
	}
}

function init_offline() {
	try {
		offline_mode = true;

		render_offline_feedlist();

		remove_splash();
	} catch (e) {
		exception_error("init_offline", e);
	}
}

function offline_download_parse(stage, transport) {
	try {
		if (transport.responseXML) {

			if (stage == 0) {

				var feeds = transport.responseXML.getElementsByTagName("feed");

				if (feeds.length > 0) {
					db.execute("DELETE FROM feeds");
				}

				for (var i = 0; i < feeds.length; i++) {
					var id = feeds[i].getAttribute("id");
					var has_icon = feeds[i].getAttribute("has_icon");
					var title = feeds[i].firstChild.nodeValue;
	
					db.execute("INSERT INTO feeds (id,title,has_icon)"+
						"VALUES (?,?,?)",
						[id, title, has_icon]);
				}
		
				window.setTimeout("initiate_offline_download("+(stage+1)+")", 50);
			} else {

				var articles = transport.responseXML.getElementsByTagName("article");

				var articles_found = 0;

				for (var i = 0; i < articles.length; i++) {					
					var a = eval("("+articles[i].firstChild.nodeValue+")");
					articles_found++;
					if (a) {
						db.execute("DELETE FROM articles WHERE id = ?", [a.id]);
						db.execute("INSERT INTO articles "+
						"(id, feed_id, title, link, guid, updated, content, "+
							"unread, marked, tags) "+
						"VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", 
							[a.id, a.feed_id, a.title, a.link, a.guid, a.updated, 
								a.content, a.unread, a.marked, a.tags]);

					}
				}

				if (articles_found > 0) {
					window.setTimeout("initiate_offline_download("+(stage+1)+")", 50);
				} else {
					notify_info("All done.");
					closeInfoBox();
				}
			}

		}
	} catch (e) {
		exception_error("offline_download_parse", e);
	}
}

function initiate_offline_download(stage, caller) {
	try {

		if (!stage) stage = 0;
		if (caller) caller.disabled = true;

		notify_progress("Loading, please wait... (" + stage +")", true);

		var query = "backend.php?op=rpc&subop=download&stage=" + stage;

		if (stage == 0) {
			var rs = db.execute("SELECT MAX(id) FROM articles");
			if (rs.isValidRow() && rs.field(0)) {
				offline_dl_max_id = rs.field(0);
			}
		}

		if (offline_dl_max_id) {
			query = query + "&cid=" + offline_dl_max_id;
		}

		if (document.getElementById("download_ops_form")) {
			query = query + "&" + Form.serialize("download_ops_form");
		}

		new Ajax.Request(query, {
			onComplete: function(transport) { 
				offline_download_parse(stage, transport);				
			} });

	} catch (e) {
		exception_error("initiate_offline_download", e);
	}
}

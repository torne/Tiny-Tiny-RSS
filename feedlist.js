var _feed_cur_page = 0;
var _infscroll_disable = 0;
var _infscroll_request_sent = 0;
var feed_under_pointer = undefined;

var mouse_is_down = false;
var mouse_y = 0;
var mouse_x = 0;

var resize_enabled = false;
var counters_last_request = 0;

function toggle_sortable_feedlist(enabled) {
	try {

		if (enabled) {
			Sortable.create('feedList', {onChange: feedlist_dragsorted, only: "feedCat"});
		} else {
			Sortable.destroy('feedList');
		}

	} catch (e) {
		exception_error("toggle_sortable_feedlist", e);
	}
}

function viewCategory(cat) {
	active_feed_is_cat = true;
	viewfeed(cat, '', true);
	return false;
}

function feedlist_callback2(transport) {
	try {
		debug("feedlist_callback2");
		var f = document.getElementById("feeds-frame");
		f.innerHTML = transport.responseText;
		feedlist_init();
	} catch (e) {
		exception_error("feedlist_callback2", e);
	}
}

function viewNextFeedPage() {
	try {
		//if (!getActiveFeedId()) return;

		debug("viewNextFeedPage: calling viewfeed(), p: " + _feed_cur_page+1);

		viewfeed(getActiveFeedId(), undefined, activeFeedIsCat(), undefined,
			undefined, _feed_cur_page+1);

	} catch (e) {
		exception_error("viewNextFeedPage", e);
	}
}

function viewfeed(feed, subop, is_cat, subop_param, skip_history, offset) {
	try {

//		if (!offset) page_offset = 0;

		last_requested_article = 0;
		counters_last_request = 0;

		if (feed == getActiveFeedId()) {
			cache_invalidate("F:" + feed);
		}

/*		if (getInitParam("theme") == "" || getInitParam("theme") == "compact") {
			if (getInitParam("hide_feedlist") == 1) {
				Element.hide("feeds-holder");
			}		
		} */

		var force_nocache = false;

		var page_offset = 0;

		if (offset > 0) {
			page_offset = offset;
		} else {
			page_offset = 0;
			_feed_cur_page = 0;
			_infscroll_disable = 0;
		}

		if (getActiveFeedId() != feed) {
			_feed_cur_page = 0;
			active_post_id = 0;
			_infscroll_disable = 0;
		}

		if (page_offset != 0 && !subop) {
			var date = new Date();
			var timestamp = Math.round(date.getTime() / 1000);

			debug("<b>" + _infscroll_request_sent + " : " + timestamp + "</b>");

			if (_infscroll_request_sent && _infscroll_request_sent + 30 > timestamp) {
				debug("infscroll request in progress, aborting");
				return;
			}

			_infscroll_request_sent = timestamp;			
		}

		enableHotkeys();

		closeInfoBox();

		Form.enable("main_toolbar_form");

		var toolbar_form = document.forms["main_toolbar_form"];
		var toolbar_query = Form.serialize("main_toolbar_form");

		if (toolbar_form.query) {
			if (toolbar_form.query.value != "") {
				force_nocache = true;
			}
			toolbar_form.query.value = "";
		}

		var query = "backend.php?op=viewfeed&feed=" + feed + "&" +
			toolbar_query + "&subop=" + param_escape(subop);

		if (document.getElementById("search_form")) {
			var search_query = Form.serialize("search_form");
			query = query + "&" + search_query;
			closeInfoBox(true);
			force_nocache = true;
		}

//		debug("IS_CAT_STORED: " + activeFeedIsCat() + ", IS_CAT: " + is_cat);

		if (subop == "MarkAllRead") {

			var feedlist = document.getElementById('feedList');
			
			var next_unread_feed = getRelativeFeedId(feedlist,
					feed, "next", true);

			if (!next_unread_feed) {
				next_unread_feed = getRelativeFeedId(feedlist,
					-3, "next", true);
			}

			var show_next_feed = getInitParam("on_catchup_show_next_feed") == "1";

			if (next_unread_feed && show_next_feed && !activeFeedIsCat()) {
				query = query + "&nuf=" + param_escape(next_unread_feed);
				//setActiveFeedId(next_unread_feed);
				feed = next_unread_feed;
			}
		}

		if (is_cat) {
			query = query + "&cat=1";
		}

		if (page_offset != 0) {
			query = query + "&skip=" + page_offset;

			// to prevent duplicate feed titles when showing grouped vfeeds
			if (vgroup_last_feed) {
				query = query + "&vgrlf=" + param_escape(vgroup_last_feed);
			}
		}

		var date = new Date();
		var timestamp = Math.round(date.getTime() / 1000);
		query = query + "&ts=" + timestamp
		
		disableContainerChildren("headlinesToolbar", false);
		Form.enable("main_toolbar_form");

		// for piggybacked counters

		if (tagsAreDisplayed()) {
			query = query + "&omode=lt";
		} else {
			query = query + "&omode=flc";
		}

		if (!async_counters_work) {
			query = query + "&csync=true";
		}

		debug(query);

		var container = document.getElementById("headlinesInnerContainer");

/*		if (container && page_offset == 0 && !isCdmMode()) {
			new Effect.Fade(container, {duration: 1, to: 0.01,
				queue: { position:'end', scope: 'FEEDL-' + feed, limit: 1 } } );
		} */

		var unread_ctr = get_feed_unread(feed);
		var cache_check = false;

		if (unread_ctr != -1 && !page_offset && !force_nocache && !subop) {

			var cache_prefix = "";
				
			if (is_cat) {
				cache_prefix = "C:";
			} else {
				cache_prefix = "F:";
			}

			cache_check = cache_check_param(cache_prefix + feed, unread_ctr);
			debug("headline cache check: " + cache_check);
		}

		if (cache_check) {
			var f = document.getElementById("headlines-frame");

			clean_feed_selections();

			setActiveFeedId(feed);
		
			if (is_cat != undefined) {
				active_feed_is_cat = is_cat;
			}
	
			if (!is_cat) {
				var feedr = document.getElementById("FEEDR-" + feed);
				if (feedr && !feedr.className.match("Selected")) {	
					feedr.className = feedr.className + "Selected";
				} 
			}

			f.innerHTML = cache_find_param(cache_prefix + feed, unread_ctr);

			request_counters();

		} else {

			if (!page_offset) {
				notify_progress("Loading, please wait...", true);
			}

			new Ajax.Request(query, {
				onComplete: function(transport) { 
					headlines_callback2(transport, feed, is_cat, page_offset); 
				} });
		}

	} catch (e) {
		exception_error("viewfeed", e);
	}		
}

function toggleCollapseCat_af(effect) {
	//var caption = elem.id.replace("FCATLIST-", "");

	try {

		var elem = effect.element;
		var cat = elem.id.replace("FCATLIST-", "");
		var cap = document.getElementById("FCAP-" + cat);

		if (Element.visible(elem)) {
			cap.innerHTML = cap.innerHTML.replace("…", "");
		} else {
			if (cap.innerHTML.lastIndexOf("…") != cap.innerHTML.length-3) {
				cap.innerHTML = cap.innerHTML + "…";
			}
		}

	} catch (e) {
		exception_error("toggleCollapseCat_af", e);
	}
}

function toggleCollapseCat(cat) {
	try {
	
		var cat_elem = document.getElementById("FCAT-" + cat);
		var cat_list = document.getElementById("FCATLIST-" + cat).parentNode;
		var caption = document.getElementById("FCAP-" + cat);
		
/*		if (cat_list.className.match("invisible")) {
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

		} */

		if (cat == 0) {
			if (Element.visible("FCATLIST-" + cat)) {
				setCookie("ttrss_vf_uclps", "1");
			} else {
				setCookie("ttrss_vf_uclps", "0");
			}
		} 

		if (cat == -2) {
			if (Element.visible("FCATLIST-" + cat)) {
				setCookie("ttrss_vf_lclps", "1");
			} else {
				setCookie("ttrss_vf_lclps", "0");
			}
		} 

		if (cat == -1) {
			if (Element.visible("FCATLIST-" + cat)) {
				setCookie("ttrss_vf_vclps", "1");
			} else {
				setCookie("ttrss_vf_vclps", "0");
			}
		} 

		Effect.toggle('FCATLIST-' + cat, 'blind', { duration: 0.5,
			afterFinish: toggleCollapseCat_af });

		new Ajax.Request("backend.php?op=feeds&subop=collapse&cid=" + 
			param_escape(cat));

	} catch (e) {
		exception_error("toggleCollapseCat", e);
	}
}

function feedlist_dragsorted(ctr) {
	try {
		var elem = document.getElementById("feedList");

		var cats = elem.getElementsByTagName("LI");
		var ordered_cats = new Array();

		for (var i = 0; i < cats.length; i++) {
			if (cats[i].id && cats[i].id.match("FCAT-")) {
				ordered_cats.push(cats[i].id.replace("FCAT-", ""));
			}
		}

		if (ordered_cats.length > 0) {

			var query = "backend.php?op=feeds&subop=catsort&corder=" + 
				param_escape(ordered_cats.toString());

			debug(query);

			new Ajax.Request(query);
		}

	} catch (e) {
		exception_error("feedlist_init", e);
	}
}

function feedlist_init() {
	try {
//		if (arguments.callee.done) return;
//		arguments.callee.done = true;		
		
		loading_set_progress(90);

		debug("in feedlist init");
		
		hideOrShowFeeds(getInitParam("hide_read_feeds") == 1);
		document.onkeydown = hotkey_handler;
		document.onmousemove = mouse_move_handler;
		document.onmousedown = mouse_down_handler;
		document.onmouseup = mouse_up_handler;
		setTimeout("timeout()", 0);

		if (typeof correctPNG != 'undefined') {
			correctPNG();
		}

		if (getActiveFeedId()) {
			//debug("some feed is open on feedlist refresh, reloading");
			//setTimeout("viewCurrentFeed()", 100);
		} else {
			if (getInitParam("cdm_auto_catchup") != 1 && get_feed_unread(-3) > 0) {
				notify_silent_next();
				setTimeout("viewfeed(-3)", 100);
			} else {
				remove_splash();
			}
		}

		if (getInitParam("theme") == "") {
			setTimeout("hide_footer()", 5000);
		}

		init_collapsable_feedlist(getInitParam("theme"));

		toggle_sortable_feedlist(isFeedlistSortable());

	} catch (e) {
		exception_error("feedlist/init", e);
	}
}

function hide_footer_af(effect) {
	try {
		var c = document.getElementById("content-frame");

		if (c) {
			c.style.bottom = "0px";
		} else {
			var h = document.getElementById("headlines-frame");

			if (h) {
				h.style.bottom = "0px";
			}
		}

	} catch (e) {
		exception_error("hide_footer_af", e);
	}
}

function hide_footer() {
	try {
		if (Element.visible("footer")) {
			new Effect.Fade("footer", { afterFinish: hide_footer_af });
		}
	} catch (e) {
		exception_error("hide_footer", e);
	}
}

/*
function init_hidden_feedlist(theme) {
	try {
		debug("init_hidden_feedlist");

		if (theme != "" && theme != "compact") return;

		var fl = document.getElementById("feeds-holder");
		var fh = document.getElementById("headlines-frame");
		var fc = document.getElementById("content-frame");
		var ft = document.getElementById("toolbar");
		var ff = document.getElementById("footer");
		var fhdr = document.getElementById("header");

		var fbtn = document.getElementById("toggle_feeds_btn");

		if (fbtn) Element.show(fbtn);

		fl.style.top = fh.offsetTop + "px";
		fl.style.backgroundColor = "white"; //FIXME

		Element.hide(fl);
		
		fh.style.left = "0px";
		ft.style.left = "0px";
		if (fc) fc.style.left = "0px";
		if (ff) ff.style.left = "0px";

		if (theme == "compact") {
			fhdr.style.left = "10px";
			fl.style.top = (fh.offsetTop + 1) + "px";
		}

	} catch (e) {
		exception_error("init_hidden_feedlist", e);
	}
} */

function init_collapsable_feedlist(theme) {
	try {
		debug("init_collapsable_feedlist");

		if (theme != "" && theme != "compact" && theme != "graycube" &&
				theme != "compat") return;

		var fbtn = document.getElementById("collapse_feeds_btn");

		if (fbtn) Element.show(fbtn);

		if (getCookie("ttrss_vf_fclps") == 1) {
			collapse_feedlist();
		}

	} catch (e) {
		exception_error("init_hidden_feedlist", e);
	}

}

function mouse_move_handler(e) {
	try {
		var client_y;
		var client_x;

		if (window.event) {
			client_y = window.event.clientY;
			client_x = window.event.clientX;
		} else if (e) {
			client_x = e.screenX;
			client_y = e.screenY;
		}

		if (mouse_is_down) {

			if (mouse_y == 0) mouse_y = client_y;
			if (mouse_x == 0) mouse_x = client_x;

			resize_headlines(mouse_x - client_x, mouse_y - client_y);

			mouse_y = client_y;
			mouse_x = client_x;

			return false;
		}

	} catch (e) {
		exception_error("mouse_move_handler", e);
	}
}

function enable_resize(b) {
	resize_enabled = b;
}

function mouse_down_handler(e) {
	try {
		if (resize_enabled) { 
			mouse_is_down = true;
			mouse_x = 0;
			mouse_y = 0;
			document.onselectstart = function() { return false; };
			return false;
		}
	} catch (e) {
		exception_error("mouse_move_handler", e);
	}
}

function mouse_up_handler(e) {
	try {
		mouse_is_down = false;
		document.onselectstart = null;
	} catch (e) {
		exception_error("mouse_move_handler", e);
	}
}

function request_counters_real() {

	try {

		debug("requesting counters...");

		var query = "backend.php?op=rpc&subop=getAllCounters";

		if (tagsAreDisplayed()) {
			query = query + "&omode=tl";
		} else {
			query = query + "&omode=flc";
		}

		new Ajax.Request(query, {
			onComplete: function(transport) { 
				try {
					all_counters_callback2(transport, true);
				} catch (e) {
					exception_error("viewfeed/getcounters", e);
				}
			} });

	} catch (e) {
		exception_error("request_counters_real", e);
	}
}


function request_counters() {

	try {

		if (getInitParam("bw_limit") == "1") return;

		var date = new Date();
		var timestamp = Math.round(date.getTime() / 1000);

//		if (getInitParam("sync_counters") == "1" || 
//				timestamp - counters_last_request > 10) {

		if (timestamp - counters_last_request > 10) {

			debug("scheduling request of counters...");
			window.setTimeout("request_counters_real()", 1000);
			counters_last_request = timestamp;
		} else {
			debug("request_counters: rate limit reached: " + (timestamp - counters_last_request));
		}

	} catch (e) {
		exception_error("request_counters", e);
	}
}

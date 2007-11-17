var _feed_cur_page = 0;
var _infscroll_disable = 0;
var _infscroll_request_sent = 0;

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
		if (!getActiveFeedId()) return;

		viewfeed(getActiveFeedId(), undefined, undefined, undefined,
			undefined, _feed_cur_page+1);

	} catch (e) {
		exception_error(e, "viewFeedGoPage");
	}
}

function viewfeed(feed, subop, is_cat, subop_param, skip_history, offset) {
	try {

//		if (!offset) page_offset = 0;

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
			toolbar_form.query.value = "";
		}

		var query = "backend.php?op=viewfeed&feed=" + feed + "&" +
			toolbar_query + "&subop=" + param_escape(subop);

		if (document.getElementById("search_form")) {
			var search_query = Form.serialize("search_form");
			query = query + "&" + search_query;
			closeInfoBox(true);
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
		}

		var date = new Date();
		var timestamp = Math.round(date.getTime() / 1000);
		query = query + "&ts=" + timestamp
		
		disableContainerChildren("headlinesToolbar", false);
		Form.enable("main_toolbar_form");

		debug(query);

		// for piggybacked counters

		if (tagsAreDisplayed()) {
			query = query + "&omode=lt";
		} else {
			query = query + "&omode=flc";
		}

		if (!page_offset) {
			notify_progress("Loading, please wait...", true);
		}

		var container = document.getElementById("headlinesInnerContainer");

		if (container && page_offset == 0 && !isCdmMode()) {
			new Effect.Fade(container, {duration: 1, to: 0.01,
				queue: { position:'end', scope: 'FEEDL-' + feed, limit: 1 } } );
		}

		new Ajax.Request(query, {
			onComplete: function(transport) { 
				headlines_callback2(transport, feed, is_cat, page_offset); 
			} });

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
			cap.innerHTML = cap.innerHTML.replace("...", "");
		} else {
			if (cap.innerHTML.lastIndexOf("...") != cap.innerHTML.length-3) {
				cap.innerHTML = cap.innerHTML + "...";
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

function feedlist_init() {
	try {
//		if (arguments.callee.done) return;
//		arguments.callee.done = true;		
		
		debug("in feedlist init");
		
		hideOrShowFeeds(document, getInitParam("hide_read_feeds") == 1);
		document.onkeydown = hotkey_handler;
		setTimeout("timeout()", 0);

		debug("about to remove splash, OMG!");

		var o = document.getElementById("overlay");

		if (o) {
			o.style.display = "none";
			debug("removed splash!");
		}

		if (typeof correctPNG != 'undefined') {
			correctPNG();
		}

		if (getActiveFeedId()) {
			debug("some feed is open on feedlist refresh, reloading");
			setTimeout("viewCurrentFeed()", 100);
		} else {
			if (getInitParam("cdm_auto_catchup") != 1) {				
				setTimeout("viewfeed(-3)", 100);
			}
		}

	} catch (e) {
		exception_error("feedlist/init", e);
	}
}

var active_post_id = false;
var last_article_view = false;
var active_real_feed_id = false;

var _tag_active_post_id = false;
var _tag_active_feed_id = false;
var _tag_active_cdm = false;

// FIXME: kludge, to restore scrollTop after tag editor terminates
var _tag_cdm_scroll = false;

// FIXME: kludges, needs proper implementation
var _reload_feedlist_after_view = false;

var _cdm_wd_timeout = false;
var _cdm_wd_vishist = new Array();

var article_cache = new Array();

var vgroup_last_feed = false;
var post_under_pointer = false;

function catchup_callback() {
	if (xmlhttp_rpc.readyState == 4) {
		try {
			debug("catchup_callback");
			notify("");			
			all_counters_callback2(xmlhttp_rpc);
			if (_catchup_callback_func) {
				setTimeout(_catchup_callback_func, 10);	
			}
		} catch (e) {
			exception_error("catchup_callback", e);
		}
	}
}

function catchup_callback2(transport, callback) {
	try {
		debug("catchup_callback2 " + transport + ", " + callback);
		notify("");			
		all_counters_callback2(transport);
		if (callback) {
			setTimeout(callback, 10);	
		}
	} catch (e) {
		exception_error("catchup_callback2", e);
	}
}

function clean_feed_selections() {
	try {
		var feeds = document.getElementById("feedList").getElementsByTagName("LI");

		for (var i = 0; i < feeds.length; i++) {
			if (feeds[i].id && feeds[i].id.match("FEEDR-")) {
				feeds[i].className = feeds[i].className.replace("Selected", "");
			}			
		}
	} catch (e) {
		exception_error("clean_feed_selections", e);
	}
}

function headlines_callback2(transport, active_feed_id, is_cat, feed_cur_page) {
	try {

		debug("headlines_callback2 [page=" + feed_cur_page + "]");

		clean_feed_selections();

		setActiveFeedId(active_feed_id);
		
		if (is_cat != undefined) {
			active_feed_is_cat = is_cat;
		}
	
		if (!is_cat) {
			var feedr = document.getElementById("FEEDR-" + active_feed_id);
			if (feedr && !feedr.className.match("Selected")) {	
				feedr.className = feedr.className + "Selected";
			} 
		}
	
		var f = document.getElementById("headlines-frame");
		try {
			if (feed_cur_page == 0) { 
				debug("resetting headlines scrollTop");
				f.scrollTop = 0; 
			}
		} catch (e) { };
	
		if (transport.responseXML) {
			var headlines = transport.responseXML.getElementsByTagName("headlines")[0];
			var headlines_count_obj = transport.responseXML.getElementsByTagName("headlines-count")[0];
			var headlines_unread_obj = transport.responseXML.getElementsByTagName("headlines-unread")[0];
			var disable_cache_obj = transport.responseXML.getElementsByTagName("disable-cache")[0];

			var vgroup_last_feed_obj =  transport.responseXML.getElementsByTagName("vgroup-last-feed")[0];

			var headlines_count = headlines_count_obj.getAttribute("value");
			var headlines_unread = headlines_unread_obj.getAttribute("value");
			var disable_cache = disable_cache_obj.getAttribute("value") != "0";

			vgroup_last_feed = vgroup_last_feed_obj.getAttribute("value");

			if (headlines_count == 0) {
				_infscroll_disable = 1;
			} else {
				_infscroll_disable = 0;
			}

			var counters = transport.responseXML.getElementsByTagName("counters")[0];
			var articles = transport.responseXML.getElementsByTagName("article");
			var runtime_info = transport.responseXML.getElementsByTagName("runtime-info");
	
			if (feed_cur_page == 0) {
				if (headlines) {
					f.innerHTML = headlines.firstChild.nodeValue;

					var cache_prefix = "";

					if (is_cat) {
						cache_prefix = "C:";
					} else {
						cache_prefix = "F:";
					}

					cache_invalidate(cache_prefix + active_feed_id);

					if (!disable_cache) {
						cache_inject(cache_prefix + active_feed_id,
							headlines.firstChild.nodeValue, headlines_unread);
					}

				} else {
					debug("headlines_callback: returned no data");
				f.innerHTML = "<div class='whiteBox'>" + __('Could not update headlines (missing XML data)') + "</div>";
	
				}
			} else {
				if (headlines) {
					if (headlines_count > 0) {
						debug("adding some more headlines...");
	
						var c = document.getElementById("headlinesList");
		
						if (!c) {
							c = document.getElementById("headlinesInnerContainer");
						}

						var ids = getSelectedArticleIds2();
	
						c.innerHTML = c.innerHTML + headlines.firstChild.nodeValue;

						debug("restore selected ids: " + ids);

						for (var i = 0; i < ids.length; i++) {
							markHeadline(ids[i]);
						}

						subtoolbarSearch();

					} else {
						debug("no new headlines received");
					}
				} else {
					debug("headlines_callback: returned no data");
					notify_error("Error while trying to load more headlines");	
				}
	
			}
	
			if (articles) {
				for (var i = 0; i < articles.length; i++) {
					var a_id = articles[i].getAttribute("id");
					debug("found id: " + a_id);
					cache_inject(a_id, articles[i].firstChild.nodeValue);
				}
			} else {
				debug("no cached articles received");
			}
	
			if (counters) {
				debug("parsing piggybacked counters: " + counters);
				parse_counters(counters, false);
			} else {
				debug("counters container not found in reply");
			}
	
			if (runtime_info) {
				debug("parsing runtime info: " + runtime_info[0]);
				parse_runtime_info(runtime_info[0]);
			} else {
				debug("counters container not found in reply");
			}
	
		} else {
			debug("headlines_callback: returned no XML object");
			f.innerHTML = "<div class='whiteBox'>" + __('Could not update headlines (missing XML object)') + "</div>";
		}
	
		if (typeof correctPNG != 'undefined') {
			correctPNG();
		}
	
		if (_cdm_wd_timeout) window.clearTimeout(_cdm_wd_timeout);
	
		if (!document.getElementById("headlinesList") && 
				getInitParam("cdm_auto_catchup") == 1) {
			debug("starting CDM watchdog");
			_cdm_wd_timeout = window.setTimeout("cdmWatchdog()", 5000);
			_cdm_wd_vishist = new Array();
		} else {
			debug("not in CDM mode or watchdog disabled");
		}
	
		if (_tag_cdm_scroll) {
			try {
				document.getElementById("headlinesInnerContainer").scrollTop = _tag_cdm_scroll;
				_tag_cdm_scroll = false;
				debug("resetting headlinesInner scrollTop");
	
			} catch (e) { }
		}
	
		_feed_cur_page = feed_cur_page;
		_infscroll_request_sent = 0;

		notify("");
	} catch (e) {
		exception_error("headlines_callback2", e);
	}
}

function render_article(article) {
	try {
		var f = document.getElementById("content-frame");
		try {
			f.scrollTop = 0;
		} catch (e) { };

		f.innerHTML = article;

	} catch (e) {
		exception_error("render_article", e);
	}
}

function showArticleInHeadlines(id) {

	try {

		cleanSelected("headlinesList");
	
		var crow = document.getElementById("RROW-" + id);

		if (!crow) return;

		var article_is_unread = crow.className.match("Unread");
			
		crow.className = crow.className.replace("Unread", "");

		selectTableRowsByIdPrefix('headlinesList', 'RROW-', 'RCHK-', false);
	
		var upd_img_pic = document.getElementById("FUPDPIC-" + id);

		var cache_prefix = "";
				
		if (activeFeedIsCat()) {
			cache_prefix = "C:";
		} else {
			cache_prefix = "F:";
		}

		var view_mode = false;

		try {
			view_mode = document.forms['main_toolbar_form'].view_mode;	
			view_mode = view_mode[view_mode.selectedIndex].value;
		} catch (e) {
			exception_error("showArticleInHeadlines/viewmode", e, true);
		}

		if (upd_img_pic && upd_img_pic.src.match("updated.png")) {
			upd_img_pic.src = "images/blank_icon.gif";

			cache_invalidate(cache_prefix + getActiveFeedId());

			cache_inject(cache_prefix + getActiveFeedId(),
				document.getElementById("headlines-frame").innerHTML,
				get_feed_unread(getActiveFeedId()));

		} else if (article_is_unread && view_mode == "all_articles") {

			cache_invalidate(cache_prefix + getActiveFeedId());

			cache_inject(cache_prefix + getActiveFeedId(),
				document.getElementById("headlines-frame").innerHTML,
				get_feed_unread(getActiveFeedId())-1);

		} else if (article_is_unread) {
			cache_invalidate(cache_prefix + getActiveFeedId());
		}

		markHeadline(id);

	} catch (e) {
		exception_error("showArticleInHeadlines", e);
	}
}

function article_callback2(transport, id, feed_id) {
	try {
		debug("article_callback2 " + id);

		if (transport.responseXML) {

			active_real_feed_id = feed_id;
			active_post_id = id; 

			showArticleInHeadlines(id);	

			var reply = transport.responseXML.firstChild.firstChild;

			var articles = transport.responseXML.getElementsByTagName("article");

			for (var i = 0; i < articles.length; i++) {
				var a_id = articles[i].getAttribute("id");

				debug("found id: " + a_id);

				if (a_id == active_post_id) {
					debug("active article, rendering...");					
					render_article(articles[i].firstChild.nodeValue);
				}

				cache_inject(a_id, articles[i].firstChild.nodeValue);
			}
		
		} else {
			debug("article_callback: returned no XML object");
			var f = document.getElementById("content-frame");
			f.innerHTML = "<div class='whiteBox'>" + __('Could not display article (missing XML object)') + "</div>";
		}

		var date = new Date();
		last_article_view = date.getTime() / 1000;

		if (typeof correctPNG != 'undefined') {
			correctPNG();
		}

		if (_reload_feedlist_after_view) {
			setTimeout('updateFeedList(false, false)', 50);			
			_reload_feedlist_after_view = false;
		} else {
			var counters = transport.responseXML.getElementsByTagName("counters")[0];

			if (counters) {
				debug("parsing piggybacked counters: " + counters);
				parse_counters(counters, false);
			} else {
				debug("counters container not found in reply");
			}
		}

		notify("");
	} catch (e) {
		exception_error("article_callback2", e);
	}
}

function view(id, feed_id, skip_history) {
	
	try {
		debug("loading article: " + id + "/" + feed_id);
	
		var cached_article = cache_find(id);

		debug("cache check result: " + (cached_article != false));
	
		enableHotkeys();
	
		//setActiveFeedId(feed_id);

		var query = "backend.php?op=view&id=" + param_escape(id) +
			"&feed=" + param_escape(feed_id);

		var date = new Date();

		var neighbor_ids = getRelativePostIds(active_post_id);

		/* only request uncached articles */

		var cids_to_request = Array();

		for (var i = 0; i < neighbor_ids.length; i++) {
			if (!cache_check(neighbor_ids[i])) {
				cids_to_request.push(neighbor_ids[i]);
			}
		}

		debug("additional ids: " + cids_to_request.toString());			

		/* additional info for piggyback counters */

		if (tagsAreDisplayed()) {
			query = query + "&omode=lt";
		} else {
			query = query + "&omode=flc";
		}

		var date = new Date();
		var timestamp = Math.round(date.getTime() / 1000);
		query = query + "&ts=" + timestamp;

		query = query + "&cids=" + cids_to_request.toString();

		var crow = document.getElementById("RROW-" + id);
		var article_is_unread = crow.className.match("Unread");

		showArticleInHeadlines(id);

		if (!cached_article) {

			notify_progress("Loading, please wait...");

		} else if (cached_article && article_is_unread) {

			query = query + "&mode=prefetch";

			render_article(cached_article);

		} else if (cached_article) {

			query = query + "&mode=prefetch_old";
			render_article(cached_article);
		}

		cache_expire();

		new Ajax.Request(query, {
			onComplete: function(transport) { 
				article_callback2(transport, id, feed_id); 
			} });

	} catch (e) {
		exception_error("view", e);
	}
}

function tMark(id) {
	return toggleMark(id);
}

function tPub(id) {
	return togglePub(id);
}

function tMark_afh_off(effect) {
	try {
		var elem = effect.effects[0].element;

		debug("tMark_afh_off : " + elem.id);

		if (elem) {
			elem.src = elem.src.replace("mark_set", "mark_unset");
			elem.alt = __("Star article");
			Element.show(elem);
		}

	} catch (e) {
		exception_error("tMark_afh_off", e);
	}
}

function tPub_afh_off(effect) {
	try {
		var elem = effect.effects[0].element;

		debug("tPub_afh_off : " + elem.id);

		if (elem) {
			elem.src = elem.src.replace("pub_set", "pub_unset");
			elem.alt = __("Publish article");
			Element.show(elem);
		}

	} catch (e) {
		exception_error("tPub_afh_off", e);
	}
}

function toggleMark(id, client_only, no_effects) {

	try {

		var query = "backend.php?op=rpc&id=" + id + "&subop=mark";
	
		query = query + "&afid=" + getActiveFeedId();
	
		if (tagsAreDisplayed()) {
			query = query + "&omode=tl";
		} else {
			query = query + "&omode=flc";
		}
	
		var mark_img = document.getElementById("FMPIC-" + id);
		var vfeedu = document.getElementById("FEEDU--1");
		var crow = document.getElementById("RROW-" + id);
	
		if (mark_img.src.match("mark_unset")) {
			mark_img.src = mark_img.src.replace("mark_unset", "mark_set");
			mark_img.alt = __("Unstar article");
			query = query + "&mark=1";
	
/*			if (vfeedu && crow.className.match("Unread")) {
				vfeedu.innerHTML = (+vfeedu.innerHTML) + 1;
			} */
	
		} else {
			//mark_img.src = "images/mark_unset.png";
			mark_img.alt = __("Please wait...");
			query = query + "&mark=0";
	
/*			if (vfeedu && crow.className.match("Unread")) {
				vfeedu.innerHTML = (+vfeedu.innerHTML) - 1;
			} */
	
			if (document.getElementById("headlinesList") && !no_effects) {
				Effect.Puff(mark_img, {duration : 0.25, afterFinish: tMark_afh_off});
			} else { 
				mark_img.src = mark_img.src.replace("mark_set", "mark_unset");
				mark_img.alt = __("Star article");
			}
		}
	
/*		var vfeedctr = document.getElementById("FEEDCTR--1");
		var vfeedr = document.getElementById("FEEDR--1");
	
		if (vfeedu && vfeedctr) {
			if ((+vfeedu.innerHTML) > 0) {
				if (crow.className.match("Unread") && !vfeedr.className.match("Unread")) {
					vfeedr.className = vfeedr.className + "Unread";
					vfeedctr.className = "odd";
				}
			} else {
				vfeedctr.className = "invisible";
				vfeedr.className = vfeedr.className.replace("Unread", "");
			}
		}
	
		debug("toggle starred for aid " + id);
	
		//new Ajax.Request(query); */

		if (!client_only) {
			debug(query);

			new Ajax.Request(query, {
				onComplete: function(transport) { 
					all_counters_callback2(transport); 
				} });

		}

	} catch (e) {
		exception_error("toggleMark", e);
	}
}

function togglePub(id, client_only, no_effects) {

	try {

		var query = "backend.php?op=rpc&id=" + id + "&subop=publ";
	
		query = query + "&afid=" + getActiveFeedId();
	
		if (tagsAreDisplayed()) {
			query = query + "&omode=tl";
		} else {
			query = query + "&omode=flc";
		}
	
		var mark_img = document.getElementById("FPPIC-" + id);
		var vfeedu = document.getElementById("FEEDU--2");
		var crow = document.getElementById("RROW-" + id);
	
		if (mark_img.src.match("pub_unset")) {
			mark_img.src = mark_img.src.replace("pub_unset", "pub_set");
			mark_img.alt = __("Unpublish article");
			query = query + "&pub=1";
	
/*			if (vfeedu && crow.className.match("Unread")) {
				vfeedu.innerHTML = (+vfeedu.innerHTML) + 1;
			} */
	
		} else {
			//mark_img.src = "images/pub_unset.png";
			mark_img.alt = __("Please wait...");
			query = query + "&pub=0";
	
/*			if (vfeedu && crow.className.match("Unread")) {
				vfeedu.innerHTML = (+vfeedu.innerHTML) - 1;
			} */

			if (document.getElementById("headlinesList") && !no_effects) {
				Effect.Puff(mark_img, {duration : 0.25, afterFinish: tPub_afh_off});
			} else { 
				mark_img.src = mark_img.src.replace("pub_set", "pub_unset");
				mark_img.alt = __("Publish article");
			}
		}
	
/*		var vfeedctr = document.getElementById("FEEDCTR--2");
		var vfeedr = document.getElementById("FEEDR--2");
	
		if (vfeedu && vfeedctr) {
			if ((+vfeedu.innerHTML) > 0) {
				if (crow.className.match("Unread") && !vfeedr.className.match("Unread")) {
					vfeedr.className = vfeedr.className + "Unread";
					vfeedctr.className = "odd";
				}
			} else {
				vfeedctr.className = "invisible";
				vfeedr.className = vfeedr.className.replace("Unread", "");
			}
		}
	
		debug("toggle published for aid " + id);
	
		new Ajax.Request(query); */

		if (!client_only) {
			new Ajax.Request(query, {
				onComplete: function(transport) { 
					all_counters_callback2(transport); 
				} });
		}

	} catch (e) {

		exception_error("togglePub", e);
	}
}

function correctHeadlinesOffset(id) {
	
	try {

		var hlist = document.getElementById("headlinesList");
		var container = document.getElementById("headlinesInnerContainer");
		var row = document.getElementById("RROW-" + id);
	
		var viewport = container.offsetHeight;
	
		var rel_offset_top = row.offsetTop - container.scrollTop;
		var rel_offset_bottom = row.offsetTop + row.offsetHeight - container.scrollTop;
	
		debug("Rtop: " + rel_offset_top + " Rbtm: " + rel_offset_bottom);
		debug("Vport: " + viewport);

		if (rel_offset_top <= 0 || rel_offset_top > viewport) {
			container.scrollTop = row.offsetTop;
		} else if (rel_offset_bottom > viewport) {

			/* doesn't properly work with Opera in some cases because
				Opera fucks up element scrolling */

			container.scrollTop = row.offsetTop + row.offsetHeight - viewport;		
		} 

	} catch (e) {
		exception_error("correctHeadlinesOffset", e);
	}

}

function moveToPost(mode) {

	try {

		var rows;

		if (isCdmMode()) {
			rows = cdmGetVisibleArticles();
		} else {
			rows = getVisibleHeadlineIds();
		}
		
		var prev_id = false;
		var next_id = false;
		
		if (!document.getElementById('RROW-' + active_post_id)) {
			active_post_id = false;
		}
		
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
		 	if (next_id) {
				if (isCdmMode()) {
	
					if (!cdmArticleIsActuallyVisible(next_id)) {
						cdmScrollToArticleId(next_id);
					}
					cdmSelectArticles("none");
					toggleUnread(next_id, 0, true);
					toggleSelected(next_id);

				} else {
					correctHeadlinesOffset(next_id);
					view(next_id, getActiveFeedId());
				}
			}
		}
		
		if (mode == "prev") {
			if (prev_id) {
				if (isCdmMode()) {
					cdmScrollToArticleId(prev_id);
					cdmSelectArticles("none");
					toggleUnread(prev_id, 0, true);
					toggleSelected(prev_id);
				} else {
					correctHeadlinesOffset(prev_id);
					view(prev_id, getActiveFeedId());
				}
			}
		} 


/*	} else {
			var rows = cdmGetVisibleArticles();

			if (mode == "next") {

				for (var i = 0; i < rows.length; i++) {

					if (cdmArticleIsActuallyVisible(rows[i]) ||
							cdmArticleIsBelowViewport(rows[i])) {

						if (!cdmArticleIsActuallyVisible(rows[i])) {
							cdmScrollToArticleId(rows[i]);
						}
						//setTimeout("toggleUnread(" + rows[i] + ", undefined, true)", 500);
						cdmSelectArticles("none");
						toggleUnread(rows[i], undefined, true);
						toggleSelected(rows[i]);

						return;
					}
				}

				cdmScrollViewport('bottom');

			} else if (mode == "prev") {

				for (var i = 0; i < rows.length; i++) {

					if (cdmArticleIsAboveViewport(rows[i]) &&
								!cdmArticleIsAboveViewport(rows[i+1])) {

						cdmScrollToArticleId(rows[i]);
						//setTimeout("toggleUnread(" + rows[i] + ", undefined, true)", 500);
						cdmSelectArticles("none");
						toggleUnread(rows[i], undefined, true);
						cdmSelectArticleById(rows[i]);

						break;
					} 
				}

			} 
	
		} */

	} catch (e) {
		exception_error("moveToPost", e);
	}
}

function toggleSelected(id) {
	try {
	
		var cb = document.getElementById("RCHK-" + id);

		var row = document.getElementById("RROW-" + id);
		if (row) {
			var nc = row.className;
			
			if (!nc.match("Selected")) {
				nc = nc + "Selected";
				if (cb) {
					cb.checked = true;
				}

				// In CDM basically last selected article == active article
				if (isCdmMode()) active_post_id = id;
			} else {
				nc = nc.replace("Selected", "");
				if (cb) {
					cb.checked = false;
				}

			}

			row.className = nc;
		}
	} catch (e) {
		exception_error("toggleSelected", e);
	}
}

function toggleUnread_afh(effect) {
	try {

		var elem = effect.element;
		elem.style.backgroundColor = "";

	} catch (e) {
		exception_error("toggleUnread_afh", e);
	}
} 

function toggleUnread(id, cmode, effect) {
	try {
	
		var row = document.getElementById("RROW-" + id);
		if (row) {
			var nc = row.className;
			var is_selected = row.className.match("Selected");
			nc = nc.replace("Unread", "");
			nc = nc.replace("Selected", "");

			// since we are removing selection from the object, uncheck
			// corresponding checkbox

			var cb = document.getElementById("RCHK-" + id);
			if (cb) {
				cb.checked = false;
			}

			// NOTE: I'm not sure that resetting selection here is a feature -fox

			if (cmode == undefined || cmode == 2) {
				if (row.className.match("Unread")) {
					row.className = nc;

					if (effect) {
						new Effect.Highlight(row, {duration: 1, startcolor: "#fff7d5",
							afterFinish: toggleUnread_afh,
							queue: { position:'end', scope: 'TMRQ-' + id, limit: 1 } } );
					} 

				} else {
					row.className = nc + "Unread";
				}
			} else if (cmode == 0) {
				row.className = nc;

				if (effect) {
					new Effect.Highlight(row, {duration: 1, startcolor: "#fff7d5",
						afterFinish: toggleUnread_afh,
						queue: { position:'end', scope: 'TMRQ-' + id, limit: 1 } } );
				} 
			} else if (cmode == 1) {
				row.className = nc + "Unread";
			}

			// Disable unmarking as selected for the time being (16.05.08) -fox
			if (is_selected) row.className = row.className + "Selected";

			if (cmode == undefined) cmode = 2;

			var query = "backend.php?op=rpc&subop=catchupSelected&ids=" +
				param_escape(id) + "&cmode=" + param_escape(cmode);

//			notify_progress("Loading, please wait...");

			new Ajax.Request(query, {
				onComplete: function(transport) { 
					all_counters_callback2(transport); 
				} });

		}


	} catch (e) {
		exception_error("toggleUnread", e);
	}
}

function selectionToggleUnread(cdm_mode, set_state, callback_func, no_error) {
	try {
		var rows;

		if (cdm_mode) {
			rows = cdmGetSelectedArticles();
		} else {	
			rows = getSelectedTableRowIds("headlinesList", "RROW", "RCHK");
		}

		if (rows.length == 0 && !no_error) {
			alert(__("No articles are selected."));
			return;
		}

		for (i = 0; i < rows.length; i++) {
			var row = document.getElementById("RROW-" + rows[i]);
			if (row) {
				var nc = row.className;
				nc = nc.replace("Unread", "");
				nc = nc.replace("Selected", "");

				if (set_state == undefined) {
					if (row.className.match("Unread")) {
						row.className = nc + "Selected";
					} else {
						row.className = nc + "UnreadSelected";
					}
				}

				if (set_state == false) {
					row.className = nc + "Selected";
				}

				if (set_state == true) {
					row.className = nc + "UnreadSelected";
				}
			}
		}

		if (rows.length > 0) {

			var cmode = "";

			if (set_state == undefined) {
				cmode = "2";
			} else if (set_state == true) {
				cmode = "1";
			} else if (set_state == false) {
				cmode = "0";
			}

			var query = "backend.php?op=rpc&subop=catchupSelected&ids=" +
				param_escape(rows.toString()) + "&cmode=" + cmode;

			notify_progress("Loading, please wait...");

			new Ajax.Request(query, {
				onComplete: function(transport) { 
					catchup_callback2(transport, callback_func); 
				} });

		}

	} catch (e) {
		exception_error("selectionToggleUnread", e);
	}
}

function selectionToggleMarked(cdm_mode) {
	try {
	
		var rows;
		
		if (cdm_mode) {
			rows = cdmGetSelectedArticles();
		} else {	
			rows = getSelectedTableRowIds("headlinesList", "RROW", "RCHK");
		}	

		if (rows.length == 0) {
			alert(__("No articles are selected."));
			return;
		}

		for (i = 0; i < rows.length; i++) {
			toggleMark(rows[i], true, true);
		}

		if (rows.length > 0) {

			var query = "backend.php?op=rpc&subop=markSelected&ids=" +
				param_escape(rows.toString()) + "&cmode=2";

			query = query + "&afid=" + getActiveFeedId();

/*			if (tagsAreDisplayed()) {
				query = query + "&omode=tl";
			} else {
				query = query + "&omode=flc";
			} */

			query = query + "&omode=lc";

			new Ajax.Request(query, {
				onComplete: function(transport) { 
					all_counters_callback2(transport); 
				} });

		}

	} catch (e) {
		exception_error("selectionToggleMarked", e);
	}
}

function selectionTogglePublished(cdm_mode) {
	try {
	
		var rows;
		
		if (cdm_mode) {
			rows = cdmGetSelectedArticles();
		} else {	
			rows = getSelectedTableRowIds("headlinesList", "RROW", "RCHK");
		}	

		if (rows.length == 0) {
			alert(__("No articles are selected."));
			return;
		}

		for (i = 0; i < rows.length; i++) {
			togglePub(rows[i], true, true);
		}

		if (rows.length > 0) {

			var query = "backend.php?op=rpc&subop=publishSelected&ids=" +
				param_escape(rows.toString()) + "&cmode=2";

			query = query + "&afid=" + getActiveFeedId();

/*			if (tagsAreDisplayed()) {
				query = query + "&omode=tl";
			} else {
				query = query + "&omode=flc";
			} */

			query = query + "&omode=lc";

			new Ajax.Request(query, {
				onComplete: function(transport) { 
					all_counters_callback2(transport); 
				} });

		}

	} catch (e) {
		exception_error("selectionToggleMarked", e);
	}
}

function cdmGetSelectedArticles() {
	var sel_articles = new Array();
	var container = document.getElementById("headlinesInnerContainer");

	for (i = 0; i < container.childNodes.length; i++) {
		var child = container.childNodes[i];

		if (child.id.match("RROW-") && child.className.match("Selected")) {
			var c_id = child.id.replace("RROW-", "");
			sel_articles.push(c_id);
		}
	}

	return sel_articles;
}

function cdmGetVisibleArticles() {
	var sel_articles = new Array();
	var container = document.getElementById("headlinesInnerContainer");

	for (i = 0; i < container.childNodes.length; i++) {
		var child = container.childNodes[i];

		if (child.id.match("RROW-")) {
			var c_id = child.id.replace("RROW-", "");
			sel_articles.push(c_id);
		}
	}

	return sel_articles;
}

function cdmGetUnreadArticles() {
	var sel_articles = new Array();
	var container = document.getElementById("headlinesInnerContainer");

	for (i = 0; i < container.childNodes.length; i++) {
		var child = container.childNodes[i];

		if (child.id.match("RROW-") && child.className.match("Unread")) {
			var c_id = child.id.replace("RROW-", "");
			sel_articles.push(c_id);
		}
	}

	return sel_articles;
}


// mode = all,none,unread
function cdmSelectArticles(mode) {
	var container = document.getElementById("headlinesInnerContainer");

	for (i = 0; i < container.childNodes.length; i++) {
		var child = container.childNodes[i];

		if (child.id.match("RROW-")) {
			var aid = child.id.replace("RROW-", "");

			var cb = document.getElementById("RCHK-" + aid);

			if (mode == "all") {
				if (!child.className.match("Selected")) {
					child.className = child.className + "Selected";
					cb.checked = true;
				}
			} else if (mode == "unread") {
				if (child.className.match("Unread") && !child.className.match("Selected")) {
					child.className = child.className + "Selected";
					cb.checked = true;
				}
			} else {
				child.className = child.className.replace("Selected", "");
				cb.checked = false;
			}
		}		
	}
}

function catchupPage() {

	var fn = getFeedName(getActiveFeedId(), active_feed_is_cat);
	
	var str = __("Mark all visible articles in %s as read?");

	str = str.replace("%s", fn);

	if (getInitParam("confirm_feed_catchup") == 1 && !confirm(str)) {
		return;
	}

	if (document.getElementById("headlinesList")) {
		selectTableRowsByIdPrefix('headlinesList', 'RROW-', 'RCHK-', true, 'Unread', true);
		selectionToggleUnread(false, false, 'viewCurrentFeed()', true);
		selectTableRowsByIdPrefix('headlinesList', 'RROW-', 'RCHK-', false);
	} else {
		cdmSelectArticles('all');
		selectionToggleUnread(true, false, 'viewCurrentFeed()', true)
		cdmSelectArticles('none');
	}
}

function catchupSelection() {

	try {

		var rows;
	
		if (document.getElementById("headlinesList")) {
			rows = getSelectedTableRowIds("headlinesList", "RROW", "RCHK");
		} else {	
			rows = cdmGetSelectedArticles();
		}
	
		if (rows.length == 0) {
			alert(__("No articles are selected."));
			return;
		}
	
	
		var fn = getFeedName(getActiveFeedId(), active_feed_is_cat);
		
		var str = __("Mark %d selected articles in %s as read?");
	
		str = str.replace("%d", rows.length);
		str = str.replace("%s", fn);
	
		if (getInitParam("confirm_feed_catchup") == 1 && !confirm(str)) {
			return;
		}
	
		if (document.getElementById("headlinesList")) {
			selectionToggleUnread(false, false, 'viewCurrentFeed()', true);
	//		selectTableRowsByIdPrefix('headlinesList', 'RROW-', 'RCHK-', false);
		} else {
			selectionToggleUnread(true, false, 'viewCurrentFeed()', true)
	//		cdmSelectArticles('none');
		}

	} catch (e) {
		exception_error("catchupSelection", e);
	}
}


function labelFromSearch(search, search_mode, match_on, feed_id, is_cat) {

	if (!xmlhttp_ready(xmlhttp_rpc)) {
		printLockingError();
	}

	var title = prompt(__("Please enter label title:"), "");

	if (title) {

		var query = "backend.php?op=labelFromSearch&search=" + param_escape(search) +
			"&smode=" + param_escape(search_mode) + "&match=" + param_escape(match_on) +
			"&feed=" + param_escape(feed_id) + "&is_cat=" + param_escape(is_cat) + 
			"&title=" + param_escape(title);

		debug("LFS: " + query);

		new Ajax.Request(query,	{
			onComplete: function(transport) {
					dlg_frefresh_callback(transport);
				} });
	}
}

function editArticleTags(id, feed_id, cdm_enabled) {
	_tag_active_post_id = id;
	_tag_active_feed_id = feed_id;
	_tag_active_cdm = cdm_enabled;

	cache_invalidate(id);

	try {
		_tag_cdm_scroll = document.getElementById("headlinesInnerContainer").scrollTop;
	} catch (e) { }
	displayDlg('editArticleTags', id);
}


function tag_saved_callback(transport) {
	try {
		debug("in tag_saved_callback");

		closeInfoBox();
		notify("");

		if (tagsAreDisplayed()) {
			_reload_feedlist_after_view = true;
		}

		if (!_tag_active_cdm) {
			if (active_post_id == _tag_active_post_id) {
				debug("reloading current article");
				view(_tag_active_post_id, _tag_active_feed_id);			
			}
		} else {
			debug("reloading current feed");
			viewCurrentFeed();
		}

	} catch (e) {
		exception_error("catchup_callback", e);
	}
}

function editTagsSave() {

	notify_progress("Saving article tags...");

	var form = document.forms["tag_edit_form"];

	var query = Form.serialize("tag_edit_form");

	query = "backend.php?op=rpc&subop=setArticleTags&" + query;

	debug(query);

	new Ajax.Request(query,	{
		onComplete: function(transport) {
				tag_saved_callback(transport);
			} });

}

function editTagsInsert() {
	try {

		var form = document.forms["tag_edit_form"];

		var found_tags = form.found_tags;
		var tags_str = form.tags_str;

		var tag = found_tags[found_tags.selectedIndex].value;

		if (tags_str.value.length > 0 && 
				tags_str.value.lastIndexOf(", ") != tags_str.value.length - 2) {

			tags_str.value = tags_str.value + ", ";
		}

		tags_str.value = tags_str.value + tag + ", ";

		found_tags.selectedIndex = 0;
		
	} catch (e) {
		exception_error("editTagsInsert", e);
	}
}

function cdmScrollViewport(where) {
	debug("cdmScrollViewport: " + where);

	var ctr = document.getElementById("headlinesInnerContainer");

	if (!ctr) return;

	if (where == "bottom") {
		ctr.scrollTop = ctr.scrollHeight;
	} else {
		ctr.scrollTop = where;
	}
}

function cdmArticleIsBelowViewport(id) {
	try {
		var ctr = document.getElementById("headlinesInnerContainer");
		var e = document.getElementById("RROW-" + id);

		if (!e || !ctr) return;

		// article starts below viewport

		if (ctr.scrollTop < e.offsetTop) {
			return true;
		} else {	
			return false;
		}

	} catch (e) {
		exception_error("cdmArticleIsVisible", e);
	}
}

function cdmArticleIsAboveViewport(id) {
	try {
		var ctr = document.getElementById("headlinesInnerContainer");
		var e = document.getElementById("RROW-" + id);

		if (!e || !ctr) return;

		// article starts above viewport

		if (ctr.scrollTop > e.offsetTop + e.offsetHeight) {
			return true;
		} else {	
			return false;
		}

	} catch (e) {
		exception_error("cdmArticleIsVisible", e);
	}
}

function cdmScrollToArticleId(id) {
	try {
		var ctr = document.getElementById("headlinesInnerContainer");
		var e = document.getElementById("RROW-" + id);

		if (!e || !ctr) return;

		ctr.scrollTop = e.offsetTop;

	} catch (e) {
		exception_error("cdmScrollToArticleId", e);
	}
}

function cdmArticleIsActuallyVisible(id) {
	try {
		var ctr = document.getElementById("headlinesInnerContainer");
		var e = document.getElementById("RROW-" + id);

		if (!e || !ctr) return;

		// article fits in viewport OR article is longer than viewport and
		// its bottom is visible

		if (ctr.scrollTop <= e.offsetTop && e.offsetTop + e.offsetHeight <=
				ctr.scrollTop + ctr.offsetHeight) {

			return true;
		
		} else if (e.offsetHeight > ctr.offsetHeight &&
				e.offsetTop + e.offsetHeight >= ctr.scrollTop &&
				e.offsetTop + e.offsetHeight <= ctr.scrollTop + ctr.offsetHeight) {

			return true;

		}

		return false;

	} catch (e) {
		exception_error("cdmArticleIsVisible", e);
	}
}

function cdmWatchdog() {

	try {

		var ctr = document.getElementById("headlinesInnerContainer");

		if (!ctr) return;

		var ids = new Array();

		var e = ctr.firstChild;

		while (e) {
			if (e.className && e.className == "cdmArticleUnread" && e.id &&
					e.id.match("RROW-")) {

				// article fits in viewport OR article is longer than viewport and
				// its bottom is visible

				if (ctr.scrollTop <= e.offsetTop && e.offsetTop + e.offsetHeight <=
						ctr.scrollTop + ctr.offsetHeight) {

//					debug(e.id + " is visible " + e.offsetTop + "." + 
//						(e.offsetTop + e.offsetHeight) + " vs " + ctr.scrollTop + "." +
//						(ctr.scrollTop + ctr.offsetHeight));

					ids.push(e.id.replace("RROW-", ""));

				} else if (e.offsetHeight > ctr.offsetHeight &&
						e.offsetTop + e.offsetHeight >= ctr.scrollTop &&
						e.offsetTop + e.offsetHeight <= ctr.scrollTop + ctr.offsetHeight) {

					ids.push(e.id.replace("RROW-", "")); 

				}

				// method 2: article bottom is visible and is in upper 1/2 of the viewport

/*				if (e.offsetTop + e.offsetHeight >= ctr.scrollTop &&
						e.offsetTop + e.offsetHeight <= ctr.scrollTop + ctr.offsetHeight/2) {

					ids.push(e.id.replace("RROW-", "")); 

				} */

			}

			e = e.nextSibling;
		}

		debug("cdmWatchdog, ids= " + ids.toString());

		if (ids.length > 0) {

			for (var i = 0; i < ids.length; i++) {
				var e = document.getElementById("RROW-" + ids[i]);
				if (e) {
					e.className = e.className.replace("Unread", "");
				}
			}

			var query = "backend.php?op=rpc&subop=catchupSelected&ids=" +
				param_escape(ids.toString()) + "&cmode=0";

			new Ajax.Request(query, {
				onComplete: function(transport) { 
					all_counters_callback2(transport); 
				} });

		}

		_cdm_wd_timeout = window.setTimeout("cdmWatchdog()", 4000);

	} catch (e) {
		exception_error("cdmWatchdog", e);
	}

}


function cache_inject(id, article, param) {
	if (!cache_check_param(id, param)) {
		debug("cache_article: miss: " + id + " [p=" + param + "]");

		var cache_obj = new Array();

		cache_obj["id"] = id;
		cache_obj["data"] = article;
		cache_obj["param"] = param;

		article_cache.push(cache_obj);

	} else {
		debug("cache_article: hit: " + id + " [p=" + param + "]");
	}
}

function cache_find(id) {
	for (var i = 0; i < article_cache.length; i++) {
		if (article_cache[i]["id"] == id) {
			return article_cache[i]["data"];
		}
	}
	return false;
}

function cache_find_param(id, param) {
	for (var i = 0; i < article_cache.length; i++) {
		if (article_cache[i]["id"] == id && article_cache[i]["param"] == param) {
			return article_cache[i]["data"];
		}
	}
	return false;
}

function cache_check(id) {
	for (var i = 0; i < article_cache.length; i++) {
		if (article_cache[i]["id"] == id) {
			return true;
		}
	}
	return false;
}

function cache_check_param(id, param) {
	for (var i = 0; i < article_cache.length; i++) {

//		debug("cache_check_param " + article_cache[i]["id"] + ":" + 
//			article_cache[i]["param"] + " vs " + id + ":" + param);

		if (article_cache[i]["id"] == id && article_cache[i]["param"] == param) {
			return true;
		}
	}
	return false;
}

function cache_expire() {
	while (article_cache.length > 20) {
		article_cache.shift();
	}
}

function cache_empty() {
	article_cache = new Array();
}

function cache_invalidate(id) {
	var i = 0

	try {	

		while (i < article_cache.length) {
			if (article_cache[i]["id"] == id) {
				debug("cache_invalidate: removed id " + id);
				article_cache.splice(i, 1);
				return true;
			}
			i++;
		}
		debug("cache_invalidate: id not found: " + id);
		return false;
	} catch (e) {
		exception_error("cache_invalidate", e);
	}
}

function getActiveArticleId() {
	return active_post_id;
}

function cdmClicked(elem) {
	try {
		if (elem.id && elem.id.match("RROW-")) {
			var id = elem.id.replace("RROW-", "");
			active_post_id = id;

			cdmSelectArticles("none");
			toggleUnread(id, 0, true);
			toggleSelected(id);

		}
	} catch (e) {
		exception_error("cdmMouseIn", e);
	} 
}

function preload_article_callback(transport) {
	try {
		if (transport.responseXML) {
			var articles = transport.responseXML.getElementsByTagName("article");

			for (var i = 0; i < articles.length; i++) {
				var id = articles[i].getAttribute("id");
				if (!cache_find(id)) {
					cache_inject(id, articles[i].firstChild.nodeValue);				
					debug("preloaded article: " + id);
				}
			}
		}
	} catch (e) {
		exception_error("preload_article_callback", e);
	}
}

function preloadArticleUnderPointer(id) {
	try {
		if (post_under_pointer == id && !cache_find(id)) {

			debug("trying to preload article " + id);

			var neighbor_ids = getRelativePostIds(id, 1);

			/* only request uncached articles */

			var cids_to_request = Array();

			for (var i = 0; i < neighbor_ids.length; i++) {
				if (!cache_check(neighbor_ids[i])) {
					cids_to_request.push(neighbor_ids[i]);
				}
			}
			debug("additional ids: " + cids_to_request.toString());

			cids_to_request.push(id);

			var query = "backend.php?op=rpc&subop=getArticles&ids=" + 
				cids_to_request.toString();
			new Ajax.Request(query, {
				onComplete: function(transport) { 
					preload_article_callback(transport);
			} });
		}
	} catch (e) {
		exception_error("preloadArticleUnderPointer", e);
	}
}

function postMouseIn(id) {
	try {
		if (post_under_pointer != id) {
			post_under_pointer = id;
			if (!isCdmMode()) {
				window.setTimeout("preloadArticleUnderPointer(" + id + ")", 250);
			}
		}

	} catch (e) {
		exception_error("postMouseIn", e);
	}
}

function postMouseOut(id) {
	try {
		post_under_pointer = false;
	} catch (e) {
		exception_error("postMouseOut", e);
	}
}

function cdmMouseIn(elem) {
/*	try {
		if (elem.id && elem.id.match("RROW-")) {
			var id = elem.id.replace("RROW-", "");
			active_post_id = id;
		}
	} catch (e) {
		exception_error("cdmMouseIn", e);
	} */
}

function cdmMouseOut(elem) {
	//active_post_id = false;
}

function headlines_scroll_handler() {
	try {

		var e = document.getElementById("headlinesInnerContainer");

		// don't do infinite scrolling when Limit == All

		var toolbar_form = document.forms["main_toolbar_form"];

		var limit = toolbar_form.limit[toolbar_form.limit.selectedIndex];
		if (limit.value != 0) {
		
			debug((e.scrollTop + e.offsetHeight) + " vs " + e.scrollHeight + " dis? " +
				_infscroll_disable);

			if (e.scrollTop + e.offsetHeight > e.scrollHeight - 100) {
				if (!_infscroll_disable) {
					debug("more cowbell!");
					viewNextFeedPage();
				}
			}
		}

	} catch (e) {
		exception_error("headlines_scroll_handler", e);
	}
}

function catchupRelativeToArticle(below) {

	try {

		if (!xmlhttp_ready(xmlhttp_rpc)) {
			printLockingError();
		}
	
		if (!getActiveArticleId()) {
			alert(__("No article is selected."));
			return;
		}

		var visible_ids;

		if (document.getElementById("headlinesList")) {
			visible_ids = getVisibleHeadlineIds();
		} else {
			visible_ids = cdmGetVisibleArticles();
		}

		var ids_to_mark = new Array();

		if (!below) {
			for (var i = 0; i < visible_ids.length; i++) {
				if (visible_ids[i] != getActiveArticleId()) {
					var e = document.getElementById("RROW-" + visible_ids[i]);

					if (e && e.className.match("Unread")) {
						ids_to_mark.push(visible_ids[i]);
					}
				} else {
					break;
				}
			}
		} else {
			for (var i = visible_ids.length-1; i >= 0; i--) {
				if (visible_ids[i] != getActiveArticleId()) {
					var e = document.getElementById("RROW-" + visible_ids[i]);

					if (e && e.className.match("Unread")) {
						ids_to_mark.push(visible_ids[i]);
					}
				} else {
					break;
				}
			}
		}

		if (ids_to_mark.length == 0) {
			alert(__("No articles found to mark"));
		} else {
			var msg = __("Mark %d article(s) as read?").replace("%d", ids_to_mark.length);

			if (confirm(msg)) {

				for (var i = 0; i < ids_to_mark.length; i++) {
					var e = document.getElementById("RROW-" + ids_to_mark[i]);
					e.className = e.className.replace("Unread", "");
				}

				var query = "backend.php?op=rpc&subop=catchupSelected&ids=" +
					param_escape(ids_to_mark.toString()) + "&cmode=0";

				new Ajax.Request(query, {
					onComplete: function(transport) { 
						catchup_callback2(transport); 
					} });

			}
		}

	} catch (e) {
		exception_error("catchupRelativeToArticle", e);
	}
}

function cdmExpandArticle(a_id) {
	try {
		var id = 'CICD-' + a_id;

		Effect.Appear(id, {duration : 0.5, 
			beforeStart: function(effect) { 
				var h_id = 'CICH-' + a_id;
				var h_elem = document.getElementById(h_id);
				if (h_elem) { h_elem.style.display = "none"; }

				toggleUnread(a_id, 0);
			}});


	} catch (e) {
		exception_error("appearBlockElementF", e);
	}

}

function fixHeadlinesOrder(ids) {
	try {
		for (var i = 0; i < ids.length; i++) {
			var e = document.getElementById("RROW-" + ids[i]);

			if (e) {
				if (i % 2 == 0) {
					e.className = e.className.replace("even", "odd");
				} else {
					e.className = e.className.replace("odd", "even");
				}
			}
		}
	} catch (e) {
		exception_error("fixHeadlinesOrder", e);
	}
}

function subtoolbarSearch() {

	try {

		var q = document.getElementById("subtoolbar_search_box");
		
		if (!q) return;

		q = q.value.toUpperCase();

		var ids = false;
		var vis_ids = new Array();

		if (document.getElementById("headlinesList")) {
			ids = getVisibleHeadlineIds();
		} else {
			ids = cdmGetVisibleArticles();
		}

		for (var i = 0; i < ids.length; i++) {
			var title = document.getElementById("RTITLE-" + ids[i]);

			if (title) {
				if (!title.innerHTML.toUpperCase().match(q)) {
					Element.hide(document.getElementById("RROW-" + ids[i]));
				} else {
					Element.show(document.getElementById("RROW-" + ids[i]));
					vis_ids.push(ids[i]);
				}
			}
		}

		fixHeadlinesOrder(vis_ids);

	} catch (e) {
		exception_error("subtoolbarSearch", e);
	}
}

function getArticleUnderPointer() {
	return post_under_pointer;
}

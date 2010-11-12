var _feed_cur_page = 0;
var _infscroll_disable = 0;
var _infscroll_request_sent = 0;
var feed_under_pointer = undefined;

var mouse_is_down = false;
var mouse_y = 0;
var mouse_x = 0;

var counter_timeout_id = false;

var resize_enabled = false;
var selection_disabled = false;
var counters_last_request = 0;

var feeds_sort_by_unread = false;
var feedlist_sortable_enabled = false;

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
	viewfeed(cat, '', true);
	return false;
}

function render_feedlist(data) {
	try {

		var f = $("feeds-frame");
		f.innerHTML = data;
//		cache_invalidate("FEEDLIST");
//		cache_inject("FEEDLIST", data, getInitParam("num_feeds"));
		feedlist_init();

	} catch (e) {
		exception_error("render_feedlist", e);
	}
}

function viewNextFeedPage() {
	try {
		//if (!getActiveFeedId()) return;

		console.log("viewNextFeedPage: calling viewfeed(), p: " + parseInt(_feed_cur_page+1));

		viewfeed(getActiveFeedId(), '', activeFeedIsCat(), parseInt(_feed_cur_page+1));

	} catch (e) {
		exception_error("viewNextFeedPage", e);
	}
}


function viewfeed(feed, subop, is_cat, offset) {
	try {
		if (is_cat == undefined) is_cat = false;

		if (offline_mode) return viewfeed_offline(feed, subop, is_cat, offset);

//		if (!offset) page_offset = 0;

		last_requested_article = 0;
		//counters_last_request = 0;

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

			console.log(_infscroll_request_sent + " : " + timestamp);

			if (_infscroll_request_sent && _infscroll_request_sent + 30 > timestamp) {
				console.log("infscroll request in progress, aborting");
				return;
			}

			_infscroll_request_sent = timestamp;			
		}

		enableHotkeys();
		hideAuxDlg();
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

		var query = "?op=viewfeed&feed=" + feed + "&" +
			toolbar_query + "&subop=" + param_escape(subop);

		if ($("search_form")) {
			var search_query = Form.serialize("search_form");
			query = query + "&" + search_query;
			$("search_form").query.value = "";
			closeInfoBox(true);
			force_nocache = true;
		}

//		console.log("IS_CAT_STORED: " + activeFeedIsCat() + ", IS_CAT: " + is_cat);

		if (subop == "MarkAllRead") {

			catchup_local_feed(feed, is_cat);

			var show_next_feed = getInitParam("on_catchup_show_next_feed") == "1";

			if (show_next_feed) {

				if (!activeFeedIsCat()) {
	
					var feedlist = $('feedList');
				
					var next_unread_feed = getRelativeFeedId2(feed, false,
							"next", true);

					/* gRFI2 also returns categories which we don't really 
					 * need here, so we skip them */

					while (next_unread_feed && next_unread_feed.match("CAT:")) 
						next_unread_feed = getRelativeFeedId2(
								next_unread_feed.replace("CAT:", ""),
								true, "next", true);
					
					if (!next_unread_feed) {
						next_unread_feed = getRelativeFeedId2(-3, true,
							"next", true);
					}
	
					if (next_unread_feed) {
						query = query + "&nuf=" + param_escape(next_unread_feed);
						//setActiveFeedId(next_unread_feed);
						feed = next_unread_feed;
					}
				} else {
	
					var next_unread_feed = getNextUnreadCat(feed);

					/* we don't need to specify that our next feed is actually
					a category, because we're in the is_cat mode by definition
					already */

					if (next_unread_feed && show_next_feed) {
						query = query + "&nuf=" + param_escape(next_unread_feed);
						feed = next_unread_feed;
					}

				}
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

		Form.enable("main_toolbar_form");

		// for piggybacked counters

		if (tagsAreDisplayed()) {
			query = query + "&omode=lt";
		} else {
			query = query + "&omode=flc";
		}

		console.log(query);

		var container = $("headlinesInnerContainer");

/*		if (container && page_offset == 0 && !isCdmMode()) {
			new Effect.Fade(container, {duration: 1, to: 0.01,
				queue: { position:'end', scope: 'FEEDL-' + feed, limit: 1 } } );
		} */

		var unread_ctr = -1;
		
		if (!is_cat) unread_ctr = get_feed_unread(feed);

		var cache_check = false;

		if (unread_ctr != -1 && !page_offset && !force_nocache && !subop) {

			var cache_prefix = "";
				
			if (is_cat) {
				cache_prefix = "C:";
			} else {
				cache_prefix = "F:";
			}

			cache_check = cache_check_param(cache_prefix + feed, unread_ctr);
			console.log("headline cache check: " + cache_check);
		}

		if (cache_check) {
			var f = $("headlines-frame");

			clean_feed_selections();

			setActiveFeedId(feed, is_cat);
		
			if (!is_cat) {
				var feedr = $("FEEDR-" + feed);
				if (feedr && !feedr.className.match("Selected")) {	
					feedr.className = feedr.className + "Selected";
				} 
			} else {
				var feedr = $("FCAT-" + feed_id);
				if (feedr && !feedr.className.match("Selected")) {	
					feedr.className = feedr.className + "Selected";
				} 
			}

			f.innerHTML = cache_find_param(cache_prefix + feed, unread_ctr);

			request_counters();
			remove_splash();

		} else {

			if (!page_offset) {
				var feedr;

				if (is_cat) {
					feedr = $('FCAP-' + feed);
				} else {
					feedr = $('FEEDR-' + feed);
				}

				if (feedr && !$('FLL-' + feed)) {

					var img = $('FIMG-' + feed);

					if (!is_cat && img) {

						var cat_list = feedr.parentNode;

						if (!cat_list || Element.visible(cat_list)) {
							if (!img.src.match("indicator_white")) {
								img.alt = img.src;
								img.src = getInitParam("sign_progress");
							}
						} else if (cat_list) {
							feed_cat_id = cat_list.id.replace("FCATLIST-", "");

							if (!$('FLL-' + feed_cat_id)) {

								var ll = document.createElement('img');

								ll.src = getInitParam("sign_progress_tiny");
								ll.className = 'hlLoading';
								ll.id = 'FLL-' + feed;

								$("FCAP-" + feed_cat_id).appendChild(ll);
							}
						} 
					
					} else {

						if (!$('FLL-' + feed)) {
							var ll = document.createElement('img');

							ll.src = getInitParam("sign_progress_tiny");
							ll.className = 'hlLoading';
							ll.id = 'FLL-' + feed;
	
							feedr.appendChild(ll);
						}
					}
				} 
			}  

			new Ajax.Request("backend.php", {
				parameters: query,
				onComplete: function(transport) { 
					headlines_callback2(transport, page_offset); 
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
		var cap = $("FCAP-" + cat);

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
	
		var cat_elem = $("FCAT-" + cat);
		var cat_list = $("FCATLIST-" + cat).parentNode;
		var caption = $("FCAP-" + cat);
		
		Effect.toggle('FCATLIST-' + cat, 'blind', { duration: 0.5,
			afterFinish: toggleCollapseCat_af });

		var img = cat_elem.getElementsByTagName("IMG")[0];

		if (img.src.match("-collapse"))
			img.src = img.src.replace("-collapse", "-uncollapse")
		else
			img.src = img.src.replace("-uncollapse", "-collapse")

		new Ajax.Request("backend.php", 
			{ parameters: "backend.php?op=feeds&subop=collapse&cid=" + 
				param_escape(cat) } );

		local_collapse_cat(cat);

	} catch (e) {
		exception_error("toggleCollapseCat", e);
	}
}

function isCatCollapsed(cat) {
	try {
		return Element.visible("FCATLIST-" + cat);
	} catch (e) {
		exception_error("isCatCollapsed", e);
	}
}

function feedlist_dragsorted(ctr) {
	try {
		var elem = $("feedList");

		var cats = elem.getElementsByTagName("LI");
		var ordered_cats = new Array();

		for (var i = 0; i < cats.length; i++) {
			if (cats[i].id && cats[i].id.match("FCAT-")) {
				ordered_cats.push(cats[i].id.replace("FCAT-", ""));
			}
		}

		if (ordered_cats.length > 0) {

			var query = "?op=feeds&subop=catsort&corder=" + 
				param_escape(ordered_cats.toString());

			//console.log(query);

			new Ajax.Request("backend.php", { parameters: query });
		}

	} catch (e) {
		exception_error("feedlist_dragsorted", e);
	}
}

function feedlist_init() {
	try {
		loading_set_progress(90);

		//console.log("in feedlist init");
		
		hideOrShowFeeds(getInitParam("hide_read_feeds") == 1);
		document.onkeydown = hotkey_handler;
		document.onmousemove = mouse_move_handler;
		document.onmousedown = mouse_down_handler;
		document.onmouseup = mouse_up_handler;

		if (!offline_mode) setTimeout("timeout()", 1);

		setTimeout("hotkey_prefix_timeout()", 5*1000);

		if (getActiveFeedId()) {
			//console.log("some feed is open on feedlist refresh, reloading");
			//setTimeout("viewCurrentFeed()", 100);
		} else {
			if (getInitParam("cdm_auto_catchup") != 1 && get_feed_unread(-3) > 0) {
				notify_silent_next();
				setTimeout("viewfeed(-3)", 100);
			} else {
				setTimeout("viewfeed(-5)", 100);
				remove_splash();
			}
		}

		if (getInitParam("theme") == "" || 
				getInitParam("theme_options").match("hide_footer")) {
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
		var c = $("content-frame");

		if (c) {
			c.style.bottom = "0px";

			var ioa = $("inline_orig_article");

			if (ioa) {
				ioa.height = c.offsetHeight;
			}

		} else {
			var h = $("headlines-frame");

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

function init_collapsable_feedlist() {
	try {
		//console.log("init_collapsable_feedlist");

		var theme = getInitParam("theme");
		var options = getInitParam("theme_options");

		if (theme != "" && !options.match("collapse_feedlist")) return;

		var fbtn = $("collapse_feeds_btn");

		if (fbtn) Element.show(fbtn);

		if (getInitParam("collapsed_feedlist") == 1) {
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

function enable_selection(b) {
	selection_disabled = !b;
}

function enable_resize(b) {
	resize_enabled = b;
}

function mouse_down_handler(e) {
	try {

		/* do not prevent right click */
		if (e && e.button && e.button == 2) return;

		if (resize_enabled) { 
			mouse_is_down = true;
			mouse_x = 0;
			mouse_y = 0;
			document.onselectstart = function() { return false; };
			return false;
		}

		if (selection_disabled) {
			document.onselectstart = function() { return false; };
			return false;
		}

	} catch (e) {
		exception_error("mouse_down_handler", e);
	}
}

function mouse_up_handler(e) {
	try {
		mouse_is_down = false;

		if (!selection_disabled) {
			document.onselectstart = null;
			var e = $("headlineActionsBody");
			if (e) Element.hide(e);
			
			var e = $("offlineModeDrop");
			if (e) Element.hide(e);

		}

	} catch (e) {
		exception_error("mouse_up_handler", e);
	}
}

function request_counters_real() {

	try {

		if (offline_mode) return;

		console.log("requesting counters...");

		var query = "?op=rpc&subop=getAllCounters&seq=" + next_seq();

		if (tagsAreDisplayed()) {
			query = query + "&omode=tl";
		} else {
			query = query + "&omode=flc";
		}

		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function(transport) { 
				try {
					handle_rpc_reply(transport);
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

		if (timestamp - counters_last_request > 5) {
			console.log("scheduling request of counters...");

			window.clearTimeout(counter_timeout_id);
			counter_timeout_id = window.setTimeout("request_counters_real()", 1000);

			counters_last_request = timestamp;
		} else {
			console.log("request_counters: rate limit reached: " + (timestamp - counters_last_request));
		}

	} catch (e) {
		exception_error("request_counters", e);
	}
}

function displayNewContentPrompt(id) {
	try {

		var msg = "<a href='#' onclick='viewfeed("+id+")'>" +
			__("New articles available in this feed (click to show)") + "</a>";

		msg = msg.replace("%s", getFeedName(id));

		$('auxDlg').innerHTML = msg;

		new Effect.Appear('auxDlg', {duration : 0.5});

	} catch (e) {
		exception_error("displayNewContentPrompt", e);
	}
}

function parse_counters(reply, scheduled_call) {
	try {

		var feeds_found = 0;

		var elems = JSON.parse(reply.firstChild.nodeValue);

		for (var l = 0; l < elems.length; l++) {

			var id = elems[l].id
			var kind = elems[l].kind;
			var ctr = parseInt(elems[l].counter)
			var error = elems[l].error;
			var has_img = elems[l].has_img;
			var updated = elems[l].updated;
			var title = elems[l].title;
			var xmsg = elems[l].xmsg;
	
			if (id == "global-unread") {

				if (ctr > global_unread) {
					offlineDownloadStart(1);
				}

				global_unread = ctr;
				updateTitle();
				continue;
			}

			if (id == "subscribed-feeds") {
				feeds_found = ctr;
				continue;
			}
	
			if (kind && kind == "cat") {
				var catctr = $("FCATCTR-" + id);
				if (catctr) {
					catctr.innerHTML = "(" + ctr + ")";
					if (ctr > 0) {
						catctr.className = "catCtrHasUnread";
					} else {
						catctr.className = "catCtrNoUnread";
					}
				}
				continue;
			}
		
			var feedctr = $("FEEDCTR-" + id);
			var feedu = $("FEEDU-" + id);
			var feedr = $("FEEDR-" + id);
			var feed_img = $("FIMG-" + id);
			var feedlink = $("FEEDL-" + id);
			var feedupd = $("FLUPD-" + id);

			if (updated && feedlink) {
				if (error) {
					feedlink.title = "Error: " + error + " (" + updated + ")";
				} else {
					feedlink.title = "Updated: " + updated;
				}
			}

			if (feedupd) {
				if (!updated) updated = "";

				if (error) {
					if (xmsg) {
						feedupd.innerHTML = updated + " " + xmsg + " (Error)";
					} else {
						feedupd.innerHTML = updated + " (Error)";
					}
				} else {
					if (xmsg) {
						feedupd.innerHTML = updated + " (" + xmsg + ")";
					} else {
						feedupd.innerHTML = updated;
					}
				}
			}

			if (has_img && feed_img) {
				if (!feed_img.src.match(id + ".ico")) {
					feed_img.src = getInitParam("icons_url") + "/" + id + ".ico";
				}
			}

			if (feedlink && title) {
				feedlink.innerHTML = title;
			}

			if (feedctr && feedu && feedr) {

//				if (id == getActiveFeedId())
//					console.log("HAS CTR: " + feedu.innerHTML + " GOT CTR: " + ctr + 
//							" IS_SCHED: " + scheduled_call);

				if (parseInt(ctr) > 0 && 
						parseInt(feedu.innerHTML) < parseInt(ctr) && 
						id == getActiveFeedId() && scheduled_call) {

					displayNewContentPrompt(id);
				}

				var row_needs_hl = (ctr > 0 && ctr > parseInt(feedu.innerHTML));

				feedu.innerHTML = ctr;

				if (error) {
					feedr.className = feedr.className.replace("feed", "error");
				} else if (id > 0) {
					feedr.className = feedr.className.replace("error", "feed");
				}
	
				if (ctr > 0) {					
					feedctr.className = "feedCtrHasUnread";
					if (!feedr.className.match("Unread")) {
						var is_selected = feedr.className.match("Selected");
		
						feedr.className = feedr.className.replace("Selected", "");
						feedr.className = feedr.className.replace("Unread", "");
		
						feedr.className = feedr.className + "Unread";
		
						if (is_selected) {
							feedr.className = feedr.className + "Selected";
						}	
						
					}

					if (row_needs_hl && 
							!getInitParam("theme_options").match('no_highlights')) { 
						new Effect.Highlight(feedr, {duration: 1, startcolor: "#fff7d5",
							queue: { position:'end', scope: 'EFQ-' + id, limit: 1 } } );

						cache_invalidate("F:" + id);
					}
				} else {
					feedctr.className = "feedCtrNoUnread";
					feedr.className = feedr.className.replace("Unread", "");
				}			
			}
		}

		hideOrShowFeeds(getInitParam("hide_read_feeds") == 1);

		var feeds_stored = number_of_feeds;

		//console.log("Feed counters, C: " + feeds_found + ", S:" + feeds_stored);

		if (feeds_stored != feeds_found) {
			number_of_feeds = feeds_found;

			if (feeds_stored != 0 && feeds_found != 0) {
				console.log("Subscribed feed number changed, refreshing feedlist");
				setTimeout('updateFeedList(false, false)', 50);
			}
		} else {
/*			var fl = $("feeds-frame").innerHTML;
			if (fl) {
				cache_invalidate("FEEDLIST");
				cache_inject("FEEDLIST", fl, getInitParam("num_feeds"));
			} */
		}

	} catch (e) {
		exception_error("parse_counters", e);
	}
}

function get_feed_unread(id) {
	try {
		return parseInt($("FEEDU-" + id).innerHTML);	
	} catch (e) {
		return -1;
	}
}

function get_cat_unread(id) {
	try {
		var ctr = $("FCATCTR-" + id).innerHTML;
		ctr = ctr.replace("(", "");
		ctr = ctr.replace(")", "");
		return parseInt(ctr);
	} catch (e) {
		return -1;
	}
}

function get_feed_entry_unread(elem) {

	var id = elem.id.replace("FEEDR-", "");

	if (id <= 0) {
		return -1;
	}

	try {
		return parseInt($("FEEDU-" + id).innerHTML);	
	} catch (e) {
		return -1;
	}
}

function get_feed_entry_name(elem) {
	var id = elem.id.replace("FEEDR-", "");
	return getFeedName(id);
}


function resort_category(node, cat_mode) {

	try {

		console.log("resort_category: " + node + " CM=" + cat_mode);
	
		var by_unread = feedsSortByUnread();
	
		var list = node.getElementsByTagName("LI");
	
		for (i = 0; i < list.length; i++) {
	
			for (j = i+1; j < list.length; j++) {			
	
				var tmp_val = get_feed_entry_unread(list[i]);
				var cur_val = get_feed_entry_unread(list[j]);
	
				var tmp_name = get_feed_entry_name(list[i]);
				var cur_name = get_feed_entry_name(list[j]);

				/* we don't want to match FEEDR-0 - e.g. Archived articles */

				var valid_pair = cat_mode || (list[i].id.match(/FEEDR-[1-9]/) &&
						list[j].id.match(/FEEDR-[1-9]/));

				if (valid_pair && ((by_unread && (cur_val > tmp_val)) || (!by_unread && (cur_name < tmp_name)))) {
					tempnode_i = list[i].cloneNode(true);
					tempnode_j = list[j].cloneNode(true);
					node.replaceChild(tempnode_i, list[j]);
					node.replaceChild(tempnode_j, list[i]);
				}
			}
		}

	} catch (e) {
		exception_error("resort_category", e);
	}

}

function resort_feedlist() {
	console.log("resort_feedlist");

	if ($("FCATLIST--1")) {

		var lists = document.getElementsByTagName("UL");

		for (var i = 0; i < lists.length; i++) {
			if (lists[i].id && lists[i].id.match("FCATLIST-")) {
				resort_category(lists[i], true);
			}
		}

	} else {
		resort_category($("feedList"), false);
	}
}

function hideOrShowFeeds(hide) {

	try {

	//console.log("hideOrShowFeeds: " + hide);

	if ($("FCATLIST--1")) {

		var lists = document.getElementsByTagName("UL");

		for (var i = 0; i < lists.length; i++) {
			if (lists[i].id && lists[i].id.match("FCATLIST-")) {

				var id = lists[i].id.replace("FCATLIST-", "");
				hideOrShowFeedsCategory(id, hide);
			}
		}

	} else {
		hideOrShowFeedsCategory(null, hide);
	}

	} catch (e) {
		exception_error("hideOrShowFeeds", e);
	}
}

function hideOrShowFeedsCategory(id, hide) {

	try {
	
		var node = null;
		var cat_node = null;

		if (id) {
			node = $("FCATLIST-" + id);
			cat_node = $("FCAT-" + id);
		} else {
			node = $("feedList"); // no categories
		}

	//	console.log("hideOrShowFeedsCategory: " + node + " (" + hide + ")");
	
		var cat_unread = 0;
	
		if (!node) {
			console.warn("hideOrShowFeeds: passed node is null, aborting");
			return;
		}
	
	//	console.log("cat: " + node.id);
	
		if (node.hasChildNodes() && node.firstChild.nextSibling != false) {  
			for (i = 0; i < node.childNodes.length; i++) {
				if (node.childNodes[i].nodeName != "LI") { continue; }
	
				if (node.childNodes[i].style != undefined) {
	
					var has_unread = (node.childNodes[i].className != "feed" &&
						node.childNodes[i].className != "label" && 
						!(!getInitParam("hide_read_shows_special") && 
							node.childNodes[i].className == "virt") && 
						node.childNodes[i].className != "error" && 
						node.childNodes[i].className != "tag");

					var has_error = node.childNodes[i].className.match("error");
		
	//				console.log(node.childNodes[i].id + " --> " + has_unread);
		
					if (hide && !has_unread) {
						var id = node.childNodes[i].id;
						Effect.Fade(node.childNodes[i], {duration : 0.3, 
							queue: { position: 'end', scope: 'FFADE-' + id, limit: 1 }});
					}
		
					if (!hide) {
						Element.show(node.childNodes[i]);
					}
		
					if (has_unread) {
						Element.show(node.childNodes[i]);
						cat_unread++;
					}

					//if (has_error)	Element.hide(node.childNodes[i]);
				}
			}
		}	
	
	//	console.log("end cat: " + node.id + " unread " + cat_unread);

		if (cat_node) {

			if (cat_unread == 0) {
				if (cat_node.style == undefined) {
					console.log("ERROR: supplied cat_node " + cat_node + 
						" has no styles. WTF?");
					return;
				}
				if (hide) {
					//cat_node.style.display = "none";
					Effect.Fade(cat_node, {duration : 0.3, 
						queue: { position: 'end', scope: 'CFADE-' + node.id, limit: 1 }});
				} else {
					cat_node.style.display = "list-item";
				}
			} else {
				try {
					cat_node.style.display = "list-item";
				} catch (e) {
					console.log(e);
				}
			}
		}

//	console.log("unread for category: " + cat_unread);

	} catch (e) {
		exception_error("hideOrShowFeedsCategory", e);
	}
}

function getFeedName(id, is_cat) {	
	var e;

	if (is_cat) {
		e = $("FCATN-" + id);
	} else {
		e = $("FEEDN-" + id);
	}
	if (e) {
		return e.innerHTML.stripTags();
	} else {
		return null;
	}
}

function getNextUnreadCat(id) {
	try {
		var rows = $("feedList").getElementsByTagName("LI");
		var feeds = new Array();

		var unread_only = true;
		var is_cat = true;

		for (var i = 0; i < rows.length; i++) {
			if (rows[i].id.match("FCAT-")) {
				if (rows[i].id == "FCAT-" + id && is_cat || (Element.visible(rows[i]) && Element.visible(rows[i].parentNode))) {

					var cat_id = parseInt(rows[i].id.replace("FCAT-", ""));

					if (cat_id >= 0) {
						if (!unread_only || get_cat_unread(cat_id) > 0) {
							feeds.push(cat_id);
						}
					}
				}
			}
		}

		var idx = feeds.indexOf(id);
		if (idx != -1 && idx < feeds.length) {
			return feeds[idx+1];					
		} else {
			return feeds.shift();
		}

	} catch (e) {
		exception_error("getNextUnreadCat", e);
	}
}

function getRelativeFeedId2(id, is_cat, direction, unread_only) {	
	try {

//		alert(id + " IC: " + is_cat + " D: " + direction + " U: " + unread_only);

		var rows = $("feedList").getElementsByTagName("LI");
		var feeds = new Array();
	
		for (var i = 0; i < rows.length; i++) {
			if (rows[i].id.match("FEEDR-")) {
	
				if (rows[i].id == "FEEDR-" + id && !is_cat || (Element.visible(rows[i]) && Element.visible(rows[i].parentNode))) {
	
					if (!unread_only || 
							(rows[i].className.match("Unread") || rows[i].id == "FEEDR-" + id)) {
						feeds.push(rows[i].id.replace("FEEDR-", ""));
					}
				}
			}

			if (rows[i].id.match("FCAT-")) {
				if (rows[i].id == "FCAT-" + id && is_cat || (Element.visible(rows[i]) && Element.visible(rows[i].parentNode))) {

					var cat_id = parseInt(rows[i].id.replace("FCAT-", ""));

					if (cat_id >= 0) {
						if (!unread_only || get_cat_unread(cat_id) > 0) {
							feeds.push("CAT:"+cat_id);
						}
					}
				}
			}
		}
	
//		alert(feeds.toString());

		if (!id) {
			if (direction == "next") {
				return feeds.shift();
			} else {
				return feeds.pop();
			}
		} else {
			if (direction == "next") {
				if (is_cat) id = "CAT:" + id;
				var idx = feeds.indexOf(id);
				if (idx != -1 && idx < feeds.length) {
					return feeds[idx+1];					
				} else {
					return getRelativeFeedId2(false, is_cat, direction, unread_only);
				}
			} else {
				if (is_cat) id = "CAT:" + id;
				var idx = feeds.indexOf(id);
				if (idx > 0) {
					return feeds[idx-1];
				} else {
					return getRelativeFeedId2(false, is_cat, direction, unread_only);
				}
			}
	
		}

	} catch (e) {
		exception_error("getRelativeFeedId2", e);
	}
}

function clean_feed_selections() {
	try {
		var feeds = $("feedList").getElementsByTagName("LI");

		for (var i = 0; i < feeds.length; i++) {
			if (feeds[i].id && feeds[i].id.match("FEEDR-")) {
				feeds[i].className = feeds[i].className.replace("Selected", "");
			}			
			if (feeds[i].id && feeds[i].id.match("FCAT-")) {
				feeds[i].className = feeds[i].className.replace("Selected", "");
			}
		}
	} catch (e) {
		exception_error("clean_feed_selections", e);
	}
}

function feedsSortByUnread() {
	return feeds_sort_by_unread;
}



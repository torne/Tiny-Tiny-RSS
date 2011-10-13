var _infscroll_disable = 0;
var _infscroll_request_sent = 0;
var _search_query = false;

var counter_timeout_id = false;

var counters_last_request = 0;

function viewCategory(cat) {
	viewfeed(cat, '', true);
	return false;
}

function loadMoreHeadlines() {
	try {
		console.log("loadMoreHeadlines");

		var offset = 0;

		var view_mode = document.forms["main_toolbar_form"].view_mode.value;
		var num_unread = $$("#headlines-frame > div[id*=RROW][class*=Unread]").length;
		var num_all = $$("#headlines-frame > div[id*=RROW]").length;

		// TODO implement marked & published

		if (view_mode == "marked") {
			console.warn("loadMoreHeadlines: marked is not implemented, falling back.");
			offset = num_all;
		} else if (view_mode == "published") {
			console.warn("loadMoreHeadlines: published is not implemented, falling back.");
			offset = num_all;
		} else if (view_mode == "unread") {
			offset = num_unread;
		} else if (view_mode == "adaptive") {
			if (num_unread > 0)
				offset = num_unread;
			else
				offset = num_all;
		} else {
			offset = num_all;
		}

		viewfeed(getActiveFeedId(), '', activeFeedIsCat(), offset);

	} catch (e) {
		exception_error("viewNextFeedPage", e);
	}
}


function viewfeed(feed, subop, is_cat, offset, background) {
	try {
		if (is_cat == undefined)
			is_cat = false;
		else
			is_cat = !!is_cat;

		if (subop == undefined) subop = '';
		if (offset == undefined) offset = 0;
		if (background == undefined) background = false;

		last_requested_article = 0;

		var cached_headlines = false;

		if (feed == getActiveFeedId())
			cache_delete("feed:" + feed + ":" + is_cat);
		else
			cached_headlines = cache_get("feed:" + feed + ":" + is_cat);

		if (offset == 0)
			dijit.byId("content-tabs").selectChild(
				dijit.byId("content-tabs").getChildren()[0]);

		var force_nocache = false;

		if (!background) {
			if (getActiveFeedId() != feed || offset == 0) {
				active_post_id = 0;
				_infscroll_disable = 0;
			}

			if (!offset && !subop && cached_headlines && !background) {
				try {
					render_local_headlines(feed, is_cat, JSON.parse(cached_headlines));
					return;
				} catch (e) {
					console.warn("render_local_headlines failed: " + e);
				}
			}

			if (offset != 0 && !subop) {
				var date = new Date();
				var timestamp = Math.round(date.getTime() / 1000);

				if (_infscroll_request_sent && _infscroll_request_sent + 30 > timestamp) {
					//console.log("infscroll request in progress, aborting");
					return;
				}

				_infscroll_request_sent = timestamp;
			}

			hideAuxDlg();
		}

		Form.enable("main_toolbar_form");

		var toolbar_query = Form.serialize("main_toolbar_form");

		var query = "?op=viewfeed&feed=" + feed + "&" +
			toolbar_query + "&subop=" + param_escape(subop);

		if (!background) {
			if (_search_query) {
				force_nocache = true;
				query = query + "&" + _search_query;
				_search_query = false;
			}

			if (subop == "MarkAllRead") {

				var show_next_feed = getInitParam("on_catchup_show_next_feed") == "1";

				if (show_next_feed) {
					var nuf = getNextUnreadFeed(feed, is_cat);

					if (nuf) {
						var cached_nuf = cache_get("feed:" + nuf + ":false");

						if (cached_nuf) {

							render_local_headlines(nuf, false, JSON.parse(cached_nuf));

							var catchup_query = "?op=rpc&subop=catchupFeed&feed_id=" +
								feed + "&is_cat=" + is_cat;

							console.log(catchup_query);

							new Ajax.Request("backend.php",	{
								parameters: catchup_query,
								onComplete: function(transport) {
									handle_rpc_json(transport);
								} });

							return;
						} else {
							query += "&nuf=" + param_escape(nuf);
						}
					}
				}
			}

			if (offset != 0) {
				query = query + "&skip=" + offset;

				// to prevent duplicate feed titles when showing grouped vfeeds
				if (vgroup_last_feed) {
					query = query + "&vgrlf=" + param_escape(vgroup_last_feed);
				}
			}

			Form.enable("main_toolbar_form");

			if (!offset)
				if (!is_cat) {
					if (!setFeedExpandoIcon(feed, is_cat, 'images/indicator_white.gif'))
						notify_progress("Loading, please wait...", true);
				} else {
					notify_progress("Loading, please wait...", true);
				}
		}

		query += "&cat=" + is_cat;

		console.log(query);

		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function(transport) {
				setFeedExpandoIcon(feed, is_cat, 'images/blank_icon.gif');
				headlines_callback2(transport, offset, background);
			} });

	} catch (e) {
		exception_error("viewfeed", e);
	}
}

function feedlist_init() {
	try {
		console.log("in feedlist init");

		hideOrShowFeeds(getInitParam("hide_read_feeds") == 1);
		document.onkeydown = hotkey_handler;
		setTimeout("hotkey_prefix_timeout()", 5*1000);

		 if (!getActiveFeedId()) {
			setTimeout("viewfeed(-3)", 100);
		}

		console.log("T:" +
				getInitParam("cdm_auto_catchup") + " " + getFeedUnread(-3));

		hideOrShowFeeds(getInitParam("hide_read_feeds") == 1);

		setTimeout("timeout()", 5000);
		setTimeout("precache_headlines_idle()", 3000);

	} catch (e) {
		exception_error("feedlist/init", e);
	}
}

function request_counters_real() {
	try {
		console.log("requesting counters...");

		var query = "?op=rpc&subop=getAllCounters&seq=" + next_seq();

		query = query + "&omode=flc";

		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function(transport) {
				try {
					handle_rpc_json(transport);
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

		var msg = "<a href='#' onclick='viewCurrentFeed()'>" +
			__("New articles available in this feed (click to show)") + "</a>";

		msg = msg.replace("%s", getFeedName(id));

		$('auxDlg').innerHTML = msg;

		new Effect.Appear('auxDlg', {duration : 0.5});

	} catch (e) {
		exception_error("displayNewContentPrompt", e);
	}
}

function parse_counters(elems, scheduled_call) {
	try {

		var feeds_found = 0;

		for (var l = 0; l < elems.length; l++) {

			var id = elems[l].id
			var kind = elems[l].kind;
			var ctr = parseInt(elems[l].counter)
			var error = elems[l].error;
			var has_img = elems[l].has_img;
			var updated = elems[l].updated;
			var title = elems[l].title;

			if (id == "global-unread") {
				global_unread = ctr;
				updateTitle();
				continue;
			}

			if (id == "subscribed-feeds") {
				feeds_found = ctr;
				continue;
			}

			var treeItem;

			// TODO: enable new content notification for categories

			if (!activeFeedIsCat() && id == getActiveFeedId()
					&& ctr > getFeedUnread(id) && scheduled_call) {
				displayNewContentPrompt(id);
			}

			if (getFeedUnread(id, (kind == "cat")) != ctr)
				cache_delete("feed:" + id + ":" + (kind == "cat"));

			setFeedUnread(id, (kind == "cat"), ctr);

			if (kind != "cat") {
				setFeedValue(id, false, 'error', error);
				setFeedValue(id, false, 'updated', updated);

				if (id > 0) {
					if (has_img) {
						setFeedIcon(id, false,
							getInitParam("icons_url") + "/" + id + ".ico");
					} else {
						setFeedIcon(id, false, 'images/blank_icon.gif');
					}
				}
			}
		}

		hideOrShowFeeds(getInitParam("hide_read_feeds") == 1);

	} catch (e) {
		exception_error("parse_counters", e);
	}
}

function getFeedUnread(feed, is_cat) {
	try {
		var tree = dijit.byId("feedTree");

		if (tree && tree.model)
			return tree.model.getFeedUnread(feed, is_cat);

	} catch (e) {
		//
	}

	return -1;
}

function resort_feedlist() {
	console.warn("resort_feedlist: function not implemented");
}

function hideOrShowFeeds(hide) {
	var tree = dijit.byId("feedTree");

	if (tree)
		return tree.hideRead(hide, getInitParam("hide_read_shows_special"));
}

function getFeedName(feed, is_cat) {
	var tree = dijit.byId("feedTree");

	if (tree && tree.model)
		return tree.model.getFeedValue(feed, is_cat, 'name');
}

function getFeedValue(feed, is_cat, key) {
	try {
		var tree = dijit.byId("feedTree");

		if (tree && tree.model)
			return tree.model.getFeedValue(feed, is_cat, key);

	} catch (e) {
		//
	}
	return '';
}

function setFeedUnread(feed, is_cat, unread) {
	try {
		var tree = dijit.byId("feedTree");

		if (tree && tree.model)
			return tree.model.setFeedUnread(feed, is_cat, unread);

	} catch (e) {
		exception_error("setFeedUnread", e);
	}
}

function setFeedValue(feed, is_cat, key, value) {
	try {
		var tree = dijit.byId("feedTree");

		if (tree && tree.model)
			return tree.model.setFeedValue(feed, is_cat, key, value);

	} catch (e) {
		//
	}
}

function selectFeed(feed, is_cat) {
	try {
		var tree = dijit.byId("feedTree");

		if (tree) return tree.selectFeed(feed, is_cat);

	} catch (e) {
		exception_error("selectFeed", e);
	}
}

function setFeedIcon(feed, is_cat, src) {
	try {
		var tree = dijit.byId("feedTree");

		if (tree) return tree.setFeedIcon(feed, is_cat, src);

	} catch (e) {
		exception_error("setFeedIcon", e);
	}
}

function setFeedExpandoIcon(feed, is_cat, src) {
	try {
		var tree = dijit.byId("feedTree");

		if (tree) return tree.setFeedExpandoIcon(feed, is_cat, src);

	} catch (e) {
		exception_error("setFeedIcon", e);
	}
	return false;
}

function getNextUnreadFeed(feed, is_cat) {
	try {
		var tree = dijit.byId("feedTree");
		var nuf = tree.model.getNextUnreadFeed(feed, is_cat);

		if (nuf)
			return tree.model.store.getValue(nuf, 'bare_id');

	} catch (e) {
		exception_error("getNextUnreadFeed", e);
	}
}


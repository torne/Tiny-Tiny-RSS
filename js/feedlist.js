var _infscroll_disable = 0;
var _infscroll_request_sent = 0;
var _search_query = false;
var _viewfeed_last = 0;

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
		var unread_in_buffer = $$("#headlines-frame > div[id*=RROW][class*=Unread]").length;
		var num_all = $$("#headlines-frame > div[id*=RROW]").length;
		var num_unread = getFeedUnread(getActiveFeedId(), activeFeedIsCat());

		// TODO implement marked & published

		if (view_mode == "marked") {
			console.warn("loadMoreHeadlines: marked is not implemented, falling back.");
			offset = num_all;
		} else if (view_mode == "published") {
			console.warn("loadMoreHeadlines: published is not implemented, falling back.");
			offset = num_all;
		} else if (view_mode == "unread") {
			offset = unread_in_buffer;
		} else if (_search_query) {
			offset = num_all;
		} else if (view_mode == "adaptive") {
			if (num_unread > 0)
				offset = unread_in_buffer;
			else
				offset = num_all;
		} else {
			offset = num_all;
		}

		console.log("offset: " + offset);

		viewfeed(getActiveFeedId(), '', activeFeedIsCat(), offset, false, true);

	} catch (e) {
		exception_error("viewNextFeedPage", e);
	}
}


function viewfeed(feed, method, is_cat, offset, background, infscroll_req) {
	try {
		if (is_cat == undefined)
			is_cat = false;
		else
			is_cat = !!is_cat;

		if (method == undefined) method = '';
		if (offset == undefined) offset = 0;
		if (background == undefined) background = false;
		if (infscroll_req == undefined) infscroll_req = false;

		last_requested_article = 0;

		if (feed != getActiveFeedId() || activeFeedIsCat() != is_cat) {
			if (!background && _search_query) _search_query = false;
		}

		if (!background) {
			_viewfeed_last = get_timestamp();

			if (getActiveFeedId() != feed || offset == 0) {
				setActiveArticleId(0);
				_infscroll_disable = 0;
			}

			if (offset != 0 && !method) {
				var timestamp = get_timestamp();

				if (_infscroll_request_sent && _infscroll_request_sent + 30 > timestamp) {
					//console.log("infscroll request in progress, aborting");
					return;
				}

				_infscroll_request_sent = timestamp;
			}
		}

		Form.enable("main_toolbar_form");

		var toolbar_query = Form.serialize("main_toolbar_form");

		var query = "?op=feeds&method=view&feed=" + feed + "&" +
			toolbar_query;

		if (method) {
			query = query + "&m=" + param_escape(method);
		}

		if (!background) {
			if (_search_query) {
				force_nocache = true;
				query = query + "&" + _search_query;
				//_search_query = false;
			}

			if (offset != 0) {
				query = query + "&skip=" + offset;

				// to prevent duplicate feed titles when showing grouped vfeeds
				if (vgroup_last_feed) {
					query = query + "&vgrlf=" + param_escape(vgroup_last_feed);
				}
			} else {
				if (!method && !is_cat && feed == getActiveFeedId()) {
					query = query + "&m=ForceUpdate";
				}
			}

			Form.enable("main_toolbar_form");

			if (!setFeedExpandoIcon(feed, is_cat,
				(is_cat) ? 'images/indicator_tiny.gif' : 'images/indicator_white.gif'))
					notify_progress("Loading, please wait...", true);
		}

		query += "&cat=" + is_cat;

		console.log(query);

		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function(transport) {
				setFeedExpandoIcon(feed, is_cat, 'images/blank_icon.gif');
				headlines_callback2(transport, offset, background, infscroll_req);
			} });

	} catch (e) {
		exception_error("viewfeed", e);
	}
}

function feedlist_init() {
	try {
		console.log("in feedlist init");

		document.onkeydown = hotkey_handler;
		setTimeout("hotkey_prefix_timeout()", 5*1000);

		if (!getActiveFeedId()) {
			viewfeed(-3);
		} else {
 			viewfeed(getActiveFeedId(), '', activeFeedIsCat());
		}

		hideOrShowFeeds(getInitParam("hide_read_feeds") == 1);

		request_counters(true);
		timeout();

	} catch (e) {
		exception_error("feedlist/init", e);
	}
}


function request_counters(force) {
	try {
		var date = new Date();
		var timestamp = Math.round(date.getTime() / 1000);

		if (force || timestamp - counters_last_request > 5) {
			console.log("scheduling request of counters...");

			counters_last_request = timestamp;

			var query = "?op=rpc&method=getAllCounters&seq=" + next_seq();

			if (!force)
				query = query + "&last_article_id=" + getInitParam("last_article_id");

			console.log(query);

			new Ajax.Request("backend.php", {
				parameters: query,
				onComplete: function(transport) {
					try {
						handle_rpc_json(transport);
					} catch (e) {
						exception_error("request_counters", e);
					}
				} });

		} else {
			console.log("request_counters: rate limit reached: " + (timestamp - counters_last_request));
		}

	} catch (e) {
		exception_error("request_counters", e);
	}
}

function parse_counters(elems, scheduled_call) {
	try {
		for (var l = 0; l < elems.length; l++) {

			var id = elems[l].id;
			var kind = elems[l].kind;
			var ctr = parseInt(elems[l].counter);
			var error = elems[l].error;
			var has_img = elems[l].has_img;
			var updated = elems[l].updated;

			if (id == "global-unread") {
				global_unread = ctr;
				updateTitle();
				continue;
			}

			if (id == "subscribed-feeds") {
				feeds_found = ctr;
				continue;
			}

			if (getFeedUnread(id, (kind == "cat")) != ctr ||
					(kind == "cat")) {
			}

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

function getFeedCategory(feed) {
	try {
		var tree = dijit.byId("feedTree");

		if (tree && tree.model)
			return tree.getFeedCategory(feed);

	} catch (e) {
		//
	}

	return false;
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

function catchupCurrentFeed() {
	return catchupFeed(getActiveFeedId(), activeFeedIsCat());
}

function catchupFeedInGroup(id) {
	try {

		var title = getFeedName(id);

		var str = __("Mark all articles in %s as read?").replace("%s", title);

		if (getInitParam("confirm_feed_catchup") != 1 || confirm(str)) {
			return viewCurrentFeed('MarkAllReadGR:' + id);
		}

	} catch (e) {
		exception_error("catchupFeedInGroup", e);
	}
}

function catchupFeed(feed, is_cat) {
	try {
		if (is_cat == undefined) is_cat = false;

		var str = __("Mark all articles in %s as read?");
		var fn = getFeedName(feed, is_cat);

		str = str.replace("%s", fn);

		if (getInitParam("confirm_feed_catchup") == 1 && !confirm(str)) {
			return;
		}

		var max_id = 0;

		if (feed == getActiveFeedId() && is_cat == activeFeedIsCat()) {
			$$("#headlines-frame > div[id*=RROW]").each(
				function(child) {
					var id = parseInt(child.id.replace("RROW-", ""));

					if (id > max_id) max_id = id;
				}
			);
		}

		var catchup_query = "?op=rpc&method=catchupFeed&feed_id=" +
			feed + "&is_cat=" + is_cat + "&max_id=" + max_id;

		console.log(catchup_query);

		notify_progress("Loading, please wait...", true);

		new Ajax.Request("backend.php",	{
			parameters: catchup_query,
			onComplete: function(transport) {
					handle_rpc_json(transport);

					if (feed == getActiveFeedId() && is_cat == activeFeedIsCat()) {

						$$("#headlines-frame > div[id*=RROW][class*=Unread]").each(
							function(child) {
								child.removeClassName("Unread");
							}
						);
					}

					var show_next_feed = getInitParam("on_catchup_show_next_feed") == "1";

					if (show_next_feed) {
						var nuf = getNextUnreadFeed(feed, is_cat);

						if (nuf) {
							viewfeed(nuf, '', is_cat);
						}
					}

					notify("");
				} });

	} catch (e) {
		exception_error("catchupFeed", e);
	}
}

function decrementFeedCounter(feed, is_cat) {
	try {
		var ctr = getFeedUnread(feed, is_cat);

		if (ctr > 0) {
			setFeedUnread(feed, is_cat, ctr - 1);
			global_unread = global_unread - 1;
			updateTitle();

			if (!is_cat) {
				var cat = parseInt(getFeedCategory(feed));

				if (!isNaN(cat)) {
					ctr = getFeedUnread(cat, true);

					if (ctr > 0) {
						setFeedUnread(cat, true, ctr - 1);
					}
				}
			}
		}

	} catch (e) {
		exception_error("decrement_feed_counter", e);
	}
}



var _feed_cur_page = 0;
var _infscroll_disable = 0;
var _infscroll_request_sent = 0;

var counter_timeout_id = false;

var resize_enabled = false;
var counters_last_request = 0;

function viewCategory(cat) {
	viewfeed(cat, '', true);
	return false;
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

		dijit.byId("content-tabs").selectChild(
			dijit.byId("content-tabs").getChildren()[0]);

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

			var show_next_feed = getInitParam("on_catchup_show_next_feed") == "1";

			if (show_next_feed) {
				// TODO: implement show_next_feed handling
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

		console.log(query);

		var container = $("headlinesInnerContainer");

		var unread_ctr = -1;
		
		if (!is_cat) unread_ctr = getFeedUnread(feed);

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

			setActiveFeedId(feed, is_cat);
		
			$("headlines-frame").innerHTML = cache_find_param(cache_prefix + feed, 
				unread_ctr);

			request_counters();
			remove_splash();

		} else {

			if (!is_cat)
				if (!setFeedExpandoIcon(feed, is_cat, 'images/indicator_white.gif'))
					notify_progress("Loading, please wait...", true);

			new Ajax.Request("backend.php", {
				parameters: query,
				onComplete: function(transport) { 
					setFeedExpandoIcon(feed, is_cat, 'images/blank_icon.gif');
					headlines_callback2(transport, page_offset); 
				} });
		}

	} catch (e) {
		exception_error("viewfeed", e);
	}		
}

function feedlist_init() {
	try {
		loading_set_progress(90);

		console.log("in feedlist init");
		
		hideOrShowFeeds(getInitParam("hide_read_feeds") == 1);
		document.onkeydown = hotkey_handler;
		setTimeout("hotkey_prefix_timeout()", 5*1000);

		 if (!getActiveFeedId()) {
			if (getInitParam("cdm_auto_catchup") != 1) {
				setTimeout("viewfeed(-3)", 100);
			} else {
				setTimeout("viewfeed(-5)", 100);
				remove_splash();
			}
		} 

		console.log("T:" + 
				getInitParam("cdm_auto_catchup") + " " + getFeedUnread(-3));

		hideOrShowFeeds(getInitParam("hide_read_feeds") == 1);

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

		var msg = "<a href='#' onclick='viewCurrentFeed()'>" +
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

			if (id == getActiveFeedId() && ctr > getFeedUnread(id) && scheduled_call) {
				displayNewContentPrompt(id);
			}

			setFeedUnread(id, (kind == "cat"), ctr);

			if (kind != "cat") {
				//setFeedValue(id, false, 'error', error);
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

		var feeds_stored = number_of_feeds;

		if (feeds_stored != feeds_found) {
			number_of_feeds = feeds_found;

			if (feeds_stored != 0 && feeds_found != 0) {
				console.log("Subscribed feed number changed, refreshing feedlist");
				setTimeout('updateFeedList()', 50);
			}
		}

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

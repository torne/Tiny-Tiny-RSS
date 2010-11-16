var _feed_cur_page = 0;
var _infscroll_disable = 0;
var _infscroll_request_sent = 0;
var feed_under_pointer = undefined;

var counter_timeout_id = false;

var resize_enabled = false;
var selection_disabled = false;
var counters_last_request = 0;

var feeds_sort_by_unread = false;
var feedlist_sortable_enabled = false;

function toggle_sortable_feedlist(enabled) {
/*	try {

		if (enabled) {
			Sortable.create('feedList', {onChange: feedlist_dragsorted, only: "feedCat"});
		} else {
			Sortable.destroy('feedList');
		}

	} catch (e) {
		exception_error("toggle_sortable_feedlist", e);
	} */
}

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

		console.log(query);

		var container = $("headlinesInnerContainer");

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

			setActiveFeedId(feed, is_cat);
		
			$("headlines-frame").innerHTML = cache_find_param(cache_prefix + feed, 
				unread_ctr);

			request_counters();
			remove_splash();

		} else {

			if (!is_cat)
				setFeedExpandoIcon(feed, is_cat, 'images/indicator_white.gif');

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


function feedlist_dragsorted(ctr) {
	try {
		var cats = $$("#feedList > li[id*=FCAT-]");
		var ordered_cats = [];

		cats.each(function(cat) {
			ordered_cats.push(cat.id.replace("FCAT-", ""));
		});

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

		console.log("in feedlist init");
		
		hideOrShowFeeds(getInitParam("hide_read_feeds") == 1);
		document.onkeydown = hotkey_handler;
		setTimeout("hotkey_prefix_timeout()", 5*1000);

		 if (getActiveFeedId()) {
			//console.log("some feed is open on feedlist refresh, reloading");
			//setTimeout("viewCurrentFeed()", 100);
		} else {
			if (getInitParam("cdm_auto_catchup") != 1) {
				setTimeout("viewfeed(-3)", 100);
			} else {
				setTimeout("viewfeed(-5)", 100);
				remove_splash();
			}
		} 

		console.log("T:" + 
				getInitParam("cdm_auto_catchup") + " " + get_feed_unread(-3));

		toggle_sortable_feedlist(isFeedlistSortable());

	} catch (e) {
		exception_error("feedlist/init", e);
	}
}

function enable_selection(b) {
	selection_disabled = !b;
}

function enable_resize(b) {
	resize_enabled = b;
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
				global_unread = ctr;
				updateTitle();
				continue;
			}

			if (id == "subscribed-feeds") {
				feeds_found = ctr;
				continue;
			}

			var treeItem;

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

function get_feed_unread(feed, is_cat) {
	try {
		if (is_cat) 
			treeItem = treeModel.store._itemsByIdentity['CAT:' + feed];
		else
			treeItem = treeModel.store._itemsByIdentity['FEED:' + feed];

		if (treeItem)
			return treeModel.store.getValue(treeItem, 'unread');

	} catch (e) {
		//
	}

	return -1;
}

function get_cat_unread(id) {
	return get_feed_unread(id, true);
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

		//console.log("resort_category: " + node + " CM=" + cat_mode);
	
		var by_unread = feedsSortByUnread();
	
		var list = node.getElementsByTagName("LI");

		for (i = 0; i < list.length; i++) {
	
			for (j = i+1; j < list.length; j++) {			
	
				var tmp_val = get_feed_entry_unread(list[i]);
				var cur_val = get_feed_entry_unread(list[j]);
				
				//console.log(list[i].id + " vs " + list[j].id);
	
				var tmp_name = get_feed_entry_name(list[i]).toLowerCase();
				var cur_name = get_feed_entry_name(list[j]).toLowerCase();

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
	return;

	console.log("resort_feedlist");

	if ($("FCATLIST--1")) {

		var lists = $$("#feedList ul[id*=FCATLIST]");

		lists.each(function(list) {
				if (list.id != "FCATLIST--1") resort_category(list, true);
			});

	} else {
		resort_category($("feedList"), false);
	}
}

function hideOrShowFeeds(hide) {
	var tree = dijit.byId("feedTree");

	if (!tree) return;

	if (getInitParam("enable_feed_cats")) {

		var cats = tree.model.store._arrayOfTopLevelItems;

		cats.each(function(cat) {
			var cat_unread = hideOrShowFeedsCategory(cat.items, hide);
	
			var id = String(cat.id);
			var node = tree._itemNodesMap[id];
	
			if (node) {
				if (hide && cat_unread == 0) {
					Effect.Fade(node[0].rowNode, {duration : 0.3, 
						queue: { position: 'end', scope: 'FFADE-' + id, limit: 1 }});
				} else {
					Element.show(node[0].rowNode);
					++cat_unread;
				}
			}
	
		});

	} else {
		hideOrShowFeedsCategory(tree.model.store._arrayOfTopLevelItems, hide);
	}

/*	try {

		if ($("FCATLIST--1")) {
	
			var lists = $$("#feedList ul[id*=FCATLIST]");
	
			lists.each(function(list) {
					hideOrShowFeedsCategory(list.id.replace("FCATLIST-", ""), hide);
				});
	
		} else {
			hideOrShowFeedsCategory(null, hide);
		}

	} catch (e) {
		exception_error("hideOrShowFeeds", e);
	} */
}

function hideOrShowFeedsCategory(feeds, hide) {
	try {
		//console.warn("hideOrShowFeedsCategory: function not implemented");
	var tree = dijit.byId("feedTree");

	if (!tree) return;

	var cat_unread = 0;

	feeds.each(function(feed) {
		var id = String(feed.id);
		var bare_id = parseInt(id.substr(id.indexOf(":")+1));

		var unread = feed.unread[0];
		var node = tree._itemNodesMap[id];

		if (node) {
			if (hide && unread == 0 && (bare_id > 0 || !getInitParam("hide_read_shows_special"))) {
				Effect.Fade(node[0].rowNode, {duration : 0.3, 
					queue: { position: 'end', scope: 'FFADE-' + id, limit: 1 }});
			} else {
				Element.show(node[0].rowNode);
				++cat_unread;
			}
		}
	});

	return cat_unread;

/*		var nodes;
		var cat_node;
		
		if (cat_id) {
			nodes = $$("#FCATLIST-" + cat_id + " > li");
			cat_node = $("FCAT-" + cat_id);
		} else {
			nodes = $$("#feedList li");
		}

		var cat_unread = 0;

		nodes.each(function(node) {

			var is_unread = node.hasClassName("Unread") ||
				node.hasClassName("Selected") || 
				(node.hasClassName("virt") && 
				 	getInitParam("hide_read_shows_special"));

			if (hide && !is_unread) {
				Effect.Fade(node, {duration : 0.3, 
					queue: { position: 'end', scope: 'FFADE-' + node.id, limit: 1 }});
			} else {
				Element.show(node);
				++cat_unread;
			}

		});

		if (cat_node) {
			if (hide && cat_unread == 0 && !cat_node.hasClassName("Selected")) {
				Effect.Fade(cat_node, {duration : 0.3, 
					queue: { position: 'end', scope: 'CFADE-' + cat_node.id, limit: 1 }});
			} else {
				Element.show(cat_node);
			}
		} */

	} catch (e) {
		exception_error("hideOrShowFeedsCategory", e);
	}
}

function getFeedName(feed, is_cat) {	
	return getFeedValue(feed, is_cat, 'name');
}

function getFeedValue(feed, is_cat, key) {	

	try {
		if (is_cat) 
			treeItem = treeModel.store._itemsByIdentity['CAT:' + feed];
		else
			treeItem = treeModel.store._itemsByIdentity['FEED:' + feed];

		if (treeItem)
			return treeModel.store.getValue(treeItem, key);

	} catch (e) {
		//
	}

	return '';
}

function getNextUnreadCat(id) {
	try {
		var rows = $$("#feedList li[id*=FCAT]");
		var unread_cats = [];

		rows.each(function(row) {
			var cat_id = row.id.replace("FCAT-", "");

			if (Element.visible(row) && get_cat_unread(cat_id) > 0)
				unread_cats.push(parseInt(cat_id));
			});

		console.log(unread_cats);
		
		var idx = unread_cats.indexOf(id);

		if (idx != -1 && idx < unread_cats.length-1) {
			return unread_cats[idx+1];					
		} else {
			return unread_cats[0];
		}

	} catch (e) {
		exception_error("getNextUnreadCat", e);
	}
}

function getRelativeFeedId2(id, is_cat, direction, unread_only) {	
	try {

//		alert(id + " IC: " + is_cat + " D: " + direction + " U: " + unread_only);

		var rows = $$("#feedList li");
		var feeds = new Array();
	
		for (var i = 0; i < rows.length; i++) {
			if (rows[i].id.match("FEEDR-")) {
	
				if (rows[i].id == "FEEDR-" + id && !is_cat || (Element.visible(rows[i]) && Element.visible(rows[i].parentNode))) {
	
					if (!unread_only || 
							(rows[i].hasClassName("Unread") || rows[i].id == "FEEDR-" + id)) {
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

function feedsSortByUnread() {
	return feeds_sort_by_unread;
}

function setFeedUnread(feed, is_cat, unread) {
	try {
		setFeedValue(feed, is_cat, 'unread', parseInt(unread));
	} catch (e) {
		exception_error("setFeedUnread", e);
	}
}

function setFeedValue(feed, is_cat, key, value) {
	try {
		if (!value) value = '';
		if (!treeModel) return;

		if (is_cat) 
			treeItem = treeModel.store._itemsByIdentity['CAT:' + feed];
		else
			treeItem = treeModel.store._itemsByIdentity['FEED:' + feed];

		if (treeItem)
			treeModel.store.setValue(treeItem, key, value);

	} catch (e) {
		exception_error("setFeedValue", e);
	}
}

function toggleCollapseCat(id) {
	console.warn("toggleCollapseCat: function not implemented");
}

function selectFeed(feed, is_cat) {
	try {
		var tree = dijit.byId("feedTree");

		if (!tree) return;

		if (is_cat) 
			treeNode = tree._itemNodesMap['CAT:' + feed];
		else
			treeNode = tree._itemNodesMap['FEED:' + feed];

		if (treeNode) {
			treeNode = treeNode[0];
			if (!is_cat) tree._expandNode(treeNode);
			tree._selectNode(treeNode);
		}

	} catch (e) {
		exception_error("selectFeed", e);
	}
}

function setFeedIcon(feed, is_cat, src) {
	try {
		var tree = dijit.byId("feedTree");

		if (!tree) return;

		if (is_cat) 
			treeNode = tree._itemNodesMap['CAT:' + feed];
		else
			treeNode = tree._itemNodesMap['FEED:' + feed];

		if (treeNode) {
			treeNode = treeNode[0];
			treeNode.iconNode.src = src;
		}

	} catch (e) {
		exception_error("setFeedIcon", e);
	}
}

function setFeedExpandoIcon(feed, is_cat, src) {
	try {
		var tree = dijit.byId("feedTree");

		if (!tree) return;

		if (is_cat) 
			treeNode = tree._itemNodesMap['CAT:' + feed];
		else
			treeNode = tree._itemNodesMap['FEED:' + feed];

		if (treeNode) {
			treeNode = treeNode[0];
			treeNode.expandoNode.src = src;
		}

	} catch (e) {
		exception_error("setFeedIcon", e);
	}
}

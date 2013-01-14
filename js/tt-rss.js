var total_unread = 0;
var global_unread = -1;
var firsttime_update = true;
var _active_feed_id = undefined;
var _active_feed_is_cat = false;
var hotkey_prefix = false;
var hotkey_prefix_pressed = false;
var _force_scheduled_update = false;
var last_scheduled_update = false;
var _widescreen_mode = false;

var _rpc_seq = 0;

function next_seq() {
	_rpc_seq += 1;
	return _rpc_seq;
}

function get_seq() {
	return _rpc_seq;
}

function activeFeedIsCat() {
	return _active_feed_is_cat;
}

function getActiveFeedId() {
	try {
		//console.log("gAFID: " + _active_feed_id);
		return _active_feed_id;
	} catch (e) {
		exception_error("getActiveFeedId", e);
	}
}

function setActiveFeedId(id, is_cat) {
	try {
		_active_feed_id = id;

		if (is_cat != undefined) {
			_active_feed_is_cat = is_cat;
		}

		selectFeed(id, is_cat);
	} catch (e) {
		exception_error("setActiveFeedId", e);
	}
}


function updateFeedList() {
	try {

//		$("feeds-holder").innerHTML = "<div id=\"feedlistLoading\">" +
//			__("Loading, please wait...") + "</div>";

		Element.show("feedlistLoading");

		if (dijit.byId("feedTree")) {
			dijit.byId("feedTree").destroyRecursive();
		}

		var store = new dojo.data.ItemFileWriteStore({
         url: "backend.php?op=pref_feeds&method=getfeedtree&mode=2"});

		var treeModel = new fox.FeedStoreModel({
			store: store,
			query: {
				"type": getInitParam('enable_feed_cats') == 1 ? "category" : "feed"
			},
			rootId: "root",
			rootLabel: "Feeds",
			childrenAttrs: ["items"]
		});

		var tree = new fox.FeedTree({
		persist: false,
		model: treeModel,
		onOpen: function (item, node) {
			var id = String(item.id);
			var cat_id = id.substr(id.indexOf(":")+1);

			new Ajax.Request("backend.php",
				{ parameters: "backend.php?op=feeds&method=collapse&cid=" +
					param_escape(cat_id) + "&mode=0" } );
	   },
		onClose: function (item, node) {
			var id = String(item.id);
			var cat_id = id.substr(id.indexOf(":")+1);

			new Ajax.Request("backend.php",
				{ parameters: "backend.php?op=feeds&method=collapse&cid=" +
					param_escape(cat_id) + "&mode=1" } );

	   },
		onClick: function (item, node) {
			var id = String(item.id);
			var is_cat = id.match("^CAT:");
			var feed = id.substr(id.indexOf(":")+1);
			viewfeed(feed, '', is_cat);
			return false;
		},
		openOnClick: false,
		showRoot: false,
		id: "feedTree",
		}, "feedTree");

		_force_scheduled_update = true;

/*		var menu = new dijit.Menu({id: 'feedMenu'});

		menu.addChild(new dijit.MenuItem({
          label: "Simple menu item"
		}));

//		menu.bindDomNode(tree.domNode); */

		var tmph = dojo.connect(dijit.byId('feedMenu'), '_openMyself', function (event) {
			console.log(dijit.getEnclosingWidget(event.target));
			dojo.disconnect(tmph);
		});

		$("feeds-holder").appendChild(tree.domNode);

		var tmph = dojo.connect(tree, 'onLoad', function() {
	   	dojo.disconnect(tmph);
			Element.hide("feedlistLoading");

			tree.collapseHiddenCats();

			feedlist_init();

//			var node = dijit.byId("feedTree")._itemNodesMap['FEED:-2'][0].domNode
//			menu.bindDomNode(node);

			loading_set_progress(25);
		});

		tree.startup();

	} catch (e) {
		exception_error("updateFeedList", e);
	}
}

function catchupAllFeeds() {

	var str = __("Mark all articles as read?");

	if (getInitParam("confirm_feed_catchup") != 1 || confirm(str)) {

		var query_str = "backend.php?op=feeds&method=catchupAll";

		notify_progress("Marking all feeds as read...");

		//console.log("catchupAllFeeds Q=" + query_str);

		new Ajax.Request("backend.php", {
			parameters: query_str,
			onComplete: function(transport) {
				feedlist_callback2(transport);
			} });

		global_unread = 0;
		updateTitle("");
	}
}

function viewCurrentFeed(method) {
	console.log("viewCurrentFeed");

	if (getActiveFeedId() != undefined) {
		viewfeed(getActiveFeedId(), method, activeFeedIsCat());
	}
	return false; // block unneeded form submits
}

function timeout() {
	if (getInitParam("bw_limit") == "1") return;

	try {
	   var date = new Date();
      var ts = Math.round(date.getTime() / 1000);

		if (ts - last_scheduled_update > 10 || _force_scheduled_update) {

			//console.log("timeout()");

			window.clearTimeout(counter_timeout_id);

			var query_str = "?op=rpc&method=getAllCounters&seq=" + next_seq();

			var omode;

			if (firsttime_update && !navigator.userAgent.match("Opera")) {
				firsttime_update = false;
				omode = "T";
			} else {
				omode = "flc";
			}

			query_str = query_str + "&omode=" + omode;

			if (!_force_scheduled_update)
				query_str = query_str + "&last_article_id=" + getInitParam("last_article_id");

			//console.log("[timeout]" + query_str);

			new Ajax.Request("backend.php", {
				parameters: query_str,
				onComplete: function(transport) {
						handle_rpc_json(transport, !_force_scheduled_update);
						_force_scheduled_update = false;
					} });

			last_scheduled_update = ts;
		}

	} catch (e) {
		exception_error("timeout", e);
	}

	setTimeout("timeout()", 3000);
}

function search() {
	var query = "backend.php?op=dlg&method=search&param=" +
		param_escape(getActiveFeedId() + ":" + activeFeedIsCat());

	if (dijit.byId("searchDlg"))
		dijit.byId("searchDlg").destroyRecursive();

	dialog = new dijit.Dialog({
		id: "searchDlg",
		title: __("Search"),
		style: "width: 600px",
		execute: function() {
			if (this.validate()) {
				_search_query = dojo.objectToQuery(this.attr('value'));
				this.hide();
				viewCurrentFeed();
			}
		},
		href: query});

	dialog.show();
}

function updateTitle() {
	var tmp = "Tiny Tiny RSS";

	if (global_unread > 0) {
		tmp = tmp + " (" + global_unread + ")";
	}

	if (window.fluid) {
		if (global_unread > 0) {
			window.fluid.dockBadge = global_unread;
		} else {
			window.fluid.dockBadge = "";
		}
	}

	document.title = tmp;
}

function genericSanityCheck() {
	setCookie("ttrss_test", "TEST");

	if (getCookie("ttrss_test") != "TEST") {
		return fatalError(2);
	}

	return true;
}

function init() {
	try {
		//dojo.registerModulePath("fox", "../../js/");

		dojo.require("fox.FeedTree");

		if (typeof themeBeforeLayout == 'function') {
			themeBeforeLayout();
		}

		dojo.require("dijit.ColorPalette");
		dojo.require("dijit.Dialog");
		dojo.require("dijit.form.Button");
		dojo.require("dijit.form.CheckBox");
		dojo.require("dijit.form.DropDownButton");
		dojo.require("dijit.form.FilteringSelect");
		dojo.require("dijit.form.Form");
		dojo.require("dijit.form.RadioButton");
		dojo.require("dijit.form.Select");
		dojo.require("dijit.form.SimpleTextarea");
		dojo.require("dijit.form.TextBox");
		dojo.require("dijit.form.ValidationTextBox");
		dojo.require("dijit.InlineEditBox");
		dojo.require("dijit.layout.AccordionContainer");
		dojo.require("dijit.layout.BorderContainer");
		dojo.require("dijit.layout.ContentPane");
		dojo.require("dijit.layout.TabContainer");
		dojo.require("dijit.Menu");
		dojo.require("dijit.ProgressBar");
		dojo.require("dijit.ProgressBar");
		dojo.require("dijit.Toolbar");
		dojo.require("dijit.Tree");
		dojo.require("dijit.tree.dndSource");
		dojo.require("dojo.data.ItemFileWriteStore");

		dojo.parser.parse();

		if (!genericSanityCheck())
			return false;

		loading_set_progress(20);

		var hasAudio = !!((myAudioTag = document.createElement('audio')).canPlayType);

		new Ajax.Request("backend.php",	{
			parameters: {op: "rpc", method: "sanityCheck", hasAudio: hasAudio},
			onComplete: function(transport) {
					backend_sanity_check_callback(transport);
				} });

	} catch (e) {
		exception_error("init", e);
	}
}

function init_second_stage() {

	try {
		dojo.addOnLoad(function() {
			updateFeedList();
			closeArticlePanel();

			if (typeof themeAfterLayout == 'function') {
				themeAfterLayout();
			}

		});

		delCookie("ttrss_test");

		var toolbar = document.forms["main_toolbar_form"];

		dijit.getEnclosingWidget(toolbar.view_mode).attr('value',
			getInitParam("default_view_mode"));

		dijit.getEnclosingWidget(toolbar.order_by).attr('value',
			getInitParam("default_view_order_by"));

		feeds_sort_by_unread = getInitParam("feeds_sort_by_unread") == 1;

		loading_set_progress(30);

		// can't use cache_clear() here because viewfeed might not have initialized yet
		if ('sessionStorage' in window && window['sessionStorage'] !== null)
			sessionStorage.clear();

		console.log("second stage ok");

	} catch (e) {
		exception_error("init_second_stage", e);
	}
}

function quickMenuGo(opid) {
	try {
		if (opid == "qmcPrefs") {
			gotoPreferences();
		}

		if (opid == "qmcTagCloud") {
			displayDlg("printTagCloud");
		}

		if (opid == "qmcTagSelect") {
			displayDlg("printTagSelect");
		}

		if (opid == "qmcSearch") {
			search();
			return;
		}

		if (opid == "qmcAddFeed") {
			quickAddFeed();
			return;
		}

		if (opid == "qmcDigest") {
			window.location.href = "backend.php?op=digest";
			return;
		}

		if (opid == "qmcEditFeed") {
			if (activeFeedIsCat())
				alert(__("You can't edit this kind of feed."));
			else
				editFeed(getActiveFeedId());
			return;
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

		if (opid == "qmcCatchupAll") {
			catchupAllFeeds();
			return;
		}

		if (opid == "qmcShowOnlyUnread") {
			toggleDispRead();
			return;
		}

		if (opid == "qmcAddFilter") {
			quickAddFilter();
			return;
		}

		if (opid == "qmcAddLabel") {
			addLabel();
			return;
		}

		if (opid == "qmcRescoreFeed") {
			rescoreCurrentFeed();
			return;
		}

		if (opid == "qmcToggleWidescreen") {
			if (!isCdmMode()) {
				_widescreen_mode = !_widescreen_mode;

				switchPanelMode(_widescreen_mode);
			}
			return;
		}

		if (opid == "qmcHKhelp") {
			helpDialog("main");
		}

	} catch (e) {
		exception_error("quickMenuGo", e);
	}
}

function toggleDispRead() {
	try {

		var hide = !(getInitParam("hide_read_feeds") == "1");

		hideOrShowFeeds(hide);

		var query = "?op=rpc&method=setpref&key=HIDE_READ_FEEDS&value=" +
			param_escape(hide);

		setInitParam("hide_read_feeds", hide);

		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function(transport) {
			} });

	} catch (e) {
		exception_error("toggleDispRead", e);
	}
}

function parse_runtime_info(data) {

	//console.log("parsing runtime info...");

	for (k in data) {
		var v = data[k];

//		console.log("RI: " + k + " => " + v);

		if (k == "new_version_available") {
			var icon = $("newVersionIcon");
			if (icon) {
				if (v == "1") {
					icon.style.display = "inline";
				} else {
					icon.style.display = "none";
				}
			}
			return;
		}

		if (k == "daemon_is_running" && v != 1) {
			notify_error("<span onclick=\"javascript:explainError(1)\">Update daemon is not running.</span>", true);
			return;
		}

		if (k == "daemon_stamp_ok" && v != 1) {
			notify_error("<span onclick=\"javascript:explainError(3)\">Update daemon is not updating feeds.</span>", true);
			return;
		}

		if (k == "max_feed_id" || k == "num_feeds") {
			if (init_params[k] != v) {
				console.log("feed count changed, need to reload feedlist.");
				updateFeedList();
			}
		}

		init_params[k] = v;
		notify('');
	}
}

function collapse_feedlist() {
	try {

		if (!Element.visible('feeds-holder')) {
			Element.show('feeds-holder');
			Element.show('feeds-holder_splitter');
			$("collapse_feeds_btn").innerHTML = "&lt;&lt;";
		} else {
			Element.hide('feeds-holder');
			Element.hide('feeds-holder_splitter');
			$("collapse_feeds_btn").innerHTML = "&gt;&gt;";
		}

		dijit.byId("main").resize();

		query = "?op=rpc&method=setpref&key=_COLLAPSED_FEEDLIST&value=true";
		new Ajax.Request("backend.php", { parameters: query });

	} catch (e) {
		exception_error("collapse_feedlist", e);
	}
}

function viewModeChanged() {
	cache_clear();
	return viewCurrentFeed('');
}

function viewLimitChanged() {
	return viewCurrentFeed('');
}

/* function adjustArticleScore(id, score) {
	try {

		var pr = prompt(__("Assign score to article:"), score);

		if (pr != undefined) {
			var query = "?op=rpc&method=setScore&id=" + id + "&score=" + pr;

			new Ajax.Request("backend.php",	{
			parameters: query,
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

	if (activeFeedIsCat() || actid < 0) {
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

		var query = "?op=pref-feeds&method=rescore&quiet=1&ids=" + actid;

		new Ajax.Request("backend.php",	{
			parameters: query,
			onComplete: function(transport) {
				viewCurrentFeed();
			} });
	}
}

function hotkey_handler(e) {
	try {

		if (e.target.nodeName == "INPUT" || e.target.nodeName == "TEXTAREA") return;

		var keycode = false;
		var shift_key = false;

		var cmdline = $('cmdline');

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
			hotkey_prefix = false;
		}

		if (keycode == 16) return; // ignore lone shift
		if (keycode == 17) return; // ignore lone ctrl

		if (!shift_key) keychar = keychar.toLowerCase();

		var hotkeys = getInitParam("hotkeys");

		if (!hotkey_prefix && hotkeys[0].indexOf(keychar) != -1) {

			var date = new Date();
			var ts = Math.round(date.getTime() / 1000);

			hotkey_prefix = keychar;
			hotkey_prefix_pressed = ts;

			cmdline.innerHTML = keychar;
			Element.show(cmdline);

			return true;
		}

		Element.hide(cmdline);

		var hotkey = keychar.search(/[a-zA-Z0-9]/) != -1 ? keychar : "(" + keycode + ")";
		hotkey = hotkey_prefix ? hotkey_prefix + " " + hotkey : hotkey;
		hotkey_prefix = false;

		var hotkey_action = false;
		var hotkeys = getInitParam("hotkeys");

		for (sequence in hotkeys[1]) {
			if (sequence == hotkey) {
				hotkey_action = hotkeys[1][sequence];
				break;
			}
		}

		switch (hotkey_action) {
		case "next_feed":
			var rv = dijit.byId("feedTree").getNextFeed(
					getActiveFeedId(), activeFeedIsCat());

			if (rv) viewfeed(rv[0], '', rv[1]);
			return false;
		case "prev_feed":
			var rv = dijit.byId("feedTree").getPreviousFeed(
					getActiveFeedId(), activeFeedIsCat());

			if (rv) viewfeed(rv[0], '', rv[1]);
			return false;
		case "next_article":
			moveToPost('next');
			return false;
		case "prev_article":
			moveToPost('prev');
			return false;
		case "search_dialog":
			search();
			return ;
		case "toggle_mark":
			selectionToggleMarked(undefined, false, true);
			return false;
		case "toggle_publ":
			selectionTogglePublished(undefined, false, true);
			return false;
		case "toggle_unread":
			selectionToggleUnread(undefined, false, true);
			return false;
		case "edit_tags":
			var id = getActiveArticleId();
			if (id) {
				editArticleTags(id, getActiveFeedId(), isCdmMode());
				return;
			}
			return false;
		case "dismiss_selected":
			dismissSelectedArticles();
			return false;
		case "dismiss_read":
			return false;
		case "open_in_new_window":
			if (getActiveArticleId()) {
				openArticleInNewWindow(getActiveArticleId());
				return;
			}
			return false;
		case "catchup_below":
			catchupRelativeToArticle(1);
			return false;
		case "catchup_above":
			catchupRelativeToArticle(0);
			return false;
		case "article_scroll_down":
			scrollArticle(50);
			return false;
		case "article_scroll_up":
			scrollArticle(-50);
			return false;
		case "email_article":
			if (typeof emailArticle != "undefined") {
				emailArticle();
			} else {
				alert(__("Please enable mail plugin first."));
			}
			return false;
		case "select_all":
			selectArticles('all');
			return false;
		case "select_unread":
			selectArticles('unread');
			return false;
		case "select_marked":
			selectArticles('marked');
			return false;
		case "select_published":
			selectArticles('published');
			return false;
		case "select_invert":
			selectArticles('invert');
			return false;
		case "select_none":
			selectArticles('none');
			return false;
		case "feed_refresh":
			if (getActiveFeedId() != undefined) {
				viewfeed(getActiveFeedId(), '', activeFeedIsCat());
				return;
			}
			return false;
		case "feed_unhide_read":
			toggleDispRead();
			return false;
		case "feed_subscribe":
			quickAddFeed();
			return false;
		case "feed_debug_update":
			window.open("backend.php?op=feeds&method=view&feed=" + getActiveFeedId() +
				"&view_mode=adaptive&order_by=default&update=&m=ForceUpdate&cat=" +
				activeFeedIsCat() + "&DevForceUpdate=1&debug=1&xdebug=1&csrf_token=" +
				getInitParam("csrf_token"));
			return false;
		case "feed_edit":
			if (activeFeedIsCat())
				alert(__("You can't edit this kind of feed."));
			else
				editFeed(getActiveFeedId());
			return false;
		case "feed_catchup":
			if (getActiveFeedId() != undefined) {
				catchupCurrentFeed();
				return;
			}
			return false;
		case "feed_reverse":
			reverseHeadlineOrder();
			return false;
		case "catchup_all":
			catchupAllFeeds();
			return false;
		case "cat_toggle_collapse":
			if (activeFeedIsCat()) {
				dijit.byId("feedTree").collapseCat(getActiveFeedId());
				return;
			}
			return false;
		case "goto_all":
			viewfeed(-4);
			return false;
		case "goto_fresh":
			viewfeed(-3);
			return false;
		case "goto_marked":
			viewfeed(-1);
			return false;
		case "goto_published":
			viewfeed(-2);
			return false;
		case "goto_tagcloud":
			displayDlg("printTagCloud");
			return false;
		case "goto_prefs":
			gotoPreferences();
			return false;
		case "select_article_cursor":
			var id = getArticleUnderPointer();
			if (id) {
				var cb = dijit.byId("RCHK-" + id);
				if (cb) {
					cb.attr("checked", !cb.attr("checked"));
					toggleSelectRowById(cb, "RROW-" + id);
					return false;
				}
			}
			return false;
		case "create_label":
			addLabel();
			return false;
		case "create_filter":
			quickAddFilter();
			return false;
		case "collapse_sidebar":
			collapse_feedlist();
			return false;
		case "toggle_widescreen":
			if (!isCdmMode()) {
				_widescreen_mode = !_widescreen_mode;

				switchPanelMode(_widescreen_mode);
			}
			return false;
		case "help_dialog":
			helpDialog("main");
			return false;
		default:
			console.log("unhandled action: " + hotkey_action + "; hotkey: " + hotkey);
		}

	} catch (e) {
		exception_error("hotkey_handler", e);
	}
}

function inPreferences() {
	return false;
}

function reverseHeadlineOrder() {
	try {

		var query_str = "?op=rpc&method=togglepref&key=REVERSE_HEADLINES";

		new Ajax.Request("backend.php", {
			parameters: query_str,
			onComplete: function(transport) {
					viewCurrentFeed();
				} });

	} catch (e) {
		exception_error("reverseHeadlineOrder", e);
	}
}

function newVersionDlg() {
	try {
		var query = "backend.php?op=dlg&method=newVersion";

		if (dijit.byId("newVersionDlg"))
			dijit.byId("newVersionDlg").destroyRecursive();

		dialog = new dijit.Dialog({
			id: "newVersionDlg",
			title: __("New version available!"),
			style: "width: 600px",
			href: query,
		});

		dialog.show();

	} catch (e) {
		exception_error("newVersionDlg", e);
	}
}

function handle_rpc_json(transport, scheduled_call) {
	try {
		var reply = JSON.parse(transport.responseText);

		if (reply) {

			var error = reply['error'];

			if (error) {
				var code = error['code'];
				var msg = error['msg'];

				console.warn("[handle_rpc_json] received fatal error " + code + "/" + msg);

				if (code != 0) {
					fatalError(code, msg);
					return false;
				}
			}

			var seq = reply['seq'];

			if (seq) {
				if (get_seq() != seq) {
					console.log("[handle_rpc_json] sequence mismatch: " + seq +
						" (want: " + get_seq() + ")");
					return true;
				}
			}

			var message = reply['message'];

			if (message) {
				if (message == "UPDATE_COUNTERS") {
					console.log("need to refresh counters...");
					setInitParam("last_article_id", -1);
					_force_scheduled_update = true;
				}
			}

			var counters = reply['counters'];

			if (counters)
				parse_counters(counters, scheduled_call);

			var runtime_info = reply['runtime-info'];;

			if (runtime_info)
				parse_runtime_info(runtime_info);

			hideOrShowFeeds(getInitParam("hide_read_feeds") == 1);

			Element.hide("net-alert");

		} else {
			//notify_error("Error communicating with server.");
			Element.show("net-alert");
		}

	} catch (e) {
		Element.show("net-alert");
		//notify_error("Error communicating with server.");
		console.log(e);
		//exception_error("handle_rpc_json", e, transport);
	}

	return true;
}

function switchPanelMode(wide) {
	try {
		article_id = getActiveArticleId();

		if (wide) {
			dijit.byId("headlines-wrap-inner").attr("design", 'sidebar');
			dijit.byId("content-insert").attr("region", "trailing");

	  		dijit.byId("content-insert").domNode.setStyle({width: '50%',
				height: 'auto',
				'border-top-width': '0px' });

			$("headlines-toolbar").setStyle({ 'border-bottom-width': '0px' });

		} else {

			dijit.byId("content-insert").attr("region", "bottom");

	  		dijit.byId("content-insert").domNode.setStyle({width: 'auto',
				height: '50%',
				'border-top-width': '1px'});

			$("headlines-toolbar").setStyle({ 'border-bottom-width': '1px' });
		}

		closeArticlePanel();

		if (article_id) view(article_id);

	} catch (e) {
		exception_error("switchPanelMode", e);
	}
}

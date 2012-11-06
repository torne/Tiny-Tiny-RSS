var total_unread = 0;
var global_unread = -1;
var firsttime_update = true;
var _active_feed_id = undefined;
var _active_feed_is_cat = false;
var hotkey_prefix = false;
var hotkey_prefix_pressed = false;
var _force_scheduled_update = false;
var last_scheduled_update = false;

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
			window.location.href = "digest.php";
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

		if (opid == "qmcHKhelp") {
			new Ajax.Request("backend.php", {
				parameters: "?op=backend&method=help&topic=main",
				onComplete: function(transport) {
					$("hotkey_help_overlay").innerHTML = transport.responseText;
					Effect.Appear("hotkey_help_overlay", {duration : 0.3});
				} });
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
			if (Element.visible("hotkey_help_overlay")) {
				Element.hide("hotkey_help_overlay");
			}
			hotkey_prefix = false;
		}

		if (keycode == 16) return; // ignore lone shift
		if (keycode == 17) return; // ignore lone ctrl

		if ((keycode == 70 || keycode == 67 || keycode == 71 || keycode == 65)
				&& !hotkey_prefix) {

			var date = new Date();
			var ts = Math.round(date.getTime() / 1000);

			hotkey_prefix = keycode;
			hotkey_prefix_pressed = ts;

			cmdline.innerHTML = keychar;
			Element.show(cmdline);

			console.log("KP: PREFIX=" + keycode + " CHAR=" + keychar + " TS=" + ts);
			return true;
		}

		if (Element.visible("hotkey_help_overlay")) {
			Element.hide("hotkey_help_overlay");
		}

		/* Global hotkeys */

		Element.hide(cmdline);

		if (!hotkey_prefix) {

			if (keycode == 27) { // escape
				closeArticlePanel();
				return;
			}

			if (keycode == 69) { // e
				emailArticle();
			}

			if ((keycode == 191 || keychar == '?') && shift_key) { // ?

				new Ajax.Request("backend.php", {
					parameters: "?op=backend&method=help&topic=main",
					onComplete: function(transport) {
						$("hotkey_help_overlay").innerHTML = transport.responseText;
						Effect.Appear("hotkey_help_overlay", {duration : 0.3});
					} });
				return false;
			}

			if (keycode == 191 || keychar == '/') { // /
				search();
				return false;
			}

			if (keycode == 74 && !shift_key) { // j
				var rv = dijit.byId("feedTree").getPreviousFeed(
						getActiveFeedId(), activeFeedIsCat());

				if (rv) viewfeed(rv[0], '', rv[1]);

				return;
			}

			if (keycode == 75) { // k
				var rv = dijit.byId("feedTree").getNextFeed(
						getActiveFeedId(), activeFeedIsCat());

				if (rv) viewfeed(rv[0], '', rv[1]);

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

			if (keycode == 68 && shift_key) { // shift-D
				dismissSelectedArticles();
				return;
			}

			if (keycode == 88 && shift_key) { // shift-X
				dismissReadArticles();
				return;
			}

			if (keycode == 78 || keycode == 40) { // n, down
				if (typeof moveToPost != 'undefined') {
					moveToPost('next');
					return false;
				}
			}

			if (keycode == 80 || keycode == 38) { // p, up
				if (typeof moveToPost != 'undefined') {
					moveToPost('prev');
					return false;
				}
			}

			if (keycode == 83 && shift_key) { // S
				selectionTogglePublished(undefined, false, true);
				return;
			}

			if (keycode == 83) { // s
				selectionToggleMarked(undefined, false, true);
				return;
			}

			if (keycode == 85) { // u
				selectionToggleUnread(undefined, false, true);
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
					var cb = $("RCHK-" + id);

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

			if (keycode == 88 && !shift_key) { // x
				if (activeFeedIsCat()) {
					dijit.byId("feedTree").collapseCat(getActiveFeedId());
					return;
				}
			}
		}

		/* Prefix a */

		if (hotkey_prefix == 65) { // a
			hotkey_prefix = false;

			if (keycode == 65) { // a
				selectArticles('all');
				return;
			}

			if (keycode == 85 && !shift_key) { // u
				selectArticles('unread');
				return;
			}

			if (keycode == 80) { // p
				selectArticles('published');
				return;
			}

			if (keycode == 85 && shift_key) { // u
				selectArticles('marked');
				return;
			}

			if (keycode == 73) { // i
				selectArticles('invert');
				return;
			}

			if (keycode == 78) { // n
				selectArticles('none');
				return;
			}

		}

		/* Prefix f */

		if (hotkey_prefix == 70) { // f

			hotkey_prefix = false;

			if (keycode == 81) { // q
				if (getActiveFeedId() != undefined) {
					catchupCurrentFeed();
					return;
				}
			}

			if (keycode == 82) { // r
				if (getActiveFeedId() != undefined) {
					viewfeed(getActiveFeedId(), '', activeFeedIsCat());
					return;
				}
			}

			if (keycode == 65) { // a
				toggleDispRead();
				return false;
			}

			if (keycode == 85) { // u
				if (getActiveFeedId() != undefined) {
					viewfeed(getActiveFeedId(), '');
					return false;
				}
			}

			if (keycode == 69) { // e

				if (activeFeedIsCat())
					alert(__("You can't edit this kind of feed."));
				else
					editFeed(getActiveFeedId());
				return;

				return false;
			}

			if (keycode == 83) { // s
				quickAddFeed();
				return false;
			}

			if (keycode == 67 && shift_key) { // C
				if (typeof catchupAllFeeds != 'undefined') {
					catchupAllFeeds();
					return false;
				}
			}

			if (keycode == 67) { // c
				if (getActiveFeedId() != undefined) {
					catchupCurrentFeed();
					return false;
				}
			}

			if (keycode == 88) { // x
				reverseHeadlineOrder();
				return;
			}
		}

		/* Prefix c */

		if (hotkey_prefix == 67) { // c
			hotkey_prefix = false;

			if (keycode == 70) { // f
				quickAddFilter();
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
				// TODO: sortable feedlist
				return;
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

			if (keycode == 84) { // t
				displayDlg("printTagCloud");
				return false;
			}
		}

		/* Cmd */

		if (hotkey_prefix == 224 || hotkey_prefix == 91) { // f
			hotkey_prefix = false;
			return;
		}

		if (hotkey_prefix) {
			console.log("KP: PREFIX=" + hotkey_prefix + " CODE=" + keycode + " CHAR=" + keychar);
		} else {
			console.log("KP: CODE=" + keycode + " CHAR=" + keychar);
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

		} else {
			notify_error("Error communicating with server.");
		}

	} catch (e) {
		notify_error("Error communicating with server.");
		console.log(e);
		//exception_error("handle_rpc_json", e, transport);
	}

	return true;
}


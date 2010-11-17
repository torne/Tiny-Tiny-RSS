var total_unread = 0;
var global_unread = -1;
var firsttime_update = true;
var _active_feed_id = 0;
var _active_feed_is_cat = false;
var number_of_feeds = 0;
var hotkey_prefix = false;
var hotkey_prefix_pressed = false;
var init_params = {};
var _force_scheduled_update = false;
var last_scheduled_update = false;
var treeModel;

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


function dlg_frefresh_callback(transport, deleted_feed) {
	if (getActiveFeedId() == deleted_feed) {
		setTimeout("viewfeed(-5)", 100);
	}

	setTimeout('updateFeedList()', 50);
	closeInfoBox();
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
         url: "backend.php?op=feeds"});

		treeModel = new dijit.tree.ForestStoreModel({
			store: store,
			query: {
				"type": "feed"
			},
			rootId: "root",
			rootLabel: "Feeds",
			childrenAttrs: ["items"]
		});

		var tree = new dijit.Tree({
		model: treeModel,
		_createTreeNode: function(args) {
			var tnode = new dijit._TreeNode(args);

			if (args.item.icon)
				tnode.iconNode.src = args.item.icon[0];

			//tnode.labelNode.innerHTML = args.label;
			return tnode;
			},
		getIconClass: function (item, opened) {
			return (!item || this.model.mayHaveChildren(item)) ? (opened ? "dijitFolderOpened" : "dijitFolderClosed") : "feedIcon";
		},
		getLabelClass: function (item, opened) {
			return (item.unread == 0) ? "dijitTreeLabel" : "dijitTreeLabel Unread";
		},
		getRowClass: function (item, opened) {
			return (!item.error || item.error == '') ? "dijitTreeRow" : 
				"dijitTreeRow Error";
		},
		getLabel: function(item) {
			if (item.unread > 0) {
				return item.name + " (" + item.unread + ")";
			} else {
				return item.name;
			}
		},
		onOpen: function (item, node) {
			var id = String(item.id);
			var cat_id = id.substr(id.indexOf(":")+1);

			new Ajax.Request("backend.php", 
				{ parameters: "backend.php?op=feeds&subop=collapse&cid=" + 
					param_escape(cat_id) + "&mode=1" } );
	   },
		onClose: function (item, node) {
			var id = String(item.id);
			var cat_id = id.substr(id.indexOf(":")+1);

			new Ajax.Request("backend.php", 
				{ parameters: "backend.php?op=feeds&subop=collapse&cid=" + 
					param_escape(cat_id) + "&mode=0" } );

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

		$("feeds-holder").appendChild(tree.domNode);

		var tmph = dojo.connect(tree, 'onLoad', function() {
	   	dojo.disconnect(tmph);
			Element.hide("feedlistLoading");
		});

		tree.startup();

	} catch (e) {
		exception_error("updateFeedList", e);
	}
}

function catchupAllFeeds() {

	var str = __("Mark all articles as read?");

	if (getInitParam("confirm_feed_catchup") != 1 || confirm(str)) {

		var query_str = "backend.php?op=feeds&subop=catchupAll";

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

function viewCurrentFeed(subop) {

	if (getActiveFeedId() != undefined) {
		viewfeed(getActiveFeedId(), subop, activeFeedIsCat());
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
		
			var query_str = "?op=rpc&subop=getAllCounters&seq=" + next_seq();
		
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
						handle_rpc_reply(transport, !_force_scheduled_update);
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
	closeInfoBox();	
	viewCurrentFeed();
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
		fatalError(2);
	}

	return true;
}

function init() {
	try {
		Form.disable("main_toolbar_form");

		dojo.require("dijit.layout.BorderContainer");
		dojo.require("dijit.layout.TabContainer");
		dojo.require("dijit.layout.ContentPane");
		dojo.require("dijit.Dialog");
		dojo.require("dijit.form.Button");
		dojo.require("dojo.data.ItemFileWriteStore");
		dojo.require("dijit.Tree");
		dojo.require("dijit.form.Select");
		dojo.require("dojo.parser");

		if (typeof themeBeforeLayout == 'function') {
			themeBeforeLayout();
		}

		dojo.addOnLoad(function() {
			updateFeedList();
			closeArticlePanel();

			if (typeof themeAfterLayout == 'function') {
				themeAfterLayout();
			}

		});

		if (!genericSanityCheck()) 
			return;

		var params = "&ua=" + param_escape(navigator.userAgent);

		loading_set_progress(30);

		new Ajax.Request("backend.php",	{
			parameters: "backend.php?op=rpc&subop=sanityCheck" + params,
			onComplete: function(transport) {
					backend_sanity_check_callback(transport);
				} });

	} catch (e) {
		exception_error("init", e);
	}
}

function init_second_stage() {

	try {

		delCookie("ttrss_test");

		var toolbar = document.forms["main_toolbar_form"];

		dropboxSelect(toolbar.view_mode, getInitParam("default_view_mode"));
		dropboxSelect(toolbar.order_by, getInitParam("default_view_order_by"));

		feeds_sort_by_unread = getInitParam("feeds_sort_by_unread") == 1;

		loading_set_progress(60);

		if (has_local_storage())
			localStorage.clear();

		feedlist_init();
		setTimeout("timeout()", 3000);

		console.log("second stage ok");

	} catch (e) {
		exception_error("init_second_stage", e);
	}
}

function quickMenuChange() {
	var chooser = $("quickMenuChooser");
	var opid = chooser[chooser.selectedIndex].value;

	chooser.selectedIndex = 0;
	quickMenuGo(opid);
}

function quickMenuGo(opid) {
	try {

		if (opid == "qmcPrefs") {
			gotoPreferences();
		}
	
		if (opid == "qmcTagCloud") {
			displayDlg("printTagCloud");
		}

		if (opid == "qmcSearch") {
			displayDlg("search", getActiveFeedId() + ":" + activeFeedIsCat(), 
				function() { 
					document.forms['search_form'].query.focus();
				});
			return;
		}
	
		if (opid == "qmcAddFeed") {
			quickAddFeed();
			return;
		}

		if (opid == "qmcEditFeed") {
			editFeedDlg(getActiveFeedId());
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
			displayDlg('quickAddFilter', '',
			   function () {document.forms['filter_add_form'].reg_exp.focus();});
		}

		if (opid == "qmcAddLabel") {
			addLabel();
		}

		if (opid == "qmcRescoreFeed") {
			rescoreCurrentFeed();
		}

		if (opid == "qmcHKhelp") {
			//Element.show("hotkey_help_overlay");
			Effect.Appear("hotkey_help_overlay", {duration : 0.3});
		}

	} catch (e) {
		exception_error("quickMenuGo", e);
	}
}

function toggleDispRead() {
	try {

		var hide = !(getInitParam("hide_read_feeds") == "1");

		hideOrShowFeeds(hide);

		var query = "?op=rpc&subop=setpref&key=HIDE_READ_FEEDS&value=" + 
			param_escape(hide);

		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function(transport) { 
				setInitParam("hide_read_feeds", hide);
			} });
				
	} catch (e) {
		exception_error("toggleDispRead", e);
	}
}

function parse_runtime_info(elem) {

	if (!elem || !elem.firstChild) {
		console.warn("parse_runtime_info: invalid node passed");
		return;
	}

	var data = JSON.parse(elem.firstChild.nodeValue);

	//console.log("parsing runtime info...");

	for (k in data) {
		var v = data[k];

		// console.log("RI: " + k + " => " + v);

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

		var error_flag;

		if (k == "daemon_is_running" && v != 1) {
			notify_error("<span onclick=\"javascript:explainError(1)\">Update daemon is not running.</span>", true);
			return;
		}

		if (k == "daemon_stamp_ok" && v != 1) {
			notify_error("<span onclick=\"javascript:explainError(3)\">Update daemon is not updating feeds.</span>", true);
			return;
		}

		init_params[k] = v;					
		notify('');
	}
}

function catchupCurrentFeed() {

	var fn = getFeedName(getActiveFeedId(), activeFeedIsCat());
	
	var str = __("Mark all articles in %s as read?").replace("%s", fn);

	if (getInitParam("confirm_feed_catchup") != 1 || confirm(str)) {
		return viewCurrentFeed('MarkAllRead')
	}
}

function catchupFeedInGroup(id) {

	try {

		var title = getFeedName(id);

		var str = __("Mark all articles in %s as read?").replace("%s", title);

		if (getInitParam("confirm_feed_catchup") != 1 || confirm(str)) {
			return viewCurrentFeed('MarkAllReadGR:' + id)
		}

	} catch (e) {
		exception_error("catchupFeedInGroup", e);
	}
}

function editFeedDlg(feed) {
	try {

		if (!feed) {
			alert(__("Please select some feed first."));
			return;
		}
	
		if ((feed <= 0) || activeFeedIsCat()) {
			alert(__("You can't edit this kind of feed."));
			return;
		}
	
		var query = "";
	
		if (feed > 0) {
			query = "?op=pref-feeds&subop=editfeed&id=" +	param_escape(feed);
		} else {
			query = "?op=pref-labels&subop=edit&id=" +	param_escape(-feed-11);
		}

		disableHotkeys();

		notify_progress("Loading, please wait...", true);

		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function(transport) { 
				infobox_callback2(transport); 
				document.forms["edit_feed_form"].title.focus();
			} });

	} catch (e) {
		exception_error("editFeedDlg", e);
	}
}

/* this functions duplicate those of prefs.js feed editor, with
	some differences because there is no feedlist */

function feedEditCancel() {
	closeInfoBox();
	return false;
}

function feedEditSave() {

	try {
	
		// FIXME: add parameter validation

		var query = Form.serialize("edit_feed_form");

		notify_progress("Saving feed...");

		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function(transport) { 
				dlg_frefresh_callback(transport); 
			} });

		cache_flush();
		closeInfoBox();

		return false;

	} catch (e) {
		exception_error("feedEditSave (main)", e);
	} 
}

function collapse_feedlist() {
	try {

		if (!Element.visible('feeds-holder')) {
			Element.show('feeds-holder');
			$("collapse_feeds_btn").innerHTML = "&lt;&lt;";
		} else {
			Element.hide('feeds-holder');
			$("collapse_feeds_btn").innerHTML = "&gt;&gt;";
		}

		dijit.byId("main").resize();

		query = "?op=rpc&subop=setpref&key=_COLLAPSED_FEEDLIST&value=true";
		new Ajax.Request("backend.php", { parameters: query });

	} catch (e) {
		exception_error("collapse_feedlist", e);
	}
}

function viewModeChanged() {
	cache_flush();
	return viewCurrentFeed('')
}

function viewLimitChanged() {
	cache_flush();
	return viewCurrentFeed('')
}

/* function adjustArticleScore(id, score) {
	try {

		var pr = prompt(__("Assign score to article:"), score);

		if (pr != undefined) {
			var query = "?op=rpc&subop=setScore&id=" + id + "&score=" + pr;

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

		var query = "?op=pref-feeds&subop=rescore&quiet=1&ids=" + actid;

		new Ajax.Request("backend.php",	{
			parameters: query,
			onComplete: function(transport) {
				viewCurrentFeed();
			} });
	}
}

function hotkey_handler(e) {

	try {

		var keycode;
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
			closeInfoBox();
		} 

		var dialog = dijit.byId("infoBox");
		var dialog_visible = false;

		if (dialog)
			dialog_visible = Element.visible(dialog.domNode);

		if (dialog_visible || !hotkeys_enabled) {
			console.log("hotkeys disabled");
			return;
		}

		if (keycode == 16) return; // ignore lone shift
		if (keycode == 17) return; // ignore lone ctrl

		if ((keycode == 70 || keycode == 67 || keycode == 71) 
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

			if ((keycode == 191 || keychar == '?') && shift_key) { // ?
				if (!Element.visible("hotkey_help_overlay")) {
					Effect.Appear("hotkey_help_overlay", {duration : 0.3});
				} else {
					Element.hide("hotkey_help_overlay");
				}
				return false;
			}

			if (keycode == 191 || keychar == '/') { // /
				displayDlg("search", getActiveFeedId() + ":" + activeFeedIsCat(), 
					function() { 
						document.forms['search_form'].query.focus();
					});
				return false;
			}

			if (keycode == 74) { // j
				// TODO: move to previous feed
				return;
			}
	
			if (keycode == 75) { // k
				// TODO: move to next feed
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
			}

			if (keycode == 88 && shift_key) { // shift-X
				dismissReadArticles();
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
				selectionToggleUnread(undefined, false, true)
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

			if (keycode == 88) { // x
				if (activeFeedIsCat()) {
					toggleCollapseCat(getActiveFeedId());
				}
			}
		}

		/* Prefix f */

		if (hotkey_prefix == 70) { // f 

			hotkey_prefix = false;

			if (keycode == 81) { // q
				if (getActiveFeedId()) {
					catchupCurrentFeed();
					return;
				}
			}

			if (keycode == 82) { // r
				if (getActiveFeedId()) {
					viewfeed(getActiveFeedId(), "ForceUpdate", activeFeedIsCat());
					return;
				}
			}

			if (keycode == 65) { // a
				toggleDispRead();
				return false;
			}

			if (keycode == 85) { // u
				if (getActiveFeedId()) {
					viewfeed(getActiveFeedId(), "ForceUpdate");
					return false;
				}
			}

			if (keycode == 69) { // e
				editFeedDlg(getActiveFeedId());
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
				if (getActiveFeedId()) {
					catchupCurrentFeed();
					return false;
				}
			}

			if (keycode == 87) { // w
				feeds_sort_by_unread = !feeds_sort_by_unread;
				return resort_feedlist();
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
				displayDlg('quickAddFilter', '',
				   function () {document.forms['filter_add_form'].reg_exp.focus();});
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

			if (keycode == 84 && shift_key) { // T
				toggleTags();
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

		var query_str = "?op=rpc&subop=togglepref&key=REVERSE_HEADLINES";

		new Ajax.Request("backend.php", {
			parameters: query_str,
			onComplete: function(transport) { 
					viewCurrentFeed();
				} });

	} catch (e) {
		exception_error("reverseHeadlineOrder", e);
	}
}

function showFeedsWithErrors() {
	displayDlg('feedUpdateErrors');
}

function handle_rpc_reply(transport, scheduled_call) {
	try {
		if (transport.responseXML) {

			if (!transport_error_check(transport)) return false;

			var seq = transport.responseXML.getElementsByTagName("seq")[0];

			if (seq) {
				seq = seq.firstChild.nodeValue;

				if (get_seq() != seq) {
					//console.log("[handle_rpc_reply] sequence mismatch: " + seq);
					return true;
				}
			}

			var message = transport.responseXML.getElementsByTagName("message")[0];

			if (message) {
				message = message.firstChild.nodeValue;

				if (message == "UPDATE_COUNTERS") {
					console.log("need to refresh counters...");
					setInitParam("last_article_id", -1);
					_force_scheduled_update = true;
				}
			}

			var counters = transport.responseXML.getElementsByTagName("counters")[0];
	
			if (counters)
				parse_counters(counters, scheduled_call);

			var runtime_info = transport.responseXML.getElementsByTagName("runtime-info")[0];

			if (runtime_info)
				parse_runtime_info(runtime_info);

			hideOrShowFeeds(getInitParam("hide_read_feeds") == 1);

		} else {
			notify_error("Error communicating with server.");
		}

	} catch (e) {
		exception_error("handle_rpc_reply", e, transport);
	}

	return true;
}

function scheduleFeedUpdate() {
	try {

		if (!getActiveFeedId()) {
			alert(__("Please select some feed first."));
			return;
		}

		var query = "?op=rpc&subop=scheduleFeedUpdate&id=" + 
			param_escape(getActiveFeedId()) +
			"&is_cat=" + param_escape(activeFeedIsCat());

		console.log(query);

		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function(transport) { 

				if (transport.responseXML) {
					var message = transport.responseXML.getElementsByTagName("message")[0];

					if (message) {
						notify_info(message.firstChild.nodeValue);
						return;
					}
				}

				notify_error("Error communicating with server.");

			} });


	} catch (e) {
		exception_error("scheduleFeedUpdate", e);
	}
}

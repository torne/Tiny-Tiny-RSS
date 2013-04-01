var global_unread = -1;
var hotkey_prefix = false;
var hotkey_prefix_pressed = false;
var hotkey_actions = {};
var _widescreen_mode = false;
var _rpc_seq = 0;
var _active_feed_id = 0;
var _active_feed_is_cat = false;

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
		return _active_feed_id;
	} catch (e) {
		exception_error("getActiveFeedId", e);
	}
}

function setActiveFeedId(id, is_cat) {
	try {
		hash_set('f', id);
		hash_set('c', is_cat ? 1 : 0);

		_active_feed_id = id;
		_active_feed_is_cat = is_cat;

		$("headlines-frame").setAttribute("feed-id", id);
		$("headlines-frame").setAttribute("is-cat", is_cat ? 1 : 0);

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
		model: treeModel,
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
	if (getInitParam("bw_limit") != "1") {
		request_counters();
		setTimeout("timeout()", 60*1000);
	}
}

function search() {
	var query = "backend.php?op=feeds&method=search&param=" +
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
		tmp = "(" + global_unread + ") " + tmp;
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
		var hasSandbox = "sandbox" in document.createElement("iframe");

		new Ajax.Request("backend.php",	{
			parameters: {op: "rpc", method: "sanityCheck", hasAudio: hasAudio,
				hasSandbox: hasSandbox},
			onComplete: function(transport) {
					backend_sanity_check_callback(transport);
				} });

		hotkey_actions["next_feed"] = function() {
				var rv = dijit.byId("feedTree").getNextFeed(
						getActiveFeedId(), activeFeedIsCat());

				if (rv) viewfeed(rv[0], '', rv[1]);
		};
		hotkey_actions["prev_feed"] = function() {
				var rv = dijit.byId("feedTree").getPreviousFeed(
						getActiveFeedId(), activeFeedIsCat());

				if (rv) viewfeed(rv[0], '', rv[1]);
		};
		hotkey_actions["next_article"] = function() {
				moveToPost('next');
		};
		hotkey_actions["prev_article"] = function() {
				moveToPost('prev');
		};
		hotkey_actions["next_article_noscroll"] = function() {
				moveToPost('next', true);
		};
		hotkey_actions["prev_article_noscroll"] = function() {
				moveToPost('prev', true);
		};
		hotkey_actions["collapse_article"] = function() {
				var id = getActiveArticleId();
				var elem = $("CICD-"+id);
				if(elem.visible()) {
					cdmCollapseArticle(null, id);
				}
				else {
					cdmExpandArticle(id);
				}
		};
		hotkey_actions["search_dialog"] = function() {
				search();
		};
		hotkey_actions["toggle_mark"] = function() {
				selectionToggleMarked(undefined, false, true);
		};
		hotkey_actions["toggle_publ"] = function() {
				selectionTogglePublished(undefined, false, true);
		};
		hotkey_actions["toggle_unread"] = function() {
				selectionToggleUnread(undefined, false, true);
		};
		hotkey_actions["edit_tags"] = function() {
				var id = getActiveArticleId();
				if (id) {
					editArticleTags(id, getActiveFeedId(), isCdmMode());
				};
			}
		hotkey_actions["dismiss_selected"] = function() {
				dismissSelectedArticles();
		};
		hotkey_actions["open_in_new_window"] = function() {
				if (getActiveArticleId()) {
					openArticleInNewWindow(getActiveArticleId());
					return;
				}
		};
		hotkey_actions["catchup_below"] = function() {
				catchupRelativeToArticle(1);
		};
		hotkey_actions["catchup_above"] = function() {
				catchupRelativeToArticle(0);
		};
		hotkey_actions["article_scroll_down"] = function() {
				var ctr = $("content_insert") ? $("content_insert") : $("headlines-frame");

				scrollArticle(40);
		};
		hotkey_actions["article_scroll_up"] = function() {
				var ctr = $("content_insert") ? $("content_insert") : $("headlines-frame");

				scrollArticle(-40);
		};
		hotkey_actions["close_article"] = function() {
				if (isCdmMode()) {
					if (!getInitParam("cdm_expanded")) {
						cdmCollapseArticle(false, getActiveArticleId());
					} else {
						dismissArticle(getActiveArticleId());
					}
				} else {
					closeArticlePanel();
				}
		};
		hotkey_actions["email_article"] = function() {
				if (typeof emailArticle != "undefined") {
					emailArticle();
				} else if (typeof mailtoArticle != "undefined") {
					mailtoArticle();
				} else {
					alert(__("Please enable mail plugin first."));
				}
		};
		hotkey_actions["select_all"] = function() {
				selectArticles('all');
		};
		hotkey_actions["select_unread"] = function() {
				selectArticles('unread');
		};
		hotkey_actions["select_marked"] = function() {
				selectArticles('marked');
		};
		hotkey_actions["select_published"] = function() {
				selectArticles('published');
		};
		hotkey_actions["select_invert"] = function() {
				selectArticles('invert');
		};
		hotkey_actions["select_none"] = function() {
				selectArticles('none');
		};
		hotkey_actions["feed_refresh"] = function() {
				if (getActiveFeedId() != undefined) {
					viewfeed(getActiveFeedId(), '', activeFeedIsCat());
					return;
				}
		};
		hotkey_actions["feed_unhide_read"] = function() {
				toggleDispRead();
		};
		hotkey_actions["feed_subscribe"] = function() {
				quickAddFeed();
		};
		hotkey_actions["feed_debug_update"] = function() {
				window.open("backend.php?op=feeds&method=view&feed=" + getActiveFeedId() +
					"&view_mode=adaptive&order_by=default&update=&m=ForceUpdate&cat=" +
					activeFeedIsCat() + "&DevForceUpdate=1&debug=1&xdebug=1&csrf_token=" +
					getInitParam("csrf_token"));
		};
		hotkey_actions["feed_edit"] = function() {
				if (activeFeedIsCat())
					alert(__("You can't edit this kind of feed."));
				else
					editFeed(getActiveFeedId());
		};
		hotkey_actions["feed_catchup"] = function() {
				if (getActiveFeedId() != undefined) {
					catchupCurrentFeed();
					return;
				}
		};
		hotkey_actions["feed_reverse"] = function() {
				reverseHeadlineOrder();
		};
		hotkey_actions["catchup_all"] = function() {
				catchupAllFeeds();
		};
		hotkey_actions["cat_toggle_collapse"] = function() {
				if (activeFeedIsCat()) {
					dijit.byId("feedTree").collapseCat(getActiveFeedId());
					return;
				}
		};
		hotkey_actions["goto_all"] = function() {
				viewfeed(-4);
		};
		hotkey_actions["goto_fresh"] = function() {
				viewfeed(-3);
		};
		hotkey_actions["goto_marked"] = function() {
				viewfeed(-1);
		};
		hotkey_actions["goto_published"] = function() {
				viewfeed(-2);
		};
		hotkey_actions["goto_tagcloud"] = function() {
				displayDlg(__("Tag cloud"), "printTagCloud");
		};
		hotkey_actions["goto_prefs"] = function() {
				gotoPreferences();
		};
		hotkey_actions["select_article_cursor"] = function() {
				var id = getArticleUnderPointer();
				if (id) {
					var cb = dijit.byId("RCHK-" + id);
					if (cb) {
						cb.attr("checked", !cb.attr("checked"));
						toggleSelectRowById(cb, "RROW-" + id);
						return false;
					}
				}
		};
		hotkey_actions["create_label"] = function() {
				addLabel();
		};
		hotkey_actions["create_filter"] = function() {
				quickAddFilter();
		};
		hotkey_actions["collapse_sidebar"] = function() {
				collapse_feedlist();
		};
		hotkey_actions["toggle_embed_original"] = function() {
				if (typeof embedOriginalArticle != "undefined") {
					if (getActiveArticleId())
						embedOriginalArticle(getActiveArticleId());
				} else {
					alert(__("Please enable embed_original plugin first."));
				}
		};
		hotkey_actions["toggle_widescreen"] = function() {
				if (!isCdmMode()) {
					_widescreen_mode = !_widescreen_mode;

					switchPanelMode(_widescreen_mode);
				}
		};
		hotkey_actions["help_dialog"] = function() {
				helpDialog("main");
		};
		hotkey_actions["toggle_combined_mode"] = function() {
				notify_progress("Loading, please wait...");

				var value = isCdmMode() ? "false" : "true";
				var query = "?op=rpc&method=setpref&key=COMBINED_DISPLAY_MODE&value=" + value;

				new Ajax.Request("backend.php",	{
					parameters: query,
					onComplete: function(transport) {
						setInitParam("combined_display_mode",
								!getInitParam("combined_display_mode"));

						closeArticlePanel();
						viewCurrentFeed();

								} });
		};
		hotkey_actions["toggle_cdm_expanded"] = function() {
				notify_progress("Loading, please wait...");

				var value = getInitParam("cdm_expanded") ? "false" : "true";
				var query = "?op=rpc&method=setpref&key=CDM_EXPANDED&value=" + value;

				new Ajax.Request("backend.php",	{
					parameters: query,
					onComplete: function(transport) {
						setInitParam("cdm_expanded", !getInitParam("cdm_expanded"));
						viewCurrentFeed();
					} });
		};


	} catch (e) {
		exception_error("init", e);
	}
}

function init_second_stage() {

	try {
		dojo.addOnLoad(function() {
			updateFeedList();
			closeArticlePanel();

			_widescreen_mode = getInitParam("widescreen");

			if (_widescreen_mode) {
				switchPanelMode(_widescreen_mode);
			}

		});

		delCookie("ttrss_test");

		var toolbar = document.forms["main_toolbar_form"];

		dijit.getEnclosingWidget(toolbar.view_mode).attr('value',
			getInitParam("default_view_mode"));

		dijit.getEnclosingWidget(toolbar.order_by).attr('value',
			getInitParam("default_view_order_by"));

		feeds_sort_by_unread = getInitParam("feeds_sort_by_unread") == 1;

		var hash_feed_id = hash_get('f');
		var hash_feed_is_cat = hash_get('c') == "1";

		if (hash_feed_id != undefined) {
			setActiveFeedId(hash_feed_id, hash_feed_is_cat);
		}

		loading_set_progress(30);

		// can't use cache_clear() here because viewfeed might not have initialized yet
		if ('sessionStorage' in window && window['sessionStorage'] !== null)
			sessionStorage.clear();

		var hotkeys = getInitParam("hotkeys");
		var tmp = [];

		for (sequence in hotkeys[1]) {
			filtered = sequence.replace(/\|.*$/, "");
			tmp[filtered] = hotkeys[1][sequence];
		}

		hotkeys[1] = tmp;
		setInitParam("hotkeys", hotkeys);

		console.log("second stage ok");

		if (getInitParam("simple_update")) {
			console.log("scheduling simple feed updater...");
			window.setTimeout("update_random_feed()", 30*1000);
		}

	} catch (e) {
		exception_error("init_second_stage", e);
	}
}

function quickMenuGo(opid) {
	try {
		switch (opid) {
		case "qmcPrefs":
			gotoPreferences();
			break;
		case "qmcLogout":
			gotoLogout();
			break;
		case "qmcTagCloud":
			displayDlg(__("Tag cloud"), "printTagCloud");
			break;
		case "qmcTagSelect":
			displayDlg(__("Select item(s) by tags"), "printTagSelect");
			break;
		case "qmcSearch":
			search();
			break;
		case "qmcAddFeed":
			quickAddFeed();
			break;
		case "qmcDigest":
			window.location.href = "backend.php?op=digest";
			break;
		case "qmcEditFeed":
			if (activeFeedIsCat())
				alert(__("You can't edit this kind of feed."));
			else
				editFeed(getActiveFeedId());
			break;
		case "qmcRemoveFeed":
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
			break;
		case "qmcCatchupAll":
			catchupAllFeeds();
			break;
		case "qmcShowOnlyUnread":
			toggleDispRead();
			break;
		case "qmcAddFilter":
			quickAddFilter();
			break;
		case "qmcAddLabel":
			addLabel();
			break;
		case "qmcRescoreFeed":
			rescoreCurrentFeed();
			break;
		case "qmcToggleWidescreen":
			if (!isCdmMode()) {
				_widescreen_mode = !_widescreen_mode;

				switchPanelMode(_widescreen_mode);
			}
			break;
		case "qmcHKhelp":
			helpDialog("main");
			break;
		default:
			console.log("quickMenuGo: unknown action: " + opid);
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
			if (v == "1") {
				Element.show(dijit.byId("newVersionIcon").domNode);
			} else {
				Element.hide(dijit.byId("newVersionIcon").domNode);
			}
			return;
		}

		if (k == "dep_ts" && parseInt(getInitParam("dep_ts")) > 0) {
			if (parseInt(getInitParam("dep_ts")) < parseInt(v) && getInitParam("reload_on_ts_change")) {
				window.location.reload();
			}
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

		shift_key = e.shiftKey;
		ctrl_key = e.ctrlKey;

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

		keychar = keychar.toLowerCase();

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

		// ensure ^*char notation
		if (shift_key) hotkey = "*" + hotkey;
		if (ctrl_key) hotkey = "^" + hotkey;

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

		var action = hotkey_actions[hotkey_action];

		if (action != null) {
			action();
			return false;
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

		/* var query_str = "?op=rpc&method=togglepref&key=REVERSE_HEADLINES";

		new Ajax.Request("backend.php", {
			parameters: query_str,
			onComplete: function(transport) {
					viewCurrentFeed();
				} }); */

		var toolbar = document.forms["main_toolbar_form"];
		var order_by = dijit.getEnclosingWidget(toolbar.order_by);

		var value = order_by.attr('value');

		if (value == "date_reverse")
			value = "default";
		else
			value = "date_reverse";

		order_by.attr('value', value);

		viewCurrentFeed();

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
					request_counters(true);
				}
			}

			var counters = reply['counters'];

			if (counters)
				parse_counters(counters, scheduled_call);

			var runtime_info = reply['runtime-info'];;

			if (runtime_info)
				parse_runtime_info(runtime_info);

			Element.hide(dijit.byId("net-alert").domNode);

		} else {
			//notify_error("Error communicating with server.");
			Element.show(dijit.byId("net-alert").domNode);
		}

	} catch (e) {
		Element.show(dijit.byId("net-alert").domNode);
		//notify_error("Error communicating with server.");
		console.log(e);
		//exception_error("handle_rpc_json", e, transport);
	}

	return true;
}

function switchPanelMode(wide) {
	try {
		if (isCdmMode()) return;

		article_id = getActiveArticleId();

		if (wide) {
			dijit.byId("headlines-wrap-inner").attr("design", 'sidebar');
			dijit.byId("content-insert").attr("region", "trailing");

	  		dijit.byId("content-insert").domNode.setStyle({width: '50%',
				height: 'auto',
				borderLeftWidth: '1px',
				borderLeftColor: '#c0c0c0',
				borderTopWidth: '0px' });

			$("headlines-toolbar").setStyle({ borderBottomWidth: '0px' });

		} else {

			dijit.byId("content-insert").attr("region", "bottom");

	  		dijit.byId("content-insert").domNode.setStyle({width: 'auto',
				height: '50%',
				borderLeftWidth: '0px',
				borderTopWidth: '1px'});

			$("headlines-toolbar").setStyle({ borderBottomWidth: '1px' });
		}

		closeArticlePanel();

		if (article_id) view(article_id);

		new Ajax.Request("backend.php", {
			parameters: "op=rpc&method=setpanelmode&wide=" + (wide ? 1 : 0),
			onComplete: function(transport) {
				console.log(transport.responseText);
			} });


	} catch (e) {
		exception_error("switchPanelMode", e);
	}
}

function update_random_feed() {
	try {
		console.log("in update_random_feed");

		new Ajax.Request("backend.php", {
			parameters: "op=rpc&method=updateRandomFeed",
			onComplete: function(transport) {
				handle_rpc_json(transport, true);
				window.setTimeout("update_random_feed()", 30*1000);
			} });

	} catch (e) {
		exception_error("update_random_feed", e);
	}
}

function hash_get(key) {
	try {
		kv = window.location.hash.substring(1).toQueryParams();
		return kv[key];
	} catch (e) {
		exception_error("hash_set", e);
	}
}
function hash_set(key, value) {
	try {
		kv = window.location.hash.substring(1).toQueryParams();
		kv[key] = value;
		window.location.hash = $H(kv).toQueryString();
	} catch (e) {
		exception_error("hash_set", e);
	}
}

var active_post_id = false;
var last_article_view = false;
var active_real_feed_id = false;

var _cdm_wd_timeout = false;
var _cdm_wd_vishist = new Array();

var article_cache = new Array();

var vgroup_last_feed = false;
var post_under_pointer = false;

var last_requested_article = false;

var preload_id_batch = [];
var preload_timeout_id = false;

var cache_added = [];

function headlines_callback2(transport, feed_cur_page) {
	try {

		if (!handle_rpc_reply(transport)) return;

		loading_set_progress(25);

		console.log("headlines_callback2 [page=" + feed_cur_page + "]");

		if (!transport_error_check(transport)) return;

		var is_cat = false;
		var feed_id = false;

		if (transport.responseXML) {
			var headlines = transport.responseXML.getElementsByTagName("headlines")[0];
			if (headlines) {
				is_cat = headlines.getAttribute("is_cat");
				feed_id = headlines.getAttribute("id");
				setActiveFeedId(feed_id, is_cat);
			}
		}

		var update_btn = document.forms["main_toolbar_form"].update;

		update_btn.disabled = !(feed_id >= 0 && !is_cat);

		try {
			if (feed_cur_page == 0) { 
				$("headlines-frame").scrollTop = 0; 
			}
		} catch (e) { };
	
		if (transport.responseXML) {
			var response = transport.responseXML;

			var headlines = response.getElementsByTagName("headlines")[0];

			var headlines_content = headlines.getElementsByTagName("content")[0];
			var headlines_toolbar = headlines.getElementsByTagName("toolbar")[0];
			
			var headlines_info = response.getElementsByTagName("headlines-info")[0];

			if (headlines_info)
				headlines_info = JSON.parse(headlines_info.firstChild.nodeValue);
			else {
				console.error("didn't find headlines-info object in response");
				return;
			}

			var headlines_count = headlines_info.count;
			var headlines_unread = headlines_info.unread;
			var disable_cache = headlines_info.disable_cache;
			
			vgroup_last_feed = headlines_info.vgroup_last_feed;

			if (parseInt(headlines_count) < getInitParam("default_article_limit")) {
				_infscroll_disable = 1;
			} else {
				_infscroll_disable = 0;
			}

			var counters = response.getElementsByTagName("counters")[0];
			var articles = response.getElementsByTagName("article");
			var runtime_info = response.getElementsByTagName("runtime-info");
	
			if (feed_cur_page == 0) {
				if (headlines) {
					dijit.byId("headlines-frame").attr('content', 
						headlines_content.firstChild.nodeValue);

					dijit.byId("headlines-toolbar").attr('content',
						headlines_toolbar.firstChild.nodeValue);

					initHeadlinesMenu();

					var cache_prefix = "";

					if (is_cat) {
						cache_prefix = "C:";
					} else {
						cache_prefix = "F:";
					}

					cache_invalidate(cache_prefix + feed_id);

					if (!disable_cache) {
						cache_inject(cache_prefix + feed_id,
							$("headlines-frame").innerHTML, headlines_unread);
					}

				} else {
					console.warn("headlines_callback: returned no data");
					dijit.byId("headlines-frame").attr('content', 
						"<div class='whiteBox'>" + 
						__('Could not update headlines (missing XML data)') + "</div>");
	
				}
			} else {
				if (headlines) {
					if (headlines_count > 0) {
						console.log("adding some more headlines...");

						var c = dijit.byId("headlines-frame");	
						var ids = getSelectedArticleIds2();
	
						//c.attr('content', c.attr('content') + 
						//	headlines_content.firstChild.nodeValue);

						$("headlines-tmp").innerHTML = headlines_content.firstChild.nodeValue;

						$$("#headlines-tmp > div").each(function(row) {
							c.domNode.appendChild(row);
						});

						console.log("restore selected ids: " + ids);

						for (var i = 0; i < ids.length; i++) {
							markHeadline(ids[i]);
						}

						initHeadlinesMenu();

					} else {
						console.log("no new headlines received");
					}
				} else {
					console.warn("headlines_callback: returned no data");
					notify_error("Error while trying to load more headlines");	
				}

			}
	
			if (articles) {
				for (var i = 0; i < articles.length; i++) {
					var a_id = articles[i].getAttribute("id");
					//console.log("found id: " + a_id);
					cache_inject(a_id, articles[i].firstChild.nodeValue);
				}
			} else {
				console.log("no cached articles received");
			}

			if (counters)
				parse_counters(counters);
			else
				request_counters();

		} else {
			console.warn("headlines_callback: returned no XML object");
			dijit.byId("headlines-frame").attr('content', "<div class='whiteBox'>" + 
					__('Could not update headlines (missing XML object)') + "</div>");
		}
	

		if (_cdm_wd_timeout) window.clearTimeout(_cdm_wd_timeout);

		if (isCdmMode() && 
				getActiveFeedId() != -3 &&
				getInitParam("cdm_auto_catchup") == 1) {
			console.log("starting CDM watchdog");
			_cdm_wd_timeout = window.setTimeout("cdmWatchdog()", 5000);
			_cdm_wd_vishist = new Array();
		} else {
			console.log("not in CDM mode or watchdog disabled");
		}
	
		_feed_cur_page = feed_cur_page;
		_infscroll_request_sent = 0;

		notify("");

	} catch (e) {
		exception_error("headlines_callback2", e, transport);
	}
}

function render_article(article) {
	try {
		dijit.byId("headlines-wrap-inner").addChild(
				dijit.byId("content-insert"));

		var c = dijit.byId("content-insert");

		try {
			c.domNode.scrollTop = 0;
		} catch (e) { };
		
		c.attr('content', article);

		correctHeadlinesOffset(getActiveArticleId());		

	} catch (e) {
		exception_error("render_article", e);
	}
}

function showArticleInHeadlines(id) {

	try {

		selectArticles("none");

		var crow = $("RROW-" + id);

		if (!crow) return;

		var article_is_unread = crow.hasClassName("Unread");
			
		crow.removeClassName("Unread");

		selectArticles('none');

		var upd_img_pic = $("FUPDPIC-" + id);

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
			//
		}

		if (upd_img_pic && (upd_img_pic.src.match("updated.png") || 
					upd_img_pic.src.match("fresh_sign.png"))) {

			upd_img_pic.src = "images/blank_icon.gif";

			cache_invalidate(cache_prefix + getActiveFeedId());

			cache_inject(cache_prefix + getActiveFeedId(),
				$("headlines-frame").innerHTML,
				getFeedUnread(getActiveFeedId()));

		} else if (article_is_unread && view_mode == "all_articles") {

			cache_invalidate(cache_prefix + getActiveFeedId());

			cache_inject(cache_prefix + getActiveFeedId(),
				$("headlines-frame").innerHTML,
				getFeedUnread(getActiveFeedId())-1);

		} else if (article_is_unread) {
			cache_invalidate(cache_prefix + getActiveFeedId());
		}

		markHeadline(id);

		if (article_is_unread)
			_force_scheduled_update = true;

	} catch (e) {
		exception_error("showArticleInHeadlines", e);
	}
}

function article_callback2(transport, id) {
	try {
		console.log("article_callback2 " + id);

		if (!handle_rpc_reply(transport)) return;

		if (transport.responseXML) {

			if (!transport_error_check(transport)) return;

			var upic = $('FUPDPIC-' + id);

			if (upic) {
				upic.src = 'images/blank_icon.gif';
			}

			if (id != last_requested_article) {
				console.log("requested article id is out of sequence, aborting");
				return;
			}

//			active_post_id = id; 

			//console.log("looking for articles to cache...");

			var articles = transport.responseXML.getElementsByTagName("article");

			for (var i = 0; i < articles.length; i++) {
				var a_id = articles[i].getAttribute("id");

				//console.log("found id: " + a_id);

				if (a_id == active_post_id) {
					//console.log("active article, rendering...");					
					render_article(articles[i].firstChild.nodeValue);
				}

				cache_inject(a_id, articles[i].firstChild.nodeValue);
			}


//			showArticleInHeadlines(id);	

			var reply = transport.responseXML.firstChild.firstChild;
		
		} else {
			console.warn("article_callback: returned no XML object");
			//var f = $("content-frame");
			//f.innerHTML = "<div class='whiteBox'>" + __('Could not display article (missing XML object)') + "</div>";
		}

		var date = new Date();
		last_article_view = date.getTime() / 1000;

		request_counters();

		notify("");
	} catch (e) {
		exception_error("article_callback2", e, transport);
	}
}

function view(id) {
	try {
		console.log("loading article: " + id);

		var cached_article = cache_find(id);

		console.log("cache check result: " + (cached_article != false));
	
		hideAuxDlg();

		var query = "?op=view&id=" + param_escape(id);

		var neighbor_ids = getRelativePostIds(active_post_id);

		/* only request uncached articles */

		var cids_to_request = Array();

		for (var i = 0; i < neighbor_ids.length; i++) {
			if (!cache_check(neighbor_ids[i])) {
				cids_to_request.push(neighbor_ids[i]);
			}
		}

		console.log("additional ids: " + cids_to_request.toString());			
	
		query = query + "&cids=" + cids_to_request.toString();

		var crow = $("RROW-" + id);
		var article_is_unread = crow.hasClassName("Unread");

		active_post_id = id;
		showArticleInHeadlines(id);

		if (!cached_article) {

			var upic = $('FUPDPIC-' + id);

			if (upic) {	
				upic.src = getInitParam("sign_progress");
			}

		} else if (cached_article && article_is_unread) {

			query = query + "&mode=prefetch";

			render_article(cached_article);

		} else if (cached_article) {

			query = query + "&mode=prefetch_old";
			render_article(cached_article);

		}

		cache_expire();

		last_requested_article = id;

		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function(transport) { 
				article_callback2(transport, id); 
			} });

		return false;

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

function toggleMark(id, client_only) {
	try {
		var query = "?op=rpc&id=" + id + "&subop=mark";
	
		var img = $("FMPIC-" + id);

		if (!img) return;
	
		if (img.src.match("mark_unset")) {
			img.src = img.src.replace("mark_unset", "mark_set");
			img.alt = __("Unstar article");
			query = query + "&mark=1";

		} else {
			img.src = img.src.replace("mark_set", "mark_unset");
			img.alt = __("Star article");
			query = query + "&mark=0";
		}

		if (!client_only) {
			new Ajax.Request("backend.php", {
				parameters: query,
				onComplete: function(transport) { 
					handle_rpc_json(transport); 
				} });
		}

	} catch (e) {
		exception_error("toggleMark", e);
	}
}

function togglePub(id, client_only, no_effects, note) {
	try {
		var query = "?op=rpc&id=" + id + "&subop=publ";
	
		if (note != undefined) {
			query = query + "&note=" + param_escape(note);
		} else {
			query = query + "&note=undefined";
		}

		var img = $("FPPIC-" + id);

		if (!img) return;
	
		if (img.src.match("pub_unset") || note != undefined) {
			img.src = img.src.replace("pub_unset", "pub_set");
			img.alt = __("Unpublish article");
			query = query + "&pub=1";

		} else {
			img.src = img.src.replace("pub_set", "pub_unset");
			img.alt = __("Publish article");

			query = query + "&pub=0";
		}

		if (!client_only) {
			new Ajax.Request("backend.php", {
				parameters: query,
				onComplete: function(transport) { 
					handle_rpc_json(transport); 
				} });
		}

/*		if (!client_only) {
			new Ajax.Request("backend.php", {
				parameters: query,
				onComplete: function(transport) { 
					handle_rpc_reply(transport);
		
					var note = transport.responseXML.getElementsByTagName("note")[0];
		
					if (note) {
						var note_id = note.getAttribute("id");
						var note_size = note.getAttribute("size");
						var note_content = note.firstChild.nodeValue;
		
						var container = $('POSTNOTE-' + note_id);
		
						cache_invalidate(note_id);
		
						if (container) {
							if (note_size == "0") {
								Element.hide(container);
							} else {
								container.innerHTML = note_content;
								Element.show(container);
							}
						}
					}	

				} });
		} */

	} catch (e) {
		exception_error("togglePub", e);
	}
}

function moveToPost(mode) {

	try {

		var rows = getVisibleArticleIds();

		var prev_id = false;
		var next_id = false;
		
		if (!$('RROW-' + active_post_id)) {
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
	
					cdmExpandArticle(next_id);
					cdmScrollToArticleId(next_id);

				} else {
					correctHeadlinesOffset(next_id);
					view(next_id, getActiveFeedId());
				}
			}
		}
		
		if (mode == "prev") {
			if (prev_id) {
				if (isCdmMode()) {
					cdmExpandArticle(prev_id);
					cdmScrollToArticleId(prev_id);
				} else {
					correctHeadlinesOffset(prev_id);
					view(prev_id, getActiveFeedId());
				}
			}
		} 

	} catch (e) {
		exception_error("moveToPost", e);
	}
}

function toggleSelected(id, force_on) {
	try {
	
		var cb = $("RCHK-" + id);
		var row = $("RROW-" + id);

		if (row) {
			if (row.hasClassName('Selected') && !force_on) {
				row.removeClassName('Selected');
				if (cb) cb.checked = false;
			} else {
				row.addClassName('Selected');
				if (cb) cb.checked = true;
			}
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
	
		var row = $("RROW-" + id);
		if (row) {
			if (cmode == undefined || cmode == 2) {
				if (row.hasClassName("Unread")) {
					row.removeClassName("Unread");

					if (effect) {
						new Effect.Highlight(row, {duration: 1, startcolor: "#fff7d5",
							afterFinish: toggleUnread_afh,
							queue: { position:'end', scope: 'TMRQ-' + id, limit: 1 } } );
					} 

				} else {
					row.addClassName("Unread");
				}

			} else if (cmode == 0) {

				row.removeClassName("Unread");

				if (effect) {
					new Effect.Highlight(row, {duration: 1, startcolor: "#fff7d5",
						afterFinish: toggleUnread_afh,
						queue: { position:'end', scope: 'TMRQ-' + id, limit: 1 } } );
				} 

			} else if (cmode == 1) {
				row.addClassName("Unread");
			}

			if (cmode == undefined) cmode = 2;

			var query = "?op=rpc&subop=catchupSelected" +
				"&cmode=" + param_escape(cmode) + "&ids=" + param_escape(id);

//			notify_progress("Loading, please wait...");

			new Ajax.Request("backend.php", {
				parameters: query,
				onComplete: function(transport) { 
					handle_rpc_json(transport); 
				} });

		}

	} catch (e) {
		exception_error("toggleUnread", e);
	}
}

function selectionRemoveLabel(id, ids) {
	try {

		if (!ids) var ids = getSelectedArticleIds2();

		if (ids.length == 0) {
			alert(__("No articles are selected."));
			return;
		}

//		var ok = confirm(__("Remove selected articles from label?"));

//		if (ok) {

			var query = "?op=rpc&subop=removeFromLabel&ids=" +
				param_escape(ids.toString()) + "&lid=" + param_escape(id);

			console.log(query);

//			notify_progress("Loading, please wait...");

			cache_invalidate("F:" + (-11 - id));

			new Ajax.Request("backend.php", {
				parameters: query,
				onComplete: function(transport) { 
					handle_rpc_json(transport);
					show_labels_in_headlines(transport);
				} });

//		}

	} catch (e) {
		exception_error("selectionAssignLabel", e);

	}
}

function selectionAssignLabel(id, ids) {
	try {

		if (!ids) ids = getSelectedArticleIds2();

		if (ids.length == 0) {
			alert(__("No articles are selected."));
			return;
		}

//		var ok = confirm(__("Assign selected articles to label?"));

//		if (ok) {

			cache_invalidate("F:" + (-11 - id));

			var query = "?op=rpc&subop=assignToLabel&ids=" +
				param_escape(ids.toString()) + "&lid=" + param_escape(id);

			console.log(query);

//			notify_progress("Loading, please wait...");

			new Ajax.Request("backend.php", {
				parameters: query,
				onComplete: function(transport) { 
					handle_rpc_json(transport);
					show_labels_in_headlines(transport);
				} });

//		}

	} catch (e) {
		exception_error("selectionAssignLabel", e);

	}
}

function selectionToggleUnread(set_state, callback, no_error) {
	try {
		var rows = getSelectedArticleIds2();

		if (rows.length == 0 && !no_error) {
			alert(__("No articles are selected."));
			return;
		}

		for (i = 0; i < rows.length; i++) {
			var row = $("RROW-" + rows[i]);
			if (row) {
				if (set_state == undefined) {
					if (row.hasClassName("Unread")) {
						row.removeClassName("Unread");
					} else {
						row.addClassName("Unread");
					}
				}

				if (set_state == false) {
					row.removeClassName("Unread");
				}

				if (set_state == true) {
					row.addClassName("Unread");
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

			var query = "?op=rpc&subop=catchupSelected" +
				"&cmode=" + cmode + "&ids=" + param_escape(rows.toString()); 

			notify_progress("Loading, please wait...");

			new Ajax.Request("backend.php", {
				parameters: query,
				onComplete: function(transport) { 
					handle_rpc_json(transport);
					if (callback) callback(transport);
				} });

		}

	} catch (e) {
		exception_error("selectionToggleUnread", e);
	}
}

function selectionToggleMarked() {
	try {
	
		var rows = getSelectedArticleIds2();
		
		if (rows.length == 0) {
			alert(__("No articles are selected."));
			return;
		}

		for (i = 0; i < rows.length; i++) {
			toggleMark(rows[i], true, true);
		}

		if (rows.length > 0) {

			var query = "?op=rpc&subop=markSelected&ids=" +
				param_escape(rows.toString()) + "&cmode=2";

			new Ajax.Request("backend.php", {
				parameters: query,
				onComplete: function(transport) { 
					handle_rpc_json(transport); 
				} });

		}

	} catch (e) {
		exception_error("selectionToggleMarked", e);
	}
}

function selectionTogglePublished() {
	try {
	
		var rows = getSelectedArticleIds2();

		if (rows.length == 0) {
			alert(__("No articles are selected."));
			return;
		}

		for (i = 0; i < rows.length; i++) {
			togglePub(rows[i], true, true);
		}

		if (rows.length > 0) {

			var query = "?op=rpc&subop=publishSelected&ids=" +
				param_escape(rows.toString()) + "&cmode=2";

			new Ajax.Request("backend.php", {
				parameters: query,
				onComplete: function(transport) { 
					handle_rpc_json(transport); 
				} });

		}

	} catch (e) {
		exception_error("selectionToggleMarked", e);
	}
}

function getSelectedArticleIds2() {

	var rv = [];

	$$("#headlines-frame > div[id*=RROW][class*=Selected]").each(
		function(child) {
			rv.push(child.id.replace("RROW-", ""));
		});

	return rv;
}

function getLoadedArticleIds() {
	var rv = [];

	var children = $$("#headlines-frame > div[id*=RROW-]");

	children.each(function(child) {
			rv.push(child.id.replace("RROW-", ""));
		});

	return rv;

}

// mode = all,none,unread,invert
function selectArticles(mode) {
	try {

		var children = $$("#headlines-frame > div[id*=RROW]");

		children.each(function(child) {
			var id = child.id.replace("RROW-", "");
			var cb = $("RCHK-" + id);

			if (mode == "all") {
				child.addClassName("Selected");
				cb.checked = true;
			} else if (mode == "unread") {
				if (child.hasClassName("Unread")) {
					child.addClassName("Selected");
					cb.checked = true;
				} else {
					child.removeClassName("Selected");
					cb.checked = false;
				}
			} else if (mode == "invert") {
				if (child.hasClassName("Selected")) {
					child.removeClassName("Selected");
					cb.checked = false;
				} else {
					child.addClassName("Selected");
					cb.checked = true;
				}

			} else {
				child.removeClassName("Selected");
				cb.checked = false;
			}
		});

	} catch (e) {
		exception_error("selectArticles", e);
	}
}

function catchupPage() {

	var fn = getFeedName(getActiveFeedId(), activeFeedIsCat());
	
	var str = __("Mark all visible articles in %s as read?");

	str = str.replace("%s", fn);

	if (getInitParam("confirm_feed_catchup") == 1 && !confirm(str)) {
		return;
	}

	selectArticles('all');
	selectionToggleUnread(false, 'viewCurrentFeed()', true)
	selectArticles('none');
}

function deleteSelection() {

	try {
	
		var rows = getSelectedArticleIds2();

		if (rows.length == 0) {
			alert(__("No articles are selected."));
			return;
		}
	
		var fn = getFeedName(getActiveFeedId(), activeFeedIsCat());
		var str;
		var op;
	
		if (getActiveFeedId() != 0) {
			str = __("Delete %d selected articles in %s?");
		} else {
			str = __("Delete %d selected articles?");
		}
	
		str = str.replace("%d", rows.length);
		str = str.replace("%s", fn);
	
		if (getInitParam("confirm_feed_catchup") == 1 && !confirm(str)) {
			return;
		}

		query = "?op=rpc&subop=delete&ids=" + param_escape(rows);

		console.log(query);

		new Ajax.Request("backend.php",	{
			parameters: query,
			onComplete: function(transport) {
					handle_rpc_json(transport);
					viewCurrentFeed();
				} });

	} catch (e) {
		exception_error("deleteSelection", e);
	}
}

function archiveSelection() {

	try {

		var rows = getSelectedArticleIds2();

		if (rows.length == 0) {
			alert(__("No articles are selected."));
			return;
		}
	
		var fn = getFeedName(getActiveFeedId(), activeFeedIsCat());
		var str;
		var op;
	
		if (getActiveFeedId() != 0) {
			str = __("Archive %d selected articles in %s?");
			op = "archive";
		} else {
			str = __("Move %d archived articles back?");
			op = "unarchive";
		}
	
		str = str.replace("%d", rows.length);
		str = str.replace("%s", fn);
	
		if (getInitParam("confirm_feed_catchup") == 1 && !confirm(str)) {
			return;
		}

		query = "?op=rpc&subop="+op+"&ids=" + param_escape(rows);

		console.log(query);

		for (var i = 0; i < rows.length; i++) {
			cache_invalidate(rows[i]);
		}

		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function(transport) {
					handle_rpc_json(transport);
					viewCurrentFeed();
				} });

	} catch (e) {
		exception_error("archiveSelection", e);
	}
}

function catchupSelection() {

	try {

		var rows = getSelectedArticleIds2();

		if (rows.length == 0) {
			alert(__("No articles are selected."));
			return;
		}
	
		var fn = getFeedName(getActiveFeedId(), activeFeedIsCat());
		
		var str = __("Mark %d selected articles in %s as read?");
	
		str = str.replace("%d", rows.length);
		str = str.replace("%s", fn);
	
		if (getInitParam("confirm_feed_catchup") == 1 && !confirm(str)) {
			return;
		}
	
		selectionToggleUnread(false, 'viewCurrentFeed()', true)

	} catch (e) {
		exception_error("catchupSelection", e);
	}
}

function editArticleTags(id) {
		var query = "backend.php?op=dlg&id=editArticleTags&param=" + param_escape(id);

		if (dijit.byId("editTagsDlg"))
			dijit.byId("editTagsDlg").destroyRecursive();

		dialog = new dijit.Dialog({
			id: "editTagsDlg",
			title: __("Edit article Tags"),
			style: "width: 600px",
			execute: function() {
				if (this.validate()) {
					var query = dojo.objectToQuery(this.attr('value'));

					notify_progress("Saving article tags...", true);

					new Ajax.Request("backend.php",	{
					parameters: query,
					onComplete: function(transport) {
						notify('');
						dialog.hide();
	
						var data = JSON.parse(transport.responseText);

						if (data) {
							var tags_str = data.tags_str;
							var id = tags_str.id;

							var tags = $("ATSTR-" + id);

							if (tags) {
								tags.innerHTML = tags_str.content;
							}

							cache_invalidate(id);
						}
	
					}});
				}
			},
			href: query,
		});

		var tmph = dojo.connect(dialog, 'onLoad', function() {
	   	dojo.disconnect(tmph);

			new Ajax.Autocompleter('tags_str', 'tags_choices',
			   "backend.php?op=rpc&subop=completeTags",
			   { tokens: ',', paramName: "search" });
		});

		dialog.show();

}

function cdmScrollToArticleId(id) {
	try {
		var ctr = $("headlines-frame");
		var e = $("RROW-" + id);

		if (!e || !ctr) return;

		ctr.scrollTop = e.offsetTop;

	} catch (e) {
		exception_error("cdmScrollToArticleId", e);
	}
}

function cdmWatchdog() {

	try {

		var ctr = $("headlines-frame");

		if (!ctr) return;

		var ids = new Array();

		var e = ctr.firstChild;

		while (e) {
			if (e.className && e.hasClassName("Unread") && e.id &&
					e.id.match("RROW-")) {

				// article fits in viewport OR article is longer than viewport and
				// its bottom is visible

				if (ctr.scrollTop <= e.offsetTop && e.offsetTop + e.offsetHeight <=
						ctr.scrollTop + ctr.offsetHeight) {

//					console.log(e.id + " is visible " + e.offsetTop + "." + 
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

		console.log("cdmWatchdog, ids= " + ids.toString());

		if (ids.length > 0) {

			for (var i = 0; i < ids.length; i++) {
				var e = $("RROW-" + ids[i]);
				if (e) {
					e.removeClassName("Unread");
				}
			}

			var query = "?op=rpc&subop=catchupSelected" +
				"&cmode=0" + "&ids=" + param_escape(ids.toString());

			new Ajax.Request("backend.php", {
				parameters: query,
				onComplete: function(transport) { 
					handle_rpc_json(transport); 
				} });

		}

		_cdm_wd_timeout = window.setTimeout("cdmWatchdog()", 4000);

	} catch (e) {
		exception_error("cdmWatchdog", e);
	}

}


function cache_inject(id, article, param) {

	try {
		if (!cache_check_param(id, param)) {
			//console.log("cache_article: miss: " + id + " [p=" + param + "]");

		   var date = new Date();
	      var ts = Math.round(date.getTime() / 1000);

			var cache_obj = {};
	
			cache_obj["id"] = id;
			cache_obj["data"] = article;
			cache_obj["param"] = param;

			if (param) id = id + ":" + param;

			cache_added["TS:" + id] = ts;

			if (has_local_storage()) 
				sessionStorage.setItem(id, JSON.stringify(cache_obj));
			else
				article_cache.push(cache_obj);

		} else {
			//console.log("cache_article: hit: " + id + " [p=" + param + "]");
		}
	} catch (e) {	
		exception_error("cache_inject", e);
	}
}

function cache_find(id) {

	if (has_local_storage()) {
		var cache_obj = sessionStorage.getItem(id);

		if (cache_obj) {
			cache_obj = JSON.parse(cache_obj);

			if (cache_obj)
				return cache_obj['data'];
		}

	} else {
		for (var i = 0; i < article_cache.length; i++) {
			if (article_cache[i]["id"] == id) {
				return article_cache[i]["data"];
			}
		}
	}
	return false;
}

function cache_find_param(id, param) {

	if (has_local_storage()) {

		if (param) id = id + ":" + param;

		var cache_obj = sessionStorage.getItem(id);

		if (cache_obj) {
			cache_obj = JSON.parse(cache_obj);

			if (cache_obj)
				return cache_obj['data'];
		}

	} else {
		for (var i = 0; i < article_cache.length; i++) {
			if (article_cache[i]["id"] == id && article_cache[i]["param"] == param) {
				return article_cache[i]["data"];
			}
		}
	}

	return false;
}

function cache_check(id) {
	if (has_local_storage()) {
		if (sessionStorage.getItem(id))
			return true;
	} else {
		for (var i = 0; i < article_cache.length; i++) {
			if (article_cache[i]["id"] == id) {
				return true;
			}
		}
	}
	return false;
}

function cache_check_param(id, param) {
	if (has_local_storage()) {

		if (param) id = id + ":" + param;

		if (sessionStorage.getItem(id))
			return true;

	} else {
		for (var i = 0; i < article_cache.length; i++) {
			if (article_cache[i]["id"] == id && article_cache[i]["param"] == param) {
				return true;
			}
		}
	}
	return false;
}

function cache_expire() {
if (has_local_storage()) {

		var date = new Date();
		var timestamp = Math.round(date.getTime() / 1000);

		for (var i = 0; i < sessionStorage.length; i++) {

			var id = sessionStorage.key(i);

			if (timestamp - cache_added["TS:" + id] > 180) {
				sessionStorage.removeItem(id);
			}
		}

	} else {
		while (article_cache.length > 25) {
			article_cache.shift();
		}
	}
}

function cache_flush() {
	if (has_local_storage()) {
		sessionStorage.clear();
	} else {
		article_cache = new Array();
	}
}

function cache_invalidate(id) {
	try {	
		if (has_local_storage()) {

			var found = false;

			for (var i = 0; i < sessionStorage.length; i++) {
				var key = sessionStorage.key(i);

//					console.warn("cache_invalidate: " + key_id + " cmp " + id);

				if (key == id || key.indexOf(id + ":") == 0) {
					sessionStorage.removeItem(key);
					found = true;
					break;
				}
			}

			return found;

		} else {
			var i = 0

			while (i < article_cache.length) {
				if (article_cache[i]["id"] == id) {
					//console.log("cache_invalidate: removed id " + id);
					article_cache.splice(i, 1);
					return true;
				}
				i++;
			}
		}

		//console.log("cache_invalidate: id not found: " + id);
		return false;
	} catch (e) {
		exception_error("cache_invalidate", e);
	}
}

function getActiveArticleId() {
	return active_post_id;
}

function preloadBatchedArticles() {
	try {

		var query = "?op=rpc&subop=getArticles&ids=" + 
			preload_id_batch.toString();

		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function(transport) { 

				preload_id_batch = [];

				var articles = transport.responseXML.getElementsByTagName("article");

				for (var i = 0; i < articles.length; i++) {
					var id = articles[i].getAttribute("id");
					if (!cache_check(id)) {
						cache_inject(id, articles[i].firstChild.nodeValue);				
						console.log("preloaded article: " + id);
					}
				}
		} }); 

	} catch (e) {
		exception_error("preloadBatchedArticles", e);
	}
}

function preloadArticleUnderPointer(id) {
	try {
		if (getInitParam("bw_limit") == "1") return;

		if (post_under_pointer == id && !cache_check(id)) {

			console.log("trying to preload article " + id);

			var neighbor_ids = getRelativePostIds(id, 1);

			/* only request uncached articles */

			if (preload_id_batch.indexOf(id) == -1) {
				for (var i = 0; i < neighbor_ids.length; i++) {
					if (!cache_check(neighbor_ids[i])) {
						preload_id_batch.push(neighbor_ids[i]);
					}
				}
			}

			if (preload_id_batch.indexOf(id) == -1)
				preload_id_batch.push(id);

			//console.log("preload ids batch: " + preload_id_batch.toString());

			window.clearTimeout(preload_timeout_id);
			preload_batch_timeout_id = window.setTimeout('preloadBatchedArticles()', 1000);

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

function headlines_scroll_handler(e) {
	try {

		if (e.scrollTop + e.offsetHeight > e.scrollHeight - 100) {
			if (!_infscroll_disable) {
				viewNextFeedPage();
			}
		}

	} catch (e) {
		exception_error("headlines_scroll_handler", e);
	}
}

function catchupRelativeToArticle(below) {

	try {


		if (!getActiveArticleId()) {
			alert(__("No article is selected."));
			return;
		}

		var visible_ids = getVisibleArticleIds();

		var ids_to_mark = new Array();

		if (!below) {
			for (var i = 0; i < visible_ids.length; i++) {
				if (visible_ids[i] != getActiveArticleId()) {
					var e = $("RROW-" + visible_ids[i]);

					if (e && e.hasClassName("Unread")) {
						ids_to_mark.push(visible_ids[i]);
					}
				} else {
					break;
				}
			}
		} else {
			for (var i = visible_ids.length-1; i >= 0; i--) {
				if (visible_ids[i] != getActiveArticleId()) {
					var e = $("RROW-" + visible_ids[i]);

					if (e && e.hasClassName("Unread")) {
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

			if (getInitParam("confirm_feed_catchup") != 1 || confirm(msg)) {

				for (var i = 0; i < ids_to_mark.length; i++) {
					var e = $("RROW-" + ids_to_mark[i]);
					e.removeClassName("Unread");
				}

				var query = "?op=rpc&subop=catchupSelected" +
					"&cmode=0" + "&ids=" + param_escape(ids_to_mark.toString()); 

				new Ajax.Request("backend.php", {
					parameters: query,
					onComplete: function(transport) { 
						handle_rpc_json(transport); 
					} });

			}
		}

	} catch (e) {
		exception_error("catchupRelativeToArticle", e);
	}
}

function cdmExpandArticle(id) {
	try {

		hideAuxDlg();

		var elem = $("CICD-" + active_post_id);

		var upd_img_pic = $("FUPDPIC-" + id);

		if (upd_img_pic && (upd_img_pic.src.match("updated.png") || 
				upd_img_pic.src.match("fresh_sign.png"))) {

			upd_img_pic.src = "images/blank_icon.gif";
		}

		if (id == active_post_id && Element.visible(elem))
			return true;

		selectArticles("none");

		var old_offset = $("RROW-" + id).offsetTop;

		if (active_post_id && elem && !getInitParam("cdm_expanded")) {
		  	Element.hide(elem);
			Element.show("CEXC-" + active_post_id);
		}

		active_post_id = id;

		elem = $("CICD-" + id);

		if (!Element.visible(elem)) {
			Element.show(elem);
			Element.hide("CEXC-" + id);

			if ($("CWRAP-" + id).innerHTML == "") {

				$("FUPDPIC-" + id).src = "images/indicator_tiny.gif";

				$("CWRAP-" + id).innerHTML = "<div class=\"insensitive\">" + 
					__("Loading, please wait...") + "</div>";
	
				var query = "?op=rpc&subop=cdmGetArticle&id=" + param_escape(id);
	
				//console.log(query);
	
				new Ajax.Request("backend.php", {
					parameters: query,
					onComplete: function(transport) { 
						$("FUPDPIC-" + id).src = 'images/blank_icon.gif';
	
						handle_rpc_json(transport);

						var reply = JSON.parse(transport.responseText);

						if (reply) {
							var article = reply['article']['content'];
							var recv_id = reply['article']['id'];
	
							if (recv_id == id)
								$("CWRAP-" + id).innerHTML = article;
	
						} else {
							$("CWRAP-" + id).innerHTML = __("Unable to load article.");
	
						}
				}});
	
			}
		}

		var new_offset = $("RROW-" + id).offsetTop;

		$("headlines-frame").scrollTop += (new_offset-old_offset);

		if ($("RROW-" + id).offsetTop != old_offset) 
			$("headlines-frame").scrollTop = new_offset;

		toggleUnread(id, 0, true);
		toggleSelected(id);

	} catch (e) {
		exception_error("cdmExpandArticle", e);
	}

	return false;
}

function fixHeadlinesOrder(ids) {
	try {
		for (var i = 0; i < ids.length; i++) {
			var e = $("RROW-" + ids[i]);

			if (e) {
				if (i % 2 == 0) {
					e.removeClassName("even");
					e.addClassName("odd");
				} else {
					e.removeClassName("odd");
					e.addClassName("even");
				}
			}
		}
	} catch (e) {
		exception_error("fixHeadlinesOrder", e);
	}
}

function getArticleUnderPointer() {
	return post_under_pointer;
}

function zoomToArticle(event, id) {
	try {
		var cached_article = cache_find(id);

		if (dijit.byId("ATAB-" + id))
			if (!event || !event.shiftKey)
				return dijit.byId("content-tabs").selectChild(dijit.byId("ATAB-" + id));

		if (cached_article) {
			//closeArticlePanel();
		
			var article_pane = new dijit.layout.ContentPane({ 
				title: __("Loading...") , content: cached_article, 
				style: 'padding : 0px;',
				id: 'ATAB-' + id,
				closable: true });
	
			dijit.byId("content-tabs").addChild(article_pane);

			if (!event || !event.shiftKey)
				dijit.byId("content-tabs").selectChild(article_pane);

			if ($("PTITLE-" + id))
				article_pane.attr('title', $("PTITLE-" + id).innerHTML);

		} else {

			var query = "?op=rpc&subop=getArticles&ids=" + param_escape(id);
	
			notify_progress("Loading, please wait...", true);
	
			new Ajax.Request("backend.php", {
				parameters: query,
				onComplete: function(transport) { 
					notify('');
	
					if (transport.responseXML) {
						//closeArticlePanel();
	
						var article = transport.responseXML.getElementsByTagName("article")[0];
						var content = article.firstChild.nodeValue;
	
						var article_pane = new dijit.layout.ContentPane({ 
							title: "article-" + id , content: content, 
							style: 'padding : 0px;',
							id: 'ATAB-' + id,
							closable: true });
	
						dijit.byId("content-tabs").addChild(article_pane);

						if (!event || !event.shiftKey)
							dijit.byId("content-tabs").selectChild(article_pane);

						if ($("PTITLE-" + id))
							article_pane.attr('title', $("PTITLE-" + id).innerHTML);
					}
	
				} });
			}

	} catch (e) {
		exception_error("zoomToArticle", e);
	}
}

function scrollArticle(offset) {
	try {
		if (!isCdmMode()) {
			var ci = $("content-insert");
			if (ci) {
				ci.scrollTop += offset;
			}
		} else {
			var hi = $("headlines-frame");
			if (hi) {
				hi.scrollTop += offset;
			}

		}
	} catch (e) {
		exception_error("scrollArticle", e);
	}
}

function show_labels_in_headlines(transport) {
	try {
		var data = JSON.parse(transport.responseText);

		if (data) {
			data['info-for-headlines'].each(function(elem) {
				var ctr = $("HLLCTR-" + elem.id);

				if (ctr) ctr.innerHTML = elem.labels;
			});
		}
	} catch (e) {
		exception_error("show_labels_in_headlines", e);
	}
}

function toggleHeadlineActions() {
	try {
		var e = $("headlineActionsBody");
		var p = $("headlineActionsDrop");

		if (!Element.visible(e)) {
			Element.show(e);
		} else {
			Element.hide(e);
		}

		e.scrollTop = 0;
		e.style.left = (p.offsetLeft + 1) + "px";
		e.style.top = (p.offsetTop + p.offsetHeight + 2) + "px";

	} catch (e) {
		exception_error("toggleHeadlineActions", e);
	}
}

/* function publishWithNote(id, def_note) {
	try {
		if (!def_note) def_note = '';

		var note = prompt(__("Please enter a note for this article:"), def_note);

		if (note != undefined) {
			togglePub(id, false, false, note);
		}

	} catch (e) {
		exception_error("publishWithNote", e);
	}
} */

function emailArticle(id) {
	try {
		if (!id) {
			var ids = getSelectedArticleIds2();

			if (ids.length == 0) {
				alert(__("No articles are selected."));
				return;
			}

			id = ids.toString();
		}

		if (dijit.byId("emailArticleDlg"))
			dijit.byId("emailArticleDlg").destroyRecursive();

		var query = "backend.php?op=dlg&id=emailArticle&param=" + param_escape(id);

		dialog = new dijit.Dialog({
			id: "emailArticleDlg",
			title: __("Forward article by email"),
			style: "width: 600px",
			execute: function() {
				if (this.validate()) {

					new Ajax.Request("backend.php", {
						parameters: dojo.objectToQuery(this.attr('value')),
						onComplete: function(transport) { 

							var reply = JSON.parse(transport.responseText);
		
							var error = reply['error'];
			
							if (error) {
								alert(__('Error sending email:') + ' ' + error);
							} else {
								notify_info('Your message has been sent.');
								dialog.hide();
							}
			
					} });
				}
			},
			href: query});

		var tmph = dojo.connect(dialog, 'onLoad', function() {
	   	dojo.disconnect(tmph);

		   new Ajax.Autocompleter('emailArticleDlg_destination', 'emailArticleDlg_dst_choices',
			   "backend.php?op=rpc&subop=completeEmails",
			   { tokens: '', paramName: "search" });
		});

		dialog.show();

		/* displayDlg('emailArticle', id, 
		   function () {				
				document.forms['article_email_form'].destination.focus();

			   new Ajax.Autocompleter('destination', 'destination_choices',
				   "backend.php?op=rpc&subop=completeEmails",
				   { tokens: '', paramName: "search" });

			}); */

	} catch (e) {
		exception_error("emailArticle", e);
	}
}

function dismissArticle(id) {
	try {
		var elem = $("RROW-" + id);

		toggleUnread(id, 0, true);

		new Effect.Fade(elem, {duration : 0.5});

		active_post_id = false;

	} catch (e) {
		exception_error("dismissArticle", e);
	}
}

function dismissSelectedArticles() {
	try {

		var ids = getVisibleArticleIds();
		var tmp = [];
		var sel = [];

		for (var i = 0; i < ids.length; i++) {
			var elem = $("RROW-" + ids[i]);

			if (elem.className && elem.hasClassName("Selected") && 
					ids[i] != active_post_id) {
				new Effect.Fade(elem, {duration : 0.5});
				sel.push(ids[i]);
			} else {
				tmp.push(ids[i]);
			}
		}

		if (sel.length > 0)
			selectionToggleUnread(false);

		fixHeadlinesOrder(tmp);

	} catch (e) {
		exception_error("dismissSelectedArticles", e);
	}
}

function dismissReadArticles() {
	try {

		var ids = getVisibleArticleIds();
		var tmp = [];

		for (var i = 0; i < ids.length; i++) {
			var elem = $("RROW-" + ids[i]);

			if (elem.className && !elem.hasClassName("Unread") && 
					!elem.hasClassName("Selected")) {
			
				new Effect.Fade(elem, {duration : 0.5});
			} else {
				tmp.push(ids[i]);
			}
		}

		fixHeadlinesOrder(tmp);

	} catch (e) {
		exception_error("dismissSelectedArticles", e);
	}
}

function getVisibleArticleIds() {
	var ids = [];

	try {
		
		getLoadedArticleIds().each(function(id) {
			var elem = $("RROW-" + id);
			if (elem && Element.visible(elem))
				ids.push(id);
			});

	} catch (e) {
		exception_error("getVisibleArticleIds", e);
	}

	return ids;
}

function cdmClicked(event, id) {
	try {
		var shift_key = event.shiftKey;

		hideAuxDlg();

		if (!event.ctrlKey) {

			if (!getInitParam("cdm_expanded")) {
				return cdmExpandArticle(id);
			} else {

				selectArticles("none");
				toggleSelected(id);
	
				var elem = $("RROW-" + id);
	
				if (elem)
					elem.removeClassName("Unread");
	
				var upd_img_pic = $("FUPDPIC-" + id);
	
				if (upd_img_pic && (upd_img_pic.src.match("updated.png") || 
						upd_img_pic.src.match("fresh_sign.png"))) {
	
					upd_img_pic.src = "images/blank_icon.gif";
				}
	
				active_post_id = id;
	
				var query = "?op=rpc&subop=catchupSelected" +
					"&cmode=0&ids=" + param_escape(id);
	
				new Ajax.Request("backend.php", {
					parameters: query,
					onComplete: function(transport) { 
						handle_json_reply(transport); 
					} });
			}

		} else {
			toggleSelected(id, true);
			toggleUnread(id, 0, false);
			zoomToArticle(event, id);
		}

	} catch (e) {
		exception_error("cdmClicked");
	}

	return false;
}

function postClicked(event, id) {
	try {

		if (!event.ctrlKey) {
			return true;
		} else {
			postOpenInNewTab(event, id);
			return false;
		}

	} catch (e) {
		exception_error("postClicked");
	}
}

function hlOpenInNewTab(event, id) {
	toggleUnread(id, 0, false);
	zoomToArticle(event, id);
}

function postOpenInNewTab(event, id) {
	closeArticlePanel(id);
	zoomToArticle(event, id);
}

function hlClicked(event, id) {
	try {
		if (event.altKey) {
			openArticleInNewWindow(id);
		} else if (!event.ctrlKey) {
			view(id);
			return true;
		} else {
			toggleSelected(id);
			toggleUnread(id, 0, false);
			zoomToArticle(event, id);
			return false;
		}

	} catch (e) {
		exception_error("hlClicked");
	}
}

function getFirstVisibleHeadlineId() {
	var rows = getVisibleArticleIds();
	return rows[0];
	
}

function getLastVisibleHeadlineId() {
	var rows = getVisibleArticleIds();
	return rows[rows.length-1];
}

function openArticleInNewWindow(id) {
	toggleUnread(id, 0, false);
	window.open("backend.php?op=la&id=" + id);
}

function isCdmMode() {
	return getInitParam("combined_display_mode");
}

function markHeadline(id) {
	var row = $("RROW-" + id);
	if (row) {
		var check = $("RCHK-" + id);

		if (check) {
			check.checked = true;
		}

		row.addClassName("Selected");
	}
}

function getRelativePostIds(id, limit) {

	var tmp = [];

	try {

		if (!limit) limit = 3;
	
		var ids = getVisibleArticleIds();
	
		for (var i = 0; i < ids.length; i++) {
			if (ids[i] == id) {
				for (var k = 1; k <= limit; k++) {
					if (i > k-1) tmp.push(ids[i-k]);
					if (i < ids.length-k) tmp.push(ids[i+k]);
				}
				break;
			}
		}

	} catch (e) {
		exception_error("getRelativePostIds", e);
	}

	return tmp;
}

function correctHeadlinesOffset(id) {
	
	try {

		var container = $("headlines-frame");
		var row = $("RROW-" + id);
	
		var viewport = container.offsetHeight;
	
		var rel_offset_top = row.offsetTop - container.scrollTop;
		var rel_offset_bottom = row.offsetTop + row.offsetHeight - container.scrollTop;
	
		//console.log("Rtop: " + rel_offset_top + " Rbtm: " + rel_offset_bottom);
		//console.log("Vport: " + viewport);

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

function headlineActionsChange(elem) {
	try {
		eval(elem.value);
		elem.attr('value', 'false');
	} catch (e) {
		exception_error("headlineActionsChange", e);
	}
}

function closeArticlePanel() {

	var tabs = dijit.byId("content-tabs");
	var child = tabs.selectedChildWidget;

	if (child && tabs.getIndexOfChild(child) > 0) {
		tabs.removeChild(child);
		child.destroy();
	} else {
		if (dijit.byId("content-insert"))
			dijit.byId("headlines-wrap-inner").removeChild(
				dijit.byId("content-insert"));
	}
}

function initHeadlinesMenu() {
	try {
		if (dijit.byId("headlinesMenu"))
			dijit.byId("headlinesMenu").destroyRecursive();

		var ids = [];

		if (!isCdmMode()) {
			nodes = $$("#headlines-frame > div[id*=RROW]");
		} else {
			nodes = $$("#headlines-frame span[id*=RTITLE]");
		}

		nodes.each(function(node) {
			ids.push(node.id);
		});

		var menu = new dijit.Menu({
			id: "headlinesMenu",
			targetNodeIds: ids,
		});

		var tmph = dojo.connect(menu, '_openMyself', function (event) {
			var callerNode = event.target, match = null, tries = 0;

			while (match == null && callerNode && tries <= 3) {
				match = callerNode.id.match("^[A-Z]+[-]([0-9]+)$");
				callerNode = callerNode.parentNode;
				++tries;
			}

			if (match) this.callerRowId = parseInt(match[1]);

		});

		if (!isCdmMode())
			menu.addChild(new dijit.MenuItem({
				label: __("View article"),
				onClick: function(event) {
					view(this.getParent().callerRowId);
				}}));

		menu.addChild(new dijit.MenuItem({
			label: __("View in a new tab"),
			onClick: function(event) {
				hlOpenInNewTab(event, this.getParent().callerRowId);
				}}));

		menu.addChild(new dijit.MenuSeparator());

		menu.addChild(new dijit.MenuItem({
			label: __("Open original article"),
			onClick: function(event) {
				openArticleInNewWindow(this.getParent().callerRowId);
			}}));

		var labels = dijit.byId("feedTree").model.getItemsInCategory(-2);

		if (labels) {

			menu.addChild(new dijit.MenuSeparator());

			var labelsMenu = new dijit.Menu({ownerMenu: menu});

			labels.each(function(label) {
				var id = label.id[0];
				var bare_id = id.substr(id.indexOf(":")+1);
				var name = label.name[0];

				bare_id = -11-bare_id;

				labelsMenu.addChild(new dijit.MenuItem({
					label: name,
					labelId: bare_id,
					onClick: function(event) {
						//console.log(this.labelId);
						//console.log(this.getParent().ownerMenu.callerRowId);
						selectionAssignLabel(this.labelId, 
							[this.getParent().ownerMenu.callerRowId]);
				}}));
			});

			menu.addChild(new dijit.PopupMenuItem({
				label: __("Labels"),
				popup: labelsMenu,
			}));
		}

		menu.startup();

	} catch (e) {
		exception_error("initHeadlinesMenu", e);
	}
}

function tweetArticle(id) {
	try {
		var query = "?op=rpc&subop=getTweetInfo&id=" + param_escape(id);

		console.log(query);

		var d = new Date();
      var ts = d.getTime();

		var w = window.open('backend.php?op=loading', 'ttrss_tweet',
			"status=0,toolbar=0,location=0,width=500,height=400,scrollbars=1,menubar=0");

		new Ajax.Request("backend.php",	{
			parameters: query, 
			onComplete: function(transport) {
				var ti = JSON.parse(transport.responseText);

				var share_url = "http://twitter.com/share?_=" + ts +
					"&text=" + param_escape(ti.title) + 
					"&url=" + param_escape(ti.link);
						
				w.location.href = share_url;

			} });


	} catch (e) {
		exception_error("tweetArticle", e);
	}
}

function editArticleNote(id) {
	try {

		var query = "backend.php?op=dlg&id=editArticleNote&param=" + param_escape(id);

		if (dijit.byId("editNoteDlg"))
			dijit.byId("editNoteDlg").destroyRecursive();

		dialog = new dijit.Dialog({
			id: "editNoteDlg",
			title: __("Edit article note"),
			style: "width: 600px",
			execute: function() {
				if (this.validate()) {
					var query = dojo.objectToQuery(this.attr('value'));

					notify_progress("Saving article note...", true);

					new Ajax.Request("backend.php",	{
					parameters: query,
					onComplete: function(transport) {
						notify('');
						dialog.hide();
					
						var reply = JSON.parse(transport.responseText);

						cache_invalidate(id);

						var elem = $("POSTNOTE-" + id);

						if (elem) {
							Element.hide(elem);
							elem.innerHTML = reply.note;
							new Effect.Appear(elem);
						}

					}});
				}
			},
			href: query,
		});

		dialog.show();

	} catch (e) {
		exception_error("editArticleNote", e);
	}
}

function player(elem) {
	var aid = elem.getAttribute("audio-id");
	var status = elem.getAttribute("status");

	var audio = $(aid);

	if (audio) {
		if (status == 0) {
			audio.play();
			status = 1;
			elem.innerHTML = __("Playing...");
			elem.title = __("Click to pause");
			elem.addClassName("playing");
		} else {
			audio.pause();
			status = 0;
			elem.innerHTML = __("Play");
			elem.title = __("Click to play");
			elem.removeClassName("playing");
		}

		elem.setAttribute("status", status);
	} else {
		alert("Your browser doesn't seem to support HTML5 audio.");
	}
}


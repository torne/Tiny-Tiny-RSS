var active_post_id = false;
var last_article_view = false;
var active_real_feed_id = false;

// FIXME: kludges, needs proper implementation
var _reload_feedlist_after_view = false;

var _cdm_wd_timeout = false;
var _cdm_wd_vishist = new Array();

var article_cache = new Array();

var vgroup_last_feed = false;
var post_under_pointer = false;

var last_requested_article = false;

function catchup_callback2(transport, callback) {
	try {
		console.log("catchup_callback2 " + transport + ", " + callback);
		notify("");			
		all_counters_callback2(transport);
		if (callback) {
			setTimeout(callback, 10);	
		}
	} catch (e) {
		exception_error("catchup_callback2", e, transport);
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

function headlines_callback2(transport, feed_cur_page) {
	try {

		if (!transport.responseText && db) {
			offlineConfirmModeChange();
			return;
		}

		loading_set_progress(100);

		console.log("headlines_callback2 [page=" + feed_cur_page + "]");

		if (!transport_error_check(transport)) return;

		clean_feed_selections();
	
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

		var ll = $('FLL-' + feed_id);

		if (!is_cat) {
			var feedr = $("FEEDR-" + feed_id);
			if (feedr && !feedr.className.match("Selected")) {	
				feedr.className = feedr.className + "Selected";
			} 
			if (feedr && ll) feedr.removeChild(ll);
		} else {
			var feedr = $("FCAT-" + feed_id);
			if (feedr && !feedr.className.match("Selected")) {	
				feedr.className = feedr.className + "Selected";
			} 

			var fcap = $("FCAP-" + feed_id);
			if (fcap && ll) fcap.removeChild(ll);

		}

		var img = $('FIMG-' + feed_id);

		if (img && !is_cat) {
			img.src = img.alt;
		}

		var f = $("headlines-frame");
		try {
			if (feed_cur_page == 0) { 
				console.log("resetting headlines scrollTop");
				f.scrollTop = 0; 
			}
		} catch (e) { };
	
		if (transport.responseXML) {
			var response = transport.responseXML;

			var headlines = response.getElementsByTagName("headlines")[0];
			var headlines_info = response.getElementsByTagName("headlines-info")[0];

			if (headlines_info)
				headlines_info = JSON.parse(headlines_info.firstChild.nodeValue);
			else
				console.log("didn't find headlines-info object in response");

			var headlines_count = headlines_info.count;
			var headlines_unread = headlines_info.unread;
			var disable_cache = headlines_info.disable_cache;
			
			vgroup_last_feed = headlines_info.vgroup_last_feed;

			if (headlines_count == 0) {
				_infscroll_disable = 1;
			} else {
				_infscroll_disable = 0;
			}

			var counters = response.getElementsByTagName("counters")[0];
			var articles = response.getElementsByTagName("article");
			var runtime_info = response.getElementsByTagName("runtime-info");
	
			if (feed_cur_page == 0) {
				if (headlines) {
					f.innerHTML = headlines.firstChild.nodeValue;

					var cache_prefix = "";

					if (is_cat) {
						cache_prefix = "C:";
					} else {
						cache_prefix = "F:";
					}

					cache_invalidate(cache_prefix + feed_id);

					if (!disable_cache) {
						cache_inject(cache_prefix + feed_id,
							headlines.firstChild.nodeValue, headlines_unread);
					}

				} else {
					console.log("headlines_callback: returned no data");
				f.innerHTML = "<div class='whiteBox'>" + __('Could not update headlines (missing XML data)') + "</div>";
	
				}
			} else {
				if (headlines) {
					if (headlines_count > 0) {
						console.log("adding some more headlines...");
	
						var c = $("headlinesList");
		
						if (!c) {
							c = $("headlinesInnerContainer");
						}

						var ids = getSelectedArticleIds2();
	
						c.innerHTML = c.innerHTML + headlines.firstChild.nodeValue;

						console.log("restore selected ids: " + ids);

						for (var i = 0; i < ids.length; i++) {
							markHeadline(ids[i]);
						}

					} else {
						console.log("no new headlines received");
					}
				} else {
					console.log("headlines_callback: returned no data");
					notify_error("Error while trying to load more headlines");	
				}

			}
	
			if (articles) {
				for (var i = 0; i < articles.length; i++) {
					var a_id = articles[i].getAttribute("id");
					console.log("found id: " + a_id);
					cache_inject(a_id, articles[i].firstChild.nodeValue);
				}
			} else {
				console.log("no cached articles received");
			}
	
			if (counters) {
				console.log("parsing piggybacked counters: " + counters);
				parse_counters(counters, false);
			} else {
				console.log("counters container not found in reply, requesting...");
				request_counters();
			}
	
			if (runtime_info) {
				console.log("parsing runtime info: " + runtime_info[0]);
				parse_runtime_info(runtime_info[0]);
			} else {
				console.log("counters container not found in reply");
			}
	
		} else {
			console.log("headlines_callback: returned no XML object");
			f.innerHTML = "<div class='whiteBox'>" + __('Could not update headlines (missing XML object)') + "</div>";
		}
	
		if (typeof correctPNG != 'undefined') {
			correctPNG();
		}
	
		if (_cdm_wd_timeout) window.clearTimeout(_cdm_wd_timeout);
	
		if (!$("headlinesList") && 
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

		remove_splash();

	} catch (e) {
		exception_error("headlines_callback2", e, transport);
	}
}

function render_article(article) {
	try {
		var f = $("content-frame");
		try {
			f.scrollTop = 0;
		} catch (e) { };

		var fi = $("content-insert");

		try {
			fi.scrollTop = 0;
		} catch (e) { };
		
		fi.innerHTML = article;
		
//		article.evalScripts();		

	} catch (e) {
		exception_error("render_article", e);
	}
}

function showArticleInHeadlines(id) {

	try {

		cleanSelected("headlinesList");
	
		var crow = $("RROW-" + id);

		if (!crow) return;

		var article_is_unread = crow.className.match("Unread");
			
		crow.className = crow.className.replace("Unread", "");

		selectTableRowsByIdPrefix('headlinesList', 'RROW-', 'RCHK-', false);
	
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
				get_feed_unread(getActiveFeedId()));

		} else if (article_is_unread && view_mode == "all_articles") {

			cache_invalidate(cache_prefix + getActiveFeedId());

			cache_inject(cache_prefix + getActiveFeedId(),
				$("headlines-frame").innerHTML,
				get_feed_unread(getActiveFeedId())-1);

		} else if (article_is_unread) {
			cache_invalidate(cache_prefix + getActiveFeedId());
		}

		markHeadline(id);

	} catch (e) {
		exception_error("showArticleInHeadlines", e);
	}
}

function article_callback2(transport, id) {
	try {
		console.log("article_callback2 " + id);

		if (!transport.responseText && db) {
			offlineConfirmModeChange();
			return;
		}

		if (transport.responseXML) {

			if (!transport_error_check(transport)) return;

/*			var ll = $('LL-' + id);
			var content = $('HLC-' + id);

			if (ll && content) content.removeChild(ll); */
			
			var upic = $('FUPDPIC-' + id);

			if (upic) {
				upic.src = 'images/blank_icon.gif';
			}

			if (id != last_requested_article) {
				console.log("requested article id is out of sequence, aborting");
				return;
			}

			active_post_id = id; 

			console.log("looking for articles to cache...");

			var articles = transport.responseXML.getElementsByTagName("article");

			for (var i = 0; i < articles.length; i++) {
				var a_id = articles[i].getAttribute("id");

				console.log("found id: " + a_id);

				if (a_id == active_post_id) {
					console.log("active article, rendering...");					
					render_article(articles[i].firstChild.nodeValue);
				}

				cache_inject(a_id, articles[i].firstChild.nodeValue);
			}


			showArticleInHeadlines(id);	

			if (db) {
				db.execute("UPDATE articles SET unread = 0 WHERE id = ?", [id]);
			}

			var reply = transport.responseXML.firstChild.firstChild;
		
		} else {
			console.log("article_callback: returned no XML object");
			//var f = $("content-frame");
			//f.innerHTML = "<div class='whiteBox'>" + __('Could not display article (missing XML object)') + "</div>";
		}

		var date = new Date();
		last_article_view = date.getTime() / 1000;

		if (typeof correctPNG != 'undefined') {
			correctPNG();
		}

		if (_reload_feedlist_after_view) {
			setTimeout('updateFeedList(false, false)', 50);			
			_reload_feedlist_after_view = false;
		} else {
			if (transport.responseXML) {
				var counters = transport.responseXML.getElementsByTagName("counters")[0];

				if (counters) {
					console.log("parsing piggybacked counters: " + counters);
					parse_counters(counters, false);
				} else {
					console.log("counters container not found in reply, requesting...");
					request_counters();
				}
			}
		}

		notify("");
	} catch (e) {
		exception_error("article_callback2", e, transport);
	}
}

function view(id) {
	try {
		console.log("loading article: " + id);

		if (offline_mode) return view_offline(id);

		var cached_article = cache_find(id);

		console.log("cache check result: " + (cached_article != false));
	
		enableHotkeys();
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

		/* additional info for piggyback counters */

		if (tagsAreDisplayed()) {
			query = query + "&omode=lt";
		} else {
			query = query + "&omode=flc";
		}

		query = query + "&cids=" + cids_to_request.toString();

		var crow = $("RROW-" + id);
		var article_is_unread = crow.className.match("Unread");

		if (!async_counters_work) {
			query = query + "&csync=true";
		}

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

function tMark_afh_off(effect) {
	try {
		var elem = effect.effects[0].element;

		console.log("tMark_afh_off : " + elem.id);

		if (elem) {
			elem.src = elem.src.replace("mark_set", "mark_unset");
			elem.alt = __("Star article");
			Element.show(elem);
		}

	} catch (e) {
		exception_error("tMark_afh_off", e);
	}
}

function tPub_afh_off(effect) {
	try {
		var elem = effect.effects[0].element;

		console.log("tPub_afh_off : " + elem.id);

		if (elem) {
			elem.src = elem.src.replace("pub_set", "pub_unset");
			elem.alt = __("Publish article");
			Element.show(elem);
		}

	} catch (e) {
		exception_error("tPub_afh_off", e);
	}
}

function toggleMark(id, client_only, no_effects) {

	try {

		var query = "?op=rpc&id=" + id + "&subop=mark";
	
		query = query + "&afid=" + getActiveFeedId();
	
		if (tagsAreDisplayed()) {
			query = query + "&omode=tl";
		} else {
			query = query + "&omode=flc";
		}
	
		var mark_img = $("FMPIC-" + id);

		if (!mark_img) return;

		var vfeedu = $("FEEDU--1");
		var crow = $("RROW-" + id);
	
		if (mark_img.src.match("mark_unset")) {
			mark_img.src = mark_img.src.replace("mark_unset", "mark_set");
			mark_img.alt = __("Unstar article");
			query = query + "&mark=1";

			if (db) {
				db.execute("UPDATE articles SET marked = 1 WHERE id = ?", [id]);
			}

		} else {
			mark_img.alt = __("Please wait...");
			query = query + "&mark=0";
	
			if ($("headlinesList") && !no_effects) {
				Effect.Puff(mark_img, {duration : 0.25, afterFinish: tMark_afh_off});
			} else { 
				mark_img.src = mark_img.src.replace("mark_set", "mark_unset");
				mark_img.alt = __("Star article");
			}

			if (db) {
				db.execute("UPDATE articles SET marked = 0 WHERE id = ?", [id]);
			}

		}

		if (!no_effects) update_local_feedlist_counters();

		if (!client_only) {
			console.log(query);

			new Ajax.Request("backend.php", {
				parameters: query,
				onComplete: function(transport) { 
					all_counters_callback2(transport); 
				} });

		}

	} catch (e) {
		exception_error("toggleMark", e);
	}
}

function togglePub(id, client_only, no_effects, note) {

	try {

		var query = "?op=rpc&id=" + id + "&subop=publ";
	
		query = query + "&afid=" + getActiveFeedId();

		if (note != undefined) {
			query = query + "&note=" + param_escape(note);
		} else {
			query = query + "&note=undefined";
		}
	
		if (tagsAreDisplayed()) {
			query = query + "&omode=tl";
		} else {
			query = query + "&omode=flc";
		}
	
		var mark_img = $("FPPIC-" + id);

		if (!mark_img) return;

		var vfeedu = $("FEEDU--2");
		var crow = $("RROW-" + id);
	
		if (mark_img.src.match("pub_unset") || note != undefined) {
			mark_img.src = mark_img.src.replace("pub_unset", "pub_set");
			mark_img.alt = __("Unpublish article");
			query = query + "&pub=1";

		} else {
			mark_img.alt = __("Please wait...");
			query = query + "&pub=0";
	
			if ($("headlinesList") && !no_effects) {
				Effect.Puff(mark_img, {duration : 0.25, afterFinish: tPub_afh_off});
			} else { 
				mark_img.src = mark_img.src.replace("pub_set", "pub_unset");
				mark_img.alt = __("Publish article");
			}
		}

		if (!client_only) {
			new Ajax.Request("backend.php", {
				parameters: query,
				onComplete: function(transport) { 
					all_counters_callback2(transport);
		
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
		}

	} catch (e) {
		exception_error("togglePub", e);
	}
}

function correctHeadlinesOffset(id) {
	
	try {

		var hlist = $("headlinesList");
		var container = $("headlinesInnerContainer");
		var row = $("RROW-" + id);
	
		var viewport = container.offsetHeight;
	
		var rel_offset_top = row.offsetTop - container.scrollTop;
		var rel_offset_bottom = row.offsetTop + row.offsetHeight - container.scrollTop;
	
		console.log("Rtop: " + rel_offset_top + " Rbtm: " + rel_offset_bottom);
		console.log("Vport: " + viewport);

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

function moveToPost(mode) {

	try {

		var rows;

		if (isCdmMode()) {
			rows = cdmGetVisibleArticles();
		} else {
			rows = getVisibleHeadlineIds();
		}
		
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
	
					if (!cdmArticleIsActuallyVisible(next_id)) {
						cdmScrollToArticleId(next_id);
					}
					cdmSelectArticles("none");
					toggleUnread(next_id, 0, true);
					toggleSelected(next_id);

				} else {
					correctHeadlinesOffset(next_id);
					view(next_id, getActiveFeedId());
				}
			}
		}
		
		if (mode == "prev") {
			if (prev_id) {
				if (isCdmMode()) {
					cdmScrollToArticleId(prev_id);
					cdmSelectArticles("none");
					toggleUnread(prev_id, 0, true);
					toggleSelected(prev_id);
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

function toggleSelected(id) {
	try {
	
		var cb = $("RCHK-" + id);

		var row = $("RROW-" + id);
		if (row) {
			var nc = row.className;
			
			if (!nc.match("Selected")) {
				nc = nc + "Selected";
				if (cb) {
					cb.checked = true;
				}

				// In CDM basically last selected article == active article
				if (isCdmMode()) active_post_id = id;
			} else {
				nc = nc.replace("Selected", "");
				if (cb) {
					cb.checked = false;
				}

			}

			row.className = nc;
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
			var nc = row.className;
			var is_selected = row.className.match("Selected");
			nc = nc.replace("Unread", "");
			nc = nc.replace("Selected", "");

			// since we are removing selection from the object, uncheck
			// corresponding checkbox

			var cb = $("RCHK-" + id);
			if (cb) {
				cb.checked = false;
			}

			// NOTE: I'm not sure that resetting selection here is a feature -fox

			if (cmode == undefined || cmode == 2) {
				if (row.className.match("Unread")) {
					row.className = nc;

					if (effect) {
						new Effect.Highlight(row, {duration: 1, startcolor: "#fff7d5",
							afterFinish: toggleUnread_afh,
							queue: { position:'end', scope: 'TMRQ-' + id, limit: 1 } } );
					} 

				} else {
					row.className = nc + "Unread";
				}

				if (db) {
					db.execute("UPDATE articles SET unread = not unread "+
						"WHERE id = ?", [id]);
				}

			} else if (cmode == 0) {
				row.className = nc;

				if (effect) {
					new Effect.Highlight(row, {duration: 1, startcolor: "#fff7d5",
						afterFinish: toggleUnread_afh,
						queue: { position:'end', scope: 'TMRQ-' + id, limit: 1 } } );
				} 

				if (db) {
					db.execute("UPDATE articles SET unread = 0 "+
						"WHERE id = ?", [id]);
				}

			} else if (cmode == 1) {
				row.className = nc + "Unread";

				if (db) {
					db.execute("UPDATE articles SET unread = 1 "+
						"WHERE id = ?", [id]);
				}

			}

			update_local_feedlist_counters();

			// Disable unmarking as selected for the time being (16.05.08) -fox
			if (is_selected) row.className = row.className + "Selected";

			if (cmode == undefined) cmode = 2;

			var query = "?op=rpc&subop=catchupSelected" +
				"&cmode=" + param_escape(cmode) + "&ids=" + param_escape(id);

//			notify_progress("Loading, please wait...");

			new Ajax.Request("backend.php", {
				parameters: query,
				onComplete: function(transport) { 
					all_counters_callback2(transport); 
				} });

		}

	} catch (e) {
		exception_error("toggleUnread", e);
	}
}

function selectionRemoveLabel(id) {
	try {

		var ids = getSelectedArticleIds2();

		if (ids.length == 0) {
			alert(__("No articles are selected."));
			return;
		}

//		var ok = confirm(__("Remove selected articles from label?"));

//		if (ok) {

			var query = "?op=rpc&subop=removeFromLabel&ids=" +
				param_escape(ids.toString()) + "&lid=" + param_escape(id);

//			notify_progress("Loading, please wait...");

			cache_invalidate("F:" + (-11 - id));

			new Ajax.Request("backend.php", {
				parameters: query,
				onComplete: function(transport) { 
					show_labels_in_headlines(transport);
					all_counters_callback2(transport);
				} });

//		}

	} catch (e) {
		exception_error("selectionAssignLabel", e);

	}
}

function selectionAssignLabel(id) {
	try {

		var ids = getSelectedArticleIds2();

		if (ids.length == 0) {
			alert(__("No articles are selected."));
			return;
		}

//		var ok = confirm(__("Assign selected articles to label?"));

//		if (ok) {

			cache_invalidate("F:" + (-11 - id));

			var query = "?op=rpc&subop=assignToLabel&ids=" +
				param_escape(ids.toString()) + "&lid=" + param_escape(id);

//			notify_progress("Loading, please wait...");

			new Ajax.Request("backend.php", {
				parameters: query,
				onComplete: function(transport) { 
					show_labels_in_headlines(transport);
					all_counters_callback2(transport);
				} });

//		}

	} catch (e) {
		exception_error("selectionAssignLabel", e);

	}
}

function selectionToggleUnread(set_state, callback_func, no_error) {
	try {
		var rows;

		if (isCdmMode()) {
			rows = cdmGetSelectedArticles();
		} else {	
			rows = getSelectedTableRowIds("headlinesList", "RROW", "RCHK");
		}

		if (rows.length == 0 && !no_error) {
			alert(__("No articles are selected."));
			return;
		}

		for (i = 0; i < rows.length; i++) {
			var row = $("RROW-" + rows[i]);
			if (row) {
				var nc = row.className;
				nc = nc.replace("Unread", "");
				nc = nc.replace("Selected", "");

				if (set_state == undefined) {
					if (row.className.match("Unread")) {
						row.className = nc + "Selected";
					} else {
						row.className = nc + "UnreadSelected";
					}
					if (db) {
						db.execute("UPDATE articles SET unread = NOT unread WHERE id = ?", 
							[rows[i]]);
					}
				}

				if (set_state == false) {
					row.className = nc + "Selected";
					if (db) {
						db.execute("UPDATE articles SET unread = 0 WHERE id = ?", 
							[rows[i]]);
					}
				}

				if (set_state == true) {
					row.className = nc + "UnreadSelected";
					if (db) {
						db.execute("UPDATE articles SET unread = 1 WHERE id = ?", 
							[rows[i]]);
					}
				}
			}
		}

		if (rows.length > 0) {

			update_local_feedlist_counters();

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
					catchup_callback2(transport, callback_func); 
				} });

		}

	} catch (e) {
		exception_error("selectionToggleUnread", e);
	}
}

function selectionToggleMarked() {
	try {
	
		var rows;
		
		if (isCdmMode()) {
			rows = cdmGetSelectedArticles();
		} else {	
			rows = getSelectedTableRowIds("headlinesList", "RROW", "RCHK");
		}	

		if (rows.length == 0) {
			alert(__("No articles are selected."));
			return;
		}

		for (i = 0; i < rows.length; i++) {
			toggleMark(rows[i], true, true);
		}

		update_local_feedlist_counters();

		if (rows.length > 0) {

			var query = "?op=rpc&subop=markSelected&ids=" +
				param_escape(rows.toString()) + "&cmode=2";

			query = query + "&afid=" + getActiveFeedId();

			query = query + "&omode=lc";

			new Ajax.Request("backend.php", {
				parameters: query,
				onComplete: function(transport) { 
					all_counters_callback2(transport); 
				} });

		}

	} catch (e) {
		exception_error("selectionToggleMarked", e);
	}
}

function selectionTogglePublished() {
	try {
	
		var rows;
		
		if (isCdmMode()) {
			rows = cdmGetSelectedArticles();
		} else {	
			rows = getSelectedTableRowIds("headlinesList", "RROW", "RCHK");
		}	

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

			query = query + "&afid=" + getActiveFeedId();

			query = query + "&omode=lc";

			new Ajax.Request("backend.php", {
				parameters: query,
				onComplete: function(transport) { 
					all_counters_callback2(transport); 
				} });

		}

	} catch (e) {
		exception_error("selectionToggleMarked", e);
	}
}

function cdmGetSelectedArticles() {
	var sel_articles = new Array();
	var container = $("headlinesInnerContainer");

	for (i = 0; i < container.childNodes.length; i++) {
		var child = container.childNodes[i];

		if (child.id && child.id.match("RROW-") && child.className.match("Selected")) {
			var c_id = child.id.replace("RROW-", "");
			sel_articles.push(c_id);
		}
	}

	return sel_articles;
}

function cdmGetVisibleArticles() {
	var sel_articles = new Array();
	var container = $("headlinesInnerContainer");

	if (!container) return sel_articles;

	for (i = 0; i < container.childNodes.length; i++) {
		var child = container.childNodes[i];

		if (child.id && child.id.match("RROW-")) {
			var c_id = child.id.replace("RROW-", "");
			sel_articles.push(c_id);
		}
	}

	return sel_articles;
}

function cdmGetUnreadArticles() {
	var sel_articles = new Array();
	var container = $("headlinesInnerContainer");

	for (i = 0; i < container.childNodes.length; i++) {
		var child = container.childNodes[i];

		if (child.id && child.id.match("RROW-") && child.className.match("Unread")) {
			var c_id = child.id.replace("RROW-", "");
			sel_articles.push(c_id);
		}
	}

	return sel_articles;
}


// mode = all,none,unread
function cdmSelectArticles(mode) {
	var container = $("headlinesInnerContainer");

	for (i = 0; i < container.childNodes.length; i++) {
		var child = container.childNodes[i];

		if (child.id && child.id.match("RROW-")) {
			var aid = child.id.replace("RROW-", "");

			var cb = $("RCHK-" + aid);

			if (mode == "all") {
				if (!child.className.match("Selected")) {
					child.className = child.className + "Selected";
					cb.checked = true;
				}
			} else if (mode == "unread") {
				if (child.className.match("Unread") && !child.className.match("Selected")) {
					child.className = child.className + "Selected";
					cb.checked = true;
				}
			} else {
				child.className = child.className.replace("Selected", "");
				cb.checked = false;
			}
		}		
	}
}

function catchupPage() {

	var fn = getFeedName(getActiveFeedId(), activeFeedIsCat());
	
	var str = __("Mark all visible articles in %s as read?");

	str = str.replace("%s", fn);

	if (getInitParam("confirm_feed_catchup") == 1 && !confirm(str)) {
		return;
	}

	if ($("headlinesList")) {
		selectTableRowsByIdPrefix('headlinesList', 'RROW-', 'RCHK-', true, 'Unread', true);
		selectionToggleUnread(false, 'viewCurrentFeed()', true);
		selectTableRowsByIdPrefix('headlinesList', 'RROW-', 'RCHK-', false);
	} else {
		cdmSelectArticles('all');
		selectionToggleUnread(false, 'viewCurrentFeed()', true)
		cdmSelectArticles('none');
	}
}

function deleteSelection() {

	try {

		var rows;
	
		if ($("headlinesList")) {
			rows = getSelectedTableRowIds("headlinesList", "RROW", "RCHK");
		} else {	
			rows = cdmGetSelectedArticles();
		}
	
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
					viewCurrentFeed();
				} });

	} catch (e) {
		exception_error("deleteSelection", e);
	}
}

function archiveSelection() {

	try {

		var rows;
	
		if ($("headlinesList")) {
			rows = getSelectedTableRowIds("headlinesList", "RROW", "RCHK");
		} else {	
			rows = cdmGetSelectedArticles();
		}
	
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
					viewCurrentFeed();
				} });

	} catch (e) {
		exception_error("archiveSelection", e);
	}
}

function catchupSelection() {

	try {

		var rows;
	
		if ($("headlinesList")) {
			rows = getSelectedTableRowIds("headlinesList", "RROW", "RCHK");
		} else {	
			rows = cdmGetSelectedArticles();
		}
	
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
	
		if ($("headlinesList")) {
			selectionToggleUnread(false, 'viewCurrentFeed()', true);
		} else {
			selectionToggleUnread(false, 'viewCurrentFeed()', true)
		}

	} catch (e) {
		exception_error("catchupSelection", e);
	}
}

function editArticleTags(id, feed_id, cdm_enabled) {
	displayDlg('editArticleTags', id,
			   function () {
				   new Ajax.Autocompleter('tags_str', 'tags_choices',
					   "backend.php?op=rpc&subop=completeTags",
					   { tokens: ',', paramName: "search" });
			   });
}

function editTagsSave() {

	notify_progress("Saving article tags...");

	var form = document.forms["tag_edit_form"];

	var query = Form.serialize("tag_edit_form");

	query = "?op=rpc&subop=setArticleTags&" + query;

	console.log(query);

	new Ajax.Request("backend.php",	{
		parameters: query,
		onComplete: function(transport) {
				try {
					console.log("tags saved...");
			
					closeInfoBox();
					notify("");
			
					if (tagsAreDisplayed()) {
						_reload_feedlist_after_view = true;
					}			
			
					if (transport.responseXML) {
						var tags_str = transport.responseXML.getElementsByTagName("tags-str")[0];
						
						if (tags_str) {
							var id = tags_str.getAttribute("id");
			
							if (id) {
								var tags = $("ATSTR-" + id);
								if (tags) {
									tags.innerHTML = tags_str.firstChild.nodeValue;
								}

								cache_invalidate(id);
							}
						}
					}
			
				} catch (e) {
					exception_error("editTagsSave", e);
				}
			} });
}

function editTagsInsert() {
	try {

		var form = document.forms["tag_edit_form"];

		var found_tags = form.found_tags;
		var tags_str = form.tags_str;

		var tag = found_tags[found_tags.selectedIndex].value;

		if (tags_str.value.length > 0 && 
				tags_str.value.lastIndexOf(", ") != tags_str.value.length - 2) {

			tags_str.value = tags_str.value + ", ";
		}

		tags_str.value = tags_str.value + tag + ", ";

		found_tags.selectedIndex = 0;
		
	} catch (e) {
		exception_error("editTagsInsert", e);
	}
}

function cdmScrollViewport(where) {
	console.log("cdmScrollViewport: " + where);

	var ctr = $("headlinesInnerContainer");

	if (!ctr) return;

	if (where == "bottom") {
		ctr.scrollTop = ctr.scrollHeight;
	} else {
		ctr.scrollTop = where;
	}
}

function cdmArticleIsBelowViewport(id) {
	try {
		var ctr = $("headlinesInnerContainer");
		var e = $("RROW-" + id);

		if (!e || !ctr) return;

		// article starts below viewport

		if (ctr.scrollTop < e.offsetTop) {
			return true;
		} else {	
			return false;
		}

	} catch (e) {
		exception_error("cdmArticleIsVisible", e);
	}
}

function cdmArticleIsAboveViewport(id) {
	try {
		var ctr = $("headlinesInnerContainer");
		var e = $("RROW-" + id);

		if (!e || !ctr) return;

		// article starts above viewport

		if (ctr.scrollTop > e.offsetTop + e.offsetHeight) {
			return true;
		} else {	
			return false;
		}

	} catch (e) {
		exception_error("cdmArticleIsVisible", e);
	}
}

function cdmScrollToArticleId(id) {
	try {
		var ctr = $("headlinesInnerContainer");
		var e = $("RROW-" + id);

		if (!e || !ctr) return;

		ctr.scrollTop = e.offsetTop;

	} catch (e) {
		exception_error("cdmScrollToArticleId", e);
	}
}

function cdmArticleIsActuallyVisible(id) {
	try {
		var ctr = $("headlinesInnerContainer");
		var e = $("RROW-" + id);

		if (!e || !ctr) return;

		// article fits in viewport OR article is longer than viewport and
		// its bottom is visible

		if (ctr.scrollTop <= e.offsetTop && e.offsetTop + e.offsetHeight <=
				ctr.scrollTop + ctr.offsetHeight) {

			return true;
		
		} else if (e.offsetHeight > ctr.offsetHeight &&
				e.offsetTop + e.offsetHeight >= ctr.scrollTop &&
				e.offsetTop + e.offsetHeight <= ctr.scrollTop + ctr.offsetHeight) {

			return true;

		}

		return false;

	} catch (e) {
		exception_error("cdmArticleIsVisible", e);
	}
}

function cdmWatchdog() {

	try {

		var ctr = $("headlinesInnerContainer");

		if (!ctr) return;

		var ids = new Array();

		var e = ctr.firstChild;

		while (e) {
			if (e.className && e.className == "cdmArticleUnread" && e.id &&
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
					e.className = e.className.replace("Unread", "");
				}
			}

			var query = "?op=rpc&subop=catchupSelected" +
				"&cmode=0" + "&ids=" + param_escape(ids.toString());

			new Ajax.Request("backend.php", {
				parameters: query,
				onComplete: function(transport) { 
					all_counters_callback2(transport); 
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
			console.log("cache_article: miss: " + id + " [p=" + param + "]");
	
			if (db) {

			   var date = new Date();
		      var ts = Math.round(date.getTime() / 1000);

				db.execute("INSERT INTO cache (id, article, param, added) VALUES (?, ?, ?, ?)",
					[id, article, param, ts]);				
			} else {
	
				var cache_obj = {};
	
				cache_obj["id"] = id;
				cache_obj["data"] = article;
				cache_obj["param"] = param;
				cache_obj["added"] = new Date();

				if (param) id = id + ":" + param;

				if (has_local_storage()) 
					localStorage.setItem(id, JSON.stringify(cache_obj));
				else
					article_cache.push(cache_obj);
			}
	
		} else {
			console.log("cache_article: hit: " + id + " [p=" + param + "]");
		}
	} catch (e) {	
		exception_error("cache_inject", e);
	}
}

function cache_find(id) {

	if (db) {
		var rs = db.execute("SELECT article FROM cache WHERE id = ?", [id]);
		var a = false;

		if (rs.isValidRow()) {
			var a = rs.field(0);			
		}

		rs.close();

		return a;

	} else {

		if (has_local_storage()) {
			var cache_obj = localStorage.getItem(id);
	
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
	}
	return false;
}

function cache_find_param(id, param) {

	if (db) {
		var rs = db.execute("SELECT article FROM cache WHERE id = ? AND param = ?",
			[id, param]);
		var a = false;

		if (rs.isValidRow()) {
			a = rs.field(0);
		}

		rs.close();

		return a;

	} else {

		if (has_local_storage()) {
			var cache_obj = localStorage.getItem(id + ":" + param);

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
	}
	return false;
}

function cache_check(id) {

	if (db) {
		var rs = db.execute("SELECT COUNT(*) AS c FROM cache WHERE id = ?",
			[id]);
		var a = false;

		if (rs.isValidRow()) {
			 a = rs.field(0) != "0";
		}

		rs.close();

		return a;

	} else {
		if (has_local_storage) {
			if (localStorage.getItem(id))
					return true;
		} else {
			for (var i = 0; i < article_cache.length; i++) {
				if (article_cache[i]["id"] == id) {
					return true;
				}
			}
		}
	}
	return false;
}

function cache_check_param(id, param) {

	if (db) {
		var rs = db.execute("SELECT COUNT(*) AS c FROM cache WHERE id = ? AND param = ?",
			[id, param]);
		var a = false;

		if (rs.isValidRow()) {
			a = rs.field(0) != "0";
		}

		rs.close();

		return a;

	} else {

		if (has_local_storage) {
			if (localStorage.getItem(id + ':' + param))
					return true;
		} else {
			for (var i = 0; i < article_cache.length; i++) {
				if (article_cache[i]["id"] == id && article_cache[i]["param"] == param) {
					return true;
				}
			}
		}
	}
	return false;
}

function cache_expire() {
	if (db) {
		var date = new Date();
		var ts = Math.round(date.getTime() / 1000);

		db.execute("DELETE FROM cache WHERE added < ? - 1800 AND id LIKE 'FEEDLIST'", [ts]);
		db.execute("DELETE FROM cache WHERE added < ? - 600 AND (id LIKE 'F:%' OR id LIKE 'C:%')", [ts]);
		db.execute("DELETE FROM cache WHERE added < ? - 86400", [ts]);


	} else {
		if (has_local_storage()) {
			while (localStorage.length > 25) {
				localStorage.removeItem(localStorage.key(localStorage.length-1));
			}
		} else {
			while (article_cache.length > 25) {
				article_cache.shift();
			}
		}
	}
}

function cache_flush() {
	article_cache = new Array();
}

function cache_invalidate(id) {
	try {	

		if (db) {
			rs = db.execute("DELETE FROM cache WHERE id = ?", [id]);
			return rs.rowsAffected != 0;
		} else {

			if (has_local_storage()) {
				for (var i = 0; i < localStorage.length; i++) {
					var key = localStorage.key(i);

					if (key == id || key.indexOf(id + ":") == 0)
						localStorage.removeItem(key);

				}
			} else {
				var i = 0

				while (i < article_cache.length) {
					if (article_cache[i]["id"] == id) {
						console.log("cache_invalidate: removed id " + id);
						article_cache.splice(i, 1);
						return true;
					}
					i++;
				}
			}
		}

		console.log("cache_invalidate: id not found: " + id);
		return false;
	} catch (e) {
		exception_error("cache_invalidate", e);
	}
}

function getActiveArticleId() {
	return active_post_id;
}

function cdmClicked(id) {
	try {
		var elem = $("RROW-" + id);

		if (elem) {
			var id = elem.id.replace("RROW-", "");
			active_post_id = id;

//			cdmSelectArticles("none");
			toggleUnread(id, 0, true);
//			toggleSelected(id);

		}
	} catch (e) {
		exception_error("cdmClicked", e);
	} 
}

function preloadArticleUnderPointer(id) {
	try {
		if (getInitParam("bw_limit") == "1") return;

		if (post_under_pointer == id && !cache_check(id)) {

			console.log("trying to preload article " + id);

			var neighbor_ids = getRelativePostIds(id, 1);

			/* only request uncached articles */

			var cids_to_request = Array();

			for (var i = 0; i < neighbor_ids.length; i++) {
				if (!cache_check(neighbor_ids[i])) {
					cids_to_request.push(neighbor_ids[i]);
				}
			}
			console.log("additional ids: " + cids_to_request.toString());

			cids_to_request.push(id);

			var query = "?op=rpc&subop=getArticles&ids=" + 
				cids_to_request.toString();

			new Ajax.Request("backend.php", {
				parameters: query,
				onComplete: function(transport) { 
					var articles = transport.responseXML.getElementsByTagName("article");

					for (var i = 0; i < articles.length; i++) {
						var id = articles[i].getAttribute("id");
						if (!cache_check(id)) {
							cache_inject(id, articles[i].firstChild.nodeValue);				
							console.log("preloaded article: " + id);
						}
					}
			} });
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

function headlines_scroll_handler() {
	try {

		var e = $("headlinesInnerContainer");

		var toolbar_form = document.forms["main_toolbar_form"];

		console.log((e.scrollTop + e.offsetHeight) + " vs " + e.scrollHeight + " dis? " +
			_infscroll_disable);

		if (e.scrollTop + e.offsetHeight > e.scrollHeight - 100) {
			if (!_infscroll_disable) {
				console.log("more cowbell!");
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

		var visible_ids;

		if ($("headlinesList")) {
			visible_ids = getVisibleHeadlineIds();
		} else {
			visible_ids = cdmGetVisibleArticles();
		}

		var ids_to_mark = new Array();

		if (!below) {
			for (var i = 0; i < visible_ids.length; i++) {
				if (visible_ids[i] != getActiveArticleId()) {
					var e = $("RROW-" + visible_ids[i]);

					if (e && e.className.match("Unread")) {
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

					if (e && e.className.match("Unread")) {
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
					e.className = e.className.replace("Unread", "");
				}

				var query = "?op=rpc&subop=catchupSelected" +
					"&cmode=0" + "&ids=" + param_escape(ids_to_mark.toString()); 

				new Ajax.Request("backend.php", {
					parameters: query,
					onComplete: function(transport) { 
						catchup_callback2(transport); 
					} });

			}
		}

	} catch (e) {
		exception_error("catchupRelativeToArticle", e);
	}
}

function cdmExpandArticle(a_id) {
	try {
		var id = 'CICD-' + a_id;

		try {
			Element.hide("CEXC-" + a_id);
		} catch (e) { } 

		Effect.Appear(id, {duration : 0.5, 
			beforeStart: function(effect) { 
				var h_id = 'CICH-' + a_id;
				var h_elem = $(h_id);
				if (h_elem) { h_elem.style.display = "none"; }

				toggleUnread(a_id, 0);
			}});


	} catch (e) {
		exception_error("cdmExpandArticle", e);
	}

}

function fixHeadlinesOrder(ids) {
	try {
		for (var i = 0; i < ids.length; i++) {
			var e = $("RROW-" + ids[i]);

			if (e) {
				if (i % 2 == 0) {
					e.className = e.className.replace("even", "odd");
				} else {
					e.className = e.className.replace("odd", "even");
				}
			}
		}
	} catch (e) {
		exception_error("fixHeadlinesOrder", e);
	}
}

function hideReadHeadlines() {
	try {

		var ids = false;
		var vis_ids = new Array();

		if ($("headlinesList")) {
			ids = getVisibleHeadlineIds();
		} else {
			ids = cdmGetVisibleArticles();
		}

		var read_headlines_visible = true;

		for (var i = 0; i < ids.length; i++) {
			var row = $("RROW-" + ids[i]);

			if (row && row.className) {
				if (read_headlines_visible) {
					if (row.className.match("Unread") || row.className.match("Selected")) {
						Element.show(row);
						vis_ids.push(ids[i]);
					} else {
						//Effect.Fade(row, {duration : 0.3});
						Element.hide(row);
					}
				} else {
					Element.show(row);
					vis_ids.push(ids[i]);
				}
			}
		}
		
		fixHeadlinesOrder(vis_ids);

		read_headlines_visible = !read_headlines_visible;

	} catch (e) {
		exception_error("hideReadHeadlines", e);
	} 
}

function invertHeadlineSelection() {
	try {
		var rows = new Array();
		var r = false;
		
		if (!isCdmMode()) {		
			r = document.getElementsByTagName("TR");
		} else {
			r = document.getElementsByTagName("DIV");
		}

		for (var i = 0; i < r.length; i++) {
			if (r[i].id && r[i].id.match("RROW-")) {
				rows.push(r[i]);
			}
		}
		
		for (var i = 0; i < rows.length; i++) {
			var nc = rows[i].className;
			var id = rows[i].id.replace("RROW-", "");
			var cb = $("RCHK-" + id);

			if (!rows[i].className.match("Selected")) {
				nc = nc + "Selected";
				cb.checked = true;
			} else {
				nc = nc.replace("Selected", "");
				cb.checked = false;
			}

			rows[i].className = nc;

		}

	} catch (e) {
		exception_error("invertHeadlineSelection", e);
	}
}

function getArticleUnderPointer() {
	return post_under_pointer;
}

function zoomToArticle(id) {
	try {
		var w = window.open("backend.php?op=view&mode=zoom&id=" + param_escape(id), 
			"ttrss_zoom_" + id,
			"status=0,toolbar=0,location=0,width=450,height=300,scrollbars=1,menubar=0");

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
			var hi = $("headlinesInnerContainer");
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
		if (transport.responseXML) {
			var info = transport.responseXML.getElementsByTagName("info-for-headlines")[0];

			var elems = info.getElementsByTagName("entry");

			for (var l = 0; l < elems.length; l++) {
				var e_id = elems[l].getAttribute("id");

				if (e_id) {

					var ctr = $("HLLCTR-" + e_id);

					if (ctr) {
						ctr.innerHTML = elems[l].firstChild.nodeValue;
					}
				}

			}

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

function publishWithNote(id, def_note) {
	try {
		if (!def_note) def_note = '';

		var note = prompt(__("Please enter a note for this article:"), def_note);

		if (note != undefined) {
			togglePub(id, false, false, note);
		}

	} catch (e) {
		exception_error("publishWithNote", e);
	}
}

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

		displayDlg('emailArticle', id, 
		   function () {				
				document.forms['article_email_form'].destination.focus();

			   new Ajax.Autocompleter('destination', 'destination_choices',
				   "backend.php?op=rpc&subop=completeEmails",
				   { tokens: '', paramName: "search" });

			});

	} catch (e) {
		exception_error("emailArticle", e);
	}
}

function emailArticleDo() {
	try {
		var f = document.forms['article_email_form'];

		if (f.destination.value == "") {
			alert("Please fill in the destination email.");
			return;
		}

		if (f.subject.value == "") {
			alert("Please fill in the subject.");
			return;
		}

		var query = Form.serialize("article_email_form");

//		console.log(query);

		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function(transport) { 
				try {

					var error = transport.responseXML.getElementsByTagName('error')[0];

					if (error) {
						alert(__('Error sending email:') + ' ' + error.firstChild.nodeValue);
					} else {
						notify_info('Your message has been sent.');
						closeInfoBox();
					}

				} catch (e) {
					exception_error("sendEmailDo", e);
				}

			} });

	} catch (e) {
		exception_error("emailArticleDo", e);
	}
}

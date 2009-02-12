var SCHEMA_VERSION = 10;

var offline_mode = false;
var store = false;
var localServer = false;
var db = false;
var articles_synced = 0;
var sync_in_progress = false;
var sync_timer = false;

function view_offline(id, feed_id) {
	try {

		enableHotkeys();
		showArticleInHeadlines(id);

		db.execute("UPDATE articles SET unread = 0 WHERE id = ?", [id]);

		var rs = db.execute("SELECT * FROM articles WHERE id = ?", [id]);

		if (rs.isValidRow()) {

			var tmp = "<div class=\"postReply\">";

			tmp += "<div class=\"postHeader\" onmouseover=\"enable_resize(true)\" "+
				"onmouseout=\"enable_resize(false)\">";

			tmp += "<div class=\"postDate\">"+rs.fieldByName("updated")+"</div>";

			if (rs.fieldByName("link") != "") {
				tmp += "<div clear='both'><a target=\"_blank\" "+
					"href=\"" + rs.fieldByName("link") + "\">" +
					rs.fieldByName("title") + "</a></div>";
			} else {
				tmp += "<div clear='both'>" + rs.fieldByName("title") + "</div>";
			}

/*			tmp += "<div style='float : right'> "+
				"<img src='images/tag.png' class='tagsPic' alt='Tags' title='Tags'>";
			tmp += rs.fieldByName("tags");
			tmp += "</div>"; */

/*			tmp += "<div clear='both'>"+
				"<a target=\"_blank\" "+
					"href=\"" + rs.fieldByName("comments") + "\">" +
					__("comments") + "</a></div>"; */

			tmp += "</div>";

			tmp += "<div class=\"postContent\">"
			tmp += rs.fieldByName("content");
			tmp += "</div>";

			tmp += "</div>";

			render_article(tmp);
			update_local_feedlist_counters();
		}

		rs.close();

		return false;

	} catch (e) {
		exception_error("view_offline", e);
	}
}

function viewfeed_offline(feed_id, subop, is_cat, subop_param, skip_history, offset) {
	try {
		notify('');

		if (!offset) offset = 0;

		if (offset > 0) {
			_feed_cur_page = parseInt(offset);
			if (_infscroll_request_sent) {
				return;
			}
		} else {
			_feed_cur_page = 0;
			_infscroll_disable = 0;
		}

		if (getActiveFeedId() != feed_id) {
			_feed_cur_page = 0;
			active_post_id = 0;
			_infscroll_disable = 0;
		}

		loading_set_progress(100);

		clean_feed_selections();
	
		setActiveFeedId(feed_id, is_cat);

		if (!is_cat) {
			var feedr = $("FEEDR-" + feed_id);
			if (feedr && !feedr.className.match("Selected")) {	
				feedr.className = feedr.className + "Selected";
			} 
		} else {
			var feedr = $("FCAT-" + feed_id);
			if (feedr && !feedr.className.match("Selected")) {	
				feedr.className = feedr.className + "Selected";
			} 
		}

		if (subop == "MarkAllRead") {
			catchup_local_feed(feed_id, is_cat);
		}

		disableContainerChildren("headlinesToolbar", false);
		Form.enable("main_toolbar_form");

		var f = $("headlines-frame");
		try {
			if (reply.offset == 0) { 
				debug("resetting headlines scrollTop");
				f.scrollTop = 0; 
			}
		} catch (e) { };


		var tmp = "";
		var feed_title = "";

		if (is_cat) {
			feed_title = get_local_category_title(feed_id);
		} else {		
			feed_title = get_local_feed_title(feed_id);
		}

		if (feed_title) {

			if (offset == 0) {
				tmp += "<div id=\"headlinesContainer\">";
		
				tmp += "<div class=\"headlinesSubToolbar\">";
				tmp += "<div id=\"subtoolbar_ftitle\">";
				tmp += feed_title;
				tmp += "</div>";

				var sel_all_link;
				var sel_unread_link;
				var sel_none_link;
				var sel_inv_link;

				if ($("content-frame")) {
					sel_all_link = "javascript:selectTableRowsByIdPrefix('headlinesList', 'RROW-', 'RCHK-', true, '', true)";
					sel_unread_link = "javascript:selectTableRowsByIdPrefix('headlinesList', 'RROW-', 'RCHK-', true, 'Unread', true)";
					sel_none_link = "javascript:selectTableRowsByIdPrefix('headlinesList', 'RROW-', 'RCHK-', false)";
					sel_inv_link = "javascript:invertHeadlineSelection()";
				} else {
					sel_all_link = "javascript:cdmSelectArticles('all')";
					sel_unread_link = "javascript:cdmSelectArticles('unread')";
					sel_none_link = "javascript:cdmSelectArticles('none')";
					sel_inv_link = "javascript:invertHeadlineSelection()";
				}

				tmp += __('Select:')+
					" <a href=\""+sel_all_link+"\">"+__('All')+"</a>, "+
					"<a href=\""+sel_unread_link+"\">"+__('Unread')+"</a>, "+
					"<a href=\""+sel_inv_link+"\">"+__('Invert')+"</a>, "+
					"<a href=\""+sel_none_link+"\">"+__('None')+"</a>";
	
				tmp += "&nbsp;&nbsp;";
	
				tmp += "</div>";
	
				tmp += "<div id=\"headlinesInnerContainer\" onscroll=\"headlines_scroll_handler()\">";
				if ($("content-frame")) {
					tmp += "<table class=\"headlinesList\" id=\"headlinesList\" cellspacing=\"0\">";
				}
			
			}
	
			var limit = 30;
		
			var toolbar_form = document.forms["main_toolbar_form"];
			
			var limit = toolbar_form.limit[toolbar_form.limit.selectedIndex].value;
			var view_mode = toolbar_form.view_mode[toolbar_form.view_mode.selectedIndex].value;

			var limit_qpart = "";
			var strategy_qpart = "";
			var mode_qpart = "";
			var offset_qpart = "";

			if (limit != 0) {
				limit_qpart = "LIMIT " + limit;
			}

			if (view_mode == "all_articles") {
				mode_qpart = "1";
			} else if (view_mode == "adaptive") {
				if (is_cat && get_local_category_unread(feed_id) ||
					get_local_feed_unread(feed_id) > 0) {
						mode_qpart = "unread = 1";
				} else {
					mode_qpart = "1";
				}
				
			} else if (view_mode == "marked") {
				mode_qpart = "marked = 1";
			} else if (view_mode == "unread") {
				mode_qpart = "unread = 1";
			} else {
				mode_qpart = "1";
			}

			var ext_tables_qpart = "";

			if (is_cat) {
				if (feed_id >= 0) {
					strategy_qpart = "cat_id = " + feed_id;
				} else if (feed_id == -2) {
					strategy_qpart = "article_labels.id = articles.id";
					ext_tables_qpart = ",article_labels";
				}
			} else if (feed_id > 0) {
				strategy_qpart = "feed_id = " + feed_id;
			} else if (feed_id == -1) {
				strategy_qpart = "marked = 1";
			} else if (feed_id == -4) {
				strategy_qpart = "1";
			} else if (feed_id < -10) {
				var label_id = -11 - feed_id;
				strategy_qpart = "article_labels.id = articles.id AND label_id = " + label_id;
				ext_tables_qpart = ",article_labels";
			}

			if (offset > 0) {
				offset_qpart = "OFFSET " + (offset*30);
			} else {
				offset_qpart = "";
			}

			var query = "SELECT *,feeds.title AS feed_title "+
				"FROM articles,feeds,categories"+ext_tables_qpart+" "+
				"WHERE " +
				"cat_id = categories.id AND " +
				"feed_id = feeds.id AND " +
				strategy_qpart +
				" AND " + mode_qpart + 
				" ORDER BY updated DESC "+
				limit_qpart + " " +
				offset_qpart;

			var rs = db.execute(query);

			var line_num = offset*30;

			var real_feed_id = feed_id;

			while (rs.isValidRow()) {

				var id = rs.fieldByName("id");
				var feed_id = rs.fieldByName("feed_id");

				var entry_feed_title = false;

				if (real_feed_id < 0 || is_cat) {
					entry_feed_title = rs.fieldByName("feed_title");
				}

				var marked_pic;
	
				var row_class = (line_num % 2) ? "even" : "odd";

				if (rs.fieldByName("unread") == "1") {
					row_class += "Unread";
				}

				var labels = get_local_article_labels(id);

				var labels_str = "<span id=\"HLLCTR-"+id+"\">";
				labels_str += format_article_labels(labels, id);
				labels_str += "</span>";

				if (rs.fieldByName("marked") == "1") {
					marked_pic = "<img id=\"FMPIC-"+id+"\" "+
						"src=\"images/mark_set.png\" class=\"markedPic\""+
						"alt=\"Unstar article\" onclick='javascript:tMark("+id+")'>";
				} else {
					marked_pic = "<img id=\"FMPIC-"+id+"\" "+
						"src=\"images/mark_unset.png\" class=\"markedPic\""+
						"alt=\"Star article\" onclick='javascript:tMark("+id+")'>";
				}

				var mouseover_attrs = "onmouseover='postMouseIn($id)' "+
					"onmouseout='postMouseOut($id)'";

				var content_preview = truncate_string(strip_tags(rs.fieldByName("content")), 
						100);
	
				if ($("content-frame")) {

					tmp += "<tr class='"+row_class+"' id='RROW-"+id+"' "+mouseover_attrs+">";
					
					tmp += "<td class='hlUpdPic'> </td>";
	
					tmp += "<td class='hlSelectRow'>"+
						"<input type=\"checkbox\" onclick=\"tSR(this)\"	id=\"RCHK-"+id+"\"></td>";
					
					tmp += "<td class='hlMarkedPic'>"+marked_pic+"</td>";
		
					tmp += "<td onclick='view("+id+","+feed_id+")' "+
						"class='hlContent' valign='middle'>";
		
					tmp += "<a target=\"_blank\" id=\"RTITLE-"+id+"\" href=\"" + 
						rs.fieldByName("link") + "\"" +
						"onclick=\"return view("+id+","+feed_id+");\">"+
						rs.fieldByName("title");
	
					tmp += "<span class=\"contentPreview\"> - "+content_preview+"</span>";
	
					tmp += "</a>";

					tmp += labels_str;

					if (entry_feed_title) {
						tmp += " <span class=\"hlFeed\">"+
							"(<a href='javascript:viewfeed("+feed_id+
							")'>"+entry_feed_title+"</a>)</span>";
					}

					tmp += "</td>";

					tmp += "<td class=\"hlUpdated\" onclick='view("+id+","+feed_id+")'>"+
						"<nobr>"+rs.fieldByName("updated").substring(0,16)+
						"</nobr></td>";
	
					tmp += "</tr>";
				} else {

					var add_class = "";

					if (rs.fieldByName("unread") == "1") {
						add_class = "Unread";					
					}
				
					tmp += "<div class=\"cdmArticle"+add_class+"\" id=\"RROW-"+id+"\" "+
						mouseover_attrs+"'>";

					feed_icon_img = "<img class=\"tinyFeedIcon\" src=\""+
						getInitParam("icons_url")+"/"+feed_id+".ico\" alt=\"\">";
					cdm_feed_icon = "<span style=\"cursor : pointer\" "+
						"onclick=\"viewfeed("+feed_id+")\">"+feed_icon_img+"</span>";

					tmp += "<div class=\"cdmHeader\">";
					tmp += "<div class=\"articleUpdated\">"+
						rs.fieldByName("updated").substring(0,16)+
						" "+cdm_feed_icon+"</div>";

					tmp += "<span id=\"RTITLE-"+id+"\" class=\"titleWrap\">"+
						"<a class=\"title\" onclick=\"javascript:toggleUnread("+id+", 0)\""+
						"target=\"_blank\" href=\""+rs.fieldByName("link")+
						"\">"+rs.fieldByName("title")+"</a>";

					tmp += labels_str;

					if (entry_feed_title) {
						tmp += "&nbsp;(<a href='javascript:viewfeed("+feed_id+
							")'>"+entry_feed_title+"</a>)";
					}

					tmp += "</span></div>";

					tmp += "<div class=\"cdmContent\" onclick=\"cdmClicked("+id+")\""+
						"id=\"CICD-"+id+"\">";
					tmp += rs.fieldByName("content");
					tmp += "<br clear='both'>"
					tmp += "</div>"; 

					tmp += "<div class=\"cdmFooter\"><span class='s0'>";
					tmp += __("Select:")+
						" <input type=\"checkbox\" "+
						"onclick=\"toggleSelectRowById(this, 'RROW-"+id+"')\" "+
						"class=\"feedCheckBox\" id=\"RCHK-"+id+"\">";

					tmp += "</span><span class='s1'>"+marked_pic+"</span> ";

/*					tmp += "<span class='s1'>"+
						"<img class='tagsPic' src='images/tag.png' alt='Tags' title='Tags'>"+
						"<span id=\"ATSTR-"+id+"\">"+rs.fieldByName("tags")+"</span>"+
						"</span>"; */

					tmp += "<span class='s2'>Toggle: <a class=\"cdmToggleLink\""+
						"href=\"javascript:toggleUnread("+id+")\">"+
						"Unread</a></span>";
					tmp += "</div>";

					tmp += "</div>";
				}

				rs.next();
				line_num++;
			}

			if (line_num - offset*30 < 30) {
				_infscroll_disable = 1;
			}

			rs.close();
	
			if (offset == 0) {
				tmp += "</table>";

				if (line_num - offset*30 == 0) {
					tmp += "<div class='whiteBox'>" +
						__("No articles found to display.") +
						"</div>";
				}
				tmp += "</div></div>";
			}
	
			if (offset == 0) {
				var container = $("headlines-frame");
				container.innerHTML = tmp;
			} else {
				var ids = getSelectedArticleIds2();
		
				var container = $("headlinesList");
				container.innerHTML = container.innerHTML + tmp;
	
				for (var i = 0; i < ids.length; i++) {
					markHeadline(ids[i]);
				}
			}
		}

		remove_splash();

		_infscroll_request_sent = 0;

	} catch (e) {
		exception_error("viewfeed_offline", e);
	}
}

function render_offline_feedlist() {
	try {
		var cats_enabled = getInitParam("enable_feed_cats") == "1";

		var tmp = "<ul class=\"feedList\" id=\"feedList\">";

		var unread = get_local_feed_unread(-4);

		global_unread = unread;
		updateTitle();

		if (cats_enabled) {
			tmp += printCategoryHeader(-1, is_local_cat_collapsed(-1), false);
		}

		tmp += printFeedEntry(-4, __("All articles"), "feed", unread,
			"images/tag.png");

		var unread = get_local_feed_unread(-1);

		tmp += printFeedEntry(-1, __("Starred articles"), "feed", unread,
			"images/mark_set.png");

		if (cats_enabled) {
			tmp += "</ul></li>";
		} else {
			tmp += "<li><hr/></li>";
		}

		if (cats_enabled) {
			tmp += printCategoryHeader(-2, is_local_cat_collapsed(-2), false);
		}

		var rs = db.execute("SELECT id,caption "+
			"FROM labels "+
			"ORDER BY caption");

		while (rs.isValidRow()) {
			var id = -11 - parseInt(rs.field(0));
			var caption = rs.field(1);
			var unread = get_local_feed_unread(id);

			tmp += printFeedEntry(id, caption, "feed", unread,
				"images/label.png");

			rs.next();
		}

		rs.close();

		if (cats_enabled) {
			tmp += "</ul></li>";
		} else {
			tmp += "<li><hr/></li>";
		}

/*		var rs = db.execute("SELECT feeds.id,feeds.title,has_icon,COUNT(articles.id) "+
			"FROM feeds LEFT JOIN articles ON (feed_id = feeds.id) "+
			"WHERE unread = 1 OR unread IS NULL GROUP BY feeds.id "+
			"ORDER BY feeds.title"); */

		var order_by = "feeds.title";

		if (cats_enabled) order_by = "categories.title," + order_by;

		var rs = db.execute("SELECT "+
			"feeds.id,feeds.title,has_icon,cat_id,collapsed "+
			"FROM feeds,categories WHERE cat_id = categories.id "+
			"ORDER BY "+order_by);

		var tmp_cat_id = -1;

		while (rs.isValidRow()) {

			var id = rs.field(0);
			var title = rs.field(1);
			var has_icon = rs.field(2);
			var unread = get_local_feed_unread(id);
			var cat_id = rs.field(3);
			var cat_hidden = rs.field(4);

			if (cat_id != tmp_cat_id && cats_enabled) {
				if (tmp_cat_id != -1) {
					tmp += "</ul></li>";
				}
				tmp += printCategoryHeader(cat_id, cat_hidden, true);
				tmp_cat_id = cat_id;
			}

			var icon = "";

			if (has_icon) {
				icon = getInitParam("icons_url") + "/" + id + ".ico";
			}

			var feed_icon = "";

			var row_class = "feed";

			if (unread > 0) {
				row_class += "Unread";
				fctr_class = "feedCtrHasUnread";
			} else {
				fctr_class = "feedCtrNoUnread";
			}

			tmp += printFeedEntry(id, title, "feed", unread, icon);

			rs.next();
		}

		rs.close();

		if (cats_enabled) {
			tmp += "</ul>";
		}

		tmp += "</ul>";

		render_feedlist(tmp);
	} catch (e) {
		exception_error("render_offline_feedlist", e);
	}
}

function init_offline() {
	try {
		offline_mode = true;

		Element.hide("dispSwitchPrompt");
		Element.hide("feedBrowserPrompt");

		Element.hide("topLinksOnline");
		Element.show("topLinksOffline");

		var tb_form = $("main_toolbar_form");
		Element.hide(tb_form.update);

		var chooser = $("quickMenuChooser");
		chooser.disabled = true;

		var rs = db.execute("SELECT key, value FROM init_params");

		while (rs.isValidRow()) {
			init_params[rs.field(0)] = rs.field(1);
			rs.next();
		}

		rs.close();

		var rs = db.execute("SELECT COUNT(*) FROM feeds");

		var num_feeds = 0;

		if (rs.isValidRow()) {
			num_feeds = rs.field(0);			
		}
		
		rs.close();

		if (num_feeds == 0) {
			remove_splash();
			return fatalError(0, 
				__("Data for offline browsing has not been downloaded yet."));
		}

		render_offline_feedlist();
		init_second_stage();
		window.setTimeout("viewfeed(-4)", 50);

	} catch (e) {
		exception_error("init_offline", e);
	}
}

function offline_download_parse(stage, transport) {
	try {
		if (transport.responseXML) {

			if (!sync_in_progress) return;

			var sync_ok = transport.responseXML.getElementsByTagName("sync-ok");

			if (sync_ok.length > 0) {
				for (var i = 0; i < sync_ok.length; i++) {
					var id = sync_ok[i].getAttribute("id");
					if (id) {
						debug("synced offline info for id " + id);
						db.execute("UPDATE articles SET modified = '' WHERE id = ?", [id]);
					}
				}
			}

			if (stage == 0) {

				$("offlineModeSyncMsg").innerHTML = __("Synchronizing feeds...");

				var feeds = transport.responseXML.getElementsByTagName("feed");

				if (feeds.length > 0) {
					db.execute("DELETE FROM feeds");
				}

				for (var i = 0; i < feeds.length; i++) {
					var id = feeds[i].getAttribute("id");
					var has_icon = feeds[i].getAttribute("has_icon");
					var title = feeds[i].firstChild.nodeValue;
					var cat_id = feeds[i].getAttribute("cat_id");

					db.execute("INSERT INTO feeds (id,title,has_icon,cat_id)"+
						"VALUES (?,?,?,?)",
						[id, title, has_icon, cat_id]);
				}

				$("offlineModeSyncMsg").innerHTML = __("Synchronizing categories...");

				var cats = transport.responseXML.getElementsByTagName("category");

				if (feeds.length > 0) {
					db.execute("DELETE FROM categories");
				}

				for (var i = 0; i < cats.length; i++) {
					var id = cats[i].getAttribute("id");
					var collapsed = cats[i].getAttribute("collapsed");
					var title = cats[i].firstChild.nodeValue;

					db.execute("INSERT INTO categories (id,title,collapsed)"+
						"VALUES (?,?,?)",
						[id, title, collapsed]);
				}

				$("offlineModeSyncMsg").innerHTML = __("Synchronizing labels...");

				var labels = transport.responseXML.getElementsByTagName("label");

				if (labels.length > 0) {
					db.execute("DELETE FROM labels");
				}

				for (var i = 0; i < labels.length; i++) {
					var id = labels[i].getAttribute("id");
					var fg_color = labels[i].getAttribute("fg_color");
					var bg_color = labels[i].getAttribute("bg_color");
					var caption = labels[i].firstChild.nodeValue;

					db.execute("INSERT INTO labels (id,caption,fg_color,bg_color)"+
						"VALUES (?,?,?,?)",
						[id, caption, fg_color, bg_color]);
				}

				$("offlineModeSyncMsg").innerHTML = __("Synchronizing articles...");

				sync_timer = window.setTimeout("update_offline_data("+(stage+1)+")", 2*1000);
			} else {

				var articles = transport.responseXML.getElementsByTagName("article");

				var limit = transport.responseXML.getElementsByTagName("limit")[0];

				if (limit) {
					limit = limit.getAttribute("value");
				} else {
					limit = 0;
				}

				var articles_found = 0;

				for (var i = 0; i < articles.length; i++) {					
					var a = eval("("+articles[i].firstChild.nodeValue+")");
					articles_found++;
					if (a) {

						db.execute("DELETE FROM articles WHERE id = ?", [a.id]);

						db.execute("INSERT INTO articles "+
						"(id, feed_id, title, link, guid, updated, content, "+
							"unread, marked, tags, comments) "+
						"VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", 
							[a.id, a.feed_id, a.title, a.link, a.guid, a.updated, 
								a.content, a.unread, a.marked, a.tags,
								a.comments]);

						if (a.labels.length > 0) {
							for (var j = 0; j < a.labels.length; j++) {
								label_local_add_article(a.id, a.labels[j][0]);
							}
						}

					}
				}

				debug("downloaded articles: " + articles_found + " limit: " + limit);

				articles_synced += articles_found;

				var msg =__("Synchronizing articles (%d)...").replace("%d", articles_synced);

				$("offlineModeSyncMsg").innerHTML = msg;

				var has_sync_data = has_local_sync_data();

				if (articles_found >= limit || has_sync_data) {
					sync_timer = window.setTimeout("update_offline_data("+(stage+1)+")", 
						3*1000);
					debug("<b>update_offline_data: done " + stage + " HSD: " + 
						has_sync_data + "</b>");
				} else {
					window.setTimeout("offlineDownloadStart()", 180*1000);
					debug("update_offline_data: finished");

					var pic = $("offlineModePic");

					if (pic) { 
						pic.src = "images/offline.png";

						var rs = db.execute("SELECT value FROM syncdata WHERE key = 'last_online'");
						var last_sync = "";

						if (rs.isValidRow()) {
							last_sync = rs.field(0).substring(0,16);
						}
						rs.close();

						var msg = __("Last sync: %s").replace("%s", last_sync);

						articles_synced = 0;

						$("offlineModeSyncMsg").innerHTML = msg;					
					}			 

					offlineSyncShowHideElems(false);

					sync_in_progress = false;

					db.execute("DELETE FROM articles WHERE "+
						"updated < DATETIME('NOW', 'localtime', '-31 days')");

				}
			}

			update_local_sync_data();
		

//			notify('');

		} else {
			sync_in_progress = false;

			var pic = $("offlineModePic");

			if (pic) { 
				pic.src = "images/offline.png";
				var msg = __("Last sync: Error receiving data.");
				articles_synced = 0;
				$("offlineModeSyncMsg").innerHTML = msg;					
			}			 

			offlineSyncShowHideElems(false);
		}

	} catch (e) {
		exception_error("offline_download_parse", e);
	}
}

function update_offline_data(stage) {
	try {

		if (!stage) stage = 0;

		if (!db || offline_mode || getInitParam("offline_enabled") != "1") return;

//		notify_progress("Updating offline data... (" + stage +")", true);

		var query = "backend.php?op=rpc&subop=download";
		
		var rs = db.execute("SELECT MAX(id), MIN(id) FROM articles");

		if (rs.isValidRow() && rs.field(0)) {
			var offline_dl_max_id = rs.field(0);
			var offline_dl_min_id = rs.field(1);

			query = query + "&cidt=" + offline_dl_max_id;
			query = query + "&cidb=" + offline_dl_min_id;

			stage = 1;
		}

		rs.close();

		debug("update_offline_data: stage " + stage);

		query = query + "&stage=" + stage;

		var to_sync = prepare_local_sync_data();

		if (to_sync != "") {
			to_sync = "?sync=" + param_escape(to_sync);
		}

		debug(query + "/" + to_sync);

		var pic = $("offlineModePic");

		if (pic) {
			pic.src = "images/offline-sync.gif";
			if (articles_synced == 0) {
				$("offlineModeSyncMsg").innerHTML = __("Synchronizing...");
			}
		}

		offlineSyncShowHideElems(true);

		sync_in_progress = true;

		new Ajax.Request(query, {
			parameters: to_sync,
			onComplete: function(transport) { 
				offline_download_parse(stage, transport);				
			} });

	} catch (e) {
		exception_error("initiate_offline_download", e);
	}
}

function set_feedlist_counter(id, ctr, is_cat) {
	try {

		var feedctr = $("FEEDCTR-" + id);
		var feedu = $("FEEDU-" + id);
		var feedr = $("FEEDR-" + id);

		if (is_cat) {
			var catctr = $("FCATCTR-" + id);
			if (catctr) {
				catctr.innerHTML = "(" + ctr + ")";
				if (ctr > 0) {
					catctr.className = "catCtrHasUnread";
				} else {
					catctr.className = "catCtrNoUnread";
				}
			}
		} else if (feedctr && feedu && feedr) {

			var row_needs_hl = (ctr > 0 && ctr > parseInt(feedu.innerHTML));

			feedu.innerHTML = ctr;

			if (ctr > 0) {					
				feedctr.className = "feedCtrHasUnread";
				if (!feedr.className.match("Unread")) {
					var is_selected = feedr.className.match("Selected");
	
					feedr.className = feedr.className.replace("Selected", "");
					feedr.className = feedr.className.replace("Unread", "");
	
					feedr.className = feedr.className + "Unread";
	
					if (is_selected) {
						feedr.className = feedr.className + "Selected";
					}	
					
				}

				if (row_needs_hl) { 
					new Effect.Highlight(feedr, {duration: 1, startcolor: "#fff7d5",
						queue: { position:'end', scope: 'EFQ-' + id, limit: 1 } } );
				}
			} else {
				feedctr.className = "feedCtrNoUnread";
				feedr.className = feedr.className.replace("Unread", "");
			}			
		}

	} catch (e) {
		exception_error("set_feedlist_counter", e);
	}
}

function update_local_feedlist_counters() {
	try {
		if (!offline_mode || !db) return;

/*		var rs = db.execute("SELECT feeds.id,COUNT(articles.id) "+
			"FROM feeds LEFT JOIN articles ON (feed_id = feeds.id) "+
			"WHERE unread = 1 OR unread IS NULL GROUP BY feeds.id "+
			"ORDER BY feeds.title"); */

		var rs = db.execute("SELECT id FROM feeds "+
			"ORDER BY title");

		while (rs.isValidRow()) {
			var id = rs.field(0);
			var ctr = get_local_feed_unread(id);
			set_feedlist_counter(id, ctr, false);
			rs.next();
		}

		rs.close();

		var rs = db.execute("SELECT cat_id,SUM(unread) "+
			"FROM articles, feeds WHERE feeds.id = feed_id GROUP BY cat_id");

		while (rs.isValidRow()) {
			var id = rs.field(0);
			var ctr = rs.field(1);
			set_feedlist_counter(id, ctr, true);
			rs.next();
		}

		rs.close();

		set_feedlist_counter(-2, get_local_category_unread(-2), true);

		set_feedlist_counter(-4, get_local_feed_unread(-4));
		set_feedlist_counter(-1, get_local_feed_unread(-1));

		var rs = db.execute("SELECT id FROM labels");
			
		while (rs.isValidRow()) {
			var id = -11 - rs.field(0);
			var ctr = get_local_feed_unread(id);
			set_feedlist_counter(id, ctr, false);
			rs.next();		
		}

		rs.close();

		hideOrShowFeeds(getInitParam("hide_read_feeds") == 1);

		global_unread = get_local_feed_unread(-4);
		updateTitle();

	} catch (e) {
		exception_error("update_local_feedlist_counters", e);
	}
}

function get_local_feed_unread(id) {
	try {
		var rs;

		if (id == -4) {
			rs = db.execute("SELECT SUM(unread) FROM articles");
		} else if (id == -1) {
			rs = db.execute("SELECT SUM(unread) FROM articles WHERE marked = 1");
		} else if (id > 0) {
			rs = db.execute("SELECT SUM(unread) FROM articles WHERE feed_id = ?", [id]);
		} else if (id < -10) {
			var label_id = -11 - id;
			rs = db.execute("SELECT SUM(unread) FROM articles,article_labels "+
				"WHERE article_labels.id = articles.id AND label_id = ?", [label_id]);
		}

		var a = false;

		if (rs.isValidRow()) {
			a = rs.field(0);
		} else {
			a = 0;
		}

		rs.close();

		return a;

	} catch (e) {
		exception_error("get_local_feed_unread", e);
	}
}

function enable_offline_reading() {
	try {

		if (db && getInitParam("offline_enabled") == "1") {
			init_local_sync_data();
			Element.show("offlineModePic");
			offlineDownloadStart();
		}

	} catch (e) {
		exception_error("enable_offline_reading", e);
	}
}

function init_gears() {
	try {

		if (window.google && google.gears) {

			try {
				localServer = google.gears.factory.create("beta.localserver");
			} catch (e) {
				return;
			}

			store = localServer.createManagedStore("tt-rss");
			store.manifestUrl = "manifest.json.php";

			db = google.gears.factory.create('beta.database');
			db.open('tt-rss');

			db.execute("CREATE TABLE IF NOT EXISTS version (schema_version text)");

			var rs = db.execute("SELECT schema_version FROM version");

			var version = "";

			if (rs.isValidRow()) {
				version = rs.field(0);
			}

			rs.close();

			if (version != SCHEMA_VERSION) {
				db.execute("DROP TABLE IF EXISTS init_params");
				db.execute("DROP TABLE IF EXISTS cache");
				db.execute("DROP TABLE IF EXISTS feeds");
				db.execute("DROP TABLE IF EXISTS categories");
				db.execute("DROP TABLE IF EXISTS labels");
				db.execute("DROP TABLE IF EXISTS article_labels");
				db.execute("DROP TABLE IF EXISTS articles");
				db.execute("DROP INDEX IF EXISTS article_labels_label_id_idx");
				db.execute("DROP INDEX IF EXISTS articles_unread_idx");
				db.execute("DROP INDEX IF EXISTS articles_feed_id_idx");
				db.execute("DROP INDEX IF EXISTS articles_id_idx");
				db.execute("DROP INDEX IF EXISTS article_labels_id_idx");
				db.execute("DROP TABLE IF EXISTS version");
				db.execute("DROP TRIGGER IF EXISTS articles_update_unread");
				db.execute("DROP TRIGGER IF EXISTS articles_update_marked");
				db.execute("DROP TRIGGER IF EXISTS articles_remove_labelrefs");
				db.execute("CREATE TABLE IF NOT EXISTS version (schema_version text)");
				db.execute("DROP TABLE IF EXISTS syncdata");
				db.execute("INSERT INTO version (schema_version) VALUES (?)", 
					[SCHEMA_VERSION]);
			}

			db.execute("CREATE TABLE IF NOT EXISTS init_params (key text, value text)");

			db.execute("CREATE TABLE IF NOT EXISTS cache (id integer, article text, param text, added text)");
			db.execute("CREATE TABLE IF NOT EXISTS feeds (id integer, title text, has_icon integer, cat_id integer)");
			db.execute("CREATE TABLE IF NOT EXISTS categories (id integer, title text, collapsed integer)");
			db.execute("CREATE TABLE IF NOT EXISTS labels (id integer, caption text, fg_color text, bg_color text)");
			db.execute("CREATE TABLE IF NOT EXISTS article_labels (id integer, label_id integer)");
			db.execute("CREATE TABLE IF NOT EXISTS articles (id integer, feed_id integer, title text, link text, guid text, updated timestamp, content text, tags text, unread integer, marked integer, added text, modified timestamp, comments text)");
	
			db.execute("CREATE INDEX IF NOT EXISTS articles_unread_idx ON articles(unread)");
			db.execute("CREATE INDEX IF NOT EXISTS article_labels_label_id_idx ON article_labels(label_id)");
			db.execute("CREATE INDEX IF NOT EXISTS articles_feed_id_idx ON articles(feed_id)");
			db.execute("CREATE INDEX IF NOT EXISTS articles_id_idx ON articles(id)");
			db.execute("CREATE INDEX IF NOT EXISTS article_labels_id_idx ON article_labels(id)");

			db.execute("CREATE TABLE IF NOT EXISTS syncdata (key integer, value text)");

			db.execute("DELETE FROM cache WHERE id LIKE 'F:%' OR id LIKE 'C:%'");

			db.execute("CREATE TRIGGER IF NOT EXISTS articles_update_unread "+
				"UPDATE OF unread ON articles "+
				"BEGIN "+
				"UPDATE articles SET modified = DATETIME('NOW', 'localtime') "+
				"WHERE id = OLD.id AND "+
				"OLD.unread != NEW.unread;"+
				"END;");

			db.execute("CREATE TRIGGER IF NOT EXISTS articles_update_marked "+
				"UPDATE OF marked ON articles "+
				"BEGIN "+
				"UPDATE articles SET modified = DATETIME('NOW', 'localtime') "+
				"WHERE id = OLD.id;"+
				"END;");

			db.execute("CREATE TRIGGER IF NOT EXISTS articles_remove_labelrefs "+
				"DELETE ON articles "+
				"BEGIN "+
				"DELETE FROM article_labels WHERE id = OLD.id; "+
				"END; ");

		}	
	
		cache_expire();

	} catch (e) {
		exception_error("init_gears", e);
	}
}

function gotoOffline() {

//	debug("[Local store] currentVersion = " + store.currentVersion);

	var rs = db.execute("SELECT COUNT(*) FROM articles");
	var count = 0;
	if (rs.isValidRow()) {
		count = rs.field(0);
	}

	rs.close();

	if (count == 0) {
		notify_error("You have to synchronize some articles before going into offline mode.");
		return;
	}

	if (confirm(__("Switch Tiny Tiny RSS into offline mode?"))) {

		store.checkForUpdate();
	
		notify_progress("Preparing offline mode...");
	
		var timerId = window.setInterval(function() {
			if (store.currentVersion) {
				window.clearInterval(timerId);
				debug("[Local store] sync complete: " + store.currentVersion);

				//window.location.href = "tt-rss.php";

				offlineDownloadStop();
				offline_mode = true;
				init_offline();

				notify_info("Tiny Tiny RSS is in offline mode.");

			} else if (store.updateStatus == 3) {
				debug("[Local store] sync error: " + store.lastErrorMessage);
				notify_error(store.lastErrorMessage, true);
			} }, 500);
	}
}

function gotoOnline() {
	if (confirm(__("You won't be able to access offline version of Tiny Tiny RSS until you switch it into offline mode again. Go online?"))) {
		localServer.removeManagedStore("tt-rss");
		window.location.href = "tt-rss.php";
	}
}

function local_collapse_cat(id) {
	try {
		if (db) {
			db.execute("UPDATE categories SET collapsed = NOT collapsed WHERE id = ?",
				[id]);
		}	
	} catch (e) {
		exception_error("local_collapse_cat", e);
	}
}

function get_local_category_title(id) {
	try {
	
		var rs = db.execute("SELECT title FROM categories WHERE id = ?", [id]);
		var tmp = "";

		if (rs.isValidRow()) {
			tmp = rs.field(0);
		}

		rs.close();

		return tmp;

	} catch (e) {
		exception_error("get_local_category_title", e);
	}
}

function get_local_category_unread(id) {
	try {
		var rs = false;

		if (id >= 0) {
			rs = db.execute("SELECT SUM(unread) FROM articles, feeds "+
				"WHERE feeds.id = feed_id AND cat_id = ?", [id]);
		} else if (id == -2) {
			rs = db.execute("SELECT SUM(unread) FROM article_labels, articles "+
				"where article_labels.id = articles.id");
		} else {
			return 0;
		}

		var tmp = 0;

		if (rs.isValidRow()) {
			tmp = rs.field(0);
		}

		rs.close();

		return tmp;

	} catch (e) {
		exception_error("get_local_category_unread", e);
	}
}

function printCategoryHeader(cat_id, hidden, can_browse) {
	try {
		if (hidden == undefined) hidden = false;
		if (can_browse == undefined) can_browse = false;

			var tmp_category = get_local_category_title(cat_id);
			var tmp = "";

			var cat_unread = get_local_category_unread(cat_id);

			var holder_style = "";
			var ellipsis = "";

			if (hidden) {
				holder_style = "display:none;";
				ellipsis = "…";
			}

			var catctr_class = (cat_unread > 0) ? "catCtrHasUnread" : "catCtrNoUnread";

			var browse_cat_link = "";
			var inner_title_class = "catTitleNL";

			if (can_browse) {
				browse_cat_link = "onclick=\"javascript:viewCategory("+cat_id+")\"";
				inner_title_class = "catTitle";
			}

			var cat_class = "feedCat";

			tmp += "<li class=\""+cat_class+"\" id=\"FCAT-"+cat_id+"\">"+
				"<img onclick=\"toggleCollapseCat("+cat_id+")\" class=\"catCollapse\""+
					" title=\""+__('Click to collapse category')+"\""+
					" src=\"images/cat-collapse.png\"><span class=\""+inner_title_class+"\" "+
					" id=\"FCATN-"+cat_id+"\" "+browse_cat_link+
				"\">"+tmp_category+"</span>";

			tmp += "<span id=\"FCAP-"+cat_id+"\">";

			tmp += " <span id=\"FCATCTR-"+cat_id+"\" "+
				"class=\""+catctr_class+"\">("+cat_unread+")</span> "+ellipsis;

			tmp += "</span>";

			tmp += "<ul class=\"feedCatList\" id=\"FCATLIST-"+cat_id+"\" "+
				"style='"+holder_style+"'>";

			return tmp;
	} catch (e) {
		exception_error("printCategoryHeader", e);
	}
}

function is_local_cat_collapsed(id) {
	try {

		var rs = db.execute("SELECT collapsed FROM categories WHERE id = ?", [id]);
		var cat_hidden = 0;

		if (rs.isValidRow()) {
			cat_hidden = rs.field(0);
		}

		rs.close();

		return cat_hidden == "1";

	} catch (e) {
		exception_error("is_local_cat_collapsed", e);
	}
}

function get_local_article_labels(id) {
	try {
		var rs = db.execute("SELECT DISTINCT label_id,caption,fg_color,bg_color "+
			"FROM labels, article_labels "+
			"WHERE labels.id = label_id AND article_labels.id = ?", [id]);

		var tmp = new Array();

		while (rs.isValidRow()) {
			var e = new Array();

			e[0] = rs.field(0);
			e[1] = rs.field(1);
			e[2] = rs.field(2);
			e[3] = rs.field(3);

			tmp.push(e);

			rs.next();
		}

		return tmp;

	} catch (e) {
		exception_error("get_local_article_labels", e);
	}
}

function label_local_add_article(id, label_id) {
	try {
		//debug("label_local_add_article " + id + " => " + label_id);

		var rs = db.execute("SELECT COUNT(id) FROM article_labels WHERE "+
			"id = ? AND label_id = ?", [id, label_id]);
		var check = rs.field(0);

		if (rs.isValidRow()) {
			var check = rs.field(0);
		}
		rs.close();

		if (check == 0) {
			db.execute("INSERT INTO article_labels (id, label_id) VALUES "+
				"(?,?)", [id, label_id]);
		}

	} catch (e) {
		exception_error("label_local_add_article", e);
	}
}

function get_local_feed_title(id) {
	try {

		var feed_title = "Unknown feed: " + id;

		if (id > 0) {
			var rs = db.execute("SELECT title FROM feeds WHERE id = ?", [id]);

			if (rs.isValidRow()) {
				feed_title = rs.field(0);
			}

			rs.close();
		} else if (id == -1) {
			feed_title = __("Starred articles");
		} else if (id == -4) {
			feed_title = __("All articles");
		} else if (id < -10) {
			
			var label_id = -11 - id;
				
			var rs = db.execute("SELECT caption FROM labels WHERE id = ?", [label_id]);

			if (rs.isValidRow()) {
				feed_title = rs.field(0);
			}

			rs.close();
		}

		return feed_title;

	} catch (e) {
		exception_error("get_local_feed_title", e);
	}
}

function format_article_labels(labels, id) {
	try {

		var labels_str = "";

		if (!labels) return "";

		for (var i = 0; i < labels.length; i++) {
			var l = labels[i];

			labels_str += "<span class='hlLabelRef' "+
				"style='color : "+l[2]+"; background-color : "+l[3]+"'>"+l[1]+"</span>";
		}

		return labels_str;

	} catch (e) {
		exception_error("format_article_labels", e);
	}
}

function init_local_sync_data() {
	try {

		if (!db) return;

		var rs = db.execute("SELECT COUNT(*) FROM syncdata WHERE key = 'last_online'");
		var has_last_online = 0;

		if (rs.isValidRow()) {
			has_last_online = rs.field(0);
		}

		rs.close();

		if (!has_last_online) {
			db.execute("INSERT INTO syncdata (key, value) VALUES ('last_online', '')");
		}

	} catch (e) {
		exception_error("init_local_sync_data", e);

	}
}

function has_local_sync_data() {
	try {

		var rs = db.execute("SELECT id FROM articles "+
			"WHERE modified > (SELECT value FROM syncdata WHERE key = 'last_online') "+
			"LIMIT 1");

		var tmp = 0;

		if (rs.isValidRow()) {
			tmp = rs.field(0);
		}

		rs.close();

		return tmp != 0;

	} catch (e) {
		exception_error("has_local_sync_data", e);
	}
}

function prepare_local_sync_data() {
	try {
		var rs = db.execute("SELECT value FROM syncdata WHERE key = 'last_online'");

		var last_online = "";
		
		if (rs.isValidRow()) {
			last_online = rs.field(0);
		}

		rs.close();

		var rs = db.execute("SELECT id,unread,marked FROM articles "+
			"WHERE modified > ? LIMIT 200", [last_online]);

		var tmp = last_online + ";";

		var entries = 0;

		while (rs.isValidRow()) {
			var e = new Array();

			tmp = tmp + rs.field(0) + "," + rs.field(1) + "," + rs.field(2) + ";";
			entries++;

			rs.next();
		}

		rs.close();

		if (entries > 0) {
			return tmp;
		} else {
			return '';
		}

	} catch (e) {
		exception_error("prepare_local_sync_data", e);
	}
}

function update_local_sync_data() {
	try {
		if (db && !offline_mode) {

			var rs = db.execute("SELECT id FROM articles "+
				"WHERE modified > (SELECT value FROM syncdata WHERE "+
					"key = 'last_online') LIMIT 1")

			var f_id = 0;

			if (rs.isValidRow()) {
				f_id = rs.field(0);
			}

			rs.close();

			/* no pending articles to sync */

			if (f_id == 0) {
				db.execute("UPDATE syncdata SET value = DATETIME('NOW', 'localtime') "+
					"WHERE key = 'last_online'");
			}

		}
	} catch (e) {
		exception_error("update_local_sync_data", e);
	}
}

function catchup_local_feed(id, is_cat) {
	try {
		if (!db) return;

		if (!is_cat) {
			if (id >= 0) {
				db.execute("UPDATE articles SET unread = 0 WHERE feed_id = ?", [id]);
			} else if (id == -1) {
				db.execute("UPDATE articles SET unread = 0 WHERE marked = 1");
			} else if (id == -4) {
				db.execute("UPDATE articles SET unread = 0");
			} else if (id < -10) {
				var label_id = -11-id;

				db.execute("UPDATE articles SET unread = 0 WHERE "+
					"(SELECT COUNT(*) FROM article_labels WHERE "+
					"article_labels.id = articles.id AND label_id = ?) > 0", [label_id]);
			}
		}

		update_local_feedlist_counters();

	} catch (e) {
		exception_error("catchup_local_feed", e);
	}
}

function toggleOfflineModeInfo() {
	try {
		var e = $('offlineModeDrop');
		var p = $('offlineModePic');
		
		if (Element.visible(e)) {
			Element.hide(e);
		} else {
			Element.show(e);
		}

	} catch (e) {
		exception_error("toggleOfflineModeInfo", e);
	}
}

function offlineDownloadStart() {
	try {
		if (db && !sync_in_progress && getInitParam("offline_enabled") == "1") {
			window.setTimeout("update_offline_data(0)", 100);
		}
	} catch (e) {
		exception_error("offlineDownloadStart", e);
	}
}

function offlineDownloadStop() {
	try {
		if (db && sync_in_progress && getInitParam("offline_enabled") == "1") {

			sync_in_progress = false;

			if (sync_timer) {
				window.clearTimeout(sync_timer);
				sync_timer = false;
			}

			var pic = $("offlineModePic");
	
			if (pic) { 
				pic.src = "images/offline.png";
				var msg = __("Last sync: Cancelled.");
				articles_synced = 0;
				$("offlineModeSyncMsg").innerHTML = msg;					
			}			 

			offlineSyncShowHideElems(false);

		}
	} catch (e) {
		exception_error("offlineDownloadStart", e);
	}
}

function offlineClearData() {
	try {
		if (db) {

			if (confirm(__("This will remove all offline data stored by Tiny Tiny RSS on this computer. Continue?"))) {

				notify_progress("Removing offline data...");

				localServer.removeManagedStore("tt-rss");

				db.execute("DELETE FROM articles");
				db.execute("DELETE FROM article_labels");
				db.execute("DELETE FROM labels");
				db.execute("DELETE FROM feeds");
				db.execute("DELETE FROM cache");

				notify_info("Local data removed.");
			}
		}
	} catch (e) {
		exception_error("offlineClearData", e);
	}
}

function offlineUpdateStore() {
	try {

		if (offline_mode) return;

	} catch (e) {
		exception_error("offlineUpdateStore", e);
	}
}

function offlineSyncShowHideElems(syncing) {
	try {

		var elems = $$("div.hideWhenSyncing");
	
		for (var j = 0; j < elems.length; j++) {
			if (syncing) {
				Element.hide(elems[j]);
			} else {
				Element.show(elems[j]);
			}
		}

		var elems = $$("div.showWhenSyncing");
	
		for (var j = 0; j < elems.length; j++) {
			if (syncing) {
				Element.show(elems[j]);
			} else {
				Element.hide(elems[j]);
			}
		}

	} catch (e) {
		exception_error("offlineSyncShowHideElems", e);
	}
}

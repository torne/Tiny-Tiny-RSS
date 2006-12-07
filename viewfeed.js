var active_post_id = false;
var _catchup_callback_func = false;
var last_article_view = false;
var active_real_feed_id = false;

var _tag_active_post_id = false;
var _tag_active_feed_id = false;

// FIXME: kludge, needs proper implementation
var _reload_feedlist_after_view = false;

function catchup_callback() {
	if (xmlhttp_rpc.readyState == 4) {
		try {
			debug("catchup_callback");
			if (_catchup_callback_func) {
				setTimeout(_catchup_callback_func, 100);	
			}
			all_counters_callback();
		} catch (e) {
			exception_error("catchup_callback", e);
		}
	}
}

function headlines_callback() {
	if (xmlhttp.readyState == 4) {
		debug("headlines_callback");
		var f = document.getElementById("headlines-frame");
		try {
			f.scrollTop = 0;
		} catch (e) { };
		f.innerHTML = xmlhttp.responseText;
		update_all_counters();
		if (typeof correctPNG != 'undefined') {
			correctPNG();
		}
		notify("");
	}
}

function article_callback() {
	if (xmlhttp.readyState == 4) {
		debug("article_callback");
		var f = document.getElementById("content-frame");
		try {
			f.scrollTop = 0;
		} catch (e) { };
		f.innerHTML = xmlhttp.responseText;

		var date = new Date();
		last_article_view = date.getTime() / 1000;

		if (typeof correctPNG != 'undefined') {
			correctPNG();
		}

		if (_reload_feedlist_after_view) {
			setTimeout('updateFeedList(false, false)', 50);			
			_reload_feedlist_after_view = false;
		} else {
			update_all_counters();
		}
	}
}

function view(id, feed_id, skip_history) {
	
	try {
		debug("loading article: " + id + "/" + feed_id);

		active_real_feed_id = feed_id;

		if (!skip_history) {
			history_push("ARTICLE:" + id + ":" + feed_id);
		}
	
		enableHotkeys();
	
		active_post_id = id; 
		//setActiveFeedId(feed_id);

		var query = "backend.php?op=view&id=" + param_escape(id) +
			"&feed=" + param_escape(feed_id);

		var date = new Date();

		if (!xmlhttp_ready(xmlhttp) && last_article_view < date.getTime() / 1000 - 15) {
			debug("<b>xmlhttp seems to be stuck at view, aborting</b>");
			xmlhttp.abort();
		}

		if (xmlhttp_ready(xmlhttp)) {

			cleanSelected("headlinesList");

			var crow = document.getElementById("RROW-" + active_post_id);
			crow.className = crow.className.replace("Unread", "");

			var upd_img_pic = document.getElementById("FUPDPIC-" + active_post_id);

			if (upd_img_pic) {
				upd_img_pic.src = "images/blank_icon.gif";
			}

			selectTableRowsByIdPrefix('headlinesList', 'RROW-', 'RCHK-', false);
			markHeadline(active_post_id);

			xmlhttp.open("GET", query, true);
			xmlhttp.onreadystatechange=article_callback;
			xmlhttp.send(null);
		} else {
			debug("xmlhttp busy (@view)");
			printLockingError();
		}  

	} catch (e) {
		exception_error("view", e);
	}
}

function toggleMark(id) {

	if (!xmlhttp_ready(xmlhttp_rpc)) {
		printLockingError();
		return;
	}

	var query = "backend.php?op=rpc&id=" + id + "&subop=mark";

	var mark_img = document.getElementById("FMARKPIC-" + id);
	var vfeedu = document.getElementById("FEEDU--1");
	var crow = document.getElementById("RROW-" + id);

	if (mark_img.alt != "Reset mark") {
		mark_img.src = "images/mark_set.png";
		mark_img.alt = "Reset mark";
		query = query + "&mark=1";

		if (vfeedu && crow.className.match("Unread")) {
			vfeedu.innerHTML = (+vfeedu.innerHTML) + 1;
		}

	} else {
		mark_img.src = "images/mark_unset.png";
		mark_img.alt = "Set mark";
		query = query + "&mark=0";

		if (vfeedu && crow.className.match("Unread")) {
			vfeedu.innerHTML = (+vfeedu.innerHTML) - 1;
		}

	}

	var vfeedctr = document.getElementById("FEEDCTR--1");
	var vfeedr = document.getElementById("FEEDR--1");

	if (vfeedu && vfeedctr) {
		if ((+vfeedu.innerHTML) > 0) {
			if (crow.className.match("Unread") && !vfeedr.className.match("Unread")) {
				vfeedr.className = vfeedr.className + "Unread";
				vfeedctr.className = "odd";
			}
		} else {
			vfeedctr.className = "invisible";
			vfeedr.className = vfeedr.className.replace("Unread", "");
		}
	}

	debug("toggle starred for aid " + id);

	new Ajax.Request(query);

}

function moveToPost(mode) {

	// check for combined mode
	if (!document.getElementById("headlinesList"))
		return;

	var rows = getVisibleHeadlineIds();

	var prev_id;
	var next_id;

	if (!document.getElementById('RROW-' + active_post_id)) {
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
	 	if (next_id != undefined) {
			view(next_id, getActiveFeedId());
		}
	}

	if (mode == "prev") {
		if ( prev_id != undefined) {
			view(prev_id, getActiveFeedId());
		}
	} 
}

function toggleUnread(id, cmode) {
	try {
		if (!xmlhttp_ready(xmlhttp_rpc)) {
			printLockingError();
			return;
		}
	
		var row = document.getElementById("RROW-" + id);
		if (row) {
			var nc = row.className;
			nc = nc.replace("Unread", "");
			nc = nc.replace("Selected", "");

			if (row.className.match("Unread")) {
				row.className = nc;
			} else {
				row.className = nc + "Unread";
			}

			if (!cmode) cmode = 2;

			var query = "backend.php?op=rpc&subop=catchupSelected&ids=" +
				param_escape(id) + "&cmode=" + param_escape(cmode);

			xmlhttp_rpc.open("GET", query, true);
			xmlhttp_rpc.onreadystatechange=all_counters_callback;
			xmlhttp_rpc.send(null);

		}


	} catch (e) {
		exception_error("toggleUnread", e);
	}
}

function selectionToggleUnread(cdm_mode, set_state, callback_func) {
	try {
		if (!xmlhttp_ready(xmlhttp_rpc)) {
			printLockingError();
			return;
		}
	
		var rows;

		if (cdm_mode) {
			rows = cdmGetSelectedArticles();
		} else {	
			rows = getSelectedTableRowIds("headlinesList", "RROW", "RCHK");
		}

		for (i = 0; i < rows.length; i++) {
			var row = document.getElementById("RROW-" + rows[i]);
			if (row) {
				var nc = row.className;
				nc = nc.replace("Unread", "");
				nc = nc.replace("Selected", "");

				if (row.className.match("Unread")) {
					row.className = nc + "Selected";
				} else {
					row.className = nc + "UnreadSelected";
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

			var query = "backend.php?op=rpc&subop=catchupSelected&ids=" +
				param_escape(rows.toString()) + "&cmode=" + cmode;

			_catchup_callback_func = callback_func;

			xmlhttp_rpc.open("GET", query, true);
			xmlhttp_rpc.onreadystatechange=catchup_callback;
			xmlhttp_rpc.send(null);

		}

	} catch (e) {
		exception_error("selectionToggleUnread", e);
	}
}

function selectionToggleMarked(cdm_mode) {
	try {
		if (!xmlhttp_ready(xmlhttp_rpc)) {
			printLockingError();
			return;
		}
	
		var rows;
		
		if (cdm_mode) {
			rows = cdmGetSelectedArticles();
		} else {	
			rows = getSelectedTableRowIds("headlinesList", "RROW", "RCHK");
		}	

		for (i = 0; i < rows.length; i++) {
			var row = document.getElementById("RROW-" + rows[i]);
			var mark_img = document.getElementById("FMARKPIC-" + rows[i]);

			if (row && mark_img) {

				if (mark_img.alt == "Set mark") {
					mark_img.src = "images/mark_set.png";
					mark_img.alt = "Reset mark";
					mark_img.setAttribute('onclick', 
						'javascript:toggleMark('+rows[i]+', false)');

				} else {
					mark_img.src = "images/mark_unset.png";
					mark_img.alt = "Set mark";
					mark_img.setAttribute('onclick', 
						'javascript:toggleMark('+rows[i]+', true)');
				}
			}
		}

		if (rows.length > 0) {

			var query = "backend.php?op=rpc&subop=markSelected&ids=" +
				param_escape(rows.toString()) + "&cmode=2";

			xmlhttp_rpc.open("GET", query, true);
			xmlhttp_rpc.onreadystatechange=all_counters_callback;
			xmlhttp_rpc.send(null);

		}

	} catch (e) {
		exception_error("selectionToggleMarked", e);
	}
}

function cdmGetSelectedArticles() {
	var sel_articles = new Array();
	var container = document.getElementById("headlinesInnerContainer");

	for (i = 0; i < container.childNodes.length; i++) {
		var child = container.childNodes[i];

		if (child.id.match("RROW-") && child.className.match("Selected")) {
			var c_id = child.id.replace("RROW-", "");
			sel_articles.push(c_id);
		}
	}

	return sel_articles;
}

// mode = all,none,unread
function cdmSelectArticles(mode) {
	var container = document.getElementById("headlinesInnerContainer");

	for (i = 0; i < container.childNodes.length; i++) {
		var child = container.childNodes[i];

		if (child.id.match("RROW-")) {
			var aid = child.id.replace("RROW-", "");

			var cb = document.getElementById("RCHK-" + aid);

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

	if (document.getElementById("headlinesList")) {
		selectTableRowsByIdPrefix('headlinesList', 'RROW-', 'RCHK-', true, 'Unread', true);
		selectionToggleUnread(false, false, 'viewCurrentFeed()');
		selectTableRowsByIdPrefix('headlinesList', 'RROW-', 'RCHK-', false);
	} else {
		cdmSelectArticles('all');
		selectionToggleUnread(true, false, 'viewCurrentFeed()')
		cdmSelectArticles('none');
	}
}

function labelFromSearch(search, search_mode, match_on, feed_id, is_cat) {

	if (!xmlhttp_ready(xmlhttp_rpc)) {
		printLockingError();
	}

	var title = prompt("Please enter label title:", "");

	if (title) {

		var query = "backend.php?op=labelFromSearch&search=" + param_escape(search) +
			"&smode=" + param_escape(search_mode) + "&match=" + param_escape(match_on) +
			"&feed=" + param_escape(feed_id) + "&is_cat=" + param_escape(is_cat) + 
			"&title=" + param_escape(title);

		debug("LFS: " + query);
	
		xmlhttp_rpc.open("GET", query, true);
		xmlhttp_rpc.onreadystatechange=dlg_frefresh_callback;
		xmlhttp_rpc.send(null);
	}

}

function editArticleTags(id, feed_id) {
	_tag_active_post_id = id;
	_tag_active_feed_id = feed_id;
	displayDlg('editArticleTags', id);
}


function tag_saved_callback() {
	if (xmlhttp_rpc.readyState == 4) {
		try {
			debug("in tag_saved_callback");

			closeInfoBox();
			notify("");

			if (tagsAreDisplayed()) {
				_reload_feedlist_after_view = true;
			}

			if (active_post_id == _tag_active_post_id) {
				debug("reloading current article");
				view(_tag_active_post_id, _tag_active_feed_id);			
			} 

		} catch (e) {
			exception_error("catchup_callback", e);
		}
	}
}

function editTagsSave() {

	if (!xmlhttp_ready(xmlhttp_rpc)) {
		printLockingError();
	}

	notify("Saving article tags...");

	var form = document.forms["tag_edit_form"];

	var query = Form.serialize("tag_edit_form");

	xmlhttp_rpc.open("GET", "backend.php?op=rpc&subop=setArticleTags&" + query, true);			
	xmlhttp_rpc.onreadystatechange=tag_saved_callback;
	xmlhttp_rpc.send(null);

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
		exception_error(e, "editTagsInsert");
	}
}

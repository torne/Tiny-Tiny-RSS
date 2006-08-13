var hotkeys_enabled = true;

var xmlhttp_rpc = Ajax.getTransport();

function browser_has_opacity() {
	return navigator.userAgent.match("Gecko") != null || 
		navigator.userAgent.match("Opera") != null;
}

function exception_error(location, e, silent) {
	var msg;

	if (e.fileName) {
		var base_fname = e.fileName.substring(e.fileName.lastIndexOf("/") + 1);
	
		msg = "Exception: " + e.name + ", " + e.message + 
			"\nFunction: " + location + "()" +
			"\nLocation: " + base_fname + ":" + e.lineNumber;
		
	} else {
		msg = "Exception: " + e + "\nFunction: " + location + "()";
	}

	debug("<b>EXCEPTION: " + msg + "</b>");

	if (!silent) {
		alert(msg);
	}
}

function disableHotkeys() {
	hotkeys_enabled = false;
}

function enableHotkeys() {
	hotkeys_enabled = true;
}

function xmlhttp_ready(obj) {
	return obj.readyState == 4 || obj.readyState == 0 || !obj.readyState;
}

function notify_callback() {
	var container = document.getElementById('notify');
	if (xmlhttp.readyState == 4) {
		container.innerHTML=xmlhttp.responseText;
	}
}

function rpc_notify_callback() {
	var container = document.getElementById('notify');
	if (xmlhttp_rpc.readyState == 4) {
		container.innerHTML=xmlhttp_rpc.responseText;
	}
}

function param_escape(arg) {
	if (typeof encodeURIComponent != 'undefined')
		return encodeURIComponent(arg);	
	else
		return escape(arg);
}

function param_unescape(arg) {
	if (typeof decodeURIComponent != 'undefined')
		return decodeURIComponent(arg);	
	else
		return unescape(arg);
}

function delay(gap) {
	var then,now; 
	then=new Date().getTime();
	now=then;
	while((now-then)<gap) {
		now=new Date().getTime();
	}
}

var notify_hide_timerid = false;
var notify_last_doc = false;

var notify_effect = false;

function hide_notify() {
	if (notify_last_doc) {
		var n = notify_last_doc.getElementById("notify");		
		if (browser_has_opacity()) {
			if (notify_opacity >= 0) {
				notify_opacity = notify_opacity - 0.1;
				n.style.opacity = notify_opacity;
				notify_hide_timerid = window.setTimeout("hide_notify()", 20);	
			} else {
				n.style.display = "none";
				n.style.opacity = 1;
			}
		} else {
			n.style.display = "none";
		}
	}
}

function notify_real(msg, doc, no_hide, is_err) {

	var n = doc.getElementById("notify");
	var nb = doc.getElementById("notify_body");

	if (!n || !nb) return;

	if (notify_hide_timerid) {
		window.clearTimeout(notify_hide_timerid);
	}

	notify_last_doc = doc;
	notify_opacity = 1;

	if (msg == "") {
		if (n.style.display == "block") {
			notify_hide_timerid = window.setTimeout("hide_notify()", 0);
		}
		return;
	} else {
		n.style.display = "block";
	}

	if (is_err) {
		n.style.backgroundColor = "#ffcccc";
		n.style.color = "black";
		n.style.borderColor = "#ff0000";
	} else {
		n.style.backgroundColor = "#fff7d5";
		n.style.borderColor = "#d7c47a";
		n.style.color = "black";
	}

	nb.innerHTML = msg;

	if (!no_hide) {
		notify_hide_timerid = window.setTimeout("hide_notify()", 3000);
	}
}

function p_notify(msg, no_hide, is_err) {
	notify_real(msg, parent.document, no_hide, is_err);
}

function notify(msg, no_hide, is_err) {
	notify_real(msg, document, no_hide, is_err);
}

function printLockingError() {
	notify("Please wait until operation finishes");}

function hotkey_handler(e) {

	try {

		var keycode;
	
		if (!hotkeys_enabled) return;
	
		if (window.event) {
			keycode = window.event.keyCode;
		} else if (e) {
			keycode = e.which;
		}

		var m_ctx = getMainContext();
		var f_ctx = getFeedsContext();
		var h_ctx = getHeadlinesContext();

		if (keycode == 82) { // r
			return m_ctx.scheduleFeedUpdate(true);
		}

		if (keycode == 83) { // r
			return m_ctx.displayDlg("search", getActiveFeedId());
		}

		if (keycode == 85) { // u
			if (getActiveFeedId()) {
				return f_ctx.viewfeed(getActiveFeedId(), "ForceUpdate");
			}
		}
	
		if (keycode == 65) { // a
			return m_ctx.toggleDispRead();
		}
	
		var f_doc = m_ctx.frames["feeds-frame"].document;
		var feedlist = f_doc.getElementById('feedList');
	
		if (keycode == 74) { // j
			var feed = getActiveFeedId();
			var new_feed = getRelativeFeedId(feedlist, feed, 'prev');
			if (new_feed) viewfeed(new_feed, '');
		}
	
		if (keycode == 75) { // k
			var feed = getActiveFeedId();
			var new_feed = getRelativeFeedId(feedlist, feed, 'next');
			if (new_feed) viewfeed(new_feed, '');
		}

		if (keycode == 78 || keycode == 40) { // n, down
			if (typeof h_ctx.moveToPost != undefined) {
				return h_ctx.moveToPost('next');
			}
		}
	
		if (keycode == 80 || keycode == 38) { // p, up
			if (typeof h_ctx.moveToPost != undefined) {
				return h_ctx.moveToPost('prev');
			}
		}
		
		if (typeof localHotkeyHandler != 'undefined') {
			try {
				localHotkeyHandler(keycode);
			} catch (e) {
				exception_error("hotkey_handler, local:", e);
			}
		}
	} catch (e) {
		exception_error("hotkey_handler", e);
	}
}

function cleanSelectedList(element) {
	var content = document.getElementById(element);

	if (!document.getElementById("feedCatHolder")) {
		for (i = 0; i < content.childNodes.length; i++) {
			var child = content.childNodes[i];
			try {
				child.className = child.className.replace("Selected", "");
			} catch (e) {
				//
			}
		}
	} else {
		for (i = 0; i < content.childNodes.length; i++) {
			var child = content.childNodes[i];
			if (child.id == "feedCatHolder") {
				parent.debug(child.id);
				var fcat = child.lastChild;
				for (j = 0; j < fcat.childNodes.length; j++) {
					var feed = fcat.childNodes[j];
					feed.className = feed.className.replace("Selected", "");
				}		
			}
		} 
	}
}


function cleanSelected(element) {
	var content = document.getElementById(element);

	for (i = 0; i < content.rows.length; i++) {
		content.rows[i].className = content.rows[i].className.replace("Selected", "");
	}
}

function getVisibleUnreadHeadlines() {
	var content = document.getElementById("headlinesList");

	var rows = new Array();

	for (i = 0; i < content.rows.length; i++) {
		var row_id = content.rows[i].id.replace("RROW-", "");
		if (row_id.length > 0 && content.rows[i].className.match("Unread")) {
				rows.push(row_id);	
		}
	}
	return rows;
}

function getVisibleHeadlineIds() {

	var content = document.getElementById("headlinesList");

	var rows = new Array();

	for (i = 0; i < content.rows.length; i++) {
		var row_id = content.rows[i].id.replace("RROW-", "");
		if (row_id.length > 0) {
				rows.push(row_id);	
		}
	}
	return rows;
}

function getFirstVisibleHeadlineId() {
	var rows = getVisibleHeadlineIds();
	return rows[0];
}

function getLastVisibleHeadlineId() {
	var rows = getVisibleHeadlineIds();
	return rows[rows.length-1];
}

function markHeadline(id) {
	var row = document.getElementById("RROW-" + id);
	if (row) {
		var is_active = false;
	
		if (row.className.match("Active")) {
			is_active = true;
		}
		row.className = row.className.replace("Selected", "");
		row.className = row.className.replace("Active", "");
		row.className = row.className.replace("Insensitive", "");
		
		if (is_active) {
			row.className = row.className = "Active";
		}
		
		var check = document.getElementById("RCHK-" + id);

		if (check) {
			check.checked = true;
		}

		row.className = row.className + "Selected"; 
		
	}
}

function getFeedIds() {
	var content = document.getElementById("feedsList");

	var rows = new Array();

	for (i = 0; i < content.rows.length; i++) {
		var id = content.rows[i].id.replace("FEEDR-", "");
		if (id.length > 0) {
			rows.push(id);
		}
	}

	return rows;
}

function setCookie(name, value, lifetime, path, domain, secure) {
	
	var d = false;
	
	if (lifetime) {
		d = new Date();
		d.setTime(lifetime * 1000);
	}
	
	int_setCookie(name, value, d, path, domain, secure);

}

function int_setCookie(name, value, expires, path, domain, secure) {
	document.cookie= name + "=" + escape(value) +
		((expires) ? "; expires=" + expires.toGMTString() : "") +
		((path) ? "; path=" + path : "") +
		((domain) ? "; domain=" + domain : "") +
		((secure) ? "; secure" : "");
}

function delCookie(name, path, domain) {
	if (getCookie(name)) {
		document.cookie = name + "=" +
		((path) ? ";path=" + path : "") +
		((domain) ? ";domain=" + domain : "" ) +
		";expires=Thu, 01-Jan-1970 00:00:01 GMT";
	}
}
		

function getCookie(name) {

	var dc = document.cookie;
	var prefix = name + "=";
	var begin = dc.indexOf("; " + prefix);
	if (begin == -1) {
	    begin = dc.indexOf(prefix);
	    if (begin != 0) return null;
	}
	else {
	    begin += 2;
	}
	var end = document.cookie.indexOf(";", begin);
	if (end == -1) {
	    end = dc.length;
	}
	return unescape(dc.substring(begin + prefix.length, end));
}

function disableContainerChildren(id, disable, doc) {

	if (!doc) doc = document;

	var container = doc.getElementById(id);

	if (!container) {
		//alert("disableContainerChildren: element " + id + " not found");
		return;
	}

	for (var i = 0; i < container.childNodes.length; i++) {
		var child = container.childNodes[i];

		try {
			child.disabled = disable;
		} catch (E) {

		}

		if (disable) {
			if (child.className && child.className.match("button")) {
				child.className = "disabledButton";
			}
		} else {
			if (child.className && child.className.match("disabledButton")) {
				child.className = "button";
			}
		} 
	}

}

function gotoPreferences() {
	document.location.href = "prefs.php";
}

function gotoMain() {
	document.location.href = "tt-rss.php";
}

function gotoExportOpml() {
	document.location.href = "opml.php?op=Export";
}

function getActiveFeedId() {
//	return getCookie("ttrss_vf_actfeed");
	try {
		debug("gAFID: " + getMainContext().active_feed_id);
		return getMainContext().active_feed_id;
	} catch (e) {
		exception_error("getActiveFeedId", e);
	}
}

function activeFeedIsCat() {
	return getMainContext().active_feed_is_cat;
}

function setActiveFeedId(id) {
//	return setCookie("ttrss_vf_actfeed", id);
	try {
		debug("sAFID(" + id + ")");
		getMainContext().active_feed_id = id;
	} catch (e) {
		exception_error("setActiveFeedId", e);
	}
}

function parse_counters(reply, scheduled_call) {
	try {
		var f_document = getFeedsContext().document;
		var title_obj = getMainContext();

		var feeds_found = 0;

		if (reply.firstChild && reply.firstChild.firstChild) {
			debug("<b>wrong element passed to parse_counters, adjusting.</b>");
			reply = reply.firstChild;
		}

		debug("F_DOC: " + f_document + ", T_OBJ: " + title_obj);

		for (var l = 0; l < reply.childNodes.length; l++) {
			if (!reply.childNodes[l] ||
				typeof(reply.childNodes[l].getAttribute) == "undefined") {
				// where did this come from?
				continue;
			}

			var id = reply.childNodes[l].getAttribute("id");
			var t = reply.childNodes[l].getAttribute("type");
			var ctr = reply.childNodes[l].getAttribute("counter");
			var error = reply.childNodes[l].getAttribute("error");
			var has_img = reply.childNodes[l].getAttribute("hi");
			var updated = reply.childNodes[l].getAttribute("updated");
	
			if (t == "feed") {
				feeds_found++;
			}

			if (id == "global-unread") {
				title_obj.global_unread = ctr;
				title_obj.updateTitle();
				continue;
			}
	
			if (t == "category") {
				var catctr = f_document.getElementById("FCATCTR-" + id);
				if (catctr) {
					catctr.innerHTML = "(" + ctr + " unread)";
				}
				continue;
			}
		
			var feedctr = f_document.getElementById("FEEDCTR-" + id);
			var feedu = f_document.getElementById("FEEDU-" + id);
			var feedr = f_document.getElementById("FEEDR-" + id);
			var feed_img = f_document.getElementById("FIMG-" + id);
			var feedlink = f_document.getElementById("FEEDL-" + id);
			var feedupd = f_document.getElementById("FLUPD-" + id);

			if (updated && feedlink) {
				if (error) {
					feedlink.title = "Error: " + error + " (" + updated + ")";
				} else {
					feedlink.title = "Updated: " + updated;
				}
			}

			if (updated && feedupd) {
				if (error) {
					feedupd.innerHTML = updated + " (Error)";
				} else {
					feedupd.innerHTML = updated;
				}
			}

			if (feedctr && feedu && feedr) {

				if (feedu.innerHTML != ctr && id == getActiveFeedId() && scheduled_call) {
					var hf = title_obj.parent.frames["headlines-frame"];
					hf.location.reload(true);
				}
		
				feedu.innerHTML = ctr;

				if (error) {
					feedr.className = feedr.className.replace("feed", "error");
				} else if (id > 0) {
					feedr.className = feedr.className.replace("error", "feed");
				}
	
				if (ctr > 0) {					
					feedctr.className = "odd";
					if (!feedr.className.match("Unread")) {
						var is_selected = feedr.className.match("Selected");
		
						feedr.className = feedr.className.replace("Selected", "");
						feedr.className = feedr.className.replace("Unread", "");
		
						feedr.className = feedr.className + "Unread";
		
						if (is_selected) {
							feedr.className = feedr.className + "Selected";
						}
		
					}
				} else {
					feedctr.className = "invisible";
					feedr.className = feedr.className.replace("Unread", "");
				}			
			}
		}

		var feeds_stored = getMainContext().number_of_feeds;

		debug("Feed counters, C: " + feeds_found + ", S:" + feeds_stored);

		if (feeds_stored != feeds_found) {
			if (feeds_found != 0) {
				getMainContext().number_of_feeds = feeds_found;
			}
			if (feeds_stored != 0 && feeds_found != 0) {
				debug("Subscribed feed number changed, refreshing feedlist");
				updateFeedList();
			}
		}

	} catch (e) {
		exception_error("parse_counters", e);
	}
}

function all_counters_callback() {
	if (xmlhttp_rpc.readyState == 4) {
		try {
			if (!xmlhttp_rpc.responseXML || !xmlhttp_rpc.responseXML.firstChild) {
				debug("[all_counters_callback] backend did not return valid XML");
				return;
			}

			debug("in all_counters_callback");

			var reply = xmlhttp_rpc.responseXML.firstChild;

			var counters = reply.firstChild;

			parse_counters(counters);

			var runtime = counters.nextSibling;

			if (runtime) {
				getMainContext().parse_runtime_info(runtime);
			}

			if (getInitParam("feeds_sort_by_unread") == 1) {
				resort_feedlist();		
			}	

			hideOrShowFeeds(document, getInitParam("hide_read_feeds") == 1);

		} catch (e) {
			exception_error("all_counters_callback", e);
		}
	}
}

function get_feed_entry_unread(doc, elem) {

	var id = elem.id.replace("FEEDR-", "");

	if (id <= 0) {
		return -1;
	}

	try {
		return parseInt(doc.getElementById("FEEDU-" + id).innerHTML);	
	} catch (e) {
		return -1;
	}
}

function resort_category(doc, node) {
	debug("resort_category: " + node);

	if (node.hasChildNodes() && node.firstChild.nextSibling != false) {  
		for (i = 0; i < node.childNodes.length; i++) {
			if (node.childNodes[i].nodeName != "LI") { continue; }

			if (get_feed_entry_unread(doc, node.childNodes[i]) < 0) {
				continue;
			}

			for (j = i+1; j < node.childNodes.length; j++) {			
				if (node.childNodes[j].nodeName != "LI") { continue; }  

				var tmp_val = get_feed_entry_unread(doc, node.childNodes[i]);
				var cur_val = get_feed_entry_unread(doc, node.childNodes[j]);

				if (cur_val > tmp_val) {
					tempnode_i = node.childNodes[i].cloneNode(true);
					tempnode_j = node.childNodes[j].cloneNode(true);
					node.replaceChild(tempnode_i, node.childNodes[j]);
					node.replaceChild(tempnode_j, node.childNodes[i]);
				}
			}

		}
	}

}

function resort_feedlist() {
	debug("resort_feedlist");

	var fd = getFeedsContext().document;

	if (fd.getElementById("feedCatHolder")) {

		var feeds = fd.getElementById("feedList");
		var child = feeds.firstChild;

		while (child) {

			if (child.id == "feedCatHolder") {
				resort_category(fd, child.firstChild);
			}
	
			child = child.nextSibling;
		}

	} else {
		resort_category(fd, fd.getElementById("feedList"));
	}
}

function update_all_counters(feed) {
	if (xmlhttp_ready(xmlhttp_rpc)) {
		var query = "backend.php?op=rpc&subop=getAllCounters";

		if (feed > 0) {
			query = query + "&aid=" + feed;
		}

		xmlhttp_rpc.open("GET", query, true);
		xmlhttp_rpc.onreadystatechange=all_counters_callback;
		xmlhttp_rpc.send(null);
	}
}

function popupHelp(tid) {
	var w = window.open("backend.php?op=help&tid=" + tid,
		"Popup Help", 
		"menubar=no,location=no,resizable=yes,scrollbars=yes,status=no");
}

/** * @(#)isNumeric.js * * Copyright (c) 2000 by Sundar Dorai-Raj
  * * @author Sundar Dorai-Raj
  * * Email: sdoraira@vt.edu
  * * This program is free software; you can redistribute it and/or
  * * modify it under the terms of the GNU General Public License 
  * * as published by the Free Software Foundation; either version 2 
  * * of the License, or (at your option) any later version, 
  * * provided that any use properly credits the author. 
  * * This program is distributed in the hope that it will be useful,
  * * but WITHOUT ANY WARRANTY; without even the implied warranty of
  * * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  * * GNU General Public License for more details at http://www.gnu.org * * */

  var numbers=".0123456789";
  function isNumeric(x) {
    // is x a String or a character?
    if(x.length>1) {
      // remove negative sign
      x=Math.abs(x)+"";
      for(j=0;j<x.length;j++) {
        // call isNumeric recursively for each character
        number=isNumeric(x.substring(j,j+1));
        if(!number) return number;
      }
      return number;
    }
    else {
      // if x is number return true
      if(numbers.indexOf(x)>=0) return true;
      return false;
    }
  }


function hideOrShowFeeds(doc, hide) {

	var fd = getFeedsContext().document;

	var list = fd.getElementById("feedList");

	if (fd.getElementById("feedCatHolder")) {

		var feeds = fd.getElementById("feedList");
		var child = feeds.firstChild;

		while (child) {

			if (child.id == "feedCatHolder") {
				hideOrShowFeedsCategory(fd, child.firstChild, hide, child.previousSibling);
			}
	
			child = child.nextSibling;
		}

	} else {
		hideOrShowFeedsCategory(fd, fd.getElementById("feedList"), hide);
	}
}

function hideOrShowFeedsCategory(doc, node, hide, cat_node) {

//	debug("hideOrShowFeedsCategory: " + node + " (" + hide + ")");

	var cat_unread = 0;

	if (node.hasChildNodes() && node.firstChild.nextSibling != false) {  
		for (i = 0; i < node.childNodes.length; i++) {
			if (node.childNodes[i].nodeName != "LI") { continue; }

			if (node.childNodes[i].style != undefined) {

				var has_unread = (node.childNodes[i].className != "feed");
	
	//			debug(node.childNodes[i].id + " --> " + has_unread);
	
				if (hide && !has_unread) {
					node.childNodes[i].style.display = "none";
				}
	
				if (!hide) {
					node.childNodes[i].style.display = "list-item";
				}
	
				if (has_unread) {
					cat_unread++;
				}
			}
		}
	}	

	if (cat_unread == 0) {
		if (cat_node.style == undefined) {
			debug("ERROR: supplied cat_node " + cat_node + 
				" has no styles. WTF?");
			return;
		}
		if (hide) {
			cat_node.style.display = "none";
		} else {
			cat_node.style.display = "list-item";
		}
	}

//	debug("unread for category: " + cat_unread);
}

function selectTableRow(r, do_select) {
	r.className = r.className.replace("Selected", "");
	
	if (do_select) {
		r.className = r.className + "Selected";
	}
}

function selectTableRowById(elem_id, check_id, do_select) {

	try {

		var row = document.getElementById(elem_id);

		if (row) {
			selectTableRow(row, do_select);
		}		

		var check = document.getElementById(check_id);

		if (check) {
			check.checked = do_select;
		}
	} catch (e) {
		exception_error("selectTableRowById", e);
	}
}

function selectTableRowsByIdPrefix(content_id, prefix, check_prefix, do_select, 
	classcheck, reset_others) {

	var content = document.getElementById(content_id);

	if (!content) {
		alert("[selectTableRows] Element " + content_id + " not found.");
		return;
	}

	for (i = 0; i < content.rows.length; i++) {
		if (!classcheck || content.rows[i].className.match(classcheck)) {
	
			if (content.rows[i].id.match(prefix)) {
				selectTableRow(content.rows[i], do_select);
			
				var row_id = content.rows[i].id.replace(prefix, "");
				var check = document.getElementById(check_prefix + row_id);

				if (check) {
					check.checked = do_select;
				}
			} else if (reset_others) {
				selectTableRow(content.rows[i], false);

				var row_id = content.rows[i].id.replace(prefix, "");
				var check = document.getElementById(check_prefix + row_id);

				if (check) {
					check.checked = false;
				}

			}
		} else if (reset_others) {
			selectTableRow(content.rows[i], false);

			var row_id = content.rows[i].id.replace(prefix, "");
			var check = document.getElementById(check_prefix + row_id);

			if (check) {
				check.checked = false;
			}

		}
	}
}

function getSelectedTableRowIds(content_id, prefix) {

	var content = document.getElementById(content_id);

	if (!content) {
		alert("[getSelectedTableRowIds] Element " + content_id + " not found.");
		return;
	}

	var sel_rows = new Array();

	for (i = 0; i < content.rows.length; i++) {
		if (content.rows[i].id.match(prefix) && 
				content.rows[i].className.match("Selected")) {
				
			var row_id = content.rows[i].id.replace(prefix + "-", "");
			sel_rows.push(row_id);	
		}
	}

	return sel_rows;

}

function toggleSelectRowById(sender, id) {
	var row = document.getElementById(id);

	if (sender.checked) {
		if (!row.className.match("Selected")) {
			row.className = row.className + "Selected";
		}
	} else {
		if (row.className.match("Selected")) {
			row.className = row.className.replace("Selected", "");
		}
	}
}

function toggleSelectListRow(sender) {
	var parent_row = sender.parentNode;

	if (sender.checked) {
		if (!parent_row.className.match("Selected")) {
			parent_row.className = parent_row.className + "Selected";
		}
	} else {
		if (parent_row.className.match("Selected")) {
			parent_row.className = parent_row.className.replace("Selected", "");
		}
	}
}


function toggleSelectRow(sender) {
	var parent_row = sender.parentNode.parentNode;

	if (sender.checked) {
		if (!parent_row.className.match("Selected")) {
			parent_row.className = parent_row.className + "Selected";
		}
	} else {
		if (parent_row.className.match("Selected")) {
			parent_row.className = parent_row.className.replace("Selected", "");
		}
	}
}

function openExternalUrl(url) {
	var w = window.open(url);
}

function getRelativeFeedId(list, id, direction, unread_only) {	
	if (!id) {
		if (direction == "next") {
			for (i = 0; i < list.childNodes.length; i++) {
				var child = list.childNodes[i];
				if (child.id && child.id == "feedCatHolder") {
					if (child.lastChild) {
						var cr = getRelativeFeedId(child.firstChild, id, direction);
						if (cr) return cr;					
					}
				} else if (child.id && child.id.match("FEEDR-")) {
					return child.id.replace('FEEDR-', '');
				}				
			}
		}

		// FIXME select last feed doesn't work when only unread feeds are visible

		if (direction == "prev") {
			for (i = list.childNodes.length-1; i >= 0; i--) {
				var child = list.childNodes[i];				
				if (child.id == "feedCatHolder") {
					if (child.firstChild) {
						var cr = getRelativeFeedId(child.firstChild, id, direction);
						if (cr) return cr;					
					}
				} else if (child.id.match("FEEDR-")) {
				
					if (getInitParam("hide_read_feeds") == 1) {
						if (child.className != "feed") {
							alert(child.className);
							return child.id.replace('FEEDR-', '');						
						}							
					} else {
							return child.id.replace('FEEDR-', '');						
					}				
				}				
			}
		}
	} else {
	
		var feed = list.ownerDocument.getElementById("FEEDR-" + getActiveFeedId());
		
		if (getInitParam("hide_read_feeds") == 1) {
			unread_only = true;
		}

		if (direction == "next") {

			var e = feed;

			while (e) {

				if (e.nextSibling) {
				
					e = e.nextSibling;
					
				} else if (e.parentNode.parentNode.nextSibling) {

					var this_cat = e.parentNode.parentNode;

					e = false;

					if (this_cat && this_cat.nextSibling) {
						while (!e && this_cat.nextSibling) {
							this_cat = this_cat.nextSibling;
							if (this_cat.id == "feedCatHolder") {
								e = this_cat.firstChild.firstChild;
							}
						}
					}

				} else {
					e = false;
			   }

				if (e) {
					if (!unread_only || (unread_only && e.className != "feed" && 
							e.className != "error"))	{
						return e.id.replace("FEEDR-", "");
					}
				}
			}
			
		} else if (direction == "prev") {

			var e = feed;

			while (e) {

				if (e.previousSibling) {
				
					e = e.previousSibling;
					
				} else if (e.parentNode.parentNode.previousSibling) {

					var this_cat = e.parentNode.parentNode;

					e = false;

					if (this_cat && this_cat.previousSibling) {
						while (!e && this_cat.previousSibling) {
							this_cat = this_cat.previousSibling;
							if (this_cat.id == "feedCatHolder") {
								e = this_cat.firstChild.lastChild;
							}
						}
					}

				} else {
					e = false;
			   }

				if (e) {
					if (!unread_only || (unread_only && e.className != "feed" && 
							e.className != "error"))	{
						return e.id.replace("FEEDR-", "");
					}
				}
			}
		}
	}
}

function showBlockElement(id) {
	var elem = document.getElementById(id);

	if (elem) {
		elem.style.display = "block";
	} else {
		alert("[showBlockElement] can't find element with id " + id);
	} 
}

function hideParentElement(e) {
	e.parentNode.style.display = "none";
}

function dropboxSelect(e, v) {
	for (i = 0; i < e.length; i++) {
		if (e[i].value == v) {
			e.selectedIndex = i;
			break;
		}
	}
}

// originally stolen from http://www.11tmr.com/11tmr.nsf/d6plinks/MWHE-695L9Z
// bugfixed just a little bit :-)
function getURLParam(strParamName){
  var strReturn = "";
  var strHref = window.location.href;

  if (strHref.indexOf("#") == strHref.length-1) {
		strHref = strHref.substring(0, strHref.length-1);
  }

  if ( strHref.indexOf("?") > -1 ){
    var strQueryString = strHref.substr(strHref.indexOf("?"));
    var aQueryString = strQueryString.split("&");
    for ( var iParam = 0; iParam < aQueryString.length; iParam++ ){
      if (aQueryString[iParam].indexOf(strParamName + "=") > -1 ){
        var aParam = aQueryString[iParam].split("=");
        strReturn = aParam[1];
        break;
      }
    }
  }
  return strReturn;
} 

function leading_zero(p) {
	var s = String(p);
	if (s.length == 1) s = "0" + s;
	return s;
}

function closeInfoBox(cleanup) {
	var box = document.getElementById('infoBox');
	var shadow = document.getElementById('infoBoxShadow');

	if (shadow) {
		shadow.style.display = "none";
	} else if (box) {
		box.style.display = "none";
	}

	if (cleanup) box.innerHTML = "&nbsp;";

	enableHotkeys();

	return false;
}


function displayDlg(id, param) {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	notify("");

	xmlhttp.open("GET", "backend.php?op=dlg&id=" +
		param_escape(id) + "&param=" + param_escape(param), true);
	xmlhttp.onreadystatechange=infobox_callback;
	xmlhttp.send(null);

	disableHotkeys();

	return false;
}

function infobox_submit_callback() {
	if (xmlhttp.readyState == 4) {
		closeInfoBox();

		// called from prefs, reload tab
		if (active_tab) {
			selectTab(active_tab, false);
		}

		notify(xmlhttp.responseText);

	} 
}

function infobox_callback() {
	if (xmlhttp.readyState == 4) {
		var box = document.getElementById('infoBox');
		var shadow = document.getElementById('infoBoxShadow');
		if (box) {			
			box.innerHTML=xmlhttp.responseText;			
			if (shadow) {
				shadow.style.display = "block";
			} else {
				box.style.display = "block";				
			}
		}
	}
}

function qaddFilter() {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	var query = Form.serialize("filter_add_form");

	xmlhttp.open("GET", "backend.php?" + query, true);
	xmlhttp.onreadystatechange=infobox_submit_callback;
	xmlhttp.send(null);

	return true;
}

function toggleSubmitNotEmpty(e, submit_id) {
	try {
		document.getElementById(submit_id).disabled = (e.value == "")
	} catch (e) {
		exception_error("toggleSubmitNotEmpty", e);
	}
}

function isValidURL(s) {
	return s.match("http://") != null || s.match("https://") != null;
}

function qafAdd() {

	if (!xmlhttp_ready(xmlhttp)) {
		printLockingError();
		return
	}

	notify("Adding feed...");

	closeInfoBox();

	var feeds_doc = getFeedsContext().document;

	feeds_doc.location.href = "backend.php?op=error&msg=Loading,%20please wait...";
	
	var query = Form.serialize("feed_add_form");
	
	xmlhttp.open("GET", "backend.php?" + query, true);
	xmlhttp.onreadystatechange=dlg_frefresh_callback;
	xmlhttp.send(null);
}

function filterCR(e)
{
     var key;

     if(window.event)
          key = window.event.keyCode;     //IE
     else
          key = e.which;     //firefox

     if(key == 13)
          return false;
     else
          return true;
}

function getMainContext() {
	if (parent.window != window) {
		return parent.window;
	} else {
		return this.window;
	}
}

function getFeedsContext() {
	try {
		return getMainContext().frames["feeds-frame"];
	} catch (e) {
		exception_error("getFeedsContext", e);
	}
}


function getHeadlinesContext() {
	try {
		return getMainContext().frames["headlines-frame"];
	} catch (e) {
		exception_error("getHeadlinesContext", e);
	}
}

var debug_last_class = "even";

function debug(msg) {
	var ctx = getMainContext();

	if (ctx.debug_last_class == "even") {
		ctx.debug_last_class = "odd";
	} else {
		ctx.debug_last_class = "even";
	}

	var c = ctx.document.getElementById('debug_output');
	if (c && c.style.display == "block") {
		while (c.lastChild != 'undefined' && c.childNodes.length > 100) {
			c.removeChild(c.lastChild);
		}
	
		var d = new Date();
		var ts = leading_zero(d.getHours()) + ":" + leading_zero(d.getMinutes()) +
			":" + leading_zero(d.getSeconds());
		c.innerHTML = "<li class=\"" + ctx.debug_last_class + "\"><span class=\"debugTS\">[" + ts + "]</span> " + 
			msg + "</li>" + c.innerHTML;
	}
}

function getInitParam(key) {
	return getMainContext().init_params[key];
}

function storeInitParam(key, value, is_client) {
	try {
		if (!is_client) {
			if (getMainContext().init_params[key] != value) {
				debug("storeInitParam: " + key + " => " + value);
				//new Ajax.Request("backend.php?op=rpc&subop=storeParam&key=" + 
				//	param_escape(key) + "&value=" + param_escape(value));	
				var f = getMainContext().document.getElementById("backReqBox");
				f.src = "backend.php?op=rpc&subop=storeParam&key=" + 
					param_escape(key) + "&value=" + param_escape(value);
			}
		}
		getMainContext().init_params[key] = value;
	} catch (e) {
		exception_error("storeInitParam", e);
	}
}

/*
function storeInitParams(params, is_client) {
	try {
		var s = "";

		for (k in params) {
			if (getMainContext().init_params[k] != params[k]) {
				s += k + "=" + params[k] + ";";
				getMainContext().init_params[k] = params[k];
			}
		} 

		debug("storeInitParams: " + s);
	
		if (!is_client) {
			new Ajax.Request("backend.php?op=rpc&subop=storeParams&str=" + s);
		}
	} catch (e) {
		exception_error("storeInitParams", e);
	}
}*/

function fatalError(code, message) {
	try {	
		var fe = document.getElementById("fatal_error");
		var fc = document.getElementById("fatal_error_msg");

		fc.innerHTML = "Code " + code + ": " + message;

		fe.style.display = "block";

	} catch (e) {
		exception_error("fatalError", e);
	}
}

function getFeedName(id, is_cat) {	
	var d = getFeedsContext().document;

	var e;

	if (is_cat) {
		e = d.getElementById("FCATN-" + id);
	} else {
		e = d.getElementById("FEEDN-" + id);
	}
	if (e) {
		return e.innerHTML.stripTags();
	} else {
		return null;
	}
}

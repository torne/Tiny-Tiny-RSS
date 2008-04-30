var hotkeys_enabled = true;
var debug_mode_enabled = false;
var xmlhttp_rpc = Ajax.getTransport();

/* add method to remove element from array */

Array.prototype.remove = function(s) {
	for (var i=0; i < this.length; i++) {
		if (s == this[i]) this.splice(i, 1);
	}
}

function browser_has_opacity() {
	return navigator.userAgent.match("Gecko") != null || 
		navigator.userAgent.match("Opera") != null;
}

function is_msie() {
	return navigator.userAgent.match("MSIE");
}

function is_opera() {
	return navigator.userAgent.match("Opera");
}

function is_khtml() {
	return navigator.userAgent.match("KHTML");
}

function is_safari() {
	return navigator.userAgent.match("Safari");
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

function open_article_callback(transport) {
	try {

		if (transport.responseXML) {
			
			var link = transport.responseXML.getElementsByTagName("link")[0];
			var id = transport.responseXML.getElementsByTagName("id")[0];

			debug("open_article_callback, received link: " + link);

			if (link) {
				debug("link url: " + link.firstChild.nodeValue);

				window.open(link.firstChild.nodeValue, "_blank");

				if (id) {
					id = id.firstChild.nodeValue;
					if (!document.getElementById("headlinesList")) {
						window.setTimeout("toggleUnread(" + id + ", 0)", 100);
					}
				}
			} else {
				notify_error("Can't open article: received invalid article link");
			}
		} else {
			notify_error("Can't open article: received invalid XML");
		}

	} catch (e) {
		exception_error("open_article_callback", e);
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

function hide_notify() {
	var n = document.getElementById("notify");
	if (n) {
		n.style.display = "none";
	}
} 

function notify_real(msg, no_hide, n_type) {

	var n = document.getElementById("notify");
	var nb = document.getElementById("notify_body");

	if (!n || !nb) return;

	if (notify_hide_timerid) {
		window.clearTimeout(notify_hide_timerid);
	}

	if (msg == "") {
		if (n.style.display == "block") {
			notify_hide_timerid = window.setTimeout("hide_notify()", 0);
		}
		return;
	} else {
		n.style.display = "block";
	}

	/* types:

		1 - generic
		2 - progress
		3 - error
		4 - info

	*/

	if (typeof __ != 'undefined') {
		msg = __(msg);
	}

	if (n_type == 1) {
		n.className = "notify";
	} else if (n_type == 2) {
		n.className = "notifyProgress";
		msg = "<img src='images/indicator_white.gif'> " + msg;
	} else if (n_type == 3) {
		n.className = "notifyError";
		msg = "<img src='images/sign_excl.gif'> " + msg;
	} else if (n_type == 4) {
		n.className = "notifyInfo";
		msg = "<img src='images/sign_info.gif'> " + msg;
	}

//	msg = "<img src='images/live_com_loading.gif'> " + msg;

	nb.innerHTML = msg;

	if (!no_hide) {
		notify_hide_timerid = window.setTimeout("hide_notify()", 3000);
	}
}

function notify(msg, no_hide) {
	notify_real(msg, no_hide, 1);
}

function notify_progress(msg, no_hide) {
	notify_real(msg, no_hide, 2);
}

function notify_error(msg, no_hide) {
	notify_real(msg, no_hide, 3);

}

function notify_info(msg, no_hide) {
	notify_real(msg, no_hide, 4);
}

function printLockingError() {
	notify_info("Please wait until operation finishes.");
}

function hotkey_handler(e) {

	try {

		var keycode;
		var shift_key = false;

		try {
			shift_key = e.shiftKey;
		} catch (e) {

		}
	
		if (!hotkeys_enabled) {
			debug("hotkeys disabled");
			return;
		}
	
		if (window.event) {
			keycode = window.event.keyCode;
		} else if (e) {
			keycode = e.which;
		}

		if (keycode == 82) { // r
			return scheduleFeedUpdate(true);
		}

		if (keycode == 83) { // s
			return displayDlg("search", getActiveFeedId());
		}

		if (keycode == 85) { // u
			if (getActiveFeedId()) {
				return viewfeed(getActiveFeedId(), "ForceUpdate");
			}
		}
	
		if (keycode == 65) { // a
			return toggleDispRead();
		}
	
		var feedlist = document.getElementById('feedList');
	
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
			if (typeof moveToPost != 'undefined') {
				return moveToPost('next');
			}
		}
	
		if (keycode == 80 || keycode == 38) { // p, up
			if (typeof moveToPost != 'undefined') {
				return moveToPost('prev');
			}
		}
		
		if (keycode == 68 && shift_key) { // d
			if (!debug_mode_enabled) {
				document.getElementById('debug_output').style.display = 'block';
				debug('debug mode activated');
			} else {
				document.getElementById('debug_output').style.display = 'none';
			}

			debug_mode_enabled = !debug_mode_enabled;
		}

		if (keycode == 190 && shift_key) { // >
			viewFeedGoPage(1);
		}
		
		if (keycode == 188 && shift_key) { // <
			viewFeedGoPage(-1);
		}

		if (keycode == 191 && shift_key) { // ?
			viewFeedGoPage(0);
		}

		if (keycode == 69 && shift_key) { // e
			return editFeedDlg(getActiveFeedId());
		}

		if (keycode == 70 && shift_key) { // f
			if (getActiveFeedId()) {
				return catchupCurrentFeed();
			}
		}

		if (keycode == 80 && shift_key) { // p 
			if (getActiveFeedId()) {
				return catchupPage();
			}
		}

		if (keycode == 86) { // v
			if (getActiveArticleId()) {
				openArticleInNewWindow(getActiveArticleId());
			}
		}

		if (keycode == 84) { // t

			var id = getActiveArticleId();

			if (id) {				

				var cb = document.getElementById("RCHK-" + id);

				if (cb) {
					cb.checked = !cb.checked;
					toggleSelectRowById(cb, "RROW-" + id);
				}
			}
		}

		if (keycode == 67) { // c
			var id = getActiveArticleId();

			if (id) {				
				toggleUnread(id, 0);
			}
		}

		if (keycode == 67 && shift_key) { // c
			if (typeof collapse_feedlist != 'undefined') {
				return collapse_feedlist();
			}
		}

		if (keycode == 81 && shift_key) { // shift + q
			if (typeof catchupAllFeeds != 'undefined') {
				return catchupAllFeeds();
			}
		}

		if (typeof localHotkeyHandler != 'undefined') {
			try {
				return localHotkeyHandler(e);
			} catch (e) {
				exception_error("hotkey_handler, local:", e);
			}
		}

		debug("KP=" + keycode);
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
				debug(child.id);
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
		d.setTime(d.getTime() + (lifetime * 1000));
	}

	debug("setCookie: " + name + " => " + value + ": " + d);
	
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
		debug("gAFID: " + active_feed_id);
		return active_feed_id;
	} catch (e) {
		exception_error("getActiveFeedId", e);
	}
}

function activeFeedIsCat() {
	return active_feed_is_cat;
}

function setActiveFeedId(id) {
//	return setCookie("ttrss_vf_actfeed", id);
	try {
		debug("sAFID(" + id + ")");
		active_feed_id = id;
	} catch (e) {
		exception_error("setActiveFeedId", e);
	}
}

function parse_counters(reply, scheduled_call) {
	try {

		var feeds_found = 0;

		var elems = reply.getElementsByTagName("counter");

		for (var l = 0; l < elems.length; l++) {

			var id = elems[l].getAttribute("id");
			var t = elems[l].getAttribute("type");
			var ctr = elems[l].getAttribute("counter");
			var error = elems[l].getAttribute("error");
			var has_img = elems[l].getAttribute("hi");
			var updated = elems[l].getAttribute("updated");
			var title = elems[l].getAttribute("title");
		
			if (id == "global-unread") {
				global_unread = ctr;
				updateTitle();
				continue;
			}

			if (id == "subscribed-feeds") {
				feeds_found = ctr;
				continue;
			}
	
			if (t == "category") {
				var catctr = document.getElementById("FCATCTR-" + id);
				if (catctr) {
					catctr.innerHTML = "(" + ctr + ")";
					if (ctr > 0) {
						catctr.className = "catCtrHasUnread";
					} else {
						catctr.className = "catCtrNoUnread";
					}
				}
				continue;
			}
		
			var feedctr = document.getElementById("FEEDCTR-" + id);
			var feedu = document.getElementById("FEEDU-" + id);
			var feedr = document.getElementById("FEEDR-" + id);
			var feed_img = document.getElementById("FIMG-" + id);
			var feedlink = document.getElementById("FEEDL-" + id);
			var feedupd = document.getElementById("FLUPD-" + id);

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

			if (has_img && feed_img && !is_msie()) {
				if (!feed_img.src.match(id + ".ico")) {
					feed_img.src = getInitParam("icons_location") + "/" + id + ".ico";
				}
			}

			if (feedlink && title) {
				feedlink.innerHTML = title;
			}

			if (feedctr && feedu && feedr) {

				if (feedu.innerHTML != ctr && id == getActiveFeedId() && scheduled_call) {
					viewCurrentFeed();
				}

				var row_needs_hl = (ctr > 0 && ctr > parseInt(feedu.innerHTML));

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

					if (row_needs_hl) { 
						new Effect.Highlight(feedr, {duration: 1, startcolor: "#fff7d5",
							queue: { position:'end', scope: 'EFQ-' + id, limit: 1 } } );
					}
				} else {
					feedctr.className = "invisible";
					feedr.className = feedr.className.replace("Unread", "");
				}			
			}
		}

		hideOrShowFeeds(document, getInitParam("hide_read_feeds") == 1);

		var feeds_stored = number_of_feeds;

		debug("Feed counters, C: " + feeds_found + ", S:" + feeds_stored);

		if (feeds_stored != feeds_found) {
			number_of_feeds = feeds_found;

			if (feeds_stored != 0 && feeds_found != 0) {
				debug("Subscribed feed number changed, refreshing feedlist");
				setTimeout('updateFeedList(false, false)', 50);
			}
		}

	} catch (e) {
		exception_error("parse_counters", e);
	}
}

function parse_counters_reply(transport, scheduled_call) {

	if (!transport.responseXML) {
		notify_error("Backend did not return valid XML", true);
		return;
	}

	var reply = transport.responseXML.firstChild;
	
	if (!reply) {
		notify_error("Backend did not return expected XML object", true);
		updateTitle("");
		return;
	} 
	
	var error_code = false;
	var error_msg = false;

	if (reply.firstChild) {
		error_code = reply.firstChild.getAttribute("error-code");
		error_msg = reply.firstChild.getAttribute("error-msg");
	}

	if (!error_code) {	
		error_code = reply.getAttribute("error-code");
		error_msg = reply.getAttribute("error-msg");
	}
	
	if (error_code && error_code != 0) {
		debug("refetch_callback: got error code " + error_code);
		return fatalError(error_code, error_msg);
	}

	var counters = reply.getElementsByTagName("counters")[0];
	
	parse_counters(counters, scheduled_call);

	var runtime_info = reply.getElementsByTagName("runtime-info")[0];

	parse_runtime_info(runtime_info);

	if (getInitParam("feeds_sort_by_unread") == 1) {
			resort_feedlist();		
	}	

	hideOrShowFeeds(document, getInitParam("hide_read_feeds") == 1);

}

function all_counters_callback2(transport) {
	try {
		debug("<b>all_counters_callback2 IN: " + transport + "</b>");
		parse_counters_reply(transport);
		debug("<b>all_counters_callback2 OUT: " + transport + "</b>");

	} catch (e) {
		exception_error("all_counters_callback2", e);
	}
}

function get_feed_unread(id) {
	try {
		return parseInt(document.getElementById("FEEDU-" + id).innerHTML);	
	} catch (e) {
		exception_error("get_feed_unread", e, true);
		return -1;
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

	var fd = document;

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

	debug("hideOrShowFeeds: " + doc + ", " + hide);

	var fd = document;

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

	if (!node) {
		debug("hideOrShowFeeds: passed node is null, aborting");
		return;
	}

//	debug("cat: " + node.id);

	if (node.hasChildNodes() && node.firstChild.nextSibling != false) {  
		for (i = 0; i < node.childNodes.length; i++) {
			if (node.childNodes[i].nodeName != "LI") { continue; }

			if (node.childNodes[i].style != undefined) {

				var has_unread = (node.childNodes[i].className != "feed" &&
					node.childNodes[i].className != "label" && 
					!(!getInitParam("hide_read_shows_special") && 
						node.childNodes[i].className == "virt") && 
					node.childNodes[i].className != "error" && 
					node.childNodes[i].className != "tag");
	
//				debug(node.childNodes[i].id + " --> " + has_unread);
	
				if (hide && !has_unread) {
					//node.childNodes[i].style.display = "none";
					var id = node.childNodes[i].id;
					Effect.Fade(node.childNodes[i], {duration : 0.3, 
						queue: { position: 'end', scope: 'FFADE-' + id, limit: 1 }});
				}
	
				if (!hide) {
					node.childNodes[i].style.display = "list-item";
					//Effect.Appear(node.childNodes[i], {duration : 0.3});
				}
	
				if (has_unread) {
					node.childNodes[i].style.display = "list-item";
					cat_unread++;
					//Effect.Appear(node.childNodes[i], {duration : 0.3});
					//Effect.Highlight(node.childNodes[i]);
				}
			}
		}
	}	

//	debug("end cat: " + node.id + " unread " + cat_unread);

	if (cat_unread == 0) {
		if (cat_node.style == undefined) {
			debug("ERROR: supplied cat_node " + cat_node + 
				" has no styles. WTF?");
			return;
		}
		if (hide) {
			//cat_node.style.display = "none";
			Effect.Fade(cat_node, {duration : 0.3, 
				queue: { position: 'end', scope: 'CFADE-' + node.id, limit: 1 }});
		} else {
			cat_node.style.display = "list-item";
		}
	} else {
		try {
			cat_node.style.display = "list-item";
		} catch (e) {
			debug(e);
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

function tSR(sender) {
	return toggleSelectRow(sender);
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

function getRelativeFeedId(list, id, direction, unread_only) {	
	var rows = list.getElementsByTagName("LI");
	var feeds = new Array();

	for (var i = 0; i < rows.length; i++) {
		if (rows[i].id.match("FEEDR-")) {

			if (rows[i].id == "FEEDR-" + id || (Element.visible(rows[i]) && Element.visible(rows[i].parentNode))) {

				if (!unread_only || 
						(rows[i].className.match("Unread") || rows[i].id == "FEEDR-" + id)) {
					feeds.push(rows[i].id.replace("FEEDR-", ""));
				}
			}
		}
	}

	if (!id) {
		if (direction == "next") {
			return feeds.shift();
		} else {
			return feeds.pop();
		}
	} else {
		if (direction == "next") {
			var idx = feeds.indexOf(id);
			if (idx != -1 && idx < feeds.length) {
				return feeds[idx+1];					
			} else {
				return getRelativeFeedId(list, false, direction, unread_only);
			}
		} else {
			var idx = feeds.indexOf(id);
			if (idx > 0) {
				return feeds[idx-1];
			} else {
				return getRelativeFeedId(list, false, direction, unread_only);
			}
		}

	}
}

function showBlockElement(id, h_id) {
	var elem = document.getElementById(id);

	if (elem) {
		elem.style.display = "block";

		if (h_id) {
			elem = document.getElementById(h_id);
			if (elem) {
				elem.style.display = "none";
			}
		}
	} else {
		alert("[showBlockElement] can't find element with id " + id);
	} 
}

function appearBlockElement_afh(effect) {

}

function checkboxToggleElement(elem, id) {
	if (elem.checked) {
		Effect.SlideDown(id, {duration : 0.5});
	} else {
		Effect.SlideUp(id, {duration : 0.5});
	}
}

function appearBlockElement(id, h_id) {

	try {
		if (h_id) {
			Effect.Fade(h_id);
		}
		Effect.SlideDown(id, {duration : 1.0, afterFinish: appearBlockElement_afh});
	} catch (e) {
		exception_error("appearBlockElement", e);
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

	if (!is_msie() && !getInitParam("infobox_disable_overlay")) {
		var overlay = document.getElementById("dialog_overlay");
		if (overlay) {
			overlay.style.display = "none";
		}
	}

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

	notify_progress("Loading, please wait...", true);

	disableHotkeys();

	var query = "backend.php?op=dlg&id=" +
		param_escape(id) + "&param=" + param_escape(param);

	new Ajax.Request(query, {
		onComplete: function (transport) {
			infobox_callback2(transport);
		} });

	return false;
}

function infobox_submit_callback2(transport) {
	closeInfoBox();

	try {
		// called from prefs, reload tab
		if (active_tab) {
			selectTab(active_tab, false);
		}
	} catch (e) { }

	if (transport.responseText) {
		notify_info(transport.responseText);
	}
}

function infobox_callback2(transport) {
	try {

		debug("infobox_callback2");

		if (!is_msie() && !getInitParam("infobox_disable_overlay")) {
			var overlay = document.getElementById("dialog_overlay");
			if (overlay) {
				overlay.style.display = "block";
			}
		}

		var box = document.getElementById('infoBox');
		var shadow = document.getElementById('infoBoxShadow');
		if (box) {			

/*				if (!is_safari()) {
				new Draggable(shadow);
			} */

			box.innerHTML=transport.responseText;			
			if (shadow) {
				shadow.style.display = "block";
			} else {
				box.style.display = "block";				
			}
		}

		/* FIXME this needs to be moved out somewhere */

		if (document.getElementById("tags_choices")) {
			new Ajax.Autocompleter('tags_str', 'tags_choices',
				"backend.php?op=rpc&subop=completeTags",
				{ tokens: ',', paramName: "search" });
		}

		notify("");
	} catch (e) {
		exception_error("infobox_callback2", e);
	}
}

function createFilter() {

	var form = document.forms['filter_add_form'];
	var reg_exp = form.reg_exp.value;

	if (reg_exp == "") {
		alert(__("Can't add filter: nothing to match on."));
		return false;
	}

	var query = Form.serialize("filter_add_form");

	new Ajax.Request("backend.php?" + query, {
		onComplete: function (transport) {
			infobox_submit_callback2(transport);
		} });
	
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
	return s.match("http://") != null || s.match("https://") != null || s.match("feed://") != null;
}

function subscribeToFeed() {

	var form = document.forms['feed_add_form'];
	var feed_url = form.feed_url.value;

	if (feed_url == "") {
		alert(__("Can't subscribe: no feed URL given."));
		return false;
	}

	notify_progress(__("Subscribing to feed..."), true);

	closeInfoBox();

	var feeds_doc = document;

//	feeds_doc.location.href = "backend.php?op=error&msg=Loading,%20please wait...";
	
	var query = Form.serialize("feed_add_form");
	
	debug("subscribe q: " + query);

	new Ajax.Request("backend.php", {
		parameters: query,
		onComplete: function(transport) { 
			dlg_frefresh_callback(transport); 
		} });

	return false;
}

function filterCR(e, f)
{
     var key;

     if(window.event)
          key = window.event.keyCode;     //IE
     else
          key = e.which;     //firefox

	if (key == 13) {
  		if (typeof f != 'undefined') {
			f();
			return false;
		} else {
			return false;
		}
	} else {
		return true;
	}
}

function getMainContext() {
	return this.window;
}

function getFeedsContext() {
	return this.window;
}

function getContentContext() {
	return this.window;
}

function getHeadlinesContext() {
	return this.window;
}

var debug_last_class = "even";

function debug(msg) {

	if (debug_last_class == "even") {
		debug_last_class = "odd";
	} else {
		debug_last_class = "even";
	}

	var c = document.getElementById('debug_output');
	if (c && c.style.display == "block") {
		while (c.lastChild != 'undefined' && c.childNodes.length > 100) {
			c.removeChild(c.lastChild);
		}
	
		var d = new Date();
		var ts = leading_zero(d.getHours()) + ":" + leading_zero(d.getMinutes()) +
			":" + leading_zero(d.getSeconds());
		c.innerHTML = "<li class=\"" + debug_last_class + "\"><span class=\"debugTS\">[" + ts + "]</span> " + 
			msg + "</li>" + c.innerHTML;
	}
}

function getInitParam(key) {
	return init_params[key];
}

function storeInitParam(key, value) {
	debug("<b>storeInitParam is OBSOLETE: " + key + " => " + value + "</b>");
	init_params[key] = value;
}

function fatalError(code, message) {
	try {	

		if (code == 6) {
			window.location.href = "tt-rss.php";			
		} else if (code == 5) {
			window.location.href = "update.php";
		} else {
			var fe = document.getElementById("fatal_error");
			var fc = document.getElementById("fatal_error_msg");
	
			if (message == "") message = "Unknown error";

			fc.innerHTML = "<img src='images/sign_excl.gif'> " + message + " (Code " + code + ")";
	
			fe.style.display = "block";
		}

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

function viewContentUrl(url) {
	getContentContext().location = url;
}

function filterDlgCheckAction(sender) {

	try {

		var action = sender[sender.selectedIndex].value;

		var form = document.forms["filter_add_form"];
	
		if (!form) {
			form = document.forms["filter_edit_form"];
		}

		if (!form) {
			debug("filterDlgCheckAction: can't find form!");
			return;
		}

		var action_param = form.action_param;

		if (!action_param) {
			debug("filterDlgCheckAction: can't find action param!");
			return;
		}

		// if selected action supports parameters, enable params field
		if (action == 4 || action == 6) {
			action_param.disabled = false;
		} else {
			action_param.disabled = true;
		}

	} catch (e) {
		exception_error(e, "filterDlgCheckAction");
	}

}

function explainError(code) {
	return displayDlg("explainError", code);
}

// this only searches loaded headlines list, not in CDM
function getRelativePostIds(id) {

	debug("getRelativePostIds: " + id);

	var ids = new Array();
	var container = document.getElementById("headlinesList");

	if (container) {
		var rows = container.rows;

		for (var i = 0; i < rows.length; i++) {
			var r_id = rows[i].id.replace("RROW-", "");

			if (r_id == id) {
				if (i > 0) ids.push(rows[i-1].id.replace("RROW-", ""));
				if (i > 1) ids.push(rows[i-2].id.replace("RROW-", ""));
				if (i > 2) ids.push(rows[i-3].id.replace("RROW-", ""));

				if (i < rows.length-1) ids.push(rows[i+1].id.replace("RROW-", ""));
				if (i < rows.length-2) ids.push(rows[i+2].id.replace("RROW-", ""));
				if (i < rows.length-3) ids.push(rows[i+3].id.replace("RROW-", ""));

				return ids;
			}
		}
	}

	return false;
}

function openArticleInNewWindow(id) {
	try {
		debug("openArticleInNewWindow: " + id);

		var query = "backend.php?op=rpc&subop=getArticleLink&id=" + id;

		debug(query);

		new Ajax.Request(query, {
			onComplete: function(transport) { 
				open_article_callback(transport); 
			} });


	} catch (e) {
		exception_error("openArticleInNewWindow", e);
	}
}

/* http://textsnippets.com/posts/show/835 */

Position.GetWindowSize = function(w) {
        w = w ? w : window;
        var width = w.innerWidth || (w.document.documentElement.clientWidth || w.document.body.clientWidth);
        var height = w.innerHeight || (w.document.documentElement.clientHeight || w.document.body.clientHeight);
        return [width, height]
}

/* http://textsnippets.com/posts/show/836 */

Position.Center = function(element, parent) {
        var w, h, pw, ph;
        var d = Element.getDimensions(element);
        w = d.width;
        h = d.height;
        Position.prepare();
        if (!parent) {
                var ws = Position.GetWindowSize();
                pw = ws[0];
                ph = ws[1];
        } else {
                pw = parent.offsetWidth;
                ph = parent.offsetHeight;
        }
        element.style.top = (ph/2) - (h/2) -  Position.deltaY + "px";
        element.style.left = (pw/2) - (w/2) -  Position.deltaX + "px";
}


function labeltest_callback(transport) {
	try {
		var container = document.getElementById('label_test_result');
	
		container.innerHTML = transport.responseText;
		if (!Element.visible(container)) {
			Effect.SlideDown(container, { duration : 0.5 });
		}

		notify("");
	} catch (e) {
		exception_error("labeltest_callback", e);
	}
}

function labelTest() {

	try {
		var container = document.getElementById('label_test_result');
	
		var form = document.forms['label_edit_form'];
	
		var sql_exp = form.sql_exp.value;
		var description = form.description.value;
	
		notify_progress("Loading, please wait...");
	
		var query = "backend.php?op=pref-labels&subop=test&expr=" +
			param_escape(sql_exp) + "&descr=" + param_escape(description);
	
		new Ajax.Request(query, {
			onComplete: function (transport) {
				labeltest_callback(transport);
			} });
	
		return false;

	} catch (e) {
		exception_error("labelTest", e);
	}
}

function isCdmMode() {
	return !document.getElementById("headlinesList");
}

function getSelectedArticleIds2() {
	var rows = new Array();
	var cdm_mode = isCdmMode();

	if (cdm_mode) {
		rows = cdmGetSelectedArticles();
	} else {	
		rows = getSelectedTableRowIds("headlinesList", "RROW", "RCHK");
	}

	var ids = new Array();

	for (var i = 0; i < rows.length; i++) {
		var chk = document.getElementById("RCHK-" + rows[i]);
		if (chk && chk.checked) {
			ids.push(rows[i]);
		}
	}

	return ids;
}

function displayHelpInfobox(topic_id) {

	var url = "backend.php?op=help&tid=" + param_escape(topic_id);

	var w = window.open(url, "ttrss_help", 
		"status=0,toolbar=0,location=0,width=450,height=500,scrollbars=1,menubar=0");

	return false;
}



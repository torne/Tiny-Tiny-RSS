var hotkeys_enabled = true;
var notify_silent = false;
var last_progress_point = 0;
var async_counters_work = false;

/* add method to remove element from array */

Array.prototype.remove = function(s) {
	for (var i=0; i < this.length; i++) {
		if (s == this[i]) this.splice(i, 1);
	}
}

function is_opera() {
	return window.opera;
}

function exception_error(location, e, ext_info) {
	var msg = format_exception_error(location, e);

	if (!ext_info) ext_info = "N/A";

	disableHotkeys();

	try {

		var ebc = document.getElementById("xebContent");
	
		if (ebc) {
	
			Element.show("dialog_overlay");
			Element.show("errorBoxShadow");
	
			if (ext_info) {
				if (ext_info.responseText) {
					ext_info = ext_info.responseText;
				}
			}
	
			ebc.innerHTML = 
				"<div><b>Error message:</b></div>" +
				"<pre>" + msg + "</pre>" +
				"<div><b>Additional information:</b></div>" +
				"<textarea readonly=\"1\">" + ext_info + "</textarea>";
	
		} else {
			alert(msg);
		}

	} catch (e) {
		alert(msg);

	}

}

function format_exception_error(location, e) {
	var msg;

	if (e.fileName) {
		var base_fname = e.fileName.substring(e.fileName.lastIndexOf("/") + 1);
	
		msg = "Exception: " + e.name + ", " + e.message + 
			"\nFunction: " + location + "()" +
			"\nLocation: " + base_fname + ":" + e.lineNumber;

	} else if (e.description) {
		msg = "Exception: " + e.description + "\nFunction: " + location + "()";
	} else {
		msg = "Exception: " + e + "\nFunction: " + location + "()";
	}

	debug("<b>EXCEPTION: " + msg + "</b>");

	return msg;
}


function disableHotkeys() {
	hotkeys_enabled = false;
}

function enableHotkeys() {
	hotkeys_enabled = true;
}

function open_article_callback(transport) {
	try {

		if (transport.responseXML) {
			
			var link = transport.responseXML.getElementsByTagName("link")[0];
			var id = transport.responseXML.getElementsByTagName("id")[0];

			debug("open_article_callback, received link: " + link);

			if (link && id) {

				var wname = "ttrss_article_" + id.firstChild.nodeValue;

				debug("link url: " + link.firstChild.nodeValue + ", wname " + wname);

				var w = window.open(link.firstChild.nodeValue, wname);

				if (!w) { notify_error("Failed to load article in new window"); }

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

function notify_silent_next() {
	notify_silent = true;
}

function notify_real(msg, no_hide, n_type) {

	if (notify_silent) {
		notify_silent = false;
		return;
	}

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

function cleanSelected(element) {
	var content = document.getElementById(element);

	for (i = 0; i < content.rows.length; i++) {
		content.rows[i].className = content.rows[i].className.replace("Selected", "");
	}
}

function getVisibleUnreadHeadlines() {
	var content = document.getElementById("headlinesList");

	var rows = new Array();

	if (!content) return rows;

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

	if (!content) return rows;

	for (i = 0; i < content.rows.length; i++) {
		var row_id = content.rows[i].id.replace("RROW-", "");
		if (row_id.length > 0) {
				rows.push(row_id);	
		}
	}
	return rows;
}

function getFirstVisibleHeadlineId() {
	if (isCdmMode()) {
		var rows = cdmGetVisibleArticles();
		return rows[0];
	} else {
		var rows = getVisibleHeadlineIds();
		return rows[0];
	}
}

function getLastVisibleHeadlineId() {
	if (isCdmMode()) {
		var rows = cdmGetVisibleArticles();
		return rows[rows.length-1];
	} else {
		var rows = getVisibleHeadlineIds();
		return rows[rows.length-1];
	}
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
			var xmsg = elems[l].getAttribute("xmsg");
	
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

			if (feedupd) {
				if (!updated) updated = "";

				if (error) {
					if (xmsg) {
						feedupd.innerHTML = updated + " " + xmsg + " (Error)";
					} else {
						feedupd.innerHTML = updated + " (Error)";
					}
				} else {
					if (xmsg) {
						feedupd.innerHTML = updated + " " + xmsg;
					} else {
						feedupd.innerHTML = updated;
					}
				}
			}

			if (has_img && feed_img) {
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
		}

		hideOrShowFeeds(getInitParam("hide_read_feeds") == 1);

		var feeds_stored = number_of_feeds;

		debug("Feed counters, C: " + feeds_found + ", S:" + feeds_stored);

		if (feeds_stored != feeds_found) {
			number_of_feeds = feeds_found;

			if (feeds_stored != 0 && feeds_found != 0) {
				debug("Subscribed feed number changed, refreshing feedlist");
				setTimeout('updateFeedList(false, false)', 50);
			}
		} else {
/*			var fl = document.getElementById("feeds-frame").innerHTML;
			if (fl) {
				cache_invalidate("FEEDLIST");
				cache_inject("FEEDLIST", fl, getInitParam("num_feeds"));
			} */
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

	if (!transport_error_check(transport)) return;

	var counters = reply.getElementsByTagName("counters")[0];
	
	parse_counters(counters, scheduled_call);

	var runtime_info = reply.getElementsByTagName("runtime-info")[0];

	parse_runtime_info(runtime_info);

	if (feedsSortByUnread()) {
		resort_feedlist();
	}

	hideOrShowFeeds(getInitParam("hide_read_feeds") == 1);

}

function all_counters_callback2(transport, async_call) {
	try {
		if (async_call) async_counters_work = true;
		
		if (offline_mode) return;

		debug("<b>all_counters_callback2 IN: " + transport + "</b>");
		parse_counters_reply(transport);
		debug("<b>all_counters_callback2 OUT: " + transport + "</b>");

	} catch (e) {
		exception_error("all_counters_callback2", e, transport);
	}
}

function get_feed_unread(id) {
	try {
		return parseInt(document.getElementById("FEEDU-" + id).innerHTML);	
	} catch (e) {
		return -1;
	}
}

function get_cat_unread(id) {
	try {
		var ctr = document.getElementById("FCATCTR-" + id).innerHTML;
		ctr = ctr.replace("(", "");
		ctr = ctr.replace(")", "");
		return parseInt(ctr);
	} catch (e) {
		return -1;
	}
}

function get_feed_entry_unread(elem) {

	var id = elem.id.replace("FEEDR-", "");

	if (id <= 0) {
		return -1;
	}

	try {
		return parseInt(document.getElementById("FEEDU-" + id).innerHTML);	
	} catch (e) {
		return -1;
	}
}

function get_feed_entry_name(elem) {
	var id = elem.id.replace("FEEDR-", "");
	return getFeedName(id);
}


function resort_category(node) {

	try {

		debug("resort_category: " + node);
	
		var by_unread = feedsSortByUnread();
	
		var list = node.getElementsByTagName("LI");
	
		for (i = 0; i < list.length; i++) {
	
			for (j = i+1; j < list.length; j++) {			
	
				var tmp_val = get_feed_entry_unread(list[i]);
				var cur_val = get_feed_entry_unread(list[j]);
	
				var tmp_name = get_feed_entry_name(list[i]);
				var cur_name = get_feed_entry_name(list[j]);
	
				if ((by_unread && (cur_val > tmp_val)) || (!by_unread && (cur_name < tmp_name))) {
					tempnode_i = list[i].cloneNode(true);
					tempnode_j = list[j].cloneNode(true);
					node.replaceChild(tempnode_i, list[j]);
					node.replaceChild(tempnode_j, list[i]);
				}
			}
		}

	} catch (e) {
		exception_error("resort_category", e);
	}

}

function resort_feedlist() {
	debug("resort_feedlist");

	if (document.getElementById("FCATLIST--1")) {

		var lists = document.getElementsByTagName("UL");

		for (var i = 0; i < lists.length; i++) {
			if (lists[i].id && lists[i].id.match("FCATLIST-")) {
				resort_category(lists[i]);
			}
		}

	} else {
		resort_category(document.getElementById("feedList"));
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


function hideOrShowFeeds(hide) {

	try {

	debug("hideOrShowFeeds: " + hide);

	if (document.getElementById("FCATLIST--1")) {

		var lists = document.getElementsByTagName("UL");

		for (var i = 0; i < lists.length; i++) {
			if (lists[i].id && lists[i].id.match("FCATLIST-")) {

				var id = lists[i].id.replace("FCATLIST-", "");
				hideOrShowFeedsCategory(id, hide);
			}
		}

	} else {
		hideOrShowFeedsCategory(null, hide);
	}

	} catch (e) {
		exception_error("hideOrShowFeeds", e);
	}
}

function hideOrShowFeedsCategory(id, hide) {

	try {
	
		var node = null;
		var cat_node = null;

		if (id) {
			node = document.getElementById("FCATLIST-" + id);
			cat_node = document.getElementById("FCAT-" + id);
		} else {
			node = document.getElementById("feedList"); // no categories
		}

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

		if (cat_node) {

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
		}

//	debug("unread for category: " + cat_unread);

	} catch (e) {
		exception_error("hideOrShowFeedsCategory", e);
	}
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
		if (Element.visible(content.rows[i])) {
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

function getNextUnreadCat(id) {
	try {
		var rows = document.getElementById("feedList").getElementsByTagName("LI");
		var feeds = new Array();

		var unread_only = true;
		var is_cat = true;

		for (var i = 0; i < rows.length; i++) {
			if (rows[i].id.match("FCAT-")) {
				if (rows[i].id == "FCAT-" + id && is_cat || (Element.visible(rows[i]) && Element.visible(rows[i].parentNode))) {

					var cat_id = parseInt(rows[i].id.replace("FCAT-", ""));

					if (cat_id >= 0) {
						if (!unread_only || get_cat_unread(cat_id) > 0) {
							feeds.push(cat_id);
						}
					}
				}
			}
		}

		var idx = feeds.indexOf(id);
		if (idx != -1 && idx < feeds.length) {
			return feeds[idx+1];					
		} else {
			return feeds.shift();
		}

	} catch (e) {
		exception_error("getNextUnreadCat", e);
	}
}

function getRelativeFeedId2(id, is_cat, direction, unread_only) {	
	try {

//		alert(id + " IC: " + is_cat + " D: " + direction + " U: " + unread_only);

		var rows = document.getElementById("feedList").getElementsByTagName("LI");
		var feeds = new Array();
	
		for (var i = 0; i < rows.length; i++) {
			if (rows[i].id.match("FEEDR-")) {
	
				if (rows[i].id == "FEEDR-" + id && !is_cat || (Element.visible(rows[i]) && Element.visible(rows[i].parentNode))) {
	
					if (!unread_only || 
							(rows[i].className.match("Unread") || rows[i].id == "FEEDR-" + id)) {
						feeds.push(rows[i].id.replace("FEEDR-", ""));
					}
				}
			}

			if (rows[i].id.match("FCAT-")) {
				if (rows[i].id == "FCAT-" + id && is_cat || (Element.visible(rows[i]) && Element.visible(rows[i].parentNode))) {

					var cat_id = parseInt(rows[i].id.replace("FCAT-", ""));

					if (cat_id >= 0) {
						if (!unread_only || get_cat_unread(cat_id) > 0) {
							feeds.push("CAT:"+cat_id);
						}
					}
				}
			}
		}
	
//		alert(feeds.toString());

		if (!id) {
			if (direction == "next") {
				return feeds.shift();
			} else {
				return feeds.pop();
			}
		} else {
			if (direction == "next") {
				if (is_cat) id = "CAT:" + id;
				var idx = feeds.indexOf(id);
				if (idx != -1 && idx < feeds.length) {
					return feeds[idx+1];					
				} else {
					return getRelativeFeedId2(false, is_cat, direction, unread_only);
				}
			} else {
				if (is_cat) id = "CAT:" + id;
				var idx = feeds.indexOf(id);
				if (idx > 0) {
					return feeds[idx-1];
				} else {
					return getRelativeFeedId2(false, is_cat, direction, unread_only);
				}
			}
	
		}

	} catch (e) {
		exception_error("getRelativeFeedId2", e);
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
		Effect.Appear(id, {duration : 0.5});
	} else {
		Effect.Fade(id, {duration : 0.5});
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

function closeErrorBox() {

	if (Element.visible("errorBoxShadow")) {
		Element.hide("dialog_overlay");
		Element.hide("errorBoxShadow");

		enableHotkeys();
	}

	return false;
}

function closeInfoBox(cleanup) {

	if (Element.visible("infoBoxShadow")) {
		Element.hide("dialog_overlay");
	
		var shadow = document.getElementById('infoBoxShadow');
		var box = document.getElementById('infoBoxShadow');

		Element.hide(shadow);

		if (cleanup) box.innerHTML = "&nbsp;";

		enableHotkeys();
	}

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
		if (typeof active_tab != 'undefined' && active_tab) {
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

		if (!getInitParam("infobox_disable_overlay")) {
			Element.show("dialog_overlay");
		}

		var box = document.getElementById('infoBox');
		var shadow = document.getElementById('infoBoxShadow');
		if (box) {			

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

		disableHotkeys();

		notify("");
	} catch (e) {
		exception_error("infobox_callback2", e);
	}
}

function createFilter() {

	try {

		var form = document.forms['filter_add_form'];
		var reg_exp = form.reg_exp.value;
	
		if (reg_exp == "") {
			alert(__("Can't add filter: nothing to match on."));
			return false;
		}
	
		var query = Form.serialize("filter_add_form");
	
		// we can be called from some other tab in Prefs		
		if (typeof active_tab != 'undefined' && active_tab) {
			active_tab = "filterConfig";
		}
	
		new Ajax.Request("backend.php?" + query, {
			onComplete: function (transport) {
				infobox_submit_callback2(transport);
			} });
		
		return true;

	} catch (e) {
		exception_error("createFilter", e);
	}
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

var debug_last_class = "even";

function debug(msg) {

	if (debug_last_class == "even") {
		debug_last_class = "odd";
	} else {
		debug_last_class = "even";
	}

	var c = document.getElementById('debug_output');
	if (c && Element.visible(c)) {
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

function fatalError(code, msg, ext_info) {
	try {	

		if (!ext_info) ext_info = "N/A";

		if (code == 6) {
			window.location.href = "tt-rss.php";			
		} else if (code == 5) {
			window.location.href = "update.php";
		} else {
	
			if (msg == "") msg = "Unknown error";

			var ebc = document.getElementById("xebContent");
	
			if (ebc) {
	
				Element.show("dialog_overlay");
				Element.show("errorBoxShadow");
				Element.hide("xebBtn");

				if (ext_info) {
					if (ext_info.responseText) {
						ext_info = ext_info.responseText;
					}
				}
	
				ebc.innerHTML = 
					"<div><b>Error message:</b></div>" +
					"<pre>" + msg + "</pre>" +
					"<div><b>Additional information:</b></div>" +
					"<textarea readonly=\"1\">" + ext_info + "</textarea>";
			}
		}

	} catch (e) {
		exception_error("fatalError", e);
	}
}

function getFeedName(id, is_cat) {	
	var e;

	if (is_cat) {
		e = document.getElementById("FCATN-" + id);
	} else {
		e = document.getElementById("FEEDN-" + id);
	}
	if (e) {
		return e.innerHTML.stripTags();
	} else {
		return null;
	}
}

function filterDlgCheckType(sender) {

	try {

		var ftype = sender[sender.selectedIndex].value;

		var form = document.forms["filter_add_form"];
	
		if (!form) {
			form = document.forms["filter_edit_form"];
		}

		if (!form) {
			debug("filterDlgCheckType: can't find form!");
			return;
		}

		// if selected filter type is 5 (Date) enable the modifier dropbox
		if (ftype == 5) {
			Element.show("filter_dlg_date_mod_box");
			Element.show("filter_dlg_date_chk_box");
		} else {
			Element.hide("filter_dlg_date_mod_box");
			Element.hide("filter_dlg_date_chk_box");

		}

	} catch (e) {
		exception_error("filterDlgCheckType", e);
	}

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

		var action_param = document.getElementById("filter_dlg_param_box");

		if (!action_param) {
			debug("filterDlgCheckAction: can't find action param box!");
			return;
		}

		// if selected action supports parameters, enable params field
		if (action == 4 || action == 6 || action == 7) {
			Element.show(action_param);
			if (action != 7) {
				Element.show(form.action_param);
				Element.hide(form.action_param_label);
			} else {
				Element.show(form.action_param_label);
				Element.hide(form.action_param);
			}
		} else {
			Element.hide(action_param);
		}

	} catch (e) {
		exception_error("filterDlgCheckAction", e);
	}

}

function filterDlgCheckDate() {
	try {
		var form = document.forms["filter_add_form"];
	
		if (!form) {
			form = document.forms["filter_edit_form"];
		}

		if (!form) {
			debug("filterDlgCheckAction: can't find form!");
			return;
		}

		var reg_exp = form.reg_exp.value;

		var query = "backend.php?op=rpc&subop=checkDate&date=" + reg_exp;

		new Ajax.Request(query, {
			onComplete: function(transport) { 

				var form = document.forms["filter_add_form"];
	
				if (!form) {
					form = document.forms["filter_edit_form"];
				}

				if (transport.responseXML) {
					var result = transport.responseXML.getElementsByTagName("result")[0];

					if (result && result.firstChild) {
						if (result.firstChild.nodeValue == "1") {

							new Effect.Highlight(form.reg_exp, {startcolor : '#00ff00'});

							return;
						}
					}
				}

				new Effect.Highlight(form.reg_exp, {startcolor : '#ff0000'});

			} });


	} catch (e) {
		exception_error("filterDlgCheckDate", e);
	}
}

function explainError(code) {
	return displayDlg("explainError", code);
}

// this only searches loaded headlines list, not in CDM
function getRelativePostIds(id, limit) {

	if (!limit) limit = 3;

	debug("getRelativePostIds: " + id + " limit=" + limit);

	var ids = new Array();
	var container = document.getElementById("headlinesList");

	if (container) {
		var rows = container.rows;

		for (var i = 0; i < rows.length; i++) {
			var r_id = rows[i].id.replace("RROW-", "");

			if (r_id == id) {
				for (var k = 1; k <= limit; k++) {
					var nid = false;

					if (i > k-1) var nid = rows[i-k].id.replace("RROW-", "");
					if (nid) ids.push(nid);

					if (i < rows.length-k) nid = rows[i+k].id.replace("RROW-", "");
					if (nid) ids.push(nid);
				}

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
		var wname = "ttrss_article_" + id;

		debug(query + " " + wname);

		var w = window.open("", wname);

		if (!w) notify_error("Failed to open window for the article");

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

}

function focus_element(id) {
	try {
		var e = document.getElementById(id);
		if (e) e.focus();
	} catch (e) {
		exception_error("focus_element", e);
	}
	return false;
}

function loading_set_progress(p) {
	try {
		if (p < last_progress_point || !Element.visible("overlay")) return;

		debug("<b>loading_set_progress : " + p + " (" + last_progress_point + ")</b>");

		var o = document.getElementById("l_progress_i");

//		o.style.width = (p * 2) + "px";

		new Effect.Scale(o, p, { 
			scaleY : false,
			scaleFrom : last_progress_point,
			scaleMode: { originalWidth : 200 },
			queue: { position: 'end', scope: 'LSP-Q', limit: 3 } }); 

		last_progress_point = p;

	} catch (e) {
		exception_error("loading_set_progress", e);
	}
}

function remove_splash() {
	if (Element.visible("overlay")) {
		debug("about to remove splash, OMG!");
		Element.hide("overlay");
		debug("removed splash!");
	}
}

function addLabelExample() {
	try {
		var form = document.forms["label_edit_form"];

		var text = form.sql_exp;
		var op = form.label_fields[form.label_fields.selectedIndex];
		var p = form.label_fields_param;

		if (op) {
			op = op.value;

			var tmp = "";

			if (text.value != "") {			
				if (text.value.substring(text.value.length-3, 3).toUpperCase() != "AND") {
					tmp = " AND ";
				} else {
					tmp = " ";
				}
			}

			if (op == "unread") {
				tmp = tmp + "unread = true";
			}

			if (op == "updated") {
				tmp = tmp + "last_read is null and unread = false";
			}

			if (op == "kw_title") {
				if (p.value == "") {
					alert("This action requires a parameter.");
					return false;
				}
				tmp = tmp + "ttrss_entries.title like '%"+p.value+"%'";
			}

			if (op == "kw_content") {
				if (p.value == "") {
					alert("This action requires a parameter.");
					return false;
				}

				tmp = tmp + "ttrss_entries.content like '%"+p.value+"%'";
			}

			if (op == "scoreE") {
				if (isNaN(parseInt(p.value))) {
					alert("This action expects numeric parameter.");
					return false;
				}
				tmp = tmp + "score = " + p.value;
			}

			if (op == "scoreG") {
				if (isNaN(parseInt(p.value))) {
					alert("This action expects numeric parameter.");
					return false;
				}
				tmp = tmp + "score > " + p.value;
			}

			if (op == "scoreL") {
				if (isNaN(parseInt(p.value))) {
					alert("This action expects numeric parameter.");
					return false;
				}
				tmp = tmp + "score < " + p.value;
			}

			if (op == "newerD") {
				if (isNaN(parseInt(p.value))) {
					alert("This action expects numeric parameter.");
					return false;
				}
				tmp = tmp + "updated > NOW() - INTERVAL '"+parseInt(p.value)+" days'";
			}

			if (op == "newerH") {
				if (isNaN(parseInt(p.value))) {
					alert("This action expects numeric parameter.");
					return false;
				}

				tmp = tmp + "updated > NOW() - INTERVAL '"+parseInt(p.value)+" hours'";
			}

			text.value = text.value + tmp;

			p.value = "";

		}
		
	} catch (e) {
		exception_error("addLabelExample", e);
	}

	return false;
}

function labelFieldsCheck(elem) {
	try {
		var op = elem[elem.selectedIndex].value;

		var p = document.forms["label_edit_form"].label_fields_param;

		if (op == "kw_title" || op == "kw_content" || op == "scoreL" || 
				op == "scoreG" ||	op == "scoreE" || op == "newerD" ||
				op == "newerH" ) {
			Element.show(p);
		} else {
			Element.hide(p);
		}

	} catch (e) {
		exception_error("labelFieldsCheck", e);

	}
}

function getSelectedFeedsFromBrowser() {

	var list = document.getElementById("browseFeedList");
	if (!list) list = document.getElementById("browseBigFeedList");

	var selected = new Array();
	
	for (i = 0; i < list.childNodes.length; i++) {
		var child = list.childNodes[i];
		if (child.id && child.id.match("FBROW-")) {
			var id = child.id.replace("FBROW-", "");
			
			var cb = document.getElementById("FBCHK-" + id);

			if (cb.checked) {
				selected.push(id);
			}
		}
	}

	return selected;
}

function updateFeedBrowser() {
	try {

		var query = "backend.php?op=rpc&subop=feedBrowser";

		var search = document.getElementById("feed_browser_search");
		var limit = document.getElementById("feed_browser_limit");

		if (limit) {
			query = query + "&limit=" + limit[limit.selectedIndex].value;
		}

		if (search) {
			query = query + "&search=" + param_escape(search.value);
		}

		notify_progress("Loading, please wait...", true);

		new Ajax.Request(query, {
			onComplete: function(transport) { 
				notify('');

				var c = document.getElementById("browseFeedList");
				var r = transport.responseXML.getElementsByTagName("content")[0];
				var nr = transport.responseXML.getElementsByTagName("num-results")[0];
				var sb = document.getElementById("feed_browser_subscribe");

				if (c && r) {
					c.innerHTML = r.firstChild.nodeValue;
				}
	
				if (nr && sb) {
					if (nr.getAttribute("value") > 0) {
						sb.disabled = false;
					} else {
						sb.disabled = true;
					}
				}

			} });


	} catch (e) {
		exception_error("updateFeedBrowser", e);
	}
}

function browseFeeds(limit) {

	try {

		var query = "backend.php?op=pref-feeds&subop=browse";

		notify_progress("Loading, please wait...", true);

		new Ajax.Request(query, {
			onComplete: function(transport) { 
				infobox_callback2(transport);
			} });

		return false;
	} catch (e) {
		exception_error("browseFeeds", e);
	}
}

function transport_error_check(transport) {
	try {
		if (transport.responseXML) {
			var error = transport.responseXML.getElementsByTagName("error")[0];

			if (error) {
				var code = error.getAttribute("error-code");
				var msg = error.getAttribute("error-msg");
				if (code != 0) {
					fatalError(code, msg);
					return false;
				}
			}
		}
	} catch (e) {
		exception_error("check_for_error_xml", e);
	}
	return true;
}

function strip_tags(s) {
	return s.replace(/<\/?[^>]+(>|$)/g, "");
}

function truncate_string(s, length) {
	if (!length) length = 30;
	var tmp = s.substring(0, length);
	if (s.length > length) tmp += "&hellip;";
	return tmp;
}

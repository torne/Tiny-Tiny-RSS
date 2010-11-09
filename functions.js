var hotkeys_enabled = true;
var notify_silent = false;
var last_progress_point = 0;
var async_counters_work = false;
var sanity_check_done = false;

/* add method to remove element from array */

Array.prototype.remove = function(s) {
	for (var i=0; i < this.length; i++) {
		if (s == this[i]) this.splice(i, 1);
	}
}

/* create console.log if it doesn't exist */

if (!window.console) console = {};
console.log = console.log || function(msg) { };
console.warn = console.warn || function(msg) { };
console.error = console.error || function(msg) { };

function exception_error(location, e, ext_info) {
	var msg = format_exception_error(location, e);

	if (!ext_info) ext_info = false;

	disableHotkeys();

	try {

		var ebc = $("xebContent");
	
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
				"<pre>" + msg + "</pre>";

			if (ext_info) {
				ebc.innerHTML += "<div><b>Additional information:</b></div>" +
				"<textarea readonly=\"1\">" + ext_info + "</textarea>";
			}

			ebc.innerHTML += "<div><b>Stack trace:</b></div>" +
				"<textarea readonly=\"1\">" + e.stack + "</textarea>";
	
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

	console.error("EXCEPTION: " + msg);

	return msg;
}


function disableHotkeys() {
	hotkeys_enabled = false;
}

function enableHotkeys() {
	hotkeys_enabled = true;
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
	var n = $("notify");
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

	var n = $("notify");
	var nb = $("notify_body");

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
		msg = "<img src='"+getInitParam("sign_progress")+"'> " + msg;
	} else if (n_type == 3) {
		n.className = "notifyError";
		msg = "<img src='"+getInitParam("sign_excl")+"'> " + msg;
	} else if (n_type == 4) {
		n.className = "notifyInfo";
		msg = "<img src='"+getInitParam("sign_info")+"'> " + msg;
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
	var content = $(element);

	for (i = 0; i < content.rows.length; i++) {
		content.rows[i].className = content.rows[i].className.replace("Selected", "");
	}
}

function getVisibleUnreadHeadlines() {
	var content = $("headlinesList");

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

	var content = $("headlinesList");

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
	var row = $("RROW-" + id);
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
		
		var check = $("RCHK-" + id);

		if (check) {
			check.checked = true;
		}

		row.className = row.className + "Selected"; 
		
	}
}

function getFeedIds() {
	var content = $("feedsList");

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

	console.log("setCookie: " + name + " => " + value + ": " + d);
	
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

		var elems = JSON.parse(reply.firstChild.nodeValue);

		for (var l = 0; l < elems.length; l++) {

			var id = elems[l].id
			var kind = elems[l].kind;
			var ctr = parseInt(elems[l].counter)
			var error = elems[l].error;
			var has_img = elems[l].has_img;
			var updated = elems[l].updated;
			var title = elems[l].title;
			var xmsg = elems[l].xmsg;
	
			if (id == "global-unread") {

				if (ctr > global_unread) {
					offlineDownloadStart(1);
				}

				global_unread = ctr;
				updateTitle();
				continue;
			}

			if (id == "subscribed-feeds") {
				feeds_found = ctr;
				continue;
			}
	
			if (kind && kind == "cat") {
				var catctr = $("FCATCTR-" + id);
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
		
			var feedctr = $("FEEDCTR-" + id);
			var feedu = $("FEEDU-" + id);
			var feedr = $("FEEDR-" + id);
			var feed_img = $("FIMG-" + id);
			var feedlink = $("FEEDL-" + id);
			var feedupd = $("FLUPD-" + id);

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
					feed_img.src = getInitParam("icons_url") + "/" + id + ".ico";
				}
			}

			if (feedlink && title) {
				feedlink.innerHTML = title;
			}

			if (feedctr && feedu && feedr) {

				if (parseInt(ctr) > 0 && 
						parseInt(feedu.innerHTML) < parseInt(ctr) && 
						id == getActiveFeedId() && scheduled_call) {

					displayNewContentPrompt(id);
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

					if (row_needs_hl && 
							!getInitParam("theme_options").match('no_highlights')) { 
						new Effect.Highlight(feedr, {duration: 1, startcolor: "#fff7d5",
							queue: { position:'end', scope: 'EFQ-' + id, limit: 1 } } );

						cache_invalidate("F:" + id);
					}
				} else {
					feedctr.className = "feedCtrNoUnread";
					feedr.className = feedr.className.replace("Unread", "");
				}			
			}
		}

		hideOrShowFeeds(getInitParam("hide_read_feeds") == 1);

		var feeds_stored = number_of_feeds;

		console.log("Feed counters, C: " + feeds_found + ", S:" + feeds_stored);

		if (feeds_stored != feeds_found) {
			number_of_feeds = feeds_found;

			if (feeds_stored != 0 && feeds_found != 0) {
				console.log("Subscribed feed number changed, refreshing feedlist");
				setTimeout('updateFeedList(false, false)', 50);
			}
		} else {
/*			var fl = $("feeds-frame").innerHTML;
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
	
	if (counters)
		parse_counters(counters, scheduled_call);

	var runtime_info = reply.getElementsByTagName("runtime-info")[0];

	if (runtime_info)
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

		parse_counters_reply(transport);

	} catch (e) {
		exception_error("all_counters_callback2", e, transport);
	}
}

function get_feed_unread(id) {
	try {
		return parseInt($("FEEDU-" + id).innerHTML);	
	} catch (e) {
		return -1;
	}
}

function get_cat_unread(id) {
	try {
		var ctr = $("FCATCTR-" + id).innerHTML;
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
		return parseInt($("FEEDU-" + id).innerHTML);	
	} catch (e) {
		return -1;
	}
}

function get_feed_entry_name(elem) {
	var id = elem.id.replace("FEEDR-", "");
	return getFeedName(id);
}


function resort_category(node, cat_mode) {

	try {

		console.log("resort_category: " + node + " CM=" + cat_mode);
	
		var by_unread = feedsSortByUnread();
	
		var list = node.getElementsByTagName("LI");
	
		for (i = 0; i < list.length; i++) {
	
			for (j = i+1; j < list.length; j++) {			
	
				var tmp_val = get_feed_entry_unread(list[i]);
				var cur_val = get_feed_entry_unread(list[j]);
	
				var tmp_name = get_feed_entry_name(list[i]);
				var cur_name = get_feed_entry_name(list[j]);

				/* we don't want to match FEEDR-0 - e.g. Archived articles */

				var valid_pair = cat_mode || (list[i].id.match(/FEEDR-[1-9]/) &&
						list[j].id.match(/FEEDR-[1-9]/));

				if (valid_pair && ((by_unread && (cur_val > tmp_val)) || (!by_unread && (cur_name < tmp_name)))) {
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
	console.log("resort_feedlist");

	if ($("FCATLIST--1")) {

		var lists = document.getElementsByTagName("UL");

		for (var i = 0; i < lists.length; i++) {
			if (lists[i].id && lists[i].id.match("FCATLIST-")) {
				resort_category(lists[i], true);
			}
		}

	} else {
		resort_category($("feedList"), false);
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

	console.log("hideOrShowFeeds: " + hide);

	if ($("FCATLIST--1")) {

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
			node = $("FCATLIST-" + id);
			cat_node = $("FCAT-" + id);
		} else {
			node = $("feedList"); // no categories
		}

	//	console.log("hideOrShowFeedsCategory: " + node + " (" + hide + ")");
	
		var cat_unread = 0;
	
		if (!node) {
			console.log("hideOrShowFeeds: passed node is null, aborting");
			return;
		}
	
	//	console.log("cat: " + node.id);
	
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
		
	//				console.log(node.childNodes[i].id + " --> " + has_unread);
		
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
	
	//	console.log("end cat: " + node.id + " unread " + cat_unread);

		if (cat_node) {

			if (cat_unread == 0) {
				if (cat_node.style == undefined) {
					console.log("ERROR: supplied cat_node " + cat_node + 
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
					console.log(e);
				}
			}
		}

//	console.log("unread for category: " + cat_unread);

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

		var row = $(elem_id);

		if (row) {
			selectTableRow(row, do_select);
		}		

		var check = $(check_id);

		if (check) {
			check.checked = do_select;
		}
	} catch (e) {
		exception_error("selectTableRowById", e);
	}
}

function selectTableRowsByIdPrefix(content_id, prefix, check_prefix, do_select, 
	classcheck, reset_others) {

	var content = $(content_id);

	if (!content) {
		console.log("[selectTableRows] Element " + content_id + " not found.");
		return;
	}

	for (i = 0; i < content.rows.length; i++) {
		if (Element.visible(content.rows[i])) {
			if (!classcheck || content.rows[i].className.match(classcheck)) {
		
				if (content.rows[i].id.match(prefix)) {
					selectTableRow(content.rows[i], do_select);
				
					var row_id = content.rows[i].id.replace(prefix, "");
					var check = $(check_prefix + row_id);
	
					if (check) {
						check.checked = do_select;
					}
				} else if (reset_others) {
					selectTableRow(content.rows[i], false);
	
					var row_id = content.rows[i].id.replace(prefix, "");
					var check = $(check_prefix + row_id);
	
					if (check) {
						check.checked = false;
					}
	
				}
			} else if (reset_others) {
				selectTableRow(content.rows[i], false);
	
				var row_id = content.rows[i].id.replace(prefix, "");
				var check = $(check_prefix + row_id);
	
				if (check) {
					check.checked = false;
				}
	
			}
		}
	}
}

function getSelectedTableRowIds(content_id, prefix) {

	var content = $(content_id);

	if (!content) {
		console.log("[getSelectedTableRowIds] Element " + content_id + " not found.");
		return new Array();
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
	var row = $(id);

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
		var rows = $("feedList").getElementsByTagName("LI");
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

		var rows = $("feedList").getElementsByTagName("LI");
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

function checkboxToggleElement(elem, id) {
	if (elem.checked) {
		Effect.Appear(id, {duration : 0.5});
	} else {
		Effect.Fade(id, {duration : 0.5});
	}
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

function make_timestamp() {
	var d = new Date();

  	return leading_zero(d.getHours()) + ":" + leading_zero(d.getMinutes()) +
			":" + leading_zero(d.getSeconds());
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

	try {
		enableHotkeys();

		if (Element.visible("infoBoxShadow")) {
			Element.hide("dialog_overlay");
			Element.hide("infoBoxShadow");

			if (cleanup) $("infoBox").innerHTML = "&nbsp;";
		}
	} catch (e) {
		exception_error("closeInfoBox", e);
	}
	
	return false;
}


function displayDlg(id, param, callback) {

	notify_progress("Loading, please wait...", true);

	disableHotkeys();

	var query = "?op=dlg&id=" +
		param_escape(id) + "&param=" + param_escape(param);

	new Ajax.Request("backend.php", {
		parameters: query,
		onComplete: function (transport) {
			infobox_callback2(transport);
			if (callback) callback(transport);
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

		console.log("infobox_callback2");

		var box = $('infoBox');
		
		if (box) {			

			if (!getInitParam("infobox_disable_overlay")) {
				Element.show("dialog_overlay");
			}

			box.innerHTML=transport.responseText;			
			Element.show("infoBoxShadow");
			//Effect.SlideDown("infoBoxShadow", {duration : 1.0});


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

function isValidURL(s) {
	return s.match("http://") != null || s.match("https://") != null || s.match("feed://") != null;
}

function subscribeToFeed() {

	try {

	var form = document.forms['feed_add_form'];
	var feed_url = form.feed_url.value;

	if (feed_url == "") {
		alert(__("Can't subscribe: no feed URL given."));
		return false;
	}

	notify_progress(__("Subscribing to feed..."), true);

	var query = Form.serialize("feed_add_form");
	
	console.log("subscribe q: " + query);

	Form.disable("feed_add_form");

	new Ajax.Request("backend.php", {
		parameters: query,
		onComplete: function(transport) { 
			//dlg_frefresh_callback(transport); 

			notify('');

			var result = transport.responseXML.getElementsByTagName('result')[0];
			var rc = parseInt(result.getAttribute('code'));

			Form.enable("feed_add_form");

			switch (rc) {
			case 1:
				closeInfoBox();
				notify_info(__("Subscribed to %s").replace("%s", feed_url));

				if (inPreferences()) {
					updateFeedList();
				} else {
					setTimeout('updateFeedList(false, false)', 50);
				}
				break;
			case 2:
			case 3:
				alert(__("Can't subscribe to the specified URL."));
				break;
			case 0:
				alert(__("You are already subscribed to this feed."));
				break;
			}

		} });

	} catch (e) {
		exception_error("subscribeToFeed", e);
	}

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

function getInitParam(key) {
	return init_params[key];
}

function setInitParam(key, value) {
	init_params[key] = value;
}

function fatalError(code, msg, ext_info) {
	try {	

		if (!ext_info) ext_info = "N/A";

		if (code == 6) {
			window.location.href = "tt-rss.php";			
		} else if (code == 5) {
			window.location.href = "db-updater.php";
		} else {
	
			if (msg == "") msg = "Unknown error";

			var ebc = $("xebContent");
	
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
		e = $("FCATN-" + id);
	} else {
		e = $("FEEDN-" + id);
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
			console.log("filterDlgCheckType: can't find form!");
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
			console.log("filterDlgCheckAction: can't find form!");
			return;
		}

		var action_param = $("filter_dlg_param_box");

		if (!action_param) {
			console.log("filterDlgCheckAction: can't find action param box!");
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
			console.log("filterDlgCheckAction: can't find form!");
			return;
		}

		var reg_exp = form.reg_exp.value;

		var query = "?op=rpc&subop=checkDate&date=" + reg_exp;

		new Ajax.Request("backend.php", {
			parameters: query,
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

	console.log("getRelativePostIds: " + id + " limit=" + limit);

	var ids = new Array();
	var container = $("headlinesList");

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
		console.log("openArticleInNewWindow: " + id);

		var query = "?op=rpc&subop=getArticleLink&id=" + id;
		var wname = "ttrss_article_" + id;

		console.log(query + " " + wname);

		var w = window.open("", wname);

		if (!w) notify_error("Failed to open window for the article");

		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function(transport) { 

					var link = transport.responseXML.getElementsByTagName("link")[0];
					var id = transport.responseXML.getElementsByTagName("id")[0];
		
					console.log("open_article received link: " + link);
		
					if (link && id) {
		
						var wname = "ttrss_article_" + id.firstChild.nodeValue;
		
						console.log("link url: " + link.firstChild.nodeValue + ", wname " + wname);
		
						var w = window.open(link.firstChild.nodeValue, wname);
		
						if (!w) { notify_error("Failed to load article in new window"); }
		
						if (id) {
							id = id.firstChild.nodeValue;
							if (!$("headlinesList")) {
								window.setTimeout("toggleUnread(" + id + ", 0)", 100);
							}
						}
					} else {
						notify_error("Can't open article: received invalid article link");
					}
				} });

	} catch (e) {
		exception_error("openArticleInNewWindow", e);
	}
}

function isCdmMode() {
	return !$("headlinesList");
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
		var chk = $("RCHK-" + rows[i]);
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

function loading_set_progress(p) {
	try {
		if (p < last_progress_point || !Element.visible("overlay")) return;

		console.log("loading_set_progress : " + p + " (" + last_progress_point + ")");

		var o = $("l_progress_i");

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
		console.log("about to remove splash, OMG!");
		Element.hide("overlay");
		console.log("removed splash!");
	}
}

function getSelectedFeedsFromBrowser() {

	var list = $("browseFeedList");

	var selected = new Array();
	
	for (i = 0; i < list.childNodes.length; i++) {
		var child = list.childNodes[i];
		if (child.id && child.id.match("FBROW-")) {
			var id = child.id.replace("FBROW-", "");
			
			var cb = $("FBCHK-" + id);

			if (cb.checked) {
				selected.push(id);
			}
		}
	}

	return selected;
}

function updateFeedBrowser() {
	try {

		var query = Form.serialize("feed_browser");

		Element.show('feed_browser_spinner');

		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function(transport) { 
				notify('');

				Element.hide('feed_browser_spinner');

				var c = $("browseFeedList");
				var r = transport.responseXML.getElementsByTagName("content")[0];
				var nr = transport.responseXML.getElementsByTagName("num-results")[0];
				var mode = transport.responseXML.getElementsByTagName("mode")[0];

				if (c && r) {
					c.innerHTML = r.firstChild.nodeValue;
				}

				if (parseInt(mode.getAttribute("value")) == 2) {
					Element.show('feed_archive_remove');
				} else {
					Element.hide('feed_archive_remove');
				}
	
			} });

	} catch (e) {
		exception_error("updateFeedBrowser", e);
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

function hotkey_prefix_timeout() {
	try {

		var date = new Date();
		var ts = Math.round(date.getTime() / 1000);

		if (hotkey_prefix_pressed && ts - hotkey_prefix_pressed >= 5) {
			console.log("hotkey_prefix seems to be stuck, aborting");
			hotkey_prefix_pressed = false;
			hotkey_prefix = false;
			Element.hide('cmdline');
		}

		setTimeout("hotkey_prefix_timeout()", 1000);

	} catch  (e) {
		exception_error("hotkey_prefix_timeout", e);
	}
}

function hideAuxDlg() {
	try {
		Element.hide('auxDlg');
	} catch (e) {
		exception_error("hideAuxDlg", e);
	}
}

function displayNewContentPrompt(id) {
	try {

		var msg = "<a href='#' onclick='viewfeed("+id+")'>" +
			__("New articles available in this feed (click to show)") + "</a>";

		msg = msg.replace("%s", getFeedName(id));

		$('auxDlg').innerHTML = msg;

		new Effect.Appear('auxDlg', {duration : 0.5});

	} catch (e) {
		exception_error("displayNewContentPrompt", e);
	}
}

function feedBrowserSubscribe() {
	try {

		var selected = getSelectedFeedsFromBrowser();

		var mode = document.forms['feed_browser'].mode;

		mode = mode[mode.selectedIndex].value;

		if (selected.length > 0) {
			closeInfoBox();

			notify_progress("Loading, please wait...", true);

			var query = "?op=rpc&subop=massSubscribe&ids="+
				param_escape(selected.toString()) + "&mode=" + param_escape(mode);

			new Ajax.Request("backend.php", {
				parameters: query,
				onComplete: function(transport) { 

					var nf = transport.responseXML.getElementsByTagName('num-feeds')[0];
					var nf_value = nf.getAttribute("value");

					notify_info(__("Subscribed to %d feed(s).").replace("%d", nf_value));

					if (inPreferences()) {
						updateFeedList();
					} else {
						setTimeout('updateFeedList(false, false)', 50);
					}
				} });

		} else {
			alert(__("No feeds are selected."));
		}

	} catch (e) {
		exception_error("feedBrowserSubscribe", e);
	}
}

function feedArchiveRemove() {
	try {

		var selected = getSelectedFeedsFromBrowser();

		if (selected.length > 0) {

			var pr = __("Remove selected feeds from the archive? Feeds with stored articles will not be removed.");

			if (confirm(pr)) {
				Element.show('feed_browser_spinner');

				var query = "?op=rpc&subop=remarchived&ids=" + 
					param_escape(selected.toString());;

				new Ajax.Request("backend.php", {
					parameters: query,
					onComplete: function(transport) { 
						updateFeedBrowser();
					} }); 
			}

		} else {
			alert(__("No feeds are selected."));
		}

	} catch (e) {
		exception_error("feedArchiveRemove", e);
	}
}

function uploadIconHandler(rc) {
	try {
		switch (rc) {
			case 0:
				notify_info("Upload complete.");
				if (inPreferences()) {
					updateFeedList();
				} else {
					setTimeout('updateFeedList(false, false)', 50);
				}
				break;
			case 1:
				notify_error("Upload failed: icon is too big.");
				break;
			case 2:
				notify_error("Upload failed.");
				break;
		}

	} catch (e) {
		exception_error("uploadIconHandler", e);
	}
}

function removeFeedIcon(id) {

	try {

		if (confirm(__("Remove stored feed icon?"))) {
			var query = "backend.php?op=pref-feeds&subop=removeicon&feed_id=" + param_escape(id);

			console.log(query);

			notify_progress("Removing feed icon...", true);

			new Ajax.Request("backend.php", {
				parameters: query,
				onComplete: function(transport) { 
					notify_info("Feed icon removed.");
					if (inPreferences()) {
						updateFeedList();
					} else {
						setTimeout('updateFeedList(false, false)', 50);
					}
				} }); 
		}

		return false;
	} catch (e) {
		exception_error("uploadFeedIcon", e);
	}
}

function uploadFeedIcon() {

	try {

		var file = $("icon_file");

		if (file.value.length == 0) {
			alert(__("Please select an image file to upload."));
		} else {
			if (confirm(__("Upload new icon for this feed?"))) {
				notify_progress("Uploading, please wait...", true);
				return true;
			}
		}

		return false;

	} catch (e) {
		exception_error("uploadFeedIcon", e);
	}
}

function addLabel(select, callback) {

	try {

		var caption = prompt(__("Please enter label caption:"), "");

		if (caption != undefined) {
	
			if (caption == "") {
				alert(__("Can't create label: missing caption."));
				return false;
			}

			var query = "?op=pref-labels&subop=add&caption=" + 
				param_escape(caption);

			if (select)
				query += "&output=select";

			notify_progress("Loading, please wait...", true);

			if (inPreferences() && !select) active_tab = "labelConfig";

			new Ajax.Request("backend.php", {
				parameters: query,
				onComplete: function(transport) { 
					if (callback) {
						callback(transport);
					} else if (inPreferences()) {
						infobox_submit_callback2(transport);
					} else {
						updateFeedList();
					}
			} });

		}

	} catch (e) {
		exception_error("addLabel", e);
	}
}

function quickAddFeed() {
	displayDlg('quickAddFeed', '',
	   function () {$('feed_url').focus();});
}

function quickAddFilter() {
	displayDlg('quickAddFilter', '',
	   function () {document.forms['filter_add_form'].reg_exp.focus();});
}

function unsubscribeFeed(feed_id, title) {

	var msg = __("Unsubscribe from %s?").replace("%s", title);

	if (title == undefined || confirm(msg)) {
		notify_progress("Removing feed...");

		var query = "?op=pref-feeds&quiet=1&subop=remove&ids=" + feed_id;

		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function(transport) {

					closeInfoBox();

					if (inPreferences()) {
						updateFeedList();				
					} else {
						dlg_frefresh_callback(transport, feed_id);
					}

				} });
	}

	return false;
}


function backend_sanity_check_callback(transport) {

	try {

		if (sanity_check_done) {
			fatalError(11, "Sanity check request received twice. This can indicate "+
		      "presence of Firebug or some other disrupting extension. "+
				"Please disable it and try again.");
			return;
		}

		if (!transport.responseXML) {
			if (!store) {
				fatalError(3, "Sanity check: Received reply is not XML", 
					transport.responseText);
				return;
			} else {
				init_offline();
				return;
			}
		}

		if (getURLParam("offline")) {
			return init_offline();
		}

		var reply = transport.responseXML.getElementsByTagName("error")[0];

		if (!reply) {
			fatalError(3, "Sanity check: invalid RPC reply", transport.responseText);
			return;
		}

		var error_code = reply.getAttribute("error-code");
	
		if (error_code && error_code != 0) {
			return fatalError(error_code, reply.getAttribute("error-msg"));
		}

		console.log("sanity check ok");

		var params = transport.responseXML.getElementsByTagName("init-params")[0];

		if (params) {
			console.log('reading init-params...');

			params = JSON.parse(params.firstChild.nodeValue);

			if (params) {
				for (var i = 0; i < params.length; i++) {
	
					var k = params[i].param;
					var v = params[i].value;
	
					if (getURLParam('debug')) console.log(k + " => " + v);
					init_params[k] = v;					
	
					if (db) {
						db.execute("DELETE FROM init_params WHERE key = ?", [k]);
						db.execute("INSERT INTO init_params (key,value) VALUES (?, ?)",
							[k, v]);
					}
				}
			}
		}

		sanity_check_done = true;

		init_second_stage();

	} catch (e) {
		exception_error("backend_sanity_check_callback", e, transport);	
	} 
}

function has_local_storage() {
	try {
		return 'localStorage' in window && window['localStorage'] != null;
	} catch (e) {
		return false;
	}
}

function catSelectOnChange(elem) {
	try {
		var value = elem[elem.selectedIndex].value;
		var def = elem.getAttribute('default');

		if (value == "ADD_CAT") {

			if (def)
				dropboxSelect(elem, def);
			else
				elem.selectedIndex = 0;

			quickAddCat(elem);
		}

	} catch (e) {
		exception_error("catSelectOnChange", e);
	}
}

function quickAddCat(elem) {
	try {
		var cat = prompt(__("Please enter category title:"));

		if (cat) {

			var query = "?op=rpc&subop=quickAddCat&cat=" + param_escape(cat);

			notify_progress("Loading, please wait...", true);

			new Ajax.Request("backend.php", {
				parameters: query,
				onComplete: function (transport) {
					var response = transport.responseXML;
					var select = response.getElementsByTagName("select")[0];
					var options = select.getElementsByTagName("option");

					dropbox_replace_options(elem, options);

					notify('');

			} });

		}

	} catch (e) {
		exception_error("quickAddCat", e);
	}
}

function genUrlChangeKey(feed, is_cat) {

	try {
		var ok = confirm(__("Generate new syndication address for this feed?"));
	
		if (ok) {
	
			notify_progress("Trying to change address...", true);
	
			var query = "?op=rpc&subop=regenFeedKey&id=" + param_escape(feed) + 
				"&is_cat=" + param_escape(is_cat);

			new Ajax.Request("backend.php", {
				parameters: query,
				onComplete: function(transport) {
						var new_link = transport.responseXML.getElementsByTagName("link")[0];
	
						var e = $('gen_feed_url');
	
						if (new_link) {
							
							new_link = new_link.firstChild.nodeValue;

							e.innerHTML = e.innerHTML.replace(/\&amp;key=.*$/, 
								"&amp;key=" + new_link);

							e.href = e.href.replace(/\&amp;key=.*$/,
								"&amp;key=" + new_link);

							new Effect.Highlight(e);

							notify('');
	
						} else {
							notify_error("Could not change feed URL.");
						}
				} });
		}
	} catch (e) {
		exception_error("genUrlChangeKey", e);
	}
	return false;
}

function labelSelectOnChange(elem) {
	try {
		var value = elem[elem.selectedIndex].value;
		var def = elem.getAttribute('default');

		if (value == "ADD_LABEL") {

			if (def)
				dropboxSelect(elem, def);
			else
				elem.selectedIndex = 0;

			addLabel(elem, function(transport) {

					try {

						var response = transport.responseXML;
						var select = response.getElementsByTagName("select")[0];
						var options = select.getElementsByTagName("option");

						dropbox_replace_options(elem, options);

						notify('');
					} catch (e) {
						exception_error("addLabel", e);
					}
			});
		}

	} catch (e) {
		exception_error("labelSelectOnChange", e);
	}
}

function dropbox_replace_options(elem, options) {

	try {
		while (elem.hasChildNodes())
			elem.removeChild(elem.firstChild);

		var sel_idx = -1;

		for (var i = 0; i < options.length; i++) {
			var text = options[i].firstChild.nodeValue;
			var value = options[i].getAttribute("value");

			if (value == undefined) value = text;

			var issel = options[i].getAttribute("selected") == "1";

			var option = new Option(text, value, issel);

			if (options[i].getAttribute("disabled"))
				option.setAttribute("disabled", true);

			elem.insert(option);

			if (issel) sel_idx = i;
		}

		// Chrome doesn't seem to just select stuff when you pass new Option(x, y, true)
		if (sel_idx >= 0) elem.selectedIndex = sel_idx;

	} catch (e) {
		exception_error("dropbox_replace_options", e);
	}
}

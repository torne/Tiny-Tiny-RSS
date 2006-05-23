var hotkeys_enabled = true;

function browser_has_opacity() {
	return navigator.userAgent.match("Gecko") != null || 
		navigator.userAgent.match("Opera") != null;
}

function exception_error(location, e) {
	var msg;

	if (e.fileName) {
		var base_fname = e.fileName.substring(e.fileName.lastIndexOf("/") + 1);
	
		msg = "Exception: " + e.name + ", " + e.message + 
			"\nFunction: " + location + "()" +
			"\nLocation: " + base_fname + ":" + e.lineNumber;
	} else {
		msg = "Exception: " + e + "\nFunction: " + location + "()";
	}

	alert(msg);
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

function rpc_pnotify_callback() {
	var container = parent.document.getElementById('notify');
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

var seq = "";

function hotkey_handler(e) {

	var keycode;

	if (!hotkeys_enabled) return;

	if (window.event) {
		keycode = window.event.keyCode;
	} else if (e) {
		keycode = e.which;
	}

	if (keycode == 13 || keycode == 27) {
		seq = "";
	} else {
		seq = seq + "" + keycode;
	}

	if (document.getElementById("piggie")) {

		if (seq.match("807371717369")) {
			seq = "";
			localPiggieFunction(true);
		} else {
			localPiggieFunction(false);
		}
	}
	
	if (typeof localHotkeyHandler != 'undefined') {
		try {
			localHotkeyHandler(keycode);
		} catch (e) {
			exception_error("hotkey_handler", e);
		}
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
	return getCookie("ttrss_vf_actfeed");
}

function setActiveFeedId(id) {
	return setCookie("ttrss_vf_actfeed", id);
}

var xmlhttp_rpc = Ajax.getTransport();

function parse_counters(reply, f_document, title_obj, scheduled_call) {
	try {
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

			if (updated && feedlink) {
				if (error) {
					feedlink.title = "Error: " + error + " (" + updated + ")";
				} else {
					feedlink.title = "Updated: " + updated;
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
	} catch (e) {
		exception_error("parse_counters", e);
	}
}

// this one is called from feedlist context
// thus title_obj passed to parse_counters is parent (e.g. main ttrss window)

function all_counters_callback() {
	if (xmlhttp_rpc.readyState == 4) {
		try {
			if (!xmlhttp_rpc.responseXML || !xmlhttp_rpc.responseXML.firstChild) {
				notify("[all_counters_callback] backend did not return valid XML");
				return;
			}

			if (!parent.frames["feeds-frame"]) {
				notify("[all_counters_callback] no parent feeds-frame");
				return;
			}

			var reply = xmlhttp_rpc.responseXML.firstChild;
			var f_document = parent.frames["feeds-frame"].document;

			parse_counters(reply, f_document, parent);
	
		} catch (e) {
			exception_error("all_counters_callback", e);
		}
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

	if (!doc.styleSheets) return;

	var css_rules = doc.styleSheets[0].cssRules;

	if (!css_rules || !css_rules.length) return;

	for (i = 0; i < css_rules.length; i++) {
		var rule = css_rules[i];

		if (rule.selectorText == "ul.feedList li.feed") {
			if (!hide) {
				rule.style.display = "block";
			} else {
				rule.style.display = "none";
			}
		}

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
				
					if (getCookie("ttrss_vf_hreadf") == 1) {
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
		
		if (getCookie("ttrss_vf_hreadf") == 1) {
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

	var feeds_doc = window.frames["feeds-frame"].document;

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



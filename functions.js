var hotkeys_enabled = true;
var notify_silent = false;
var loading_progress = 0;
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

	try {

		if (ext_info) {
			if (ext_info.responseText) {
				ext_info = ext_info.responseText;
			}
		}

		var content = "<div class=\"fatalError\">" +
			"<pre>" + msg + "</pre>";

		if (ext_info) {
			content += "<div><b>Additional information:</b></div>" +
			"<textarea readonly=\"1\">" + ext_info + "</textarea>";
		}

		content += "<div><b>Stack trace:</b></div>" +
			"<textarea readonly=\"1\">" + e.stack + "</textarea>";

//		content += "<div style='text-align : center'>" +
//			"<button onclick=\"closeInfoBox()\">" +
//			"Close this window" + "</button></div>";

		content += "</div>";

		// TODO: add code to automatically report errors to tt-rss.org

		var dialog = new dijit.Dialog({
			title: "Unhandled exception",
			style: "width: 600px",
			content: content});

		dialog.show();

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


function toggleSelectRowById(sender, id) {
	var row = $(id);
	return toggleSelectRow(sender, row);
}

function toggleSelectListRow(sender) {
	var row = sender.parentNode;
	return toggleSelectRow(sender, row);
}

/* this is for dijit Checkbox */
function toggleSelectListRow2(sender) {
	var row = sender.domNode.parentNode;
	return toggleSelectRow(sender, row);
}

function tSR(sender, row) {
	return toggleSelectRow(sender, row);
}

/* this is for dijit Checkbox */
function toggleSelectRow2(sender, row) {

	if (!row) row = sender.domNode.parentNode.parentNode;

	if (sender.checked && !row.hasClassName('Selected'))
		row.addClassName('Selected');
	else
		row.removeClassName('Selected');
}


function toggleSelectRow(sender, row) {

	if (!row) row = sender.parentNode.parentNode;

	if (sender.checked && !row.hasClassName('Selected'))
		row.addClassName('Selected');
	else
		row.removeClassName('Selected');
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

		dialog = dijit.byId("infoBox");

		if (dialog)	dialog.hide();

	} catch (e) {
		//exception_error("closeInfoBox", e);
	}
	return false;
}


function displayDlg(id, param, callback) {

	notify_progress("Loading, please wait...", true);

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
		var dialog = false;

		if (dijit.byId("infoBox")) {
			dialog = dijit.byId("infoBox");
		}

		//console.log("infobox_callback2");
		notify('');

		var content;
		var dtitle = "Dialog";

		if (transport.responseXML) {
			var dlg = transport.responseXML.getElementsByTagName("dlg")[0];

			var title = transport.responseXML.getElementsByTagName("title")[0];
			if (title)
				title = title.firstChild.nodeValue;

			var content = transport.responseXML.getElementsByTagName("content")[0];
			
			content = content.firstChild.nodeValue;

		} else {
			content = transport.responseText;
		}

		if (!dialog) {
			dialog = new dijit.Dialog({
				title: title,
				id: 'infoBox',
				style: "width: 600px",
				onCancel: function() {
					return true;
				},
				onExecute: function() {
					return true;
				},
				onClose: function() {
					return true;
					},
				content: content});
		} else {
			dialog.attr('title', title);
			dialog.attr('content', content);
		}

		dialog.show();

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

		var query = "?op=rpc&subop=verifyRegexp&reg_exp=" + param_escape(reg_exp);

		notify_progress("Verifying regular expression...");

		new Ajax.Request("backend.php",	{
				parameters: query,
				onComplete: function(transport) {
					handle_rpc_reply(transport);

					var response = transport.responseXML;

					if (response) {
						var s = response.getElementsByTagName("status")[0].firstChild.nodeValue;
	
						notify('');

						if (s == "INVALID") {
							alert("Match regular expression seems to be invalid.");
							return;
						} else {

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
						}
					}

			} });

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
			try {

				if (!transport.responseXML) {
					console.log(transport.responseText);
					alert(__("Server error while trying to subscribe to specified feed."));
					return;
				}

				var result = transport.responseXML.getElementsByTagName('result')[0];
				var rc = parseInt(result.getAttribute('code'));
	
				Form.enable("feed_add_form");
	
				notify('');
	
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
					alert(__("Specified URL seems to be invalid."));
					break;
				case 3:
					alert(__("Specified URL doesn't seem to contain any feeds."));
					break;
				case 4:
					new Ajax.Request("backend.php", {
						parameters: 'op=rpc&subop=extractfeedurls&url=' + encodeURIComponent(feed_url),
						onComplete: function(transport) {
							var result = transport.responseXML.getElementsByTagName('urls')[0];
							var feeds = JSON.parse(result.firstChild.nodeValue);
							var select = document.getElementById("faad_feeds_container_select");
	
							while (select.hasChildNodes()) {
								select.removeChild(elem.firstChild);
							}
							var count = 0;
							for (var feedUrl in feeds) {
								select.insert(new Option(feeds[feedUrl], feedUrl, false));
								count++;
							}
							if (count > 5) count = 5;
							select.size = count;
	
							Effect.Appear('fadd_feeds_container', {duration : 0.5});
						}
					});
					break;
				case 5:
					alert(__("Couldn't download the specified URL."));
					break;
				case 0:
					alert(__("You are already subscribed to this feed."));
					break;
				}

			} catch (e) {
				exception_error("subscribeToFeed", e);
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

function filterDlgCheckType(sender) {

	try {

		var ftype = sender.value;

		// if selected filter type is 5 (Date) enable the modifier dropbox
		if (ftype == 5) {
			Element.show("filterDlg_dateModBox");
			Element.show("filterDlg_dateChkBox");
		} else {
			Element.hide("filterDlg_dateModBox");
			Element.hide("filterDlg_dateChkBox");

		}

	} catch (e) {
		exception_error("filterDlgCheckType", e);
	}

}

function filterDlgCheckAction(sender) {

	try {

		var action = sender.value;

		var action_param = $("filterDlg_paramBox");

		if (!action_param) {
			console.log("filterDlgCheckAction: can't find action param box!");
			return;
		}

		// if selected action supports parameters, enable params field
		if (action == 4 || action == 6 || action == 7) {
			new Effect.Appear(action_param, {duration : 0.5});
			if (action != 7) {
				Element.show(dijit.byId("filterDlg_actionParam").domNode);
				Element.hide(dijit.byId("filterDlg_actionParamLabel").domNode);
			} else {
				Element.show(dijit.byId("filterDlg_actionParamLabel").domNode);
				Element.hide(dijit.byId("filterDlg_actionParam").domNode);
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
		var dialog = dijit.byId("filterEditDlg");

		var reg_exp = dialog.attr('value').reg_exp;

		var query = "?op=rpc&subop=checkDate&date=" + reg_exp;

		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function(transport) { 

				if (transport.responseXML) {
					var result = transport.responseXML.getElementsByTagName("result")[0];

					if (result && result.firstChild) {
						if (result.firstChild.nodeValue == "1") {
							alert(__("Date syntax appears to be correct."));
							return;
						}
					}
				}

				alert(__("Date syntax is incorrect."));

			} });


	} catch (e) {
		exception_error("filterDlgCheckDate", e);
	}
}

function explainError(code) {
	return displayDlg("explainError", code);
}

function displayHelpInfobox(topic_id) {

	var url = "backend.php?op=help&tid=" + param_escape(topic_id);

	var w = window.open(url, "ttrss_help", 
		"status=0,toolbar=0,location=0,width=450,height=500,scrollbars=1,menubar=0");

}

function loading_set_progress(p) {
	try {
		loading_progress += p;

		if (dijit.byId("loading_bar"))
			dijit.byId("loading_bar").update({progress: loading_progress});

		if (loading_progress >= 90)
			remove_splash();

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

	var list = $$("#browseFeedList li[id*=FBROW]");

	var selected = new Array();

	list.each(function(child) {	
		var id = child.id.replace("FBROW-", "");
		var cb = $("FBCHK-" + id);

		if (cb.checked) {
			selected.push(id);
		}	
	});

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
	try {
		var query = "backend.php?op=dlg&id=quickAddFilter";

		if (dijit.byId("filterEditDlg"))
			dijit.byId("filterEditDlg").destroyRecursive();

		dialog = new dijit.Dialog({
			id: "filterEditDlg",
			title: __("Create Filter"),
			style: "width: 600px",
			execute: function() {
				if (this.validate()) {
					console.log(dojo.objectToQuery(this.attr('value')));
					new Ajax.Request("backend.php", {
						parameters: dojo.objectToQuery(this.attr('value')),
						onComplete: function(transport) {
							this.hide();
							notify_info(transport.responseText);
							if (inPreferences()) {
								updateFilterList();				
							}
					}});

				}
			},
			href: query});

		dialog.show();
	} catch (e) {
		exception_error("quickAddFilter", e);
	}
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
			}
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
				for (k in params) {
					var v = params[k];
					console.log("IP: " + k + " => " + v);
				}
			}

			init_params = params;
		}

		sanity_check_done = true;

		init_second_stage();

	} catch (e) {
		exception_error("backend_sanity_check_callback", e, transport);	
	} 
}

function has_local_storage() {
	return false;
/*	try {
		return 'localStorage' in window && window['localStorage'] != null;
	} catch (e) {
		return false;
	} */
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
/*		var value = elem[elem.selectedIndex].value;
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
		} */

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

// mode = all, none, invert
function selectTableRows(id, mode) {
	try {
		var rows = $(id).rows;

		for (var i = 0; i < rows.length; i++) {
			var row = rows[i];
			var cb = false;

			if (row.id && row.className) {
				var bare_id = row.id.replace(/^[A-Z]*?-/, "");
				var inputs = rows[i].getElementsByTagName("input");

				for (var j = 0; j < inputs.length; j++) {
					var input = inputs[j];

					if (input.getAttribute("type") == "checkbox" && 
							input.id.match(bare_id)) {

						cb = input;
						break;
					}
				}

				if (cb) {
					var issel = row.hasClassName("Selected");

					if (mode == "all" && !issel) {
						row.addClassName("Selected");
						cb.checked = true;
					} else if (mode == "none" && issel) {
						row.removeClassName("Selected");
						cb.checked = false;
					} else if (mode == "invert") {

						if (issel) {
							row.removeClassName("Selected");
							cb.checked = false;
						} else {
							row.addClassName("Selected");
							cb.checked = true;
						}
					}
				}
			}
		}

	} catch (e) {
		exception_error("selectTableRows", e);

	}
}

function getSelectedTableRowIds(id) {
	var rows = [];

	try {
		var elem_rows = $(id).rows;

		for (i = 0; i < elem_rows.length; i++) {
			if (elem_rows[i].hasClassName("Selected")) {
				var bare_id = elem_rows[i].id.replace(/^[A-Z]*?-/, "");
				rows.push(bare_id);
			}
		}

	} catch (e) {
		exception_error("getSelectedTableRowIds", e);
	}

	return rows;
}


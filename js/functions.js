var loading_progress = 0;
var sanity_check_done = false;
var init_params = {};
var _label_base_index = -1024;
var notify_hide_timerid = false;

Ajax.Base.prototype.initialize = Ajax.Base.prototype.initialize.wrap(
	function (callOriginal, options) {

		if (getInitParam("csrf_token") != undefined) {
			Object.extend(options, options || { });

			if (Object.isString(options.parameters))
				options.parameters = options.parameters.toQueryParams();
			else if (Object.isHash(options.parameters))
				options.parameters = options.parameters.toObject();

			options.parameters["csrf_token"] = getInitParam("csrf_token");
		}

		return callOriginal(options);
	}
);

/* add method to remove element from array */

Array.prototype.remove = function(s) {
	for (var i=0; i < this.length; i++) {
		if (s == this[i]) this.splice(i, 1);
	}
};

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

		try {
			new Ajax.Request("backend.php", {
				parameters: {op: "rpc", method: "log", logmsg: msg},
				onComplete: function (transport) {
					console.log(transport.responseText);
				} });

		} catch (eii) {
			console.log("Exception while trying to log the error.");
			console.log(eii);
		}

		msg += "<p>"+ __("The error will be reported to the configured log destination.") +
			"</p>";

		var content = "<div class=\"fatalError\">" +
			"<pre>" + msg + "</pre>";

		content += "<form name=\"exceptionForm\" id=\"exceptionForm\" target=\"_blank\" "+
		  "action=\"http://tt-rss.org/report.php\" method=\"POST\">";

		content += "<textarea style=\"display : none\" name=\"message\">" + msg + "</textarea>";
		content += "<textarea style=\"display : none\" name=\"params\">N/A</textarea>";

		if (ext_info) {
			content += "<div><b>Additional information:</b></div>" +
			"<textarea name=\"xinfo\" readonly=\"1\">" + ext_info + "</textarea>";
		}

		content += "<div><b>Stack trace:</b></div>" +
			"<textarea name=\"stack\" readonly=\"1\">" + e.stack + "</textarea>";

		content += "</form>";

		content += "</div>";

		content += "<div class='dlgButtons'>";

		content += "<button dojoType=\"dijit.form.Button\""+
				"onclick=\"dijit.byId('exceptionDlg').report()\">" +
				__('Report to tt-rss.org') + "</button> ";
		content += "<button dojoType=\"dijit.form.Button\" "+
				"onclick=\"dijit.byId('exceptionDlg').hide()\">" +
				__('Close') + "</button>";
		content += "</div>";

		if (dijit.byId("exceptionDlg"))
			dijit.byId("exceptionDlg").destroyRecursive();

		var dialog = new dijit.Dialog({
			id: "exceptionDlg",
			title: "Unhandled exception",
			style: "width: 600px",
			report: function() {
				if (confirm(__("Are you sure to report this exception to tt-rss.org? The report will include information about your web browser and tt-rss configuration. Your IP will be saved in the database."))) {

					document.forms['exceptionForm'].params.value = $H({
						browserName: navigator.appName,
						browserVersion: navigator.appVersion,
						browserPlatform: navigator.platform,
						browserCookies: navigator.cookieEnabled,
						ttrssVersion: __ttrss_version,
						initParams: JSON.stringify(init_params),
					}).toQueryString();

					document.forms['exceptionForm'].submit();

				}
			},
			content: content});

		dialog.show();

	} catch (ei) {
		console.log("Exception while trying to report an exception. Oh boy.");
		console.log(ei);
		console.log("Original exception:");
		console.log(e);

		msg += "\n\nAdditional exception caught while trying to show the error dialog.\n\n" +  format_exception_error('exception_error', ei);

		try {
			new Ajax.Request("backend.php", {
				parameters: {op: "rpc", method: "log", logmsg: msg},
				onComplete: function (transport) {
					console.log(transport.responseText);
				} });

		} catch (eii) {
			console.log("Third exception while trying to log the error! Seriously?");
			console.log(eii);
		}

		msg += "\n\nThe error will be reported to the configured log destination.";

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


function hide_notify() {
	Element.hide('notify');
}

function notify_real(msg, no_hide, n_type) {

	var n = $("notify");

	if (!n) return;

	if (notify_hide_timerid) {
		window.clearTimeout(notify_hide_timerid);
	}

	if (msg == "") {
		if (Element.visible(n)) {
			notify_hide_timerid = window.setTimeout("hide_notify()", 0);
		}
		return;
	} else {
		Element.show(n);
	}

	/* types:

		1 - generic
		2 - progress
		3 - error
		4 - info

	*/

	msg = "<span class=\"msg\"> " + __(msg) + "</span>";

	if (n_type == 1) {
		n.className = "notify";
	} else if (n_type == 2) {
		n.className = "notify progress";
		msg = "<span><img src='images/indicator_white.gif'></span>" + msg;
		no_hide = true;
	} else if (n_type == 3) {
		n.className = "notify error";
		msg = "<span><img src='images/alert.png'></span>" + msg;
	} else if (n_type == 4) {
		n.className = "notify info";
		msg = "<span><img src='images/information.png'></span>" + msg;
	}

	msg += " <span><img src=\"images/cross.png\" class=\"close\" title=\"" +
		__("Click to close") + "\" onclick=\"notify('')\"></span>";

//	msg = "<img src='images/live_com_loading.gif'> " + msg;

	n.innerHTML = msg;

	if (!no_hide) {
		notify_hide_timerid = window.setTimeout("hide_notify()", 5*1000);
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

function gotoLogout() {
	document.location.href = "backend.php?op=logout";
}

function gotoMain() {
	document.location.href = "index.php";
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
      for(var j=0;j<x.length;j++) {
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

/* this is for dijit Checkbox */
function toggleSelectRow2(sender, row, is_cdm) {

	if (!row)
		if (!is_cdm)
			row = sender.domNode.parentNode.parentNode;
		else
			row = sender.domNode.parentNode.parentNode.parentNode; // oh ffs

	if (sender.checked && !row.hasClassName('Selected'))
		row.addClassName('Selected');
	else
		row.removeClassName('Selected');

	if (typeof updateSelectedPrompt != undefined)
		updateSelectedPrompt();
}


function toggleSelectRow(sender, row) {

	if (!row) row = sender.parentNode.parentNode;

	if (sender.checked && !row.hasClassName('Selected'))
		row.addClassName('Selected');
	else
		row.removeClassName('Selected');

	if (typeof updateSelectedPrompt != undefined)
		updateSelectedPrompt();
}

function checkboxToggleElement(elem, id) {
	if (elem.checked) {
		Effect.Appear(id, {duration : 0.5});
	} else {
		Effect.Fade(id, {duration : 0.5});
	}
}

function dropboxSelect(e, v) {
	for (var i = 0; i < e.length; i++) {
		if (e[i].value == v) {
			e.selectedIndex = i;
			break;
		}
	}
}

function getURLParam(param){
	return String(window.location.href).parseQuery()[param];
}

function closeInfoBox(cleanup) {
	try {
		dialog = dijit.byId("infoBox");

		if (dialog)	dialog.hide();

	} catch (e) {
		//exception_error("closeInfoBox", e);
	}
	return false;
}


function displayDlg(title, id, param, callback) {

	notify_progress("Loading, please wait...", true);

	var query = "?op=dlg&method=" +
		param_escape(id) + "&param=" + param_escape(param);

	new Ajax.Request("backend.php", {
		parameters: query,
		onComplete: function (transport) {
			infobox_callback2(transport, title);
			if (callback) callback(transport);
		} });

	return false;
}

function infobox_callback2(transport, title) {
	try {
		var dialog = false;

		if (dijit.byId("infoBox")) {
			dialog = dijit.byId("infoBox");
		}

		//console.log("infobox_callback2");
		notify('');

		var content = transport.responseText;

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

		if (code == 6) {
			window.location.href = "index.php";
		} else if (code == 5) {
			window.location.href = "public.php?op=dbupdate";
		} else {

			if (msg == "") msg = "Unknown error";

			if (ext_info) {
				if (ext_info.responseText) {
					ext_info = ext_info.responseText;
				}
			}

			if (ERRORS && ERRORS[code] && !msg) {
				msg = ERRORS[code];
			}

			var content = "<div><b>Error code:</b> " + code + "</div>" +
				"<p>" + msg + "</p>";

			if (ext_info) {
				content = content + "<div><b>Additional information:</b></div>" +
					"<textarea style='width: 100%' readonly=\"1\">" +
					ext_info + "</textarea>";
			}

			var dialog = new dijit.Dialog({
				title: "Fatal error",
				style: "width: 600px",
				content: content});

			dialog.show();

		}

		return false;

	} catch (e) {
		exception_error("fatalError", e);
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


function explainError(code) {
	return displayDlg(__("Error explained"), "explainError", code);
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
			var query = "backend.php?op=pref-feeds&method=removeicon&feed_id=" + param_escape(id);

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
		exception_error("removeFeedIcon", e);
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

			var query = "?op=pref-labels&method=add&caption=" +
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
						updateLabelList();
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
	try {
		var query = "backend.php?op=feeds&method=quickAddFeed";

		// overlapping widgets
		if (dijit.byId("batchSubDlg")) dijit.byId("batchSubDlg").destroyRecursive();
		if (dijit.byId("feedAddDlg"))	dijit.byId("feedAddDlg").destroyRecursive();

		var dialog = new dijit.Dialog({
			id: "feedAddDlg",
			title: __("Subscribe to Feed"),
			style: "width: 600px",
			execute: function() {
				if (this.validate()) {
					console.log(dojo.objectToQuery(this.attr('value')));

					var feed_url = this.attr('value').feed;

					Element.show("feed_add_spinner");

					new Ajax.Request("backend.php", {
						parameters: dojo.objectToQuery(this.attr('value')),
						onComplete: function(transport) {
							try {

								try {
									var reply = JSON.parse(transport.responseText);
								} catch (e) {
									Element.hide("feed_add_spinner");
									alert(__("Failed to parse output. This can indicate server timeout and/or network issues. Backend output was logged to browser console."));
									console.log('quickAddFeed, backend returned:' + transport.responseText);
									return;
								}

								var rc = reply['result'];

								notify('');
								Element.hide("feed_add_spinner");

								console.log(rc);

								switch (parseInt(rc['code'])) {
								case 1:
									dialog.hide();
									notify_info(__("Subscribed to %s").replace("%s", feed_url));

									updateFeedList();
									break;
								case 2:
									alert(__("Specified URL seems to be invalid."));
									break;
								case 3:
									alert(__("Specified URL doesn't seem to contain any feeds."));
									break;
								case 4:
									feeds = rc['feeds'];

									Element.show("fadd_multiple_notify");

									var select = dijit.byId("feedDlg_feedContainerSelect");

									while (select.getOptions().length > 0)
										select.removeOption(0);

									select.addOption({value: '', label: __("Expand to select feed")});

									var count = 0;
									for (var feedUrl in feeds) {
										select.addOption({value: feedUrl, label: feeds[feedUrl]});
										count++;
									}

									Effect.Appear('feedDlg_feedsContainer', {duration : 0.5});

									break;
								case 5:
									alert(__("Couldn't download the specified URL: %s").
											replace("%s", rc['message']));
									break;
								case 6:
									alert(__("XML validation failed: %s").
											replace("%s", rc['message']));
									break;
									break;
								case 0:
									alert(__("You are already subscribed to this feed."));
									break;
								}

							} catch (e) {
								exception_error("subscribeToFeed", e, transport);
							}

						} });

					}
			},
			href: query});

		dialog.show();
	} catch (e) {
		exception_error("quickAddFeed", e);
	}
}

function createNewRuleElement(parentNode, replaceNode) {
	try {
		var form = document.forms["filter_new_rule_form"];

		form.reg_exp.value = form.reg_exp.value.replace(/(<([^>]+)>)/ig,"");

		var query = "backend.php?op=pref-filters&method=printrulename&rule="+
			param_escape(dojo.formToJson(form));

		console.log(query);

		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function (transport) {
				try {
					var li = dojo.create("li");

					var cb = dojo.create("input", { type: "checkbox" }, li);

					new dijit.form.CheckBox({
						onChange: function() {
							toggleSelectListRow2(this) },
					}, cb);

					dojo.create("input", { type: "hidden",
						name: "rule[]",
						value: dojo.formToJson(form) }, li);

					dojo.create("span", {
						onclick: function() {
							dijit.byId('filterEditDlg').editRule(this);
						},
						innerHTML: transport.responseText }, li);

					if (replaceNode) {
						parentNode.replaceChild(li, replaceNode);
					} else {
						parentNode.appendChild(li);
					}
				} catch (e) {
					exception_error("createNewRuleElement", e);
				}
		} });
	} catch (e) {
		exception_error("createNewRuleElement", e);
	}
}

function createNewActionElement(parentNode, replaceNode) {
	try {
		var form = document.forms["filter_new_action_form"];

		if (form.action_id.value == 7) {
			form.action_param.value = form.action_param_label.value;
		}

		var query = "backend.php?op=pref-filters&method=printactionname&action="+
			param_escape(dojo.formToJson(form));

		console.log(query);

		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function (transport) {
				try {
					var li = dojo.create("li");

					var cb = dojo.create("input", { type: "checkbox" }, li);

					new dijit.form.CheckBox({
						onChange: function() {
							toggleSelectListRow2(this) },
					}, cb);

					dojo.create("input", { type: "hidden",
						name: "action[]",
						value: dojo.formToJson(form) }, li);

					dojo.create("span", {
						onclick: function() {
							dijit.byId('filterEditDlg').editAction(this);
						},
						innerHTML: transport.responseText }, li);

					if (replaceNode) {
						parentNode.replaceChild(li, replaceNode);
					} else {
						parentNode.appendChild(li);
					}

				} catch (e) {
					exception_error("createNewActionElement", e);
				}
			} });
	} catch (e) {
		exception_error("createNewActionElement", e);
	}
}


function addFilterRule(replaceNode, ruleStr) {
	try {
		if (dijit.byId("filterNewRuleDlg"))
			dijit.byId("filterNewRuleDlg").destroyRecursive();

		var query = "backend.php?op=pref-filters&method=newrule&rule=" +
			param_escape(ruleStr);

		var rule_dlg = new dijit.Dialog({
			id: "filterNewRuleDlg",
			title: ruleStr ? __("Edit rule") : __("Add rule"),
			style: "width: 600px",
			execute: function() {
				if (this.validate()) {
					createNewRuleElement($("filterDlg_Matches"), replaceNode);
					this.hide();
				}
			},
			href: query});

		rule_dlg.show();
	} catch (e) {
		exception_error("addFilterRule", e);
	}
}

function addFilterAction(replaceNode, actionStr) {
	try {
		if (dijit.byId("filterNewActionDlg"))
			dijit.byId("filterNewActionDlg").destroyRecursive();

		var query = "backend.php?op=pref-filters&method=newaction&action=" +
			param_escape(actionStr);

		var rule_dlg = new dijit.Dialog({
			id: "filterNewActionDlg",
			title: actionStr ? __("Edit action") : __("Add action"),
			style: "width: 600px",
			execute: function() {
				if (this.validate()) {
					createNewActionElement($("filterDlg_Actions"), replaceNode);
					this.hide();
				}
			},
			href: query});

		rule_dlg.show();
	} catch (e) {
		exception_error("addFilterAction", e);
	}
}

function quickAddFilter() {
	try {
		var query = "";
		if (!inPreferences()) {
			query = "backend.php?op=pref-filters&method=newfilter&feed=" +
				param_escape(getActiveFeedId()) + "&is_cat=" +
				param_escape(activeFeedIsCat());
		} else {
			query = "backend.php?op=pref-filters&method=newfilter";
		}

		console.log(query);

		if (dijit.byId("feedEditDlg"))
			dijit.byId("feedEditDlg").destroyRecursive();

		if (dijit.byId("filterEditDlg"))
			dijit.byId("filterEditDlg").destroyRecursive();

		dialog = new dijit.Dialog({
			id: "filterEditDlg",
			title: __("Create Filter"),
			style: "width: 600px",
			test: function() {
				var query = "backend.php?" + dojo.formToQuery("filter_new_form") + "&savemode=test";

				if (dijit.byId("filterTestDlg"))
					dijit.byId("filterTestDlg").destroyRecursive();

				var test_dlg = new dijit.Dialog({
					id: "filterTestDlg",
					title: "Test Filter",
					style: "width: 600px",
					href: query});

				test_dlg.show();
			},
			selectRules: function(select) {
				$$("#filterDlg_Matches input[type=checkbox]").each(function(e) {
					e.checked = select;
					if (select)
						e.parentNode.addClassName("Selected");
					else
						e.parentNode.removeClassName("Selected");
				});
			},
			selectActions: function(select) {
				$$("#filterDlg_Actions input[type=checkbox]").each(function(e) {
					e.checked = select;

					if (select)
						e.parentNode.addClassName("Selected");
					else
						e.parentNode.removeClassName("Selected");

				});
			},
			editRule: function(e) {
				var li = e.parentNode;
				var rule = li.getElementsByTagName("INPUT")[1].value;
				addFilterRule(li, rule);
			},
			editAction: function(e) {
				var li = e.parentNode;
				var action = li.getElementsByTagName("INPUT")[1].value;
				addFilterAction(li, action);
			},
			addAction: function() { addFilterAction(); },
			addRule: function() { addFilterRule(); },
			deleteAction: function() {
				$$("#filterDlg_Actions li.[class*=Selected]").each(function(e) { e.parentNode.removeChild(e) });
			},
			deleteRule: function() {
				$$("#filterDlg_Matches li.[class*=Selected]").each(function(e) { e.parentNode.removeChild(e) });
			},
			execute: function() {
				if (this.validate()) {

					var query = dojo.formToQuery("filter_new_form");

					console.log(query);

					new Ajax.Request("backend.php", {
						parameters: query,
						onComplete: function (transport) {
							if (inPreferences()) {
								updateFilterList();
							}

							dialog.hide();
					} });
				}
			},
			href: query});

		if (!inPreferences()) {
			var selectedText = getSelectionText();

			var lh = dojo.connect(dialog, "onLoad", function(){
				dojo.disconnect(lh);

				if (selectedText != "") {

					var feed_id = activeFeedIsCat() ? 'CAT:' + parseInt(getActiveFeedId()) :
						getActiveFeedId();

					var rule = { reg_exp: selectedText, feed_id: feed_id, filter_type: 1 };

					addFilterRule(null, dojo.toJson(rule));

				} else {

					var query = "op=rpc&method=getlinktitlebyid&id=" + getActiveArticleId();

					new Ajax.Request("backend.php", {
					parameters: query,
					onComplete: function(transport) {
						var reply = JSON.parse(transport.responseText);

						var title = false;

						if (reply && reply) title = reply.title;

						if (title || getActiveFeedId() || activeFeedIsCat()) {

							console.log(title + " " + getActiveFeedId());

							var feed_id = activeFeedIsCat() ? 'CAT:' + parseInt(getActiveFeedId()) :
								getActiveFeedId();

							var rule = { reg_exp: title, feed_id: feed_id, filter_type: 1 };

							addFilterRule(null, dojo.toJson(rule));
						}

					} });

				}

			});
		}

		dialog.show();

	} catch (e) {
		exception_error("quickAddFilter", e);
	}
}

function resetPubSub(feed_id, title) {

	var msg = __("Reset subscription? Tiny Tiny RSS will try to subscribe to the notification hub again on next feed update.").replace("%s", title);

	if (title == undefined || confirm(msg)) {
		notify_progress("Loading, please wait...");

		var query = "?op=pref-feeds&quiet=1&method=resetPubSub&ids=" + feed_id;

		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function(transport) {
				dijit.byId("pubsubReset_Btn").attr('disabled', true);
				notify_info("Subscription reset.");
			} });
	}

	return false;
}


function unsubscribeFeed(feed_id, title) {

	var msg = __("Unsubscribe from %s?").replace("%s", title);

	if (title == undefined || confirm(msg)) {
		notify_progress("Removing feed...");

		var query = "?op=pref-feeds&quiet=1&method=remove&ids=" + feed_id;

		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function(transport) {

					if (dijit.byId("feedEditDlg")) dijit.byId("feedEditDlg").hide();

					if (inPreferences()) {
						updateFeedList();
					} else {
						if (feed_id == getActiveFeedId())
							setTimeout("viewfeed(-5)", 100);

						if (feed_id < 0) updateFeedList();
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

		var reply = JSON.parse(transport.responseText);

		if (!reply) {
			fatalError(3, "Sanity check: invalid RPC reply", transport.responseText);
			return;
		}

		var error_code = reply['error']['code'];

		if (error_code && error_code != 0) {
			return fatalError(error_code, reply['error']['message']);
		}

		console.log("sanity check ok");

		var params = reply['init-params'];

		if (params) {
			console.log('reading init-params...');

			for (k in params) {
				var v = params[k];
				console.log("IP: " + k + " => " + v);

				if (k == "label_base_index") _label_base_index = parseInt(v);
			}

			init_params = params;

			// PluginHost might not be available on non-index pages
			window.PluginHost && PluginHost.run(PluginHost.HOOK_PARAMS_LOADED, init_params);
		}

		sanity_check_done = true;

		init_second_stage();

	} catch (e) {
		exception_error("backend_sanity_check_callback", e, transport);
	}
}

/*function has_local_storage() {
	try {
		return 'sessionStorage' in window && window['sessionStorage'] != null;
	} catch (e) {
		return false;
	}
} */

function catSelectOnChange(elem) {
	try {
/*		var value = elem[elem.selectedIndex].value;
		var def = elem.getAttribute('default');

		if (value == "ADD_CAT") {

			if (def)
				dropboxSelect(elem, def);
			else
				elem.selectedIndex = 0;

			quickAddCat(elem);
		} */

	} catch (e) {
		exception_error("catSelectOnChange", e);
	}
}

function quickAddCat(elem) {
	try {
		var cat = prompt(__("Please enter category title:"));

		if (cat) {

			var query = "?op=rpc&method=quickAddCat&cat=" + param_escape(cat);

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

			var query = "?op=pref-feeds&method=regenFeedKey&id=" + param_escape(feed) +
				"&is_cat=" + param_escape(is_cat);

			new Ajax.Request("backend.php", {
				parameters: query,
				onComplete: function(transport) {
						var reply = JSON.parse(transport.responseText);
						var new_link = reply.link;

						var e = $('gen_feed_url');

						if (new_link) {

							e.innerHTML = e.innerHTML.replace(/\&amp;key=.*$/,
								"&amp;key=" + new_link);

							e.href = e.href.replace(/\&key=.*$/,
								"&key=" + new_link);

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
			var dcb = false;

			if (row.id && row.className) {
				var bare_id = row.id.replace(/^[A-Z]*?-/, "");
				var inputs = rows[i].getElementsByTagName("input");

				for (var j = 0; j < inputs.length; j++) {
					var input = inputs[j];

					if (input.getAttribute("type") == "checkbox" &&
							input.id.match(bare_id)) {

						cb = input;
						dcb = dijit.getEnclosingWidget(cb);
						break;
					}
				}

				if (cb || dcb) {
					var issel = row.hasClassName("Selected");

					if (mode == "all" && !issel) {
						row.addClassName("Selected");
						cb.checked = true;
						if (dcb) dcb.set("checked", true);
					} else if (mode == "none" && issel) {
						row.removeClassName("Selected");
						cb.checked = false;
						if (dcb) dcb.set("checked", false);

					} else if (mode == "invert") {

						if (issel) {
							row.removeClassName("Selected");
							cb.checked = false;
							if (dcb) dcb.set("checked", false);
						} else {
							row.addClassName("Selected");
							cb.checked = true;
							if (dcb) dcb.set("checked", true);
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

		for (var i = 0; i < elem_rows.length; i++) {
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

function editFeed(feed, event) {
	try {
		if (feed <= 0)
			return alert(__("You can't edit this kind of feed."));

		var query = "backend.php?op=pref-feeds&method=editfeed&id=" +
			param_escape(feed);

		console.log(query);

		if (dijit.byId("filterEditDlg"))
			dijit.byId("filterEditDlg").destroyRecursive();

		if (dijit.byId("feedEditDlg"))
			dijit.byId("feedEditDlg").destroyRecursive();

		dialog = new dijit.Dialog({
			id: "feedEditDlg",
			title: __("Edit Feed"),
			style: "width: 600px",
			execute: function() {
				if (this.validate()) {
//					console.log(dojo.objectToQuery(this.attr('value')));

					notify_progress("Saving data...", true);

					new Ajax.Request("backend.php", {
						parameters: dojo.objectToQuery(dialog.attr('value')),
						onComplete: function(transport) {
							dialog.hide();
							notify('');
							updateFeedList();
					}});
				}
			},
			href: query});

		dialog.show();

	} catch (e) {
		exception_error("editFeed", e);
	}
}

function feedBrowser() {
	try {
		var query = "backend.php?op=feeds&method=feedBrowser";

		if (dijit.byId("feedAddDlg"))
			dijit.byId("feedAddDlg").hide();

		if (dijit.byId("feedBrowserDlg"))
			dijit.byId("feedBrowserDlg").destroyRecursive();

		var dialog = new dijit.Dialog({
			id: "feedBrowserDlg",
			title: __("More Feeds"),
			style: "width: 600px",
			getSelectedFeedIds: function() {
				var list = $$("#browseFeedList li[id*=FBROW]");
				var selected = new Array();

				list.each(function(child) {
					var id = child.id.replace("FBROW-", "");

					if (child.hasClassName('Selected')) {
						selected.push(id);
					}
				});

				return selected;
			},
			getSelectedFeeds: function() {
				var list = $$("#browseFeedList li.Selected");
				var selected = new Array();

				list.each(function(child) {
					var title = child.getElementsBySelector("span.fb_feedTitle")[0].innerHTML;
					var url = child.getElementsBySelector("a.fb_feedUrl")[0].href;

					selected.push([title,url]);

				});

				return selected;
			},

			subscribe: function() {
				var mode = this.attr('value').mode;
				var selected = [];

				if (mode == "1")
					selected = this.getSelectedFeeds();
				else
					selected = this.getSelectedFeedIds();

				if (selected.length > 0) {
					dijit.byId("feedBrowserDlg").hide();

					notify_progress("Loading, please wait...", true);

					// we use dojo.toJson instead of JSON.stringify because
					// it somehow escapes everything TWICE, at least in Chrome 9

					var query = "?op=rpc&method=massSubscribe&payload="+
						param_escape(dojo.toJson(selected)) + "&mode=" + param_escape(mode);

					console.log(query);

					new Ajax.Request("backend.php", {
						parameters: query,
						onComplete: function(transport) {
							notify('');
							updateFeedList();
						} });

				} else {
					alert(__("No feeds are selected."));
				}

			},
			update: function() {
				var query = dojo.objectToQuery(dialog.attr('value'));

				Element.show('feed_browser_spinner');

				new Ajax.Request("backend.php", {
					parameters: query,
					onComplete: function(transport) {
						notify('');

						Element.hide('feed_browser_spinner');

						var c = $("browseFeedList");

						var reply = JSON.parse(transport.responseText);

						var r = reply['content'];
						var mode = reply['mode'];

						if (c && r) {
							c.innerHTML = r;
						}

						dojo.parser.parse("browseFeedList");

						if (mode == 2) {
							Element.show(dijit.byId('feed_archive_remove').domNode);
						} else {
							Element.hide(dijit.byId('feed_archive_remove').domNode);
						}

					} });
			},
			removeFromArchive: function() {
				var selected = this.getSelectedFeedIds();

				if (selected.length > 0) {

					var pr = __("Remove selected feeds from the archive? Feeds with stored articles will not be removed.");

					if (confirm(pr)) {
						Element.show('feed_browser_spinner');

						var query = "?op=rpc&method=remarchive&ids=" +
							param_escape(selected.toString());;

						new Ajax.Request("backend.php", {
							parameters: query,
							onComplete: function(transport) {
								dialog.update();
							} });
					}
				}
			},
			execute: function() {
				if (this.validate()) {
					this.subscribe();
				}
			},
			href: query});

		dialog.show();

	} catch (e) {
		exception_error("editFeed", e);
	}
}

function showFeedsWithErrors() {
	try {
		var query = "backend.php?op=pref-feeds&method=feedsWithErrors";

		if (dijit.byId("errorFeedsDlg"))
			dijit.byId("errorFeedsDlg").destroyRecursive();

		dialog = new dijit.Dialog({
			id: "errorFeedsDlg",
			title: __("Feeds with update errors"),
			style: "width: 600px",
			getSelectedFeeds: function() {
				return getSelectedTableRowIds("prefErrorFeedList");
			},
			removeSelected: function() {
				var sel_rows = this.getSelectedFeeds();

				console.log(sel_rows);

				if (sel_rows.length > 0) {
					var ok = confirm(__("Remove selected feeds?"));

					if (ok) {
						notify_progress("Removing selected feeds...", true);

						var query = "?op=pref-feeds&method=remove&ids="+
							param_escape(sel_rows.toString());

						new Ajax.Request("backend.php",	{
							parameters: query,
							onComplete: function(transport) {
								notify('');
								dialog.hide();
								updateFeedList();
							} });
					}

				} else {
					alert(__("No feeds are selected."));
				}
			},
			execute: function() {
				if (this.validate()) {
				}
			},
			href: query});

		dialog.show();

	} catch (e) {
		exception_error("showFeedsWithErrors", e);
	}

}

/* new support functions for SelectByTag */

function get_all_tags(selObj){
	try {
		if( !selObj ) return "";

		var result = "";
		var len = selObj.options.length;

		for (var i=0; i < len; i++){
			if (selObj.options[i].selected) {
				result += selObj[i].value + "%2C";   // is really a comma
			}
		}

		if (result.length > 0){
			result = result.substr(0, result.length-3);  // remove trailing %2C
		}

		return(result);

	} catch (e) {
		exception_error("get_all_tags", e);
	}
}

function get_radio_checked(radioObj) {
	try {
		if (!radioObj) return "";

		var len = radioObj.length;

		if (len == undefined){
			if(radioObj.checked){
				return(radioObj.value);
			} else {
				return("");
			}
		}

		for( var i=0; i < len; i++ ){
			if( radioObj[i].checked ){
				return( radioObj[i].value);
			}
		}

	} catch (e) {
		exception_error("get_radio_checked", e);
	}
	return("");
}

function get_timestamp() {
	var date = new Date();
	return Math.round(date.getTime() / 1000);
}

function helpDialog(topic) {
	try {
		var query = "backend.php?op=backend&method=help&topic=" + param_escape(topic);

		if (dijit.byId("helpDlg"))
			dijit.byId("helpDlg").destroyRecursive();

		dialog = new dijit.Dialog({
			id: "helpDlg",
			title: __("Help"),
			style: "width: 600px",
			href: query,
		});

		dialog.show();

	} catch (e) {
		exception_error("helpDialog", e);
	}
}

function htmlspecialchars_decode (string, quote_style) {
  // http://kevin.vanzonneveld.net
  // +   original by: Mirek Slugen
  // +   improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
  // +   bugfixed by: Mateusz "loonquawl" Zalega
  // +      input by: ReverseSyntax
  // +      input by: Slawomir Kaniecki
  // +      input by: Scott Cariss
  // +      input by: Francois
  // +   bugfixed by: Onno Marsman
  // +    revised by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
  // +   bugfixed by: Brett Zamir (http://brett-zamir.me)
  // +      input by: Ratheous
  // +      input by: Mailfaker (http://www.weedem.fr/)
  // +      reimplemented by: Brett Zamir (http://brett-zamir.me)
  // +    bugfixed by: Brett Zamir (http://brett-zamir.me)
  // *     example 1: htmlspecialchars_decode("<p>this -&gt; &quot;</p>", 'ENT_NOQUOTES');
  // *     returns 1: '<p>this -> &quot;</p>'
  // *     example 2: htmlspecialchars_decode("&amp;quot;");
  // *     returns 2: '&quot;'
  var optTemp = 0,
    i = 0,
    noquotes = false;
  if (typeof quote_style === 'undefined') {
    quote_style = 2;
  }
  string = string.toString().replace(/&lt;/g, '<').replace(/&gt;/g, '>');
  var OPTS = {
    'ENT_NOQUOTES': 0,
    'ENT_HTML_QUOTE_SINGLE': 1,
    'ENT_HTML_QUOTE_DOUBLE': 2,
    'ENT_COMPAT': 2,
    'ENT_QUOTES': 3,
    'ENT_IGNORE': 4
  };
  if (quote_style === 0) {
    noquotes = true;
  }
  if (typeof quote_style !== 'number') { // Allow for a single string or an array of string flags
    quote_style = [].concat(quote_style);
    for (i = 0; i < quote_style.length; i++) {
      // Resolve string input to bitwise e.g. 'PATHINFO_EXTENSION' becomes 4
      if (OPTS[quote_style[i]] === 0) {
        noquotes = true;
      } else if (OPTS[quote_style[i]]) {
        optTemp = optTemp | OPTS[quote_style[i]];
      }
    }
    quote_style = optTemp;
  }
  if (quote_style & OPTS.ENT_HTML_QUOTE_SINGLE) {
    string = string.replace(/&#0*39;/g, "'"); // PHP doesn't currently escape if more than one 0, but it should
    // string = string.replace(/&apos;|&#x0*27;/g, "'"); // This would also be useful here, but not a part of PHP
  }
  if (!noquotes) {
    string = string.replace(/&quot;/g, '"');
  }
  // Put this in last place to avoid escape being double-decoded
  string = string.replace(/&amp;/g, '&');

  return string;
}


function label_to_feed_id(label) {
	return _label_base_index - 1 - Math.abs(label);
}

function feed_to_label_id(feed) {
	return _label_base_index - 1 + Math.abs(feed);
}

// http://stackoverflow.com/questions/6251937/how-to-get-selecteduser-highlighted-text-in-contenteditable-element-and-replac

function getSelectionText() {
	var text = "";

	if (typeof window.getSelection != "undefined") {
		var sel = window.getSelection();
		if (sel.rangeCount) {
			var container = document.createElement("div");
			for (var i = 0, len = sel.rangeCount; i < len; ++i) {
				container.appendChild(sel.getRangeAt(i).cloneContents());
			}
			text = container.innerHTML;
		}
	} else if (typeof document.selection != "undefined") {
		if (document.selection.type == "Text") {
			text = document.selection.createRange().textText;
		}
	}

	return text.stripTags();
}

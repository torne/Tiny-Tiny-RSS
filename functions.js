var hotkeys_enabled = true;

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

function p_notify(msg) {

	var n = parent.document.getElementById("notify");
	var nb = parent.document.getElementById("notify_body");

	if (!n || !nb) return;

	if (msg == "") msg = "&nbsp;";

	nb.innerHTML = msg;

/*	if (msg.length == 0) {
		n.style.display = "none";
	} else {
		n.style.display = "block";
	} */

}

function notify(msg) {

	var n = document.getElementById("notify");
	var nb = document.getElementById("notify_body");

	if (!n || !nb) return;

	if (msg == "") msg = "&nbsp;";

	nb.innerHTML = msg;

/*	if (msg.length == 0) {
		n.style.display = "none";
	} else {
		n.style.display = "block";
	} */

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

	var piggie = document.getElementById("piggie");

	if (piggie) {

		if (seq.match("807371717369")) {
			localPiggieFunction(true);
		} else {
			localPiggieFunction(false);
		}
	}
	
	if (typeof localHotkeyHandler != 'undefined') {
		localHotkeyHandler(keycode);
	}

}

function cleanSelectedList(element) {
	var content = document.getElementById(element);

	for (i = 0; i < content.childNodes.length; i++) {
		content.childNodes[i].className = 
			content.childNodes[i].className.replace("Selected", "");
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

function setCookie(name, value, expires, path, domain, secure) {
	document.cookie= name + "=" + escape(value) +
		((expires) ? "; expires=" + expires.toGMTString() : "") +
		((path) ? "; path=" + path : "") +
		((domain) ? "; domain=" + domain : "") +
		((secure) ? "; secure" : "");
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

var xmlhttp_rpc = false;

/*@cc_on @*/
/*@if (@_jscript_version >= 5)
// JScript gives us Conditional compilation, we can cope with old IE versions.
// and security blocked creation of the objects.
try {
	xmlhttp_rpc = new ActiveXObject("Msxml2.XMLHTTP");
} catch (e) {
	try {
		xmlhttp_rpc = new ActiveXObject("Microsoft.XMLHTTP");
	} catch (E) {
		xmlhttp_rpc = false;
	}
}
@end @*/

if (!xmlhttp_rpc && typeof XMLHttpRequest!='undefined') {
	xmlhttp_rpc = new XMLHttpRequest();
}

function label_counters_callback() {
	if (xmlhttp_rpc.readyState == 4) {

		if (!xmlhttp.responseXML) {
			notify("label_counters_callback: backend did not return valid XML");
			return;
		}

		var reply = xmlhttp_rpc.responseXML.firstChild;

		var f_document = parent.frames["feeds-frame"].document;

		for (var l = 0; l < reply.childNodes.length; l++) {
			var id = reply.childNodes[l].getAttribute("id");
			var ctr = reply.childNodes[l].getAttribute("counter");

			var feedctr = f_document.getElementById("FEEDCTR-" + id);
			var feedu = f_document.getElementById("FEEDU-" + id);
			var feedr = f_document.getElementById("FEEDR-" + id);

			if (feedctr && feedu && feedr) {

				feedu.innerHTML = ctr;
	
				if (ctr > 0) {
					feedctr.className = "odd";
					if (!feedr.className.match("Unread")) {
						feedr.className = feedr.className + "Unread";
					}
				} else {
					feedctr.className = "invisible";
					feedr.className = feedr.className.replace("Unread", "");
				}			
			}
		}
	}
}

function update_label_counters(feed) {
	if (xmlhttp_ready(xmlhttp_rpc)) {
		var query = "backend.php?op=rpc&subop=getAllCounters";

		if (feed > 0) {
			query = query + "&aid=" + feed;
		}

		xmlhttp_rpc.open("GET", query, true);
		xmlhttp_rpc.onreadystatechange=label_counters_callback;
		xmlhttp_rpc.send(null);
	}
}

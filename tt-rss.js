/*
	This program is Copyright (c) 2003-2005 Andrew Dolgov <cthulhoo@gmail.com>		
	Licensed under GPL v.2 or (at your preference) any later version.
*/

var xmlhttp = false;

/*@cc_on @*/
/*@if (@_jscript_version >= 5)
// JScript gives us Conditional compilation, we can cope with old IE versions.
// and security blocked creation of the objects.
try {
	xmlhttp = new ActiveXObject("Msxml2.XMLHTTP");
} catch (e) {
	try {
		xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
	} catch (E) {
		xmlhttp = false;
	}
}
@end @*/

if (!xmlhttp && typeof XMLHttpRequest!='undefined') {
	xmlhttp = new XMLHttpRequest();
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

function notify(msg) {

	var n = document.getElementById("notify");

	n.innerHTML = msg;

}

function feedlist_callback() {
	var container = document.getElementById('feeds');
	if (xmlhttp.readyState == 4) {
		container.innerHTML=xmlhttp.responseText;
	} else {
	}
}

function viewfeed_callback() {
	var container = document.getElementById('headlines');
	if (xmlhttp.readyState == 4) {
		container.innerHTML = xmlhttp.responseText;
	} else {
	}
}

function view_callback() {
	var container = document.getElementById('content');
	if (xmlhttp.readyState == 4) {
		container.innerHTML=xmlhttp.responseText;		
	} else {
	}
}


function update_feed_list() {

	xmlhttp.open("GET", "backend.php?op=feeds", true);
	xmlhttp.onreadystatechange=feedlist_callback;
	xmlhttp.send(null);

}

function viewfeed(feed, skip) {

	notify("view-feed: " + feed);

	document.getElementById('headlines').innerHTML='Loading headlines, please wait...';		

	xmlhttp.open("GET", "backend.php?op=viewfeed&feed=" + param_escape(feed) +
		"&skip=" + skip, true);
	xmlhttp.onreadystatechange=viewfeed_callback;
	xmlhttp.send(null);

}

function view(id) {

	var crow = document.getElementById("RROW-" + id);

	if (crow) {
		crow.className = crow.className.replace("Unread", "");
	}

	notify(crow.className);

	document.getElementById('content').innerHTML='Loading, please wait...';		

	xmlhttp.open("GET", "backend.php?op=view&id=" + param_escape(id), true);
	xmlhttp.onreadystatechange=view_callback;
	xmlhttp.send(null);

}

function init() {

	notify("init");

	update_feed_list();

}

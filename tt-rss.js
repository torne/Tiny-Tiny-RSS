/*
	This program is Copyright (c) 2003-2005 Andrew Dolgov <cthulhoo@gmail.com>		
	Licensed under GPL v.2 or (at your preference) any later version.
*/

var xmlhttp = false;

var total_unread = 0;

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

function feedlist_callback() {
	var container = document.getElementById('feeds');
	if (xmlhttp.readyState == 4) {
		container.innerHTML=xmlhttp.responseText;

		var feedtu = document.getElementById("FEEDTU");

		if (feedtu) {
			total_unread = feedtu.innerHTML;
			update_title();
		}
	}
}

function viewfeed_callback() {
	var container = document.getElementById('headlines');
	if (xmlhttp.readyState == 4) {
		container.innerHTML = xmlhttp.responseText;

		var factive = document.getElementById("FACTIVE");
		var funread = document.getElementById("FUNREAD");
		var ftotal = document.getElementById("FTOTAL");

		if (ftotal && factive && funread) {
			var feed_id = factive.innerHTML;

			var feedr = document.getElementById("FEEDR-" + feed_id);
			var feedt = document.getElementById("FEEDT-" + feed_id);
			var feedu = document.getElementById("FEEDU-" + feed_id);

			feedt.innerHTML = ftotal.innerHTML;
			feedu.innerHTML = funread.innerHTML;

			if (feedu.innerHTML > 0 && !feedr.className.match("Unread")) {
					feedr.className = feedr.className + "Unread";
			} else if (feedu.innerHTML <= 0) {	
					feedr.className = feedr.className.replace("Unread", "");
			}

		}
	}
}

function view_callback() {
	var container = document.getElementById('content');
	if (xmlhttp.readyState == 4) {
		container.innerHTML=xmlhttp.responseText;		
	}
}


function update_feed_list(called_from_timer, fetch) {

	if (called_from_timer != true) {
		document.getElementById("feeds").innerHTML = "Loading feeds, please wait...";
	}

	var query_str = "backend.php?op=feeds";

	if (fetch) query_str = query_str + "&fetch=yes";

	xmlhttp.open("GET", query_str, true);
	xmlhttp.onreadystatechange=feedlist_callback;
	xmlhttp.send(null);


}

function viewfeed(feed, skip, ext) {

//	notify("view-feed: " + feed);

	document.getElementById('headlines').innerHTML='Loading headlines, please wait...';		
	document.getElementById('content').innerHTML='&nbsp;';		

	xmlhttp.open("GET", "backend.php?op=viewfeed&feed=" + param_escape(feed) +
		"&skip=" + param_escape(skip) + "&ext=" + param_escape(ext) , true);
	xmlhttp.onreadystatechange=viewfeed_callback;
	xmlhttp.send(null);

}

function view(id,feed_id) {

	var crow = document.getElementById("RROW-" + id);

	if (crow.className.match("Unread")) {
		var umark = document.getElementById("FEEDU-" + feed_id);
		umark.innerHTML = umark.innerHTML - 1;
		crow.className = crow.className.replace("Unread", "");

		if (umark.innerHTML == "0") {
			var feedr = document.getElementById("FEEDR-" + feed_id);
			feedr.className = feedr.className.replace("Unread", "");
		}
	
		total_unread--;

		update_title(); 
	}	

	var upd_img_pic = document.getElementById("FUPDPIC-" + id);

	if (upd_img_pic) {
		upd_img_pic.innerHTML = "";
	} 

	document.getElementById('content').innerHTML='Loading, please wait...';		

	xmlhttp.open("GET", "backend.php?op=view&id=" + param_escape(id), true);
	xmlhttp.onreadystatechange=view_callback;
	xmlhttp.send(null);

}

function timeout() {

	update_feed_list(true);

	setTimeout("timeout()", 1800*1000);

}

function search(feed, sender) {

	notify("Search: " + feed + ", " + sender.value)

	document.getElementById('headlines').innerHTML='Loading headlines, please wait...';		
	document.getElementById('content').innerHTML='&nbsp;';		

	xmlhttp.open("GET", "backend.php?op=viewfeed&feed=" + param_escape(feed) +
		"&search=" + param_escape(sender.value) + "&ext=SEARCH", true);
	xmlhttp.onreadystatechange=viewfeed_callback;
	xmlhttp.send(null);

}

function update_title() {
	//document.title = "Tiny Tiny RSS (" + total_unread + " unread)";
}

function init() {
	update_feed_list(false, false);
	setTimeout("timeout()", 1800*1000);
}

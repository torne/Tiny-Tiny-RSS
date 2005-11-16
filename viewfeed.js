var active_post_id = false;
var total_unread = 0;

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

function view(id, feed_id) {

//	p_notify("Loading article...");

	var f_document = parent.frames["feeds-frame"].document;
	var h_document = document;
	var m_document = parent.document;

	enableHotkeys();

	var crow = h_document.getElementById("RROW-" + id);

/*	if (crow.className.match("Unread")) {
		var umark = f_document.getElementById("FEEDU-" + feed_id);
		
		umark.innerHTML = umark.innerHTML - 1;
		crow.className = crow.className.replace("Unread", "");

		if (umark.innerHTML == "0") {
			var feedr = f_document.getElementById("FEEDR-" + feed_id);	
			feedr.className = feedr.className.replace("Unread", "");

			var feedctr = f_document.getElementById("FEEDCTR-" + feed_id);

			if (feedctr) {
				feedctr.className = "invisible";
			}
		}

		total_unread--;
	}	 */

	crow.className = crow.className.replace("Unread", "");

	cleanSelected("headlinesList");

	var upd_img_pic = h_document.getElementById("FUPDPIC-" + id);

	if (upd_img_pic) {
		upd_img_pic.src = "images/blank_icon.gif";
	} 

	active_post_id = id; 
	setActiveFeedId(feed_id);

	var content = m_document.getElementById("content-frame");

	if (content) {
		content.src = "backend.php?op=view&addheader=true&id=" + param_escape(id) +
			"&feed=" + param_escape(feed_id);
		markHeadline(active_post_id);
	}

}


function toggleMark(id, toggle) {

	var f_document = parent.frames["feeds-frame"].document;

	if (!xmlhttp_ready(xmlhttp_rpc)) {
		printLockingError();
		return;
	}

	var query = "backend.php?op=rpc&id=" + id + "&subop=mark";

	var mark_img = document.getElementById("FMARKPIC-" + id);
	var vfeedu = f_document.getElementById("FEEDU--1");
	var crow = document.getElementById("RROW-" + id);

//	alert(vfeedu);

	if (toggle == true) {
		mark_img.src = "images/mark_set.png";
		mark_img.alt = "Reset mark";
		mark_img.setAttribute('onclick', 'javascript:toggleMark('+id+', false)');
		query = query + "&mark=1";

		if (vfeedu && crow.className.match("Unread")) {
			vfeedu.innerHTML = (+vfeedu.innerHTML) + 1;
		}

	} else {
		mark_img.src = "images/mark_unset.png";
		mark_img.alt = "Set mark";
		mark_img.setAttribute('onclick', 'javascript:toggleMark('+id+', true)');
		query = query + "&mark=0";

		if (vfeedu && crow.className.match("Unread")) {
			vfeedu.innerHTML = (+vfeedu.innerHTML) - 1;
		}

	}

	var vfeedctr = f_document.getElementById("FEEDCTR--1");
	var vfeedr = f_document.getElementById("FEEDR--1");

	if (vfeedu && vfeedctr) {
		if ((+vfeedu.innerHTML) > 0) {
			if (crow.className.match("Unread") && !vfeedr.className.match("Unread")) {
				vfeedr.className = vfeedr.className + "Unread";
				vfeedctr.className = "odd";
			}
		} else {
			vfeedctr.className = "invisible";
			vfeedr.className = vfeedr.className.replace("Unread", "");
		}
	}

	xmlhttp_rpc.open("GET", query, true);
	xmlhttp_rpc.onreadystatechange=rpc_notify_callback;
	xmlhttp_rpc.send(null);

}

function moveToPost(mode) {

	var rows = getVisibleHeadlineIds();

	var prev_id;
	var next_id;

	if (active_post_id == false) {
		next_id = getFirstVisibleHeadlineId();
		prev_id = getLastVisibleHeadlineId();
	} else {	
		for (var i = 0; i < rows.length; i++) {
			if (rows[i] == active_post_id) {
				prev_id = rows[i-1];
				next_id = rows[i+1];			
			}
		}
	}

	if (mode == "next") {
	 	if (next_id != undefined) {
			view(next_id, getActiveFeedId());
		}
	}

	if (mode == "prev") {
		if ( prev_id != undefined) {
			view(prev_id, getActiveFeedId());
		}
	} 
}

function viewfeed(id) {
	var f = parent.frames["feeds-frame"];
	f.viewfeed(id, 0);
}

function localHotkeyHandler(keycode) {

	if (keycode == 78 || keycode == 40) { // n, down
		return moveToPost('next');
	}

	if (keycode == 80 || keycode == 38) { // p, up
		return moveToPost('prev');
	} 

	if (keycode == 65) { // a
		return parent.toggleDispRead();
	}

	if (keycode == 85) { // u
		if (parent.getActiveFeedId()) {
			return parent.viewfeed(parent.getActiveFeedId(), 0, "ForceUpdate");
		}
	}

	if (keycode == 82) { // r
		return parent.scheduleFeedUpdate(true);
	}

// FIXME
//	if (keycode == 85) {
//		return viewfeed(active_feed_id, active_offset, "ForceUpdate");
//	}

//	alert("KC: " + keycode);

}

function init() {
	document.onkeydown = hotkey_handler;
}

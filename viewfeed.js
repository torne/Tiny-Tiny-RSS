var active_feed_id = false;
var active_post_id = false;
var total_unread = 0;

var xmlhttp_rpc = false;

/*@cc_on @*/
/*@if (@_jscript_version >= 5)
// JScript gives us Conditional compilation, we can cope with old IE versions.
// and security blocked creation of the objects.
try {
	xmlhttp = new ActiveXObject("Msxml2.XMLHTTP");
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

	enableHotkeys();

	var crow = document.getElementById("RROW-" + id);

	var f_doc = parent.frames["feeds-frame"].document;

	if (crow.className.match("Unread")) {
		var umark = f_doc.getElementById("FEEDU-" + feed_id);
		
		umark.innerHTML = umark.innerHTML - 1;
		crow.className = crow.className.replace("Unread", "");

		if (umark.innerHTML == "0") {
			var feedr = f_doc.getElementById("FEEDR-" + feed_id);	
			feedr.className = feedr.className.replace("Unread", "");

			var feedctr = f_doc.getElementById("FEEDCTR-" + feed_id);

			if (feedctr) {
				feedctr.className = "invisible";
			}
		}

		total_unread--;
	}	


	cleanSelected("headlinesList");

	var upd_img_pic = document.getElementById("FUPDPIC-" + id);

	if (upd_img_pic) {
		upd_img_pic.src = "images/blank_icon.png";
	} 

	var unread_rows = getVisibleUnreadHeadlines();

	if (unread_rows.length == 0) {
		var button = document.getElementById("btnCatchupPage");
		if (button) {
			button.className = "disabledButton";
			button.href = "";
		}
	}

	active_post_id = id; 
	active_feed_id = feed_id;

	var content = parent.document.getElementById("content-frame");

	if (content) {
		content.src = "backend.php?op=view&addheader=true&id=" + param_escape(id);
		markHeadline(active_post_id);
	}
}


function toggleMark(id, toggle) {

	if (!xmlhttp_ready(xmlhttp_rpc)) {
		printLockingError();
		return;
	}

	var mark_img = document.getElementById("FMARKPIC-" + id);

	var query = "backend.php?op=rpc&id=" + id + "&subop=mark";

	if (toggle == true) {
		mark_img.src = "images/mark_set.png";
		mark_img.alt = "Reset mark";
		mark_img.setAttribute('onclick', 'javascript:toggleMark('+id+', false)');
		query = query + "&mark=1";
	} else {
		mark_img.src = "images/mark_unset.png";
		mark_img.alt = "Set mark";
		mark_img.setAttribute('onclick', 'javascript:toggleMark('+id+', true)');
		query = query + "&mark=0";
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
			view(next_id, active_feed_id);
		}
	}

	if (mode == "prev") {
		if ( prev_id != undefined) {
			view(prev_id, active_feed_id);
		}
	} 
}

function localHotkeyHandler(keycode) {

	if (keycode == 78) {
		return moveToPost('next');
	}

	if (keycode == 80) {
		return moveToPost('prev');
	} 

// FIXME
//	if (keycode == 85) {
//		return viewfeed(active_feed_id, active_offset, "ForceUpdate");
//	}

//	alert("KC: " + keycode);

}

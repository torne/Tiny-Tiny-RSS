var active_post_id = false;

var xmlhttp_rpc = Ajax.getTransport();

function view(id, feed_id) {
	
	try {
		debug("loading article: " + id + "/" + feed_id);
	
		var f_document = getFeedsContext().document;
		var m_document = parent.document;
	
		enableHotkeys();
	
		var crow = document.getElementById("RROW-" + id);
	
		crow.className = crow.className.replace("Unread", "");
	
		cleanSelected("headlinesList");
	
		var upd_img_pic = document.getElementById("FUPDPIC-" + id);
	
		if (upd_img_pic) {
			upd_img_pic.src = "images/blank_icon.gif";
		} 
	
		active_post_id = id; 
		setActiveFeedId(feed_id);
	
		var content = m_document.getElementById("content-frame");
	
		content.src = "backend.php?op=view&id=" + param_escape(id) +
			"&feed=" + param_escape(feed_id);
	
		selectTableRowsByIdPrefix('headlinesList', 'RROW-', 'RCHK-', false);
		markHeadline(active_post_id);

	} catch (e) {
		exception_error("view", e);
	}
}

function toggleMark(id) {

	var f_document = parent.frames["feeds-frame"].document;

	if (!xmlhttp_ready(xmlhttp_rpc)) {
		printLockingError();
		return;
	}

	var query = "backend.php?op=rpc&id=" + id + "&subop=mark";

	var mark_img = document.getElementById("FMARKPIC-" + id);
	var vfeedu = f_document.getElementById("FEEDU--1");
	var crow = document.getElementById("RROW-" + id);

	if (mark_img.alt != "Reset mark") {
		mark_img.src = "images/mark_set.png";
		mark_img.alt = "Reset mark";
		mark_img.setAttribute('onclick', 'javascript:toggleMark('+id+')');
		query = query + "&mark=1";

		if (vfeedu && crow.className.match("Unread")) {
			vfeedu.innerHTML = (+vfeedu.innerHTML) + 1;
		}

	} else {
		mark_img.src = "images/mark_unset.png";
		mark_img.alt = "Set mark";
		mark_img.setAttribute('onclick', 'javascript:toggleMark('+id+')');
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

	debug("toggle starred for aid " + id);

	new Ajax.Request(query);

}

function moveToPost(mode) {

	// check for combined mode
	if (!document.getElementById("headlinesList"))
		return;

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

function toggleUnread(id, cmode) {
	try {
		if (!xmlhttp_ready(xmlhttp_rpc)) {
			printLockingError();
			return;
		}
	
		var row = document.getElementById("RROW-" + id);
		if (row) {
			var nc = row.className;
			nc = nc.replace("Unread", "");
			nc = nc.replace("Selected", "");

			if (row.className.match("Unread")) {
				row.className = nc;
			} else {
				row.className = nc + "Unread";
			}

			if (!cmode) cmode = 2;

			var query = "backend.php?op=rpc&subop=catchupSelected&ids=" +
				param_escape(id) + "&cmode=" + param_escape(cmode);

			xmlhttp_rpc.open("GET", query, true);
			xmlhttp_rpc.onreadystatechange=all_counters_callback;
			xmlhttp_rpc.send(null);

		}


	} catch (e) {
		exception_error("toggleUnread", e);
	}
}

function selectionToggleUnread(cdm_mode) {
	try {
		if (!xmlhttp_ready(xmlhttp_rpc)) {
			printLockingError();
			return;
		}
	
		var rows;

		if (cdm_mode) {
			rows = cdmGetSelectedArticles();
		} else {	
			rows = getSelectedTableRowIds("headlinesList", "RROW", "RCHK");
		}

		for (i = 0; i < rows.length; i++) {
			var row = document.getElementById("RROW-" + rows[i]);
			if (row) {
				var nc = row.className;
				nc = nc.replace("Unread", "");
				nc = nc.replace("Selected", "");

				if (row.className.match("Unread")) {
					row.className = nc + "Selected";
				} else {
					row.className = nc + "UnreadSelected";
				}
			}
		}

		if (rows.length > 0) {

			var query = "backend.php?op=rpc&subop=catchupSelected&ids=" +
				param_escape(rows.toString()) + "&cmode=2";

			xmlhttp_rpc.open("GET", query, true);
			xmlhttp_rpc.onreadystatechange=all_counters_callback;
			xmlhttp_rpc.send(null);

		}

	} catch (e) {
		exception_error("selectionToggleUnread", e);
	}
}

function selectionToggleMarked(cdm_mode) {
	try {
		if (!xmlhttp_ready(xmlhttp_rpc)) {
			printLockingError();
			return;
		}
	
		var rows;
		
		if (cdm_mode) {
			rows = cdmGetSelectedArticles();
		} else {	
			rows = getSelectedTableRowIds("headlinesList", "RROW", "RCHK");
		}	

		for (i = 0; i < rows.length; i++) {
			var row = document.getElementById("RROW-" + rows[i]);
			var mark_img = document.getElementById("FMARKPIC-" + rows[i]);

			if (row && mark_img) {

				if (mark_img.alt == "Set mark") {
					mark_img.src = "images/mark_set.png";
					mark_img.alt = "Reset mark";
					mark_img.setAttribute('onclick', 
						'javascript:toggleMark('+rows[i]+', false)');

				} else {
					mark_img.src = "images/mark_unset.png";
					mark_img.alt = "Set mark";
					mark_img.setAttribute('onclick', 
						'javascript:toggleMark('+rows[i]+', true)');
				}
			}
		}

		if (rows.length > 0) {

			var query = "backend.php?op=rpc&subop=markSelected&ids=" +
				param_escape(rows.toString()) + "&cmode=2";

			xmlhttp_rpc.open("GET", query, true);
			xmlhttp_rpc.onreadystatechange=all_counters_callback;
			xmlhttp_rpc.send(null);

		}

	} catch (e) {
		exception_error("selectionToggleMarked", e);
	}
}

function cdmGetSelectedArticles() {
	var sel_articles = new Array();
	var container = document.getElementById("headlinesContainer");

	for (i = 0; i < container.childNodes.length; i++) {
		var child = container.childNodes[i];

		if (child.id.match("RROW-") && child.className.match("Selected")) {
			var c_id = child.id.replace("RROW-", "");
			sel_articles.push(c_id);
		}
	}

	return sel_articles;
}

// mode = all,none,unread
function cdmSelectArticles(mode) {
	var container = document.getElementById("headlinesContainer");

	for (i = 0; i < container.childNodes.length; i++) {
		var child = container.childNodes[i];

		if (child.id.match("RROW-")) {
			var aid = child.id.replace("RROW-", "");

			var cb = document.getElementById("RCHK-" + aid);

			if (mode == "all") {
				if (!child.className.match("Selected")) {
					child.className = child.className + "Selected";
					cb.checked = true;
				}
			} else if (mode == "unread") {
				if (child.className.match("Unread") && !child.className.match("Selected")) {
					child.className = child.className + "Selected";
					cb.checked = true;
				}
			} else {
				child.className = child.className.replace("Selected", "");
				cb.checked = false;
			}
		}		
	}
}

function catchupPage() {
	selectTableRowsByIdPrefix('headlinesList', 'RROW-', 'RCHK-', true, 'Unread', true);
	selectionToggleUnread();
	selectTableRowsByIdPrefix('headlinesList', 'RROW-', 'RCHK-', false);
}


function init() {
	if (arguments.callee.done) return;
	arguments.callee.done = true;		

	if (parent.frames["feeds-frame"]) {
		document.onkeydown = hotkey_handler;
	}

	var hw = document.getElementById("headlinesList").scrollHeight;
	var pw = parent.document.getElementById("headlines").scrollHeight;

	if (hw >= pw) {
		var bt = document.getElementById("headlineActionsBottom");
		bt.className = "headlinesSubToolbar";
	}
	
}

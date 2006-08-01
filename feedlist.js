var xmlhttp = Ajax.getTransport();

var cat_view_mode = false;

function viewCategory(cat) {
	viewfeed(cat, 0, '', false, true);
}

function viewfeed(feed, skip, subop, doc, is_cat, subop_param) {
	try {

		if (!doc) doc = parent.document;
	
		enableHotkeys();

		var toolbar_query = parent.Form.serialize("main_toolbar_form");
		var toolbar_form = parent.document.forms["main_toolbar_form"];

		if (parent.document.forms["main_toolbar_form"].query) {
			toolbar_form.query.value = "";
		}

//		setCookie("ttrss_vf_limit", toolbar_form.limit[toolbar_form.limit.selectedIndex].value);
//		setCookie("ttrss_vf_vmode", toolbar_form.view_mode[toolbar_form.view_mode.selectedIndex].value);

		storeInitParam("toolbar_limit", 
			toolbar_form.limit[toolbar_form.limit.selectedIndex].value);

		storeInitParam("toolbar_view_mode", 
			toolbar_form.view_mode[toolbar_form.view_mode.selectedIndex].value); 

/*		var sp = new Object();
		sp["toolbar_limit"] = toolbar_form.limit[toolbar_form.limit.selectedIndex].value;
		sp["toolbar_view_mode"] = toolbar_form.view_mode[toolbar_form.view_mode.selectedIndex].value;
		storeInitParams(sp); */

		var query = "backend.php?op=viewfeed&feed=" + feed + "&" +
			toolbar_query + "&subop=" + param_escape(subop);

		if (parent.document.getElementById("search_form")) {
			var search_query = parent.Form.serialize("search_form");
			query = query + "&" + search_query;
			parent.closeInfoBox(true);
		}

//		if (getActiveFeedId() != feed) {
			cat_view_mode = is_cat;
//		}

		debug("CVMODE: " + cat_view_mode);

		var fe = document.getElementById("FEEDR-" + getActiveFeedId());

		if (fe) {
			fe.className = fe.className.replace("Selected", "");
		}

		setActiveFeedId(feed);
	
		getMainContext().active_feed_is_cat = is_cat;

		if (subop == "MarkAllRead") {

			var feedr = document.getElementById("FEEDR-" + feed);
			var feedctr = document.getElementById("FEEDCTR-" + feed);

			if (feedr && feedctr) {
		
				feedctr.className = "invisible";
	
				if (feedr.className.match("Unread")) {
					feedr.className = feedr.className.replace("Unread", "");
				}
			}

			var feedlist = document.getElementById('feedList');
			
			var next_unread_feed = getRelativeFeedId(feedlist,
					getActiveFeedId(), "next", true);

			var show_next_feed = parent.getInitParam("on_catchup_show_next_feed") == "1";

			if (next_unread_feed && show_next_feed) {
				query = query + "&nuf=" + param_escape(next_unread_feed);
				setActiveFeedId(next_unread_feed);
			}
		}

		if (cat_view_mode) {
			query = query + "&cat=1";
		}

		var headlines_frame = parent.frames["headlines-frame"];

		if (navigator.userAgent.match("Opera")) {
			var date = new Date();
			var timestamp = Math.round(date.getTime() / 1000);
			query = query + "&ts=" + timestamp
		}

		debug(query);

		headlines_frame.location.href = query;
	
//		cleanSelectedList("feedList");
	
		if (!cat_view_mode) {
			var feedr = document.getElementById("FEEDR-" + getActiveFeedId());
			if (feedr && !feedr.className.match("Selected")) {	
				feedr.className = feedr.className + "Selected";
			} 
		}
		
		parent.disableContainerChildren("headlinesToolbar", false);
		parent.Form.enable("main_toolbar_form");

	} catch (e) {
		exception_error("viewfeed", e);
	}		
}

function toggleCollapseCat(cat) {
	try {
		if (!xmlhttp_ready(xmlhttp)) {
			printLockingError();
			return;
		}
	
		var cat_elem = document.getElementById("FCAT-" + cat);
		var cat_list = document.getElementById("FCATLIST-" + cat).parentNode;
		var caption = document.getElementById("FCAP-" + cat);
		
		if (cat_list.className.match("invisible")) {
			cat_list.className = "";
			caption.innerHTML = caption.innerHTML.replace("...", "");
			if (cat == 0) {
				setCookie("ttrss_vf_uclps", "0");
			}
		} else {
			cat_list.className = "invisible";
			caption.innerHTML = caption.innerHTML + "...";
			if (cat == 0) {
				setCookie("ttrss_vf_uclps", "1");
			}
		}

		new Ajax.Request("backend.php?op=feeds&subop=collapse&cid=" + 
			param_escape(cat));

	} catch (e) {
		exception_error("toggleCollapseCat", e);
	}
}

function init() {
	try {
		if (arguments.callee.done) return;
		arguments.callee.done = true;		
		
		parent.debug("in feedlist init");
		
		hideOrShowFeeds(document, getInitParam("hide_read_feeds") == 1);
		document.onkeydown = hotkey_handler;
		parent.setTimeout("timeout()", 0);

		parent.debug("about to remove splash, OMG!");

		var o = parent.document.getElementById("overlay");

		if (o) {
			o.style.display = "none";
			parent.debug("removed splash!");
		}

	} catch (e) {
		exception_error("feedlist/init", e);
	}
}

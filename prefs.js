var active_feed_cat = false;
var active_tab = false;

var init_params = new Array();

var caller_subop = false;
var sanity_check_done = false;
var hotkey_prefix = false;
var hotkey_prefix_pressed = false;

var color_picker_active = false;
var selection_disabled = false;
var mouse_is_down = false;

function feedlist_callback2(transport) {

	try {	

		var container = $('prefContent');	
		container.innerHTML=transport.responseText;
		selectTab("feedConfig", true);

		if (caller_subop) {
			var tuple = caller_subop.split(":");
			if (tuple[0] == 'editFeed') {
				window.setTimeout('editFeed('+tuple[1]+')', 100);
			}				

			caller_subop = false;
		}
		if (typeof correctPNG != 'undefined') {
			correctPNG();
		}
		notify("");
		remove_splash();

	} catch (e) {
		exception_error("feedlist_callback2", e);
	}
}

function filterlist_callback2(transport) {
	var container = $('prefContent');
	container.innerHTML=transport.responseText;
	if (typeof correctPNG != 'undefined') {
		correctPNG();
	}
	notify("");
	remove_splash();
}

function init_label_inline_editor() {
	try {
		if ($("prefLabelList")) {
			var elems = $("prefLabelList").getElementsByTagName("SPAN");

			for (var i = 0; i < elems.length; i++) {
				if (elems[i].id && elems[i].id.match("LILT-")) {

					var id = elems[i].id.replace("LILT-", "");

					new Ajax.InPlaceEditor(elems[i],
						'backend.php?op=pref-labels&subop=save&id=' + id,
						{cols: 20, rows: 1});

				}
			}
		}

	} catch (e) {
		exception_error("init_label_inline_editor", e);
	}
}

function labellist_callback2(transport) {

	try {

		var container = $('prefContent');
			closeInfoBox();
			container.innerHTML=transport.responseText;

			init_label_inline_editor();
	
			if (typeof correctPNG != 'undefined') {
				correctPNG();
			}
			notify("");
			remove_splash();

	} catch (e) {
		exception_error("labellist_callback2", e);
	}
}

function userlist_callback2(transport) {
	try {
		var container = $('prefContent');
		if (transport.readyState == 4) {
			container.innerHTML=transport.responseText;
			notify("");
			remove_splash();
		}
	} catch (e) {
		exception_error("userlist_callback2", e);
	}
}

function prefslist_callback2(transport) {
	try {
		var container = $('prefContent');
		container.innerHTML=transport.responseText;
		notify("");
		remove_splash();
	} catch (e) {
		exception_error("prefslist_callback2", e);
	}
}

function notify_callback2(transport) {
	notify_info(transport.responseText);	 
}

function init_profile_inline_editor() {
	try {

		if ($("prefFeedCatList")) {
			var elems = $("prefFeedCatList").getElementsByTagName("SPAN");

			for (var i = 0; i < elems.length; i++) {
				if (elems[i].id && elems[i].id.match("FCATT-")) {
					var id = elems[i].id.replace("FCATT-", "");
						new Ajax.InPlaceEditor(elems[i],
						'backend.php?op=rpc&subop=saveprofile&id=' + id);
				}
			}
		}

	} catch (e) {
		exception_error("init_profile_inline_editor", e);
	}
}

function init_cat_inline_editor() {
	try {

		if ($("prefFeedCatList")) {
			var elems = $("prefFeedCatList").getElementsByTagName("SPAN");

			for (var i = 0; i < elems.length; i++) {
				if (elems[i].id && elems[i].id.match("FCATT-")) {
					var cat_id = elems[i].id.replace("FCATT-", "");
						new Ajax.InPlaceEditor(elems[i],
						'backend.php?op=pref-feeds&subop=editCats&action=save&cid=' + cat_id);
				}
			}
		}

	} catch (e) {
		exception_error("init_cat_inline_editor", e);
	}
}

function infobox_feed_cat_callback2(transport) {
	try {
		infobox_callback2(transport);
		init_cat_inline_editor();
	} catch (e) {
		exception_error("infobox_feed_cat_callback2", e);
	}
}

function updateFeedList(sort_key) {

	try {

	var feed_search = $("feed_search");
	var search = "";
	if (feed_search) { search = feed_search.value; }

	var slat = $("show_last_article_times");

	var slat_checked = false;
	if (slat) {
		slat_checked = slat.checked;
	}

	var query = "?op=pref-feeds" +
		"&sort=" + param_escape(sort_key) + 
		"&slat=" + param_escape(slat_checked) +
		"&search=" + param_escape(search);

	new Ajax.Request("backend.php", {
		parameters: query,
		onComplete: function(transport) { 
			feedlist_callback2(transport); 
		} });
	} catch (e) {
		exception_error("updateFeedList", e);
	}
}

function updateUsersList(sort_key) {

	try {

		var user_search = $("user_search");
		var search = "";
		if (user_search) { search = user_search.value; }
	
		var query = "?op=pref-users&sort="
			+ param_escape(sort_key) +
			"&search=" + param_escape(search);
	
		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function(transport) { 
				userlist_callback2(transport); 
			} });

	} catch (e) {
		exception_error("updateUsersList", e);
	}
}

function addFeed() {

	try {

		var link = $("fadd_link");
	
		if (link.value.length == 0) {
			alert(__("Error: No feed URL given."));
		} else if (!isValidURL(link.value)) {
			alert(__("Error: Invalid feed URL."));
		} else {
			notify_progress("Adding feed...");
	
			var query = "?op=pref-feeds&subop=add&from=tt-rss&feed_url=" +
				param_escape(link.value);
	
			new Ajax.Request("backend.php",	{
				parameters: query,
				onComplete: function(transport) {
						feedlist_callback2(transport);
					} });
	
			link.value = "";
	
		}

	} catch (e) {
		exception_error("addFeed", e);
	}

}

function addPrefProfile() {

	var profile = $("fadd_profile");

	if (profile.value.length == 0) {
		alert(__("Can't add profile: no name specified."));
	} else {
		notify_progress("Adding profile...");

		var query = "?op=rpc&subop=addprofile&title=" +	
			param_escape(profile.value);

		new Ajax.Request("backend.php",	{
			parameters: query,
			onComplete: function(transport) {
					editProfiles();
				} });

	}
}


function addFeedCat() {

	var cat = $("fadd_cat");

	if (cat.value.length == 0) {
		alert(__("Can't add category: no name specified."));
	} else {
		notify_progress("Adding feed category...");

		var query = "?op=pref-feeds&subop=editCats&action=add&cat=" +
			param_escape(cat.value);

		new Ajax.Request("backend.php",	{
			parameters: query,
			onComplete: function(transport) {
					infobox_feed_cat_callback2(transport);
				} });

		link.value = "";

	}
}

function addUser() {

	try {

		var login = prompt(__("Please enter login:"), "");
	
		if (login == null) { 
			return false;
		}
	
		if (login == "") {
			alert(__("Can't create user: no login specified."));
			return false;
		}
	
		notify_progress("Adding user...");
	
		var query = "?op=pref-users&subop=add&login=" +
			param_escape(login);
				
		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function(transport) { 
				userlist_callback2(transport); 
			} });

	} catch (e) {
		exception_error("addUser", e);
	}
}

function editUser(id) {

	try {

		disableHotkeys();

		notify_progress("Loading, please wait...");

		selectTableRowsByIdPrefix('prefUserList', 'UMRR-', 'UMCHK-', false);
		selectTableRowById('UMRR-'+id, 'UMCHK-'+id, true);

		var query = "?op=pref-users&subop=edit&id=" +
			param_escape(id);

		new Ajax.Request("backend.php",	{
			parameters: query,
			onComplete: function(transport) {
					infobox_callback2(transport);
				} });

	} catch (e) {
		exception_error("editUser", e);
	}
		
}

function editFilter(id) {

	try {

		disableHotkeys();

		notify_progress("Loading, please wait...");

		selectTableRowsByIdPrefix('prefFilterList', 'FILRR-', 'FICHK-', false);
		selectTableRowById('FILRR-'+id, 'FICHK-'+id, true);

		var query = "?op=pref-filters&subop=edit&id=" + 
			param_escape(id);

		new Ajax.Request("backend.php",	{
			parameters: query,
			onComplete: function(transport) {
					infobox_callback2(transport);
				} });
	} catch (e) {
		exception_error("editFilter", e);
	}
}

function editFeed(feed) {

	try {

		disableHotkeys();
	
		notify_progress("Loading, please wait...");
	
		// clean selection from all rows & select row being edited
		selectTableRowsByIdPrefix('prefFeedList', 'FEEDR-', 'FRCHK-', false);
		selectTableRowById('FEEDR-'+feed, 'FRCHK-'+feed, true);
	
		var query = "?op=pref-feeds&subop=editfeed&id=" +
			param_escape(feed);
	
		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function(transport) {
					infobox_callback2(transport);
					document.forms["edit_feed_form"].title.focus();
				} });

	} catch (e) {
		exception_error("editFeed", e);
	}
}

function getSelectedLabels() {
	return getSelectedTableRowIds("prefLabelList", "LILRR");
}

function getSelectedUsers() {
	return getSelectedTableRowIds("prefUserList", "UMRR");
}

function getSelectedFeeds() {
	return getSelectedTableRowIds("prefFeedList", "FEEDR");
}

function getSelectedFilters() {
	return getSelectedTableRowIds("prefFilterList", "FILRR");
}

function getSelectedFeedCats() {
	return getSelectedTableRowIds("prefFeedCatList", "FCATR");
}


function removeSelectedLabels() {

	var sel_rows = getSelectedLabels();

	if (sel_rows.length > 0) {

		var ok = confirm(__("Remove selected labels?"));

		if (ok) {
			notify_progress("Removing selected labels...");
	
			var query = "?op=pref-labels&subop=remove&ids="+
				param_escape(sel_rows.toString());

			new Ajax.Request("backend.php",	{
				parameters: query,
				onComplete: function(transport) {
						labellist_callback2(transport);
					} });

		}
	} else {
		alert(__("No labels are selected."));
	}

	return false;
}

function removeSelectedUsers() {

	try {

		var sel_rows = getSelectedUsers();
	
		if (sel_rows.length > 0) {
	
			var ok = confirm(__("Remove selected users?"));
	
			if (ok) {
				notify_progress("Removing selected users...");
		
				var query = "?op=pref-users&subop=remove&ids="+
					param_escape(sel_rows.toString());
	
				new Ajax.Request("backend.php", {
					parameters: query,
					onComplete: function(transport) { 
						userlist_callback2(transport); 
					} });
	
			}
	
		} else {
			alert(__("No users are selected."));
		}

	} catch (e) {
		exception_error("removeSelectedUsers", e);
	}

	return false;
}

function removeSelectedFilters() {

	try {

		var sel_rows = getSelectedFilters();
	
		if (sel_rows.length > 0) {
	
			var ok = confirm(__("Remove selected filters?"));
	
			if (ok) {
				notify_progress("Removing selected filters...");
		
				var query = "?op=pref-filters&subop=remove&ids="+
					param_escape(sel_rows.toString());
	
				new Ajax.Request("backend.php",	{
						parameters: query,
						onComplete: function(transport) {
								filterlist_callback2(transport);
					} });
	
			}
		} else {
			alert(__("No filters are selected."));
		}

	} catch (e) {
		exception_error("removeSelectedFilters", e);
	}

	return false;
}


function removeSelectedFeeds() {

	try {

		var sel_rows = getSelectedFeeds();
	
		if (sel_rows.length > 0) {
	
			var ok = confirm(__("Unsubscribe from selected feeds?"));
	
			if (ok) {
	
				notify_progress("Unsubscribing from selected feeds...", true);
		
				var query = "?op=pref-feeds&subop=remove&ids="+
					param_escape(sel_rows.toString());

				debug(query);

				new Ajax.Request("backend.php",	{
					parameters: query,
					onComplete: function(transport) {
						updateFeedList();
						} });
			}
	
		} else {
			alert(__("No feeds are selected."));
		}

	} catch (e) {
		exception_error("removeSelectedFeeds", e);
	}
	
	return false;
}

function clearSelectedFeeds() {

	var sel_rows = getSelectedFeeds();

	if (sel_rows.length > 1) {
		alert(__("Please select only one feed."));
		return;
	}

	if (sel_rows.length > 0) {

		var ok = confirm(__("Erase all non-starred articles in selected feed?"));

		if (ok) {
			notify_progress("Clearing selected feed...");
			clearFeedArticles(sel_rows[0]);
		}

	} else {

		alert(__("No feeds are selected."));

	}
	
	return false;
}

function purgeSelectedFeeds() {

	var sel_rows = getSelectedFeeds();

	if (sel_rows.length > 0) {

		var pr = prompt(__("How many days of articles to keep (0 - use default)?"), "0");

		if (pr != undefined) {
			notify_progress("Purging selected feed...");

			var query = "?op=rpc&subop=purge&ids="+
				param_escape(sel_rows.toString()) + "&days=" + pr;

			debug(query);

			new Ajax.Request("prefs.php",	{
				parameters: query,
				onComplete: function(transport) {
					notify('');
				} });
		}

	} else {

		alert(__("No feeds are selected."));

	}
	
	return false;
}

function removeSelectedPrefProfiles() {

	var sel_rows = getSelectedFeedCats();

	if (sel_rows.length > 0) {

		var ok = confirm(__("Remove selected profiles? Active and default profiles will not be removed."));

		if (ok) {
			notify_progress("Removing selected profiles...");
	
			var query = "?op=rpc&subop=remprofiles&ids="+
				param_escape(sel_rows.toString());

			new Ajax.Request("backend.php",	{
				parameters: query,
				onComplete: function(transport) {
					editProfiles();
				} });
		}

	} else {
		alert(__("No profiles selected."));
	}

	return false;
}

function removeSelectedFeedCats() {

	var sel_rows = getSelectedFeedCats();

	if (sel_rows.length > 0) {

		var ok = confirm(__("Remove selected categories?"));

		if (ok) {
			notify_progress("Removing selected categories...");
	
			var query = "?op=pref-feeds&subop=editCats&action=remove&ids="+
				param_escape(sel_rows.toString());

			new Ajax.Request("backend.php",	{
				parameters: query,
				onComplete: function(transport) {
					infobox_feed_cat_callback2(transport);
				} });

		}

	} else {

		alert(__("No categories are selected."));

	}

	return false;
}

function feedEditCancel() {

	closeInfoBox();

	selectPrefRows('feed', false); // cleanup feed selection

	return false;
}

function feedEditSave() {

	try {
	
		// FIXME: add parameter validation

		var query = Form.serialize("edit_feed_form");

		notify_progress("Saving feed...");

		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function(transport) { 
				feedlist_callback2(transport); 
			} });

		closeInfoBox();

		return false;

	} catch (e) {
		exception_error("feedEditSave", e);
	} 
}

function userEditCancel() {

	selectPrefRows('user', false); // cleanup feed selection
	closeInfoBox();

	return false;
}

function filterEditCancel() {

	try {
		selectPrefRows('filter', false); // cleanup feed selection
	} catch (e) { }

	closeInfoBox();

	return false;
}

function userEditSave() {

	try {

		var login = document.forms["user_edit_form"].login.value;
	
		if (login.length == 0) {
			alert(__("Login field cannot be blank."));
			return;
		}
		
		notify_progress("Saving user...");
	
		closeInfoBox();
	
		var query = Form.serialize("user_edit_form");
		
		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function(transport) { 
				userlist_callback2(transport); 
			} });
	
	} catch (e) {
		exception_error("userEditSave", e);
	}

	return false;

}


function filterEditSave() {

	try {

		notify_progress("Saving filter...");
	
		var query = "?" + Form.serialize("filter_edit_form");
	
		closeInfoBox();

		new Ajax.Request("backend.php",	{
				parameters: query,
				onComplete: function(transport) {
						filterlist_callback2(transport);
			} });

	} catch (e) {
		exception_error("filterEditSave", e);
	}

	return false;
}


function editSelectedUser() {
	var rows = getSelectedUsers();

	if (rows.length == 0) {
		alert(__("No users are selected."));
		return;
	}

	if (rows.length > 1) {
		alert(__("Please select only one user."));
		return;
	}

	notify("");

	editUser(rows[0]);
}

function resetSelectedUserPass() {

	try {

		var rows = getSelectedUsers();
	
		if (rows.length == 0) {
			alert(__("No users are selected."));
			return;
		}
	
		if (rows.length > 1) {
			alert(__("Please select only one user."));
			return;
		}
	
		var ok = confirm(__("Reset password of selected user?"));
	
		if (ok) {
			notify_progress("Resetting password for selected user...");
		
			var id = rows[0];
		
			var query = "?op=pref-users&subop=resetPass&id=" +
				param_escape(id);
	
			new Ajax.Request("backend.php", {
				parameters: query,
				onComplete: function(transport) { 
					userlist_callback2(transport); 
				} });
	
		}

	} catch (e) {
		exception_error("resetSelectedUserPass", e);
	}
}

function selectedUserDetails() {

	try {

		var rows = getSelectedUsers();
	
		if (rows.length == 0) {
			alert(__("No users are selected."));
			return;
		}
	
		if (rows.length > 1) {
			alert(__("Please select only one user."));
			return;
		}
	
		notify_progress("Loading, please wait...");
	
		var id = rows[0];
	
		var query = "?op=pref-users&subop=user-details&id=" + id;

		new Ajax.Request("backend.php",	{
			parameters: query,
			onComplete: function(transport) {
					infobox_callback2(transport);
				} });
	} catch (e) {
		exception_error("selectedUserDetails", e);
	}
}


function editSelectedFilter() {
	var rows = getSelectedFilters();

	if (rows.length == 0) {
		alert(__("No filters are selected."));
		return;
	}

	if (rows.length > 1) {
		alert(__("Please select only one filter."));
		return;
	}

	notify("");

	editFilter(rows[0]);

}


function editSelectedFeed() {
	var rows = getSelectedFeeds();

	if (rows.length == 0) {
		alert(__("No feeds are selected."));
		return;
	}

	if (rows.length > 1) {
		return editSelectedFeeds();
	}

	notify("");

	editFeed(rows[0]);

}

function editSelectedFeeds() {

	try {
		var rows = getSelectedFeeds();
	
		if (rows.length == 0) {
			alert(__("No feeds are selected."));
			return;
		}
	
		notify("");
	
		disableHotkeys();
	
		notify_progress("Loading, please wait...");
	
		var query = "?op=pref-feeds&subop=editfeeds&ids=" +
			param_escape(rows.toString());

		new Ajax.Request("backend.php",	{
			parameters: query,
			onComplete: function(transport) {
					infobox_callback2(transport);
				} });

	} catch (e) {
		exception_error("editSelectedFeeds", e);
	}
}

function piggie(enable) {
	if (enable) {
		debug("I LOVEDED IT!");
		var piggie = $("piggie");

		Element.show(piggie);
		Position.Center(piggie);
		Effect.Puff(piggie);

	}
}

function opmlImport() {
	
	var opml_file = $("opml_file");

	if (opml_file.value.length == 0) {
		alert(__("No OPML file to upload."));
		return false;
	} else {
		return true;
	}

	notify_progress("Importing, please wait...", true);
}

function updateFilterList(sort_key) {
	try {

		var filter_search = $("filter_search");
		var search = "";
		if (filter_search) { search = filter_search.value; }
	
		var query = "?op=pref-filters&sort=" + 
			param_escape(sort_key) + 
			"&search=" + param_escape(search);

		new Ajax.Request("backend.php",	{
				parameters: query,
				onComplete: function(transport) {
						filterlist_callback2(transport);
			} });

	} catch (e) {
		exception_error("updateFilterList", e);
	}

}

function updateLabelList(sort_key) {

	try {

		var label_search = $("label_search");
		var search = "";
		if (label_search) { search = label_search.value; }
	
		var query = "?op=pref-labels&sort=" + 
			param_escape(sort_key) +
			"&search=" + param_escape(search);
	
		new Ajax.Request("backend.php",	{
			parameters: query,
			onComplete: function(transport) {
				labellist_callback2(transport);
			} });

	} catch (e) {
		exception_error("updateLabelList", e);
	}
}

function updatePrefsList() {

	var query = "?op=pref-prefs";

	new Ajax.Request("backend.php", {
		parameters: query,
		onComplete: function(transport) { 
			prefslist_callback2(transport); 
		} });

}

function selectTab(id, noupdate, subop) {

//	alert(id);

	if (!id) id = active_tab;

	try {

		try {
			if (id != active_tab) {
				var c = $('prefContent');	
				c.scrollTop = 0;
			}
		} catch (e) { };

		if (!noupdate) {

			debug("selectTab: " + id + "(NU: " + noupdate + ")");
	
			notify_progress("Loading, please wait...");
	
			// close active infobox if needed
			closeInfoBox();
	
			// clean up all current selections, just in case
			active_feed_cat = false;

//			Effect.Fade("prefContent", {duration: 1, to: 0.01, 
//				queue: { position:'end', scope: 'FEED_TAB', limit: 1 } } );

			if (id == "feedConfig") {
				updateFeedList();
			} else if (id == "filterConfig") {
				updateFilterList();
			} else if (id == "labelConfig") {
				updateLabelList();
			} else if (id == "genConfig") {
				updatePrefsList();
			} else if (id == "userConfig") {
				updateUsersList();
			}
		}

		/* clean selection from all tabs */
	
		var tabs_holder = $("prefTabs");
		var tab = tabs_holder.firstChild;

		while (tab) {
			if (tab.className && tab.className.match("prefsTabSelected")) {
				tab.className = "prefsTab";
			}
			tab = tab.nextSibling;
		}

		/* mark new tab as selected */

		tab = $(id + "Tab");
	
		if (tab) {
			if (!tab.className.match("Selected")) {
				tab.className = tab.className + "Selected";
			}
		}
	
		active_tab = id;

	} catch (e) {
		exception_error("selectTab", e);
	}
}

function backend_sanity_check_callback2(transport) {

	try {

		if (sanity_check_done) {
			fatalError(11, "Sanity check request received twice. This can indicate "+
		      "presence of Firebug or some other disrupting extension. "+
				"Please disable it and try again.");
			return;
		}

		if (!transport.responseXML) {
			fatalError(3, "Sanity Check: Received reply is not XML", 
				transport.responseText);
			return;
		}

		var reply = transport.responseXML.firstChild.firstChild;

		if (!reply) {
			fatalError(3, "Sanity Check: Invalid RPC reply", transport.responseText);
			return;
		}

		var error_code = reply.getAttribute("error-code");
	
		if (error_code && error_code != 0) {
			return fatalError(error_code, reply.getAttribute("error-msg"));
		}

		debug("sanity check ok");

		var params = reply.nextSibling;

		if (params) {
			debug('reading init-params...');
			var param = params.firstChild;

			while (param) {
				var k = param.getAttribute("key");
				var v = param.getAttribute("value");
				debug(k + " => " + v);
				init_params[k] = v;					
				param = param.nextSibling;
			}
		}

		sanity_check_done = true;

		init_second_stage();

	} catch (e) {
		exception_error("backend_sanity_check_callback", e);
	}
}

function init_second_stage() {

	try {
		active_tab = getInitParam("prefs_active_tab");
		if (!$(active_tab+"Tab")) active_tab = "genConfig";
		if (!active_tab || active_tab == '0') active_tab = "genConfig";

		document.onkeydown = pref_hotkey_handler;
		document.onmousedown = mouse_down_handler;
		document.onmouseup = mouse_up_handler;

		var tab = getURLParam('tab');
		
		caller_subop = getURLParam('subop');

		if (getURLParam("subopparam")) {
			caller_subop = caller_subop + ":" + getURLParam("subopparam");
		}

		if (tab) {
			active_tab = tab;
		}

		if (navigator.userAgent.match("Opera")) {	
			setTimeout("selectTab()", 500);
		} else {
			selectTab(active_tab);
		}
		notify("");

		loading_set_progress(60);

		setTimeout("hotkey_prefix_timeout()", 5*1000);

	} catch (e) {
		exception_error("init_second_stage", e);
	}
}

function init() {

	try {
	
		if (getURLParam('debug')) {
			Element.show("debug_output");
			debug('debug mode activated');
		}

		loading_set_progress(30);

		var query = "?op=rpc&subop=sanityCheck";

		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function(transport) { 
				backend_sanity_check_callback2(transport);
			} });

	} catch (e) {
		exception_error("init", e);
	}
}

function validatePrefsReset() {
	try {
		var ok = confirm(__("Reset to defaults?"));

		if (ok) {

			var query = Form.serialize("pref_prefs_form");
			query = query + "&subop=reset-config";
			debug(query);

			new Ajax.Request("backend.php", {
				parameters: query,
				onComplete: function(transport) { 
					var msg = transport.responseText;
					if (msg.match("PREFS_THEME_CHANGED")) {
						window.location.reload();
					} else {
						notify_info(msg);
						selectTab();
					}
				} });

		}

	} catch (e) {
		exception_error("validatePrefsReset", e);
	}

	return false;

}

function selectPrefRows(kind, select) {

	if (kind) {
		var opbarid = false;	
		var nchk = false;
		var nrow = false;
		var lname = false;

		if (kind == "feed") {
			opbarid = "feedOpToolbar";
			nrow = "FEEDR-";
			nchk = "FRCHK-";			
			lname = "prefFeedList";
		} else if (kind == "fcat") {
			opbarid = "catOpToolbar";
			nrow = "FCATR-";
			nchk = "FCCHK-";
			lname = "prefFeedCatList";
		} else if (kind == "filter") {
			opbarid = "filterOpToolbar";
			nrow = "FILRR-";
			nchk = "FICHK-";
			lname = "prefFilterList";
		} else if (kind == "label") {
			opbarid = "labelOpToolbar";
			nrow = "LILRR-";
			nchk = "LICHK-";
			lname = "prefLabelList";
		} else if (kind == "user") {
			opbarid = "userOpToolbar";
			nrow = "UMRR-";
			nchk = "UMCHK-";
			lname = "prefUserList";
		} else if (kind == "fbrowse") {
			opbarid = "browseOpToolbar";
			nrow = "FBROW-";
			nchk = "FBCHK-";
			lname = "browseFeedList";
		}

		if (opbarid) {
			selectTableRowsByIdPrefix(lname, nrow, nchk, select);
		}

	} 
}


function toggleSelectPrefRow(sender, kind) {

	toggleSelectRow(sender);

	if (kind) {
		var opbarid = false;	
		var nsel = -1;
		
		if (kind == "feed") {
			opbarid = "feedOpToolbar";
			nsel = getSelectedFeeds();
		} else if (kind == "fcat") {
			opbarid = "catOpToolbar";
			nsel = getSelectedFeedCats();
		} else if (kind == "filter") {
			opbarid = "filterOpToolbar";
			nsel = getSelectedFilters();
		} else if (kind == "label") {
			opbarid = "labelOpToolbar";
			nsel = getSelectedLabels();
		} else if (kind == "user") {
			opbarid = "userOpToolbar";
			nsel = getSelectedUsers();
		}

	} 
}

function toggleSelectFBListRow(sender) {
	toggleSelectListRow(sender);
}

var seq = "";

function pref_hotkey_handler(e) {
	try {

		var keycode;
		var shift_key = false;

		var cmdline = $('cmdline');

		try {
			shift_key = e.shiftKey;
		} catch (e) {

		}

		if (window.event) {
			keycode = window.event.keyCode;
		} else if (e) {
			keycode = e.which;
		}

		var keychar = String.fromCharCode(keycode);

		if (keycode == 27) { // escape
			if (Element.visible("hotkey_help_overlay")) {
				Element.hide("hotkey_help_overlay");
			}
			colorPickerHideAll();
			hotkey_prefix = false;
			closeInfoBox();
		} 

		if (!hotkeys_enabled) {
			debug("hotkeys disabled");
			return;
		}

		if (keycode == 16) return; // ignore lone shift

		if ((keycode == 67 || keycode == 71) && !hotkey_prefix) {
			hotkey_prefix = keycode;

			var date = new Date();
			var ts = Math.round(date.getTime() / 1000);

			hotkey_prefix_pressed = ts;

			cmdline.innerHTML = keychar;
			Element.show(cmdline);

			debug("KP: PREFIX=" + keycode + " CHAR=" + keychar);
			return;
		}

		if (Element.visible("hotkey_help_overlay")) {
			Element.hide("hotkey_help_overlay");
		}

		if (keycode == 13 || keycode == 27) {
			seq = "";
		} else {
			seq = seq + "" + keycode;
		}

		/* Global hotkeys */

		Element.hide(cmdline);

		if (!hotkey_prefix) {

			if (keycode == 68 && shift_key) { // d
				if (!Element.visible("debug_output")) {
					Element.show("debug_output");
					debug('debug mode activated');
				} else {
					Element.hide("debug_output");
				}
				return;
			}
	
			if ((keycode == 191 || keychar == '?') && shift_key) { // ?
				if (!Element.visible("hotkey_help_overlay")) {
					//Element.show("hotkey_help_overlay");
					Effect.Appear("hotkey_help_overlay", {duration : 0.3});
				} else {
					Element.hide("hotkey_help_overlay");
				}
				return false;
			}

			if (keycode == 191 || keychar == '/') { // /
				var search_boxes = new Array("label_search", 
					"feed_search", "filter_search", "user_search", "feed_browser_search");

				for (var i = 0; i < search_boxes.length; i++) {
					var elem = $(search_boxes[i]);
					if (elem) {
						focus_element(search_boxes[i]);
						return false;
					}
				}
			}
		}

		/* Prefix c */

		if (hotkey_prefix == 67) { // c
			hotkey_prefix = false;

			if (keycode == 70) { // f
				quickAddFilter();
				return false;
			}

			if (keycode == 83) { // s
				quickAddFeed();
				return false;
			}

			if (keycode == 85) { // u
				// no-op
			}

			if (keycode == 67) { // c
				editFeedCats();
				return false;
			}

			if (keycode == 84 && shift_key) { // T
				displayDlg('feedBrowser');
				return false;
			}

		}

		/* Prefix g */

		if (hotkey_prefix == 71) { // g

			hotkey_prefix = false;

			if (keycode == 49 && $("genConfigTab")) { // 1
				selectTab("genConfig");
				return false;
			}

			if (keycode == 50 && $("feedConfigTab")) { // 2
				selectTab("feedConfig");
				return false;
			}

			if (keycode == 51 && $("filterConfigTab")) { // 4
				selectTab("filterConfig");
				return false;
			}

			if (keycode == 52 && $("labelConfigTab")) { // 5
				selectTab("labelConfig");
				return false;
			}

			if (keycode == 53 && $("userConfigTab")) { // 6
				selectTab("userConfig");
				return false;
			}

			if (keycode == 88) { // x
				return gotoMain();
			}

		}

		if ($("piggie")) {
	
			if (seq.match("807371717369")) {
				seq = "";
				piggie(true);
			} else {
				piggie(false);
			}
		}

		if (hotkey_prefix) {
			debug("KP: PREFIX=" + hotkey_prefix + " CODE=" + keycode + " CHAR=" + keychar);
		} else {
			debug("KP: CODE=" + keycode + " CHAR=" + keychar);
		}

	} catch (e) {
		exception_error("pref_hotkey_handler", e);
	}
}

function editFeedCats() {
	try {
		var query = "?op=pref-feeds&subop=editCats";

		new Ajax.Request("backend.php",	{
			parameters: query,
			onComplete: function(transport) {
				infobox_feed_cat_callback2(transport);
			} });
	} catch (e) {
		exception_error("editFeedCats", e);
	}
}

function showFeedsWithErrors() {
	displayDlg('feedUpdateErrors');
}

function changeUserPassword() {

	try {

		var f = document.forms["change_pass_form"];

		if (f) {
			if (f.OLD_PASSWORD.value == "") {
				new Effect.Highlight(f.OLD_PASSWORD);
				notify_error("Old password cannot be blank.");
				return false;
			}

			if (f.NEW_PASSWORD.value == "") {
				new Effect.Highlight(f.NEW_PASSWORD);
				notify_error("New password cannot be blank.");
				return false;
			}

			if (f.CONFIRM_PASSWORD.value == "") {
				new Effect.Highlight(f.CONFIRM_PASSWORD);
				notify_error("Entered passwords do not match.");
				return false;
			}

			if (f.CONFIRM_PASSWORD.value != f.NEW_PASSWORD.value) {
				new Effect.Highlight(f.CONFIRM_PASSWORD);
				new Effect.Highlight(f.NEW_PASSWORD);
				notify_error("Entered passwords do not match.");
				return false;
			}

		}

		var query = Form.serialize("change_pass_form");
	
		notify_progress("Changing password...");

		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function(transport) { 
				if (transport.responseText.indexOf("ERROR: ") == 0) {
					notify_error(transport.responseText.replace("ERROR: ", ""));
				} else {
					notify_info(transport.responseText);
					var warn = $("default_pass_warning");
					if (warn) warn.style.display = "none";
				}
		
				document.forms['change_pass_form'].reset();
			} });


	} catch (e) {
		exception_error("changeUserPassword", e);
	}
	
	return false;
}

function changeUserEmail() {

	try {

		var query = Form.serialize("change_email_form");
	
		notify_progress("Trying to change e-mail...");
	
		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function(transport) { 
				notify_callback2(transport); 
			} });

	} catch (e) {
		exception_error("changeUserPassword", e);
	}
	
	return false;

}

function feedlistToggleSLAT() {
	notify_progress("Loading, please wait...");
	updateFeedList()
}

function pubRegenKey() {

	try {
		var ok = confirm(__("Replace current publishing address with a new one?"));
	
		if (ok) {
	
			notify_progress("Trying to change address...", true);
	
			var query = "?op=rpc&subop=regenPubKey";
	
			new Ajax.Request("backend.php", {
				parameters: query,
				onComplete: function(transport) {
						var new_link = transport.responseXML.getElementsByTagName("link")[0];
	
						var e = $('pub_feed_url');
	
						if (new_link) {
							e.href = new_link.firstChild.nodeValue;
							e.innerHTML = new_link.firstChild.nodeValue;
	
							new Effect.Highlight(e);

							notify('');
	
						} else {
							notify_error("Could not change feed URL.");
						}
				} });
		}
	} catch (e) {
		exception_error("pubRegenKey", e);
	}
	return false;
}

function validatePrefsSave() {
	try {

		var ok = confirm(__("Save current configuration?"));

		if (ok) {

			var query = Form.serialize("pref_prefs_form");
			query = query + "&subop=save-config";
			debug(query);

			new Ajax.Request("backend.php", {
				parameters: query,
				onComplete: function(transport) { 
					var msg = transport.responseText;
					if (msg.match("PREFS_THEME_CHANGED")) {
						window.location.reload();
					} else {
						notify_info(msg);
					}
			} });

		}

	} catch (e) {
		exception_error("validatePrefsSave", e);
	}

	return false;
}

function feedActionChange() {
	try {
		var chooser = $("feedActionChooser");
		var opid = chooser[chooser.selectedIndex].value;

		chooser.selectedIndex = 0;
		feedActionGo(opid);
	} catch (e) {
		exception_error("feedActionChange", e);
	}
}

function feedActionGo(op) {	
	try {
		if (op == "facEdit") {

			var rows = getSelectedFeeds();

			if (rows.length > 1) {
				editSelectedFeeds();
			} else {
				editSelectedFeed();
			}
		}

		if (op == "facClear") {
			clearSelectedFeeds();
		}

		if (op == "facPurge") {
			purgeSelectedFeeds();
		}

		if (op == "facEditCats") {
			editFeedCats();
		}

		if (op == "facRescore") {
			rescoreSelectedFeeds();
		}

		if (op == "facUnsubscribe") {
			removeSelectedFeeds();
		}

	} catch (e) {
		exception_error("feedActionGo", e);

	}
}

function clearFeedArticles(feed_id) {

	notify_progress("Clearing feed...");

	var query = "?op=pref-feeds&quiet=1&subop=clear&id=" + feed_id;

	new Ajax.Request("backend.php",	{
		parameters: query,
		onComplete: function(transport) {
				notify('');
			} });

	return false;
}

function rescoreSelectedFeeds() {

	var sel_rows = getSelectedFeeds();

	if (sel_rows.length > 0) {

		//var ok = confirm(__("Rescore last 100 articles in selected feeds?"));
		var ok = confirm(__("Rescore articles in selected feeds?"));

		if (ok) {
			notify_progress("Rescoring selected feeds...", true);
	
			var query = "?op=pref-feeds&subop=rescore&quiet=1&ids="+
				param_escape(sel_rows.toString());

			new Ajax.Request("backend.php",	{
				parameters: query,
				onComplete: function(transport) {
						notify_callback2(transport);
			} });

		}
	} else {
		alert(__("No feeds are selected."));
	}

	return false;
}

function rescore_all_feeds() {
	var ok = confirm(__("Rescore all articles? This operation may take a lot of time."));

	if (ok) {
		notify_progress("Rescoring feeds...", true);

		var query = "?op=pref-feeds&subop=rescoreAll&quiet=1";

		new Ajax.Request("backend.php",	{
			parameters: query,
			onComplete: function(transport) {
					notify_callback2(transport);
		} });
	}
}

function removeFilter(id, title) {

	try {

		var msg = __("Remove filter %s?").replace("%s", title);
	
		var ok = confirm(msg);
	
		if (ok) {
			closeInfoBox();
	
			notify_progress("Removing filter...");
		
			var query = "?op=pref-filters&subop=remove&ids="+
				param_escape(id);

			new Ajax.Request("backend.php",	{
				parameters: query,
				onComplete: function(transport) {
						filterlist_callback2(transport);
			} });

		}

	} catch (e) {
		exception_error("removeFilter", e);
	}

	return false;
}

/*function unsubscribeFeed(id, title) {

	try {

		var msg = __("Unsubscribe from %s?").replace("%s", title);
	
		var ok = confirm(msg);
	
		if (ok) {
			closeInfoBox();
	
			notify_progress("Removing feed...");
		
			var query = "?op=pref-feeds&subop=remove&ids="+
				param_escape(id);
	
			new Ajax.Request("backend.php", {
					parameters: query,
					onComplete: function(transport) {
							feedlist_callback2(transport);
				} });
		}
	
	} catch (e) {
		exception_error("unsubscribeFeed", e);
	}

	return false;

} */

function feedsEditSave() {
	try {

		var ok = confirm(__("Save changes to selected feeds?"));

		if (ok) {

			var f = document.forms["batch_edit_feed_form"];

			var query = Form.serialize("batch_edit_feed_form");

			/* Form.serialize ignores unchecked checkboxes */

			if (!query.match("&rtl_content=") && 
					f.rtl_content.disabled == false) {
				query = query + "&rtl_content=false";
			}

			if (!query.match("&private=") && 
					f.private.disabled == false) {
				query = query + "&private=false";
			}

			if (!query.match("&cache_images=") && 
					f.cache_images.disabled == false) {
				query = query + "&cache_images=false";
			}

			if (!query.match("&include_in_digest=") && 
					f.include_in_digest.disabled == false) {
				query = query + "&include_in_digest=false";
			}
	
			closeInfoBox();
	
			notify_progress("Saving feeds...");
	
			new Ajax.Request("backend.php", {
				parameters: query,
				onComplete: function(transport) { 
					feedlist_callback2(transport); 
				} });

		}

		return false;
	} catch (e) {
		exception_error("feedsEditSave", e);
	}
}

function batchFeedsToggleField(cb, elem, label) {
	try {
		var f = document.forms["batch_edit_feed_form"];
		var l = $(label);

		if (cb.checked) {
			f[elem].disabled = false;

			if (l) {
				l.className = "";
			};

//			new Effect.Highlight(f[elem], {duration: 1, startcolor: "#fff7d5",
//				queue: { position:'end', scope: 'BPEFQ', limit: 1 } } );

		} else {
			f[elem].disabled = true;

			if (l) {
				l.className = "insensitive";
			};

		}
	} catch (e) {
		exception_error("batchFeedsToggleField", e);
	}
}

function labelColorReset() {
	try {
		var labels = getSelectedLabels();

		var ok = confirm(__("Reset label colors to default?"));

		if (ok) {

			var query = "?op=pref-labels&subop=color-reset&ids="+
				param_escape(labels.toString());

			new Ajax.Request("backend.php", {
				parameters: query,
				onComplete: function(transport) {
						labellist_callback2(transport);
					} });
		}

	} catch (e) {
		exception_error("labelColorReset", e);
	}
}

function labelColorAsk(id, kind) {
	try {

		var p = null

		if (kind == "fg") {
			p = prompt(__("Please enter new label foreground color:"));
		} else {
			p = prompt(__("Please enter new label background color:"));
		}

		if (p != null) {

			var query = "?op=pref-labels&subop=color-set&kind=" + kind +
				"&ids="+	param_escape(id) + "&color=" + param_escape(p);

			selectPrefRows('label', false);

			var e = $("LICID-" + id);

			if (e) {		
				if (kind == "fg") {
					e.style.color = p
				} else {
					e.style.backgroundColor = p;
				}
			}

			new Ajax.Request("backend.php", { parameters: query });
		}

	} catch (e) {
		exception_error("labelColorReset", e);
	}
}


function colorPicker(id, fg, bg) {
	try {
		var picker = $("colorPicker-" + id);

		if (picker) Element.show(picker);

	} catch (e) {
		exception_error("colorPicker", e);
	}
}

function colorPickerHideAll() {
	try {
		if ($("prefLabelList")) {

			var elems = $("prefLabelList").getElementsByTagName("DIV");

			for (var i = 0; i < elems.length; i++) {
				if (elems[i].id && elems[i].id.match("colorPicker-")) {
					Element.hide(elems[i]);
				}
			}
		}

	} catch (e) {
		exception_error("colorPickerHideAll", e);
	}
}

function colorPickerDo(id, fg, bg) {
	try {

		var query = "?op=pref-labels&subop=color-set&kind=both"+
			"&ids=" + param_escape(id) + "&fg=" + param_escape(fg) + 
			"&bg=" + param_escape(bg);

		var e = $("LICID-" + id);

		if (e) {		
			e.style.color = fg;
			e.style.backgroundColor = bg;
		}

		new Ajax.Request("backend.php", { parameters: query });

	} catch (e) {
		exception_error("colorPickerDo", e);
	}
}

function colorPickerActive(b) {
	color_picker_active = b;
}

function mouse_down_handler(e) {
	try {

		/* do not prevent right click */
		if (e && e.button && e.button == 2) return;

		if (selection_disabled) {
			document.onselectstart = function() { return false; };
			return false;
		}

	} catch (e) {
		exception_error("mouse_down_handler", e);
	}
}

function mouse_up_handler(e) {
	try {
		mouse_is_down = false;

		if (!selection_disabled) {
			document.onselectstart = null;
		}

		if (!color_picker_active) {
			colorPickerHideAll();
		}

	} catch (e) {
		exception_error("mouse_up_handler", e);
	}
}

function inPreferences() {
	return true;
}

function editProfiles() {
	displayDlg('editPrefProfiles', false, function() {
		init_profile_inline_editor();			
			});
}

function activatePrefProfile() {

	var sel_rows = getSelectedFeedCats();

	if (sel_rows.length == 1) {

		var ok = confirm(__("Activate selected profile?"));

		if (ok) {
			notify_progress("Loading, please wait...");
	
			var query = "?op=rpc&subop=setprofile&id="+
				param_escape(sel_rows.toString());

			new Ajax.Request("backend.php",	{
				parameters: query,
				onComplete: function(transport) {
					window.location.reload();
				} });
		}

	} else {
		alert(__("Please choose a profile to activate."));
	}

	return false;
}

function opmlImportDone() {
	closeInfoBox();
	updateFeedList();
}

function opmlImportHandler(iframe) {
	try {
		var tmp = new Object();
		tmp.responseText = iframe.document.body.innerHTML;
		notify('');
		infobox_callback2(tmp);
	} catch (e) {
		exception_error("opml_import_handler", e);
	}
}

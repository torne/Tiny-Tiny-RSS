var init_params = new Array();

var hotkey_prefix = false;
var hotkey_prefix_pressed = false;

var seq = "";

function feedlist_callback2(transport) {
	try {
		dijit.byId('feedConfigTab').attr('content', transport.responseText);
		selectTab("feedConfig", true);
		notify("");
	} catch (e) {
		exception_error("feedlist_callback2", e);
	}
}

function filterlist_callback2(transport) {
	dijit.byId('filterConfigTab').attr('content', transport.responseText);
	notify("");
}

function labellist_callback2(transport) {
	try {
		dijit.byId('labelConfigTab').attr('content', transport.responseText);
		notify("");
	} catch (e) {
		exception_error("labellist_callback2", e);
	}
}

function userlist_callback2(transport) {
	try {
		dijit.byId('userConfigTab').attr('content', transport.responseText);

		notify("");
	} catch (e) {
		exception_error("userlist_callback2", e);
	}
}

function prefslist_callback2(transport) {
	try {
		dijit.byId('genConfigTab').attr('content', transport.responseText);

		notify("");
	} catch (e) {
		exception_error("prefslist_callback2", e);
	}
}

function notify_callback2(transport) {
	notify_info(transport.responseText);
}

function updateFeedList(sort_key) {
	new Ajax.Request("backend.php", {
		parameters: "?op=pref-feeds",
		onComplete: function(transport) {
			feedlist_callback2(transport);
		} });
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

function editUser(id, event) {

	try {
		if (!event || !event.ctrlKey) {

		notify_progress("Loading, please wait...");

		selectTableRows('prefUserList', 'none');
		selectTableRowById('UMRR-'+id, 'UMCHK-'+id, true);

		var query = "?op=pref-users&subop=edit&id=" +
			param_escape(id);

		new Ajax.Request("backend.php",	{
			parameters: query,
			onComplete: function(transport) {
					infobox_callback2(transport);
					document.forms['user_edit_form'].login.focus();
				} });

		} else if (event.ctrlKey) {
			var cb = $('UMCHK-' + id);
			cb.checked = !cb.checked;
			toggleSelectRow(cb);
		}

	} catch (e) {
		exception_error("editUser", e);
	}

}

function editFilter(id) {
	try {

		var query = "backend.php?op=pref-filters&subop=edit&id=" + param_escape(id);

		if (dijit.byId("filterEditDlg"))
			dijit.byId("filterEditDlg").destroyRecursive();

		dialog = new dijit.Dialog({
			id: "filterEditDlg",
			title: __("Edit Filter"),
			style: "width: 600px",
			removeFilter: function() {
				var title = this.attr('value').reg_exp;
				var msg = __("Remove filter %s?").replace("%s", title);

				if (confirm(msg)) {
					this.hide();

					notify_progress("Removing filter...");

					var id = this.attr('value').id;

					var query = "?op=pref-filters&subop=remove&ids="+
						param_escape(id);

					new Ajax.Request("backend.php",	{
						parameters: query,
						onComplete: function(transport) {
							updateFilterList();
						} });
				}
			},

			execute: function() {
				if (this.validate()) {

					var query = "?op=rpc&subop=verifyRegexp&reg_exp=" +
						param_escape(dialog.attr('value').reg_exp);

					notify_progress("Verifying regular expression...");

					new Ajax.Request("backend.php",	{
						parameters: query,
						onComplete: function(transport) {
							var reply = JSON.parse(transport.responseText);

							if (reply) {
								notify('');

								if (!reply['status']) {
									alert("Match regular expression seems to be invalid.");
									return;
								} else {
									notify_progress("Saving data...", true);

									console.log(dojo.objectToQuery(dialog.attr('value')));

									new Ajax.Request("backend.php", {
										parameters: dojo.objectToQuery(dialog.attr('value')),
										onComplete: function(transport) {
											dialog.hide();
											updateFilterList();
									}})
								}
							}
					}});
				}
			},
			href: query});

		dialog.show();


	} catch (e) {
		exception_error("editFilter", e);
	}
}

function getSelectedLabels() {
	var tree = dijit.byId("labelTree");
	var items = tree.model.getCheckedItems();
	var rv = [];

	items.each(function(item) {
		rv.push(tree.model.store.getValue(item, 'bare_id'));
	});

	return rv;
}

function getSelectedUsers() {
	return getSelectedTableRowIds("prefUserList");
}

function getSelectedFeeds() {
	var tree = dijit.byId("feedTree");
	var items = tree.model.getCheckedItems();
	var rv = [];

	items.each(function(item) {
		rv.push(tree.model.store.getValue(item, 'bare_id'));
	});

	return rv;
}

function getSelectedFilters() {
	var tree = dijit.byId("filterTree");
	var items = tree.model.getCheckedItems();
	var rv = [];

	items.each(function(item) {
		rv.push(tree.model.store.getValue(item, 'bare_id'));
	});

	return rv;

}

/* function getSelectedFeedCats() {
	return getSelectedTableRowIds("prefFeedCatList");
} */

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

			var ok = confirm(__("Remove selected users? Neither default admin nor your account will be removed."));

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

				console.log(query);

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

			console.log(query);

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

function userEditCancel() {
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

	editFeed(rows[0], {});

}

function editSelectedFeeds() {

	try {
		var rows = getSelectedFeeds();

		if (rows.length == 0) {
			alert(__("No feeds are selected."));
			return;
		}

		notify("");

		var query = "backend.php?op=pref-feeds&subop=editfeeds&ids=" +
			param_escape(rows.toString());

		if (dijit.byId("feedEditDlg"))
			dijit.byId("feedEditDlg").destroyRecursive();

		dialog = new dijit.Dialog({
			id: "feedEditDlg",
			title: __("Edit Multiple Feeds"),
			style: "width: 600px",
			getChildByName: function (name) {
				var rv = null
				this.getChildren().each(
					function(child) {
						if (child.name == name) {
							rv = child;
							return;
						}
					});
				return rv;
			},
			toggleField: function (checkbox, elem, label) {
				this.getChildByName(elem).attr('disabled', !checkbox.checked);

				if ($(label))
					if (checkbox.checked)
						$(label).removeClassName('insensitive');
					else
						$(label).addClassName('insensitive');

			},
			execute: function() {
				if (this.validate() && confirm(__("Save changes to selected feeds?"))) {
					var query = dojo.objectToQuery(this.attr('value'));

					/* Form.serialize ignores unchecked checkboxes */

					if (!query.match("&rtl_content=") &&
							this.getChildByName('rtl_content').attr('disabled') == false) {
						query = query + "&rtl_content=false";
					}

					if (!query.match("&private=") &&
							this.getChildByName('private').attr('disabled') == false) {
						query = query + "&private=false";
					}

					if (!query.match("&cache_images=") &&
							this.getChildByName('cache_images').attr('disabled') == false) {
						query = query + "&cache_images=false";
					}

					if (!query.match("&include_in_digest=") &&
							this.getChildByName('include_in_digest').attr('disabled') == false) {
						query = query + "&include_in_digest=false";
					}

					console.log(query);

					notify_progress("Saving data...", true);

					new Ajax.Request("backend.php", {
						parameters: query,
						onComplete: function(transport) {
							dialog.hide();
							updateFeedList();
					}})
				}
			},
			href: query});

		dialog.show();

	} catch (e) {
		exception_error("editSelectedFeeds", e);
	}
}

function piggie(enable) {
	if (enable) {
		console.log("I LOVEDED IT!");
		var piggie = $("piggie");

		Element.show(piggie);
		Position.Center(piggie);
		Effect.Puff(piggie);

	}
}

function opmlImportComplete(iframe) {
	try {
		if (!iframe.contentDocument.body.innerHTML) return false;

		notify('');

		if (dijit.byId('opmlImportDlg'))
			dijit.byId('opmlImportDlg').destroyRecursive();

		var content = iframe.contentDocument.body.innerHTML;

		dialog = new dijit.Dialog({
			id: "opmlImportDlg",
			title: __("OPML Import"),
			style: "width: 600px",
			onCancel: function() {
				updateFeedList();
			},
			content: content});

		dialog.show();

	} catch (e) {
		exception_error("opmlImportComplete", e);
	}
}

function opmlImport() {

	var opml_file = $("opml_file");

	if (opml_file.value.length == 0) {
		alert(__("Please choose an OPML file first."));
		return false;
	} else {
		notify_progress("Importing, please wait...", true);
		return true;
	}
}

function updateFilterList() {
	new Ajax.Request("backend.php",	{
		parameters: "?op=pref-filters",
		onComplete: function(transport) {
			filterlist_callback2(transport);
		} });
}

function updateLabelList() {
	new Ajax.Request("backend.php",	{
		parameters: "?op=pref-labels",
		onComplete: function(transport) {
			labellist_callback2(transport);
		} });
}

function updatePrefsList() {
	new Ajax.Request("backend.php", {
		parameters: "?op=pref-prefs",
		onComplete: function(transport) {
			prefslist_callback2(transport);
		} });
}

function selectTab(id, noupdate, subop) {
	try {
		if (!noupdate) {
			notify_progress("Loading, please wait...");

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

			var tab = dijit.byId(id + "Tab");
			dijit.byId("pref-tabs").selectChild(tab);

		}

	} catch (e) {
		exception_error("selectTab", e);
	}
}

function init_second_stage() {
	try {

		document.onkeydown = pref_hotkey_handler;
		loading_set_progress(50);
		notify("");

		dojo.addOnLoad(function() {
			var tab = getURLParam('tab');

			if (tab) {
			  	tab = dijit.byId(tab + "Tab");
				if (tab) dijit.byId("pref-tabs").selectChild(tab);
			}

			var subop = getURLParam('subop');

			if (subop == 'editFeed') {
				var param = getURLParam('subopparam');

				window.setTimeout('editFeed(' + param + ')', 100);
			}
		});

		setTimeout("hotkey_prefix_timeout()", 5*1000);

	} catch (e) {
		exception_error("init_second_stage", e);
	}
}

function init() {

	try {
		dojo.registerModulePath("lib", "..");
		dojo.registerModulePath("fox", "../..");

		dojo.require("lib.CheckBoxTree");
		dojo.require("fox.PrefFeedTree");
		dojo.require("fox.PrefFilterTree");
		dojo.require("fox.PrefLabelTree");

		dojo.parser.parse();

		dojo.addOnLoad(function() {
			loading_set_progress(50);

			new Ajax.Request("backend.php", {
				parameters: {op: "rpc", subop: "sanityCheck"},
					onComplete: function(transport) {
					backend_sanity_check_callback(transport);
				} });
		});

	} catch (e) {
		exception_error("init", e);
	}
}

function validatePrefsReset() {
	try {
		var ok = confirm(__("Reset to defaults?"));

		if (ok) {

			query = "?op=pref-prefs&subop=reset-config";
			console.log(query);

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


function pref_hotkey_handler(e) {
	try {
		if (e.target.nodeName == "INPUT") return;

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
			hotkey_prefix = false;
			closeInfoBox();
		}

		if (keycode == 16) return; // ignore lone shift
		if (keycode == 17) return; // ignore lone ctrl

		if ((keycode == 67 || keycode == 71) && !hotkey_prefix) {
			hotkey_prefix = keycode;

			var date = new Date();
			var ts = Math.round(date.getTime() / 1000);

			hotkey_prefix_pressed = ts;

			cmdline.innerHTML = keychar;
			Element.show(cmdline);

			console.log("KP: PREFIX=" + keycode + " CHAR=" + keychar);
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
						$(search_boxes[i]).focus();
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
				feedBrowser();
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
			if (seq.match("8073717369")) {
				seq = "";
				piggie(true);
			} else {
				piggie(false);
			}
		}

		if (hotkey_prefix) {
			console.log("KP: PREFIX=" + hotkey_prefix + " CODE=" + keycode + " CHAR=" + keychar);
		} else {
			console.log("KP: CODE=" + keycode + " CHAR=" + keychar);
		}

	} catch (e) {
		exception_error("pref_hotkey_handler", e);
	}
}

function editFeedCats() {
	try {
		var query = "backend.php?op=pref-feeds&subop=editCats";

		if (dijit.byId("feedCatEditDlg"))
			dijit.byId("feedCatEditDlg").destroyRecursive();

		dialog = new dijit.Dialog({
			id: "feedCatEditDlg",
			title: __("Feed Categories"),
			style: "width: 600px",
			getSelectedCategories: function() {
				return getSelectedTableRowIds("prefFeedCatList");
			},
			removeSelected: function() {
				var sel_rows = this.getSelectedCategories();

				if (sel_rows.length > 0) {
					var ok = confirm(__("Remove selected categories?"));

					if (ok) {
						notify_progress("Removing selected categories...", true);

						var query = "?op=pref-feeds&subop=editCats&action=remove&ids="+
							param_escape(sel_rows.toString());

						new Ajax.Request("backend.php",	{
							parameters: query,
							onComplete: function(transport) {
								notify('');
								dialog.attr('content', transport.responseText);
								updateFeedList();
							} });

					}

				} else {
					alert(__("No categories are selected."));
				}
			},
			addCategory: function() {
				if (this.validate()) {
					notify_progress("Creating category...");

					var query = "?op=pref-feeds&subop=editCats&action=add&cat=" +
						param_escape(this.attr('value').newcat);

					new Ajax.Request("backend.php",	{
						parameters: query,
						onComplete: function(transport) {
							notify('');
							dialog.attr('content', transport.responseText);
							updateFeedList();
						} });
				}
			},
			execute: function() {
				if (this.validate()) {
				}
			},
			href: query});

		dialog.show();

	} catch (e) {
		exception_error("editFeedCats", e);
	}
}

function showFeedsWithErrors() {
	displayDlg('feedUpdateErrors');
}

function opmlRegenKey() {

	try {
		var ok = confirm(__("Replace current OPML publishing address with a new one?"));

		if (ok) {

			notify_progress("Trying to change address...", true);

			var query = "?op=rpc&subop=regenOPMLKey";

			new Ajax.Request("backend.php", {
				parameters: query,
				onComplete: function(transport) {
						var reply = JSON.parse(transport.responseText);

						var new_link = reply.link;

						var e = $('pub_opml_url');

						if (new_link) {
							e.href = new_link;
							e.innerHTML = new_link;

							new Effect.Highlight(e);

							notify('');

						} else {
							notify_error("Could not change feed URL.");
						}
				} });
		}
	} catch (e) {
		exception_error("opmlRegenKey", e);
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

function labelColorReset() {
	try {
		var labels = getSelectedLabels();

		if (labels.length > 0) {
			var ok = confirm(__("Reset selected labels to default colors?"));

			if (ok) {
				var query = "?op=pref-labels&subop=color-reset&ids="+
					param_escape(labels.toString());

				new Ajax.Request("backend.php", {
					parameters: query,
					onComplete: function(transport) {
						labellist_callback2(transport);
					} });
			}

		} else {
			alert(__("No labels are selected."));
		}

	} catch (e) {
		exception_error("labelColorReset", e);
	}
}


function inPreferences() {
	return true;
}

function editProfiles() {
	try {

		if (dijit.byId("profileEditDlg"))
			dijit.byId("profileEditDlg").destroyRecursive();

		var query = "backend.php?op=dlg&id=editPrefProfiles";

		dialog = new dijit.Dialog({
			id: "profileEditDlg",
			title: __("Settings Profiles"),
			style: "width: 600px",
			getSelectedProfiles: function() {
				return getSelectedTableRowIds("prefFeedProfileList");
			},
			removeSelected: function() {
				var sel_rows = this.getSelectedProfiles();

				if (sel_rows.length > 0) {
					var ok = confirm(__("Remove selected profiles? Active and default profiles will not be removed."));

					if (ok) {
						notify_progress("Removing selected profiles...", true);

						var query = "?op=rpc&subop=remprofiles&ids="+
							param_escape(sel_rows.toString());

						new Ajax.Request("backend.php",	{
							parameters: query,
							onComplete: function(transport) {
								notify('');
								editProfiles();
							} });

					}

				} else {
					alert(__("No profiles are selected."));
				}
			},
			activateProfile: function() {
				var sel_rows = this.getSelectedProfiles();

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
			},
			addProfile: function() {
				if (this.validate()) {
					notify_progress("Creating profile...", true);

					var query = "?op=rpc&subop=addprofile&title=" +
						param_escape(dialog.attr('value').newprofile);

					new Ajax.Request("backend.php",	{
						parameters: query,
						onComplete: function(transport) {
							notify('');
							editProfiles();
						} });

				}
			},
			execute: function() {
				if (this.validate()) {
				}
			},
			href: query});

		dialog.show();
	} catch (e) {
		exception_error("editProfiles", e);
	}
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

function clearFeedAccessKeys() {

	var ok = confirm(__("This will invalidate all previously generated feed URLs. Continue?"));

	if (ok) {
		notify_progress("Clearing URLs...");

		var query = "?op=rpc&subop=clearKeys";

		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function(transport) {
				notify_info("Generated URLs cleared.");
			} });
	}

	return false;
}

function resetFeedOrder() {
	try {
		notify_progress("Loading, please wait...");

		new Ajax.Request("backend.php", {
			parameters: "?op=pref-feeds&subop=feedsortreset",
			onComplete: function(transport) {
		  		updateFeedList();
			} });


	} catch (e) {
		exception_error("resetFeedOrder");
	}
}

function resetCatOrder() {
	try {
		notify_progress("Loading, please wait...");

		new Ajax.Request("backend.php", {
			parameters: "?op=pref-feeds&subop=catsortreset",
			onComplete: function(transport) {
		  		updateFeedList();
			} });


	} catch (e) {
		exception_error("resetCatOrder");
	}
}

function editCat(id, item, event) {
	try {
		var new_name = prompt(__('Rename category to:'), item.name);

		if (new_name && new_name != item.name) {

			notify_progress("Loading, please wait...");

			new Ajax.Request("backend.php", {
			parameters: {
				op: 'pref-feeds',
				subop: 'renamecat',
				id: id,
				title: new_name,
			},
			onComplete: function(transport) {
		  		updateFeedList();
			} });
		}

	} catch (e) {
		exception_error("editCat", e);
	}
}

function editLabel(id, event) {
	try {
		var query = "backend.php?op=pref-labels&subop=edit&id=" +
			param_escape(id);

		if (dijit.byId("labelEditDlg"))
			dijit.byId("labelEditDlg").destroyRecursive();

		dialog = new dijit.Dialog({
			id: "labelEditDlg",
			title: __("Label Editor"),
			style: "width: 600px",
			setLabelColor: function(id, fg, bg) {

				var kind = '';
				var color = '';

				if (fg && bg) {
					kind = 'both';
				} else if (fg) {
					kind = 'fg';
					color = fg;
				} else if (bg) {
					kind = 'bg';
					color = bg;
				}

				var query = "?op=pref-labels&subop=color-set&kind="+kind+
					"&ids=" + param_escape(id) + "&fg=" + param_escape(fg) +
					"&bg=" + param_escape(bg) + "&color=" + param_escape(color);

		//		console.log(query);

				var e = $("LICID-" + id);

				if (e) {
					if (fg) e.style.color = fg;
					if (bg) e.style.backgroundColor = bg;
				}

				new Ajax.Request("backend.php", { parameters: query });

				updateFilterList();
			},
			execute: function() {
				if (this.validate()) {
					var caption = this.attr('value').id;
					var caption = this.attr('value').caption;
					var fg_color = this.attr('value').fg_color;
					var bg_color = this.attr('value').bg_color;
					var query = dojo.objectToQuery(this.attr('value'));

					dijit.byId('labelTree').setNameById(id, caption);
					this.setLabelColor(id, fg_color, bg_color);
					this.hide();

					new Ajax.Request("backend.php", {
						parameters: query,
						onComplete: function(transport) {
					  		updateFilterList();
					} });
				}
			},
			href: query});

		dialog.show();

	} catch (e) {
		exception_error("editLabel", e);
	}
}

function clearTwitterCredentials() {
	try {
		var ok = confirm(__("This will clear your stored authentication information for Twitter. Continue?"));

		if (ok) {
			notify_progress("Clearing credentials...");

			var query = "?op=pref-feeds&subop=remtwitterinfo";

			new Ajax.Request("backend.php", {
				parameters: query,
				onComplete: function(transport) {
					notify_info("Twitter credentials have been cleared.");
					updateFeedList();
				} });
		}

	} catch (e) {
		exception_error("clearTwitterCredentials", e);
	}
}

function customizeCSS() {
	try {
		var query = "backend.php?op=dlg&id=customizeCSS";

		if (dijit.byId("cssEditDlg"))
			dijit.byId("cssEditDlg").destroyRecursive();

		dialog = new dijit.Dialog({
			id: "cssEditDlg",
			title: __("Customize stylesheet"),
			style: "width: 600px",
			execute: function() {
				notify_progress('Saving data...', true);
				new Ajax.Request("backend.php", {
					parameters: dojo.objectToQuery(this.attr('value')),
					onComplete: function(transport) {
						notify('');
						window.location.reload();
				} });

			},
			href: query});

		dialog.show();

	} catch (e) {
		exception_error("customizeCSS", e);
	}
}

function insertSSLserial(value) {
	try {
		dijit.byId("SSL_CERT_SERIAL").attr('value', value);
	} catch (e) {
		exception_error("insertSSLcerial", e);
	}
}

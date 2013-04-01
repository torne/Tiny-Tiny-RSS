var init_params = new Array();

var hotkey_prefix = false;
var hotkey_prefix_pressed = false;

var seq = "";

function notify_callback2(transport, sticky) {
	notify_info(transport.responseText, sticky);
}

function updateFeedList(sort_key) {

	var user_search = $("feed_search");
	var search = "";
	if (user_search) { search = user_search.value; }

	new Ajax.Request("backend.php", {
		parameters: "?op=pref-feeds&search=" + param_escape(search),
		onComplete: function(transport) {
			dijit.byId('feedConfigTab').attr('content', transport.responseText);
			selectTab("feedConfig", true);
			notify("");
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
				dijit.byId('userConfigTab').attr('content', transport.responseText);
				selectTab("userConfig", true)
				notify("");
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

		var query = "?op=pref-users&method=add&login=" +
			param_escape(login);

		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function(transport) {
				notify_callback2(transport);
				updateUsersList();
			} });

	} catch (e) {
		exception_error("addUser", e);
	}
}

function editUser(id, event) {

	try {
		notify_progress("Loading, please wait...");

		var query = "?op=pref-users&method=edit&id=" +
			param_escape(id);

		new Ajax.Request("backend.php",	{
			parameters: query,
			onComplete: function(transport) {
					infobox_callback2(transport);
					document.forms['user_edit_form'].login.focus();
				} });

	} catch (e) {
		exception_error("editUser", e);
	}

}

function editFilter(id) {
	try {

		var query = "backend.php?op=pref-filters&method=edit&id=" + param_escape(id);

		if (dijit.byId("feedEditDlg"))
			dijit.byId("feedEditDlg").destroyRecursive();

		if (dijit.byId("filterEditDlg"))
			dijit.byId("filterEditDlg").destroyRecursive();

		dialog = new dijit.Dialog({
			id: "filterEditDlg",
			title: __("Edit Filter"),
			style: "width: 600px",
			test: function() {
				var query = "backend.php?" + dojo.formToQuery("filter_edit_form") + "&savemode=test";

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
			removeFilter: function() {
				var msg = __("Remove filter?");

				if (confirm(msg)) {
					this.hide();

					notify_progress("Removing filter...");

					var id = this.attr('value').id;

					var query = "?op=pref-filters&method=remove&ids="+
						param_escape(id);

					new Ajax.Request("backend.php",	{
						parameters: query,
						onComplete: function(transport) {
							updateFilterList();
						} });
				}
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

					notify_progress("Saving data...", true);

					var query = dojo.formToQuery("filter_edit_form");

					console.log(query);

					new Ajax.Request("backend.php", {
						parameters: query,
						onComplete: function(transport) {
							dialog.hide();
							updateFilterList();
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
		if (item.id[0].match("FEED:"))
			rv.push(tree.model.store.getValue(item, 'bare_id'));
	});

	return rv;
}

function getSelectedCategories() {
	var tree = dijit.byId("feedTree");
	var items = tree.model.getCheckedItems();
	var rv = [];

	items.each(function(item) {
		if (item.id[0].match("CAT:"))
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

function removeSelectedLabels() {

	var sel_rows = getSelectedLabels();

	if (sel_rows.length > 0) {

		var ok = confirm(__("Remove selected labels?"));

		if (ok) {
			notify_progress("Removing selected labels...");

			var query = "?op=pref-labels&method=remove&ids="+
				param_escape(sel_rows.toString());

			new Ajax.Request("backend.php",	{
				parameters: query,
				onComplete: function(transport) {
						updateLabelList();
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

				var query = "?op=pref-users&method=remove&ids="+
					param_escape(sel_rows.toString());

				new Ajax.Request("backend.php", {
					parameters: query,
					onComplete: function(transport) {
						updateUsersList();
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

				var query = "?op=pref-filters&method=remove&ids="+
					param_escape(sel_rows.toString());

				new Ajax.Request("backend.php",	{
						parameters: query,
						onComplete: function(transport) {
							updateFilterList();
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

				var query = "?op=pref-feeds&method=remove&ids="+
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

			var query = "?op=rpc&method=purge&ids="+
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
				updateUsersList();
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

			var query = "?op=pref-users&method=resetPass&id=" +
				param_escape(id);

			new Ajax.Request("backend.php", {
				parameters: query,
				onComplete: function(transport) {
					notify_info(transport.responseText, true);
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

		var query = "?op=pref-users&method=userdetails&id=" + id;

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

function joinSelectedFilters() {
	var rows = getSelectedFilters();

	if (rows.length == 0) {
		alert(__("No filters are selected."));
		return;
	}

	var ok = confirm(__("Combine selected filters?"));

	if (ok) {
		notify_progress("Joining filters...");

		var query = "?op=pref-filters&method=join&ids="+
			param_escape(rows.toString());

		console.log(query);

		new Ajax.Request("backend.php",	{
			parameters: query,
			onComplete: function(transport) {
					updateFilterList();
			} });
	}
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

		notify_progress("Loading, please wait...");

		var query = "backend.php?op=pref-feeds&method=editfeeds&ids=" +
			param_escape(rows.toString());

		console.log(query);

		if (dijit.byId("feedEditDlg"))
			dijit.byId("feedEditDlg").destroyRecursive();

		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function(transport) {

				notify("");

				var dialog = new dijit.Dialog({
					id: "feedEditDlg",
					title: __("Edit Multiple Feeds"),
					style: "width: 600px",
					getChildByName: function (name) {
						var rv = null;
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

							if (!query.match("&private=") &&
									this.getChildByName('private').attr('disabled') == false) {
								query = query + "&private=false";
							}

							try {
								if (!query.match("&cache_images=") &&
										this.getChildByName('cache_images').attr('disabled') == false) {
									query = query + "&cache_images=false";
								}
							} catch (e) { }

							try {
								if (!query.match("&hide_images=") &&
										this.getChildByName('hide_images').attr('disabled') == false) {
									query = query + "&hide_images=false";
								}
							} catch (e) { }

							if (!query.match("&include_in_digest=") &&
									this.getChildByName('include_in_digest').attr('disabled') == false) {
								query = query + "&include_in_digest=false";
							}

							if (!query.match("&always_display_enclosures=") &&
									this.getChildByName('always_display_enclosures').attr('disabled') == false) {
								query = query + "&always_display_enclosures=false";
							}

							if (!query.match("&mark_unread_on_update=") &&
									this.getChildByName('mark_unread_on_update').attr('disabled') == false) {
								query = query + "&mark_unread_on_update=false";
							}

							console.log(query);

							notify_progress("Saving data...", true);

							new Ajax.Request("backend.php", {
								parameters: query,
								onComplete: function(transport) {
									dialog.hide();
									updateFeedList();
							}});
						}
					},
					content: transport.responseText});

					dialog.show();

			} });

	} catch (e) {
		exception_error("editSelectedFeeds", e);
	}
}

function opmlImportComplete(iframe) {
	try {
		if (!iframe.contentDocument.body.innerHTML) return false;

		Element.show(iframe);

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
				updateFilterList();
				updateLabelList();
			},
			execute: function() {
				updateFeedList();
				updateFilterList();
				updateLabelList();
				this.hide();
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

		Element.show("upload_iframe");

		return true;
	}
}


function updateFilterList() {
	var user_search = $("filter_search");
	var search = "";
	if (user_search) { search = user_search.value; }

	new Ajax.Request("backend.php",	{
		parameters: "?op=pref-filters&search=" + param_escape(search),
		onComplete: function(transport) {
			dijit.byId('filterConfigTab').attr('content', transport.responseText);
			notify("");
		} });
}

function updateLabelList() {
	new Ajax.Request("backend.php",	{
		parameters: "?op=pref-labels",
		onComplete: function(transport) {
			dijit.byId('labelConfigTab').attr('content', transport.responseText);
			notify("");
		} });
}

function updatePrefsList() {
	new Ajax.Request("backend.php", {
		parameters: "?op=pref-prefs",
		onComplete: function(transport) {
			dijit.byId('genConfigTab').attr('content', transport.responseText);
			notify("");
		} });
}

function selectTab(id, noupdate, method) {
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

			var method = getURLParam('method');

			if (method == 'editFeed') {
				var param = getURLParam('methodparam');

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
		dojo.registerModulePath("fox", "../../js/");

		dojo.require("dijit.ColorPalette");
		dojo.require("dijit.Dialog");
		dojo.require("dijit.form.Button");
		dojo.require("dijit.form.CheckBox");
		dojo.require("dijit.form.DropDownButton");
		dojo.require("dijit.form.FilteringSelect");
		dojo.require("dijit.form.Form");
		dojo.require("dijit.form.RadioButton");
		dojo.require("dijit.form.Select");
		dojo.require("dijit.form.SimpleTextarea");
		dojo.require("dijit.form.TextBox");
		dojo.require("dijit.form.ValidationTextBox");
		dojo.require("dijit.InlineEditBox");
		dojo.require("dijit.layout.AccordionContainer");
		dojo.require("dijit.layout.BorderContainer");
		dojo.require("dijit.layout.ContentPane");
		dojo.require("dijit.layout.TabContainer");
		dojo.require("dijit.Menu");
		dojo.require("dijit.ProgressBar");
		dojo.require("dijit.ProgressBar");
		dojo.require("dijit.Toolbar");
		dojo.require("dijit.Tree");
		dojo.require("dijit.tree.dndSource");
		dojo.require("dojo.data.ItemFileWriteStore");

		dojo.require("lib.CheckBoxTree");
		dojo.require("fox.PrefFeedTree");
		dojo.require("fox.PrefFilterTree");
		dojo.require("fox.PrefLabelTree");

		dojo.parser.parse();

		dojo.addOnLoad(function() {
			loading_set_progress(50);

			new Ajax.Request("backend.php", {
				parameters: {op: "rpc", method: "sanityCheck"},
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

			query = "?op=pref-prefs&method=resetconfig";
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

		if (e.target.nodeName == "INPUT" || e.target.nodeName == "TEXTAREA") return;

		var keycode = false;
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
			hotkey_prefix = false;
		}

		if (keycode == 16) return; // ignore lone shift
		if (keycode == 17) return; // ignore lone ctrl

		if (!shift_key) keychar = keychar.toLowerCase();

		var hotkeys = getInitParam("hotkeys");

		if (!hotkey_prefix && hotkeys[0].indexOf(keychar) != -1) {

			var date = new Date();
			var ts = Math.round(date.getTime() / 1000);

			hotkey_prefix = keychar;
			hotkey_prefix_pressed = ts;

			cmdline.innerHTML = keychar;
			Element.show(cmdline);

			return true;
		}

		Element.hide(cmdline);

		var hotkey = keychar.search(/[a-zA-Z0-9]/) != -1 ? keychar : "(" + keycode + ")";
		hotkey = hotkey_prefix ? hotkey_prefix + " " + hotkey : hotkey;
		hotkey_prefix = false;

		var hotkey_action = false;
		var hotkeys = getInitParam("hotkeys");

		for (sequence in hotkeys[1]) {
			if (sequence == hotkey) {
				hotkey_action = hotkeys[1][sequence];
				break;
			}
		}

		switch (hotkey_action) {
		case "feed_subscribe":
			quickAddFeed();
			return false;
		case "create_label":
			addLabel();
			return false;
		case "create_filter":
			quickAddFilter();
			return false;
		case "help_dialog":
			//helpDialog("prefs");
			return false;
		default:
			console.log("unhandled action: " + hotkey_action + "; hotkey: " + hotkey);
		}

	} catch (e) {
		exception_error("hotkey_handler", e);
	}
}

function removeCategory(id, item) {
	try {

		var ok = confirm(__("Remove category %s? Any nested feeds would be placed into Uncategorized.").replace("%s", item.name));

		if (ok) {
			var query = "?op=pref-feeds&method=removeCat&ids="+
				param_escape(id);

			notify_progress("Removing category...");

			new Ajax.Request("backend.php",	{
				parameters: query,
				onComplete: function(transport) {
					notify('');
					updateFeedList();
				} });
			}

	} catch (e) {
		exception_error("removeCategory", e);
	}
}

function removeSelectedCategories() {

	var sel_rows = getSelectedCategories();

	if (sel_rows.length > 0) {

		var ok = confirm(__("Remove selected categories?"));

		if (ok) {
			notify_progress("Removing selected categories...");

			var query = "?op=pref-feeds&method=removeCat&ids="+
				param_escape(sel_rows.toString());

			new Ajax.Request("backend.php",	{
				parameters: query,
				onComplete: function(transport) {
						updateFeedList();
					} });

		}
	} else {
		alert(__("No categories are selected."));
	}

	return false;
}

function createCategory() {
	try {
		var title = prompt(__("Category title:"));

		if (title) {

			notify_progress("Creating category...");

			var query = "?op=pref-feeds&method=addCat&cat=" +
				param_escape(title);

			new Ajax.Request("backend.php",	{
				parameters: query,
				onComplete: function(transport) {
					notify('');
					updateFeedList();
				} });
		}

	} catch (e) {
		exception_error("createCategory", e);
	}
}

function showInactiveFeeds() {
	try {
		var query = "backend.php?op=pref-feeds&method=inactiveFeeds";

		if (dijit.byId("inactiveFeedsDlg"))
			dijit.byId("inactiveFeedsDlg").destroyRecursive();

		dialog = new dijit.Dialog({
			id: "inactiveFeedsDlg",
			title: __("Feeds without recent updates"),
			style: "width: 600px",
			getSelectedFeeds: function() {
				return getSelectedTableRowIds("prefInactiveFeedList");
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
		exception_error("showInactiveFeeds", e);
	}

}

function opmlRegenKey() {

	try {
		var ok = confirm(__("Replace current OPML publishing address with a new one?"));

		if (ok) {

			notify_progress("Trying to change address...", true);

			var query = "?op=rpc&method=regenOPMLKey";

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

	var query = "?op=pref-feeds&quiet=1&method=clear&id=" + feed_id;

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

			var query = "?op=pref-feeds&method=rescore&quiet=1&ids="+
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

		var query = "?op=pref-feeds&method=rescoreAll&quiet=1";

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
				var query = "?op=pref-labels&method=colorreset&ids="+
					param_escape(labels.toString());

				new Ajax.Request("backend.php", {
					parameters: query,
					onComplete: function(transport) {
						updateLabelList();
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

		var query = "backend.php?op=dlg&method=editPrefProfiles";

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

						var query = "?op=rpc&method=remprofiles&ids="+
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

						var query = "?op=rpc&method=setprofile&id="+
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

					var query = "?op=rpc&method=addprofile&title=" +
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

			var query = "?op=rpc&method=setprofile&id="+
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

		var query = "?op=rpc&method=clearKeys";

		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function(transport) {
				notify_info("Generated URLs cleared.");
			} });
	}

	return false;
}

function clearArticleAccessKeys() {

	var ok = confirm(__("This will invalidate all previously shared article URLs. Continue?"));

	if (ok) {
		notify_progress("Clearing URLs...");

		var query = "?op=rpc&method=clearArticleKeys";

		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function(transport) {
				notify_info("Shared URLs cleared.");
			} });
	}

	return false;
}

function resetFilterOrder() {
	try {
		notify_progress("Loading, please wait...");

		new Ajax.Request("backend.php", {
			parameters: "?op=pref-filters&method=filtersortreset",
			onComplete: function(transport) {
		  		updateFilterList();
			} });


	} catch (e) {
		exception_error("resetFilterOrder");
	}
}


function resetFeedOrder() {
	try {
		notify_progress("Loading, please wait...");

		new Ajax.Request("backend.php", {
			parameters: "?op=pref-feeds&method=feedsortreset",
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
			parameters: "?op=pref-feeds&method=catsortreset",
			onComplete: function(transport) {
		  		updateFeedList();
			} });


	} catch (e) {
		exception_error("resetCatOrder");
	}
}

function toggleHiddenFeedCats() {
	try {
		notify_progress("Loading, please wait...");

		new Ajax.Request("backend.php", {
			parameters: "?op=pref-feeds&method=togglehiddenfeedcats",
			onComplete: function(transport) {
		  		updateFeedList();
			} });

	} catch (e) {
		exception_error("toggleHiddenFeedCats");
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
				method: 'renamecat',
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
		var query = "backend.php?op=pref-labels&method=edit&id=" +
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

				var query = "?op=pref-labels&method=colorset&kind="+kind+
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


function customizeCSS() {
	try {
		var query = "backend.php?op=pref-prefs&method=customizeCSS";

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

function gotoExportOpml(filename, settings) {
	tmp = settings ? 1 : 0;
	document.location.href = "backend.php?op=opml&method=export&filename=" + filename + "&settings=" + tmp;
}


function batchSubscribe() {
	try {
		var query = "backend.php?op=dlg&method=batchSubscribe";

		// overlapping widgets
		if (dijit.byId("batchSubDlg")) dijit.byId("batchSubDlg").destroyRecursive();
		if (dijit.byId("feedAddDlg"))	dijit.byId("feedAddDlg").destroyRecursive();

		var dialog = new dijit.Dialog({
			id: "batchSubDlg",
			title: __("Batch subscribe"),
			style: "width: 600px",
			execute: function() {
				if (this.validate()) {
					console.log(dojo.objectToQuery(this.attr('value')));

					notify_progress(__("Subscribing to feeds..."), true);

					new Ajax.Request("backend.php", {
						parameters: dojo.objectToQuery(this.attr('value')),
						onComplete: function(transport) {
							notify("");
							updateFeedList();
							dialog.hide();
						} });
					}
			},
			href: query});

		dialog.show();
	} catch (e) {
		exception_error("batchSubscribe", e);
	}
}


function toggleAdvancedPrefs() {
	try {
		notify_progress("Loading, please wait...");

		new Ajax.Request("backend.php", {
			parameters: "?op=pref-prefs&method=toggleadvanced",
			onComplete: function(transport) {
				updatePrefsList();
			} });

	} catch (e) {
		exception_error("toggleAdvancedPrefs", e);
	}
}

function clearPluginData(name) {
	try {
		if (confirm(__("Clear stored data for this plugin?"))) {
			notify_progress("Loading, please wait...");

			new Ajax.Request("backend.php", {
				parameters: "?op=pref-prefs&method=clearplugindata&name=" + param_escape(name),
				onComplete: function(transport) {
					notify('');
					updatePrefsList();
				} });
		}
	} catch (e) {
		exception_error("clearPluginData", e);
	}
}

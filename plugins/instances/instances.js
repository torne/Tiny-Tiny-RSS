function addInstance() {
	try {
		var query = "backend.php?op=pluginhandler&plugin=instances&method=addInstance";

		if (dijit.byId("instanceAddDlg"))
			dijit.byId("instanceAddDlg").destroyRecursive();

		dialog = new dijit.Dialog({
			id: "instanceAddDlg",
			title: __("Link Instance"),
			style: "width: 600px",
			regenKey: function() {
				new Ajax.Request("backend.php", {
					parameters: "?op=rpc&method=genHash",
					onComplete: function(transport) {
						var reply = JSON.parse(transport.responseText);
						if (reply)
							dijit.byId('instance_add_key').attr('value', reply.hash);

					} });
			},
			execute: function() {
				if (this.validate()) {
					console.warn(dojo.objectToQuery(this.attr('value')));

					notify_progress('Saving data...', true);
					new Ajax.Request("backend.php", {
						parameters: dojo.objectToQuery(this.attr('value')),
						onComplete: function(transport) {
							dialog.hide();
							notify('');
							updateInstanceList();
					} });
				}
			},
			href: query,
		});

		dialog.show();

	} catch (e) {
		exception_error("addInstance", e);
	}
}

// *** INS ***

function updateInstanceList(sort_key) {
	new Ajax.Request("backend.php", {
		parameters: "?op=pref-instances&sort=" + param_escape(sort_key),
		onComplete: function(transport) {
			dijit.byId('instanceConfigTab').attr('content', transport.responseText);
			selectTab("instanceConfig", true);
			notify("");
		} });
}

function editInstance(id, event) {
	try {
		if (!event || !event.ctrlKey) {

		selectTableRows('prefInstanceList', 'none');
		selectTableRowById('LIRR-'+id, 'LICHK-'+id, true);

		var query = "backend.php?op=pref-instances&method=edit&id=" +
			param_escape(id);

		if (dijit.byId("instanceEditDlg"))
			dijit.byId("instanceEditDlg").destroyRecursive();

		dialog = new dijit.Dialog({
			id: "instanceEditDlg",
			title: __("Edit Instance"),
			style: "width: 600px",
			regenKey: function() {
				new Ajax.Request("backend.php", {
					parameters: "?op=rpc&method=genHash",
					onComplete: function(transport) {
						var reply = JSON.parse(transport.responseText);
						if (reply)
							dijit.byId('instance_edit_key').attr('value', reply.hash);

					} });
			},
			execute: function() {
				if (this.validate()) {
//					console.warn(dojo.objectToQuery(this.attr('value')));

					notify_progress('Saving data...', true);
					new Ajax.Request("backend.php", {
						parameters: dojo.objectToQuery(this.attr('value')),
						onComplete: function(transport) {
							dialog.hide();
							notify('');
							updateInstanceList();
					} });
				}
			},
			href: query,
		});

		dialog.show();

		} else if (event.ctrlKey) {
			var cb = $('LICHK-' + id);
			cb.checked = !cb.checked;
			toggleSelectRow(cb);
		}


	} catch (e) {
		exception_error("editInstance", e);
	}
}

function removeSelectedInstances() {
	try {
		var sel_rows = getSelectedInstances();

		if (sel_rows.length > 0) {

			var ok = confirm(__("Remove selected instances?"));

			if (ok) {
				notify_progress("Removing selected instances...");

				var query = "?op=pref-instances&method=remove&ids="+
					param_escape(sel_rows.toString());

				new Ajax.Request("backend.php", {
					parameters: query,
					onComplete: function(transport) {
						notify('');
						updateInstanceList();
					} });
			}

		} else {
			alert(__("No instances are selected."));
		}

	} catch (e) {
		exception_error("removeInstance", e);
	}
}

function editSelectedInstance() {
	var rows = getSelectedInstances();

	if (rows.length == 0) {
		alert(__("No instances are selected."));
		return;
	}

	if (rows.length > 1) {
		alert(__("Please select only one instance."));
		return;
	}

	notify("");

	editInstance(rows[0]);
}

function getSelectedInstances() {
	return getSelectedTableRowIds("prefInstanceList");
}



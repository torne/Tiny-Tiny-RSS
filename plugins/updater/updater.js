function updateSelf() {
	try {
		var query = "backend.php?op=pluginhandler&plugin=updater&method=updateSelf";

		if (dijit.byId("updateSelfDlg"))
			dijit.byId("updateSelfDlg").destroyRecursive();

		var dialog = new dijit.Dialog({
			id: "updateSelfDlg",
			title: __("Update Tiny Tiny RSS"),
			style: "width: 600px",
			closable: false,
			performUpdate: function(step) {
				dijit.byId("self_update_start_btn").attr("disabled", true);
				dijit.byId("self_update_stop_btn").attr("disabled", true);

				notify_progress("Loading, please wait...", true);
				new Ajax.Request("backend.php", {
				parameters: "?op=pluginhandler&plugin=updater&method=performUpdate&step=" + step +
					"&params=" + param_escape(JSON.stringify(dialog.attr("update-params"))),
				onComplete: function(transport) {
					try {
						rv = JSON.parse(transport.responseText);
						if (rv) {
							notify('');

							rv['log'].each(function(line) {
								$("self_update_log").innerHTML += "<li>" + line + "</li>";
							});

							dialog.attr("update-params", rv['params']);

							if (!rv['stop']) {
								window.setTimeout("dijit.byId('updateSelfDlg').performUpdate("+(step+1)+")", 500);
							} else {
								dijit.byId("self_update_stop_btn").attr("disabled", false);
							}

						} else {
							console.log(transport.responseText);
							notify_error("Received invalid data from server.");
						}

						dialog.attr("updated", true);
					} catch (e) {
						exception_error("updateSelf/inner", e);
					}
				} });
			},
			close: function() {
				if (dialog.attr("updated")) {
					window.location.reload();
				} else {
					dialog.hide();
				}
			},
			start: function() {
				if (prompt(__("Live updating is considered experimental. Backup your tt-rss directory before continuing. Please type 'yes' to continue.")) == 'yes') {
					dialog.performUpdate(0);
				}
			},
			href: query});

		dialog.show();
	} catch (e) {
		exception_error("batchSubscribe", e);
	}
}


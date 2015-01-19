function showTrgmRelated(id) {
	try {

		var query = "backend.php?op=pluginhandler&plugin=af_psql_trgm&method=showrelated&param=" + param_escape(id);

		if (dijit.byId("editNoteDlg"))
			dijit.byId("editNoteDlg").destroyRecursive();

		dialog = new dijit.Dialog({
			id: "editNoteDlg",
			title: __("Related articles"),
			style: "width: 600px",
			execute: function() {

			},
			href: query,
		});

		dialog.show();

	} catch (e) {
		exception_error("showTrgmRelated", e);
	}
}


function starredImportComplete(iframe) {
	try {
		if (!iframe.contentDocument.body.innerHTML) return false;

		Element.show(iframe);

		notify('');

		if (dijit.byId('starredImportDlg'))
			dijit.byId('starredImportDlg').destroyRecursive();

		var content = iframe.contentDocument.body.innerHTML;

		if (content) Element.hide(iframe);

		dialog = new dijit.Dialog({
			id: "starredImportDlg",
			title: __("OPML Import"),
			style: "width: 600px",
			onCancel: function() {
				Element.hide(iframe);
				this.hide();
			},
			execute: function() {
				Element.hide(iframe);
				this.hide();
			},
			content: content});

		dialog.show();

	} catch (e) {
		exception_error("starredImportComplete", e);
	}
}

function starredImport() {

	var starred_file = $("starred_file");

	if (starred_file.value.length == 0) {
		alert(__("Please choose a file first."));
		return false;
	} else {
		notify_progress("Importing, please wait...", true);

		Element.show("starred_upload_iframe");

		return true;
	}
}



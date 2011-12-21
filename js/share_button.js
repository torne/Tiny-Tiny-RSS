function shareArticle(id) {
	try {
		if (dijit.byId("shareArticleDlg"))
			dijit.byId("shareArticleDlg").destroyRecursive();

		var query = "backend.php?op=rpc&method=buttonPlugin&plugin=share&plugin_method=shareArticle&param=" + param_escape(id);

		dialog = new dijit.Dialog({
			id: "shareArticleDlg",
			title: __("Share article by URL"),
			style: "width: 600px",
			href: query});

		dialog.show();

	} catch (e) {
		exception_error("emailArticle", e);
	}
}



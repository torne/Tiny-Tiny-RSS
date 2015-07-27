function bayesTrain(id, train_up, event) {
	try {

		event.stopPropagation();

		var query = "backend.php?op=pluginhandler&plugin=af_sort_bayes&method=trainArticle&article_id=" + param_escape(id) +
			"&train_up=" + param_escape(train_up);

		notify_progress("Loading, please wait...");

		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function(transport) {
				notify(transport.responseText);
				updateScore(id);
			} });

	} catch (e) {
		exception_error("showTrgmRelated", e);
	}
}

function bayesClearDatabase() {
	try {

		if (confirm(__("Clear classifier database?"))) {

			var query = "backend.php?op=pluginhandler&plugin=af_sort_bayes&method=clearDatabase";

			new Ajax.Request("backend.php", {
				parameters: query,
				onComplete: function (transport) {
					notify(transport.responseText);
					bayesUpdateUI();
				}
			});
		}

	} catch (e) {
		exception_error("showTrgmRelated", e);
	}
}

function bayesUpdateUI() {
	try {

		var query = "backend.php?op=pluginhandler&plugin=af_sort_bayes&method=renderPrefsUI";

		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function (transport) {
				dijit.byId("af_sort_bayes_prefs").attr("content", transport.responseText);
			}
		});

	} catch (e) {
		exception_error("showTrgmRelated", e);
	}
}

function bayesShow(id) {
	try {
		if (dijit.byId("bayesShowDlg"))
			dijit.byId("bayesShowDlg").destroyRecursive();

		var query = "backend.php?op=pluginhandler&plugin=af_sort_bayes&method=showArticleStats&article_id=" + param_escape(id);

		dialog = new dijit.Dialog({
			id: "bayesShowDlg",
			title: __("Classifier information"),
			style: "width: 600px",
			href: query});

		dialog.show();

	} catch (e) {
		exception_error("shareArticle", e);
	}
}



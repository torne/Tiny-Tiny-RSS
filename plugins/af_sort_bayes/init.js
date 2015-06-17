function bayesTrain(id, train_up) {
	try {

		var query = "backend.php?op=pluginhandler&plugin=af_sort_bayes&method=trainArticle&article_id=" + param_escape(id) +
			"&train_up=" + train_up;

		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function(transport) {
				notify(transport.responseText);
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
				}
			});
		}

	} catch (e) {
		exception_error("showTrgmRelated", e);
	}
}


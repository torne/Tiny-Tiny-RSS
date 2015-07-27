function clearArticleAccessKeys() {

	var ok = confirm(__("This will invalidate all previously shared article URLs. Continue?"));

	if (ok) {
		notify_progress("Clearing URLs...");

		var query = "?op=pluginhandler&plugin=share&method=clearArticleKeys";

		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function(transport) {
				notify_info("Shared URLs cleared.");
			} });
	}

	return false;
}




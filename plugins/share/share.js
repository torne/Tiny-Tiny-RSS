function shareArticle(id) {
	try {
		if (dijit.byId("shareArticleDlg"))
			dijit.byId("shareArticleDlg").destroyRecursive();

		var query = "backend.php?op=pluginhandler&plugin=share&method=shareArticle&param=" + param_escape(id);

		dialog = new dijit.Dialog({
			id: "shareArticleDlg",
			title: __("Share article by URL"),
			style: "width: 600px",
			newurl: function() {

				var ok = confirm(__("Generate new share URL for this article?"));

				if (ok) {

					notify_progress("Trying to change URL...", true);

					var query = "op=pluginhandler&plugin=share&method=newkey&id=" + param_escape(id);

					new Ajax.Request("backend.php", {
						parameters: query,
						onComplete: function(transport) {
								var reply = JSON.parse(transport.responseText);
								var new_link = reply.link;

								var e = $('gen_article_url');

								if (new_link) {

									e.innerHTML = e.innerHTML.replace(/\&amp;key=.*$/,
										"&amp;key=" + new_link);

									e.href = e.href.replace(/\&key=.*$/,
										"&key=" + new_link);

									new Effect.Highlight(e);

									notify('');

								} else {
									notify_error("Could not change URL.");
								}
						} });

				}

			},
			unshare: function() {

				var ok = confirm(__("Remove sharing for this article?"));

				if (ok) {

					notify_progress("Trying to unshare...", true);

					var query = "op=pluginhandler&plugin=share&method=unshare&id=" + param_escape(id);

					new Ajax.Request("backend.php", {
						parameters: query,
						onComplete: function(transport) {
							notify("Article unshared.");
							dialog.hide();
						} });
				}

			},
			href: query});

		dialog.show();

	} catch (e) {
		exception_error("shareArticle", e);
	}
}



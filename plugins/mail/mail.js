function emailArticle(id) {
	try {
		if (!id) {
			var ids = getSelectedArticleIds2();

			if (ids.length == 0) {
				alert(__("No articles are selected."));
				return;
			}

			id = ids.toString();
		}

		if (dijit.byId("emailArticleDlg"))
			dijit.byId("emailArticleDlg").destroyRecursive();

		var query = "backend.php?op=pluginhandler&plugin=mail&method=emailArticle&param=" + param_escape(id);

		dialog = new dijit.Dialog({
			id: "emailArticleDlg",
			title: __("Forward article by email"),
			style: "width: 600px",
			execute: function() {
				if (this.validate()) {

					new Ajax.Request("backend.php", {
						parameters: dojo.objectToQuery(this.attr('value')),
						onComplete: function(transport) {

							var reply = JSON.parse(transport.responseText);

							var error = reply['error'];

							if (error) {
								alert(__('Error sending email:') + ' ' + error);
							} else {
								notify_info('Your message has been sent.');
								dialog.hide();
							}

					} });
				}
			},
			href: query});

		var tmph = dojo.connect(dialog, 'onLoad', function() {
	   	dojo.disconnect(tmph);

		   new Ajax.Autocompleter('emailArticleDlg_destination', 'emailArticleDlg_dst_choices',
			   "backend.php?op=pluginhandler&plugin=mail&method=completeEmails",
			   { tokens: '', paramName: "search" });
		});

		dialog.show();

	} catch (e) {
		exception_error("emailArticle", e);
	}
}



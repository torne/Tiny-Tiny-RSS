function editArticleNote(id) {
	try {

		var query = "backend.php?op=pluginhandler&plugin=note&method=edit&param=" + param_escape(id);

		if (dijit.byId("editNoteDlg"))
			dijit.byId("editNoteDlg").destroyRecursive();

		dialog = new dijit.Dialog({
			id: "editNoteDlg",
			title: __("Edit article note"),
			style: "width: 600px",
			execute: function() {
				if (this.validate()) {
					var query = dojo.objectToQuery(this.attr('value'));

					notify_progress("Saving article note...", true);

					new Ajax.Request("backend.php",	{
					parameters: query,
					onComplete: function(transport) {
						notify('');
						dialog.hide();

						var reply = JSON.parse(transport.responseText);

						cache_delete("article:" + id);

						var elem = $("POSTNOTE-" + id);

						if (elem) {
							Element.hide(elem);
							elem.innerHTML = reply.note;

							if (reply.raw_length != 0)
								new Effect.Appear(elem);
						}

					}});
				}
			},
			href: query,
		});

		dialog.show();

	} catch (e) {
		exception_error("editArticleNote", e);
	}
}


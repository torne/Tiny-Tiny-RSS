function embedOriginalArticle(id) {
	try {
		var hasSandbox = "sandbox" in document.createElement("iframe");

		if (!hasSandbox) {
			alert(__("Sorry, your browser does not support sandboxed iframes."));
			return;
		}

		var query = "op=pluginhandler&plugin=embed_original&method=getUrl&id=" +
			param_escape(id);

		var c = false;

		if (isCdmMode()) {
			c = $$("div#RROW-" + id + " div[class=cdmContentInner]")[0];
		} else if (id == getActiveArticleId()) {
			c = $$("div[class=postContent]")[0];
		}

		if (c) {
			var iframe = c.getElementsByClassName("embeddedContent")[0];

			if (iframe) {
				Element.show(c.firstChild);
				c.removeChild(iframe);

				if (isCdmMode()) {
					cdmScrollToArticleId(id, true);
				}

				return;
			}
		}

		new Ajax.Request("backend.php",	{
			parameters: query,
			onComplete: function(transport) {
				var ti = JSON.parse(transport.responseText);

				if (ti) {

					var iframe = new Element("iframe", {
						class: "embeddedContent",
						src: ti.url,
						sandbox: 'allow-scripts',
					});

					if (c) {
						Element.hide(c.firstChild);

						if (c.firstChild.nextSibling)
							c.insertBefore(iframe, c.firstChild.nextSibling);
						else
							c.appendChild(iframe);

						if (isCdmMode()) {
							cdmScrollToArticleId(id, true);
						}
					}
				}

			} });


	} catch (e) {
		exception_error("embedOriginalArticle", e);
	}
}

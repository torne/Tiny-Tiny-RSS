var _shorten_expanded_threshold = 900; //px, longer than css height so that we would only clip articles significantly longer than limit

function expandSizeWrapper(id) {
	try {
		var row = $(id);

		console.log(row);

		if (row) {
			var content = row.select(".contentSizeWrapper")[0];
			var link = row.select(".expandPrompt")[0];

			if (content) content.removeClassName("contentSizeWrapper");
			if (link) Element.hide(link);

		}
	} catch (e) {
		exception_error("expandSizeWrapper", e);
	}

	return false;

}

dojo.addOnLoad(function() {
	PluginHost.register(PluginHost.HOOK_ARTICLE_RENDERED_CDM, function(row) {
		if (getInitParam('cdm_expanded')) {

			window.setTimeout(function() {
				if (row) {
					if (row.offsetHeight >= _shorten_expanded_threshold) {
						var content = row.select(".cdmContentInner")[0];

						if (content) {
							content.innerHTML = "<div class='contentSizeWrapper'>" +
								content.innerHTML + "</div><button class='expandPrompt' onclick='return expandSizeWrapper(\""+row.id+"\")' "+
								"href='#'>" + __("Click to expand article") + "</button>";

						}
					}
				}
			}, 150);
		}
	});
});

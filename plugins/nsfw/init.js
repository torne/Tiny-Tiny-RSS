function nsfwShow(elem) {
	try {
		content = elem.parentNode.getElementsBySelector("div.nswf.content")[0];

		if (content) {
			Element.toggle(content);
		}

	} catch (e) {
		exception_error("nswfSHow", e);
	}
}

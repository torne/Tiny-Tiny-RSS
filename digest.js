var last_feeds = [];

function find_feed(feeds, feed_id) {
	try {
		for (var i = 0; i < feeds.length; i++) {
			if (feeds[i].id == feed_id)
				return feeds[i];
		}

		return false;

	} catch (e) {
		exception_error("find_feed", e);
	}
}

function add_feed_entry(feed) {
	try {
		var icon_part = "";

		if (feed.has_icon) 
			icon_part = "<img alt='zz' src='icons/" + feed.id + ".ico'/>";

		var tmp_html = "<li>" + 
			icon_part +
			feed.title +
			"<div class='unread-ctr'>" + feed.unread + "</div>" +	
			"</li>";

		$("feeds-content").innerHTML += tmp_html;

	} catch (e) {
		exception_error("add_feed_entry", e);
	}
}

function add_latest_entry(article) {
	try {

	} catch (e) {
		exception_error("add_latest_entry", e);
	}
}

function add_headline_entry(article, feed) {
	try {

		var icon_part = "";

		if (article.has_icon) 
			icon_part = "<img alt='zz' src='icons/" + article.feed_id + ".ico'/>";

		var tmp_html = "<li>" + 
			icon_part +
			"<a class='title'>" + article.title + "</a>" +
			"<div class='excerpt'>" + article.excerpt + "</div>" +
			"<div class='info'><a>" + feed.title + "</a> " + " @ " + 
				article.updated + "</div>" +
			"</li>";

		$("headlines-content").innerHTML += tmp_html;

	} catch (e) {
		exception_error("add_headline_entry", e);
	}
}

function digest_update(transport) {
	try {
		var feeds = transport.responseXML.getElementsByTagName('feeds')[0];
		var headlines = transport.responseXML.getElementsByTagName('headlines')[0];

		if (feeds) {
			last_feeds = feeds;

			feeds = eval("(" + feeds.firstChild.nodeValue + ")");

			for (var i = 0; i < feeds.length; i++) {
				add_feed_entry(feeds[i]);
			}
		}

		if (headlines) {
			headlines = eval("(" + headlines.firstChild.nodeValue + ")");

			for (var i = 0; i < headlines.length; i++) {
				add_headline_entry(headlines[i], find_feed(feeds, headlines[i].feed_id));
			}
		}

	} catch (e) {
		exception_error("digest_update", e);
	}
	}

function digest_init() {
	try {
		
		new Ajax.Request("backend.php",	{
			parameters: "backend.php?op=rpc&subop=digest-init",
			onComplete: function(transport) {
				digest_update(transport);
				} });

	} catch (e) {
		exception_error("digest_init", e);
	}
}

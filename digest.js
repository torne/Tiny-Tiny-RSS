var last_feeds = [];

var _active_feed_id = false;
var _active_feed_offset = false;
var _update_timeout = false;

function zoom(article_id) {
	try {
		var elem = $('A-' + article_id);

		if (elem) {
			var divs = elem.getElementsByTagName('DIV');
			
			for (var i = 0; i < divs.length; i++) {
				if (divs[i].className == 'excerpt') 
					Element.hide(divs[i]);

				if (divs[i].className == 'content') 
					Element.show(divs[i]);
			}
		}

		var query = "backend.php?op=rpc&subop=digest-mark&article_id=" + article_id;

		new Ajax.Request("backend.php",	{
			parameters: query, 
			onComplete: function(transport) {
					window.clearTimeout(_update_timeout);
					_update_timeout = window.setTimeout('update()', 1000);					
				} });

	} catch (e) {
		exception_error("zoom", e);
	}
}

function load_more() {
	try {
		var elem = $('MORE-PROMPT');

		if (elem) {
			elem.id = '';
			Element.hide(elem);
		}

		viewfeed(_active_feed_id, _active_feed_offset + 10);
	} catch (e) {
		exception_error("load_more", e);
	}
}

function update() {
	try {
		viewfeed(_active_feed_id, _active_feed_offset);
	} catch (e) {
		exception_error("update", e);
	}
}

function view(article_id, dismiss_only) {
	try {
		var elem = $('A-' + article_id);

		elem.id = '';

		//new Effect.Fade(elem, {duration : 0.3});

		Element.hide(elem);

		var query = "backend.php?op=rpc&subop=digest-mark&article_id=" + article_id;

		new Ajax.Request("backend.php",	{
			parameters: query, 
			onComplete: function(transport) {
					window.clearTimeout(_update_timeout);
					_update_timeout = window.setTimeout('update()', 1000);					
				} });

		return dismiss_only != true;
	} catch (e) {
		exception_error("view", e);
	}
}

function viewfeed(feed_id, offset) {
	try {

		if (!feed_id) feed_id = _active_feed_id;

		if (!offset) 
			offset = 0;
		else
			offset = _active_feed_offset + offset;

		var query = "backend.php?op=rpc&subop=digest-update&feed_id=" + feed_id +
				"&offset=" + offset;

		console.log(query);

		new Ajax.Request("backend.php",	{
			parameters: query, 
			onComplete: function(transport) {
				digest_update(transport, feed_id);
				_active_feed_id = feed_id;
				_active_feed_offset = offset;
				} });

	} catch (e) {
		exception_error("view", e);
	}
}

function find_article(articles, article_id) {
	try {
		for (var i = 0; i < articles.length; i++) {
			if (articles[i].id == article_id)
				return articles[i];
		}

		return false;

	} catch (e) {
		exception_error("find_article", e);
	}
}

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

function get_feed_icon(feed) {
	try {
		if (feed.has_icon)
			return 'icons/' + feed.id + '.ico';

		if (feed.id == -1)
			return 'images/mark_set.png';

		if (feed.id == -2)
			return 'images/pub_set.png';

		if (feed.id == -3)
			return 'images/fresh.png';

		if (feed.id == -4) 
			return 'images/tag.png';

		if (feed.id < -10) 
			return 'images/label.png';

	} catch (e) {
		exception_error("get_feed_icon", e);
	}
}

function add_feed_entry(feed) {
	try {
		var icon_part = "";

		icon_part = "<img src='" + get_feed_icon(feed) + "'/>";

		var tmp_html = "<li>" + 
			icon_part +
			"<a href=\"#\" onclick=\"viewfeed("+feed.id+")\">" + feed.title +
			"<div class='unread-ctr'>" + feed.unread + "</div>" +	
			"</li>";

		$("feeds-content").innerHTML += tmp_html;

	} catch (e) {
		exception_error("add_feed_entry", e);
	}
}

function add_latest_entry(article, feed) {
	try {
		

		//$("latest-content").innerHTML += "bbb";

	} catch (e) {
		exception_error("add_latest_entry", e);
	}
}

function add_headline_entry(article, feed) {
	try {

		var icon_part = "";

		if (article.has_icon) 
			icon_part = "<img src='icons/" + article.feed_id + ".ico'/>";

		var tmp_html = "<li id=\"A-"+article.id+"\">" + 
			icon_part +
			"<img title='Dismiss' onclick=\"view("+article.id+", true)\" class='digest-check' src='images/digest_checkbox.png'>" +
			"<a target=\"_blank\" href=\""+article.link+"\""+
		  		"onclick=\"return view("+article.id+")\" class='title'>" + 
				article.title + "</a>" +
			"<div class='body'>" + 
			"<div title=\"Click to expand article\" onclick=\"zoom("+article.id+")\" class='excerpt'>" + 
				article.excerpt + "</div>" +
			"<div style='display : none' class='content'>" + 
				article.content + "</div>" +
			"<div class='info'><a>" + feed.title + "</a> " + " @ " + 
				new Date(article.updated * 1000) + "</div>" +
			"</div></li>";

		$("headlines-content").innerHTML += tmp_html;

	} catch (e) {
		exception_error("add_headline_entry", e);
	}
}

function digest_update(transport, feed_id) {
	try {
		var feeds = transport.responseXML.getElementsByTagName('feeds')[0];
		var headlines = transport.responseXML.getElementsByTagName('headlines')[0];

		if (feeds) {
			feeds = eval("(" + feeds.firstChild.nodeValue + ")");

			last_feeds = feeds;

			$('feeds-content').innerHTML = "";

			for (var i = 0; i < feeds.length; i++) {
				add_feed_entry(feeds[i]);
			}
		} else {
			feeds = last_feeds;
		}

		if (headlines) {
			headlines = eval("(" + headlines.firstChild.nodeValue + ")");

			if (_active_feed_id != feed_id) 
				$('headlines-content').innerHTML = "";

			//Element.hide('headlines-content');
			
			var pr = $('MORE-PROMPT');

			if (pr) {
				pr.id = '';
				Element.hide(pr);
			}

			for (var i = 0; i < headlines.length; i++) {
				var elem = $('A-' + headlines[i].id);
				
				if (elem && Element.visible(elem)) {


				} else {
					add_headline_entry(headlines[i], find_feed(feeds, headlines[i].feed_id));
				}
			}

			$('headlines-content').innerHTML += "<li id='MORE-PROMPT'>" +
				"<div class='body'><a href=\"#\" onclick=\"load_more()\">" +
			  	__("More articles...") + "</a></div></li>";

			new Effect.Appear('headlines-content');

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
				digest_update(transport, -4);
				window.setTimeout('viewfeed(-4)', 100);
				} });

	} catch (e) {
		exception_error("digest_init", e);
	}
}

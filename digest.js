var last_feeds = [];

var _active_feed_id = false;
var _active_feed_offset = false;
var _update_timeout = false;

function catchup_article(article_id, callback) {
	try {
		var query = "?op=rpc&subop=catchupSelected" +
			"&cmode=0&ids=" + article_id;

		new Ajax.Request("backend.php",	{
			parameters: query, 
			onComplete: function(transport) {
				if (callback) callback(transport);
			} });

	} catch (e) {
		exception_error("catchup_article", e);
	}
}

function set_selected_feed(feed_id) {
	try {
		var feeds = $("feeds-content").getElementsByTagName("LI");

		for (var i = 0; i < feeds.length; i++) {
			if (feeds[i].id == "F-" + feed_id)
				feeds[i].className = "selected";
			else
				feeds[i].className = "";
		}

		_active_feed_id = feed_id;

	} catch (e) {
		exception_error("mark_selected_feed", e);
	}
}

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

		catchup_article(article_id, 
			function() { update(); });

	} catch (e) {
		exception_error("zoom", e);
	}
}

function load_more() {
	try {
		viewfeed(_active_feed_id, _active_feed_offset + 10);
	} catch (e) {
		exception_error("load_more", e);
	}
}

function update() {
	try {
		console.log('updating feeds...');

		window.clearTimeout(_update_timeout);

		new Ajax.Request("backend.php",	{
			parameters: "?op=rpc&subop=digest-init",
			onComplete: function(transport) {
				parse_feeds(transport);
				set_selected_feed(_active_feed_id);
				} });

		_update_timeout = window.setTimeout('update()', 5*1000);
	} catch (e) {
		exception_error("update", e);
	}
}

function remove_headline_entry(article_id) {
	try {
		var elem = $('A-' + article_id);

		if (elem) {
			elem.parentNode.removeChild(elem);
		}

	} catch (e) {
		exception_error("remove_headline_entry", e);
	}
}

function view(article_id, dismiss_only) {
	try {
		remove_headline_entry(article_id);

		catchup_article(article_id, 
			function() { update(); });

		return dismiss_only != true;
	} catch (e) {
		exception_error("view", e);
	}
}

function viewfeed(feed_id, offset) {
	try {

		if (!feed_id) feed_id = _active_feed_id;

		if (!offset) {
			offset = 0;
		} else {
			offset = _active_feed_offset + offset;
		}

		var query = "backend.php?op=rpc&subop=digest-update&feed_id=" + feed_id +
				"&offset=" + offset;

		new Ajax.Request("backend.php",	{
			parameters: query, 
			onComplete: function(transport) {
				parse_headlines(transport, offset == 0);
				set_selected_feed(feed_id);
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

		return 'images/blank_icon.gif';

	} catch (e) {
		exception_error("get_feed_icon", e);
	}
}

function add_feed_entry(feed) {
	try {
		var icon_part = "";

		icon_part = "<img src='" + get_feed_icon(feed) + "'/>";

		var tmp_html = "<li id=\"F-"+feed.id+"\">" + 
			icon_part +
			"<a href=\"#\" onclick=\"viewfeed("+feed.id+")\">" + feed.title +
			"<div class='unread-ctr'>" + feed.unread + "</div>" +	
			"</li>";

		$("feeds-content").innerHTML += tmp_html;

	} catch (e) {
		exception_error("add_feed_entry", e);
	}
}

function add_headline_entry(article, feed) {
	try {

		var icon_part = "";

		icon_part = "<img class='icon' src='" + get_feed_icon(feed) + "'/>";

		var tmp_html = "<li id=\"A-"+article.id+"\">" + 
			icon_part +
			"<div class='digest-check'>" +
			"<img title='Set starred' onclick=\"toggleMark(this, "+article.id+")\" src='images/mark_unset.png'>" +
			"<img title='Set published' onclick=\"togglePub(this, "+article.id+")\" src='images/pub_unset.png'>" +
			"<img title='Dismiss' onclick=\"view("+article.id+", true)\" class='digest-check' src='images/digest_checkbox.png'>" +
			"</div>" + 
			"<a target=\"_blank\" href=\""+article.link+"\""+
		  		"onclick=\"return view("+article.id+")\" class='title'>" + 
				article.title + "</a>" +
			"<div class='body'>" + 
			"<div title=\"Click to expand article\" onclick=\"zoom("+article.id+")\" class='excerpt'>" + 
				article.excerpt + "</div>" +
			"<div style='display : none' class='content'>" + 
				article.content + "</div>" +
			"<div class='info'><a href=\#\" onclick=\"viewfeed("+feed.id+")\">" + 
				feed.title + "</a> " + " @ " + 
				new Date(article.updated * 1000) + "</div>" +
			"</div></li>";

		$("headlines-content").innerHTML += tmp_html;

	} catch (e) {
		exception_error("add_headline_entry", e);
	}
}

function parse_feeds(transport) {
	try {

		var feeds = transport.responseXML.getElementsByTagName('feeds')[0];

		if (feeds) {
			feeds = eval("(" + feeds.firstChild.nodeValue + ")");

			last_feeds = feeds;

			$('feeds-content').innerHTML = "";

			for (var i = 0; i < feeds.length; i++) {
				add_feed_entry(feeds[i]);
			}
		}

	} catch (e) {
		exception_error("parse_feeds", e);
	}
}

function parse_headlines(transport, replace) {
	try {
		var headlines = transport.responseXML.getElementsByTagName('headlines')[0];

		if (headlines) {
			headlines = eval("(" + headlines.firstChild.nodeValue + ")");

			if (replace) $('headlines-content').innerHTML = '';

			var pr = $('MORE-PROMPT');

			if (pr) pr.parentNode.removeChild(pr);

			for (var i = 0; i < headlines.length; i++) {
				
				if (!$('A-' + headlines[i].id)) {
					add_headline_entry(headlines[i], 
							find_feed(last_feeds, headlines[i].feed_id));
				}
			}

			if (pr) {
				$('headlines-content').appendChild(pr);
			} else {
				$('headlines-content').innerHTML += "<li id='MORE-PROMPT'>" +
					"<div class='body'><a href=\"javascript:load_more()\">" +
				  	__("More articles...") + "</a></div></li>";
			}

			new Effect.Appear('headlines-content');
		}

	} catch (e) {
		exception_error("parse_headlines", e);
	}
}

/*function digest_update(transport, feed_id, offset) {
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

			if (_active_feed_id != feed_id || !offset) 
				$('headlines-content').innerHTML = "";

			var pr = $('MORE-PROMPT');

			if (pr) {
				pr.id = '';
				Element.hide(pr);
			}

			for (var i = 0; i < headlines.length; i++) {
				var elem = $('A-' + headlines[i].id);
				
				if (elem && Element.visible(elem)) {
					if (!headlines[i].unread)
						remove_headline_entry(headlines[i].id);

				} else {
					add_headline_entry(headlines[i], find_feed(feeds, headlines[i].feed_id));
				}
			}

			$('headlines-content').innerHTML += "<li id='MORE-PROMPT'>" +
				"<div class='body'><a href=\"#\" onclick=\"load_more()\">" +
			  	__("More articles...") + "</a></div></li>";

			new Effect.Appear('headlines-content');
		}

		if (feed_id != undefined) {
			_active_feed_id = feed_id;
		}

		if (offset != undefined) _active_feed_offset = offset;

		mark_selected_feed(_active_feed_id);

	} catch (e) {
		exception_error("digest_update", e);
	}
} */

function init() {
	try {
		
		new Ajax.Request("backend.php",	{
			parameters: "backend.php?op=rpc&subop=digest-init",
			onComplete: function(transport) {
				parse_feeds(transport);
				window.setTimeout('viewfeed(-4)', 100);
				_update_timeout = window.setTimeout('update()', 5*1000);
				} });

	} catch (e) {
		exception_error("digest_init", e);
	}
}

function tMark_afh_off(effect) {
	try {
		var elem = effect.effects[0].element;

		console.log("tMark_afh_off : " + elem.id);

		if (elem) {
			elem.src = elem.src.replace("mark_set", "mark_unset");
			elem.alt = __("Star article");
			Element.show(elem);
		}

	} catch (e) {
		exception_error("tMark_afh_off", e);
	}
}

function tPub_afh_off(effect) {
	try {
		var elem = effect.effects[0].element;

		console.log("tPub_afh_off : " + elem.id);

		if (elem) {
			elem.src = elem.src.replace("pub_set", "pub_unset");
			elem.alt = __("Publish article");
			Element.show(elem);
		}

	} catch (e) {
		exception_error("tPub_afh_off", e);
	}
}

function toggleMark(mark_img, id) {

	try {

		var query = "?op=rpc&id=" + id + "&subop=mark";
	
		query = query + "&afid=" + _active_feed_id;
		query = query + "&omode=c";

		if (!mark_img) return;

		if (mark_img.src.match("mark_unset")) {
			mark_img.src = mark_img.src.replace("mark_unset", "mark_set");
			mark_img.alt = __("Unstar article");
			query = query + "&mark=1";
		} else {
			mark_img.alt = __("Please wait...");
			query = query + "&mark=0";
	
			mark_img.src = mark_img.src.replace("mark_set", "mark_unset");
			mark_img.alt = __("Star article");
		}

		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function(transport) { 
				//
			} });

	} catch (e) {
		exception_error("toggleMark", e);
	}
}

function togglePub(mark_img, id, note) {

	try {

		var query = "?op=rpc&id=" + id + "&subop=publ";
	
		query = query + "&afid=" + _active_feed_id;

		if (note != undefined) {
			query = query + "&note=" + param_escape(note);
		} else {
			query = query + "&note=undefined";
		}

		query = query + "&omode=c";

		if (!mark_img) return;

		if (mark_img.src.match("pub_unset") || note != undefined) {
			mark_img.src = mark_img.src.replace("pub_unset", "pub_set");
			mark_img.alt = __("Unpublish article");
			query = query + "&pub=1";

		} else {
			mark_img.alt = __("Please wait...");
			query = query + "&pub=0";
	
			mark_img.src = mark_img.src.replace("pub_set", "pub_unset");
			mark_img.alt = __("Publish article");
		}

		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function(transport) { 
				//
			} });

	} catch (e) {
		exception_error("togglePub", e);
	}
}


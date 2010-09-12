var last_feeds = [];

var _active_feed_id = false;
var _active_feed_offset = false;
var _update_timeout = false;
var _feedlist_expanded = false;

function catchup_feed(feed_id, callback) {
	try {

		var fn = find_feed(last_feeds, feed_id).title;

		if (confirm(__("Mark all articles in %s as read?").replace("%s", fn))) {

			var is_cat = "";

			if (feed_id == -4) is_cat = "true";

			var query = "?op=rpc&subop=catchupFeed&feed_id=" + 
				feed_id + "&is_cat=" + is_cat;

			new Ajax.Request("backend.php",	{
				parameters: query, 
				onComplete: function(transport) {
					if (callback) callback(transport);
	
					update();
				} });
		}

	} catch (e) {
		exception_error("catchup_article", e);
	}
}


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
				fatal_error_check(transport);
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
				fatal_error_check(transport);
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

		var tmp_html = "<li id=\"F-"+feed.id+"\" " +
				"onmouseover=\"feed_mi(this)\" onmouseout=\"feed_mo(this)\">" + 
			icon_part +
			"<a href=\"#\" onclick=\"viewfeed("+feed.id+")\">" + feed.title + "</a>" +
			"<div class='unread-ctr'>" + 
				"<img onclick=\"catchup_feed("+feed.id+")\" title=\"Dismiss\" class=\"dismiss\" style='display : none' src=\"images/digest_checkbox.png\">" +
				"<span class=\"unread\">" + feed.unread + "</span>" + 
			"</div>" +	
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

function expand_feeds() {
	try {
		_feedlist_expanded = true;

		redraw_feedlist(last_feeds);

	} catch (e) {
		exception_error("expand_feeds", e);
	}
}

function redraw_feedlist(feeds) {
	try {

		$('feeds-content').innerHTML = "";

		var limit = 10;

		if (_feedlist_expanded) limit = feeds.length;

		for (var i = 0; i < Math.min(limit, feeds.length); i++) {
			add_feed_entry(feeds[i]);
		}

		if (feeds.length > limit) {
			$('feeds-content').innerHTML += "<li id='F-MORE-PROMPT'>" +
				"<img src='images/blank_icon.gif'>" + 
				"<a href=\"#\" onclick=\"expand_feeds()\">" +
				__("%d more...").replace("%d", feeds.length-10) + 
				"</a>" + "</li>";
		}

	} catch (e) {
		exception_error("redraw_feedlist", e);
	}
}

function parse_feeds(transport) {
	try {

		if (!transport.responseXML) return;

		var feeds = transport.responseXML.getElementsByTagName('feeds')[0];

		if (feeds) {
			feeds = eval("(" + feeds.firstChild.nodeValue + ")");

			feeds.sort( function (a,b) 
				{ 
					if (b.unread != a.unread)
						return (b.unread - a.unread) 
					else
						if (a.title > b.title)
							return 1;
						else if (a.title < b.title)
							return -1;
						else
							return 0;					
				});

			var all_articles = find_feed(feeds, -4);

			update_title(all_articles.unread);

			last_feeds = feeds;

			redraw_feedlist(feeds);
		}

	} catch (e) {
		exception_error("parse_feeds", e);
	}
}

function parse_headlines(transport, replace) {
	try {
		if (!transport.responseXML) return;

		var headlines = transport.responseXML.getElementsByTagName('headlines')[0];

		if (headlines) {
			headlines = eval("(" + headlines.firstChild.nodeValue + ")");

			if (replace) $('headlines-content').innerHTML = '';

			var pr = $('H-MORE-PROMPT');

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
				$('headlines-content').innerHTML += "<li id='H-MORE-PROMPT'>" +
					"<div class='body'><a href=\"javascript:load_more()\">" +
				  	__("More articles...") + "</a></div></li>";
			}

			new Effect.Appear('headlines-content');
		}

	} catch (e) {
		exception_error("parse_headlines", e);
	}
}

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

function fatal_error(code, msg) {
	try {	

		if (code == 6) {
			window.location.href = "digest.php";
		} else if (code == 5) {
			window.location.href = "update.php";
		} else {
	
			if (msg == "") msg = "Unknown error";

			console.error("Fatal error: " + code + "\n" + 
				msg);
			
		}

	} catch (e) {
		exception_error("fatalError", e);
	}
}

function fatal_error_check(transport) {
	try {
		if (transport.responseXML) {
			var error = transport.responseXML.getElementsByTagName("error")[0];

			if (error) {
				var code = error.getAttribute("error-code");
				var msg = error.getAttribute("error-msg");
				if (code != 0) {
					fatal_error(code, msg);
					return false;
				}
			}
		}
	} catch (e) {
		exception_error("fatal_error_check", e);
	}
	return true;
}

function feed_mi(elem) {
	try {
		var imgs = elem.getElementsByTagName('IMG');
		var spans = elem.getElementsByTagName('SPAN');

		for (var i = 0; i < imgs.length; i++) {
			if (imgs[i].className == "dismiss")
				Element.show(imgs[i]);
		}

		for (var i = 0; i < spans.length; i++) {
			if (spans[i].className == "unread")
				Element.hide(spans[i]);
		}


	} catch (e) {
		exception_error("feed_mi", e);
	}
}

function feed_mo(elem) {
	try {
		var imgs = elem.getElementsByTagName('IMG');
		var spans = elem.getElementsByTagName('SPAN');

		for (var i = 0; i < imgs.length; i++) {
			if (imgs[i].className == "dismiss")
				Element.hide(imgs[i]);
		}

		for (var i = 0; i < spans.length; i++) {
			if (spans[i].className == "unread")
				Element.show(spans[i]);
		}

	} catch (e) {
		exception_error("feed_mo", e);
	}
}

function update_title(unread) {
	try {
		document.title = "Tiny Tiny RSS";

		if (unread > 0)
			document.title += " (" + unread + ")";

	} catch (e) {
		exception_error("update_title", e);
	}
}


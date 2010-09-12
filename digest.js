var last_feeds = [];

var _active_feed_id = false;
var _active_feed_offset = false;
var _update_timeout = false;
var _feedlist_expanded = false;

function article_appear(article_id) {
	try {
		new Effect.Appear('A-' + article_id);
	} catch (e) {
		exception_error("article_appear", e);
	}
}

function catchup_feed(feed_id, callback) {
	try {

		var fn = find_feed(last_feeds, feed_id).title;

		if (confirm(__("Mark all articles in %s as read?").replace("%s", fn))) {

			var is_cat = "";

			if (feed_id < 0) is_cat = "true"; // KLUDGE

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

function catchup_visible_articles(callback) {
	try {
		var elems = $("headlines-content").getElementsByTagName("LI");
		var ids = [];
		
		for (var i = 0; i < elems.length; i++) {
			if (elems[i].id && elems[i].id.match("A-")) {
				ids.push(elems[i].id.replace("A-", ""));
			}
		}

		var query = "?op=rpc&subop=catchupSelected" +
			"&cmode=0&ids=" + param_escape(ids);

		new Ajax.Request("backend.php",	{
			parameters: query, 
			onComplete: function(transport) {
				if (callback) callback(transport);

				viewfeed(_active_feed_id, 0);
			} });

	} catch (e) {
		exception_error("catchup_visible_articles", e);
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

function zoom(elem, article_id) {
	try {
		//alert(elem + "/" + article_id);

		elem.innerHTML = "<img src='images/indicator_tiny.gif'> " +
			__("Loading, please wait...");

		new Ajax.Request("backend.php",	{
			parameters: "?op=rpc&subop=digest-get-contents&article_id=" +
				article_id,
			onComplete: function(transport) {
				fatal_error_check(transport);

				if (transport.responseXML) {
					var article = transport.responseXML.getElementsByTagName('article')[0];
					elem.innerHTML = article.firstChild.nodeValue;

					new Effect.BlindDown(elem, {duration : 0.5});

					elem.onclick = false;
					elem.style.cursor = "auto";
				} else {
					elem.innerHTML = __("Error: unable to load article.");
				}
				
				} });


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
			function() { 
				viewfeed(_active_feed_id, _active_feed_offset, false, true);
				update();	
			});

		return dismiss_only != true;
	} catch (e) {
		exception_error("view", e);
	}
}

function viewfeed(feed_id, offset, replace, no_effects) {
	try {

		if (!feed_id) feed_id = _active_feed_id;

		if (!offset) {
			offset = 0;
		} else {
			offset = _active_feed_offset + offset;
		}

		if (replace == undefined) replace = (offset == 0);

		var query = "backend.php?op=rpc&subop=digest-update&feed_id=" + 
				param_escape(feed_id) +	"&offset=" + offset;

		console.log(query);

		var img = $("F-" + feed_id).getElementsByTagName("IMG")[0];

		img.setAttribute("orig_src", img.src);
		img.src = 'images/indicator_tiny.gif';

		new Ajax.Request("backend.php",	{
			parameters: query, 
			onComplete: function(transport) {
				fatal_error_check(transport);
				parse_headlines(transport, replace, no_effects);
				set_selected_feed(feed_id);
				_active_feed_offset = offset;
				img.src = img.getAttribute("orig_src");
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
				"<img onclick=\"catchup_feed("+feed.id+")\" title=\"" + 
					__("Mark as read") + 
					"\" class=\"dismiss\" style='display : none' src=\"images/digest_checkbox.png\">" +
				"<span class=\"unread\">" + feed.unread + "</span>" + 
			"</div>" +	
			"</li>";

		$("feeds-content").innerHTML += tmp_html;

	} catch (e) {
		exception_error("add_feed_entry", e);
	}
}

function add_headline_entry(article, feed, no_effects) {
	try {

		var icon_part = "";

		icon_part = "<img class='icon' src='" + get_feed_icon(feed) + "'/>";

		var mark_part = "";
		var publ_part = "";

		var tags_part = "";

		if (article.tags.length > 0) {

			tags_part = " " + __("in") + " ";

			for (var i = 0; i < Math.min(5, article.tags.length); i++) {
				tags_part += "<a href=\"#\" onclick=\"viewfeed('" + 
						article.tags[i] + "')\">" + 
					article.tags[i] + "</a>, ";
			}

			tags_part = tags_part.replace(/, $/, "");
			tags_part = "<span class=\"tags\">" + tags_part + "</span>";
		}

		if (article.marked)
			mark_part = "<img title='"+ __("Unstar article")+"' onclick=\"toggle_mark(this, "+article.id+")\" src='images/mark_set.png'>";
		else
			mark_part =	"<img title='"+__("Star article")+"' onclick=\"toggle_mark(this, "+article.id+")\" src='images/mark_unset.png'>";

		if (article.published)
			publ_part = "<img title='"+__("Unpublish article")+"' onclick=\"toggle_pub(this, "+article.id+")\" src='images/pub_set.png'>";
		else
			publ_part =	"<img title='"+__("Publish article")+"' onclick=\"toggle_pub(this, "+article.id+")\" src='images/pub_unset.png'>";

		var style = "";

		if (!no_effects) style = "style=\"display : none\"";

		var tmp_html = "<li id=\"A-"+article.id+"\" "+style+">" + 
			icon_part +

			"<div class='digest-check'>" +
				mark_part +
				publ_part +
				"<img title='" + __("Mark as read") + "' onclick=\"view("+article.id+", true)\" src='images/digest_checkbox.png'>" +
			"</div>" + 
			"<a target=\"_blank\" href=\""+article.link+"\""+
		  		"onclick=\"return view("+article.id+")\" class='title'>" + 
				article.title + "</a>" +
			"<div class='body'>" + 
			"<div title=\""+__("Click to expand article")+"\" onclick=\"zoom(this, "+article.id+")\" class='excerpt'>" + 
				article.excerpt + "</div>" +
			"<div class='info'><a href=\#\" onclick=\"viewfeed("+feed.id+")\">" + 
				feed.title + "</a> " + tags_part + " @ " + 
				new Date(article.updated * 1000) + "</div>" +
			"</div></li>";

		$("headlines-content").innerHTML += tmp_html;

		if (!no_effects)
			window.setTimeout('article_appear(' + article.id + ')', 100);

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

function parse_headlines(transport, replace, no_effects) {
	try {
		if (!transport.responseXML) return;

		var headlines = transport.responseXML.getElementsByTagName('headlines')[0];
		var headlines_title = transport.responseXML.getElementsByTagName('headlines-title')[0];

		if (headlines && headlines_title) {
			headlines = eval("(" + headlines.firstChild.nodeValue + ")");

			var title = headlines_title.firstChild.nodeValue;

			$("headlines-title").innerHTML = title;

			if (replace) {
				$('headlines-content').innerHTML = '';
				Element.hide('headlines-content');
			}

			var pr = $('H-MORE-PROMPT');

			if (pr) pr.parentNode.removeChild(pr);

			var inserted = false;

			for (var i = 0; i < headlines.length; i++) {
				
				if (!$('A-' + headlines[i].id)) {
					add_headline_entry(headlines[i], 
							find_feed(last_feeds, headlines[i].feed_id), !no_effects);

					inserted = $("A-" + headlines[i].id);
				}
			}

			if (pr) {
				$('headlines-content').appendChild(pr);
				if (!no_effects) new Effect.ScrollTo(inserted);
			} else {
				$('headlines-content').innerHTML += "<li id='H-MORE-PROMPT'>" +
					"<div class='body'>" +
					"<a href=\"javascript:catchup_visible_articles()\">" +
				  	__("Mark as read") + "</a> | " + 
					"<a href=\"javascript:load_more()\">" +
				  	__("Load more...") + "</a>" + 
					"</div></li>";
			}

			if (replace && !no_effects) 
				new Effect.Appear('headlines-content', {duration : 0.3});

			//new Effect.Appear('headlines-content');
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

function toggle_mark(mark_img, id) {

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
				update();
			} });

	} catch (e) {
		exception_error("toggle_mark", e);
	}
}

function toggle_pub(mark_img, id, note) {

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
				update();
			} });

	} catch (e) {
		exception_error("toggle_pub", e);
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


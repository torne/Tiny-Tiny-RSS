var last_feeds = [];
var init_params = {};

var _active_feed_id = false;
var _update_timeout = false;
var _view_update_timeout = false;
var _feedlist_expanded = false;
var _update_seq = 1;

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

			var query = "?op=rpc&method=catchupFeed&feed_id=" +
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

function get_visible_article_ids() {
	try {
		var elems = $("headlines-content").getElementsByTagName("LI");
		var ids = [];

		for (var i = 0; i < elems.length; i++) {
			if (elems[i].id && elems[i].id.match("A-")) {
			ids.push(elems[i].id.replace("A-", ""));
			}
		}

		return ids;

	} catch (e) {
		exception_error("get_visible_article_ids", e);
	}
}

function catchup_visible_articles(callback) {
	try {

		var ids = get_visible_article_ids();

		if (confirm(__("Mark %d displayed articles as read?").replace("%d", ids.length))) {

			var query = "?op=rpc&method=catchupSelected" +
				"&cmode=0&ids=" + param_escape(ids);

			new Ajax.Request("backend.php",	{
				parameters: query,
				onComplete: function(transport) {
					if (callback) callback(transport);

					viewfeed(_active_feed_id, 0);
				} });

			}

	} catch (e) {
		exception_error("catchup_visible_articles", e);
	}
}

function catchup_article(article_id, callback) {
	try {
		var query = "?op=rpc&method=catchupSelected" +
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

function set_selected_article(article_id) {
	try {
		$$("#headlines-content > li[id*=A-]").each(function(article) {
				var id = article.id.replace("A-", "");

				var cb = article.getElementsByTagName("INPUT")[0];

				if (id == article_id) {
					article.addClassName("selected");
					cb.checked = true;
				} else {
					article.removeClassName("selected");
					cb.checked = false;
				}

		});

	} catch (e) {
		exception_error("mark_selected_feed", e);
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

function load_more() {
	try {
		var pr = $("H-LOADING-IMG");

		if (pr) Element.show(pr);

		var offset = $$("#headlines-content > li[id*=A-][class*=fresh],li[id*=A-][class*=unread]").length;

		viewfeed(false, offset, false, false, true,
			function() {
				var pr = $("H-LOADING-IMG");

				if (pr) Element.hide(pr);
			});
	} catch (e) {
		exception_error("load_more", e);
	}
}

function update(callback) {
	try {
		console.log('updating feeds...');

		window.clearTimeout(_update_timeout);

		new Ajax.Request("backend.php",	{
			parameters: "?op=rpc&method=digestinit",
			onComplete: function(transport) {
				fatal_error_check(transport);
				parse_feeds(transport);
				set_selected_feed(_active_feed_id);

				if (callback) callback(transport);
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

function view_update() {
	try {
		viewfeed(_active_feed_id, _active_feed_offset, false, true, true);
		update();
	} catch (e) {
		exception_error("view_update", e);
	}
}

function view(article_id) {
	try {
		$("content").addClassName("move");

		var a = $("A-" + article_id);
		var h = $("headlines");

		setTimeout(function() {
			// below or above viewport, reposition headline
			if (a.offsetTop > h.scrollTop + h.offsetHeight || a.offsetTop+a.offsetHeight < h.scrollTop+a.offsetHeight)
				h.scrollTop = a.offsetTop - (h.offsetHeight/2 - a.offsetHeight/2);
			}, 500);

		new Ajax.Request("backend.php",	{
			parameters: "?op=rpc&method=digestgetcontents&article_id=" +
				article_id,
			onComplete: function(transport) {
				fatal_error_check(transport);

				var reply = JSON.parse(transport.responseText);

				if (reply) {
					var article = reply['article'];

					var mark_part = "";
					var publ_part = "";

					var tags_part = "";

					if (article.tags.length > 0) {
						tags_part = " " + __("in") + " ";

						for (var i = 0; i < Math.min(5, article.tags.length); i++) {
							//tags_part += "<a href=\"#\" onclick=\"viewfeed('" +
							//		article.tags[i] + "')\">" +
							//	article.tags[i] + "</a>, ";

							tags_part += article.tags[i] + ", ";
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

					var tmp = "<div id=\"inner\">" +
						"<div id=\"ops\">" +
						mark_part +
						publ_part +
						"</div>" +
						"<h1>" + "<a target=\"_blank\" href=\""+article.url+"\">" +
							article.title + "</a>" + "</h1>" +
						"<div id=\"tags\">" +
						tags_part +
						"</div>" +
						article.content + "</div>";

					$("article-content").innerHTML = tmp;
					$("article").addClassName("visible");

					set_selected_article(article.id);

					catchup_article(article_id,
						function() {
							$("A-" + article_id).addClassName("read");
					});

				} else {
					elem.innerHTML = __("Error: unable to load article.");
				}
			}
		});


		return false;
	} catch (e) {
		exception_error("view", e);
	}
}

function close_article() {
	$("content").removeClassName("move");
	$("article").removeClassName("visible");
}

function viewfeed(feed_id, offset, replace, no_effects, no_indicator, callback) {
	try {

		if (!feed_id) feed_id = _active_feed_id;
		if (offset == undefined) offset = 0;
		if (replace == undefined) replace = (offset == 0);

		_update_seq = _update_seq + 1;

		if (!offset) $("headlines").scrollTop = 0;

		var query = "backend.php?op=rpc&method=digestupdate&feed_id=" +
				param_escape(feed_id) +	"&offset=" + offset +
				"&seq=" + _update_seq;

		console.log(query);

		var img = false;

		if ($("F-" + feed_id)) {
			img = $("F-" + feed_id).getElementsByTagName("IMG")[0];

			if (img && !no_indicator) {
				img.setAttribute("orig_src", img.src);
				img.src = 'images/indicator_tiny.gif';
			}
		}

		new Ajax.Request("backend.php",	{
			parameters: query,
			onComplete: function(transport) {
				Element.hide("overlay");

				fatal_error_check(transport);
				parse_headlines(transport, replace, no_effects);
				set_selected_feed(feed_id);
				_active_feed_offset = offset;

				if (img && !no_indicator)
					img.src = img.getAttribute("orig_src");

				if (callback) callback(transport);

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
			return getInitParam('icons_url') + "/" + feed.id + '.ico';

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

		var title = (feed.title.length > 30) ?
			feed.title.substring(0, 30) + "&hellip;" :
			feed.title;

		var tmp_html = "<li id=\"F-"+feed.id+"\" onclick=\"viewfeed("+feed.id+")\">" +
			icon_part + title +
			"<div class='unread-ctr'>" + "<span class=\"unread\">" + feed.unread + "</span>" +
			"</div>" + "</li>";

		$("feeds-content").innerHTML += tmp_html;


	} catch (e) {
		exception_error("add_feed_entry", e);
	}
}

function add_headline_entry(article, feed, no_effects) {
	try {

		var icon_part = "";

		icon_part = "<img class='icon' src='" + get_feed_icon(feed) + "'/>";


		var style = "";

		//if (!no_effects) style = "style=\"display : none\"";

		if (article.excerpt.trim() == "")
			article.excerpt = __("Click to expand article.");

		var li_class = "unread";

		var fresh_max = getInitParam("fresh_article_max_age") * 60 * 60;
		var d = new Date();

		if (d.getTime() / 1000 - article.updated < fresh_max)
			li_class = "fresh";

		//"<img title='" + __("Share on Twitter") + "' onclick=\"tweet_article("+article.id+", true)\" src='images/art-tweet.png'>" +

		//"<img title='" + __("Mark as read") + "' onclick=\"view("+article.id+", true)\" src='images/digest_checkbox.png'>" +

		var checkbox_part = "<input type=\"checkbox\" class=\"cb\" onclick=\"toggle_select_article(this)\"/>";

		var date = new Date(article.updated * 1000);

		var date_part = date.toString().substring(0,21);

		var tmp_html = "<li id=\"A-"+article.id+"\" "+style+" class=\""+li_class+"\">" +
			checkbox_part +
			icon_part +
			"<a target=\"_blank\" href=\""+article.link+"\""+
		  		"onclick=\"return view("+article.id+")\" class='title'>" +
				article.title + "</a>" +
			"<div class='body'>" +
			"<div onclick=\"view("+article.id+")\" class='excerpt'>" +
				article.excerpt + "</div>" +
			"<div onclick=\"view("+article.id+")\" class='info'>";

/*		tmp_html += "<a href=\#\" onclick=\"viewfeed("+feed.id+")\">" +
					feed.title + "</a> " + " @ "; */

		tmp_html += date_part + "</div>" +
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

		if (feeds.length == 0) {
			$('feeds-content').innerHTML =
				"<div class='insensitive' style='text-align : center'>" +
					__("No unread feeds.") + "</div>";
		}

		if (_active_feed_id)
			set_selected_feed(_active_feed_id);

	} catch (e) {
		exception_error("redraw_feedlist", e);
	}
}

function parse_feeds(transport) {
	try {
		var reply = JSON.parse(transport.responseText);

		if (!reply) return;

		var feeds = reply['feeds'];

		if (feeds) {

			feeds.sort( function (a,b)
				{
					if (b.unread != a.unread)
						return (b.unread - a.unread);
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
		var reply = JSON.parse(transport.responseText);
		if (!reply) return;

		var seq = reply['seq'];

		if (seq) {
			if (seq != _update_seq) {
				console.log("parse_headlines: wrong sequence received.");
				return;
			}
		} else {
			return;
		}

		var headlines = reply['headlines']['content'];
		var headlines_title = reply['headlines']['title'];

		if (headlines && headlines_title) {

			if (replace) {
				$('headlines-content').innerHTML = '';
			}

			var pr = $('H-MORE-PROMPT');

			if (pr) pr.parentNode.removeChild(pr);

			var inserted = false;

			for (var i = 0; i < headlines.length; i++) {

				if (!$('A-' + headlines[i].id)) {
					add_headline_entry(headlines[i],
							find_feed(last_feeds, headlines[i].feed_id), !no_effects);

				}
			}

			console.log(inserted.id);

			var ids = get_visible_article_ids();

			if (ids.length > 0) {
				if (pr) {
					$('headlines-content').appendChild(pr);

				} else {
					$('headlines-content').innerHTML += "<li id='H-MORE-PROMPT'>" +
						"<div class='body'>" +
						"<a href=\"#\" onclick=\"catchup_visible_articles()\">" +
					  	__("Mark as read") + "</a> | " +
						"<a href=\"javascript:load_more()\">" +
					  	__("Load more...") + "</a>" +
						"<img style=\"display : none\" "+
						"id=\"H-LOADING-IMG\" src='images/indicator_tiny.gif'>" +
						"</div></li>";
				}
			} else {
				// FIXME : display some kind of "nothing to see here" prompt here
			}

//			if (replace && !no_effects)
//				new Effect.Appear('headlines-content', {duration : 0.3});

			//new Effect.Appear('headlines-content');
		}

	} catch (e) {
		exception_error("parse_headlines", e);
	}
}

function init_second_stage() {
	try {
		new Ajax.Request("backend.php",	{
			parameters: "backend.php?op=rpc&method=digestinit",
			onComplete: function(transport) {
				parse_feeds(transport);
				Element.hide("overlay");

				document.onkeydown = hotkey_handler;

				window.setTimeout('viewfeed(-4)', 100);
				_update_timeout = window.setTimeout('update()', 5*1000);
				} });

	} catch (e) {
		exception_error("init_second_stage", e);
	}
}

function init() {
	try {
		dojo.require("dijit.Dialog");

		new Ajax.Request("backend.php", {
			parameters: {op: "rpc", method: "sanityCheck"},
			onComplete: function(transport) {
				backend_sanity_check_callback(transport);
			} });

	} catch (e) {
		exception_error("digest_init", e);
	}
}

function toggle_mark(img, id) {

	try {

		var query = "?op=rpc&id=" + id + "&method=mark";

		if (!img) return;

		if (img.src.match("mark_unset")) {
			img.src = img.src.replace("mark_unset", "mark_set");
			img.alt = __("Unstar article");
			query = query + "&mark=1";
		} else {
			img.src = img.src.replace("mark_set", "mark_unset");
			img.alt = __("Star article");
			query = query + "&mark=0";
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

function toggle_pub(img, id, note) {

	try {

		var query = "?op=rpc&id=" + id + "&method=publ";

		if (note != undefined) {
			query = query + "&note=" + param_escape(note);
		} else {
			query = query + "&note=undefined";
		}

		if (!img) return;

		if (img.src.match("pub_unset") || note != undefined) {
			img.src = img.src.replace("pub_unset", "pub_set");
			img.alt = __("Unpublish article");
			query = query + "&pub=1";

		} else {
			img.src = img.src.replace("pub_set", "pub_unset");
			img.alt = __("Publish article");
			query = query + "&pub=0";
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
			window.location.href = "db-updater.php";
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

function update_title(unread) {
	try {
		document.title = "Tiny Tiny RSS";

		if (unread > 0)
			document.title += " (" + unread + ")";

	} catch (e) {
		exception_error("update_title", e);
	}
}

function tweet_article(id) {
	try {

		var query = "?op=rpc&method=getTweetInfo&id=" + param_escape(id);

		console.log(query);

		var d = new Date();
      var ts = d.getTime();

		var w = window.open('backend.php?op=backend&method=loading', 'ttrss_tweet',
			"status=0,toolbar=0,location=0,width=500,height=400,scrollbars=1,menubar=0");

		new Ajax.Request("backend.php",	{
			parameters: query,
			onComplete: function(transport) {
				var ti = JSON.parse(transport.responseText);

				var share_url = "http://twitter.com/share?_=" + ts +
					"&text=" + param_escape(ti.title) +
					"&url=" + param_escape(ti.link);

				w.location.href = share_url;

			} });

	} catch (e) {
		exception_error("tweet_article", e);
	}
}

function toggle_select_article(elem) {
	try {
		var article = elem.parentNode;

		if (article.hasClassName("selected"))
			article.removeClassName("selected");
		else
			article.addClassName("selected");

	} catch (e) {
		exception_error("toggle_select_article", e);
	}
}

function hotkey_handler(e) {
	try {

		if (e.target.nodeName == "INPUT" || e.target.nodeName == "TEXTAREA") return;

		var keycode = false;
		var shift_key = false;

		var cmdline = $('cmdline');

		try {
			shift_key = e.shiftKey;
		} catch (e) {

		}

		if (window.event) {
			keycode = window.event.keyCode;
		} else if (e) {
			keycode = e.which;
		}

		var keychar = String.fromCharCode(keycode);

		if (keycode == 16) return; // ignore lone shift
		if (keycode == 17) return; // ignore lone ctrl

		switch (keycode) {
		case 27: // esc
			close_article();
			break;
		default:
			console.log("KP: CODE=" + keycode + " CHAR=" + keychar);
		}


	} catch (e) {
		exception_error("hotkey_handler", e);
	}
}

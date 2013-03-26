var backend = "backend.php";

function toggleMarked(id, elem) {

	var toggled = false;

	if (elem.getAttribute("toggled") == "true") {
		toggled = 1;
	} else {
		toggled = 0;
	}

	var query = "op=toggleMarked&id=" + id + "&mark=" + toggled;

	new Ajax.Request(backend, {
		parameters: query,
		onComplete: function (transport) {
			//
		} });
}

function togglePublished(id, elem) {

	var toggled = false;

	if (elem.getAttribute("toggled") == "true") {
		toggled = 1;
	} else {
		toggled = 0;
	}

	var query = "op=togglePublished&id=" + id + "&pub=" + toggled;

	new Ajax.Request(backend, {
		parameters: query,
		onComplete: function (transport) {
			//
		} });

}

function toggleUnread(id, elem) {

	var toggled = false;

	if (elem.getAttribute("toggled") == "true") {
		toggled = 1;
	} else {
		toggled = 0;
	}

	var query = "op=toggleUnread&id=" + id + "&unread=" + toggled;

	new Ajax.Request(backend, {
		parameters: query,
		onComplete: function (transport) {
			//
		} });

}

function setPref(elem) {
	var toggled = false;
	var id = elem.id;

	if (elem.getAttribute("toggled") == "true") {
		toggled = 1;
	} else {
		toggled = 0;
	}

	var query = "op=setPref&id=" + id + "&to=" + toggled;

	new Ajax.Request(backend, {
		parameters: query,
		onComplete: function (transport) {
			//
		} });

}

// Go directly to another item in the same feed
function goToSibling(article_id, feed_id, link, step) {
    var links = linksInFeed(feed_id);
    for (var i=0 ; i<links.length ; i++) {
        var re = new RegExp(".*article\\.php\\?id="+article_id+"&.*");
        if (!re.test(links[i].href)) continue;
        // here, we've found the current article
        var index = i + step;
        if (index < 0) {
            markAsRead(feed_id);
            iui.showPage($("feed-"+feed_id), true);
            return false;
        }
        if (index >= links.length) {
            showRestOfFeed(feed_id);
            return false;
        }
        console.log(links[index]);
        var match = links[index].href.match(/.*article\.php\?(.*)/);
        var qs = match[1];
        var backwards = false;
        if (step < 0) backwards = true;
        link.setAttribute("selected", "progress");
        function unselect() { link.removeAttribute("selected"); }
        iui.showPageByHref("article.php?"+qs, null, null, null, unselect, backwards);
        return false;
    }
    return false;
}
function goPrev(article_id, feed_id, link) {
    return goToSibling(article_id, feed_id, link, -1);
}
function goNext(article_id, feed_id, link) {
    return goToSibling(article_id, feed_id, link, 1);
}

// Get all the links in the feed. The all_links variable includes the "get more article" link
function linksInFeed(feed_id, all_links) {
    var feed_content = $("feed-"+feed_id);
    var links_raw = feed_content.getElementsByTagName("a");
    if (all_links) return links_raw;
    var links = [];
    // filter the array to remove the "get more articles" link
    // and the "search" link (which is always first)
    for (var i=1 ; i<links_raw.length ; i++) {
        if (links_raw[i].href.match(/.*article\.php\?id=.*/)) {
            links.push(links_raw[i]);
        }
    }
    return links;
}

// Adds the "read" class to all read links in the feed
function markAsRead(feed_id) {
    var links = linksInFeed(feed_id);
    for (var j=0 ; j<links.length ; j++) {
        var match = links[j].href.match(/.*article\.php\?id=(\d+)&.*/);
        if ($("article-"+match[1])) {
            links[j].className = "read";
        }
    }
}

// Go the the articles list and expand the "get more articles" link
function showRestOfFeed(feed_id) {
    var links_raw = linksInFeed(feed_id, true);
    var lastlink = links_raw[links_raw.length - 1];
    if (lastlink.target == "_replace") {
        // It's a "get more articles" link
        iui.showPage($("feed-"+feed_id), true);
        // Mark old items a "read"
        markAsRead(feed_id);
        // Simulate click on the "get more articles" link
        lastlink.setAttribute("selected", "progress");
        function unselect() { lastlink.removeAttribute("selected"); }
        setTimeout(window.scrollTo, 0, 0, 1000);
        iui.showPageByHref(lastlink.href, null, null, lastlink, unselect);
    } else {
        iui.showPage($("home"), true);
    }
}


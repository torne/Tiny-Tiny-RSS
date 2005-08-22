<?
	header("Content-Type: application/xml");

	require_once "config.php";
	require_once "functions.php";
	require_once "magpierss/rss_fetch.inc";


	$link = pg_connect(DB_CONN);	

	pg_query("set client_encoding = 'utf-8'");

	$op = $_GET["op"];
	$fetch = $_GET["fetch"];
		
	if ($op == "feeds") {

		$subop = $_GET["subop"];

		if ($subop == "catchupAll") {
			pg_query("UPDATE ttrss_entries SET last_read = NOW(),unread = false");
		}

		if ($fetch) update_all_feeds($link, $fetch);
		

		$result = pg_query("SELECT *,
			(SELECT count(id) FROM ttrss_entries 
				WHERE feed_id = ttrss_feeds.id) AS total,
			(SELECT count(id) FROM ttrss_entries
				WHERE feed_id = ttrss_feeds.id AND unread = true) as unread
			FROM ttrss_feeds ORDER BY title");			

		print "<table width=\"100%\" class=\"feeds\">";

		$lnum = 0;

		$total_unread = 0;

		while ($line = pg_fetch_assoc($result)) {
		
			$feed = $line["title"];
			$feed_id = $line["id"];	  
			
			$total = $line["total"];
			$unread = $line["unread"];
			
			$class = ($lnum % 2) ? "even" : "odd";

			if ($unread > 0) $class .= "Unread";

			$total_unread += $unread;

			print "<tr class=\"$class\" id=\"FEEDR-$feed_id\">";

			$feed = "<a href=\"javascript:viewfeed($feed_id, 0);\">$feed</a>";
			print "<td id=\"FEEDN-$feed_id\">$feed</td>";
			print "<td>";
			print "<span id=\"FEEDU-$feed_id\">$unread</span>&nbsp;/&nbsp;";
			print "<span id=\"FEEDT-$feed_id\">$total</span>";
			print "</td>";

			print "</tr>";
			++$lnum;
		}

//		print "<tr><td class=\"footer\" colspan=\"3\">
//			<a href=\"javascript:update_feed_list(false,true)\">Update all feeds</a></td></tr>";

//		print "<tr><td class=\"footer\" colspan=\"2\">&nbsp;";
//		print "</td></tr>";

		print "</table>";

		print "<p align=\"center\">All feeds: 
			<a class=\"button\" 
				href=\"javascript:updateFeedList(false,true)\">Update</a>";

		print "&nbsp;<a class=\"button\" 
				href=\"javascript:catchupAllFeeds()\">Mark as read</a></p>";

		print "<div class=\"invisible\" id=\"FEEDTU\">$total_unread</div>";

	}

	if ($op == "view") {

		$id = $_GET["id"];

		$result = pg_query("UPDATE ttrss_entries SET unread = false,last_read = NOW() WHERE id = '$id'");

		$result = pg_query("SELECT title,link,content FROM ttrss_entries
			WHERE	id = '$id'");

		if ($result) {

			$line = pg_fetch_assoc($result);

			print "<table class=\"feedOverview\">";
			print "<tr><td><b>Title:</b></td><td>".$line["title"]."</td></tr>";
			print "<tr><td><b>Link:</b></td><td><a href=\"".$line["link"]."\">".$line["link"]."</a></td></tr>";

			print "</table>";

			print $line["content"];


		}
	}

	if ($op == "viewfeed") {

		$feed = $_GET["feed"];
		$skip = $_GET["skip"];
		$subop = $_GET["subop"];

		if (!$skip) $skip = 0;

		if ($subop == "undefined") $subop = "";

		// FIXME: check for null value here

		$result = pg_query("SELECT *,
			EXTRACT(EPOCH FROM NOW()) - EXTRACT(EPOCH FROM last_updated) as update_timeout
			FROM ttrss_feeds WHERE id = '$feed'");

		if ($result) {

			$line = pg_fetch_assoc($result);

			if ($subop == "ForceUpdate" || 
				(!$subop && $line["update_timeout"] > MIN_UPDATE_TIME)) {
				
				update_rss_feed($link, $line["feed_url"], $feed);

			} else {

				if ($subop == "MarkAllRead")  {

					pg_query("UPDATE ttrss_entries SET unread = false,last_read = NOW() 
						WHERE feed_id = '$feed'");
				}

				if ($subop == "MarkPageRead")  {

//					pg_query("UPDATE ttrss_entries SET unread = false 
//						WHERE feed_id = '$feed' ORDER BY updated OFFSET $skip LIMIT 1");
				}

			}
		}

		print "<table class=\"headlines\" width=\"100%\">";

		print "<tr><td class=\"search\" colspan=\"3\">
			Search: <input onchange=\"javascript:search($feed,this);\"></td></tr>"; 
		print "<tr><td colspan=\"3\" class=\"title\">" . $line["title"] . "</td></tr>"; 

		if ($ext == "SEARCH") {
			$search = $_GET["search"];
			$search_query_part = "(upper(title) LIKE upper('%$search%') 
				OR content LIKE '%$search%') AND";
		}

		$result = pg_query("SELECT count(id) AS total_entries 
			FROM ttrss_entries WHERE feed_id = '$feed'");

		$total_entries = pg_fetch_result($result, 0, "total_entries");

		$result = pg_query("SELECT 
				id,title,updated,unread,feed_id,
				EXTRACT(EPOCH FROM last_read) AS last_read_ts,
				EXTRACT(EPOCH FROM updated) AS updated_ts
			FROM
				ttrss_entries 
			WHERE
			$search_query_part
			feed_id = '$feed' ORDER BY updated DESC LIMIT ".HEADLINES_PER_PAGE." OFFSET $skip");

		$lnum = 0;

		while ($line = pg_fetch_assoc($result)) {

			$class = ($lnum % 2) ? "even" : "odd";

			if ($line["last_read_ts"] < $line["updated_ts"] && $line["unread"] == "f") {
				$update_pic = "<img src=\"updated.png\" alt=\"Updated\">";
			} else {
				$update_pic = "&nbsp;";
			}

			if ($line["unread"] == "t") 
				$class .= "Unread";

			$id = $line["id"];
			$feed_id = $line["feed_id"];

			$content_link = "<a href=\"javascript:view($id,$feed_id);\">" .
				$line["title"] . "</a>";
				
			print "<tr class='$class' id='RROW-$id'>";

			print "<td id='FUPDPIC-$id' valign='center' class='headlineUpdateMark'>$update_pic</td>";

			print "<td class='headlineUpdated'>".$line["updated"]."</td>";
			print "<td class='headlineTitle'>$content_link</td>";

			print "</tr>";

			++$lnum;
		}

		if ($lnum == 0) {
			print "<tr><td align='center'>No entries found.</td></tr>";

		}

		print "<tr><td colspan=\"3\" class=\"headlineToolbar\">";

		$next_skip = $skip + HEADLINES_PER_PAGE;
		$prev_skip = $skip - HEADLINES_PER_PAGE;
	
		print "Navigate: ";

		if ($prev_skip >= 0) {
			print "<a class=\"button\" 
				href=\"javascript:viewfeed($feed, $prev_skip);\">Previous Page</a>";
		} else {
			print "<a class=\"disabledButton\">Previous Page</a>";
		}
		print "&nbsp;";

		if ($next_skip < $total_entries) {		
			print "<a class=\"button\" 
				href=\"javascript:viewfeed($feed, $next_skip);\">Next Page</a>";
		} else {
			print "<a class=\"disabledButton\">Next Page</a>";
		}			
		print "&nbsp;";
		print "<a class=\"button\" 
			href=\"javascript:viewfeed($feed, $skip, '');\">Refresh Page</a>";
		print "&nbsp;";
		print "<a class=\"button\" 
			href=\"javascript:viewfeed($feed, 0, 'ForceUpdate');\">Update</a>";
		print "&nbsp;&nbsp;Mark as read: ";
		print "<a class=\"button\" 
			href=\"javascript:viewfeed($feed, $skip, 'MarkPageRead');\">This Page</a>";
		print "&nbsp;";
		print "<a class=\"button\" 
			href=\"javascript:viewfeed($feed, $skip, 'MarkAllRead');\">All Posts</a>";

		print "</td></tr>";
		print "</table>";

		$result = pg_query("SELECT id, (SELECT count(id) FROM ttrss_entries 
			WHERE feed_id = ttrss_feeds.id) AS total,
		(SELECT count(id) FROM ttrss_entries
			WHERE feed_id = ttrss_feeds.id AND unread = true) as unread
		FROM ttrss_feeds WHERE id = '$feed'");			

		$total = pg_fetch_result($result, 0, "total");
		$unread = pg_fetch_result($result, 0, "unread");

		print "<div class=\"invisible\" id=\"FACTIVE\">$feed</div>";
		print "<div class=\"invisible\" id=\"FTOTAL\">$total</div>";
		print "<div class=\"invisible\" id=\"FUNREAD\">$unread</div>";

	}

	if ($op == "pref-rpc") {

		$subop = $_GET["subop"];

		if ($subop == "unread") {
			$ids = split(",", $_GET["ids"]);
			foreach ($ids as $id) {
				pg_query("UPDATE ttrss_entries SET unread = true WHERE feed_id = '$id'");
			}

			print "Marked selected feeds as read.";
		}

		if ($subop == "read") {
			$ids = split(",", $_GET["ids"]);
			foreach ($ids as $id) {
				pg_query("UPDATE ttrss_entries 
					SET unread = false,last_read = NOW() WHERE feed_id = '$id'");
			}

			print "Marked selected feeds as unread.";

		}

	}

	if ($op == "pref-feeds") {
	
		$subop = $_GET["subop"];

		if ($subop == "edit") {
			print "<p>[Edit feed placeholder]</p>";
		}

		if ($subop == "remove") {
			$ids = split(",", $_GET["ids"]);

			foreach ($ids as $id) {
				pg_query("BEGIN");
				pg_query("DELETE FROM ttrss_entries WHERE feed_id = '$id'");
				pg_query("DELETE FROM ttrss_feeds WHERE id = '$id'");
				pg_query("COMMIT");

			}
		}

		if ($subop == "add") {
			$feed_link = pg_escape_string($_GET["link"]);
				
			$result = pg_query(
				"INSERT INTO ttrss_feeds (feed_url,title) VALUES ('$feed_link', '')");

			$result = pg_query("SELECT id FROM ttrss_feeds WHERE feed_url = '$feed_link'");

			$feed_id = pg_fetch_result($result, 0, "id");

			if ($feed_id) {
				update_rss_feed($link, $feed_link, $feed_id);
			}

		}
	
		$result = pg_query("SELECT * FROM ttrss_feeds ORDER by title");

		print "<p><table width=\"100%\" class=\"prefFeedList\" id=\"prefFeedList\">";
		print "<tr class=\"title\">
					<td>Select</td><td>Title</td><td>Link</td><td>Last Updated</td></tr>";
		
		$lnum = 0;
		
		while ($line = pg_fetch_assoc($result)) {

			$class = ($lnum % 2) ? "even" : "odd";
			
			$feed_id = $line["id"];
			
			print "<tr class=\"$class\" id=\"FEEDR-$feed_id\">";

			print "<td><input onclick='toggleSelectRow(this);' 
				type=\"checkbox\" id=\"FRCHK-".$line["id"]."\"></td>";
			print "<td><a href=\"javascript:editFeed($feed_id);\">" . 
				$line["title"] . "</td>";		
			print "<td><a href=\"javascript:editFeed($feed_id);\">" . 
				$line["feed_url"] . "</td>";		
				
			print "<td>" . $line["last_updated"] . "</td>";
			print "</tr>";

			++$lnum;
		}

		print "</table>";

	}

	pg_close($link);
?>

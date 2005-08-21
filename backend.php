<?
	header("Content-Type: application/xml");

	include "config.php";

	require_once('magpierss/rss_fetch.inc');

	$link = pg_connect(DB_CONN);	

	pg_query("set client_encoding = 'utf-8'");

	$op = $_GET["op"];
	
	if ($op == "feeds") {

		$result = pg_query("SELECT *,
			(SELECT count(id) FROM ttrss_entries 
				WHERE feed_id = ttrss_feeds.id) AS total,
			(SELECT count(id) FROM ttrss_entries
				WHERE feed_id = ttrss_feeds.id AND unread = true) as unread
			FROM ttrss_feeds ORDER BY title");			

		print "<table width=\"100%\">";

		$lnum = 0;

		while ($line = pg_fetch_assoc($result)) {
		
			$feed = $line["title"];
			$feed_id = $line["id"];	  
			
			$total = $line["total"];
			$unread = $line["unread"];
			
			$class = ($lnum % 2) ? "even" : "odd";
			
//			if ($lnum == 2 || $lnum == 0) $feed = "<b>$feed</b>";

			if ($unread > 0) $class .= "Unread";

			print "<tr class=\"$class\" id=\"FEEDR-$feed_id\">";

			$feed = "<a href=\"javascript:viewfeed($feed_id, 0);\">$feed</a>";
			print "<td id=\"FEEDN-$feed_id\">$feed</td>";
			print "<td id=\"FEEDU-$feed_id\">$unread</td>";
			print "<td id=\"FEEDT-$feed_id\">$total</td>";

			print "</tr>";
			++$lnum;
		}

		print "</table>";

	}

	if ($op == "view") {

		$id = $_GET["id"];

		$result = pg_query("UPDATE ttrss_entries SET unread = false WHERE id = '$id'");

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
		$ext = $_GET["ext"];

		if ($ext == "undefined") $ext = "";

		$result = pg_query("SELECT * FROM ttrss_feeds WHERE id = '$feed'");

		if ($result) {

			$line = pg_fetch_assoc($result);

			if (!$ext) {

				$rss = fetch_rss($line["feed_url"]);
	
				if ($rss) {
	
					foreach ($rss->items as $item) {
	
						$entry_guid = $item["id"];
	
						if (!$entry_guid) $entry_guid = $item["guid"];
						if (!$entry_guid) $entry_guid = $item["link"];
	
						$entry_timestamp = $item["pubdate"];
						if (!$entry_timestamp) $entry_timestamp = $item["modified"];
						if (!$entry_timestamp) $entry_timestamp = $item["updated"];
	
						$entry_timestamp = strtotime($entry_timestamp);
	
						$entry_title = $item["title"];
						$entry_link = $item["link"];
	
						$entry_content = $item["description"];
						if (!$entry_content) $entry_content = $item["content"];
	
						$entry_content = pg_escape_string($entry_content);
						$entry_title = pg_escape_string($entry_title);
	
						$content_md5 = md5($entry_content);
	
						$result = pg_query("
							SELECT 
								id,unread,md5_hash
							FROM
								ttrss_entries 
							WHERE
								guid = '$entry_guid' OR md5_hash = '$content_md5'");
						
						if (pg_num_rows($result) == 0) {
	
							$entry_timestamp = strftime("%Y/%m/%d %H:%M:%S", $entry_timestamp);
	
							$query = "INSERT INTO ttrss_entries 
									(title, guid, link, updated, content, feed_id, md5_hash) 
								VALUES
									('$entry_title', '$entry_guid', '$entry_link', 
										'$entry_timestamp', '$entry_content', '$feed', 
										'$content_md5')";
	
							pg_query($query);
	
						} else {
	
							$entry_id = pg_fetch_result($result, 0, "id");
							$entry_timestamp = strftime("%Y/%m/%d %H:%M:%S", $entry_timestamp);
	
							$unread = pg_fetch_result($result, 0, "unread");
							$md5_hash = pg_fetch_result($result, 0, "md5_hash");
						
							if ($md5_hash != $content_md5) 
								$unread = "false";
						
							$query = "UPDATE ttrss_entries 
								SET 
									title ='$entry_title', 
									link = '$entry_link', 
									updated = '$entry_timestamp', 
									content = '$entry_content',
									md5_hash = '$content_md5',
									unread = '$unread'
								WHERE
									id = '$entry_id'";
	
							$result = pg_query($query);
	
	//						print "$entry_guid - $entry_timestamp - $entry_title - 
	//							$entry_link - $entry_id<br>";
	
						}
	
					}
		
				}

			} else {

				if ($ext == "MarkAllRead")  {

					pg_query("UPDATE ttrss_entries SET unread = false 
						WHERE feed_id = '$feed'");
				}

			}
		}

		print "<table class=\"headlines\" width=\"100%\">";
		print "<tr><td colspan=\"2\" class=\"title\">" . $line["title"] . "</td></tr>";

		$result = pg_query("SELECT id,title,updated,unread,feed_id FROM
			ttrss_entries WHERE
			feed_id = '$feed' ORDER BY updated LIMIT ".HEADLINES_PER_PAGE." OFFSET $skip");

		$lnum = 0;

		while ($line = pg_fetch_assoc($result)) {

			$class = ($lnum % 2) ? "even" : "odd";

			if ($line["unread"] == "t") 
				$class .= "Unread";

			$content_link = "<a href=\"javascript:view(".$line["id"].",".$line["feed_id"].");\">".$line["title"]."</a>";
			
			print "<tr class='$class' id='RROW-".$line["id"]."'>";
			print "<td class='headlineUpdated'>".$line["updated"]."</td>";
			print "<td class='headlineTitle'>$content_link</td>";

			print "</tr>";

			++$lnum;
		}

		print "<tr><td colspan=\"2\" class=\"headlineToolbar\">";

		$next_skip = $skip + HEADLINES_PER_PAGE;
		$prev_skip = $skip - HEADLINES_PER_PAGE;

		print "<a class=\"button\" 
			href=\"javascript:viewfeed($feed, $prev_skip);\">Previous Page</a>";
		print "&nbsp;";
		print "<a class=\"button\" 
			href=\"javascript:viewfeed($feed, $next_skip);\">Next Page</a>";
		print "&nbsp;&nbsp;&nbsp;";
		
		print "<a class=\"button\" 
			href=\"javascript:viewfeed($feed, 0, 'MarkAllRead');\">Mark all as read</a>";

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


?>

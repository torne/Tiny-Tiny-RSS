<?
//	header("Content-Type: application/xml");

	require_once "config.php";
	require_once "functions.php";
	require_once "magpierss/rss_fetch.inc";

	error_reporting(0);

	$link = pg_connect(DB_CONN);	

	error_reporting (E_ERROR | E_WARNING | E_PARSE);

	if (!$link) {
		print "Could not connect to database. Please check local configuration.";
		return;
	}

	pg_query("set client_encoding = 'utf-8'");

	$op = $_GET["op"];
	$fetch = $_GET["fetch"];

	function outputFeedList($link) {

		print "<html><head>
			<title>Tiny Tiny RSS : Feedlist</title>
			<link rel=\"stylesheet\" href=\"tt-rss.css\" type=\"text/css\">
			<script type=\"text/javascript\" src=\"functions.js\"></script>
			<script type=\"text/javascript\" src=\"feedlist.js\"></script>
			<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\">
			</head><body>";
			
		$result = pg_query($link, "SELECT *,
			(SELECT count(id) FROM ttrss_entries 
				WHERE feed_id = ttrss_feeds.id) AS total,
			(SELECT count(id) FROM ttrss_entries
				WHERE feed_id = ttrss_feeds.id AND unread = true) as unread
			FROM ttrss_feeds ORDER BY title");			

		$actid = $_GET["actid"];

//		print "<table width=\"100%\" class=\"feedsList\" id=\"feedsList\">";

		print "<ul class=\"feedList\" id=\"feedList\">";

		$lnum = 0;

		$total_unread = 0;

		while ($line = pg_fetch_assoc($result)) {
		
			$feed = $line["title"];
			$feed_id = $line["id"];	  

			$subop = $_GET["subop"];
			
			$total = $line["total"];
			$unread = $line["unread"];
			
//			$class = ($lnum % 2) ? "even" : "odd";

			$class = "odd";

			if ($unread > 0) $class .= "Unread";

			if ($actid == $feed_id) {
				$class .= "Selected";
			}

			$total_unread += $unread;

//			print "<tr class=\"$class\" id=\"FEEDR-$feed_id\">";

			$icon_file = ICONS_DIR . "/$feed_id.ico";

			if ($subop != "piggie") {

				if (file_exists($icon_file) && filesize($icon_file) > 0) {
						$feed_icon = "<img src=\"" . ICONS_URL . "/$feed_id.ico\">";
				} else {
					$feed_icon = "<img src=\"images/blank_icon.png\">";
				}
			} else {
				$feed_icon = "<img width=\"16\" height=\"16\"
					src=\"http://madoka.spb.ru/stuff/fox/tiny_piggie.png\">";
			}
		
			$feed = "<a href=\"javascript:viewfeed($feed_id, 0);\">$feed</a>";
			
/*			if (ENABLE_FEED_ICONS) {
				print "<td>$feed_icon</td>";
			}
			
			print "<td id=\"FEEDN-$feed_id\">$feed</td>";
			print "<td>";
			print "<span id=\"FEEDU-$feed_id\">$unread</span>&nbsp;/&nbsp;";
			print "<span id=\"FEEDT-$feed_id\">$total</span>";
			print "</td>";

			print "</tr>"; */

			print "<li id=\"FEEDR-$feed_id\" class=\"$class\">";
			if (ENABLE_FEED_ICONS) {
				print "$feed_icon";
			}
			print "<span id=\"FEEDN-$feed_id\">$feed</span>";

			if ($unread != 0) {
				$fctr_class = "";
			} else {
				$fctr_class = "class=\"invisible\"";
			}

			print "<span $fctr_class id=\"FEEDCTR-$feed_id\">
				 (<span id=\"FEEDU-$feed_id\">$unread</span>)</span>";
			
			print "</li>";

			++$lnum;
		}

		print "</table>";

		print "<div class=\"invisible\" id=\"FEEDTU\">$total_unread</div>";
		print "<div class=\"invisible\" id=\"ACTFEEDID\">$actid</div>";

	}


	if ($op == "rpc") {

		$subop = $_GET["subop"];

		if ($subop == "mark") {
			$mark = $_GET["mark"];
			$id = pg_escape_string($_GET["id"]);

			if ($mark == "1") {
				$mark = "true";
			} else {
				$mark = "false";
			}

			$result = pg_query("UPDATE ttrss_entries SET marked = $mark
				WHERE id = '$id'");
		}

		if ($subop == "updateFeed") {
			$feed_id = pg_escape_string($_GET["feed"]);

			$result = pg_query($link, 
				"SELECT feed_url FROM ttrss_feeds WHERE id = '$feed_id'");

			if (pg_num_rows($result) > 0) {			
				$feed_url = pg_fetch_result($result, 0, "feed_url");
//				update_rss_feed($link, $feed_url, $feed_id);
			}

			print "DONE-$feed_id";

			return;
		}

		if ($subop == "forceUpdateAllFeeds") {
			update_all_feeds($link, true);			
		}

		if ($subop == "updateAllFeeds") {
			update_all_feeds($link, false);
		}
		
		if ($subop == "catchupPage") {

			$ids = split(",", $_GET["ids"]);

			foreach ($ids as $id) {

				pg_query("UPDATE ttrss_entries SET unread=false,last_read = NOW()
					WHERE id = '$id'");

			}

			print "Marked active page as read.";
		}

	}
	
	if ($op == "feeds") {

		$subop = $_GET["subop"];

		if ($subop == "catchupAll") {
			pg_query("UPDATE ttrss_entries SET last_read = NOW(),unread = false");
		}

		outputFeedList($link);

	}

	if ($op == "view") {

		$id = $_GET["id"];

		$result = pg_query("UPDATE ttrss_entries SET unread = false,last_read = NOW() WHERE id = '$id'");

		$addheader = $_GET["addheader"];

		$result = pg_query("SELECT title,link,content,feed_id,comments,
			(SELECT icon_url FROM ttrss_feeds WHERE id = feed_id) as icon_url 
			FROM ttrss_entries
			WHERE	id = '$id'");

		if ($addheader) {
			print "<html><head>
				<title>Tiny Tiny RSS : Article $id</title>
				<link rel=\"stylesheet\" href=\"tt-rss.css\" type=\"text/css\">
				<script type=\"text/javascript\" src=\"functions.js\"></script>
				<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\">
				</head><body>";
		}

		if ($result) {

			$line = pg_fetch_assoc($result);

			if ($line["icon_url"]) {
				$feed_icon = "<img class=\"feedIcon\" src=\"" . $line["icon_url"] . "\">";
			} else {
				$feed_icon = "&nbsp;";
			}

			if ($line["comments"] && $line["link"] != $line["comments"]) {
				$entry_comments = "(<a href=\"".$line["comments"]."\">Comments</a>)";
			} else {
				$entry_comments = "";
			}

			print "<div class=\"postReply\">";

			print "<div class=\"postHeader\"><table>";

			print "<tr><td><b>Title:</b></td>
				<td width='100%'>" . $line["title"] . "</td></tr>";
				
			print "<tr><td><b>Link:</b></td>
				<td width='100%'>
				<a href=\"" . $line["link"] . "\">".$line["link"]."</a>
				$entry_comments</td></tr>";
					
			print "</table></div>";

			print "<div class=\"postIcon\">" . $feed_icon . "</div>";
			print "<div class=\"postContent\">" . $line["content"] . "</div>";
			
			print "</div>";

		}

		if ($addheader) {
			print "</body></html>";
		}
	}

	if ($op == "viewfeed") {

		$feed = $_GET["feed"];
		$skip = $_GET["skip"];
		$subop = $_GET["subop"];
		$view_mode = $_GET["view"];
		$addheader = $_GET["addheader"];
		$limit = $_GET["limit"];

		if (!$skip) $skip = 0;

		if ($subop == "undefined") $subop = "";

		if ($addheader) {
			print "<html><head>
				<title>Tiny Tiny RSS : Feed $feed</title>
				<link rel=\"stylesheet\" href=\"tt-rss.css\" type=\"text/css\">
				<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\">
				<script type=\"text/javascript\" src=\"functions.js\"></script>
				<script type=\"text/javascript\" src=\"viewfeed.js\"></script>
				</head><body>";
		}

		// FIXME: check for null value here

		$result = pg_query("SELECT *,SUBSTRING(last_updated,1,16) as last_updated_s,
			EXTRACT(EPOCH FROM NOW()) - EXTRACT(EPOCH FROM last_updated) as update_timeout
			FROM ttrss_feeds WHERE id = '$feed'");

		if ($result) {

			$line = pg_fetch_assoc($result);

			if ($subop == "ForceUpdate" ||
				$line["last_updated"] == "" ||
				$line["update_timeout"] > MIN_UPDATE_TIME) {		

				update_rss_feed($link, $line["feed_url"], $feed);
				
			} else {

				if ($subop == "MarkAllRead")  {

					pg_query("UPDATE ttrss_entries SET unread = false,last_read = NOW() 
						WHERE feed_id = '$feed'");
				}
			}
		}

		print "<table class=\"headlinesList\" id=\"headlinesList\" width=\"100%\">";

		$feed_last_updated = "Updated: " . $line["last_updated"];

		$search = $_GET["search"];

		if ($search) {
			$search_query_part = "(upper(title) LIKE upper('%$search%') 
				OR content LIKE '%$search%') AND";
		} else {
			$search_query_part = "";
		}

		$view_query_part = "";

		if ($view_mode == "Starred") {
			$view_query_part = " marked = true AND ";
		}

		if ($view_mode == "Unread") {
			$view_query_part = " unread = true AND ";
		}

		$result = pg_query("SELECT count(id) AS total_entries 
			FROM ttrss_entries WHERE 
			$search_query_part
			feed_id = '$feed'");

		$total_entries = pg_fetch_result($result, 0, "total_entries");

/*		$result = pg_query("SELECT count(id) AS unread_entries 
			FROM ttrss_entries WHERE 
			$search_query_part
			unread = true AND
			feed_id = '$feed'");

		$unread_entries = pg_fetch_result($result, 0, "unread_entries"); */

		if ($limit != "All") {
			$limit_query_part = "LIMIT " . $limit;
		} 

		$result = pg_query("SELECT 
				id,title,updated,unread,feed_id,marked,link,
				EXTRACT(EPOCH FROM last_read) AS last_read_ts,
				EXTRACT(EPOCH FROM updated) AS updated_ts
			FROM
				ttrss_entries 
			WHERE
			$search_query_part
			$view_query_part
			feed_id = '$feed' ORDER BY updated DESC 
			$limit_query_part");

		$lnum = 0;
		
		$num_unread = 0;

		while ($line = pg_fetch_assoc($result)) {

			$class = ($lnum % 2) ? "even" : "odd";

			$id = $line["id"];
			$feed_id = $line["feed_id"];

			if ($line["last_read_ts"] < $line["updated_ts"] && $line["unread"] == "f") {
				$update_pic = "<img id='FUPDPIC-$id' src=\"images/updated.png\" 
					alt=\"Updated\">";
				++$num_unread;
			} else {
				$update_pic = "<img id='FUPDPIC-$id' src=\"images/blank_icon.png\" 
					alt=\"Updated\">";
			}

			if ($line["unread"] == "t") {
				$class .= "Unread";
				++$num_unread;
			}

			if ($line["marked"] == "t") {
				$marked_pic = "<img id=\"FMARKPIC-$id\" src=\"images/mark_set.png\" 
					alt=\"Reset mark\" onclick='javascript:toggleMark($id, false)'>";
			} else {
				$marked_pic = "<img id=\"FMARKPIC-$id\" src=\"images/mark_unset.png\" 
					alt=\"Set mark\" onclick='javascript:toggleMark($id, true)'>";
			}

			$content_link = "<a id=\"FTITLE-$id\" href=\"javascript:view($id,$feed_id);\">" .
				$line["title"] . "</a>";
				
			print "<tr class='$class' id='RROW-$id'";
			// onclick=\"javascript:view($id,$feed_id)\">

			print "<td valign='center' align='center'
				class='headlineUpdateMark'>$update_pic</td>";

			print "<td valign='center' align='center'
				class='headlineUpdateMark'>$marked_pic</td>";

			print "<td class='headlineUpdated' width='25%'>
				<a href=\"javascript:view($id,$feed_id);\">".$line["updated"]."</a></td>";
			print "<td width='70%' class='headlineTitle'>$content_link</td>";

			print "</tr>";

			++$lnum;
		}

		if ($lnum == 0) {
			print "<tr><td align='center'>No entries found.</td></tr>";
		}

		while ($lnum < HEADLINES_PER_PAGE) {
			++$lnum;
			print "<tr><td>&nbsp;</td></tr>";
		}
		
		print "</table>";

		$result = pg_query("SELECT id, (SELECT count(id) FROM ttrss_entries 
			WHERE feed_id = ttrss_feeds.id) AS total,
		(SELECT count(id) FROM ttrss_entries
			WHERE feed_id = ttrss_feeds.id AND unread = true) as unread
		FROM ttrss_feeds WHERE id = '$feed'");			

		$total = pg_fetch_result($result, 0, "total");
		$unread = pg_fetch_result($result, 0, "unread");

		// update unread/total counters and status for active feed in the feedlist 
		// kludge, because iframe doesn't seem to support onload() 
		
		print "<script type=\"text/javascript\">
			document.onkeydown = hotkey_handler;

			var p_document = parent.frames['feeds-frame'].document;

			var feedr = p_document.getElementById(\"FEEDR-\" + $feed);
			var feedu = p_document.getElementById(\"FEEDU-\" + $feed);

			if (feedu) {
				feedu.innerHTML = \"$unread\";
			}

			var feedctr = p_document.getElementById(\"FEEDCTR-\" + $feed);

			if ($unread > 0 && !feedr.className.match(\"Unread\")) {
					feedr.className = feedr.className + \"Unread\";
					feedctr.className = '';
			} else if ($unread <= 0) {	
					feedr.className = feedr.className.replace(\"Unread\", \"\");
					feedctr.className = 'invisible';
			}	

//			p_notify(\"\");

		</script>";

		if ($addheader) {
			print "</body></html>";
		}

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

		if ($subop == "editSave") {
			$feed_title = pg_escape_string($_GET["t"]);
			$feed_link = pg_escape_string($_GET["l"]);
			$feed_id = $_GET["id"];

			$result = pg_query("UPDATE ttrss_feeds SET 
				title = '$feed_title', feed_url = '$feed_link' WHERE id = '$feed_id'");			

		}

		if ($subop == "remove") {

			if (!WEB_DEMO_MODE) {

				$ids = split(",", $_GET["ids"]);

				foreach ($ids as $id) {
					pg_query("BEGIN");
					pg_query("DELETE FROM ttrss_entries WHERE feed_id = '$id'");
					pg_query("DELETE FROM ttrss_feeds WHERE id = '$id'");
					pg_query("COMMIT");
					
					if (file_exists(ICONS_DIR . "/$id.ico")) {
						unlink(ICONS_DIR . "/$id.ico");
					}
				}
			}
		}

		if ($subop == "add") {
		
			if (!WEB_DEMO_MODE) {

				$feed_link = pg_escape_string($_GET["link"]);
					
				$result = pg_query(
					"INSERT INTO ttrss_feeds (feed_url,title) VALUES ('$feed_link', '')");

				$result = pg_query(
					"SELECT id FROM ttrss_feeds WHERE feed_url = '$feed_link'");

				$feed_id = pg_fetch_result($result, 0, "id");

				if ($feed_id) {
					update_rss_feed($link, $feed_link, $feed_id);
				}
			}
		}

		print "<table class=\"prefAddFeed\"><tr>
			<td><input id=\"fadd_link\"></td>
			<td colspan=\"4\" align=\"right\">
				<a class=\"button\" href=\"javascript:addFeed()\">Add feed</a></td></tr>
		</table>";

		$result = pg_query("SELECT 
				id,title,feed_url,substring(last_updated,1,16) as last_updated
			FROM 
				ttrss_feeds ORDER by title");

		print "<p><table width=\"100%\" class=\"prefFeedList\" id=\"prefFeedList\">";
		print "<tr class=\"title\">
					<td>&nbsp;</td><td>Select</td><td width=\"40%\">Title</td>
					<td width=\"40%\">Link</td><td>Last updated</td></tr>";
		
		$lnum = 0;
		
		while ($line = pg_fetch_assoc($result)) {

			$class = ($lnum % 2) ? "even" : "odd";

			$feed_id = $line["id"];

			$edit_feed_id = $_GET["id"];

			if ($subop == "edit" && $feed_id != $edit_feed_id) {
				$class .= "Grayed";
			}

			print "<tr class=\"$class\" id=\"FEEDR-$feed_id\">";

			$icon_file = ICONS_DIR . "/$feed_id.ico";

			if (file_exists($icon_file) && filesize($icon_file) > 0) {
					$feed_icon = "<img width=\"16\" height=\"16\"
						src=\"" . ICONS_URL . "/$feed_id.ico\">";
			} else {
				$feed_icon = "&nbsp;";
			}
			print "<td align='center'>$feed_icon</td>";		

			if (!$edit_feed_id || $subop != "edit") {

				print "<td><input onclick='toggleSelectRow(this);' 
				type=\"checkbox\" id=\"FRCHK-".$line["id"]."\"></td>";

				print "<td><a href=\"javascript:editFeed($feed_id);\">" . 
					$line["title"] . "</td>";		
				print "<td><a href=\"javascript:editFeed($feed_id);\">" . 
					$line["feed_url"] . "</td>";		
			

			} else if ($feed_id != $edit_feed_id) {

				print "<td><input disabled=\"true\" type=\"checkbox\" 
					id=\"FRCHK-".$line["id"]."\"></td>";

				print "<td>".$line["title"]."</td>";		
				print "<td>".$line["feed_url"]."</td>";		

			} else {

				print "<td><input disabled=\"true\" type=\"checkbox\"></td>";

				print "<td><input id=\"iedit_title\" value=\"".$line["title"]."\"></td>";
				print "<td><input id=\"iedit_link\" value=\"".$line["feed_url"]."\"></td>";
						
			}

			if (!$line["last_updated"]) $line["last_updated"] = "Never";

			print "<td>" . $line["last_updated"] . "</td>";
			
			print "</tr>";

			++$lnum;
		}

		if ($lnum == 0) {
			print "<tr><td colspan=\"5\" align=\"center\">No feeds defined.</td></tr>";
		}

		print "</table>";

		print "<p>";

		if ($subop == "edit") {
			print "Edit feed:&nbsp;
				<input type=\"submit\" class=\"button\" 
					onclick=\"javascript:feedEditCancel()\" value=\"Cancel\">
				<input type=\"submit\" class=\"button\" 
					onclickf=\"javascript:feedEditSave()\" value=\"Save\">";
			} else {

			print "
				Selection:&nbsp;
			<input type=\"submit\" class=\"button\" 
				onclick=\"javascript:editSelectedFeed()\" value=\"Edit\">
			<input type=\"submit\" class=\"button\" 
				onclick=\"javascript:removeSelectedFeeds()\" value=\"Remove\">";
				
			if (ENABLE_PREFS_CATCHUP_UNCATCHUP) {
				print "
				<input type=\"submit\" class=\"button\" 
					onclick=\"javascript:readSelectedFeeds()\" value=\"Mark as read\">
				<input type=\"submit\" class=\"button\" 
					onclick=\"javascript:unreadSelectedFeeds()\" value=\"Mark as unread\">&nbsp;";
			}
			print "
			All feeds: 
				<input type=\"submit\" 
					class=\"button\" onclick=\"opml.php?op=Export\" value=\"Export OPML\">";
		
			}

	}

	if ($op == "pref-filters") {

		$subop = $_GET["subop"];

		if ($subop == "editSave") {

			$regexp = pg_escape_string($_GET["r"]);
			$descr = pg_escape_string($_GET["d"]);
			$match = pg_escape_string($_GET["m"]);
			$filter_id = pg_escape_string($_GET["id"]);
			
			$result = pg_query("UPDATE ttrss_filters SET 
				regexp = '$regexp', 
				description = '$descr',
				filter_type = (SELECT id FROM ttrss_filter_types WHERE
					description = '$match')
				WHERE id = '$filter_id'");
		}

		if ($subop == "remove") {

			if (!WEB_DEMO_MODE) {

				$ids = split(",", $_GET["ids"]);

				foreach ($ids as $id) {
					pg_query("DELETE FROM ttrss_filters WHERE id = '$id'");
					
				}
			}
		}

		if ($subop == "add") {
		
			if (!WEB_DEMO_MODE) {

				$regexp = pg_escape_string($_GET["regexp"]);
				$match = pg_escape_string($_GET["match"]);
					
				$result = pg_query(
					"INSERT INTO ttrss_filters (regexp,filter_type) VALUES 
						('$regexp', (SELECT id FROM ttrss_filter_types WHERE
							description = '$match'))");
			} 
		}

		$result = pg_query("SELECT description 
			FROM ttrss_filter_types ORDER BY description");

		$filter_types = array();

		while ($line = pg_fetch_assoc($result)) {
			array_push($filter_types, $line["description"]);
		}

		print "<table class=\"prefAddFeed\"><tr>
			<td><input id=\"fadd_regexp\"></td>
			<td>";
			print_select("fadd_match", "Title", $filter_types);	
	
		print"</td><td colspan=\"4\" align=\"right\">
				<a class=\"button\" href=\"javascript:addFilter()\">Add filter</a></td></tr>
		</table>";

		$result = pg_query("SELECT 
				id,regexp,description,
				(SELECT name FROM ttrss_filter_types WHERE 
					id = filter_type) as filter_type_name,
				(SELECT description FROM ttrss_filter_types 
					WHERE id = filter_type) as filter_type_descr
			FROM 
				ttrss_filters ORDER by regexp");

		print "<p><table width=\"100%\" class=\"prefFilterList\" id=\"prefFilterList\">";

		print "<tr class=\"title\">
					<td width=\"5%\">Select</td><td width=\"40%\">Filter expression</td>
					<td width=\"40%\">Description</td><td width=\"10%\">Match</td></tr>";
		
		$lnum = 0;
		
		while ($line = pg_fetch_assoc($result)) {

			$class = ($lnum % 2) ? "even" : "odd";

			$filter_id = $line["id"];
			$edit_filter_id = $_GET["id"];

			if ($subop == "edit" && $filter_id != $edit_filter_id) {
				$class .= "Grayed";
			}

			print "<tr class=\"$class\" id=\"FILRR-$filter_id\">";

			$line["regexp"] = htmlspecialchars($line["regexp"]);
			$line["description"] = htmlspecialchars($line["description"]);

			if (!$edit_filter_id || $subop != "edit") {

				if (!$line["description"]) $line["description"] = "[No description]";

				print "<td><input onclick='toggleSelectRow(this);' 
				type=\"checkbox\" id=\"FICHK-".$line["id"]."\"></td>";

				print "<td><a href=\"javascript:editFilter($filter_id);\">" . 
					$line["regexp"] . "</td>";		
					
				print "<td><a href=\"javascript:editFilter($filter_id);\">" . 
					$line["description"] . "</td>";			

				print "<td>".$line["filter_type_descr"]."</td>";

			} else if ($filter_id != $edit_filter_id) {

				if (!$line["description"]) $line["description"] = "[No description]";

				print "<td><input disabled=\"true\" type=\"checkbox\" 
					id=\"FICHK-".$line["id"]."\"></td>";

				print "<td>".$line["regexp"]."</td>";		
				print "<td>".$line["description"]."</td>";		
				print "<td>".$line["filter_type_descr"]."</td>";

			} else {

				print "<td><input disabled=\"true\" type=\"checkbox\"></td>";

				print "<td><input id=\"iedit_regexp\" value=\"".$line["regexp"].
					"\"></td>";

				print "<td><input id=\"iedit_descr\" value=\"".$line["description"].
					"\"></td>";

				print "<td>";
				print_select("iedit_match", $line["filter_type_descr"], $filter_types);
				print "</td>";
						
			}
				
			
			print "</tr>";

			++$lnum;
		}

		if ($lnum == 0) {
			print "<tr><td colspan=\"4\" align=\"center\">No filters defined.</td></tr>";
		}

		print "</table>";

		print "<p>";

		if ($subop == "edit") {
			print "Edit feed:
				<input type=\"submit\" class=\"button\" 
					onclick=\"javascript:filterEditCancel()\" value=\"Cancel\">
				<input type=\"submit\" class=\"button\" 
					onclick=\"javascript:filterEditSave()\" value=\"Save\">";
					
		} else {

			print "
				Selection:
			<input type=\"submit\" class=\"button\" 
				onclick=\"javascript:editSelectedFilter()\" value=\"Edit\">
			<input type=\"submit\" class=\"button\" 
				onclick=\"javascript:removeSelectedFilters()\" value=\"Remove\">";
		}
	}

	if ($op == "error") {
		print "<div width=\"100%\" align='center'>";
		$msg = $_GET["msg"];
		print $msg;
		print "</div>";
	}

	pg_close($link);
?>

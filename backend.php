<?
	session_start();

	if (!$_SESSION["uid"]) { exit; }

	define(SCHEMA_VERSION, 2);

	require_once "config.php";
	require_once "db.php";
	require_once "db-prefs.php";
	require_once "functions.php";
	require_once "magpierss/rss_fetch.inc";

	$op = $_REQUEST["op"];

	if (($op == "rpc" || $op == "updateAllFeeds") && !$_REQUEST["noxml"]) {
		header("Content-Type: application/xml");
	}

	$script_started = getmicrotime();

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);	

	if (!$link) {
		if (DB_TYPE == "mysql") {
			print mysql_error();
		}
		// PG seems to display its own errors just fine by default.		
		return;
	}

	if (DB_TYPE == "pgsql") {
		pg_query("set client_encoding = 'utf-8'");
	}

/*
	$result = db_query($link, "SELECT schema_version FROM ttrss_version");

	$schema_version = db_fetch_result($result, 0, "schema_version");

	if ($schema_version != SCHEMA_VERSION) {
		print "Error: database schema is invalid
			(got version $schema_version; expected ".SCHEMA_VERSION.")";
		return;
	}
*/

	$fetch = $_GET["fetch"];

	/* FIXME this needs reworking */

	function getGlobalCounters($link) {
		$result = db_query($link, "SELECT count(id) as c_id FROM ttrss_entries,ttrss_user_entries
			WHERE unread = true AND 
			ttrss_user_entries.ref_id = ttrss_entries.id AND 
			owner_uid = " . $_SESSION["uid"]);
		$c_id = db_fetch_result($result, 0, "c_id");
		print "<counter id='global-unread' counter='$c_id'/>";
	}

	function getTagCounters($link) {
		$result = db_query($link, "SELECT tag_name,count(ttrss_entries.id) AS count
			FROM ttrss_tags,ttrss_entries,ttrss_user_entries WHERE
			ttrss_user_entries.ref_id = ttrss_entries.id AND 
			ttrss_tags.owner_uid = ".$_SESSION["uid"]." AND
			post_id = ttrss_entries.id AND unread = true GROUP BY tag_name 
		UNION
			select tag_name,0 as count FROM ttrss_tags
			WHERE ttrss_tags.owner_uid = ".$_SESSION["uid"]);

		$tags = array();

		while ($line = db_fetch_assoc($result)) {
			$tags[$line["tag_name"]] += $line["count"];
		}

		foreach (array_keys($tags) as $tag) {
			$unread = $tags[$tag];			

			$tag = htmlspecialchars($tag);
			print "<tag id=\"$tag\" counter=\"$unread\"/>";
		} 
	}

	function getLabelCounters($link) {

		$result = db_query($link, "SELECT count(id) as count FROM ttrss_entries,ttrss_user_entries
			WHERE marked = true AND ttrss_user_entries.ref_id = ttrss_entries.id AND 
			unread = true AND owner_uid = ".$_SESSION["uid"]);

		$count = db_fetch_result($result, 0, "count");

		print "<label id=\"-1\" counter=\"$count\"/>";

		$result = db_query($link, "SELECT owner_uid,id,sql_exp,description FROM
			ttrss_labels WHERE owner_uid = ".$_SESSION["uid"]." ORDER by description");
	
		while ($line = db_fetch_assoc($result)) {

			$id = -$line["id"] - 11;

			error_reporting (0);

			$tmp_result = db_query($link, "SELECT count(id) as count FROM ttrss_user_entries,ttrss_entries
				WHERE (" . $line["sql_exp"] . ") AND unread = true AND 
				ttrss_user_entries.ref_id = ttrss_entries.id AND 
				owner_uid = ".$_SESSION["uid"]);

			$count = db_fetch_result($tmp_result, 0, "count");

			print "<label id=\"$id\" counter=\"$count\"/>";

			error_reporting (E_ERROR | E_WARNING | E_PARSE);
	
		}
	}

	function getFeedCounter($link, $id) {
	
		$result = db_query($link, "SELECT 
				count(id) as count FROM ttrss_entries,ttrss_user_entries
			WHERE feed_id = '$id' AND unread = true
			AND ttrss_user_entries.ref_id = ttrss_entries.id");
	
			$count = db_fetch_result($result, 0, "count");
			
			print "<feed id=\"$id\" counter=\"$count\"/>";		
	}

	function getFeedCounters($link) {
	
		$result = db_query($link, "SELECT id,
			(SELECT count(id) 
				FROM ttrss_entries,ttrss_user_entries 
				WHERE feed_id = ttrss_feeds.id AND ttrss_user_entries.ref_id = ttrss_entries.id
				AND unread = true AND owner_uid = ".$_SESSION["uid"].") as count
			FROM ttrss_feeds WHERE owner_uid = ".$_SESSION["uid"]);
	
		while ($line = db_fetch_assoc($result)) {
		
			$id = $line["id"];
			$count = $line["count"];

			print "<feed id=\"$id\" counter=\"$count\"/>";
		}
	}

	function outputFeedList($link, $tags = false) {

		print "<html><head>
			<title>Tiny Tiny RSS : Feedlist</title>
			<link rel=\"stylesheet\" href=\"tt-rss.css\" type=\"text/css\">";

		if (get_pref($link, 'USE_COMPACT_STYLESHEET')) {
			print "<link rel=\"stylesheet\" type=\"text/css\" 
				href=\"tt-rss_compact.css\"/>";
		} else {
			print "<link title=\"Compact Stylesheet\" rel=\"alternate stylesheet\" 
					type=\"text/css\" href=\"tt-rss_compact.css\"/>";
		}

		print "<script type=\"text/javascript\" src=\"functions.js\"></script>
			<script type=\"text/javascript\" src=\"feedlist.js\"></script>
			<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\">
			</head><body onload=\"init()\">";

		print "<ul class=\"feedList\" id=\"feedList\">";

		$owner_uid = $_SESSION["uid"];

		if (!$tags) {

			/* virtual feeds */

			$result = db_query($link, "SELECT count(id) as num_starred 
				FROM ttrss_entries,ttrss_user_entries 
				WHERE marked = true AND 
				ttrss_user_entries.ref_id = ttrss_entries.id AND
				unread = true AND owner_uid = '$owner_uid'");
			$num_starred = db_fetch_result($result, 0, "num_starred");

			$class = "virt";

			if ($num_starred > 0) $class .= "Unread";

			printFeedEntry(-1, $class, "Starred articles", $num_starred, 
				"images/mark_set.png", $link);

			if (get_pref($link, 'ENABLE_LABELS')) {
	
				$result = db_query($link, "SELECT id,sql_exp,description FROM
					ttrss_labels WHERE owner_uid = '$owner_uid' ORDER by description");
		
				if (db_num_rows($result) > 0) {
					print "<li><hr></li>";
				}
		
				while ($line = db_fetch_assoc($result)) {
	
					error_reporting (0);
		
					$tmp_result = db_query($link, "SELECT count(id) as count FROM ttrss_entries,ttrss_user_entries
						WHERE (" . $line["sql_exp"] . ") AND unread = true AND
						ttrss_user_entries.ref_id = ttrss_entries.id
						AND owner_uid = '$owner_uid'");
	
					$count = db_fetch_result($tmp_result, 0, "count");
	
					$class = "label";
	
					if ($count > 0) {
						$class .= "Unread";
					}
					
					error_reporting (E_ERROR | E_WARNING | E_PARSE);
	
					printFeedEntry(-$line["id"]-11, 
						$class, $line["description"], $count, "images/label.png", $link);
		
				}
			}
	
			print "<li><hr></li>";

			$result = db_query($link, "SELECT *,
				(SELECT count(id) FROM ttrss_entries,ttrss_user_entries
					WHERE feed_id = ttrss_feeds.id AND 
					ttrss_user_entries.ref_id = ttrss_entries.id AND
					owner_uid = '$owner_uid') AS total,
				(SELECT count(id) FROM ttrss_entries,ttrss_user_entries
					WHERE feed_id = ttrss_feeds.id AND unread = true
						AND ttrss_user_entries.ref_id = ttrss_entries.id
						AND owner_uid = '$owner_uid') as unread
				FROM ttrss_feeds WHERE owner_uid = '$owner_uid' ORDER BY title");			
	
			$actid = $_GET["actid"];
	
			/* real feeds */
	
			$lnum = 0;
	
			$total_unread = 0;
	
			while ($line = db_fetch_assoc($result)) {
			
				$feed = $line["title"];
				$feed_id = $line["id"];	  
	
				$subop = $_GET["subop"];
				
				$total = $line["total"];
				$unread = $line["unread"];
				
	//			$class = ($lnum % 2) ? "even" : "odd";
	
				$class = "feed";
	
				if ($unread > 0) $class .= "Unread";
	
				if ($actid == $feed_id) {
					$class .= "Selected";
				}
	
				$total_unread += $unread;
	
				printFeedEntry($feed_id, $class, $feed, $unread, "icons/$feed_id.ico", $link);
	
				++$lnum;
			}
		} else {

			// tags

			$result = db_query($link, "SELECT tag_name,count(ttrss_entries.id) AS count
				FROM ttrss_tags,ttrss_entries WHERE
				post_id = ttrss_entries.id AND unread = true 
				AND ttrss_tags.owner_uid = '$owner_uid' GROUP BY tag_name	
			UNION
				select tag_name,0 as count FROM ttrss_tags WHERE owner_uid = '$owner_uid'
			ORDER BY tag_name");
	
			$tags = array();
	
			while ($line = db_fetch_assoc($result)) {
				$tags[$line["tag_name"]] += $line["count"];
			}
	
			foreach (array_keys($tags) as $tag) {
	
				$unread = $tags[$tag];
	
				$class = "odd";
	
				if ($unread > 0) {
					$class .= "Unread";
				}
	
				printFeedEntry($tag, $class, $tag, $unread, "images/tag.png", $link);
	
			} 

		}

		if (db_num_rows($result) == 0) {
			print "<li>No tags/feeds to display.</li>";
		}

		print "</ul>";

		print "<div class=\"invisible\" id=\"FEEDTU\">$total_unread</div>";

	}


	if ($op == "rpc") {

		$subop = $_GET["subop"];

		if ($subop == "getLabelCounters") {
			$aid = $_GET["aid"];		
			print "<rpc-reply>";
			getLabelCounters($link);
			if ($aid) {
				getFeedCounter($link, $aid);
			}
			print "</rpc-reply>";
		}

		if ($subop == "getFeedCounters") {
			print "<rpc-reply>";
			getFeedCounters($link);
			print "</rpc-reply>";
		}

		if ($subop == "getAllCounters") {
			print "<rpc-reply>";
			getLabelCounters($link);
			getFeedCounters($link);
			getTagCounters($link);
			getGlobalCounters($link);
			print "</rpc-reply>";
		}

		if ($subop == "mark") {
			$mark = $_GET["mark"];
			$id = db_escape_string($_GET["id"]);

			if ($mark == "1") {
				$mark = "true";
			} else {
				$mark = "false";
			}

			// FIXME this needs collision testing

			$result = db_query($link, "UPDATE ttrss_user_entries SET marked = $mark
				WHERE ref_id = '$id' AND owner_uid = " . $_SESSION["uid"]);
		}

		if ($subop == "updateFeed") {
			$feed_id = db_escape_string($_GET["feed"]);

			$result = db_query($link, 
				"SELECT feed_url FROM ttrss_feeds WHERE id = '$feed_id'");

			if (db_num_rows($result) > 0) {			
				$feed_url = db_fetch_result($result, 0, "feed_url");
//				update_rss_feed($link, $feed_url, $feed_id);
			}

			print "DONE-$feed_id";

			return;
		}

		if ($subop == "forceUpdateAllFeeds" || $subop == "updateAllFeeds") {
		
			update_all_feeds($link, true);			

			$omode = $_GET["omode"];

			if (!$omode) $omode = "tfl";

			print "<rpc-reply>";
			if (strchr($omode, "l")) getLabelCounters($link);
			if (strchr($omode, "f")) getFeedCounters($link);
			if (strchr($omode, "t")) getTagCounters($link);
			getGlobalCounters($link);
			print "</rpc-reply>";
		}
		
		if ($subop == "catchupPage") {

			$ids = split(",", $_GET["ids"]);

			foreach ($ids as $id) {

				db_query($link, "UPDATE ttrss_entries SET unread=false,last_read = NOW()
					WHERE id = '$id'");

			}

			print "Marked active page as read.";
		}

		if ($subop == "sanityCheck") {

			$error_code = 0;

			$result = db_query($link, "SELECT schema_version FROM ttrss_version");

			$schema_version = db_fetch_result($result, 0, "schema_version");

			if ($schema_version != SCHEMA_VERSION) {
				$error_code = 5;
			}

			print "<error code='$error_code'/>";
		}

		if ($subop == "globalPurge") {

			print "<rpc-reply>";
			global_purge_old_posts($link, true);
			print "</rpc-reply>";

		}

	}
	
	if ($op == "feeds") {

		$tags = $_GET["tags"];

		$subop = $_GET["subop"];

		if ($subop == "catchupAll") {
			db_query($link, "UPDATE ttrss_entries SET 
				last_read = NOW(),unread = false WHERE owner_uid = " . $_SESSION["uid"]);
		}

		outputFeedList($link, $tags);

	}

	if ($op == "view") {

		$id = $_GET["id"];
		$feed_id = $_GET["feed"];

		$result = db_query($link, "UPDATE ttrss_user_entries 
			SET unread = false,last_read = NOW() 
			WHERE ref_id = '$id' AND feed_id = '$feed_id' AND owner_uid = " . $_SESSION["uid"]);

		$addheader = $_GET["addheader"];

		$result = db_query($link, "SELECT title,link,content,feed_id,comments,
			(SELECT icon_url FROM ttrss_feeds WHERE id = feed_id) as icon_url 
			FROM ttrss_entries,ttrss_user_entries
			WHERE	id = '$id' AND ref_id = id");

		if ($addheader) {
			print "<html><head>
				<title>Tiny Tiny RSS : Article $id</title>
				<link rel=\"stylesheet\" href=\"tt-rss.css\" type=\"text/css\">
				<script type=\"text/javascript\" src=\"functions.js\"></script>
				<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\">
				</head><body>";
		}

		if ($result) {

			$line = db_fetch_assoc($result);

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

			print "<script type=\"text/javascript\">
				update_label_counters('$feed_id');
			</script>";
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

		if (!$feed) {
			print "Error: no feed to display.";
			return;
		}

		if (!$skip) $skip = 0;

		if ($subop == "undefined") $subop = "";

		if ($addheader) {
			print "<html><head>
				<title>Tiny Tiny RSS : Feed $feed</title>
				<link rel=\"stylesheet\" href=\"tt-rss.css\" type=\"text/css\">";

			if (get_pref($link, 'USE_COMPACT_STYLESHEET')) {
				print "<link rel=\"stylesheet\" 
						type=\"text/css\" href=\"tt-rss_compact.css\"/>";

			} else {
				print "<link title=\"Compact Stylesheet\" rel=\"alternate stylesheet\" 
						type=\"text/css\" href=\"tt-rss_compact.css\"/>";
			}
			print "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\">	
				<script type=\"text/javascript\" src=\"functions.js\"></script>
				<script type=\"text/javascript\" src=\"viewfeed.js\"></script>
				</head><body onload='init()'>";
		}

		if ($subop == "ForceUpdate" && sprintf("%d", $feed) > 0) {

			$tmp_result = db_query($link, "SELECT feed_url FROM ttrss_feeds
				WHERE id = '$feed'");

			$feed_url = db_fetch_result($tmp_result, 0, "feed_url");

			update_rss_feed($link, $feed_url, $feed);

		}

		if ($subop == "MarkAllRead")  {

			return; // FIXME disabled

			if (sprintf("%d", $feed) != 0) {
			
				if ($feed > 0) {
					db_query($link, "UPDATE ttrss_entries 
						SET unread = false,last_read = NOW() 
						WHERE feed_id = '$feed'");
						
				} else if ($feed < 0 && $feed > -10) { // special, like starred

					if ($feed == -1) {
						db_query($link, "UPDATE ttrss_entries 
							SET unread = false,last_read = NOW()
							WHERE marked = true AND owner_uid = ".$_SESSION["uid"]);
					}
			
				} else if ($feed < -10) { // label

					$label_id = -$feed - 11;

					$tmp_result = db_query($link, "SELECT sql_exp FROM ttrss_labels
						WHERE id = '$label_id'");

					if ($tmp_result) {
						$sql_exp = db_fetch_result($tmp_result, 0, "sql_exp");

						db_query($link, "UPDATE ttrss_entries 
							SET unread = false,last_read = NOW()
							WHERE $sql_exp AND owner_uid = ".$_SESSION["uid"]);
					}
				}
			} else { // tag
				// FIXME, implement catchup for tags
			}

		}

		print "<table class=\"headlinesList\" id=\"headlinesList\" width=\"100%\">";

		$search = $_GET["search"];

		$search_mode = $_GET["smode"];

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

		if ($view_mode == "Unread or Starred") {
			$view_query_part = " (unread = true OR marked = true) AND ";
		}

		if ($view_mode == "Unread or Updated") {
			$view_query_part = " (unread = true OR last_read is NULL) AND ";
		}

/*		$result = db_query($link, "SELECT count(id) AS total_entries 
			FROM ttrss_entries WHERE 
			$search_query_part
			feed_id = '$feed'");

		$total_entries = db_fetch_result($result, 0, "total_entries"); */

/*		$result = db_query("SELECT count(id) AS unread_entries 
			FROM ttrss_entries WHERE 
			$search_query_part
			unread = true AND
			feed_id = '$feed'");

		$unread_entries = db_fetch_result($result, 0, "unread_entries"); */

		if ($limit && $limit != "All") {
			$limit_query_part = "LIMIT " . $limit;
		} 

		$vfeed_query_part = "";

		// override query strategy and enable feed display when searching globally
		if ($search_mode == "All feeds") {
			$query_strategy_part = "id > 0";
			$vfeed_query_part = "(SELECT title FROM ttrss_feeds WHERE
				id = feed_id) as feed_title,";
		} else if (sprintf("%d", $feed) == 0) {
			$query_strategy_part = "ttrss_entries.id > 0";
			$vfeed_query_part = "(SELECT title FROM ttrss_feeds WHERE
				id = feed_id) as feed_title,";
		} else if ($feed >= 0) {
			$query_strategy_part = "feed_id = '$feed'";
		} else if ($feed == -1) { // starred virtual feed
			$query_strategy_part = "marked = true";
			$vfeed_query_part = "(SELECT title FROM ttrss_feeds WHERE
				id = feed_id) as feed_title,";
		} else if ($feed <= -10) { // labels
			$label_id = -$feed - 11;

			$tmp_result = db_query($link, "SELECT sql_exp FROM ttrss_labels
				WHERE id = '$label_id'");
		
			$query_strategy_part = db_fetch_result($tmp_result, 0, "sql_exp");
	
			$vfeed_query_part = "(SELECT title FROM ttrss_feeds WHERE
				id = feed_id) as feed_title,";
		} else {
			$query_strategy_part = "id > 0"; // dumb
		}


		$order_by = "updated DESC";

//		if ($feed < -10) {
//			$order_by = "feed_id,updated DESC";
//		}

		if ($feed < -10) error_reporting (0);

		if (sprintf("%d", $feed) != 0) {

			$result = db_query($link, "SELECT 
					id,title,updated,unread,feed_id,marked,link,last_read,
					SUBSTRING(last_read,1,19) as last_read_noms,
					$vfeed_query_part
					SUBSTRING(updated,1,19) as updated_noms
				FROM
					ttrss_entries,ttrss_user_entries
				WHERE
				ttrss_user_entries.ref_id = ttrss_entries.id AND
				owner_uid = '".$_SESSION["uid"]."' AND
				$search_query_part
				$view_query_part
				$query_strategy_part ORDER BY $order_by
				$limit_query_part");

		} else {
			// browsing by tag

			$result = db_query($link, "SELECT
				ttrss_entries.id as id,title,updated,unread,feed_id,
				marked,link,last_read,
				SUBSTRING(last_read,1,19) as last_read_noms,
				$vfeed_query_part
				SUBSTRING(updated,1,19) as updated_noms
				FROM
					ttrss_entries,ttrss_tags
				WHERE
					ttrss_entries.owner_uid = '".$_SESSION["uid"]."' AND
					post_id = ttrss_entries.id AND tag_name = '$feed' AND
					$view_query_part
					$search_query_part
					$query_strategy_part ORDER BY $order_by
				$limit_query_part");	
		}

		if (!$result) {
			print "<tr><td colspan='4' align='center'>
				Could not display feed (query failed). Please check match syntax or local configuration.</td></tr>";
			return;
		}

		$lnum = 0;

		error_reporting (E_ERROR | E_WARNING | E_PARSE);

		$num_unread = 0;

		while ($line = db_fetch_assoc($result)) {

			$class = ($lnum % 2) ? "even" : "odd";

			$id = $line["id"];
			$feed_id = $line["feed_id"];

//			printf("L %d (%s) &gt; U %d (%s) = %d<br>", 
//				strtotime($line["last_read_noms"]), $line["last_read_noms"],
//				strtotime($line["updated"]), $line["updated"],
//				strtotime($line["last_read"]) >= strtotime($line["updated"]));

/*			if ($line["last_read"] != "" && $line["updated"] != "" &&
				strtotime($line["last_read_noms"]) < strtotime($line["updated_noms"])) {

				$update_pic = "<img id='FUPDPIC-$id' src=\"images/updated.png\" 
					alt=\"Updated\">";

			} else {

				$update_pic = "<img id='FUPDPIC-$id' src=\"images/blank_icon.gif\" 
					alt=\"Updated\">";

			} */

			if ($line["last_read"] == "" && 
					($line["unread"] != "t" && $line["unread"] != "1")) {

				$update_pic = "<img id='FUPDPIC-$id' src=\"images/updated.png\" 
					alt=\"Updated\">";
			} else {
				$update_pic = "<img id='FUPDPIC-$id' src=\"images/blank_icon.gif\" 
					alt=\"Updated\">";
			}

			if ($line["unread"] == "t" || $line["unread"] == "1") {
				$class .= "Unread";
				++$num_unread;
			}

			if ($line["marked"] == "t" || $line["marked"] == "1") {
				$marked_pic = "<img id=\"FMARKPIC-$id\" src=\"images/mark_set.png\" 
					alt=\"Reset mark\" onclick='javascript:toggleMark($id, false)'>";
			} else {
				$marked_pic = "<img id=\"FMARKPIC-$id\" src=\"images/mark_unset.png\" 
					alt=\"Set mark\" onclick='javascript:toggleMark($id, true)'>";
			}

			$content_link = "<a id=\"FTITLE-$id\" href=\"javascript:view($id,$feed_id);\">" .
				$line["title"] . "</a>";
				
			print "<tr class='$class' id='RROW-$id'>";
			// onclick=\"javascript:view($id,$feed_id)\">

			print "<td valign='center' align='center'>$update_pic</td>";
			print "<td valign='center' align='center'>$marked_pic</td>";

			print "<td width='25%'>
				<a href=\"javascript:view($id,$feed_id);\">".$line["updated"]."</a></td>";

			if ($line["feed_title"]) {			
				print "<td width='50%'>$content_link</td>";
				print "<td width='20%'>
					<a href='javascript:viewfeed($feed_id)'>".$line["feed_title"]."</a></td>";
			} else {
				print "<td width='70%'>$content_link</td>";
			}

			print "</tr>";

			++$lnum;
		}

		if ($lnum == 0) {
			print "<tr><td align='center'>No articles found.</td></tr>";
		}
		
		print "</table>";
		
		print "<script type=\"text/javascript\">
			document.onkeydown = hotkey_handler;
			update_label_counters('$feed');
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
				db_query($link, "UPDATE ttrss_entries SET unread = true WHERE feed_id = '$id'");
			}

			print "Marked selected feeds as read.";
		}

		if ($subop == "read") {
			$ids = split(",", $_GET["ids"]);
			foreach ($ids as $id) {
				db_query($link, "UPDATE ttrss_entries 
					SET unread = false,last_read = NOW() WHERE feed_id = '$id'");
			}

			print "Marked selected feeds as unread.";

		}

	}

	if ($op == "pref-feeds") {
	
		$subop = $_GET["subop"];

		if ($subop == "editSave") {
			$feed_title = db_escape_string($_GET["t"]);
			$feed_link = db_escape_string($_GET["l"]);
			$upd_intl = db_escape_string($_GET["ui"]);
			$purge_intl = db_escape_string($_GET["pi"]);
			$feed_id = $_GET["id"];

			if (strtoupper($upd_intl) == "DEFAULT")
				$upd_intl = 0;

			if (strtoupper($purge_intl) == "DEFAULT")
				$purge_intl = 0;

			if (strtoupper($purge_intl) == "DISABLED")
				$purge_intl = -1;

			$result = db_query($link, "UPDATE ttrss_feeds SET 
				title = '$feed_title', feed_url = '$feed_link',
				update_interval = '$upd_intl',
				purge_interval = '$purge_intl' 
				WHERE id = '$feed_id'");			

		}

		if ($subop == "remove") {

			if (!WEB_DEMO_MODE) {

				$ids = split(",", $_GET["ids"]);

				foreach ($ids as $id) {
					db_query($link, "DELETE FROM ttrss_feeds WHERE id = '$id'");

					$icons_dir = ICONS_DIR;
					
					if (file_exists($icons_dir . "/$id.ico")) {
						unlink($icons_dir . "/$id.ico");
					}
				}
			}
		}

		if ($subop == "add") {
		
			if (!WEB_DEMO_MODE) {

				$feed_link = db_escape_string($_GET["link"]);
					
				$result = db_query($link,
					"INSERT INTO ttrss_feeds (owner_uid,feed_url,title) VALUES ('".$_SESSION["uid"]."', '$feed_link', '')");

				$result = db_query($link,
					"SELECT id FROM ttrss_feeds WHERE feed_url = '$feed_link'");

				$feed_id = db_fetch_result($result, 0, "id");

				if ($feed_id) {
					update_rss_feed($link, $feed_link, $feed_id);
				}
			}
		}

		$result = db_query($link, "SELECT id,title,feed_url,last_error 
			FROM ttrss_feeds WHERE last_error != ''");

		if (db_num_rows($result) > 0) {
		
			print "<div class=\"warning\">";
		
			print "<b>Feeds with update errors:</b>";

			print "<ul class=\"nomarks\">";
						
			while ($line = db_fetch_assoc($result)) {
				print "<li>" . $line["title"] . " (" . $line["feed_url"] . "): " . 
					$line["last_error"];
			}

			print "</ul>";
			print "</div>";

		}

		print "<table class=\"prefAddFeed\"><tr>
			<td><input id=\"fadd_link\"></td>
			<td colspan=\"4\" align=\"right\">
				<a class=\"button\" href=\"javascript:addFeed()\">Add feed</a></td></tr>
		</table>";

		$result = db_query($link, "SELECT 
				id,title,feed_url,substring(last_updated,1,16) as last_updated,
				update_interval,purge_interval
			FROM 
				ttrss_feeds WHERE owner_uid = '".$_SESSION["uid"]."' ORDER by title");

		print "<p><table width=\"100%\" class=\"prefFeedList\" id=\"prefFeedList\">";
		print "<tr class=\"title\">
					<td>&nbsp;</td><td>Select</td><td width=\"30%\">Title</td>
					<td width=\"30%\">Link</td>
					<td width=\"10%\">Update Interval</td>
					<td width=\"10%\">Purge Days</td>
					<td>Last updated</td></tr>";
		
		$lnum = 0;
		
		while ($line = db_fetch_assoc($result)) {

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

			$edit_title = htmlspecialchars(db_unescape_string($line["title"]));
			$edit_link = htmlspecialchars(db_unescape_string($line["feed_url"]));

			if (!$edit_feed_id || $subop != "edit") {

				print "<td><input onclick='toggleSelectRow(this);' 
				type=\"checkbox\" id=\"FRCHK-".$line["id"]."\"></td>";

				print "<td><a href=\"javascript:editFeed($feed_id);\">" . 
					$edit_title . "</a></td>";		
				print "<td><a href=\"javascript:editFeed($feed_id);\">" . 
					$edit_link . "</a></td>";		

				if ($line["update_interval"] == "0")
					$line["update_interval"] = "Default";

				print "<td><a href=\"javascript:editFeed($feed_id);\">" . 
					$line["update_interval"] . "</a></td>";

				if ($line["purge_interval"] == "0")
					$line["purge_interval"] = "Default";

				if ($line["purge_interval"] < 0)
					$line["purge_interval"] = "Disabled";

				print "<td><a href=\"javascript:editFeed($feed_id);\">" . 
					$line["purge_interval"] . "</a></td>";

			} else if ($feed_id != $edit_feed_id) {

				print "<td><input disabled=\"true\" type=\"checkbox\" 
					id=\"FRCHK-".$line["id"]."\"></td>";

				print "<td>$edit_title</td>";		
				print "<td>$edit_link</td>";		

				if ($line["update_interval"] == "0")
					$line["update_interval"] = "Default";

				print "<td>" . $line["update_interval"] . "</td>";

				if ($line["purge_interval"] == "0")
					$line["purge_interval"] = "Default";

				if ($line["purge_interval"] < 0)
					$line["purge_interval"] = "Disabled";

				print "<td>" . $line["purge_interval"] . "</td>";

			} else {

				print "<td><input disabled=\"true\" type=\"checkbox\" checked></td>";

				print "<td><input id=\"iedit_title\" value=\"$edit_title\"></td>";
				print "<td><input id=\"iedit_link\" value=\"$edit_link\"></td>";
				print "<td><input id=\"iedit_updintl\" value=\"".$line["update_interval"]."\"></td>";
				print "<td><input id=\"iedit_purgintl\" value=\"".$line["purge_interval"]."\"></td>";
					
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
					onclick=\"javascript:feedEditSave()\" value=\"Save\">";
			} else {

			print "
				Selection:&nbsp;
			<input type=\"submit\" class=\"button\" 
				onclick=\"javascript:editSelectedFeed()\" value=\"Edit\">
			<input type=\"submit\" class=\"button\" 
				onclick=\"javascript:removeSelectedFeeds()\" value=\"Remove\">";
				
			if (get_pref($link, 'ENABLE_PREFS_CATCHUP_UNCATCHUP')) {
				print "
				<input type=\"submit\" class=\"button\" 
					onclick=\"javascript:readSelectedFeeds()\" value=\"Mark as read\">
				<input type=\"submit\" class=\"button\" 
					onclick=\"javascript:unreadSelectedFeeds()\" value=\"Mark as unread\">&nbsp;";
			}
			print "
			All feeds: 
				<input type=\"submit\" 
					class=\"button\" onclick=\"gotoExportOpml()\" value=\"Export OPML\">";
		
			}

		print "<h3>OPML Import</h3>
		<form	enctype=\"multipart/form-data\" method=\"POST\" action=\"opml.php\">
			File: <input id=\"opml_file\" name=\"opml_file\" type=\"file\">&nbsp;
			<input class=\"button\" name=\"op\" onclick=\"return validateOpmlImport();\"
				type=\"submit\" value=\"Import\">
			</form>";

	}

	if ($op == "pref-filters") {

		$subop = $_GET["subop"];

		if ($subop == "editSave") {

			$regexp = db_escape_string($_GET["r"]);
			$descr = db_escape_string($_GET["d"]);
			$match = db_escape_string($_GET["m"]);
			$filter_id = db_escape_string($_GET["id"]);
			
			$result = db_query($link, "UPDATE ttrss_filters SET 
				reg_exp = '$regexp', 
				description = '$descr',
				filter_type = (SELECT id FROM ttrss_filter_types WHERE
					description = '$match')
				WHERE id = '$filter_id'");
		}

		if ($subop == "remove") {

			if (!WEB_DEMO_MODE) {

				$ids = split(",", $_GET["ids"]);

				foreach ($ids as $id) {
					db_query($link, "DELETE FROM ttrss_filters WHERE id = '$id'");
					
				}
			}
		}

		if ($subop == "add") {
		
			if (!WEB_DEMO_MODE) {

				$regexp = db_escape_string($_GET["regexp"]);
				$match = db_escape_string($_GET["match"]);
					
				$result = db_query($link,
					"INSERT INTO ttrss_filters (reg_exp,filter_type,owner_uid) VALUES 
						('$regexp', (SELECT id FROM ttrss_filter_types WHERE
							description = '$match'),'".$_SESSION["uid"]."')");
			} 
		}

		$result = db_query($link, "SELECT description 
			FROM ttrss_filter_types ORDER BY description");

		$filter_types = array();

		while ($line = db_fetch_assoc($result)) {
			array_push($filter_types, $line["description"]);
		}

		print "<table class=\"prefAddFeed\"><tr>
			<td><input id=\"fadd_regexp\"></td>
			<td>";
			print_select("fadd_match", "Title", $filter_types);	
	
		print"</td><td colspan=\"4\" align=\"right\">
				<a class=\"button\" href=\"javascript:addFilter()\">Add filter</a></td></tr>
		</table>";

		$result = db_query($link, "SELECT 
				id,reg_exp,description,
				(SELECT name FROM ttrss_filter_types WHERE 
					id = filter_type) as filter_type_name,
				(SELECT description FROM ttrss_filter_types 
					WHERE id = filter_type) as filter_type_descr
			FROM 
				ttrss_filters
			WHERE
				owner_uid = ".$_SESSION["uid"]."
			ORDER by reg_exp");

		print "<p><table width=\"100%\" class=\"prefFilterList\" id=\"prefFilterList\">";

		print "<tr class=\"title\">
					<td width=\"5%\">Select</td><td width=\"40%\">Filter expression</td>
					<td width=\"40%\">Description</td><td width=\"10%\">Match</td></tr>";
		
		$lnum = 0;
		
		while ($line = db_fetch_assoc($result)) {

			$class = ($lnum % 2) ? "even" : "odd";

			$filter_id = $line["id"];
			$edit_filter_id = $_GET["id"];

			if ($subop == "edit" && $filter_id != $edit_filter_id) {
				$class .= "Grayed";
			}

			print "<tr class=\"$class\" id=\"FILRR-$filter_id\">";

			$line["regexp"] = htmlspecialchars($line["reg_exp"]);
			$line["description"] = htmlspecialchars($line["description"]);

			if (!$edit_filter_id || $subop != "edit") {

				if (!$line["description"]) $line["description"] = "[No description]";

				print "<td><input onclick='toggleSelectRow(this);' 
				type=\"checkbox\" id=\"FICHK-".$line["id"]."\"></td>";

				print "<td><a href=\"javascript:editFilter($filter_id);\">" . 
					$line["reg_exp"] . "</td>";		
					
				print "<td><a href=\"javascript:editFilter($filter_id);\">" . 
					$line["description"] . "</td>";			

				print "<td>".$line["filter_type_descr"]."</td>";

			} else if ($filter_id != $edit_filter_id) {

				if (!$line["description"]) $line["description"] = "[No description]";

				print "<td><input disabled=\"true\" type=\"checkbox\" 
					id=\"FICHK-".$line["id"]."\"></td>";

				print "<td>".$line["reg_exp"]."</td>";		
				print "<td>".$line["description"]."</td>";		
				print "<td>".$line["filter_type_descr"]."</td>";

			} else {

				print "<td><input disabled=\"true\" type=\"checkbox\" checked></td>";

				print "<td><input id=\"iedit_regexp\" value=\"".$line["reg_exp"].
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

	if ($op == "pref-labels") {

		$subop = $_GET["subop"];

		if ($subop == "editSave") {

			$sql_exp = $_GET["s"];
			$descr = $_GET["d"];
			$label_id = db_escape_string($_GET["id"]);
			
//			print "$sql_exp : $descr : $label_id";
			
			$result = db_query($link, "UPDATE ttrss_labels SET 
				sql_exp = '$sql_exp', 
				description = '$descr'
				WHERE id = '$label_id'");
		}

		if ($subop == "remove") {

			if (!WEB_DEMO_MODE) {

				$ids = split(",", $_GET["ids"]);

				foreach ($ids as $id) {
					db_query($link, "DELETE FROM ttrss_labels WHERE id = '$id'");
					
				}
			}
		}

		if ($subop == "add") {
		
			if (!WEB_DEMO_MODE) {

				$exp = $_GET["exp"];
					
				$result = db_query($link,
					"INSERT INTO ttrss_labels (sql_exp,description,owner_uid) 
						VALUES ('$exp', '$exp', '".$_SESSION["uid"]."')");
			} 
		}

		print "<table class=\"prefAddFeed\"><tr>
			<td><input id=\"ladd_expr\"></td>";
			
		print"<td colspan=\"4\" align=\"right\">
				<a class=\"button\" href=\"javascript:addLabel()\">Add label</a></td></tr>
		</table>";

		$result = db_query($link, "SELECT 
				id,sql_exp,description
			FROM 
				ttrss_labels 
			WHERE 
				owner_uid = ".$_SESSION["uid"]."
			ORDER by description");

		print "<p><table width=\"100%\" class=\"prefLabelList\" id=\"prefLabelList\">";

		print "<tr class=\"title\">
					<td width=\"5%\">Select</td><td width=\"40%\">SQL expression
					<a class=\"helpLink\" href=\"javascript:popupHelp(1)\">(?)</a>
					</td>
					<td width=\"40%\">Caption</td></tr>";
		
		$lnum = 0;
		
		while ($line = db_fetch_assoc($result)) {

			$class = ($lnum % 2) ? "even" : "odd";

			$label_id = $line["id"];
			$edit_label_id = $_GET["id"];

			if ($subop == "edit" && $label_id != $edit_label_id) {
				$class .= "Grayed";
			}

			print "<tr class=\"$class\" id=\"LILRR-$label_id\">";

			$line["sql_exp"] = htmlspecialchars($line["sql_exp"]);
			$line["description"] = htmlspecialchars($line["description"]);

			if (!$edit_label_id || $subop != "edit") {

				if (!$line["description"]) $line["description"] = "[No caption]";

				print "<td><input onclick='toggleSelectRow(this);' 
				type=\"checkbox\" id=\"LICHK-".$line["id"]."\"></td>";

				print "<td><a href=\"javascript:editLabel($label_id);\">" . 
					$line["sql_exp"] . "</td>";		
					
				print "<td><a href=\"javascript:editLabel($label_id);\">" . 
					$line["description"] . "</td>";			

			} else if ($label_id != $edit_label_id) {

				if (!$line["description"]) $line["description"] = "[No description]";

				print "<td><input disabled=\"true\" type=\"checkbox\" 
					id=\"LICHK-".$line["id"]."\"></td>";

				print "<td>".$line["sql_exp"]."</td>";		
				print "<td>".$line["description"]."</td>";		

			} else {

				print "<td><input disabled=\"true\" type=\"checkbox\" checked></td>";

				print "<td><input id=\"iedit_expr\" value=\"".$line["sql_exp"].
					"\"></td>";

				print "<td><input id=\"iedit_descr\" value=\"".$line["description"].
					"\"></td>";
						
			}
				
			
			print "</tr>";

			++$lnum;
		}

		if ($lnum == 0) {
			print "<tr><td colspan=\"4\" align=\"center\">No labels defined.</td></tr>";
		}

		print "</table>";

		print "<p>";

		if ($subop == "edit") {
			print "Edit label:
				<input type=\"submit\" class=\"button\" 
					onclick=\"javascript:labelEditCancel()\" value=\"Cancel\">
				<input type=\"submit\" class=\"button\" 
					onclick=\"javascript:labelEditSave()\" value=\"Save\">";
					
		} else {

			print "
				Selection:
			<input type=\"submit\" class=\"button\" 
				onclick=\"javascript:editSelectedLabel()\" value=\"Edit\">
			<input type=\"submit\" class=\"button\" 
				onclick=\"javascript:removeSelectedLabels()\" value=\"Remove\">";
		}
	}

	if ($op == "error") {
		print "<div width=\"100%\" align='center'>";
		$msg = $_GET["msg"];
		print $msg;
		print "</div>";
	}

	if ($op == "help") {
		print "<html><head>
			<title>Tiny Tiny RSS : Help</title>
			<link rel=\"stylesheet\" href=\"tt-rss.css\" type=\"text/css\">
			<script type=\"text/javascript\" src=\"functions.js\"></script>
			<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\">
			</head><body>";

		$tid = sprintf("%d", $_GET["tid"]);

		/* FIXME this badly needs real implementation */

		print "<div class='helpResponse'>";

		?>

		<h1>Help for SQL expressions</h1>

		<h2>Description</h2>

		<p>The &laquo;SQL expression&raquo; is added to WHERE clause of
			view feed query. You can match on ttrss_entries table fields
			and even use subselect to query additional information. This 
			functionality is considered to be advanced and requires basic
			understanding of SQL.</p>
			
		<h2>Examples</h2>

		<pre>unread = true</pre>

		Matches all unread articles

		<pre>title like '%Linux%'</pre>

		Matches all articles which mention Linux in the title. You get the idea.

		<p>See the database schema included in the distribution package for gruesome
		details.</p>

		<?

		print "<div align='center'>
			<a class=\"helpLink\"
			href=\"javascript:window.close()\">(Close this window)</a></div>";

		print "</div>";

		print "</body></html>";

	}

	if ($op == "dlg") {
		$id = $_GET["id"];
		$param = $_GET["param"];

		if ($id == "quickAddFeed") {
			print "Feed URL: <input 
			onblur=\"javascript:enableHotkeys()\" onfocus=\"javascript:disableHotkeys()\"
			id=\"qafInput\">
			<input class=\"button\"
				type=\"submit\" onclick=\"javascript:qafAdd()\" value=\"Add feed\">
			<input class=\"button\"
				type=\"submit\" onclick=\"javascript:closeDlg()\" 
				value=\"Cancel\">";
		}

		if ($id == "quickDelFeed") {

			$param = db_escape_string($param);

			$result = db_query($link, "SELECT title FROM ttrss_feeds WHERE id = '$param'");

			if ($result) {

				$f_title = db_fetch_result($result, 0, "title");
		
				print "Remove current feed ($f_title)?&nbsp;
				<input class=\"button\"
					type=\"submit\" onclick=\"javascript:qfdDelete($param)\" value=\"Remove\">
				<input class=\"button\"
					type=\"submit\" onclick=\"javascript:closeDlg()\" 
					value=\"Cancel\">";
			} else {
				print "Error: Feed $param not found.&nbsp;
				<input class=\"button\"
					type=\"submit\" onclick=\"javascript:closeDlg()\" 
					value=\"Cancel\">";		
			}
		}

		if ($id == "search") {

			print "<input id=\"searchbox\" class=\"extSearch\"			
			onblur=\"javascript:enableHotkeys()\" onfocus=\"javascript:disableHotkeys()\"
			onchange=\"javascript:search()\">
			<select id=\"searchmodebox\">
				<option selected>All feeds</option>
				<option>This feed</option>
			</select>		
			<input type=\"submit\" 
				class=\"button\" onclick=\"javascript:search()\" value=\"Search\">
			<input class=\"button\"
				type=\"submit\" onclick=\"javascript:closeDlg()\" 
				value=\"Close\">";

		}

	}

	if ($op == "updateAllFeeds") {
		update_all_feeds($link, true);			

		print "<rpc-reply>";
		getLabelCounters($link);
		getFeedCounters($link);
		getTagCounters($link);
		getGlobalCounters($link);
		print "</rpc-reply>";

	}

	if ($op == "pref-prefs") {

		$subop = $_REQUEST["subop"];

		if ($subop == "Save configuration") {

			if (WEB_DEMO_MODE) return;

			foreach (array_keys($_POST) as $pref_name) {
			
				$pref_name = db_escape_string($pref_name);
				$value = db_escape_string($_POST[$pref_name]);

				$result = db_query($link, "SELECT type_name 
					FROM ttrss_prefs,ttrss_prefs_types 
					WHERE pref_name = '$pref_name' AND type_id = ttrss_prefs_types.id");

				if (db_num_rows($result) > 0) {

					$type_name = db_fetch_result($result, 0, "type_name");

//					print "$pref_name : $type_name : $value<br>";

					if ($type_name == "bool") {
						if ($value == "1") {
							$value = "true";
						} else {
							$value = "false";
						}
					} else if ($type_name == "integer") {
						$value = sprintf("%d", $value);
					}

//					print "$pref_name : $type_name : $value<br>";

					db_query($link, "UPDATE ttrss_user_prefs SET value = '$value' 
						WHERE pref_name = '$pref_name' AND owner_uid = ".$_SESSION["uid"]);

				}

				header("Location: prefs.php");

			}

		} else if ($subop == "getHelp") {

			$pref_name = db_escape_string($_GET["pn"]);

			$result = db_query($link, "SELECT help_text FROM ttrss_prefs
				WHERE pref_name = '$pref_name'");

			if (db_num_rows($result) > 0) {
				$help_text = db_fetch_result($result, 0, "help_text");
				print $help_text;
			} else {
				print "Unknown option: $pref_name";
			}

		} else if ($subop == "Change password") {

			if (WEB_DEMO_MODE) return;

			$old_pw = $_POST["OLD_PASSWORD"];
			$new_pw = $_POST["OLD_PASSWORD"];

			$old_pw_hash = 'SHA1:' . sha1($_POST["OLD_PASSWORD"]);
			$new_pw_hash = 'SHA1:' . sha1($_POST["NEW_PASSWORD"]);

			$active_uid = $_SESSION["uid"];

			if ($old_pw && $new_pw) {

				$login = db_escape_string($_SERVER['PHP_AUTH_USER']);

				$result = db_query($link, "SELECT id FROM ttrss_users WHERE 
					id = '$active_uid' AND (pwd_hash = '$old_pw' OR 
						pwd_hash = '$old_pw_hash')");

				if (db_num_rows($result) == 1) {
					db_query($link, "UPDATE ttrss_users SET pwd_hash = '$new_pw_hash' 
						WHERE id = '$active_uid'");				
				}
			}

			header("Location: prefs.php");
	
		} else if ($subop == "Reset to defaults") {

			if (WEB_DEMO_MODE) return;

			if (DB_TYPE == "pgsql") {
				db_query($link,"UPDATE ttrss_user_prefs 
					SET value = ttrss_prefs.def_value 
					WHERE owner_uid = '".$_SESSION["uid"]."' AND
					ttrss_prefs.pref_name = ttrss_user_prefs.pref_name");
			} else {
				db_query($link, "DELETE FROM ttrss_user_prefs 
					WHERE owner_uid = ".$_SESSION["uid"]);
				initialize_user_prefs($link, $_SESSION["uid"]);
			}

			header("Location: prefs.php");

		} else {

			if (!SINGLE_USER_MODE) {

				print "<form action=\"backend.php\" method=\"POST\">";
	
				print "<table width=\"100%\" class=\"prefPrefsList\">";
	 			print "<tr><td colspan='3'><h3>Authentication</h3></tr></td>";
	
				print "<tr><td width=\"40%\">Old password</td>";
				print "<td><input class=\"editbox\" type=\"password\"
					name=\"OLD_PASSWORD\"></td></tr>";
	
				print "<tr><td width=\"40%\">New password</td>";
				
				print "<td><input class=\"editbox\" type=\"password\"
					name=\"NEW_PASSWORD\"></td></tr>";
	
				print "</table>";
	
				print "<input type=\"hidden\" name=\"op\" value=\"pref-prefs\">";
	
				print "<p><input class=\"button\" type=\"submit\" 
					value=\"Change password\" name=\"subop\">";
	
				print "</form>";

			}

			$result = db_query($link, "SELECT 
				ttrss_user_prefs.pref_name,short_desc,help_text,value,type_name,
				section_name,def_value
				FROM ttrss_prefs,ttrss_prefs_types,ttrss_prefs_sections,ttrss_user_prefs
				WHERE type_id = ttrss_prefs_types.id AND 
					section_id = ttrss_prefs_sections.id AND
					ttrss_user_prefs.pref_name = ttrss_prefs.pref_name AND
					owner_uid = ".$_SESSION["uid"]."
				ORDER BY section_id,short_desc");

			print "<form action=\"backend.php\" method=\"POST\">";

			$lnum = 0;

			$active_section = "";
	
			while ($line = db_fetch_assoc($result)) {

				if ($active_section != $line["section_name"]) {

					if ($active_section != "") {
						print "</table>";
					}

					print "<p><table width=\"100%\" class=\"prefPrefsList\">";
				
					$active_section = $line["section_name"];				
					
					print "<tr><td colspan=\"3\"><h3>$active_section</h3></td></tr>";
//					print "<tr class=\"title\">
//						<td width=\"25%\">Option</td><td>Value</td></tr>";

					$lnum = 0;
				}

//				$class = ($lnum % 2) ? "even" : "odd";

				print "<tr>";

				$type_name = $line["type_name"];
				$pref_name = $line["pref_name"];
				$value = $line["value"];
				$def_value = $line["def_value"];
				$help_text = $line["help_text"];

				print "<td width=\"40%\" id=\"$pref_name\">" . $line["short_desc"];

				if ($help_text) print "<div class=\"prefHelp\">$help_text</div>";
				
				print "</td>";

				print "<td>";

				if ($type_name == "bool") {
//					print_select($pref_name, $value, array("true", "false"));

					if ($value == "true") {
						$value = "Yes";
					} else {
						$value = "No";
					}

					print_radio($pref_name, $value, array("Yes", "No"));
			
				} else {
					print "<input class=\"editbox\" name=\"$pref_name\" value=\"$value\">";
				}

				print "</td>";

				print "</tr>";

				$lnum++;
			}

			print "</table>";

			print "<input type=\"hidden\" name=\"op\" value=\"pref-prefs\">";

			print "<p><input class=\"button\" type=\"submit\" 
				name=\"subop\" value=\"Save configuration\">";
				
			print "&nbsp;<input class=\"button\" type=\"submit\" 
				name=\"subop\" value=\"Reset to defaults\"></p>";

			print "</form>";

		}

	}

	if ($op == "pref-users") {

		$subop = $_GET["subop"];

		if ($subop == "editSave") {
	
			if (!WEB_DEMO_MODE) {

				$login = db_escape_string($_GET["l"]);
				$uid = db_escape_string($_GET["id"]);
				$access_level = sprintf("%d", $_GET["al"]);

				db_query($link, "UPDATE ttrss_users SET login = '$login', access_level = '$access_level' WHERE id = '$uid'");

			}
		} else if ($subop == "remove") {

			if (!WEB_DEMO_MODE && $_SESSION["access_level"] >= 10) {

				$ids = split(",", $_GET["ids"]);

				foreach ($ids as $id) {
					db_query($link, "DELETE FROM ttrss_users WHERE id = '$id' AND id != " . $_SESSION["uid"]);
					
				}
			}
		} else if ($subop == "add") {
		
			if (!WEB_DEMO_MODE && $_SESSION["access_level"] >= 10) {

				$login = db_escape_string($_GET["login"]);
				$tmp_user_pwd = make_password(8);
				$pwd_hash = 'SHA1:' . sha1($tmp_user_pwd);

				db_query($link, "INSERT INTO ttrss_users (login,pwd_hash,access_level)
					VALUES ('$login', '$pwd_hash', 0)");


				$result = db_query($link, "SELECT id FROM ttrss_users WHERE 
					login = '$login' AND pwd_hash = '$pwd_hash'");

				if (db_num_rows($result) == 1) {

					$new_uid = db_fetch_result($result, 0, "id");

					print "<div class=\"notice\">Added user <b>".$_GET["login"].
						"</b> with password <b>$tmp_user_pwd</b>.</div>";

					initialize_user($link, $new_uid);

				} else {
				
					print "<div class=\"warning\">Error while adding user <b>".
					$_GET["login"].".</b></div>";

				}
			} 
		} else if ($subop == "resetPass") {

			if (!WEB_DEMO_MODE && $_SESSION["access_level"] >= 10) {

				$uid = db_escape_string($_GET["id"]);

				$result = db_query($link, "SELECT login FROM ttrss_users WHERE id = '$uid'");

				$login = db_fetch_result($result, 0, "login");
				$tmp_user_pwd = make_password(8);
				$pwd_hash = 'SHA1:' . sha1($tmp_user_pwd);

				db_query($link, "UPDATE ttrss_users SET pwd_hash = '$pwd_hash'
					WHERE id = '$uid'");

				print "<div class=\"notice\">Changed password of 
					user <b>$login</b> to <b>$tmp_user_pwd</b>.</div>";				

			}
		}

		print "<table class=\"prefAddFeed\"><tr>
			<td><input id=\"uadd_box\"></td>";
			
		print"<td colspan=\"4\" align=\"right\">
				<a class=\"button\" href=\"javascript:addUser()\">Add user</a></td></tr>
		</table>";

		$result = db_query($link, "SELECT 
				id,login,access_level,last_login
			FROM 
				ttrss_users
			ORDER by login");

		print "<div id=\"prefUserDetails\">PLACEHOLDER</div>";

		print "<p><table width=\"100%\" class=\"prefUserList\" id=\"prefUserList\">";

		print "<tr class=\"title\">
					<td width=\"5%\">Select</td>
					<td width='30%'>Username</td>
					<td width='30%'>Access Level</td>
					<td width='30%'>Last login</td></tr>";
		
		$lnum = 0;
		
		while ($line = db_fetch_assoc($result)) {

			$class = ($lnum % 2) ? "even" : "odd";

			$uid = $line["id"];
			$edit_uid = $_GET["id"];

			if ($uid == $_SESSION["uid"] || ($subop == "edit" && $uid != $edit_uid)) {
				$class .= "Grayed";
			}
		
			print "<tr class=\"$class\" id=\"UMRR-$uid\">";

			$line["login"] = htmlspecialchars($line["login"]);

			if ($uid == $_SESSION["uid"]) {

				print "<td><input disabled=\"true\" type=\"checkbox\" 
					id=\"UMCHK-".$line["id"]."\"></td>";

				print "<td>".$line["login"]."</td>";		
				print "<td>".$line["access_level"]."</td>";		

			} else if (!$edit_uid || $subop != "edit") {

				print "<td><input onclick='toggleSelectRow(this);' 
				type=\"checkbox\" id=\"UMCHK-$uid\"></td>";

				print "<td><a href=\"javascript:editUser($uid);\">" . 
					$line["login"] . "</td>";		
					
				print "<td><a href=\"javascript:editUser($uid);\">" . 
					$line["access_level"] . "</td>";			

			} else if ($uid != $edit_uid) {

				print "<td><input disabled=\"true\" type=\"checkbox\" 
					id=\"UMCHK-".$line["id"]."\"></td>";

				print "<td>".$line["login"]."</td>";		
				print "<td>".$line["access_level"]."</td>";		

			} else {

				print "<td><input disabled=\"true\" type=\"checkbox\" checked></td>";

				print "<td><input id=\"iedit_ulogin\" value=\"".$line["login"].
					"\"></td>";

				print "<td><input id=\"iedit_ulevel\" value=\"".$line["access_level"].
					"\"></td>";
						
			}
				
			print "<td>".$line["last_login"]."</td>";		
		
			print "</tr>";

			++$lnum;
		}

		print "</table>";

		print "<p>";

		if ($subop == "edit") {
			print "Edit label:
				<input type=\"submit\" class=\"button\" 
					onclick=\"javascript:userEditCancel()\" value=\"Cancel\">
				<input type=\"submit\" class=\"button\" 
					onclick=\"javascript:userEditSave()\" value=\"Save\">";
					
		} else {

			print "
				Selection:
			<input type=\"submit\" class=\"button\" 
				onclick=\"javascript:selectedUserDetails()\" value=\"User details\">
			<input type=\"submit\" class=\"button\" 
				onclick=\"javascript:editSelectedUser()\" value=\"Edit\">
			<input type=\"submit\" class=\"button\" 
				onclick=\"javascript:removeSelectedUsers()\" value=\"Remove\">
			<input type=\"submit\" class=\"button\" 
				onclick=\"javascript:resetSelectedUserPass()\" value=\"Reset password\">";

		}
	}

	if ($op == "user-details") {

		if (WEB_DEMO_MODE || $_SESSION["access_level"] < 10) {
			return;
		}
			  
/*		print "<html><head>
			<title>Tiny Tiny RSS : User Details</title>
			<link rel=\"stylesheet\" href=\"tt-rss.css\" type=\"text/css\">
			<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\">
			</head><body>"; */

		$uid = sprintf("%d", $_GET["id"]);

		print "<div class='userDetails'>";

		$result = db_query($link, "SELECT login,last_login,access_level
			FROM ttrss_users 
			WHERE id = '$uid'");
			
		if (db_num_rows($result) == 0) {
			print "<h1>User not found</h1>";
			return;
		}
		
		print "<h1>User Details</h1>";

		print "<table width='100%'>";

		$login = db_fetch_result($result, 0, "login");
		$last_login = db_fetch_result($result, 0, "last_login");
		$access_level = db_fetch_result($result, 0, "access_level");

		print "<tr><td>Username</td><td>$login</td></tr>";
		print "<tr><td>Access level</td><td>$access_level</td></tr>";
		print "<tr><td>Last logged in</td><td>$last_login</td></tr>";

		$result = db_query($link, "SELECT COUNT(id) as num_feeds FROM ttrss_feeds
			WHERE owner_uid = '$uid'");

		$num_feeds = db_fetch_result($result, 0, "num_feeds");

		print "<tr><td>Subscribed feeds count</td><td>$num_feeds</td></tr>";

		$result = db_query($link, "SELECT 
			SUM(LENGTH(content)+LENGTH(title)+LENGTH(link)+LENGTH(guid)) AS db_size 
			FROM ttrss_entries WHERE owner_uid = '$uid'");

		$db_size = round(db_fetch_result($result, 0, "db_size") / 1024);

		print "<tr><td>Approx. DB size</td><td>$db_size KBytes</td></tr>";

		print "</table>";

		print "<h1>Subscribed feeds</h1>";

		$result = db_query($link, "SELECT id,title,feed_url FROM ttrss_feeds
			WHERE owner_uid = '$uid' ORDER BY title");

		print "<ul class=\"nomarks\">";

		while ($line = db_fetch_assoc($result)) {

			$icon_file = ICONS_URL."/".$line["id"].".ico";

			if (file_exists($icon_file) && filesize($icon_file) > 0) {
				$feed_icon = "<img class=\"feedIcon\" src=\"$icon_file\">";
			} else {
				$feed_icon = "<img class=\"feedIcon\" src=\"images/blank_icon.gif\">";
			}

			print "<li>$feed_icon&nbsp;<a href=\"".$line["feed_url"]."\">".$line["title"]."</a></li>";
		}

		print "</ul>";

		print "</div>";

		print "<div align='center'>
			<input type='submit' class='button'			
			onclick=\"closeUserDetails()\" value=\"Close this window\"></div>";

//		print "</body></html>"; 

	}

	db_close($link);
?>

<!-- <?= sprintf("Backend execution time: %.4f seconds", getmicrotime() - $script_started) ?> -->


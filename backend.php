<?
	session_start();

	header("Cache-Control: no-cache");
	header("Pragma: no-cache");
	header("Expires: -1");
	
	if ($_GET["debug"]) {
		define('DEFAULT_ERROR_LEVEL', E_ALL);
	} else {
		define('DEFAULT_ERROR_LEVEL', E_ERROR | E_WARNING | E_PARSE);
	}

	error_reporting(DEFAULT_ERROR_LEVEL);

	$op = $_REQUEST["op"];

	if ((!$op || $op == "rpc" || $op == "globalUpdateFeeds") && !$_REQUEST["noxml"]) {
		header("Content-Type: application/xml");
	}

	if (!$_SESSION["uid"] && $op != "globalUpdateFeeds") {

		if ($op == "rpc") {
			print "<error error-code=\"6\"/>";
		}
		exit;
	}

	if (!$op) {
		print "<error error-code=\"7\"/>";
		exit;
	}

	define('SCHEMA_VERSION', 5);

	require_once "sanity_check.php";
	require_once "config.php";
	require_once "db.php";
	require_once "db-prefs.php";
	require_once "functions.php";
	require_once "magpierss/rss_fetch.inc";

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

	if ($_SESSION["uid"]) {
		if (get_pref($link, "HIDE_READ_FEEDS") == "true") {
			setcookie("ttrss_vf_hreadf", 1);
		} else {
			setcookie("ttrss_vf_hreadf", 0);
		}  

		setcookie('ttrss_vf_refresh', FEEDS_FRAME_REFRESH);
		setcookie('ttrss_vf_daemon', ENABLE_UPDATE_DAEMON);
	}

	$fetch = $_GET["fetch"];

	setcookie("ttrss_icons_url", ICONS_URL);

	if (!sanity_check($link)) { return; }

	function getAllCounters($link) {
		getLabelCounters($link);
		getFeedCounters($link);
		getTagCounters($link);
		getGlobalCounters($link);
		if (get_pref($link, 'ENABLE_FEED_CATS')) {
			getCategoryCounters($link);
		}
	}	

	function getCategoryCounters($link) {
		$result = db_query($link, "SELECT cat_id,SUM((SELECT COUNT(int_id) 
				FROM ttrss_user_entries WHERE feed_id = ttrss_feeds.id 
					AND unread = true)) AS unread FROM ttrss_feeds 
			WHERE 
				owner_uid = ".$_SESSION["uid"]." GROUP BY cat_id");

		while ($line = db_fetch_assoc($result)) {
			$line["cat_id"] = sprintf("%d", $line["cat_id"]);
			print "<counter type=\"category\" id=\"".$line["cat_id"]."\" counter=\"".
				$line["unread"]."\"/>";
		}
	}

	function getFeedUnread($link, $feed) {
		$n_feed = sprintf("%d", $feed);
	
		if ($n_feed == -1) {
			$match_part = "marked = true";
		} else if ($feed > 0) {
			$match_part = "feed_id = '$n_feed'";
		} else if ($feed < -10) {
			$label_id = -$feed - 11;

			$result = db_query($link, "SELECT sql_exp FROM ttrss_labels WHERE
				id = '$label_id' AND owner_uid = " . $_SESSION["uid"]);

			$match_part = db_fetch_result($result, 0, "sql_exp");
		}

		if ($match_part) {
		
			$result = db_query($link, "SELECT count(int_id) AS unread 
				FROM ttrss_user_entries 
				WHERE	unread = true AND $match_part AND owner_uid = " . $_SESSION["uid"]);
				
		} else {
		
			$result = db_query($link, "SELECT COUNT(post_int_id) AS unread
				FROM ttrss_tags,ttrss_user_entries 
				WHERE tag_name = '$feed' AND post_int_id = int_id AND unread = true AND
					ttrss_tags.owner_uid = " . $_SESSION["uid"]);
		}
		
		$unread = db_fetch_result($result, 0, "unread");
		return $unread;
	}

	/* FIXME this needs reworking */

	function getGlobalUnread($link) {
		$result = db_query($link, "SELECT count(id) as c_id FROM ttrss_entries,ttrss_user_entries
			WHERE unread = true AND 
			ttrss_user_entries.ref_id = ttrss_entries.id AND 
			owner_uid = " . $_SESSION["uid"]);
		$c_id = db_fetch_result($result, 0, "c_id");
		return $c_id;
	}

	function getGlobalCounters($link, $global_unread = -1) {
		if ($global_unread == -1) {	
			$global_unread = getGlobalUnread($link);
		}
		print "<counter type=\"global\" id='global-unread' counter='$global_unread'/>";
	}

	function getTagCounters($link, $smart_mode = SMART_RPC_COUNTERS) {

		if ($smart_mode) {
			if (!$_SESSION["tctr_last_value"]) {
				$_SESSION["tctr_last_value"] = array();
			}
		}

		$old_counters = $_SESSION["tctr_last_value"];

		$tctrs_modified = false;

/*		$result = db_query($link, "SELECT tag_name,count(ttrss_entries.id) AS count
			FROM ttrss_tags,ttrss_entries,ttrss_user_entries WHERE
			ttrss_user_entries.ref_id = ttrss_entries.id AND 
			ttrss_tags.owner_uid = ".$_SESSION["uid"]." AND
			post_int_id = ttrss_user_entries.int_id AND unread = true GROUP BY tag_name 
		UNION
			select tag_name,0 as count FROM ttrss_tags
			WHERE ttrss_tags.owner_uid = ".$_SESSION["uid"]); */

		$result = db_query($link, "SELECT tag_name,SUM((SELECT COUNT(int_id) 
			FROM ttrss_user_entries WHERE int_id = post_int_id 
				AND unread = true)) AS count FROM ttrss_tags 
			WHERE owner_uid = 2 GROUP BY tag_name ORDER BY tag_name");
			
		$tags = array();

		while ($line = db_fetch_assoc($result)) {
			$tags[$line["tag_name"]] += $line["count"];
		}

		foreach (array_keys($tags) as $tag) {
			$unread = $tags[$tag];			

			$tag = htmlspecialchars($tag);

			if (!$smart_mode || $old_counters[$tag] != $unread) {			
				$old_counters[$tag] = $unread;
				$tctrs_modified = true;
				print "<counter type=\"tag\" id=\"$tag\" counter=\"$unread\"/>";
			}

		} 

		if ($smart_mode && $tctrs_modified) {
			$_SESSION["tctr_last_value"] = $old_counters;
		}

	}

	function getLabelCounters($link, $smart_mode = SMART_RPC_COUNTERS) {

		if ($smart_mode) {
			if (!$_SESSION["lctr_last_value"]) {
				$_SESSION["lctr_last_value"] = array();
			}
		}

		$old_counters = $_SESSION["lctr_last_value"];
		$lctrs_modified = false;

		$result = db_query($link, "SELECT count(id) as count FROM ttrss_entries,ttrss_user_entries
			WHERE marked = true AND ttrss_user_entries.ref_id = ttrss_entries.id AND 
			unread = true AND owner_uid = ".$_SESSION["uid"]);

		$count = db_fetch_result($result, 0, "count");

		print "<counter type=\"label\" id=\"-1\" counter=\"$count\"/>";

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

			if (!$smart_mode || $old_counters[$id] != $count) {	
				$old_counters[$id] = $count;
				$lctrs_modified = true;
				print "<counter type=\"label\" id=\"$id\" counter=\"$count\"/>";
			}

			error_reporting (DEFAULT_ERROR_LEVEL);
		}

		if ($smart_mode && $lctrs_modified) {
			$_SESSION["lctr_last_value"] = $old_counters;
		}
	}

/*	function getFeedCounter($link, $id) {
	
		$result = db_query($link, "SELECT 
				count(id) as count,last_error
			FROM ttrss_entries,ttrss_user_entries,ttrss_feeds
			WHERE feed_id = '$id' AND unread = true
			AND ttrss_user_entries.feed_id = ttrss_feeds.id
			AND ttrss_user_entries.ref_id = ttrss_entries.id");
	
			$count = db_fetch_result($result, 0, "count");
			$last_error = htmlspecialchars(db_fetch_result($result, 0, "last_error"));
			
			print "<counter type=\"feed\" id=\"$id\" counter=\"$count\" error=\"$last_error\"/>";		
	} */

	function getFeedCounters($link, $smart_mode = SMART_RPC_COUNTERS) {

		if ($smart_mode) {
			if (!$_SESSION["fctr_last_value"]) {
				$_SESSION["fctr_last_value"] = array();
			}
		}

		$old_counters = $_SESSION["fctr_last_value"];

		$result = db_query($link, "SELECT id,last_error,parent_feed,
			(SELECT count(id) 
				FROM ttrss_entries,ttrss_user_entries 
				WHERE feed_id = ttrss_feeds.id AND 
					ttrss_user_entries.ref_id = ttrss_entries.id
				AND unread = true AND owner_uid = ".$_SESSION["uid"].") as count
			FROM ttrss_feeds WHERE owner_uid = ".$_SESSION["uid"] . "
				AND parent_feed IS NULL");

		$fctrs_modified = false;

		while ($line = db_fetch_assoc($result)) {
		
			$id = $line["id"];
			$count = $line["count"];
			$last_error = htmlspecialchars($line["last_error"]);
	
			$has_img = is_file(ICONS_DIR . "/$id.ico");

			$tmp_result = db_query($link,
				"SELECT id,COUNT(unread) AS unread
				FROM ttrss_feeds LEFT JOIN ttrss_user_entries 
					ON (ttrss_feeds.id = ttrss_user_entries.feed_id) 
				WHERE parent_feed = '$id' AND unread = true GROUP BY ttrss_feeds.id");
			
			if (db_num_rows($tmp_result) > 0) {				
				while ($l = db_fetch_assoc($tmp_result)) {
					$count += $l["unread"];
				}
			}

			if (!$smart_mode || $old_counters[$id] != $count) {
				$old_counters[$id] = $count;
				$fctrs_modified = true;

				if ($last_error) {
					$error_part = "error=\"$last_error\"";
				} else {
					$error_part = "";
				}

				if ($has_img) {
					$has_img_part = "hi=\"$has_img\"";
				} else {
					$has_img_part = "";
				}				

				print "<counter type=\"feed\" id=\"$id\" counter=\"$count\" $has_img_part $error_part/>";
			}
		}

		if ($smart_mode && $fctrs_modified) {
			$_SESSION["fctr_last_value"] = $old_counters;
		}
	}

	function outputFeedList($link, $tags = false) {

		print "<html><head>
			<title>Tiny Tiny RSS : Feedlist</title>
			<link rel=\"stylesheet\" href=\"tt-rss.css\" type=\"text/css\">";

		$user_theme = $_SESSION["theme"];
		if ($user_theme) { 
			print "<link rel=\"stylesheet\" type=\"text/css\" 
				href=\"themes/$user_theme/theme.css\">";
		}

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
			<!--[if gte IE 5.5000]>
			<script type=\"text/javascript\" src=\"pngfix.js\"></script>
			<link rel=\"stylesheet\" type=\"text/css\" href=\"tt-rss-ie.css\">
			<![endif]-->
			</head><body>
			<script type=\"text/javascript\">
				if (document.addEventListener) {
					document.addEventListener(\"DOMContentLoaded\", init, null);
				}
				window.onload = init;
			</script>";

		print "<ul class=\"feedList\" id=\"feedList\">\n";

		$owner_uid = $_SESSION["uid"];

		if (!$tags) {

			/* virtual feeds */

			if (get_pref($link, 'ENABLE_FEED_CATS')) {
				print "<li class=\"feedCat\">Special</li>";
				print "<li id=\"feedCatHolder\"><ul class=\"feedCatList\">";
			}

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

			if (get_pref($link, 'ENABLE_FEED_CATS')) {
				print "</ul>\n";
			}

			if (GLOBAL_ENABLE_LABELS && get_pref($link, 'ENABLE_LABELS')) {
	
				$result = db_query($link, "SELECT id,sql_exp,description FROM
					ttrss_labels WHERE owner_uid = '$owner_uid' ORDER by description");
		
				if (db_num_rows($result) > 0) {
					if (get_pref($link, 'ENABLE_FEED_CATS')) {
						print "<li class=\"feedCat\">Labels</li>";
						print "<li id=\"feedCatHolder\"><ul class=\"feedCatList\">";
					} else {
						print "<li><hr></li>";
					}
				}
		
				while ($line = db_fetch_assoc($result)) {
	
					error_reporting (0);
		
					$tmp_result = db_query($link, "SELECT count(id) as count 
						FROM ttrss_entries,ttrss_user_entries
						WHERE (" . $line["sql_exp"] . ") AND unread = true AND
						ttrss_user_entries.ref_id = ttrss_entries.id
						AND owner_uid = '$owner_uid'");
	
					$count = db_fetch_result($tmp_result, 0, "count");
	
					$class = "label";
	
					if ($count > 0) {
						$class .= "Unread";
					}
					
					error_reporting (DEFAULT_ERROR_LEVEL);
	
					printFeedEntry(-$line["id"]-11, 
						$class, $line["description"], $count, "images/label.png", $link);
		
				}

				if (db_num_rows($result) > 0) {
					if (get_pref($link, 'ENABLE_FEED_CATS')) {
						print "</ul>";
					}
				}

			}

//			if (!get_pref($link, 'ENABLE_FEED_CATS')) {
				print "<li><hr></li>";
//			}

			if (get_pref($link, 'ENABLE_FEED_CATS')) {
				$order_by_qpart = "category,title";
			} else {
				$order_by_qpart = "title";
			}

			$result = db_query($link, "SELECT ttrss_feeds.*,
				(SELECT COUNT(id) FROM ttrss_entries,ttrss_user_entries
					WHERE feed_id = ttrss_feeds.id AND 
					ttrss_user_entries.ref_id = ttrss_entries.id AND
					owner_uid = '$owner_uid') AS total,
				(SELECT COUNT(id) FROM ttrss_entries,ttrss_user_entries
					WHERE feed_id = ttrss_feeds.id AND unread = true
						AND ttrss_user_entries.ref_id = ttrss_entries.id
						AND owner_uid = '$owner_uid') as unread,
				cat_id,last_error,
				ttrss_feed_categories.title AS category,
				ttrss_feed_categories.collapsed	
				FROM ttrss_feeds LEFT JOIN ttrss_feed_categories 
					ON (ttrss_feed_categories.id = cat_id)				
				WHERE 
					ttrss_feeds.owner_uid = '$owner_uid' AND parent_feed IS NULL
				ORDER BY $order_by_qpart"); 

			$actid = $_GET["actid"];
	
			/* real feeds */
	
			$lnum = 0;
	
			$total_unread = 0;

			$category = "";
	
			while ($line = db_fetch_assoc($result)) {
			
				$feed = db_unescape_string($line["title"]);
				$feed_id = $line["id"];	  
	
				$subop = $_GET["subop"];
				
				$total = $line["total"];
				$unread = $line["unread"];

				$tmp_result = db_query($link,
					"SELECT id,COUNT(unread) AS unread
					FROM ttrss_feeds LEFT JOIN ttrss_user_entries 
						ON (ttrss_feeds.id = ttrss_user_entries.feed_id) 
					WHERE parent_feed = '$feed_id' AND unread = true 
					GROUP BY ttrss_feeds.id");
			
				if (db_num_rows($tmp_result) > 0) {				
					while ($l = db_fetch_assoc($tmp_result)) {
						$unread += $l["unread"];
					}
				}

				$cat_id = $line["cat_id"];

				$tmp_category = $line["category"];

				if (!$tmp_category) {
					$tmp_category = "Uncategorized";
				}
				
	//			$class = ($lnum % 2) ? "even" : "odd";

				if ($line["last_error"]) {
					$class = "error";
				} else {
					$class = "feed";
				}
	
				if ($unread > 0) $class .= "Unread";
	
				if ($actid == $feed_id) {
					$class .= "Selected";
				}
	
				$total_unread += $unread;

				if ($category != $tmp_category && get_pref($link, 'ENABLE_FEED_CATS')) {
				
					if ($category) {
						print "</ul></li>";
					}
				
					$category = $tmp_category;

					$collapsed = $line["collapsed"];

					// workaround for NULL category
					if ($category == "Uncategorized") {
						if ($_COOKIE["ttrss_vf_uclps"] == 1) {
							$collapsed = "t";
						}
					}

					if ($collapsed == "t" || $collapsed == "1") {
						$holder_class = "invisible";
						$ellipsis = "...";
					} else {
						$holder_class = "";
						$ellipsis = "";
					}

					if ($cat_id) {
						$cat_id_qpart = "cat_id = '$cat_id'";
					} else {
						$cat_id_qpart = "cat_id IS NULL";
					}

					$tmp_result = db_query($link, "SELECT count(int_id) AS unread
						FROM ttrss_user_entries,ttrss_feeds WHERE
							unread = true AND
							feed_id = ttrss_feeds.id AND $cat_id_qpart AND
							ttrss_user_entries.owner_uid = " . $_SESSION["uid"]);

					$cat_unread = db_fetch_result($tmp_result, 0, "unread");

					$cat_id = sprintf("%d", $cat_id);
					
					print "<li class=\"feedCat\" id=\"FCAT-$cat_id\">
						<a href=\"javascript:toggleCollapseCat($cat_id)\">$tmp_category
							<span id=\"FCATCTR-$cat_id\" 
							class=\"$catctr_class\">($cat_unread unread)$ellipsis</span></a></li>";

					// !!! NO SPACE before <ul...feedCatList - breaks firstChild DOM function
					// -> keyboard navigation, etc.
					print "<li id=\"feedCatHolder\" class=\"$holder_class\"><ul class=\"feedCatList\" id=\"FCATLIST-$cat_id\">";
				}
	
				printFeedEntry($feed_id, $class, $feed, $unread, 
					"icons/$feed_id.ico", $link);
	
				++$lnum;
			}

		} else {

			// tags

/*			$result = db_query($link, "SELECT tag_name,count(ttrss_entries.id) AS count
				FROM ttrss_tags,ttrss_entries,ttrss_user_entries WHERE
				post_int_id = ttrss_user_entries.int_id AND 
				unread = true AND ref_id = ttrss_entries.id
				AND ttrss_tags.owner_uid = '$owner_uid' GROUP BY tag_name	
			UNION
				select tag_name,0 as count FROM ttrss_tags WHERE owner_uid = '$owner_uid'
			ORDER BY tag_name"); */

			$result = db_query($link, "SELECT tag_name,SUM((SELECT COUNT(int_id) 
				FROM ttrss_user_entries WHERE int_id = post_int_id 
					AND unread = true)) AS count FROM ttrss_tags 
				WHERE owner_uid = 2 GROUP BY tag_name ORDER BY tag_name");

			$tags = array();
	
			while ($line = db_fetch_assoc($result)) {
				$tags[$line["tag_name"]] += $line["count"];
			}
	
			foreach (array_keys($tags) as $tag) {
	
				$unread = $tags[$tag];
	
				$class = "tag";
	
				if ($unread > 0) {
					$class .= "Unread";
				}
	
				printFeedEntry($tag, $class, $tag, $unread, "images/tag.png", $link);
	
			} 

		}

		if (db_num_rows($result) == 0) {
			if ($tags) {
				$what = "tags";
			} else {
				$what = "feeds";
			}
			print "<li>No $what to display.</li>";
		}

		print "</ul>";

		print '
			<script type="text/javascript">
				/* for IE */
				function statechange() {
					if (document.readyState == "interactive") init();
				}
			
				if (document.readyState) {	
					if (document.readyState == "interactive" || document.readyState == "complete") {
						init();
					} else {
						document.onreadystatechange = statechange;
					}
				}
			</script></body></html>';
	}


	if ($op == "rpc") {

		$subop = $_GET["subop"];

		if ($subop == "setpref") {
			if (WEB_DEMO_MODE) {
				return;
			}

			print "<rpc-reply>";

			$key = db_escape_string($_GET["key"]);
			$value = db_escape_string($_GET["value"]);

			set_pref($link, $key, $value);

			print "<param-set key=\"$key\" value=\"$value\"/>";

			print "</rpc-reply>";

		}

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
			getAllCounters($link);
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
				"SELECT feed_url FROM ttrss_feeds WHERE id = '$feed_id'
					AND owner_uid = " . $_SESSION["uid"]);

			if (db_num_rows($result) > 0) {			
				$feed_url = db_fetch_result($result, 0, "feed_url");
				update_rss_feed($link, $feed_url, $feed_id);
			}

			print "<rpc-reply>";
			getFeedCounter($link, $feed_id);
			print "</rpc-reply>";
			
			return;
		}

		if ($subop == "forceUpdateAllFeeds" || $subop == "updateAllFeeds") {
	
			if (ENABLE_UPDATE_DAEMON) {

				if ($subop == "forceUpdateAllFeeds") {				

					$result = db_query($link, "SELECT count(id) AS cid FROM
						ttrss_scheduled_updates WHERE feed_id IS NULL AND
							owner_uid = " . $_SESSION["uid"]);
	
					$cid = db_fetch_result($result, 0, "cid");
	
#					print "<rpc-reply>";
	
					if ($cid == 0) {
	
						db_query($link, "INSERT INTO ttrss_scheduled_updates
							(owner_uid, feed_id, entered) VALUES
							(".$_SESSION["uid"].", NULL, NOW())");
							
//						print "<!-- ScheduledOK -->";
						
					} else {
//						print "<!-- RequestAlreadyInQueue -->";
					}
	
#					print "</rpc-reply>";
				}
				
			} else {	
				update_all_feeds($link, $subop == "forceUpdateAllFeeds");
			}

			$global_unread_caller = sprintf("%d", $_GET["uctr"]);
			$global_unread = getGlobalUnread($link);

			print "<rpc-reply>";

			if ($global_unread_caller != $global_unread) {

	 			$omode = $_GET["omode"];
	 
	 			if (!$omode) $omode = "tflc";
	 
	 			if (strchr($omode, "l")) getLabelCounters($link);
	 			if (strchr($omode, "f")) getFeedCounters($link);
	 			if (strchr($omode, "t")) getTagCounters($link);
	 			if (strchr($omode, "c")) {			
		 			if (get_pref($link, 'ENABLE_FEED_CATS')) {
		 				getCategoryCounters($link);
		 			}
				}
			}

 			getGlobalCounters($link, $global_unread);

			print "</rpc-reply>";

		}
	
		/* GET["cmode"] = 0 - mark as read, 1 - as unread, 2 - toggle */
		if ($subop == "catchupSelected") {

			$ids = split(",", db_escape_string($_GET["ids"]));

			$cmode = sprintf("%d", $_GET["cmode"]);

			foreach ($ids as $id) {

				if ($cmode == 0) {
					db_query($link, "UPDATE ttrss_user_entries SET 
					unread = false,last_read = NOW()
					WHERE ref_id = '$id' AND owner_uid = " . $_SESSION["uid"]);
				} else if ($cmode == 1) {
					db_query($link, "UPDATE ttrss_user_entries SET 
					unread = true
					WHERE ref_id = '$id' AND owner_uid = " . $_SESSION["uid"]);
				} else {
					db_query($link, "UPDATE ttrss_user_entries SET 
					unread = NOT unread,last_read = NOW()
					WHERE ref_id = '$id' AND owner_uid = " . $_SESSION["uid"]);
				}
			}
			print "<rpc-reply>";
			getAllCounters($link);
			print "</rpc-reply>";
		}

		if ($subop == "markSelected") {

			$ids = split(",", db_escape_string($_GET["ids"]));

			$cmode = sprintf("%d", $_GET["cmode"]);

			foreach ($ids as $id) {

				if ($cmode == 0) {
					db_query($link, "UPDATE ttrss_user_entries SET 
					marked = false
					WHERE ref_id = '$id' AND owner_uid = " . $_SESSION["uid"]);
				} else if ($cmode == 1) {
					db_query($link, "UPDATE ttrss_user_entries SET 
					marked = true
					WHERE ref_id = '$id' AND owner_uid = " . $_SESSION["uid"]);
				} else {
					db_query($link, "UPDATE ttrss_user_entries SET 
					marked = NOT marked
					WHERE ref_id = '$id' AND owner_uid = " . $_SESSION["uid"]);
				}
			}
			print "<rpc-reply>";
			getAllCounters($link);
			print "</rpc-reply>";
		}

		if ($subop == "sanityCheck") {
			if (sanity_check($link)) {
				print "<error error-code=\"0\"/>";
			}
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
			db_query($link, "UPDATE ttrss_user_entries SET 
				last_read = NOW(),unread = false WHERE owner_uid = " . $_SESSION["uid"]);
		}

		if ($subop == "collapse") {
			$cat_id = db_escape_string($_GET["cid"]);

			db_query($link, "UPDATE ttrss_feed_categories SET
				collapsed = NOT collapsed WHERE id = '$cat_id' AND owner_uid = " . 
				$_SESSION["uid"]);
			return;
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

		$result = db_query($link, "SELECT title,link,content,feed_id,comments,int_id,
			SUBSTRING(updated,1,16) as updated,
			(SELECT icon_url FROM ttrss_feeds WHERE id = feed_id) as icon_url,
			num_comments
			FROM ttrss_entries,ttrss_user_entries
			WHERE	id = '$id' AND ref_id = id");

		if ($addheader) {
			print "<html><head>
				<title>Tiny Tiny RSS : Article $id</title>
				<link rel=\"stylesheet\" href=\"tt-rss.css\" type=\"text/css\">";

			$user_theme = $_SESSION["theme"];
			if ($user_theme) { 
				print "<link rel=\"stylesheet\" type=\"text/css\" 
					href=\"themes/$user_theme/theme.css\">";
			}

			if (get_pref($link, 'USE_COMPACT_STYLESHEET')) {
				print "<link rel=\"stylesheet\" type=\"text/css\" 
					href=\"tt-rss_compact.css\"/>";
			} else {
				print "<link title=\"Compact Stylesheet\" rel=\"alternate stylesheet\" 
						type=\"text/css\" href=\"tt-rss_compact.css\"/>";
			}

			print "<script type=\"text/javascript\" src=\"functions.js\"></script>
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

/*			if ($line["comments"] && $line["link"] != $line["comments"]) {
				$entry_comments = "(<a href=\"".$line["comments"]."\">Comments</a>)";
			} else {
				$entry_comments = "";
			} */

			$num_comments = $line["num_comments"];
			$entry_comments = "";

			if ($num_comments > 0) {
				if ($line["comments"]) {
					$comments_url = $line["comments"];
				} else {
					$comments_url = $line["link"];
				}
				$entry_comments = "<a href=\"$comments_url\">$num_comments comments</a>";
			} else {
				if ($line["comments"] && $line["link"] != $line["comments"]) {
					$entry_comments = "<a href=\"".$line["comments"]."\">comments</a>";
				}				
			}

			print "<div class=\"postReply\">";

			print "<div class=\"postHeader\"><table width=\"100%\">";

			print "<tr><td><a href=\"" . $line["link"] . "\">" . $line["title"] . "</a></td>";

			$parsed_updated = date(get_pref($link, 'LONG_DATE_FORMAT'), 
				strtotime($line["updated"]));
		
			print "<td class=\"postDate\">$parsed_updated</td>";
						
			print "</tr>";

			$tmp_result = db_query($link, "SELECT DISTINCT tag_name FROM
				ttrss_tags WHERE post_int_id = " . $line["int_id"] . "
				ORDER BY tag_name");
	
			$tags_str = "";
			$f_tags_str = "";

			$num_tags = 0;

			while ($tmp_line = db_fetch_assoc($tmp_result)) {
				$num_tags++;
				$tag = $tmp_line["tag_name"];				
				$tag_str = "<a href=\"javascript:parent.viewfeed('$tag')\">$tag</a>, "; 
				
				if ($num_tags == 5) {
					$tags_str .= "<a href=\"javascript:showBlockElement('allEntryTags')\">...</a>";
				} else if ($num_tags < 5) {
					$tags_str .= $tag_str;
				}
				$f_tags_str .= $tag_str;
			}

			$tags_str = preg_replace("/, $/", "", $tags_str);
			$f_tags_str = preg_replace("/, $/", "", $f_tags_str);

//			$truncated_link = truncate_string($line["link"], 60);

			if ($tags_str || $entry_comments) {
				print "<tr><td width='50%'>
					$entry_comments</td>
					<td align=\"right\">$tags_str</td></tr>";
			}

			print "</table></div>";

			print "<div class=\"postIcon\">" . $feed_icon . "</div>";
			print "<div class=\"postContent\">";
			
			if (db_num_rows($tmp_result) > 5) {
				print "<div id=\"allEntryTags\">Tags: $f_tags_str</div>";
			}

			print $line["content"] . "</div>";
			
			print "</div>";

			print "<script type=\"text/javascript\">
				update_all_counters('$feed_id');
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
			return;
		}

		if (!$skip) $skip = 0;

		if ($subop == "undefined") $subop = "";

		if ($addheader) {
			print "<html><head>
				<title>Tiny Tiny RSS : Feed $feed</title>
				<link rel=\"stylesheet\" href=\"tt-rss.css\" type=\"text/css\">";

			$user_theme = $_SESSION["theme"];
			if ($user_theme) { 
				print "<link rel=\"stylesheet\" type=\"text/css\" 
					href=\"themes/$user_theme/theme.css\">";
			}

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
				<!--[if gte IE 5.5000]>
				<script type=\"text/javascript\" src=\"pngfix.js\"></script>
				<link rel=\"stylesheet\" type=\"text/css\" href=\"tt-rss-ie.css\">
				<![endif]-->
				</head><body>
				<script type=\"text/javascript\">
				if (document.addEventListener) {
					document.addEventListener(\"DOMContentLoaded\", init, null);
				}
				window.onload = init;
				</script>";
		}

		if ($subop == "ForceUpdate" && sprintf("%d", $feed) > 0) {

/*			if (ENABLE_UPDATE_DAEMON) {

				if ($cid == 0) {

					db_query($link, "INSERT INTO ttrss_scheduled_updates
						(owner_uid, feed_id, entered) VALUES
						(".$_SESSION["uid"].", '$feed', NOW())");
				}

			} else { 
				// fixme update_rss_feed...
			} */

			$tmp_result = db_query($link, "SELECT feed_url FROM ttrss_feeds
				WHERE id = '$feed'");
			$feed_url = db_fetch_result($tmp_result, 0, "feed_url");				
			update_rss_feed($link, $feed_url, $feed, ENABLE_UPDATE_DAEMON);
		}

		if ($subop == "MarkAllRead")  {

			if (sprintf("%d", $feed) != 0) {
			
				if ($feed > 0) {

					$tmp_result = db_query($link, "SELECT id 
						FROM ttrss_feeds WHERE parent_feed = '$feed'
						ORDER BY cat_id,title");

					$parent_ids = array();

					if (db_num_rows($tmp_result) > 0) {
						while ($p = db_fetch_assoc($tmp_result)) {
							array_push($parent_ids, "feed_id = " . $p["id"]);
						}

						$children_qpart = implode(" OR ", $parent_ids);
						
						db_query($link, "UPDATE ttrss_user_entries 
							SET unread = false,last_read = NOW() 
							WHERE (feed_id = '$feed' OR $children_qpart) 
							AND owner_uid = " . $_SESSION["uid"]);

					} else {						
						db_query($link, "UPDATE ttrss_user_entries 
							SET unread = false,last_read = NOW() 
							WHERE feed_id = '$feed' AND owner_uid = " . $_SESSION["uid"]);
					}
						
				} else if ($feed < 0 && $feed > -10) { // special, like starred

					if ($feed == -1) {
						db_query($link, "UPDATE ttrss_user_entries 
							SET unread = false,last_read = NOW()
							WHERE marked = true AND owner_uid = ".$_SESSION["uid"]);
					}
			
				} else if ($feed < -10) { // label

					// TODO make this more efficient

					$label_id = -$feed - 11;

					$tmp_result = db_query($link, "SELECT sql_exp FROM ttrss_labels
						WHERE id = '$label_id'");					

					if ($tmp_result) {
						$sql_exp = db_fetch_result($tmp_result, 0, "sql_exp");

						db_query($link, "BEGIN");

						$tmp2_result = db_query($link,
							"SELECT 
								int_id 
							FROM 
								ttrss_user_entries,ttrss_entries 
							WHERE
								ref_id = id AND 
								$sql_exp AND
								owner_uid = " . $_SESSION["uid"]);

						while ($tmp_line = db_fetch_assoc($tmp2_result)) {
							db_query($link, "UPDATE 
								ttrss_user_entries 
							SET 
								unread = false, last_read = NOW()
							WHERE
								int_id = " . $tmp_line["int_id"]);
						}
								
						db_query($link, "COMMIT");

/*						db_query($link, "UPDATE ttrss_user_entries,ttrss_entries 
							SET unread = false,last_read = NOW()
							WHERE $sql_exp
							AND ref_id = id
							AND owner_uid = ".$_SESSION["uid"]); */
					}
				}
			} else { // tag
				db_query($link, "BEGIN");

				$tag_name = db_escape_string($feed);

				$result = db_query($link, "SELECT post_int_id FROM ttrss_tags
					WHERE tag_name = '$tag_name' AND owner_uid = " . $_SESSION["uid"]);

				while ($line = db_fetch_assoc($result)) {
					db_query($link, "UPDATE ttrss_user_entries SET
						unread = false, last_read = NOW() 
						WHERE int_id = " . $line["post_int_id"]);
				}
				db_query($link, "COMMIT");
			}

		}

		$search = db_escape_string($_GET["search"]);
		$search_mode = db_escape_string($_GET["smode"]);

		if ($search) {
			$search_query_part = "(upper(ttrss_entries.title) LIKE upper('%$search%') 
				OR ttrss_entries.content LIKE '%$search%') AND";
		} else {
			$search_query_part = "";
		}

		$view_query_part = "";

		if ($view_mode == "Adaptive") {
			if ($search) {
				$view_query_part = " ";
			} else if ($feed != -1) {
				$unread = getFeedUnread($link, $feed);
				if ($unread > 0) {
					$view_query_part = " unread = true AND ";
				}
			}
		}

		if ($view_mode == "Starred") {
			$view_query_part = " marked = true AND ";
		}

		if ($view_mode == "Unread") {
			$view_query_part = " unread = true AND ";
		}

		if ($limit && $limit != "All") {
			$limit_query_part = "LIMIT " . $limit;
		} 

		$vfeed_query_part = "";

		// override query strategy and enable feed display when searching globally
		if ($search && $search_mode == "All feeds") {
			$query_strategy_part = "ttrss_entries.id > 0";
			$vfeed_query_part = "ttrss_feeds.title AS feed_title,";
		} else if (sprintf("%d", $feed) == 0) {
			$query_strategy_part = "ttrss_entries.id > 0";
			$vfeed_query_part = "(SELECT title FROM ttrss_feeds WHERE
				id = feed_id) as feed_title,";
		} else if ($feed >= 0) {
		
			$tmp_result = db_query($link, "SELECT id 
				FROM ttrss_feeds WHERE parent_feed = '$feed'
				ORDER BY cat_id,title");

			$parent_ids = array();

			if (db_num_rows($tmp_result) > 0) {
				while ($p = db_fetch_assoc($tmp_result)) {
					array_push($parent_ids, "feed_id = " . $p["id"]);
				}

				$query_strategy_part = sprintf("(feed_id = %d OR %s)", 
					$feed, implode(" OR ", $parent_ids));
	
				$vfeed_query_part = "ttrss_feeds.title AS feed_title,";
			} else {
				$query_strategy_part = "feed_id = '$feed'";
			}
		} else if ($feed == -1) { // starred virtual feed
			$query_strategy_part = "marked = true";
			$vfeed_query_part = "ttrss_feeds.title AS feed_title,";
		} else if ($feed <= -10) { // labels
			$label_id = -$feed - 11;

			$tmp_result = db_query($link, "SELECT sql_exp FROM ttrss_labels
				WHERE id = '$label_id'");
		
			$query_strategy_part = db_fetch_result($tmp_result, 0, "sql_exp");
	
			$vfeed_query_part = "ttrss_feeds.title AS feed_title,";
		} else {
			$query_strategy_part = "id > 0"; // dumb
		}

		$order_by = "updated DESC";

//		if ($feed < -10) {
//			$order_by = "feed_id,updated DESC";
//		}

		$feed_title = "";

		if ($search && $search_mode == "All feeds") {
			$feed_title = "Global search results ($search)";
		} else if ($search && sprintf("%d", $feed) == 0) {
			$feed_title = "Feed search results ($search, $feed)";
		} else if ($feed > 0) {
			$result = db_query($link, "SELECT title,site_url,last_error FROM ttrss_feeds 
				WHERE id = '$feed'");

			$feed_title = db_fetch_result($result, 0, "title");
			$feed_site_url = db_fetch_result($result, 0, "site_url");
			$last_error = db_fetch_result($result, 0, "last_error");

		} else if ($feed == -1) {
			$feed_title = "Starred articles";
		} else if ($feed < -10) {
			$label_id = -$feed - 11;
			$result = db_query($link, "SELECT description FROM ttrss_labels
				WHERE id = '$label_id'");
			$feed_title = db_fetch_result($result, 0, "description");
		} else {
			$feed_title = "?";
		}

		if ($feed < -10) error_reporting (0);

		print "<div id=\"headlinesContainer\">";

		if (sprintf("%d", $feed) != 0) {

			if ($feed > 0) {			
				$feed_kind = "Feeds";
			} else {
				$feed_kind = "Labels";
			}

//			if (!$vfeed_query_part) {
			$content_query_part = "content as content_preview,";
//			} else {
//				$content_query_part = "";
//			}

			$result = db_query($link, "SELECT 
					ttrss_entries.id,ttrss_entries.title,
					SUBSTRING(updated,1,16) as updated,
					unread,feed_id,marked,link,last_read,
					SUBSTRING(last_read,1,19) as last_read_noms,
					$vfeed_query_part
					$content_query_part
					SUBSTRING(updated,1,19) as updated_noms
				FROM
					ttrss_entries,ttrss_user_entries,ttrss_feeds
				WHERE
				ttrss_user_entries.feed_id = ttrss_feeds.id AND
				ttrss_user_entries.ref_id = ttrss_entries.id AND
				ttrss_user_entries.owner_uid = '".$_SESSION["uid"]."' AND
				$search_query_part
				$view_query_part
				$query_strategy_part ORDER BY $order_by
				$limit_query_part");

		} else {
			// browsing by tag

			$feed_kind = "Tags";

			$result = db_query($link, "SELECT
				ttrss_entries.id as id,title,
				SUBSTRING(updated,1,16) as updated,
				unread,feed_id,
				marked,link,last_read,				
				SUBSTRING(last_read,1,19) as last_read_noms,
				$vfeed_query_part
				$content_query_part
				SUBSTRING(updated,1,19) as updated_noms
				FROM
					ttrss_entries,ttrss_user_entries,ttrss_tags
				WHERE
					ref_id = ttrss_entries.id AND
					ttrss_user_entries.owner_uid = '".$_SESSION["uid"]."' AND
					post_int_id = int_id AND tag_name = '$feed' AND
					$view_query_part
					$search_query_part
					$query_strategy_part ORDER BY $order_by
				$limit_query_part");	
		}

		if (!$result) {
			print "<div align='center'>
				Could not display feed (query failed). Please check label match syntax or local configuration.</div>";
			return;
		}

		function print_headline_subtoolbar($link, $feed_site_url, $feed_title, 
						$bottom = false) {

			if (!$bottom) {
				$class = "headlinesSubToolbar";
				$tid = "headlineActionsTop";
			} else {
				$class = "invisible";
				$tid = "headlineActionsBottom";
			}

			print "<table class=\"$class\" id=\"$tid\"
				width=\"100%\" cellspacing=\"0\" cellpadding=\"0\"><tr>";

			if (!get_pref($link, 'COMBINED_DISPLAY_MODE')) {

				print "<td class=\"headlineActions\">
					Select: 
								<a href=\"javascript:selectTableRowsByIdPrefix('headlinesList', 
									'RROW-', 'RCHK-', true, '', true)\">All</a>,
								<a href=\"javascript:selectTableRowsByIdPrefix('headlinesList', 
									'RROW-', 'RCHK-', true, 'Unread', true)\">Unread</a>,
								<a href=\"javascript:selectTableRowsByIdPrefix('headlinesList', 
									'RROW-', 'RCHK-', false)\">None</a>
						&nbsp;&nbsp;
						Toggle: <a href=\"javascript:selectionToggleUnread()\">Unread</a>,
								<a href=\"javascript:selectionToggleMarked()\">Starred</a>";
				print "</td>";

			} else {

				print "<td class=\"headlineActions\">
					Select: 
								<a href=\"javascript:cdmSelectArticles('all')\">All</a>,
								<a href=\"javascript:cdmSelectArticles('unread')\">Unread</a>,
								<a href=\"javascript:cdmSelectArticles('none')\">None</a>
						&nbsp;&nbsp;
						Toggle: <a href=\"javascript:selectionToggleUnread(true)\">Unread</a>,
								<a href=\"javascript:selectionToggleMarked(true)\">Starred</a>";
			
				print "</td>";

			}

			if ($last_error) {
					print "<td align='center' class='small'>
						<a class=\"warning\" href=\"javascript:alert('TT-RSS encountered an error while trying to update this feed.\\n\\n$last_error')\">Could not update this feed.</a></td>";
			}	

			print "<td class=\"headlineTitle\">";
		
			if ($feed_site_url) {
				print "<a target=\"_blank\" href=\"$feed_site_url\">$feed_title</a>";
			} else {
				print $feed_title;
			}
				
			print "</td>";
			print "</tr></table>";

		}
	
		if (db_num_rows($result) > 0) {

			print_headline_subtoolbar($link, $feed_site_url, $feed_title);

			if (!get_pref($link, 'COMBINED_DISPLAY_MODE')) {
				print "<table class=\"headlinesList\" id=\"headlinesList\" 
					cellspacing=\"0\" width=\"100%\">";
			}

			$lnum = 0;
	
			error_reporting (DEFAULT_ERROR_LEVEL);
	
			$num_unread = 0;
	
			while ($line = db_fetch_assoc($result)) {

				$class = ($lnum % 2) ? "even" : "odd";
	
				$id = $line["id"];
				$feed_id = $line["feed_id"];
	
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
					$is_unread = true;
				} else {
					$is_unread = false;
				}
	
				if ($line["marked"] == "t" || $line["marked"] == "1") {
					$marked_pic = "<img id=\"FMARKPIC-$id\" src=\"images/mark_set.png\" 
						alt=\"Reset mark\" onclick='javascript:toggleMark($id)'>";
				} else {
					$marked_pic = "<img id=\"FMARKPIC-$id\" src=\"images/mark_unset.png\" 
						alt=\"Set mark\" onclick='javascript:toggleMark($id)'>";
				}
	
				$content_link = "<a href=\"javascript:view($id,$feed_id);\">" .
					$line["title"] . "</a>";

				if (get_pref($link, 'HEADLINES_SMART_DATE')) {
					$updated_fmt = smart_date_time(strtotime($line["updated"]));
				} else {
					$short_date = get_pref($link, 'SHORT_DATE_FORMAT');
					$updated_fmt = date($short_date, strtotime($line["updated"]));
				}				

				if (get_pref($link, 'SHOW_CONTENT_PREVIEW')) {
					$content_preview = truncate_string(strip_tags($line["content_preview"]), 
						100);
				}

				if (!get_pref($link, 'COMBINED_DISPLAY_MODE')) {
					
					print "<tr class='$class' id='RROW-$id'>";
		
					print "<td class='hlUpdatePic'>$update_pic</td>";
		
					print "<td class='hlSelectRow'>
						<input type=\"checkbox\" onclick=\"toggleSelectRow(this)\"
							class=\"feedCheckBox\" id=\"RCHK-$id\">
						</td>";
		
					print "<td class='hlMarkedPic'>$marked_pic</td>";
		
					if ($line["feed_title"]) {			
						print "<td class='hlContent'>$content_link</td>";
						print "<td class='hlFeed'>
							<a href='javascript:viewfeed($feed_id)'>".
								$line["feed_title"]."</a>&nbsp;</td>";
					} else {			
						print "<td class='hlContent' valign='middle'>";
		
						print "<a href=\"javascript:view($id,$feed_id);\">" .
							$line["title"];
		
						if (get_pref($link, 'SHOW_CONTENT_PREVIEW')) {
								
							if ($content_preview) {
								print "<span class=\"contentPreview\"> - $content_preview</span>";
							}
						}
		
						print "</a>";
						print "</td>";
					}
					
					print "<td class=\"hlUpdated\"><nobr>$updated_fmt&nbsp;</nobr></td>";
		
					print "</tr>";

				} else {
					
					if ($is_unread) {
						$add_class = "Unread";
					} else {
						$add_class = "";
					}	
					
					print "<div class=\"cdmArticle$add_class\" id=\"RROW-$id\">";

					print "<div class=\"cdmHeader\">";

					print "<div style=\"float : right\">$updated_fmt</div>";
					
					print "<a target=\"new\" href=\"".$line["link"]."\">".$line["title"]."</a>";

					if ($line["feed_title"]) {	
						print "&nbsp;(<a href='javascript:viewfeed($feed_id)'>".$line["feed_title"]."</a>)";
					}

					print "</div>";

					print "<div class=\"cdmContent\">" . $line["content_preview"] . "</div>";
	
					print "<div style=\"float : right\">$marked_pic</div>
						<div class=\"cdmFooter\">
							<input type=\"checkbox\" onclick=\"toggleSelectRowById(this, 
							'RROW-$id')\" class=\"feedCheckBox\" id=\"RCHK-$id\"></div>";

					print "</div>";	

				}				
	
				++$lnum;
			}

			if (!get_pref($link, 'COMBINED_DISPLAY_MODE')) {			
				print "</table>";
			}

			print_headline_subtoolbar($link, 
				"javascript:catchupPage()", "Mark page as read", true);


		} else {
			print "<div width='100%' align='center'>No articles found.</div>";
		}

		print "</div>";

		print "<script type=\"text/javascript\">
			document.onkeydown = hotkey_handler;
			// if (parent.daemon_enabled) parent.updateTitle('$feed_title');
			update_all_counters('$feed');
		</script>";
	
		print '
			<script type="text/javascript">
				/* for IE */
				function statechange() {
					if (document.readyState == "interactive") init();
				}
			
				if (document.readyState) {	
					if (document.readyState == "interactive" || document.readyState == "complete") {
						init();
					} else {
						document.onreadystatechange = statechange;
					}
				}
			</script>';

		if ($addheader) {
			print "</body></html>";
		}
	}

	if ($op == "pref-rpc") {

		$subop = $_GET["subop"];

		if ($subop == "unread") {
			$ids = split(",", db_escape_string($_GET["ids"]));
			foreach ($ids as $id) {
				db_query($link, "UPDATE ttrss_user_entries SET unread = true 
					WHERE feed_id = '$id' AND owner_uid = ".$_SESSION["uid"]);
			}

			print "Marked selected feeds as unread.";
		}

		if ($subop == "read") {
			$ids = split(",", db_escape_string($_GET["ids"]));
			foreach ($ids as $id) {
				db_query($link, "UPDATE ttrss_user_entries 
					SET unread = false,last_read = NOW() WHERE 
						feed_id = '$id' AND owner_uid = ".$_SESSION["uid"]);
			}

			print "Marked selected feeds as read.";

		}

	}

	if ($op == "pref-feeds") {
	
		$subop = $_REQUEST["subop"];
		$quiet = $_REQUEST["quiet"];

		if ($subop == "massSubscribe") {
			$ids = split(",", db_escape_string($_GET["ids"]));

			$subscribed = array();

			foreach ($ids as $id) {
				$result = db_query($link, "SELECT feed_url,title FROM ttrss_feeds
					WHERE id = '$id'");

				$feed_url = db_fetch_result($result, 0, "feed_url");
				$title = db_fetch_result($result, 0, "title");

				$result = db_query($link, "SELECT id FROM ttrss_feeds WHERE
					feed_url = '$feed_url' AND owner_uid = " . $_SESSION["uid"]);

				if (db_num_rows($result) == 0) {			
					$result = db_query($link,
						"INSERT INTO ttrss_feeds (owner_uid,feed_url,title,cat_id) 
						VALUES ('".$_SESSION["uid"]."', '$feed_url', '$title', NULL)");

					array_push($subscribed, $title);
				}
			}

			if (count($subscribed) > 0) {
				print "<div class=\"notice\">";
				print "<b>Subscribed to feeds:</b>";
				print "<ul class=\"nomarks\">";
				foreach ($subscribed as $title) {
					print "<li>$title</li>";
				}
				print "</ul>";
				print "</div>";
			}
		}		

		if ($subop == "browse") {

			if (!ENABLE_FEED_BROWSER) {
				print "Feed browser is administratively disabled.";
				return;
			}
			
			print "<div class=\"infoBoxContents\">";

			print "<h1>Feed browser</h1>";

			print "<p>Showing top 50 registered feeds, sorted by popularity:</p>";

			$result = db_query($link, "SELECT feed_url,count(id) AS subscribers 
				FROM ttrss_feeds 
				WHERE auth_login = '' AND auth_pass = '' AND private = false
				GROUP BY feed_url ORDER BY subscribers DESC LIMIT 50");
			
			print "<ul class='browseFeedList' id='browseFeedList'>";

			$feedctr = 0;
			
			while ($line = db_fetch_assoc($result)) {
				$feed_url = $line["feed_url"];
				$subscribers = $line["subscribers"];

				$sub_result = db_query($link, "SELECT id
					FROM ttrss_feeds WHERE feed_url = '$feed_url' AND owner_uid =" . 
					$_SESSION["uid"]);

				if (db_num_rows($sub_result) > 0) {
					continue; // already subscribed
				}
			
				$det_result = db_query($link, "SELECT site_url,title,id 
					FROM ttrss_feeds WHERE feed_url = '$feed_url' LIMIT 1");

				$details = db_fetch_assoc($det_result);
			
				$icon_file = ICONS_DIR . "/" . $details["id"] . ".ico";

				if (file_exists($icon_file) && filesize($icon_file) > 0) {
						$feed_icon = "<img class=\"tinyFeedIcon\"	src=\"" . ICONS_URL . 
							"/".$details["id"].".ico\">";
				} else {
					$feed_icon = "<img class=\"tinyFeedIcon\" src=\"images/blank_icon.gif\">";
				}

				$check_box = "<input onclick='toggleSelectListRow(this)' class='feedBrowseCB' 
					type=\"checkbox\" id=\"FBCHK-" . $details["id"] . "\">";

				$class = ($feedctr % 2) ? "even" : "odd";

				print "<li class='$class' id=\"FBROW-".$details["id"]."\">$check_box".
					"$feed_icon " . db_unescape_string($details["title"]) . 
					"&nbsp;<span class='subscribers'>($subscribers)</span></li>";

					++$feedctr;
			}

			if ($feedctr == 0) {
				print "<li>No feeds found to subscribe.</li>";
			}

			print "</ul>";

			print "<div align='center'>
				<input type=\"submit\" class=\"button\" 
				onclick=\"feedBrowserSubscribe()\" value=\"Subscribe\">
				<input type='submit' class='button'			
				onclick=\"closeInfoBox()\" value=\"Cancel\"></div>";

			print "</div>";
			return;
		}

		if ($subop == "editfeed") {
			$feed_id = db_escape_string($_GET["id"]);

			$result = db_query($link, 
				"SELECT * FROM ttrss_feeds WHERE id = '$feed_id' AND
					owner_uid = " . $_SESSION["uid"]);

			$title = htmlspecialchars(db_unescape_string(db_fetch_result($result,
				0, "title")));

			print "<div class=\"infoBoxContents\">";

			$icon_file = ICONS_DIR . "/$feed_id.ico";
	
			if (file_exists($icon_file) && filesize($icon_file) > 0) {
					$feed_icon = "<img width=\"16\" height=\"16\"
						src=\"" . ICONS_URL . "/$feed_id.ico\">";
			} else {
				$feed_icon = "";
			}
	
			print "<h1>$feed_icon $title</h1>";

			print "<table width='100%'>";

			$row_class = "odd";

			print "<tr class='$row_class'><td>Title:</td>";
			print "<td><input id=\"iedit_title\" value=\"$title\"></td></tr>";

			$feed_url = db_fetch_result($result, 0, "feed_url");
			$feed_url = htmlspecialchars(db_unescape_string(db_fetch_result($result,
				0, "feed_url")));
			$row_class = toggleEvenOdd($row_class);

			print "<tr class='$row_class'><td>Feed URL:</td>";
			print "<td><input id=\"iedit_link\" value=\"$feed_url\"></td></tr>";
	
			if (get_pref($link, 'ENABLE_FEED_CATS')) {

				$cat_id = db_fetch_result($result, 0, "cat_id");

				$row_class = toggleEvenOdd($row_class);

				print "<tr class='$row_class'><td>Category:</td>";
				print "<td>";
				print "<select id=\"iedit_fcat\">";
				print "<option id=\"0\">Uncategorized</option>";

				$tmp_result = db_query($link, "SELECT id,title FROM ttrss_feed_categories
					WHERE owner_uid = ".$_SESSION["uid"]." ORDER BY title");

				if (db_num_rows($tmp_result) > 0) {
					print "<option disabled>--------</option>";
				}

				while ($tmp_line = db_fetch_assoc($tmp_result)) {
					if ($tmp_line["id"] == $cat_id) {
						$is_selected = "selected";
					} else {
						$is_selected = "";
					}
					printf("<option $is_selected id='%d'>%s</option>", 
						$tmp_line["id"], $tmp_line["title"]);
				}

				print "</select></td>";
				print "</td></tr>";
	
			}

			$update_interval = db_fetch_result($result, 0, "update_interval");
			$row_class = toggleEvenOdd($row_class);

			print "<tr class='$row_class'><td>Update Interval:</td>";
			print "<td><input id=\"iedit_updintl\" 
				value=\"$update_interval\"></td></tr>";

			$row_class = toggleEvenOdd($row_class);
			print "<tr class='$row_class'><td>Link to:</td>";

			$tmp_result = db_query($link, "SELECT COUNT(id) AS count
				FROM ttrss_feeds WHERE parent_feed = '$feed_id'");

			$linked_count = db_fetch_result($tmp_result, 0, "count");

			$parent_feed = db_fetch_result($result, 0, "parent_feed");

			if ($linked_count > 0) {
				$disabled = "disabled";
			}

			print "<select $disabled  id=\"iedit_parent_feed\">";
			
			print "<option id=\"0\">Not linked</option>";

			if (get_pref($link, 'ENABLE_FEED_CATS')) {
				if ($cat_id) {
					$cat_qpart = "AND cat_id = '$cat_id'";
				} else {
					$cat_qpart = "AND cat_id IS NULL";
				}
			}

			$tmp_result = db_query($link, "SELECT id,title FROM ttrss_feeds
				WHERE id != '$feed_id' AND owner_uid = ".$_SESSION["uid"]." 
					$cat_qpart ORDER BY title");

				if (db_num_rows($tmp_result) > 0) {
					print "<option disabled>--------</option>";
				}

				while ($tmp_line = db_fetch_assoc($tmp_result)) {
					if ($tmp_line["id"] == $parent_feed) {
						$is_selected = "selected";
					} else {
						$is_selected = "";
					}
					printf("<option $is_selected id='%d'>%s</option>", 
						$tmp_line["id"], $tmp_line["title"]);
				}

				print "</select></td>";
				print "</td></tr>";

			$purge_interval = db_fetch_result($result, 0, "purge_interval");
			$row_class = toggleEvenOdd($row_class);

			print "<tr class='$row_class'><td>Purge Days:</td>";
			print "<td><input id=\"iedit_purgintl\" 
				value=\"$purge_interval\"></td></tr>";

//			print "<tr><td colspan=\"2\"><b>Authentication</b></td></tr>";

			$row_class = toggleEvenOdd($row_class);
			$auth_login = db_fetch_result($result, 0, "auth_login");

			print "<tr class='$row_class'><td>Login:</td>";
			print "<td><input id=\"iedit_login\" 
				value=\"$auth_login\"></td></tr>";

			$row_class = toggleEvenOdd($row_class);
			$auth_pass = db_fetch_result($result, 0, "auth_pass");

			print "<tr class='$row_class'><td>Password:</td>";
			print "<td><input type=\"password\" id=\"iedit_pass\" 
				value=\"$auth_pass\"></td></tr>";

			$row_class = toggleEvenOdd($row_class);
			$private = sql_bool_to_bool(db_fetch_result($result, 0, "private"));

			if ($private) {
				$checked = "checked";
			} else {
				$checked = "";
			}

			print "<tr class='$row_class'><td>Options:</td>";
			print "<td><input type=\"checkbox\" id=\"iedit_private\" 
				$checked><label for=\"iedit_private\">Hide from feed browser</label>
				</td></tr>";

			print "</table>";
			print "</div>";

			print "<div align='center'>
				<input type=\"submit\" class=\"button\" 
				onclick=\"feedEditSave()\" value=\"Save\">
				<input type='submit' class='button'			
				onclick=\"feedEditCancel()\" value=\"Cancel\"></div>";
			return;
		}

		if ($subop == "editSave") {
			$feed_title = db_escape_string($_POST["t"]);
			$feed_link = db_escape_string($_POST["l"]);
			$upd_intl = db_escape_string($_POST["ui"]);
			$purge_intl = db_escape_string($_POST["pi"]);
			$feed_id = db_escape_string($_POST["id"]);
			$cat_id = db_escape_string($_POST["catid"]);
			$auth_login = db_escape_string($_POST["login"]);
			$auth_pass = db_escape_string($_POST["pass"]);
			$parent_feed = db_escape_string($_POST["pfeed"]);
			$private = db_escape_string($_POST["is_pvt"]);

			if (strtoupper($upd_intl) == "DEFAULT")
				$upd_intl = 0;

			if (strtoupper($upd_intl) == "DISABLED")
				$upd_intl = -1;

			if (strtoupper($purge_intl) == "DEFAULT")
				$purge_intl = 0;

			if (strtoupper($purge_intl) == "DISABLED")
				$purge_intl = -1;

			if ($cat_id != 0) {
				$category_qpart = "cat_id = '$cat_id'";
			} else {
				$category_qpart = 'cat_id = NULL';
			}

			if ($parent_feed != 0) {
				$parent_qpart = "parent_feed = '$parent_feed'";
			} else {
				$parent_qpart = 'parent_feed = NULL';
			}

			$result = db_query($link, "UPDATE ttrss_feeds SET 
				$category_qpart,
				$parent_qpart,
				title = '$feed_title', feed_url = '$feed_link',
				update_interval = '$upd_intl',
				purge_interval = '$purge_intl',
				auth_login = '$auth_login',
				auth_pass = '$auth_pass',
				private = '$private'				
				WHERE id = '$feed_id' AND owner_uid = " . $_SESSION["uid"]);			
		}

		if ($subop == "saveCat") {
			$cat_title = db_escape_string($_GET["title"]);
			$cat_id = db_escape_string($_GET["id"]);

			$result = db_query($link, "UPDATE ttrss_feed_categories SET
				title = '$cat_title' WHERE id = '$cat_id' AND owner_uid = ".$_SESSION["uid"]);

		}

		if ($subop == "remove") {

			if (!WEB_DEMO_MODE) {

				$ids = split(",", db_escape_string($_GET["ids"]));

				foreach ($ids as $id) {
					db_query($link, "DELETE FROM ttrss_feeds 
						WHERE id = '$id' AND owner_uid = " . $_SESSION["uid"]);

					$icons_dir = ICONS_DIR;
					
					if (file_exists($icons_dir . "/$id.ico")) {
						unlink($icons_dir . "/$id.ico");
					}
				}
			}
		}

		if ($subop == "add") {
		
			if (!WEB_DEMO_MODE) {

				$feed_link = db_escape_string(trim($_GET["link"]));
				$cat_id = db_escape_string($_GET["cid"]);

				if ($cat_id == "0" || !$cat_id) {
					$cat_qpart = "NULL";
				} else {
					$cat_qpart = "'$cat_id'";
				}

				$result = db_query($link,
					"SELECT id FROM ttrss_feeds 
					WHERE feed_url = '$feed_link' AND owner_uid = ".$_SESSION["uid"]);

				if (db_num_rows($result) == 0) {
					
					$result = db_query($link,
						"INSERT INTO ttrss_feeds (owner_uid,feed_url,title,cat_id) 
						VALUES ('".$_SESSION["uid"]."', '$feed_link', 
						'[Unknown]', $cat_qpart)");

					$result = db_query($link,
						"SELECT id FROM ttrss_feeds WHERE feed_url = '$feed_link' 
						AND owner_uid = " . $_SESSION["uid"]);

					$feed_id = db_fetch_result($result, 0, "id");

					if ($feed_id) {
						update_rss_feed($link, $feed_link, $feed_id, true);
					}
				} else {

					print "<div class=\"warning\">
						Feed <b>$feed_link</b> already exists in the database.
					</div>";
				}
			}
		}

		if ($subop == "addCat") {

			if (!WEB_DEMO_MODE) {

				$feed_cat = db_escape_string(trim($_GET["cat"]));

				$result = db_query($link,
					"SELECT id FROM ttrss_feed_categories
					WHERE title = '$feed_cat' AND owner_uid = ".$_SESSION["uid"]);

				if (db_num_rows($result) == 0) {
					
					$result = db_query($link,
						"INSERT INTO ttrss_feed_categories (owner_uid,title) 
						VALUES ('".$_SESSION["uid"]."', '$feed_cat')");

				} else {

					print "<div class=\"warning\">
						Category <b>$feed_cat</b> already exists in the database.
					</div>";
				}


			}
		}

		if ($subop == "removeCats") {

			if (!WEB_DEMO_MODE) {

				$ids = split(",", db_escape_string($_GET["ids"]));

				foreach ($ids as $id) {

					db_query($link, "BEGIN");

					$result = db_query($link, 
						"SELECT count(id) as num_feeds FROM ttrss_feeds 
							WHERE cat_id = '$id'");

					$num_feeds = db_fetch_result($result, 0, "num_feeds");

					if ($num_feeds == 0) {
						db_query($link, "DELETE FROM ttrss_feed_categories
							WHERE id = '$id' AND owner_uid = " . $_SESSION["uid"]);
					} else {

						print "<div class=\"warning\">
							Unable to delete non empty feed categories.</div>";
							
					}

					db_query($link, "COMMIT");
				}
			}
		}

		if ($subop == "categorize") {

			if (!WEB_DEMO_MODE) {

				$ids = split(",", db_escape_string($_GET["ids"]));

				$cat_id = db_escape_string($_GET["cat_id"]);

				if ($cat_id == 0) {
					$cat_id_qpart = 'NULL';
				} else {
					$cat_id_qpart = "'$cat_id'";
				}

				db_query($link, "BEGIN");

				foreach ($ids as $id) {
				
					db_query($link, "UPDATE ttrss_feeds SET cat_id = $cat_id_qpart
						WHERE id = '$id' AND owner_uid = " . $_SESSION["uid"]);
				}

				db_query($link, "COMMIT");
			}

		}

		if ($quiet) return;

//		print "<h3>Edit Feeds</h3>";

		$result = db_query($link, "SELECT id,title,feed_url,last_error 
			FROM ttrss_feeds WHERE last_error != '' AND owner_uid = ".$_SESSION["uid"]);

		if (db_num_rows($result) > 0) {
		
			print "<div class=\"warning\">";
			
//			print"<img class=\"closeButton\" 
//				onclick=\"javascript:hideParentElement(this);\" src=\"images/close.png\">";
	
			print "<a href=\"javascript:showBlockElement('feedUpdateErrors')\">
				<b>Feeds with update errors</b> (click to expand)</a>";

			print "<ul id=\"feedUpdateErrors\" class=\"nomarks\">";
						
			while ($line = db_fetch_assoc($result)) {
				print "<li>" . $line["title"] . " (" . $line["feed_url"] . "): " . 
					$line["last_error"];
			}

			print "</ul>";
			print "</div>";

		}

		$feed_search = db_escape_string($_GET["search"]);

		if (array_key_exists("search", $_GET)) {
			$_SESSION["prefs_feed_search"] = $feed_search;
		} else {
			$feed_search = $_SESSION["prefs_feed_search"];
		}

		print "<table width='100%' class=\"prefGenericAddBox\" 
			cellspacing='0' cellpadding='0'><tr>
			<td>
				<input id=\"fadd_link\" 
				onchange=\"javascript:addFeed()\"
				size=\"40\">
				<input type=\"submit\" class=\"button\"
				onclick=\"javascript:addFeed()\" value=\"Add feed\">";

		if (ENABLE_FEED_BROWSER) {
			print "&nbsp;(<a href='javascript:browseFeeds()'>Top 50</a>)";
		}
		
		print "</td><td align='right'>
				<input id=\"feed_search\" size=\"20\"  
				onchange=\"javascript:updateFeedList()\"
				value=\"$feed_search\">
				<input type=\"submit\" class=\"button\" 
				onclick=\"javascript:updateFeedList()\" value=\"Search\">
			</td>			
			</tr></table>";

		$feeds_sort = db_escape_string($_GET["sort"]);

		if (!$feeds_sort || $feeds_sort == "undefined") {
			$feeds_sort = $_SESSION["pref_sort_feeds"];			
			if (!$feeds_sort) $feeds_sort = "title";
		}

		$_SESSION["pref_sort_feeds"] = $feeds_sort;

		if ($feed_search) {
			$search_qpart = "(UPPER(F1.title) LIKE UPPER('%$feed_search%') OR
				UPPER(F1.feed_url) LIKE UPPER('%$feed_search%')) AND";
		} else {
			$search_qpart = "";
		}

		$result = db_query($link, "SELECT 
				F1.id,
				F1.title,
				F1.feed_url,
				substring(F1.last_updated,1,16) AS last_updated,
				F1.parent_feed,
				F1.update_interval,
				F1.purge_interval,
				F1.cat_id,
				F2.title AS parent_title,
				C1.title AS category				
			FROM 
				ttrss_feeds AS F1 
				LEFT JOIN ttrss_feeds AS F2
					ON (F1.parent_feed = F2.id)
				LEFT JOIN ttrss_feed_categories AS C1
					ON (F1.cat_id = C1.id)
			WHERE 
				$search_qpart F1.owner_uid = '".$_SESSION["uid"]."' 			
			ORDER by category,$feeds_sort,title");

		if (db_num_rows($result) != 0) {

			print "<div id=\"infoBoxShadow\"><div id=\"infoBox\">PLACEHOLDER</div></div>";

			print "<p><table width=\"100%\" cellspacing=\"0\" 
				class=\"prefFeedList\" id=\"prefFeedList\">";
			print "<tr><td class=\"selectPrompt\" colspan=\"8\">
				Select: 
					<a href=\"javascript:selectTableRowsByIdPrefix('prefFeedList', 
						'FEEDR-', 'FRCHK-', true)\">All</a>,
					<a href=\"javascript:selectTableRowsByIdPrefix('prefFeedList', 
						'FEEDR-', 'FRCHK-', false)\">None</a>
				</td</tr>";

			if (!get_pref($link, 'ENABLE_FEED_CATS')) {
				print "<tr class=\"title\">
					<td width='5%' align='center'>&nbsp;</td>
					<td width='30%'><a href=\"javascript:updateFeedList('title')\">Title</a></td>
					<td width='30%'><a href=\"javascript:updateFeedList('feed_url')\">Feed</a></td>
					<td width='15%'><a href=\"javascript:updateFeedList('update_interval')\">Update Interval</a></td>
					<td width='15%'><a href=\"javascript:updateFeedList('purge_interval')\">Purge Days</a></td></tr>";
			}
			
			$lnum = 0;

			$cur_cat_id = -1;
			
			while ($line = db_fetch_assoc($result)) {
	
				$feed_id = $line["id"];
				$cat_id = $line["cat_id"];

				$edit_title = htmlspecialchars(db_unescape_string($line["title"]));
				$edit_link = htmlspecialchars(db_unescape_string($line["feed_url"]));
				$edit_cat = htmlspecialchars(db_unescape_string($line["category"]));
	
				if ($line["update_interval"] == "0") $line["update_interval"] = "Default";
				if ($line["update_interval"] == "-1") $line["update_interval"] = "Disabled";
				if ($line["purge_interval"] == "0") $line["purge_interval"] = "Default";
				if ($line["purge_interval"] < 0)	$line["purge_interval"] = "Disabled";

				if (!$edit_cat) $edit_cat = "Uncategorized";


				if (get_pref($link, 'ENABLE_FEED_CATS') && $cur_cat_id != $cat_id) {
					$lnum = 0;
				
					print "<tr><td colspan=\"6\" class=\"feedEditCat\">$edit_cat</td></tr>";

					print "<tr class=\"title\">
						<td width='5%' align='center'>&nbsp;</td>
						<td width='30%'><a href=\"javascript:updateFeedList('title')\">Title</a></td>
						<td width='30%'><a href=\"javascript:updateFeedList('feed_url')\">Feed</a></td>
						<td width='15%'><a href=\"javascript:updateFeedList('update_interval')\">Update Interval</a></td>
						<td width='15%'><a href=\"javascript:updateFeedList('purge_interval')\">Purge Days</a></td></tr>";

					$cur_cat_id = $cat_id;
				}

				$class = ($lnum % 2) ? "even" : "odd";
				$this_row_id = "id=\"FEEDR-$feed_id\"";

				print "<tr class=\"$class\" $this_row_id>";
	
				$icon_file = ICONS_DIR . "/$feed_id.ico";
	
				if (file_exists($icon_file) && filesize($icon_file) > 0) {
						$feed_icon = "<img class=\"tinyFeedIcon\"	src=\"" . ICONS_URL . "/$feed_id.ico\">";
				} else {
					$feed_icon = "<img class=\"tinyFeedIcon\" src=\"images/blank_icon.gif\">";
				}
//				print "<td class='feedIcon'>$feed_icon</td>";		
	
				print "<td class='feedSelect'><input onclick='toggleSelectRow(this);' 
				type=\"checkbox\" id=\"FRCHK-".$line["id"]."\"></td>";

				$edit_title = truncate_string($edit_title, 40);
				$edit_link = truncate_string($edit_link, 60);

				$parent_title = $line["parent_title"];
				if ($parent_title) {
					$parent_title = "<span class='groupPrompt'>(linked to 
						$parent_title)</span>";
				}

				print "<td><a href=\"javascript:editFeed($feed_id);\">" . 
					"$feed_icon $edit_title $parent_title" . "</a></td>";		
					
				print "<td><a href=\"javascript:editFeed($feed_id);\">" . 
					$edit_link . "</a></td>";		

/*				if (get_pref($link, 'ENABLE_FEED_CATS')) {
					print "<td><a href=\"javascript:editFeed($feed_id);\">" . 
						$edit_cat . "</a></td>";		
				} */

				print "<td><a href=\"javascript:editFeed($feed_id);\">" . 
					$line["update_interval"] . "</a></td>";

				print "<td><a href=\"javascript:editFeed($feed_id);\">" . 
					$line["purge_interval"] . "</a></td>";
	
				print "</tr>";
	
				++$lnum;
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
					onclick=\"javascript:selectedFeedDetails()\" value=\"Details\">
				<input type=\"submit\" class=\"button\" 
					onclick=\"javascript:editSelectedFeed()\" value=\"Edit\">
				<input type=\"submit\" class=\"button\" 
					onclick=\"javascript:removeSelectedFeeds()\" value=\"Remove\">";

				if (get_pref($link, 'ENABLE_FEED_CATS')) {

					print "&nbsp;&nbsp;";				

					$result = db_query($link, "SELECT title,id FROM ttrss_feed_categories
						WHERE owner_uid = ".$_SESSION["uid"]."
						ORDER BY title");

					print "<select id=\"sfeed_set_fcat\">";
					print "<option id=\"0\">Uncategorized</option>";

					if (db_num_rows($result) != 0) {
		
						print "<option disabled>--------</option>";

						while ($line = db_fetch_assoc($result)) {
							printf("<option id='%d'>%s</option>", 
								$line["id"], $line["title"]);
						}		
					}

					print "</select>";

					print " <input type=\"submit\" class=\"button\" 
					onclick=\"javascript:categorizeSelectedFeeds()\" value=\"Set category\">";

				}

				if (get_pref($link, 'ENABLE_PREFS_CATCHUP_UNCATCHUP')) {
					print "
					<input type=\"submit\" class=\"button\" 
						onclick=\"javascript:readSelectedFeeds(true)\" value=\"Mark as read\">
					<input type=\"submit\" class=\"button\" 
						onclick=\"javascript:readSelectedFeeds(false)\" 
						value=\"Mark as unread\">&nbsp;";
				}
				
				print "
					&nbsp;All feeds: <input type=\"submit\" 
							class=\"button\" onclick=\"gotoExportOpml()\" 
							value=\"Export OPML\">";			
				}
		} else {

			print "<p>No feeds defined.</p>";

		}

		if (get_pref($link, 'ENABLE_FEED_CATS')) {

			print "<h3>Edit Categories</h3>";

	//		print "<h3>Categories</h3>";

			print "<div class=\"prefGenericAddBox\">
				<input id=\"fadd_cat\" 
					onchange=\"javascript:addFeedCat()\"
					size=\"40\">&nbsp;
				<input 
					type=\"submit\" class=\"button\" 
					onclick=\"javascript:addFeedCat()\" value=\"Add category\"></div>";
	
			$result = db_query($link, "SELECT title,id FROM ttrss_feed_categories
				WHERE owner_uid = ".$_SESSION["uid"]."
				ORDER BY title");

			if (db_num_rows($result) != 0) {
	
				print "<p><table width=\"100%\" class=\"prefFeedCatList\" 
					cellspacing=\"0\" id=\"prefFeedCatList\">";

				print "<tr><td class=\"selectPrompt\" colspan=\"8\">
				Select: 
					<a href=\"javascript:selectTableRowsByIdPrefix('prefFeedCatList', 
						'FCATR-', 'FCCHK-', true)\">All</a>,
					<a href=\"javascript:selectTableRowsByIdPrefix('prefFeedCatList', 
						'FCATR-', 'FCCHK-', false)\">None</a>
				</td</tr>";

				print "<tr class=\"title\">
							<td width=\"5%\"></td><td width=\"80%\">Title</td>
						</tr>";
						
				$lnum = 0;
				
				while ($line = db_fetch_assoc($result)) {
		
					$class = ($lnum % 2) ? "even" : "odd";
		
					$cat_id = $line["id"];
		
					$edit_cat_id = $_GET["id"];
		
					if ($subop == "editCat" && $cat_id != $edit_cat_id) {
						$class .= "Grayed";
						$this_row_id = "";
					} else {
						$this_row_id = "id=\"FCATR-$cat_id\"";
					}
		
					print "<tr class=\"$class\" $this_row_id>";
		
					$edit_title = htmlspecialchars(db_unescape_string($line["title"]));
		
					if (!$edit_cat_id || $subop != "editCat") {
		
						print "<td align='center'><input onclick='toggleSelectRow(this);' 
						type=\"checkbox\" id=\"FCCHK-".$line["id"]."\"></td>";
		
						print "<td><a href=\"javascript:editFeedCat($cat_id);\">" . 
							$edit_title . "</a></td>";		
		
					} else if ($cat_id != $edit_cat_id) {
		
						print "<td><input disabled=\"true\" type=\"checkbox\" 
							id=\"FRCHK-".$line["id"]."\"></td>";
		
						print "<td>$edit_title</td>";		
		
					} else {
		
						print "<td><input disabled=\"true\" type=\"checkbox\" checked></td>";
		
						print "<td><input id=\"iedit_title\" value=\"$edit_title\"></td>";
						
					}
					
					print "</tr>";
		
					++$lnum;
				}
	
				print "</table>";
	
				print "<p>";
	
				if ($subop == "editCat") {
					print "Edit category:&nbsp;
						<input type=\"submit\" class=\"button\" 
							onclick=\"javascript:feedCatEditCancel()\" value=\"Cancel\">
						<input type=\"submit\" class=\"button\" 
							onclick=\"javascript:feedCatEditSave()\" value=\"Save\">";
					} else {
		
					print "
						Selection:&nbsp;
					<input type=\"submit\" class=\"button\" 
						onclick=\"javascript:editSelectedFeedCat()\" value=\"Edit\">
					<input type=\"submit\" class=\"button\" 
						onclick=\"javascript:removeSelectedFeedCats()\" value=\"Remove\">";
	
				}
	
			} else {
				print "<p>No feed categories defined.</p>";
 			}
		}

		print "<h3>Import OPML</h3>
		<form	enctype=\"multipart/form-data\" method=\"POST\" action=\"opml.php\">
			File: <input id=\"opml_file\" name=\"opml_file\" type=\"file\">&nbsp;
			<input class=\"button\" name=\"op\" onclick=\"return validateOpmlImport();\"
				type=\"submit\" value=\"Import\">
			</form>";

	}

	if ($op == "pref-filters") {

		$subop = $_GET["subop"];
		$quiet = $_GET["quiet"];

		if ($subop == "editSave") {

			$regexp = db_escape_string($_GET["r"]);
			$descr = db_escape_string($_GET["d"]);
			$match = db_escape_string($_GET["m"]);
			$filter_id = db_escape_string($_GET["id"]);
			$feed_id = db_escape_string($_GET["fid"]);
			$action_id = db_escape_string($_GET["aid"]); 

			if (!$feed_id) {
				$feed_id = 'NULL';
			} else {
				$feed_id = sprintf("'%s'", db_escape_string($feed_id));
			}
			
			$result = db_query($link, "UPDATE ttrss_filters SET 
				reg_exp = '$regexp', 
				description = '$descr',
				feed_id = $feed_id,
				action_id = '$action_id',
				filter_type = (SELECT id FROM ttrss_filter_types WHERE
					description = '$match')
				WHERE id = '$filter_id'");
		}

		if ($subop == "remove") {

			if (!WEB_DEMO_MODE) {

				$ids = split(",", db_escape_string($_GET["ids"]));

				foreach ($ids as $id) {
					db_query($link, "DELETE FROM ttrss_filters WHERE id = '$id'");
					
				}
			}
		}

		if ($subop == "add") {
		
			if (!WEB_DEMO_MODE) {

				$regexp = db_escape_string(trim($_GET["regexp"]));
				$match = db_escape_string(trim($_GET["match"]));
				$feed_id = db_escape_string($_GET["fid"]);
				$action_id = db_escape_string($_GET["aid"]); 

				if (!$feed_id) {
					$feed_id = 'NULL';
				} else {
					$feed_id = sprintf("'%s'", db_escape_string($feed_id));
				}

				$result = db_query($link,
					"INSERT INTO ttrss_filters (reg_exp,filter_type,owner_uid,feed_id,
						action_id) 
					VALUES 
						('$regexp', (SELECT id FROM ttrss_filter_types WHERE
							description = '$match'),'".$_SESSION["uid"]."', 
							$feed_id, '$action_id')");
			} 
		}

		if ($quiet) return;

		$result = db_query($link, "SELECT description 
			FROM ttrss_filter_types ORDER BY description");

		$filter_types = array();

		while ($line = db_fetch_assoc($result)) {
			array_push($filter_types, $line["description"]);
		}

		print "<div class=\"prefGenericAddBox\">
		<input id=\"fadd_regexp\" size=\"40\">&nbsp;";
		
		print_select("fadd_match", "Title", $filter_types);	

		print "&nbsp;<select id=\"fadd_feed\">";

		print "<option selected id=\"0\">All feeds</option>";

		$result = db_query($link, "SELECT id,title FROM ttrss_feeds
			WHERE owner_uid = ".$_SESSION["uid"]." ORDER BY title");

		if (db_num_rows($result) > 0) {
			print "<option disabled>--------</option>";
		}

		while ($line = db_fetch_assoc($result)) {
			printf("<option id='%d'>%s</option>", $line["id"], $line["title"]);
		}

		print "</select>&nbsp;";

		print "&nbsp;Action: ";

		print "<select id=\"fadd_action\">";

		$result = db_query($link, "SELECT id,description FROM ttrss_filter_actions 
			ORDER BY name");

		while ($line = db_fetch_assoc($result)) {			
			printf("<option id='%d'>%s</option>", $line["id"], $line["description"]);
		}

		print "</select>&nbsp;";

		print "<input type=\"submit\" 
			class=\"button\" onclick=\"javascript:addFilter()\" 
			value=\"Add filter\">";

		print "</div>";

		$result = db_query($link, "SELECT 
				ttrss_filters.id AS id,reg_exp,
				ttrss_filters.description AS description,
				ttrss_filter_types.name AS filter_type_name,
				ttrss_filter_types.description AS filter_type_descr,
				feed_id,
				ttrss_filter_actions.description AS action_description,
				ttrss_feeds.title AS feed_title
			FROM 
				ttrss_filter_types,ttrss_filter_actions,ttrss_filters LEFT JOIN
					ttrss_feeds ON (ttrss_filters.feed_id = ttrss_feeds.id)
			WHERE
				filter_type = ttrss_filter_types.id AND
				ttrss_filter_actions.id = action_id AND
				ttrss_filters.owner_uid = ".$_SESSION["uid"]."
			ORDER by reg_exp");

		if (db_num_rows($result) != 0) {

			print "<p><table width=\"100%\" cellspacing=\"0\" class=\"prefFilterList\" 
				id=\"prefFilterList\">";

			print "<tr><td class=\"selectPrompt\" colspan=\"8\">
				Select: 
					<a href=\"javascript:selectTableRowsByIdPrefix('prefFilterList', 
						'FILRR-', 'FICHK-', true)\">All</a>,
					<a href=\"javascript:selectTableRowsByIdPrefix('prefFilterList', 
						'FILRR-', 'FICHK-', false)\">None</a>
				</td</tr>";

			print "<tr class=\"title\">
						<td align='center' width=\"5%\">&nbsp;</td>
						<td width=\"20%\">Filter expression</td>
						<td width=\"20%\">Feed</td>
						<td width=\"15%\">Match</td>
						<td width=\"15%\">Action</td>
						<td width=\"30%\">Description</td></tr>";
		
			$lnum = 0;
			
			while ($line = db_fetch_assoc($result)) {
	
				$class = ($lnum % 2) ? "even" : "odd";
	
				$filter_id = $line["id"];
				$edit_filter_id = $_GET["id"];
	
				if ($subop == "edit" && $filter_id != $edit_filter_id) {
					$class .= "Grayed";
					$this_row_id = "";
				} else {
					$this_row_id = "id=\"FILRR-$filter_id\"";
				}
	
				print "<tr class=\"$class\" $this_row_id>";
	
				$line["regexp"] = htmlspecialchars($line["reg_exp"]);
				$line["description"] = htmlspecialchars($line["description"]);
	
				if (!$line["feed_title"]) $line["feed_title"] = "All feeds";
	
				if (!$edit_filter_id || $subop != "edit") {
	
					if (!$line["description"]) $line["description"] = "[No description]";
	
					print "<td align='center'><input onclick='toggleSelectRow(this);' 
					type=\"checkbox\" id=\"FICHK-".$line["id"]."\"></td>";
	
					print "<td><a href=\"javascript:editFilter($filter_id);\">" . 
						$line["reg_exp"] . "</td>";		
	
					print "<td><a href=\"javascript:editFilter($filter_id);\">" . 
						$line["feed_title"] . "</td>";			
	
					print "<td><a href=\"javascript:editFilter($filter_id);\">" . 
						$line["filter_type_descr"] . "</td>";		
		
					print "<td><a href=\"javascript:editFilter($filter_id);\">" . 
						$line["action_description"] . "</td>";			

					print "<td><a href=\"javascript:editFilter($filter_id);\">" . 
						$line["description"] . "</td>";			
	
				} else if ($filter_id != $edit_filter_id) {
	
					if (!$line["description"]) $line["description"] = "[No description]";
	
					print "<td><input disabled=\"true\" type=\"checkbox\" 
						id=\"FICHK-".$line["id"]."\"></td>";
	
					print "<td>".$line["reg_exp"]."</td>";		
					print "<td>".$line["feed_title"]."</td>";
					print "<td>".$line["filter_type_descr"]."</td>";
					print "<td>".$line["action_description"]."</td>";		
					print "<td>".$line["description"]."</td>";		

				} else {
	
					print "<td><input disabled=\"true\" type=\"checkbox\" checked></td>";
	
					print "<td><input id=\"iedit_regexp\" value=\"".$line["reg_exp"].
						"\"></td>";
	
					print "<td>";
					print "<select id=\"iedit_feed\">";
					print "<option id=\"0\">All feeds</option>";
	
					$tmp_result = db_query($link, "SELECT id,title FROM ttrss_feeds
						WHERE owner_uid = ".$_SESSION["uid"]." ORDER BY title");

					if (db_num_rows($tmp_result) > 0) {
						print "<option disabled>--------</option>";
					}

					while ($tmp_line = db_fetch_assoc($tmp_result)) {
						if ($tmp_line["id"] == $line["feed_id"]) {
							$is_selected = "selected";
						} else {
							$is_selected = "";
						}
						printf("<option $is_selected id='%d'>%s</option>", 
							$tmp_line["id"], $tmp_line["title"]);
					}
	
					print "</select></td>";
	
					print "<td>";
					print_select("iedit_match", $line["filter_type_descr"], $filter_types);
					print "</td>";

					print "<td>";
					print "<select id=\"iedit_filter_action\">";
	
					$tmp_result = db_query($link, "SELECT id,description FROM ttrss_filter_actions
						ORDER BY description");

					while ($tmp_line = db_fetch_assoc($tmp_result)) {
						if ($tmp_line["description"] == $line["action_description"]) {
							$is_selected = "selected";
						} else {
							$is_selected = "";
						}
						printf("<option $is_selected id='%d'>%s</option>", 
							$tmp_line["id"], $tmp_line["description"]);
					}
	
					print "</select></td>";


					print "<td><input id=\"iedit_descr\" value=\"".$line["description"].
						"\"></td>";
	
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
				print "Edit filter:
					<input type=\"submit\" class=\"button\" 
						onclick=\"javascript:filterEditSave()\" value=\"Save\">
					<input type=\"submit\" class=\"button\" 
						onclick=\"javascript:filterEditCancel()\" value=\"Cancel\">";
						
			} else {
	
				print "
					Selection:
				<input type=\"submit\" class=\"button\" 
					onclick=\"javascript:editSelectedFilter()\" value=\"Edit\">
				<input type=\"submit\" class=\"button\" 
					onclick=\"javascript:removeSelectedFilters()\" value=\"Remove\">";
			}

		} else {

			print "<p>No filters defined.</p>";

		}
	}

	// We need to accept raw SQL data in label queries, so not everything is escaped
	// here, this is by design. If you don't like the whole idea, disable labels
	// altogether with GLOBAL_ENABLE_LABELS = false

	if ($op == "pref-labels") {

		if (!GLOBAL_ENABLE_LABELS) { 
			return; 
		}

		$subop = $_GET["subop"];

		if ($subop == "test") {

			$expr = $_GET["expr"];
			$descr = $_GET["descr"];

			print "<div class='infoBoxContents'>";
		
			print "<h1>Label &laquo;$descr&raquo;</h1>";

//			print "<p><b>Expression</b>: $expr</p>";

			$result = db_query($link, 
				"SELECT count(id) AS num_matches
					FROM ttrss_entries,ttrss_user_entries
					WHERE ($expr) AND 
						ttrss_user_entries.ref_id = ttrss_entries.id AND
						owner_uid = " . $_SESSION["uid"]);

			$num_matches = db_fetch_result($result, 0, "num_matches");;
			
			if ($num_matches > 0) { 

				print "<p>Query returned <b>$num_matches</b> matches, first 5 follow:</p>";

				$result = db_query($link, 
					"SELECT title, 
						(SELECT title FROM ttrss_feeds WHERE id = feed_id) AS feed_title
					FROM ttrss_entries,ttrss_user_entries
							WHERE ($expr) AND 
							ttrss_user_entries.ref_id = ttrss_entries.id
							AND owner_uid = " . $_SESSION["uid"] . " 
							ORDER BY date_entered DESC LIMIT 5");

				print "<ul class=\"nomarks\">";
				while ($line = db_fetch_assoc($result)) {
					print "<li>".$line["title"].
						" <span class=\"insensitive\">(".$line["feed_title"].")</span></li>";
				}
				print "</ul>";

			} else {
				print "<p>Query didn't return any matches.</p>";
			}

			print "</div>";

			print "<div align='center'>
				<input type='submit' class='button'			
				onclick=\"closeInfoBox()\" value=\"Close this window\"></div>";
			return;
		}

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

				$ids = split(",", db_escape_string($_GET["ids"]));

				foreach ($ids as $id) {
					db_query($link, "DELETE FROM ttrss_labels WHERE id = '$id'");
					
				}
			}
		}

		if ($subop == "add") {
		
			if (!WEB_DEMO_MODE) {

				// no escaping is done here on purpose
				$exp = trim($_GET["exp"]);
					
				$result = db_query($link,
					"INSERT INTO ttrss_labels (sql_exp,description,owner_uid) 
						VALUES ('$exp', '$exp', '".$_SESSION["uid"]."')");
			} 
		}

		print "<div class=\"prefGenericAddBox\">
			<input size=\"40\" id=\"ladd_expr\">&nbsp;";
			
		print"<input type=\"submit\" class=\"button\" 
			onclick=\"javascript:addLabel()\" value=\"Add label\"></div>";

		$result = db_query($link, "SELECT 
				id,sql_exp,description
			FROM 
				ttrss_labels 
			WHERE 
				owner_uid = ".$_SESSION["uid"]."
			ORDER by description");

		print "<div id=\"infoBoxShadow\"><div id=\"infoBox\">PLACEHOLDER</div></div>";

		if (db_num_rows($result) != 0) {

			print "<p><table width=\"100%\" cellspacing=\"0\" 
				class=\"prefLabelList\" id=\"prefLabelList\">";

			print "<tr><td class=\"selectPrompt\" colspan=\"8\">
				Select: 
					<a href=\"javascript:selectTableRowsByIdPrefix('prefLabelList', 
						'LILRR-', 'LICHK-', true)\">All</a>,
					<a href=\"javascript:selectTableRowsByIdPrefix('prefLabelList', 
						'LILRR-', 'LICHK-', false)\">None</a>
				</td</tr>";

			print "<tr class=\"title\">
						<td align='center' width=\"5%\">&nbsp;</td>
						<td width=\"40%\">SQL expression
						<a class=\"helpLink\" href=\"javascript:displayHelpInfobox(1)\">(?)</a>
						</td>
						<td width=\"40%\">Caption</td></tr>";
			
			$lnum = 0;
			
			while ($line = db_fetch_assoc($result)) {
	
				$class = ($lnum % 2) ? "even" : "odd";
	
				$label_id = $line["id"];
				$edit_label_id = $_GET["id"];
	
				if ($subop == "edit" && $label_id != $edit_label_id) {
					$class .= "Grayed";
					$this_row_id = "";
				} else {
					$this_row_id = "id=\"LILRR-$label_id\"";
				}
	
				print "<tr class=\"$class\" $this_row_id>";
	
				$line["sql_exp"] = htmlspecialchars($line["sql_exp"]);
				$line["description"] = htmlspecialchars($line["description"]);
	
				if (!$edit_label_id || $subop != "edit") {
	
					if (!$line["description"]) $line["description"] = "[No caption]";
	
					print "<td align='center'><input onclick='toggleSelectRow(this);' 
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
						onclick=\"javascript:labelTest()\" value=\"Test\">
					<input type=\"submit\" class=\"button\" 
						onclick=\"javascript:labelEditSave()\" value=\"Save\">
					<input type=\"submit\" class=\"button\" 
						onclick=\"javascript:labelEditCancel()\" value=\"Cancel\">";
						
			} else {
	
				print "
					Selection:
				<input type=\"submit\" class=\"button\" 
					onclick=\"javascript:editSelectedLabel()\" value=\"Edit\">
				<input type=\"submit\" class=\"button\" 
					onclick=\"javascript:removeSelectedLabels()\" value=\"Remove\">";
			}
		} else {
			print "<p>No labels defined.</p>";
		}
	}

	if ($op == "error") {
		print "<div width=\"100%\" align='center'>";
		$msg = $_GET["msg"];
		print $msg;
		print "</div>";
	}

	if ($op == "help") {
		if (!$_GET["noheaders"]) {
			print "<html><head>
				<title>Tiny Tiny RSS : Help</title>
				<link rel=\"stylesheet\" href=\"tt-rss.css\" type=\"text/css\">
				<script type=\"text/javascript\" src=\"functions.js\"></script>
				<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\">
				</head><body>";
		}

		$tid = sprintf("%d", $_GET["tid"]);

		print "<div class='infoBoxContents'>";

		if (file_exists("help/$tid.php")) {
			include("help/$tid.php");
		} else {
			print "<p>Help topic not found.</p>";
		}

		print "</div>";

		print "<div align='center'>
			<input type='submit' class='button'			
			onclick=\"closeInfoBox()\" value=\"Close this window\"></div>";

		if (!$_GET["noheaders"]) { 
			print "</body></html>";
		}

	}

	if ($op == "dlg") {
		$id = $_GET["id"];
		$param = $_GET["param"];

		if ($id == "quickAddFeed") {
			print "
			Feed URL: <input 
			onblur=\"javascript:enableHotkeys()\" onfocus=\"javascript:disableHotkeys()\"
			id=\"qafInput\">";
		
			if (get_pref($link, 'ENABLE_FEED_CATS')) {
				$result = db_query($link, "SELECT title,id FROM ttrss_feed_categories
					WHERE owner_uid = ".$_SESSION["uid"]."
					ORDER BY title");

				print " <select id=\"qafCat\">";
				print "<option id=\"0\">Uncategorized</option>";

				if (db_num_rows($result) != 0) {
	
					print "<option disabled>--------</option>";

					while ($line = db_fetch_assoc($result)) {
						printf("<option id='%d'>%s</option>", 
							$line["id"], $line["title"]);
					}		
				}

				print "</select>";
			}
			
			print "&nbsp;<input class=\"button\"
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
		
				print "Remove current feed (<b>$f_title</b>)?&nbsp;
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

			$active_feed_id = db_escape_string($_GET["param"]);

			print "<input id=\"searchbox\" class=\"extSearch\"			
			onblur=\"javascript:enableHotkeys()\" onfocus=\"javascript:disableHotkeys()\"
			onchange=\"javascript:search()\">
			<select id=\"searchmodebox\">
				<option selected>All feeds</option>";
				
			if ($active_feed_id) {				
				print "<option>This feed</option>";
			} else {
				print "<option disabled>This feed</option>";
			}
			
			print "</select>		
			<input type=\"submit\" 
				class=\"button\" onclick=\"javascript:search()\" value=\"Search\">
			<input class=\"button\"
				type=\"submit\" onclick=\"javascript:closeDlg()\" 
				value=\"Close\">";

		}

		if ($id == "quickAddFilter") {

			$result = db_query($link, "SELECT description 
				FROM ttrss_filter_types ORDER BY description");
	
			$filter_types = array();
	
			while ($line = db_fetch_assoc($result)) {
				array_push($filter_types, $line["description"]);
			}

			print "<table>";

			print "<tr><td>Match:</td><td><input id=\"fadd_regexp\" size=\"40\">&nbsp;";
			
			print_select("fadd_match", "Title", $filter_types);	
	
			print "</td></tr>";
			print "<tr><td>Feed:</td><td><select id=\"fadd_feed\">";
	
			print "<option selected id=\"0\">All feeds</option>";
	
			$result = db_query($link, "SELECT id,title FROM ttrss_feeds
				WHERE owner_uid = ".$_SESSION["uid"]." ORDER BY title");
	
			if (db_num_rows($result) > 0) {
				print "<option disabled>--------</option>";
			}
	
			while ($line = db_fetch_assoc($result)) {
				if ($param == $line["id"]) {
					$selected = "selected";
				} else {
					$selected = "";
				}
				printf("<option id='%d' %s>%s</option>", $line["id"], $selected, $line["title"]);
			}
	
			print "</select></td></tr>";
	
			print "<tr><td>Action:</td>";
	
			print "<td><select id=\"fadd_action\">";
	
			$result = db_query($link, "SELECT id,description FROM ttrss_filter_actions 
				ORDER BY name");

			while ($line = db_fetch_assoc($result)) {
				printf("<option id='%d'>%s</option>", $line["id"], $line["description"]);
			}
	
			print "</select>";
	
			print "</td></tr><tr><td colspan=\"2\" align=\"right\">";
	
			print "<input type=\"submit\" 
				class=\"button\" onclick=\"javascript:qaddFilter()\" 
				value=\"Add filter\"> ";

			print "<input class=\"button\"
				type=\"submit\" onclick=\"javascript:closeDlg()\" 
				value=\"Close\">";

			print "</td></tr></table>";
		}
	}

	// update feeds of all users, may be used anonymously
	if ($op == "globalUpdateFeeds") {

		$result = db_query($link, "SELECT id FROM ttrss_users");

		while ($line = db_fetch_assoc($result)) {
			$user_id = $line["id"];
//			print "<!-- updating feeds of uid $user_id -->";
			update_all_feeds($link, false, $user_id);
		}

		print "<rpc-reply>
			<message msg=\"All feeds updated\"/>
		</rpc-reply>";

	}

	if ($op == "pref-prefs") {

		$subop = $_REQUEST["subop"];

		if ($subop == "Save configuration") {

			if (WEB_DEMO_MODE) {
				header("Location: prefs.php");
				return;
			}

			$_SESSION["prefs_op_result"] = "save-config";

			$_SESSION["prefs_cache"] = false;

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

		} else if ($subop == "Change e-mail") {

			if (WEB_DEMO_MODE) {
				header("Location: prefs.php");
				return;
			}

			$email = db_escape_string($_GET["email"]);
			$active_uid = $_SESSION["uid"];

			if ($email) {
				db_query($link, "UPDATE ttrss_users SET email = '$email' 
						WHERE id = '$active_uid'");				
			}

			header("Location: prefs.php");

		} else if ($subop == "Change password") {

			if (WEB_DEMO_MODE) {
				header("Location: prefs.php");
				return;
			}

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

					$_SESSION["pwd_change_result"] = "ok";
				} else {
					$_SESSION["pwd_change_result"] = "failed";					
				}
			}

			header("Location: prefs.php");

		} else if ($subop == "Reset to defaults") {

			if (WEB_DEMO_MODE) {
				header("Location: prefs.php");
				return;
			}

			$_SESSION["prefs_op_result"] = "reset-to-defaults";

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

		} else if ($subop == "Change theme") {

			$theme = db_escape_string($_POST["theme"]);

			if ($theme == "Default") {
				$theme_qpart = 'NULL';
			} else {
				$theme_qpart = "'$theme'";
			}

			$result = db_query($link, "SELECT id,theme_path FROM ttrss_themes
				WHERE theme_name = '$theme'");

			if (db_num_rows($result) == 1) {
				$theme_id = db_fetch_result($result, 0, "id");
				$theme_path = db_fetch_result($result, 0, "theme_path");
			} else {
				$theme_id = "NULL";
				$theme_path = "";
			}

			db_query($link, "UPDATE ttrss_users SET
				theme_id = $theme_id WHERE id = " . $_SESSION["uid"]);

			$_SESSION["theme"] = $theme_path;

			header("Location: prefs.php");

		} else {

			if (!SINGLE_USER_MODE) {

				$result = db_query($link, "SELECT id,email FROM ttrss_users
					WHERE id = ".$_SESSION["uid"]." AND (pwd_hash = 'password' OR
						pwd_hash = 'SHA1:".sha1("password")."')");

				if (db_num_rows($result) != 0) {
					print "<div class=\"warning\"> 
						Your password is at default value, please change it.
					</div>";
				}

				if ($_SESSION["pwd_change_result"] == "failed") {
					print "<div class=\"warning\"> 
							There was an error while changing your password.
						</div>";
				}

				if ($_SESSION["pwd_change_result"] == "ok") {
					print "<div class=\"notice\"> 
							Password changed successfully.
						</div>";
				}

				$_SESSION["pwd_change_result"] = "";

				if ($_SESSION["prefs_op_result"] == "reset-to-defaults") {
					print "<div class=\"notice\"> 
							Your configuration was reset to defaults.
						</div>";
				}

				if ($_SESSION["prefs_op_result"] == "save-config") {
					print "<div class=\"notice\"> 
							Your configuration was saved successfully.
						</div>";
				}

				$_SESSION["prefs_op_result"] = "";

				print "<form action=\"backend.php\" method=\"GET\">";
	
				print "<table width=\"100%\" class=\"prefPrefsList\">";
	 			print "<tr><td colspan='3'><h3>Personal data</h3></tr></td>";

				$result = db_query($link, "SELECT email FROM ttrss_users
					WHERE id = ".$_SESSION["uid"]);
					
				$email = db_fetch_result($result, 0, "email");
	
				print "<tr><td width=\"40%\">E-mail</td>";
				print "<td><input class=\"editbox\" name=\"email\" 
					value=\"$email\"></td></tr>";
	
				print "</table>";
	
				print "<input type=\"hidden\" name=\"op\" value=\"pref-prefs\">";
	
				print "<p><input class=\"button\" type=\"submit\" 
					value=\"Change e-mail\" name=\"subop\">";

				print "</form>";

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
				theme_id FROM ttrss_users WHERE id = " . $_SESSION["uid"]);

			$user_theme_id = db_fetch_result($result, 0, "theme_id");

			$result = db_query($link, "SELECT
				id,theme_name FROM ttrss_themes ORDER BY theme_name");

			if (db_num_rows($result) > 0) {

				print "<form action=\"backend.php\" method=\"POST\">";
				print "<table width=\"100%\" class=\"prefPrefsList\">";
	 			print "<tr><td colspan='3'><h3>Themes</h3></tr></td>";
				print "<tr><td width=\"40%\">Select theme</td>";
				print "<td><select name=\"theme\">";
				print "<option>Default</option>";
				print "<option disabled>--------</option>";				
				
				while ($line = db_fetch_assoc($result)) {	
					if ($line["id"] == $user_theme_id) {
						$selected = "selected";
					} else {
						$selected = "";
					}
					print "<option $selected>" . $line["theme_name"] . "</option>";
				}
				print "</select></td></tr>";
				print "</table>";
				print "<input type=\"hidden\" name=\"op\" value=\"pref-prefs\">";
				print "<p><input class=\"button\" type=\"submit\" 
					value=\"Change theme\" name=\"subop\">";
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
				name=\"subop\" onclick=\"return validatePrefsReset()\" 
				value=\"Reset to defaults\"></p>";

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
				$email = db_escape_string($_GET["e"]);

				db_query($link, "UPDATE ttrss_users SET login = '$login', 
					access_level = '$access_level', email = '$email' WHERE id = '$uid'");

			}
		} else if ($subop == "remove") {

			if (!WEB_DEMO_MODE && $_SESSION["access_level"] >= 10) {

				$ids = split(",", db_escape_string($_GET["ids"]));

				foreach ($ids as $id) {
					db_query($link, "DELETE FROM ttrss_users WHERE id = '$id' AND id != " . $_SESSION["uid"]);
					
				}
			}
		} else if ($subop == "add") {
		
			if (!WEB_DEMO_MODE && $_SESSION["access_level"] >= 10) {

				$login = db_escape_string(trim($_GET["login"]));
				$tmp_user_pwd = make_password(8);
				$pwd_hash = 'SHA1:' . sha1($tmp_user_pwd);

				db_query($link, "INSERT INTO ttrss_users 
					(login,pwd_hash,access_level,last_login)
					VALUES ('$login', '$pwd_hash', 0, NOW())");


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

				$result = db_query($link, "SELECT login,email 
					FROM ttrss_users WHERE id = '$uid'");

				$login = db_fetch_result($result, 0, "login");
				$email = db_fetch_result($result, 0, "email");
				$tmp_user_pwd = make_password(8);
				$pwd_hash = 'SHA1:' . sha1($tmp_user_pwd);

				db_query($link, "UPDATE ttrss_users SET pwd_hash = '$pwd_hash'
					WHERE id = '$uid'");

				print "<div class=\"notice\">Changed password of 
					user <b>$login</b> to <b>$tmp_user_pwd</b>.";

				if (MAIL_RESET_PASS && $email) {
					print " Notifying <b>$email</b>.";

					mail("$login <$email>", "Password reset notification",
						"Hi, $login.\n".
						"\n".
						"Your password for this TT-RSS installation was reset by".
							" an administrator.\n".
						"\n".
						"Your new password is $tmp_user_pwd, please remember".
							" it for later reference.\n".
						"\n".
						"Sincerely, TT-RSS Mail Daemon.", "From: " . MAIL_FROM);
				}
					
				print "</div>";				

			}
		}

		print "<div class=\"prefGenericAddBox\">
			<input id=\"uadd_box\" onchange=\"javascript:addUser()\" size=\"40\">&nbsp;";
			
		print"<input type=\"submit\" class=\"button\" 
			onclick=\"javascript:addUser()\" value=\"Add user\"></div>";

		$result = db_query($link, "SELECT 
				id,login,access_level,email,
				SUBSTRING(last_login,1,16) as last_login
			FROM 
				ttrss_users
			ORDER by login");

		print "<div id=\"infoBoxShadow\"><div id=\"infoBox\">PLACEHOLDER</div></div>";

		print "<p><table width=\"100%\" cellspacing=\"0\" 
			class=\"prefUserList\" id=\"prefUserList\">";

		print "<tr><td class=\"selectPrompt\" colspan=\"8\">
				Select: 
					<a href=\"javascript:selectTableRowsByIdPrefix('prefUserList', 
						'UMRR-', 'UMCHK-', true)\">All</a>,
					<a href=\"javascript:selectTableRowsByIdPrefix('prefUserList', 
						'UMRR-', 'UMCHK-', false)\">None</a>
				</td</tr>";

		print "<tr class=\"title\">
					<td align='center' width=\"5%\">&nbsp;</td>
					<td width='20%'>Username</td>
					<td width='20%'>E-mail</td>
					<td width='20%'>Access Level</td>
					<td width='20%'>Last login</td></tr>";
		
		$lnum = 0;
		
		while ($line = db_fetch_assoc($result)) {

			$class = ($lnum % 2) ? "even" : "odd";

			$uid = $line["id"];
			$edit_uid = $_GET["id"];

			if ($subop == "edit" && $uid != $edit_uid) {
				$class .= "Grayed";
				$this_row_id = "";
			} else {
				$this_row_id = "id=\"UMRR-$uid\"";
			}		
			
			print "<tr class=\"$class\" $this_row_id>";

			$line["login"] = htmlspecialchars($line["login"]);

			$line["last_login"] = date(get_pref($link, 'SHORT_DATE_FORMAT'),
				strtotime($line["last_login"]));

/*			if ($uid == $_SESSION["uid"]) {

				print "<td align='center'><input disabled=\"true\" type=\"checkbox\" 
					id=\"UMCHK-".$line["id"]."\"></td>";

				print "<td>".$line["login"]."</td>";
				print "<td>".$line["email"]."</td>";
				print "<td>".$line["access_level"]."</td>";

			} else */ if (!$edit_uid || $subop != "edit") {

				print "<td align='center'><input onclick='toggleSelectRow(this);' 
				type=\"checkbox\" id=\"UMCHK-$uid\"></td>";

				print "<td><a href=\"javascript:editUser($uid);\">" . 
					$line["login"] . "</td>";		

				print "<td><a href=\"javascript:editUser($uid);\">" . 
					$line["email"] . "</td>";			

				print "<td><a href=\"javascript:editUser($uid);\">" . 
					$line["access_level"] . "</td>";			

			} else if ($uid != $edit_uid) {

				print "<td align='center'><input disabled=\"true\" type=\"checkbox\" 
					id=\"UMCHK-".$line["id"]."\"></td>";

				print "<td>".$line["login"]."</td>";		
				print "<td>".$line["email"]."</td>";		
				print "<td>".$line["access_level"]."</td>";		

			} else {

				print "<td align='center'>
					<input disabled=\"true\" type=\"checkbox\" checked></td>";

				print "<td><input id=\"iedit_ulogin\" value=\"".$line["login"].
					"\"></td>";

				print "<td><input id=\"iedit_email\" value=\"".$line["email"].
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
			print "Edit user:
				<input type=\"submit\" class=\"button\" 
					onclick=\"javascript:userEditSave()\" value=\"Save\">
				<input type=\"submit\" class=\"button\" 
					onclick=\"javascript:userEditCancel()\" value=\"Cancel\">";
					
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

		print "<div class='infoBoxContents'>";

		$result = db_query($link, "SELECT login,
			SUBSTRING(last_login,1,16) AS last_login,
			access_level,
			(SELECT COUNT(int_id) FROM ttrss_user_entries 
				WHERE owner_uid = id) AS stored_articles
			FROM ttrss_users 
			WHERE id = '$uid'");
			
		if (db_num_rows($result) == 0) {
			print "<h1>User not found</h1>";
			return;
		}
		
		print "<h1>User Details</h1>";

		print "<table width='100%'>";

		$login = db_fetch_result($result, 0, "login");
		$last_login = date(get_pref($link, 'LONG_DATE_FORMAT'),
			strtotime(db_fetch_result($result, 0, "last_login")));
		$access_level = db_fetch_result($result, 0, "access_level");
		$stored_articles = db_fetch_result($result, 0, "stored_articles");

		print "<tr><td>Username</td><td>$login</td></tr>";
		print "<tr><td>Access level</td><td>$access_level</td></tr>";
		print "<tr><td>Last logged in</td><td>$last_login</td></tr>";
		print "<tr><td>Stored articles</td><td>$stored_articles</td></tr>";

		$result = db_query($link, "SELECT COUNT(id) as num_feeds FROM ttrss_feeds
			WHERE owner_uid = '$uid'");

		$num_feeds = db_fetch_result($result, 0, "num_feeds");

		print "<tr><td>Subscribed feeds count</td><td>$num_feeds</td></tr>";

/*		$result = db_query($link, "SELECT 
			SUM(LENGTH(content)+LENGTH(title)+LENGTH(link)+LENGTH(guid)) AS db_size 
			FROM ttrss_user_entries,ttrss_entries 
				WHERE owner_uid = '$uid' AND ref_id = id");

		$db_size = round(db_fetch_result($result, 0, "db_size") / 1024);

		print "<tr><td>Approx. used DB size</td><td>$db_size KBytes</td></tr>";  */

		print "</table>";

		print "<h1>Subscribed feeds</h1>";

		$result = db_query($link, "SELECT id,title,site_url FROM ttrss_feeds
			WHERE owner_uid = '$uid' ORDER BY title");

		print "<ul class=\"userFeedList\">";

		while ($line = db_fetch_assoc($result)) {

			$icon_file = ICONS_URL."/".$line["id"].".ico";

			if (file_exists($icon_file) && filesize($icon_file) > 0) {
				$feed_icon = "<img class=\"tinyFeedIcon\" src=\"$icon_file\">";
			} else {
				$feed_icon = "<img class=\"tinyFeedIcon\" src=\"images/blank_icon.gif\">";
			}

			print "<li>$feed_icon&nbsp;<a href=\"".$line["site_url"]."\">".$line["title"]."</a></li>";
		}

		if (db_num_rows($result) < $num_feeds) {
			 // FIXME - add link to show ALL subscribed feeds here somewhere
			print "<li><img 
				class=\"tinyFeedIcon\" src=\"images/blank_icon.gif\">&nbsp;...</li>";
		}
		
		print "</ul>";

		print "</div>";

		print "<div align='center'>
			<input type='submit' class='button'			
			onclick=\"closeInfoBox()\" value=\"Close this window\"></div>";

//		print "</body></html>"; 

	}

	if ($op == "feed-details") {

//		$feed_id = $_GET["id"];

		$feed_ids = split(",", db_escape_string($_GET["id"]));

		print "<div class=\"infoBoxContents\">";

		foreach ($feed_ids as $feed_id) {

			$result = db_query($link, 
				"SELECT 
					title,feed_url,
					SUBSTRING(last_updated,1,16) as last_updated,
					icon_url,site_url,
					(SELECT COUNT(int_id) FROM ttrss_user_entries 
						WHERE feed_id = id) AS total,
					(SELECT COUNT(int_id) FROM ttrss_user_entries 
						WHERE feed_id = id AND unread = true) AS unread,
					(SELECT COUNT(int_id) FROM ttrss_user_entries 
						WHERE feed_id = id AND marked = true) AS marked
				FROM ttrss_feeds
				WHERE id = '$feed_id' AND owner_uid = ".$_SESSION["uid"]);
	
			if (db_num_rows($result) == 0) return;
	
			$title = db_unescape_string(db_fetch_result($result, 0, "title"));
			$last_updated = date(get_pref($link, 'LONG_DATE_FORMAT'),
				strtotime(db_fetch_result($result, 0, "last_updated")));
			$feed_url = db_fetch_result($result, 0, "feed_url");
			$icon_url = db_fetch_result($result, 0, "icon_url");
			$total = db_fetch_result($result, 0, "total");
			$unread = db_fetch_result($result, 0, "unread");
			$marked = db_fetch_result($result, 0, "marked");
			$site_url = db_fetch_result($result, 0, "site_url");
	
			$result = db_query($link, "SELECT COUNT(id) AS subscribed
						FROM ttrss_feeds WHERE feed_url = '$feed_url' AND private = false");
	
			$subscribed = db_fetch_result($result, 0, "subscribed");
	
			$icon_file = ICONS_DIR . "/$feed_id.ico";
	
			if (file_exists($icon_file) && filesize($icon_file) > 0) {
					$feed_icon = "<img width=\"16\" height=\"16\"
						src=\"" . ICONS_URL . "/$feed_id.ico\">";
			} else {
				$feed_icon = "";
			}
	
			print "<h1>$feed_icon $title</h1>";
	
			print "<table width='100%'>";
	
			if ($site_url) {
				print "<tr><td width='30%'>Link</td>
					<td><a href=\"$site_url\">$site_url</a>
					<a href=\"$feed_url\">(feed)</a></td>
					</td></tr>";
			} else {
				print "<tr><td width='30%'>Feed URL</td>
					<td><a href=\"$feed_url\">$feed_url</a></td></tr>";
			}
			print "<tr><td>Last updated</td><td>$last_updated</td></tr>";
			print "<tr><td>Total articles</td><td>$total</td></tr>";
			print "<tr><td>Unread articles</td><td>$unread</td></tr>";
			print "<tr><td>Starred articles</td><td>$marked</td></tr>";
			print "<tr><td>Subscribed users</td><td>$subscribed</td></tr>";
	
			print "</table>";
	
/*			$result = db_query($link, "SELECT title,
				SUBSTRING(updated,1,16) AS updated,unread
				FROM ttrss_entries,ttrss_user_entries
				WHERE ref_id = id AND feed_id = '$feed_id' 
				ORDER BY date_entered DESC LIMIT 5");
	
			if (db_num_rows($result) > 0) {
	
				print "<h1>Latest headlines</h1>";
	
				print "<ul class=\"nomarks\">";
		
				while ($line = db_fetch_assoc($result)) {
					if ($line["unread"] == "t" || $line["unread"] == "1") {
						$line["title"] = "<b>" . $line["title"] . "</b>";
					}				
					print "<li>" . $line["title"].
					"&nbsp;<span class=\"insensitive\">(" .
						date(get_pref($link, 'SHORT_DATE_FORMAT'), 
							strtotime($line["updated"])).
					")</span></li>";
				}
		
				print "</ul>";
		
			} */
		}

		print "</div>";
	
		print "<div align='center'>
			<input type='submit' class='button'			
			onclick=\"closeInfoBox()\" value=\"Close this window\"></div>";
	}	

	if ($op == "pref-feed-browser") {

		if (!ENABLE_FEED_BROWSER) {
			print "Feed browser is administratively disabled.";
			return;
		}

		$subop = $_REQUEST["subop"];

		if ($subop == "details") {
			$id = db_escape_string($_GET["id"]);

			print "<div class=\"browserFeedInfo\">";
			print "<b>Feed information:</b>";
			print "<div class=\"detailsPart\">";

			$result = db_query($link, "SELECT 
					feed_url,site_url,
					SUBSTRING(last_updated,1,19) AS last_updated
				FROM ttrss_feeds WHERE id = '$id'");

			$feed_url = db_fetch_result($result, 0, "feed_url");
			$site_url = db_fetch_result($result, 0, "site_url");
			$last_updated = db_fetch_result($result, 0, "last_updated");

			if (get_pref($link, 'HEADLINES_SMART_DATE')) {
				$last_updated = smart_date_time(strtotime($last_updated));
			} else {
				$short_date = get_pref($link, 'SHORT_DATE_FORMAT');
				$last_updated = date($short_date, strtotime($last_updated));
			}

			print "Site: <a href='$site_url'>$site_url</a> ".
				"(<a href='$feed_url'>feed</a>), ".
				"Last updated: $last_updated";

			print "</div>";

			$result = db_query($link, "SELECT 
					ttrss_entries.title,
					content,
					substring(date_entered,1,19) as date_entered,
					substring(updated,1,19) as updated
				FROM ttrss_entries,ttrss_user_entries
				WHERE	ttrss_entries.id = ref_id AND feed_id = '$id' 
				ORDER BY updated DESC LIMIT 5");

			if (db_num_rows($result) > 0) {
				
				print "<b>Last headlines:</b><br>";
				
				print "<div class=\"detailsPart\">";
				print "<ul class=\"compact\">";
				while ($line = db_fetch_assoc($result)) {

					if (get_pref($link, 'HEADLINES_SMART_DATE')) {
						$entry_dt = smart_date_time(strtotime($line["updated"]));
					} else {
						$short_date = get_pref($link, 'SHORT_DATE_FORMAT');
						$entry_dt = date($short_date, strtotime($line["updated"]));
					}				
		
					print "<li>" . $line["title"] . 
						"&nbsp;<span class=\"insensitive\">($entry_dt)</span></li>";	
				}		
				print "</ul></div>";
			}

			print "</div>";
				
			return;
		}

		$result = db_query($link, "SELECT feed_url,count(id) AS subscribers 
			FROM ttrss_feeds 
			WHERE auth_login = '' AND auth_pass = '' AND private = false
			GROUP BY feed_url ORDER BY subscribers DESC LIMIT 100");
		
		print "<ul class='nomarks' id='browseBigFeedList'>";

		$feedctr = 0;
		
		while ($line = db_fetch_assoc($result)) {
			$feed_url = $line["feed_url"];
			$subscribers = $line["subscribers"];

			$sub_result = db_query($link, "SELECT id
				FROM ttrss_feeds WHERE feed_url = '$feed_url' AND owner_uid =" . 
				$_SESSION["uid"]);

			if (db_num_rows($sub_result) > 0) {
				continue; // already subscribed
			}
		
			$det_result = db_query($link, "SELECT site_url,title,id 
				FROM ttrss_feeds WHERE feed_url = '$feed_url' LIMIT 1");

			$details = db_fetch_assoc($det_result);
		
			$icon_file = ICONS_DIR . "/" . $details["id"] . ".ico";

			if (file_exists($icon_file) && filesize($icon_file) > 0) {
					$feed_icon = "<img class=\"tinyFeedIcon\"	src=\"" . ICONS_URL . 
						"/".$details["id"].".ico\">";
			} else {
				$feed_icon = "<img class=\"tinyFeedIcon\" src=\"images/blank_icon.gif\">";
			}

			$check_box = "<input onclick='toggleSelectListRow(this)' class='feedBrowseCB' 
				type=\"checkbox\" id=\"FBCHK-" . $details["id"] . "\">";

			$class = ($feedctr % 2) ? "even" : "odd";

			print "<li class='$class' id=\"FBROW-".$details["id"]."\">$check_box".
				"$feed_icon ";
				
			print "<a href=\"javascript:browserToggleExpand('".$details["id"]."')\">" . 
				$details["title"] ."</a>&nbsp;" .
				"<span class='subscribers'>($subscribers)</span>";
			
			print "<div class=\"browserDetails\" id=\"BRDET-" . $details["id"] . "\">";
			print "</div>";
				
			print "</li>";

				++$feedctr;
		}

		if ($feedctr == 0) {
			print "<li>No feeds found to subscribe.</li>";
		}

		print "</ul>";

		print "<p>Selection: 
			<input type='submit' class='button' onclick=\"feedBrowserSubscribe()\" 
			value=\"Subscribe\"></p>";

		print "</div>";

	}

	db_close($link);
?>

<!-- <?= sprintf("Backend execution time: %.4f seconds", getmicrotime() - $script_started) ?> -->


<?php
	error_reporting(E_ERROR | E_PARSE);

	require_once "../config.php";
	
	require_once "../db.php";
	require_once "../db-prefs.php";
	require_once "../functions.php";

	define('API_STATUS_OK', 0);
	define('API_STATUS_ERR', 1);

	if (defined('ENABLE_GZIP_OUTPUT') && ENABLE_GZIP_OUTPUT) {
		ob_start("ob_gzhandler");
	}

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);	

	$session_expire = SESSION_EXPIRE_TIME; //seconds
	$session_name = (!defined('TTRSS_SESSION_NAME')) ? "ttrss_sid_api" : TTRSS_SESSION_NAME . "_api";

	session_name($session_name);

	if ($_REQUEST["sid"]) {
		session_id($_REQUEST["sid"]);
	}

	session_start();

	if (!$link) {
		if (DB_TYPE == "mysql") {
			print mysql_error();
		}
		// PG seems to display its own errors just fine by default.		
		return;
	}

	init_connection($link);

	$op = db_escape_string($_REQUEST["op"]);
	$seq = (int) $_REQUEST["seq"];

//	header("Content-Type: application/json");

	function api_wrap_reply($status, $seq, $reply) {
		print json_encode(array("seq" => $seq,
			"status" => $status,
			"content" => $reply));
	}

	if (!$_SESSION["uid"] && $op != "login" && $op != "isLoggedIn") {
		print api_wrap_reply(API_STATUS_ERR, $seq, array("error" => 'NOT_LOGGED_IN'));
		return;
	}

	if ($_SESSION["uid"] && $op != "logout" && !get_pref($link, 'ENABLE_API_ACCESS')) {
		print api_wrap_reply(API_STATUS_ERR, $seq, array("error" => 'API_DISABLED'));
		return;
	} 

	switch ($op) {
	
		case "getVersion":
			$rv = array("version" => VERSION);
			print api_wrap_reply(API_STATUS_OK, $seq, $rv);
			break;
		
		case "login":
			$login = db_escape_string($_REQUEST["user"]);
			$password = db_escape_string($_REQUEST["password"]);
			$password_base64 = db_escape_string(base64_decode($_REQUEST["password"]));

			$result = db_query($link, "SELECT id FROM ttrss_users WHERE login = '$login'");

			if (db_num_rows($result) != 0) {
				$uid = db_fetch_result($result, 0, "id");
			} else {
				$uid = 0;
			}

			if (SINGLE_USER_MODE) $login = "admin";

			if ($uid && get_pref($link, "ENABLE_API_ACCESS", $uid)) {
				if (authenticate_user($link, $login, $password)) {               // try login with normal password
					print api_wrap_reply(API_STATUS_OK, $seq, 
						array("session_id" => session_id()));
				} else if (authenticate_user($link, $login, $password_base64)) { // else try with base64_decoded password
					print api_wrap_reply(API_STATUS_OK, $seq, 
						array("session_id" => session_id()));
				} else {                                                         // else we are not logged in
					print api_wrap_reply(API_STATUS_ERR, $seq, 
						array("error" => "LOGIN_ERROR"));
				}
			} else {
				print api_wrap_reply(API_STATUS_ERR, $seq,
					array("error" => "API_DISABLED"));
			}

			break;
			
		case "logout":
			logout_user();
			print api_wrap_reply(API_STATUS_OK, $seq, array("status" => "OK"));
			break;
			
		case "isLoggedIn":
			print api_wrap_reply(API_STATUS_OK, $seq, 
				array("status" => $_SESSION["uid"] != ''));
			break;
			
		case "getUnread":
			$feed_id = db_escape_string($_REQUEST["feed_id"]);
			$is_cat = db_escape_string($_REQUEST["is_cat"]);

			if ($feed_id) {
				print api_wrap_reply(API_STATUS_OK, $seq, 
					array("unread" => getFeedUnread($link, $feed_id, $is_cat)));
			} else {
				print api_wrap_reply(API_STATUS_OK, $seq, 
					array("unread" => getGlobalUnread($link)));
			}
			break;
			
		/* Method added for ttrss-reader for Android */
		case "getCounters":

			/* flct (flc is the default) FIXME: document */
			$output_mode = db_escape_string($_REQUEST["output_mode"]);

			print api_wrap_reply(API_STATUS_OK, $seq, 
				getAllCounters($link, $output_mode));
			break;
			
		case "getFeeds":
			$cat_id = db_escape_string($_REQUEST["cat_id"]);
			$unread_only = (bool)db_escape_string($_REQUEST["unread_only"]);
			$limit = (int) db_escape_string($_REQUEST["limit"]);
			$offset = (int) db_escape_string($_REQUEST["offset"]);

			chdir(".."); // so feed_has_icon() would work properly for relative ICONS_DIR

			$feeds = api_get_feeds($link, $cat_id, $unread_only, $limit, $offset);

			print api_wrap_reply(API_STATUS_OK, $seq, $feeds);

			break;
			
		case "getCategories":
			$unread_only = (bool)db_escape_string($_REQUEST["unread_only"]);

			$result = db_query($link, "SELECT 
					id, title FROM ttrss_feed_categories 
				WHERE owner_uid = " . 
				$_SESSION["uid"]);

			$cats = array();

			while ($line = db_fetch_assoc($result)) {
				$unread = getFeedUnread($link, $line["id"], true);

				if ($unread || !$unread_only) {
					array_push($cats, array("id" => $line["id"],
						"title" => $line["title"], 
						"unread" => $unread));
				}
			}

			print api_wrap_reply(API_STATUS_OK, $seq, $cats);
			break;
			
		case "getHeadlines":
			$feed_id = db_escape_string($_REQUEST["feed_id"]);
			$limit = (int)db_escape_string($_REQUEST["limit"]);
			$offset = (int)db_escape_string($_REQUEST["skip"]);
			$filter = db_escape_string($_REQUEST["filter"]);
			$is_cat = (bool)db_escape_string($_REQUEST["is_cat"]);
			$show_excerpt = (bool)db_escape_string($_REQUEST["show_excerpt"]);
			$show_content = (bool)db_escape_string($_REQUEST["show_content"]);
			/* all_articles, unread, adaptive, marked, updated */
			$view_mode = db_escape_string($_REQUEST["view_mode"]);

			$headlines = api_get_headlines($link, $feed_id, $limit, $offset,
				$filter, $is_cat, $show_excerpt, $show_content, $view_mode, false);

			print api_wrap_reply(API_STATUS_OK, $seq, $headlines);

			break;
			
		case "updateArticle":
			$article_ids = split(",", db_escape_string($_REQUEST["article_ids"]));
			$mode = (int) db_escape_string($_REQUEST["mode"]);
			$field_raw = (int)db_escape_string($_REQUEST["field"]);

			$field = "";
			$set_to = "";

			switch ($field_raw) {
				case 0:
					$field = "marked";
					break;
				case 1:
					$field = "published";
					break;
				case 2:
					$field = "unread";
					break;
			};

			switch ($mode) {
				case 1:
					$set_to = "true";
					break;
				case 0:
					$set_to = "false";
					break;
				case 2:
					$set_to = "NOT $field";
					break;
			}

			if ($field && $set_to && count($article_ids) > 0) {

				$article_ids = join(", ", $article_ids);

				if ($field == "unread") {
					$result = db_query($link, "UPDATE ttrss_user_entries SET $field = $set_to,
						last_read = NOW()
						WHERE ref_id IN ($article_ids) AND owner_uid = " . $_SESSION["uid"]);
				} else {
					$result = db_query($link, "UPDATE ttrss_user_entries SET $field = $set_to
						WHERE ref_id IN ($article_ids) AND owner_uid = " . $_SESSION["uid"]);
				}

				$num_updated = db_affected_rows($link, $result);

				if ($num_updated > 0 && $field == "unread") {
					$result = db_query($link, "SELECT DISTINCT feed_id FROM ttrss_user_entries
						WHERE ref_id IN ($article_ids)");

					while ($line = db_fetch_assoc($result)) {
						ccache_update($link, $line["feed_id"], $_SESSION["uid"]);
					}
				}

				print api_wrap_reply(API_STATUS_OK, $seq, array("status" => "OK", 
					"updated" => $num_updated));

			} else {
				print api_wrap_reply(API_STATUS_ERR, $seq, 
					array("error" => 'INCORRECT_USAGE'));
			}

			break;

		case "getArticle":

			$article_id = db_escape_string($_REQUEST["article_id"]);

			$query = "SELECT id,title,link,content,feed_id,comments,int_id,
				marked,unread,published,
				".SUBSTRING_FOR_DATE."(updated,1,16) as updated,
				author
				FROM ttrss_entries,ttrss_user_entries
				WHERE	id IN ($article_id) AND ref_id = id AND owner_uid = " . 
					$_SESSION["uid"] ;

			$result = db_query($link, $query);

			$articles = array();

			if (db_num_rows($result) != 0) {

				while ($line = db_fetch_assoc($result)) {

					$attachments = get_article_enclosures($link, $line['id']);

					$article = array(
						"id" => $line["id"],
						"title" => $line["title"],
						"link" => $line["link"],
						"labels" => get_article_labels($link, $line['id']),
						"unread" => sql_bool_to_bool($line["unread"]),
						"marked" => sql_bool_to_bool($line["marked"]),
						"published" => sql_bool_to_bool($line["published"]),
						"comments" => $line["comments"],
						"author" => $line["author"],
						"updated" => strtotime($line["updated"]),
						"content" => $line["content"],
						"feed_id" => $line["feed_id"],
						"attachments" => $attachments
					);

					array_push($articles, $article);

				}
			}

			print api_wrap_reply(API_STATUS_OK, $seq, $articles);

			break;
			
		case "getConfig":
			$config = array(
				"icons_dir" => ICONS_DIR,
				"icons_url" => ICONS_URL);

			if (ENABLE_UPDATE_DAEMON) {
				$config["daemon_is_running"] = file_is_locked("update_daemon.lock");
			}

			$result = db_query($link, "SELECT COUNT(*) AS cf FROM
				ttrss_feeds WHERE owner_uid = " . $_SESSION["uid"]);

			$num_feeds = db_fetch_result($result, 0, "cf");

			$config["num_feeds"] = (int)$num_feeds;
	
			print api_wrap_reply(API_STATUS_OK, $seq, $config);

			break;

		case "updateFeed":
			$feed_id = db_escape_string($_REQUEST["feed_id"]);

			update_rss_feed($link, $feed_id, true);

			print api_wrap_reply(API_STATUS_OK, $seq, array("status" => "OK"));

			break;

		case "catchupFeed":
			$feed_id = db_escape_string($_REQUEST["feed_id"]);
			$is_cat = db_escape_string($_REQUEST["is_cat"]);

			catchup_feed($link, $feed_id, $is_cat);

			print api_wrap_reply(API_STATUS_OK, $seq, array("status" => "OK"));

			break;

		case "getPref":
			$pref_name = db_escape_string($_REQUEST["pref_name"]);

			print api_wrap_reply(API_STATUS_OK, $seq, 
				array("value" => get_pref($link, $pref_name)));
			break;
		
		/* Method added for ttrss-reader for Android */
		case "getArticles":
			$isCategory = (int)db_escape_string($_REQUEST["is_cat"]);
			$id = (int)db_escape_string($_REQUEST["id"]);
			$displayUnread = (int)db_escape_string($_REQUEST["unread"]);
			$limit = (int)db_escape_string($_REQUEST["limit"]);
			$feeds = array();
			
			if ($isCategory > 0) {
				// Get Feeds of the category
				
				if ($id == 0) {
					$category_part = "cat_id is NULL";
				} else {
					$category_part = "cat_id = '$id'";
				}
				
				$result = db_query($link, "SELECT id FROM ttrss_feeds WHERE ".
							$category_part." AND owner_uid = '".$_SESSION["uid"]."'");				
				
				while ($line = db_fetch_assoc($result)) {
					array_push($feeds, $line["id"]);
				}
				
				// Virtual feeds
				$match_part = "";
				if ($id == -1) {
					$match_part = "marked = true";
					array_push($feeds, -1);
				} else if ($id == -2) {
					$match_part = "published = true";
					array_push($feeds, -2);
				} else if ($id == -3) {
					$match_part = "unread = true";
					array_push($feeds, -3);

					$intl = get_pref($link, "FRESH_ARTICLE_MAX_AGE", $owner_uid);

					if (DB_TYPE == "pgsql") {
						$match_part .= " AND updated > NOW() - INTERVAL '$intl hour' "; 
					} else {
						$match_part .= " AND updated > DATE_SUB(NOW(), INTERVAL $intl HOUR) ";
					}
				} else if ($id == -4) {
					$match_part = "true";
					array_push($feeds, -4);
				}
			} else {
				// Only add one feed
				array_push($feeds, $id);
			}
			
			$ret = array();
			
			if (DB_TYPE == "mysql") {
				$limit_part = " LIMIT 0,".$limit;
			} else if (DB_TYPE == "pgsql") {
				$limit_part = " LIMIT ".$limit;
			} else {
				$limit_part = "";
			}

			// Fetch articles for the feeds
			foreach ($feeds as $feed) {
				
				if ($match_part) {
					$from_qpart = "ttrss_user_entries,ttrss_feeds,ttrss_entries";
					$feeds_qpart = "ttrss_user_entries.feed_id = ttrss_feeds.id AND";

					$query = "SELECT ttrss_entries.id,ttrss_entries.title,link,content,feed_id,comments,int_id,
						marked,unread,published,".SUBSTRING_FOR_DATE."(updated,1,16) as updated,author 
						FROM $from_qpart WHERE
						ttrss_user_entries.ref_id = ttrss_entries.id AND 
						$feeds_qpart ($match_part) AND ttrss_user_entries.owner_uid = ".$_SESSION["uid"]." ORDER BY updated DESC".$limit_part;
					
					$result = db_query($link, $query);
				} else {
					$query = "SELECT ttrss_entries.id,ttrss_entries.title,link,content,feed_id,comments,int_id,
						marked,unread,published,".SUBSTRING_FOR_DATE."(updated,1,16) as updated,author 
						FROM ttrss_entries,ttrss_user_entries 
						WHERE feed_id = '".$feed."' AND ref_id = id AND owner_uid = ".
							$_SESSION["uid"]." AND unread >= '".$displayUnread."' ORDER BY updated DESC".$limit_part;
							
					$result = db_query($link, $query);
				}
				
				$articles = array();
				$i=0;
				while ($i < mysql_numrows($result)) {
					
					$article_id = db_fetch_result($result, $i, "id");
					
					$attachments = get_article_enclosures($link, $article_id);

					$article = array(
						"id" => db_fetch_result($result, $i, "ttrss_entries.id"),
						"title" => db_fetch_result($result, $i, "ttrss_entries.title"),
						"link" => db_fetch_result($result, $i, "link"),
						"labels" => get_article_labels($link, $article_id),
						"unread" => sql_bool_to_bool(db_fetch_result($result, $i, "unread")),
						"marked" => sql_bool_to_bool(db_fetch_result($result, $i, "marked")),
						"published" => sql_bool_to_bool(db_fetch_result($result, $i, "published")),
						"comments" => db_fetch_result($result, $i, "comments"),
						"author" => db_fetch_result($result, $i, "author"),
						"updated" => strtotime(db_fetch_result($result, $i, "updated")),
						"content" => db_fetch_result($result, $i, "content"),
						"feed_id" => db_fetch_result($result, $i, "feed_id"),
						"attachments" => $attachments
					);
					
					array_push($ret, $article);
					
					$i++;
				}
			}

			print api_wrap_reply(API_STATUS_OK, $seq, $ret);
			break;
		
		/* Method added for ttrss-reader for Android */
		case "getNewArticles":
			$time = (int) db_escape_string($_REQUEST["time"]);
			// unread=1 zeigt alle an, unread=0 nur ungelesene
			$displayUnread = (int) db_escape_string($_REQUEST["unread"]);
			
			if (DB_TYPE == "mysql") {
				$db_time_function = " AND last_updated > FROM_UNIXTIME(".$time.")";
			} else if (DB_TYPE == "pgsql") {
				$db_time_function = " AND last_updated > to_timestamp(".$time.")";
			} else {
				$db_time_function = "";
			}
			
			if (DB_TYPE == "mysql") {
				$db_time_function2 = " AND updated > FROM_UNIXTIME(".$time.")";
			} else if (DB_TYPE == "pgsql") {
				$db_time_function2 = " AND updated > to_timestamp(".$time.")";
			} else {
				$db_time_function2 = "";
			}
			
			$cats = array();


			// Add uncategorized feeds
			$unread = getFeedUnread($link, 0, true);
			if ($unread || $displayUnread > 0) {
				$feeds = array();
				$result_0 = db_query($link, "SELECT id, feed_url, cat_id, title, ".
						SUBSTRING_FOR_DATE."(last_updated,1,19) AS last_updated ".
						"FROM ttrss_feeds WHERE cat_id IS null AND owner_uid = '".$_SESSION["uid"]."'" . $db_time_function);
				
				while ($line_feeds = db_fetch_assoc($result_0)) {
					$unread_feed = getFeedUnread($link, $line_feeds["id"], false);
					if ($unread || $displayUnread > 0) {
						
						$result_1 = db_query($link, "SELECT id,title,link,content,feed_id,comments,int_id,
							marked,unread,published,".
							SUBSTRING_FOR_DATE."(updated,1,16) as updated,author
							FROM ttrss_entries,ttrss_user_entries
							WHERE feed_id = '".$line_feeds["id"]."' AND ref_id = id AND owner_uid = " . 
								$_SESSION["uid"]." AND unread >= '".$displayUnread."'" . $db_time_function2);
						
						$articles = array();
						while ($line_articles = db_fetch_assoc($result_1)) {
							$article_id = db_fetch_result($result, $i, "id");
							$attachments = get_article_enclosures($link, $article_id);
							array_push($articles, $article = array(
								"id" => $line_articles["id"],
								"title" => $line_articles["title"],
								"link" => $line_articles["link"],
								"labels" => $article_id,
								"unread" => $line_articles["unread"],
								"marked" => $line_articles["marked"],
								"published" => $line_articles["published"],
								"comments" => $line_articles["comments"],
								"author" => $line_articles["author"],
								"updated" => strtotime($line_articles["updated"]),
								"content" => $line_articles["content"],
								"feed_id" => $line_articles["feed_id"],
								"attachments" => $attachments));
						}
						
						array_push($feeds, array(
							"feed_url" => $line_feeds["feed_url"],
							"title" => $line_feeds["title"],
							"id" => (int)$line_feeds["id"],
							"unread" => (int)$unread_feed,
							"has_icon" => $has_icon,
							"cat_id" => (int)$line_feeds["cat_id"],
							"last_updated" => strtotime($line_feeds["last_updated"]),
							"articles" => $articles
						));
					}
				}
				
				array_push($cats,
					array(
						"id" => 0,
						"title" => "Uncategorized Feeds", 
						"unread" => $unread,
						"feeds" => $feeds));
			}

			
			$result = db_query($link, "SELECT id, title FROM ttrss_feed_categories WHERE owner_uid = " . $_SESSION["uid"]);
			while ($line = db_fetch_assoc($result)) {
				$unread = getFeedUnread($link, $line["id"], true);

				if ($unread || $displayUnread > 0) {
					$feeds = array();
					$result_0 = db_query($link, "SELECT id, feed_url, cat_id, title, ".
						SUBSTRING_FOR_DATE."(last_updated,1,19) AS last_updated ".
						"FROM ttrss_feeds WHERE cat_id = '".
						$line["id"]."' AND owner_uid = '".$_SESSION["uid"]."'" . $db_time_function);
					
					while ($line_feeds = db_fetch_assoc($result_0)) {
						$unread_feed = getFeedUnread($link, $line_feeds["id"], false);
						if ($unread_feed || $displayUnread > 0) {
							
							$result_1 = db_query($link, "SELECT id,title,link,content,feed_id,comments,int_id,
								marked,unread,published,".
								SUBSTRING_FOR_DATE."(updated,1,16) as updated,author
								FROM ttrss_entries,ttrss_user_entries
								WHERE feed_id = '".$line_feeds["id"]."' AND ref_id = id AND owner_uid = " . 
									$_SESSION["uid"]." AND unread >= '".$displayUnread."'" . $db_time_function2);
							
							$articles = array();
							while ($line_articles = db_fetch_assoc($result_1)) {
								$article_id = db_fetch_result($result, $i, "id");
								$attachments = get_article_enclosures($link, $article_id);
								array_push($articles, $article = array(
									"id" => $line_articles["id"],
									"title" => $line_articles["title"],
									"link" => $line_articles["link"],
									"labels" => $article_id,
									"unread" => $line_articles["unread"],
									"marked" => $line_articles["marked"],
									"published" => $line_articles["published"],
									"comments" => $line_articles["comments"],
									"author" => $line_articles["author"],
									"updated" => strtotime($line_articles["updated"]),
									"content" => $line_articles["content"],
									"feed_id" => $line_articles["feed_id"],
									"attachments" => $attachments));
							}
							
							array_push($feeds, array(
								"feed_url" => $line_feeds["feed_url"],
								"title" => $line_feeds["title"],
								"id" => (int)$line_feeds["id"],
								"unread" => (int)$unread_feed,
								"cat_id" => (int)$line_feeds["cat_id"],
								"last_updated" => strtotime($line_feeds["last_updated"]),
								"articles" => $articles
							));
						
						}
					}
					
					array_push($cats,
						array(
							"id" => $line["id"],
							"title" => $line["title"], 
							"unread" => $unread,
							"feeds" => $feeds));
				}
			}
			print api_wrap_reply(API_STATUS_OK, $seq, $cats);
			break;
		
		default:
			print api_wrap_reply(API_STATUS_ERR, $seq, 
				array("error" => 'UNKNOWN_METHOD'));
			break;

	}

	db_close($link);
	
?>

<?php
	error_reporting(E_ERROR | E_PARSE);

	require_once "../config.php";
	
	require_once "../db.php";
	require_once "../db-prefs.php";
	require_once "../functions.php";

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

//	header("Content-Type: application/json");

	if (!$_SESSION["uid"] && $op != "login" && $op != "isLoggedIn") {
		print json_encode(array("error" => 'NOT_LOGGED_IN'));
		return;
	}

	if ($_SESSION["uid"] && $op != "logout" && !get_pref($link, 'ENABLE_API_ACCESS')) {
		print json_encode(array("error" => 'API_DISABLED'));
		return;
	} 

	switch ($op) {
		case "getVersion":
			$rv = array("version" => VERSION);
			print json_encode($rv);
		break;
		case "login":
			$login = db_escape_string($_REQUEST["user"]);
			$password = db_escape_string($_REQUEST["password"]);

			$result = db_query($link, "SELECT id FROM ttrss_users WHERE login = '$login'");

			if (db_num_rows($result) != 0) {
				$uid = db_fetch_result($result, 0, "id");
			} else {
				$uid = 0;
			}

			if ($uid && get_pref($link, "ENABLE_API_ACCESS", $uid)) {
				if (authenticate_user($link, $login, $password)) {
					print json_encode(array("session_id" => session_id()));
				} else {
					print json_encode(array("error" => "LOGIN_ERROR"));
				}
			} else {
				print json_encode(array("error" => "API_DISABLED"));
			}

			break;
		case "logout":
			logout_user();
			print json_encode(array("status" => "OK"));
			break;
		case "isLoggedIn":
			print json_encode(array("status" => $_SESSION["uid"] != ''));
			break;
		case "getUnread":
			$feed_id = db_escape_string($_REQUEST["feed_id"]);
			$is_cat = db_escape_string($_REQUEST["is_cat"]);

			if ($feed_id) {
				print json_encode(array("unread" => getFeedUnread($link, $feed_id, $is_cat)));
			} else {
				print json_encode(array("unread" => getGlobalUnread($link)));
			}
			break;
		case "getCounters":

			/* TODO */

			break;
		case "getFeeds":
			$cat_id = db_escape_string($_REQUEST["cat_id"]);
			$unread_only = (bool)db_escape_string($_REQUEST["unread_only"]);
			$limit = (int) db_escape_string($_REQUEST["limit"]);
			$offset = (int) db_escape_string($_REQUEST["offset"]);

			$feeds = api_get_feeds($link, $cat_id, $unread_only, $limit, $offset);

			print json_encode($feeds);

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

			print json_encode($cats);
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

			print json_encode($headlines);

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

					print json_encode($article);
				}
			}

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
	
			print json_encode($config);

			break;

		case "updateFeed":
			$feed_id = db_escape_string($_REQUEST["feed_id"]);

			update_rss_feed($link, $feed_id, true);

			print json_encode(array("status" => "OK"));

			break;

		case "catchupFeed":
			$feed_id = db_escape_string($_REQUEST["feed_id"]);
			$is_cat = db_escape_string($_REQUEST["category"]);

			catchup_feed($link, $feed_id, $is_cat);

			print json_encode(array("status" => "OK"));

			break;

		case "getPref":
			$pref_name = db_escape_string($_REQUEST["pref_name"]);
			print json_encode(array("value" => get_pref($link, $pref_name)));
			break;

		default:
			print json_encode(array("error" => 'UNKNOWN_METHOD'));
			break;

	}

	db_close($link);
	
?>

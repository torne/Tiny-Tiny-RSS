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

			if (SINGLE_USER_MODE) $login = "admin";

			$result = db_query($link, "SELECT id FROM ttrss_users WHERE login = '$login'");

			if (db_num_rows($result) != 0) {
				$uid = db_fetch_result($result, 0, "id");
			} else {
				$uid = 0;
			}

			if (!$uid) {
				print api_wrap_reply(API_STATUS_ERR, $seq,
					array("error" => "LOGIN_ERROR"));
				return;
			}

			if (get_pref($link, "ENABLE_API_ACCESS", $uid)) {
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
			$include_attachments = (bool)db_escape_string($_REQUEST["include_attachments"]);

			$headlines = api_get_headlines($link, $feed_id, $limit, $offset,
				$filter, $is_cat, $show_excerpt, $show_content, $view_mode, false,
				$include_attachments);

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

			$config["daemon_is_running"] = file_is_locked("update_daemon.lock");

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

		default:
			print api_wrap_reply(API_STATUS_ERR, $seq,
				array("error" => 'UNKNOWN_METHOD'));
			break;

	}

	db_close($link);

?>

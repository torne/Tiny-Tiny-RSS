<?
	require "xmlrpc/lib/xmlrpc.inc";
	require "xmlrpc/lib/xmlrpcs.inc";

	require_once "sanity_check.php";
	require_once "config.php";
	
	require_once "db.php";
	require_once "db-prefs.php";
	require_once "functions.php";

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

	function getVirtualFeeds($msg) {
		global $link;

		$error_code = 0;

		$login_o = $msg->getParam(0);
		$pass_o = $msg->getParam(1);
	
		$login = $login_o->scalarval();
		$pass = $pass_o->scalarval();
	
		$user_id = authenticate_user($link, $login, $pass);
		
		$counters_ret = array();
		
		if (authenticate_user($link, $login, $pass)) {

			$counters = getLabelCounters($link, false, true);

			foreach (array_keys($counters) as $id) {
				$line_struct = new xmlrpcval(
					array(
						"id" => new xmlrpcval($id, "int"),
						"title" => new xmlrpcval($counters[$id]["description"]),
						"unread" => new xmlrpcval($counters[$id]["counter"], "int")
					),
					"struct");

				array_push($counters_ret, $line_struct);
			}

			$reply = new xmlrpcval($counters_ret, "array");

		} else {
			$reply_msg = "Login failed.";
			$error_code = 1;
		}
		
		if ($error_code != 0) {
			return new xmlrpcresp(0, $error_code, $reply_msg);
		} else {		
			return new xmlrpcresp($reply);
		} 

	}

	function getCategories($msg) {
		global $link;

		$login_o = $msg->getParam(0);
		$pass_o = $msg->getParam(1);
	
		$login = $login_o->scalarval();
		$pass = $pass_o->scalarval();
	
		$user_id = authenticate_user($link, $login, $pass);

		$error_code = 0;

		if (authenticate_user($link, $login, $pass)) {

			$result = db_query($link, "SELECT 
					id, title FROM ttrss_feed_categories 
				WHERE owner_uid = " . 
				$_SESSION["uid"]);

			$feeds = array();

			while ($line = db_fetch_assoc($result)) {

				$unread = getFeedUnread($link, $line["id"]);
				
				$line_struct = new xmlrpcval(
					array(
						"title" => new xmlrpcval($line["title"]),
						"id" => new xmlrpcval($line["id"], "int")
					),
					"struct");

				array_push($feeds, $line_struct);
			}

			$reply = new xmlrpcval($feeds, "array");
			
		} else {
			$reply = "Login failed.";
			$error_code = 1;
		}
	
		if ($error_code != 0) {
			return new xmlrpcresp(0, $error_code, $reply_msg);
		} else {		
			return new xmlrpcresp($reply);
		}

	}

	function getTotalUnread($msg) {
		global $link;

		$error_code = 0;

		$login_o = $msg->getParam(0);
		$pass_o = $msg->getParam(1);
	
		$login = $login_o->scalarval();
		$pass = $pass_o->scalarval();
	
		$user_id = authenticate_user($link, $login, $pass);
	
		
		if (authenticate_user($link, $login, $pass)) {

			$reply_msg = getGlobalUnread($link);

		} else {
			$reply_msg = "Login failed.";
			$error_code = 1;
		}
		
		if ($error_code != 0) {
			return new xmlrpcresp(0, $error_code, $reply_msg);
		} else {		
			return new xmlrpcresp(new xmlrpcval($reply_msg));
		}

	}

	function getVersion() {
		return new xmlrpcval(VERSION);
	}

	function getSubscribedFeeds($msg) {
		global $link;

		$login_o = $msg->getParam(0);
		$pass_o = $msg->getParam(1);
	
		$login = $login_o->scalarval();
		$pass = $pass_o->scalarval();
	
		$user_id = authenticate_user($link, $login, $pass);

		if (authenticate_user($link, $login, $pass)) {

			$result = db_query($link, "SELECT 
				id, feed_url, cat_id, title, SUBSTRING(last_updated,1,19) AS last_updated
					FROM ttrss_feeds WHERE owner_uid = " . 
				$_SESSION["uid"]);

			$feeds = array();

			while ($line = db_fetch_assoc($result)) {

				$unread = getFeedUnread($link, $line["id"]);
				
				$line_struct = new xmlrpcval(
					array(
						"feed_url" => new xmlrpcval($line["feed_url"]),
						"title" => new xmlrpcval($line["title"]),
						"id" => new xmlrpcval($line["id"], "int"),
						"unread" => new xmlrpcval($unread, "int"),
						"cat_id" => new xmlrpcval($line["cat_id"], "int"),
						"last_updated" => new xmlrpcval(strtotime($line["last_updated"]), "int")
					),
					"struct");

				array_push($feeds, $line_struct);
			}

			$reply = new xmlrpcval($feeds, "array");
			
		} else {
			$reply = new xmlrpcval("Login failed.");
		}
		
		return new xmlrpcresp($reply);
	}

	function subscribeToFeed($msg) {
		global $link;

		$error_code = 0;

		$login_o = $msg->getParam(0);
		$pass_o = $msg->getParam(1);
		$feed_url_o = $msg->getParam(2);
	
		$login = $login_o->scalarval();
		$pass = $pass_o->scalarval();
		$feed_url = $feed_url_o->scalarval();

		if (authenticate_user($link, $login, $pass)) {
			if (subscribe_to_feed($link, $feed_url)) {
				$reply_msg = "Subscribed successfully.";
			} else {
				$reply_msg = "Feed already exists in the database.";
				$error_code = 2;
			}		
		} else {
			$reply_msg = "Login failed.";
			$error_code = 1;
		}
	
		if ($error_code != 0) {
			return new xmlrpcresp(0, $error_code, $reply_msg);
		} else {		
			return new xmlrpcresp(new xmlrpcval($reply_msg));
		}
	}

	function getFeedHeadlines($msg) {
		global $link;

		$error_code = 0;

		$login_o = $msg->getParam(0);
		$pass_o = $msg->getParam(1);
		$feed_id_o = $msg->getParam(2);
		$limit_o = $msg->getParam(3);
		$filter_o = $msg->getParam(4);

		$login = $login_o->scalarval();
		$pass = $pass_o->scalarval();
		$feed_id = $feed_id_o->scalarval();
		$limit = $limit_o->scalarval();
		$filter = $filter_o->scalarval();

		if (authenticate_user($link, $login, $pass)) {

			if ($filter == 1) {
				$view_mode = "unread";
			} else if ($filter == 2) {
				$view_mode = "marked";
			} else if ($filter == 3) {
				$view_mode = "adaptive";
			}
		
			$cat_view = false;
			$search = "";
			$search_mode = "";
			$match_on = "";
			
			$qfh_ret = queryFeedHeadlines($link, $feed_id, $limit, 
				$view_mode, $cat_view, $search, $search_mode, $match_on);

			$result = $qfh_ret[0];
			$feed_title = $qfh_ret[1];
				
			$articles = array();

			while ($line = db_fetch_assoc($result)) {

				$headline_items =	array(
						"id" => new xmlrpcval($line["id"], "int"),
						"unread" => new xmlrpcval(sql_bool_to_bool($line["unread"]), "boolean"),
						"marked" => new xmlrpcval(sql_bool_to_bool($line["marked"]), "boolean"),
						"updated" => new xmlrpcval(strtotime($line["updated"]), "int"),
						"title" => new xmlrpcval($line["title"])
					);

				if ($feed_id < 0) {
					$headline_items["feed_id"] = new xmlrpcval($line["feed_id"], "int");
				}
			
				$line_struct = new xmlrpcval($headline_items, 
					"struct");

				array_push($articles, $line_struct);
			}

			$reply = new xmlrpcval(
			  	array(
					"title" => new xmlrpcval($feed_title),
					"headlines" => new xmlrpcval($articles, "array")
				),
				"struct");

		} else {
			$reply_msg = "Login failed.";
			$error_code = 1;
		}

		if ($error_code != 0) {
			return new xmlrpcresp(0, $error_code, $reply_msg);
		} else {		
			return new xmlrpcresp($reply);
		}

	}

	function getArticle($msg) {
		global $link;

		$error_code = 0;

		$login_o = $msg->getParam(0);
		$pass_o = $msg->getParam(1);
		$article_id_o = $msg->getParam(2);
	
		$login = $login_o->scalarval();
		$pass = $pass_o->scalarval();
		$article_id = $article_id_o->scalarval();

		if (authenticate_user($link, $login, $pass)) {

			$query = "SELECT title,link,content,feed_id,comments,int_id,
				marked,unread,
				SUBSTRING(updated,1,16) as updated,
				author
				FROM ttrss_entries,ttrss_user_entries
				WHERE	id = '$article_id' AND ref_id = id AND owner_uid = " . $_SESSION["uid"] ;

			$result = db_query($link, $query);

			if (db_num_rows($result) == 1) {

				$line = db_fetch_assoc($result);

				$reply = new xmlrpcval(
					array(
						"title" => new xmlrpcval($line["title"]),
						"link" => new xmlrpcval($line["link"]),
						"unread" => new xmlrpcval(sql_bool_to_bool($line["unread"]), "boolean"),
						"marked" => new xmlrpcval(sql_bool_to_bool($line["marked"]), "boolean"),
						"comments" => new xmlrpcval($line["comments"]),
						"author" => new xmlrpcval($line["author"]),
						"updated" => new xmlrpcval(strtotime($line["updated"], "int")),
						"content" => new xmlrpcval($line["content"])
					),
					"struct");
				
			} else {
				$reply_msg = "Article not found.";
				$error_code = 2;
			}
		
		} else {
			$reply_msg = "Login failed.";
			$error_code = 1;
		}
	
		if ($error_code != 0) {
			return new xmlrpcresp(0, $error_code, $reply_msg);
		} else {		
			return new xmlrpcresp($reply);
		}
	}

	function setArticleMarked($msg) {
		global $link;

		$error_code = 0;

		$login_o = $msg->getParam(0);
		$pass_o = $msg->getParam(1);
		$article_id_o = $msg->getParam(2);
		$marked_o = $msg->getParam(3);

		$login = $login_o->scalarval();
		$pass = $pass_o->scalarval();
		$article_id = $article_id_o->scalarval();
		$marked = $marked_o->scalarval();

		if (authenticate_user($link, $login, $pass)) {

			if ($marked == 0) {
				$query_strategy_part = "marked = false";
			} else if ($marked == 1) {
				$query_strategy_part = "marked = true";
			} else if ($marked == 2) {
				$query_strategy_part = "marked = NOT marked";
			}

			$result = db_query($link, "UPDATE ttrss_user_entries SET
				$query_strategy_part WHERE ref_id = '$article_id' AND
				owner_uid = " . $_SESSION["uid"]);

			if (db_affected_rows($link, $result) == 1) {
				$reply_msg = "OK";
			} else {
				$error_code = 2;
				$reply_msg = "Failed to update article.";
			}

		} else {
			$reply_msg = "Login failed.";
			$error_code = 1;
		}

		if ($error_code != 0) {
			return new xmlrpcresp(0, $error_code, $reply_msg);
		} else {		
			return new xmlrpcresp(new xmlrpcval($reply_msg));
		}

	}

	function setArticleRead($msg) {
		global $link;

		$error_code = 0;

		$login_o = $msg->getParam(0);
		$pass_o = $msg->getParam(1);
		$article_id_o = $msg->getParam(2);
		$read_o = $msg->getParam(3);

		$login = $login_o->scalarval();
		$pass = $pass_o->scalarval();
		$article_id = $article_id_o->scalarval();
		$read = $read_o->scalarval();

		if (authenticate_user($link, $login, $pass)) {

			if ($read == 0) {
				$query_strategy_part = "unread = true";
			} else if ($read == 1) {
				$query_strategy_part = "unread = false";
			} else if ($read == 2) {
				$query_strategy_part = "unread = NOT unread";
			}

			$result = db_query($link, "UPDATE ttrss_user_entries SET
				$query_strategy_part WHERE ref_id = '$article_id' AND
				owner_uid = " . $_SESSION["uid"]);

			if (db_affected_rows($link, $result) == 1) {
				$reply_msg = "OK";
			} else {
				$error_code = 2;
				$reply_msg = "Failed to update article.";
			}

		} else {
			$reply_msg = "Login failed.";
			$error_code = 1;
		}

		if ($error_code != 0) {
			return new xmlrpcresp(0, $error_code, $reply_msg);
		} else {		
			return new xmlrpcresp(new xmlrpcval($reply_msg));
		}

	}

	$subscribeToFeed_sig = array(array($xmlrpcString,
		$xmlrpcString, $xmlrpcString, $xmlrpcString));

	$getSubscribedFeeds_sig = array(array($xmlrpcString,
		$xmlrpcString, $xmlrpcString));

	$getFeedHeadlines_sig = array(array($xmlrpcString,
		$xmlrpcString, $xmlrpcString, $xmlrpcInt, $xmlrpcInt, $xmlrpcInt));

	$getArticle_sig = array(array($xmlrpcString,
		$xmlrpcString, $xmlrpcString, $xmlrpcInt));

	$setArticleMarked_sig = array(array($xmlrpcString,
		$xmlrpcString, $xmlrpcString, $xmlrpcInt, $xmlrpcInt));

	$setArticleUnread_sig = array(array($xmlrpcString,
		$xmlrpcString, $xmlrpcString, $xmlrpcInt, $xmlrpcInt));

	$getVersion_sig = array(array($xmlrpcString));
	
	$getTotalUnread_sig = array(array($xmlrpcInt, $xmlrpcString,
		$xmlrpcString));

	$getCategories_sig = array(array($xmlrpcString,
		$xmlrpcString, $xmlrpcString));

	$getVirtualFeeds_sig = array(array($xmlrpcInt, $xmlrpcString,
		$xmlrpcString));

	$s = new xmlrpc_server( 
			array(
			  "rss.getVirtualFeeds" => array("function" => "getVirtualFeeds",
		  			"signature" => $getVirtualFeeds_sig),
			  "rss.getCategories" => array("function" => "getCategories",
		  			"signature" => $getCategories_sig),
			  "rss.getTotalUnread" => array("function" => "getTotalUnread",
		  			"signature" => $getTotalUnread_sig),
			  "rss.getVersion" => array("function" => "getVersion",
		  			"signature" => $getVersion_sig),
			  "rss.setArticleRead" => array("function" => "setArticleRead",
		  			"signature" => $setArticleRead_sig),
			  "rss.setArticleMarked" => array("function" => "setArticleMarked",
		  			"signature" => $setArticleMarked_sig),
			  "rss.getArticle" => array("function" => "getArticle",
		  			"signature" => $getArticle_sig),
			  "rss.getFeedHeadlines" => array("function" => "getFeedHeadlines",
		  			"signature" => $getFeedHeadlines_sig),
			  "rss.getSubscribedFeeds" => array("function" => "getSubscribedFeeds",
		  			"signature" => $getSubscribedFeeds_sig),
			  "rss.subscribeToFeed" => array("function" => "subscribeToFeed",
		  			"signature" => $subscribeToFeed_sig)), 0
			);
	$s->response_charset_encoding = "UTF-8";
	$s->service();
?>

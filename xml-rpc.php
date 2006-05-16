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

	function getSubscribedFeeds($msg) {
		global $link;

		$login_o = $msg->getParam(0);
		$pass_o = $msg->getParam(1);
	
		$login = $login_o->scalarval();
		$pass = $pass_o->scalarval();
	
		$user_id = authenticate_user($link, $login, $pass);

		if (authenticate_user($link, $login, $pass)) {

			$result = db_query($link, "SELECT 
				id, feed_url, title, SUBSTRING(last_updated,1,19) AS last_updated
					FROM ttrss_feeds WHERE owner_uid = " . 
				$_SESSION["uid"]);

			$feeds = array();

			while ($line = db_fetch_assoc($result)) {
				
				$line_struct = new xmlrpcval(
					array(
						"feed_url" => new xmlrpcval($line["feed_url"]),
						"title" => new xmlrpcval($line["title"]),
						"last_updated" => new xmlrpcval(strtotime($line["last_updated"]))
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

		$login_o = $msg->getParam(0);
		$pass_o = $msg->getParam(1);
		$feed_url_o = $msg->getParam(2);
	
		$login = $login_o->scalarval();
		$pass = $pass_o->scalarval();
		$feed_url = $feed_url_o->scalarval();
	
		$user_id = authenticate_user($link, $login, $pass);

		if (authenticate_user($link, $login, $pass)) {
			if (subscribe_to_feed($link, $feed_url)) {
				$reply_msg = "Subscribed successfully.";
			} else {
				$reply_msg = "Feed already exists in the database.";
			}		
		} else {
			$reply_msg = "Login failed.";
		}
		
		return new xmlrpcresp(new xmlrpcval($reply_msg));
	}

	$subscribeToFeed_sig = array(array($xmlrpcString,
		$xmlrpcString, $xmlrpcString, $xmlrpcString));

	$getSubscribedFeeds_sig = array(array($xmlrpcString,
		$xmlrpcString, $xmlrpcString));

	$s = new xmlrpc_server( 
			array(
			  "rss.getSubscribedFeeds" => array("function" => "getSubscribedFeeds",
		  			"signature" => $getSubscribedFeeds_sig),
			  "rss.subscribeToFeed" => array("function" => "subscribeToFeed",
		  			"signature" => $subscribeToFeed_sig))
			);
?>

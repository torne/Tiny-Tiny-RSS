<?php
	error_reporting(E_ERROR | E_WARNING | E_PARSE);

	/* remove ill effects of magic quotes */

	if (get_magic_quotes_gpc()) {
		$_GET = array_map('stripslashes', $_GET);
		$_POST = array_map('stripslashes', $_POST);
		$_REQUEST = array_map('stripslashes', $_REQUEST);
		$_COOKIE = array_map('stripslashes', $_COOKIE);
	}

	require_once "sessions.php";
	require_once "modules/backend-rpc.php";

/*	if ($_GET["debug"]) {
		define('DEFAULT_ERROR_LEVEL', E_ALL);
	} else {
		define('DEFAULT_ERROR_LEVEL', E_ERROR | E_WARNING | E_PARSE);
	}
	
	error_reporting(DEFAULT_ERROR_LEVEL); */

	require_once "sanity_check.php";
	require_once "config.php";
	
	require_once "db.php";
	require_once "db-prefs.php";
	require_once "functions.php";

	no_cache_incantation();

	if (ENABLE_TRANSLATIONS == true) { 
		startup_gettext();
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

	init_connection($link);

	$op = $_REQUEST["op"];
	$mode = $_REQUEST["mode"];

	$print_exec_time = false;

	if ((!$op || $op == "rpc" || $op == "rss" || 
			($op == "view" && $mode != "zoom") || 
			$op == "digestSend" || $op == "viewfeed" || $op == "publish" ||
			$op == "globalUpdateFeeds") && !$_REQUEST["noxml"]) {
				header("Content-Type: application/xml; charset=utf-8");

				if (ENABLE_GZIP_OUTPUT) {
					ob_start("ob_gzhandler");
				}
				
		} else {
		if (!$_REQUEST["noxml"]) {
			header("Content-Type: text/html; charset=utf-8");
		} else {
			header("Content-Type: text/plain; charset=utf-8");
		}
	}

	if (!$op) {
		header("Content-Type: application/xml");
		print_error_xml(7); exit;
	}
	
	if (!($_SESSION["uid"] && validate_session($link)) && $op != "globalUpdateFeeds" 
			&& $op != "rss" && $op != "getUnread" && $op != "publish") {

		if ($op == "rpc" || $op == "viewfeed" || $op == "view") {
			print_error_xml(6); die;
		} else {
			print "
			<html><body>
				<p>Error: Not logged in.</p>
				<script type=\"text/javascript\">
					if (parent.window != 'undefined') {
						parent.window.location = \"tt-rss.php\";		
					} else {
						window.location = \"tt-rss.php\";
					}
				</script>
			</body></html>
			";
		}
		exit;
	}

	$purge_intervals = array(
		0  => __("Use default"),
		-1 => __("Never purge"),
		5  => __("1 week old"),
		14 => __("2 weeks old"),
		31 => __("1 month old"),
		60 => __("2 months old"),
		90 => __("3 months old"));

	$update_intervals = array(
		0   => __("Default interval"),
		-1  => __("Disable updates"),
		15  => __("Each 15 minutes"),
		30  => __("Each 30 minutes"),
		60  => __("Hourly"),
		240 => __("Each 4 hours"),
		720 => __("Each 12 hours"),
		1440 => __("Daily"),
		10080 => __("Weekly"));

	$update_methods = array(
		0   => __("Default"),
		1   => __("Magpie"),
		2   => __("SimplePie"));

	if (ENABLE_SIMPLEPIE) {
		$update_methods[0] .= ' (SimplePie)';
	} else {
		$update_methods[0] .= ' (Magpie)';
	}

	$access_level_names = array(
		0 => __("User"), 
		5 => __("Power User"),
		10 => __("Administrator"));

	require_once "modules/pref-prefs.php";
	require_once "modules/popup-dialog.php";
	require_once "modules/help.php";
	require_once "modules/pref-feeds.php";
	require_once "modules/pref-filters.php";
	require_once "modules/pref-labels.php";
	require_once "modules/pref-users.php";
	require_once "modules/pref-feed-browser.php"; 

	if (!sanity_check($link)) { return; }

	switch($op) { // Select action according to $op value.
		case "rpc":
			// Handle remote procedure calls.
			handle_rpc_request($link);
		break; // rpc

		case "feeds":
			if (ENABLE_GZIP_OUTPUT) {
				ob_start("ob_gzhandler");
			}

			$tags = $_GET["tags"];

			$subop = $_GET["subop"];

			switch($subop) {
				case "catchupAll":
					db_query($link, "UPDATE ttrss_user_entries SET 
						last_read = NOW(),unread = false WHERE owner_uid = " . $_SESSION["uid"]);
					ccache_zero_all($link, $_SESSION["uid"]);

				break;

				case "collapse":
					$cat_id = db_escape_string($_GET["cid"]);

					db_query($link, "UPDATE ttrss_feed_categories SET
						collapsed = NOT collapsed WHERE id = '$cat_id' AND owner_uid = " . 
						$_SESSION["uid"]);
					return;
				break;

				case "catsortreset":
					db_query($link, "UPDATE ttrss_feed_categories 
							SET order_id = 0 WHERE owner_uid = " . $_SESSION["uid"]);
					return;
				break;

				case "catsort":
					$corder = db_escape_string($_GET["corder"]);

					$cats = split(",", $corder);

					for ($i = 0; $i < count($cats); $i++) {
						$cat_id = $cats[$i];

						if ($cat_id > 0) {
							db_query($link, "UPDATE ttrss_feed_categories 
								SET order_id = '$i' WHERE id = '$cat_id' AND
								owner_uid = " . $_SESSION["uid"]);
						}
					}

					return;
				break;

			}

			outputFeedList($link, $tags);
		break; // feeds

		case "view":

			$id = db_escape_string($_GET["id"]);
			$feed_id = db_escape_string($_GET["feed"]);
			$cids = split(",", db_escape_string($_GET["cids"]));
			$mode = db_escape_string($_GET["mode"]);
			$omode = db_escape_string($_GET["omode"]);

			$csync = $_GET["csync"];

			print "<reply>";

			// in prefetch mode we only output requested cids, main article 
			// just gets marked as read (it already exists in client cache)

			if ($mode == "") {
				outputArticleXML($link, $id, $feed_id);
			} else if ($mode == "zoom") {
				outputArticleXML($link, $id, $feed_id, true, true);
			} else {
				catchupArticleById($link, $id, 0);
				ccache_update($link, $feed_id, $_SESSION["uid"]);
			}

			if (!$_SESSION["bw_limit"]) {
				foreach ($cids as $cid) {
					if ($cid) {
						outputArticleXML($link, $cid, $feed_id, false);
					}
				}
			}


//			if (get_pref($link, "SYNC_COUNTERS") || ($mode == "prefetch" && $csync)) {
				print "<counters>";
				getAllCounters($link, $omode);
				print "</counters>";
//			}

			print "</reply>";
		break; // view

		case "viewfeed":

			$print_exec_time = true;
			$timing_info = getmicrotime();

			print "<reply>";

			if ($_GET["debug"]) $timing_info = print_checkpoint("0", $timing_info);

			$omode = db_escape_string($_GET["omode"]);

			$feed = db_escape_string($_GET["feed"]);
			$subop = db_escape_string($_GET["subop"]);
			$view_mode = db_escape_string($_GET["view_mode"]);
			$limit = db_escape_string($_GET["limit"]);
			$cat_view = db_escape_string($_GET["cat"]);
			$next_unread_feed = db_escape_string($_GET["nuf"]);
			$offset = db_escape_string($_GET["skip"]);
			$vgroup_last_feed = db_escape_string($_GET["vgrlf"]);
			$csync = $_GET["csync"];
			$order_by = db_escape_string($_GET["order_by"]);

			ccache_update($link, $feed, $_SESSION["uid"]);

			set_pref($link, "_DEFAULT_VIEW_MODE", $view_mode);
			set_pref($link, "_DEFAULT_VIEW_LIMIT", $limit);
			set_pref($link, "_DEFAULT_VIEW_ORDER_BY", $order_by);

			if (!$cat_view && preg_match("/^[0-9][0-9]*$/", $feed)) {
				db_query($link, "UPDATE ttrss_feeds SET last_viewed = NOW()
					WHERE id = '$feed' AND owner_uid = ".$_SESSION["uid"]);
			}

			print "<headlines id=\"$feed\"><![CDATA[";

			if ($feed == -4) {

				generate_dashboard_feed($link);

			} else {
				
				$override_order = false;

				switch ($order_by) {
					case "date":
						if (get_pref($link, 'REVERSE_HEADLINES', $owner_uid)) {
							$override_order = "updated";
						} else {	
							$override_order = "updated DESC";
						}
						break;

					case "title":
						$override_order = "updated DESC";
						break;

					case "score":
						$override_order = "score DESC";
						break;
				}

				$ret = outputHeadlinesList($link, $feed, $subop, 
					$view_mode, $limit, $cat_view, $next_unread_feed, $offset, 
					$vgroup_last_feed, $override_order);
	
				$topmost_article_ids = $ret[0];
				$headlines_count = $ret[1];
				$returned_feed = $ret[2];
				$disable_cache = $ret[3];
				$vgroup_last_feed = $ret[4];
	
				print "]]></headlines>";
	
				print "<headlines-count value=\"$headlines_count\"/>";
				print "<vgroup-last-feed value=\"$vgroup_last_feed\"/>";

				$headlines_unread = ccache_find($link, $returned_feed, $_SESSION["uid"]);
	
				print "<headlines-unread value=\"$headlines_unread\"/>";
				printf("<disable-cache value=\"%d\"/>", $disable_cache);
	
				if ($_GET["debug"]) $timing_info = print_checkpoint("10", $timing_info);
	
				if (is_array($topmost_article_ids) && !get_pref($link, 'COMBINED_DISPLAY_MODE') && !$_SESSION["bw_limit"]) {
					print "<articles>";
					foreach ($topmost_article_ids as $id) {
						outputArticleXML($link, $id, $feed, false);
					}
					print "</articles>";
				}
			}

			if ($_GET["debug"]) $timing_info = print_checkpoint("20", $timing_info);

			$viewfeed_ctr_interval = 300;

			if ($csync || $_SESSION["bw_limit"]) {
				$viewfeed_ctr_interval = 60;
			}

//			if (get_pref($link, "SYNC_COUNTERS") ||				
//					time() - $_SESSION["get_all_counters_stamp"] > $viewfeed_ctr_interval) {
				print "<counters>";
				getAllCounters($link, $omode, $feed);
				print "</counters>";
//			}

			if ($_GET["debug"]) $timing_info = print_checkpoint("30", $timing_info);

			print_runtime_info($link);

			print "</reply>";
		break; // viewfeed

		case "pref-feeds":
			module_pref_feeds($link);
		break; // pref-feeds

		case "pref-filters":
			module_pref_filters($link);
		break; // pref-filters

		case "pref-labels":
			module_pref_labels($link);
		break; // pref-labels

		case "pref-prefs":
			module_pref_prefs($link);
		break; // pref-prefs

		case "pref-users":
			module_pref_users($link);
		break; // prefs-users

		case "help":
			module_help($link);
		break; // help

		case "dlg":
			module_popup_dialog($link);
		break; // dlg

		case "pref-pub-items":
			module_pref_pub_items($link);
		break; // pref-pub-items

		case "globalUpdateFeeds":
			// update feeds of all users, may be used anonymously

			print "<!--";
			// Update all feeds needing a update.
			update_daemon_common($link, 0, true, true);
			print " -->";

			// FIXME : old feed update way. To be removed.
			//$result = db_query($link, "SELECT id FROM ttrss_users");

			//while ($line = db_fetch_assoc($result)) {
			//	$user_id = $line["id"];
			//	print "<!-- updating feeds of uid $user_id -->";
			//	update_all_feeds($link, false, $user_id);
			//}

			print "<rpc-reply>
				<message msg=\"All feeds updated\"/>
			</rpc-reply>";
		break; // globalUpdateFeeds

		case "pref-feed-browser":
			module_pref_feed_browser($link);
		break; // pref-feed-browser

		case "publish":
			$key = db_escape_string($_GET["key"]);
			$limit = (int)db_escape_string($_GET["limit"]);

			$result = db_query($link, "SELECT login, owner_uid 
				FROM ttrss_user_prefs, ttrss_users WHERE
				pref_name = '_PREFS_PUBLISH_KEY' AND 
				value = '$key' AND 
				ttrss_users.id = owner_uid");

			if (db_num_rows($result) == 1) {
				$owner = db_fetch_result($result, 0, "owner_uid");
				$login = db_fetch_result($result, 0, "login");

				generate_syndicated_feed($link, $owner, -2, false, $limit);

			} else {
				print "<error>User not found</error>";
			}
		break; // publish

		case "rss":
			$feed = db_escape_string($_GET["id"]);
			$user = db_escape_string($_GET["user"]);
			$pass = db_escape_string($_GET["pass"]);
			$is_cat = $_GET["is_cat"] != false;
			$limit = (int)db_escape_string($_GET["limit"]);

			$search = db_escape_string($_GET["q"]);
			$match_on = db_escape_string($_GET["m"]);
			$search_mode = db_escape_string($_GET["smode"]);

			if (!$_SESSION["uid"] && $user && $pass) {
				authenticate_user($link, $user, $pass);
			}

			if ($_SESSION["uid"] ||
				http_authenticate_user($link)) {

					generate_syndicated_feed($link, 0, $feed, $is_cat, $limit,
						$search, $search_mode, $match_on);
			}
		break; // rss

		case "labelFromSearch":
			$search = db_escape_string($_GET["search"]);
			$search_mode = db_escape_string($_GET["smode"]);
			$match_on = db_escape_string($_GET["match"]);
			$is_cat = db_escape_string($_GET["is_cat"]);
			$title = db_escape_string($_GET["title"]);
			$feed = sprintf("%d", $_GET["feed"]);

			$label_qparts = array();

			$search_expr = getSearchSql($search, $match_on);

			if ($is_cat) {
				if ($feed != 0) {
					$search_expr .= " AND ttrss_feeds.cat_id = $feed ";
				} else {
					$search_expr .= " AND ttrss_feeds.cat_id IS NULL ";
				}
			} else {
				if ($search_mode == "all_feeds") {
					// NOOP
				} else if ($search_mode == "this_cat") {

					$tmp_result = db_query($link, "SELECT cat_id
						FROM ttrss_feeds WHERE id = '$feed'");

					$cat_id = db_fetch_result($tmp_result, 0, "cat_id");

					if ($cat_id > 0) {
						$search_expr .= " AND ttrss_feeds.cat_id = $cat_id ";
					} else {
						$search_expr .= " AND ttrss_feeds.cat_id IS NULL ";
					}
				} else {
					$search_expr .= " AND ttrss_feeds.id = $feed ";
				}

			}

			$search_expr = db_escape_string($search_expr);

			print $search_expr;

			if ($title) {
				$result = db_query($link,
					"INSERT INTO ttrss_labels (sql_exp,description,owner_uid) 
					VALUES ('$search_expr', '$title', '".$_SESSION["uid"]."')");
			}
		break; // labelFromSearch

		case "getUnread":
			$login = db_escape_string($_GET["login"]);

			header("Content-Type: text/plain; charset=utf-8");

			$result = db_query($link, "SELECT id FROM ttrss_users WHERE login = '$login'");

			if (db_num_rows($result) == 1) {
				$uid = db_fetch_result($result, 0, "id");
				print getGlobalUnread($link, $uid);
			} else {
				print "-1;User not found";
			}

			$print_exec_time = false;
		break; // getUnread

		case "digestTest":
			header("Content-Type: text/plain");
			print_r(prepare_headlines_digest($link, $_SESSION["uid"]));
			$print_exec_time = false;
		break; // digestTest

		case "digestSend":
			header("Content-Type: text/plain");
			send_headlines_digests($link);
			$print_exec_time = false;
		break; // digestSend

	} // Select action according to $op value.

	// We close the connection to database.
	db_close($link);
?>

<?php if ($print_exec_time) { ?>
<!-- <?php echo sprintf("Backend execution time: %.4f seconds", getmicrotime() - $script_started) ?> -->
<?php } ?>

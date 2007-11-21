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

	if (DB_TYPE == "pgsql") {
		pg_query("set client_encoding = 'UTF-8'");
		pg_set_client_encoding("UNICODE");
	} else {
		if (defined('MYSQL_CHARSET') && MYSQL_CHARSET) {
			db_query($link, "SET NAMES " . MYSQL_CHARSET);
//			db_query($link, "SET CHARACTER SET " . MYSQL_CHARSET);
		}
	}

	$op = $_REQUEST["op"];

	$print_exec_time = false;

	if ((!$op || $op == "rpc" || $op == "rss" || $op == "view" || 
			$op == "digestSend" || $op == "viewfeed" || $op == "publish" ||
			$op == "globalUpdateFeeds") && !$_REQUEST["noxml"]) {
		header("Content-Type: application/xml; charset=utf-8");
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
		0   => __("Use default"),
		-1  => __("Disable updates"),
		15  => __("Each 15 minutes"),
		30  => __("Each 30 minutes"),
		60  => __("Hourly"),
		240 => __("Each 4 hours"),
		720 => __("Each 12 hours"),
		1440 => __("Daily"),
		10080 => __("Weekly"));


	$access_level_names = array(
		0 => __("User"), 
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

	if ($op == "rpc") {
		handle_rpc_request($link);
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

		$id = db_escape_string($_GET["id"]);
		$feed_id = db_escape_string($_GET["feed"]);
		$cids = split(",", db_escape_string($_GET["cids"]));
		$mode = db_escape_string($_GET["mode"]);
		$omode = db_escape_string($_GET["omode"]);

		print "<reply>";

		// in prefetch mode we only output requested cids, main article 
		// just gets marked as read (it already exists in client cache)

		if ($mode == "") {
			outputArticleXML($link, $id, $feed_id);
		} else {
			catchupArticleById($link, $id, 0);
		}

		foreach ($cids as $cid) {
			if ($cid) {
				outputArticleXML($link, $cid, $feed_id, false);
			}
		}

		if ($mode != "prefetch_old") {
			print "<counters>";
			getAllCounters($link, $omode);
			print "</counters>";
		}

		print "</reply>";
	}

	if ($op == "viewfeed") {

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

		set_pref($link, "_DEFAULT_VIEW_MODE", $view_mode);
		set_pref($link, "_DEFAULT_VIEW_LIMIT", $limit);

		print "<headlines id=\"$feed\"><![CDATA[";

		$ret = outputHeadlinesList($link, $feed, $subop, 
			$view_mode, $limit, $cat_view, $next_unread_feed, $offset);

		$topmost_article_ids = $ret[0];
		$headlines_count = $ret[1];

		print "]]></headlines>";

		print "<headlines-count value=\"$headlines_count\"/>";

		$headlines_unread = getFeedUnread($link, $feed);

		print "<headlines-unread value=\"$headlines_unread\"/>";

		if ($_GET["debug"]) $timing_info = print_checkpoint("10", $timing_info);

		if (is_array($topmost_article_ids) && !get_pref($link, 'COMBINED_DISPLAY_MODE')) {
			print "<articles>";
			foreach ($topmost_article_ids as $id) {
				outputArticleXML($link, $id, $feed, false);
			}
			print "</articles>";
		}

		if ($_GET["debug"]) $timing_info = print_checkpoint("20", $timing_info);

		print "<counters>";
		getAllCounters($link, $omode, $feed);
		print "</counters>";

		if ($_GET["debug"]) $timing_info = print_checkpoint("30", $timing_info);

		print_runtime_info($link);

		print "</reply>";
	}

	if ($op == "pref-feeds") {
		module_pref_feeds($link);
	}

	if ($op == "pref-filters") {
		module_pref_filters($link);
	}

	if ($op == "pref-labels") {
		module_pref_labels($link);
	}

	if ($op == "pref-prefs") {
		module_pref_prefs($link);
	}

	if ($op == "pref-users") {
		module_pref_users($link);
	}

	if ($op == "help") {
		module_help($link);
	}

	if ($op == "dlg") {
		module_popup_dialog($link);
	}

	if ($op == "pref-pub-items") {
		module_pref_pub_items($link);
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

		print "<div id=\"infoBoxTitle\">User details</div>";

		print "<div class='infoBoxContents'>";

		$result = db_query($link, "SELECT login,
			SUBSTRING(last_login,1,16) AS last_login,
			access_level,
			(SELECT COUNT(int_id) FROM ttrss_user_entries 
				WHERE owner_uid = id) AS stored_articles,
			SUBSTRING(created,1,16) AS created
			FROM ttrss_users 
			WHERE id = '$uid'");
			
		if (db_num_rows($result) == 0) {
			print "<h1>User not found</h1>";
			return;
		}
		
#		print "<h1>User Details</h1>";

		$login = db_fetch_result($result, 0, "login");

#		print "<h1>$login</h1>";

		print "<table width='100%'>";

		$last_login = date(get_pref($link, 'LONG_DATE_FORMAT'),
			strtotime(db_fetch_result($result, 0, "last_login")));

		$created = date(get_pref($link, 'LONG_DATE_FORMAT'),
			strtotime(db_fetch_result($result, 0, "created")));

		$access_level = db_fetch_result($result, 0, "access_level");
		$stored_articles = db_fetch_result($result, 0, "stored_articles");

#		print "<tr><td>Username</td><td>$login</td></tr>";
#		print "<tr><td>Access level</td><td>$access_level</td></tr>";
		print "<tr><td>".__('Registered')."</td><td>$created</td></tr>";
		print "<tr><td>".__('Last logged in')."</td><td>$last_login</td></tr>";
		print "<tr><td>".__('Stored articles')."</td><td>$stored_articles</td></tr>";

		$result = db_query($link, "SELECT COUNT(id) as num_feeds FROM ttrss_feeds
			WHERE owner_uid = '$uid'");

		$num_feeds = db_fetch_result($result, 0, "num_feeds");

		print "<tr><td>".__('Subscribed feeds count')."</td><td>$num_feeds</td></tr>";

/*		$result = db_query($link, "SELECT 
			SUM(LENGTH(content)+LENGTH(title)+LENGTH(link)+LENGTH(guid)) AS db_size 
			FROM ttrss_user_entries,ttrss_entries 
				WHERE owner_uid = '$uid' AND ref_id = id");

		$db_size = round(db_fetch_result($result, 0, "db_size") / 1024);

		print "<tr><td>Approx. used DB size</td><td>$db_size KBytes</td></tr>";  */

		print "</table>";

		print "<h1>".__('Subscribed feeds')."</h1>";

		$result = db_query($link, "SELECT id,title,site_url FROM ttrss_feeds
			WHERE owner_uid = '$uid' ORDER BY title");

		print "<ul class=\"userFeedList\">";

		$row_class = "odd";

		while ($line = db_fetch_assoc($result)) {

			$icon_file = ICONS_URL."/".$line["id"].".ico";

			if (file_exists($icon_file) && filesize($icon_file) > 0) {
				$feed_icon = "<img class=\"tinyFeedIcon\" src=\"$icon_file\">";
			} else {
				$feed_icon = "<img class=\"tinyFeedIcon\" src=\"images/blank_icon.gif\">";
			}

			print "<li class=\"$row_class\">$feed_icon&nbsp;<a href=\"".$line["site_url"]."\">".$line["title"]."</a></li>";

			$row_class = toggleEvenOdd($row_class);

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

	if ($op == "pref-feed-browser") {
		module_pref_feed_browser($link);
	}

	if ($op == "publish") {
		$key = db_escape_string($_GET["key"]);

		$result = db_query($link, "SELECT login, owner_uid 
			FROM ttrss_user_prefs, ttrss_users WHERE
			pref_name = '_PREFS_PUBLISH_KEY' AND 
			value = '$key' AND 
			ttrss_users.id = owner_uid");

		if (db_num_rows($result) == 1) {
			$owner = db_fetch_result($result, 0, "owner_uid");
			$login = db_fetch_result($result, 0, "login");

			generate_syndicated_feed($link, $owner, -2, false);

		} else {
			print "<error>User not found</error>";
		}

	}

	if ($op == "rss") {
		$feed = db_escape_string($_GET["id"]);
		$user = db_escape_string($_GET["user"]);
		$pass = db_escape_string($_GET["pass"]);
		$is_cat = $_GET["is_cat"] != false;

		$search = db_escape_string($_GET["q"]);
		$match_on = db_escape_string($_GET["m"]);
		$search_mode = db_escape_string($_GET["smode"]);

		if (!$_SESSION["uid"] && $user && $pass) {
			authenticate_user($link, $user, $pass);
		}

		if ($_SESSION["uid"] ||
			http_authenticate_user($link)) {

				generate_syndicated_feed($link, 0, $feed, $is_cat, 
					$search, $search_mode, $match_on);
		}

	}

	if ($op == "labelFromSearch") {
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
	}

	if ($op == "getUnread") {
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
	}

	if ($op == "digestTest") {
		header("Content-Type: text/plain");
		print_r(prepare_headlines_digest($link, $_SESSION["uid"]));
		$print_exec_time = false;

	}

	if ($op == "digestSend") {
		header("Content-Type: text/plain");
		send_headlines_digests($link);
		$print_exec_time = false;

	}

	db_close($link);
?>

<?php if ($print_exec_time) { ?>
<!-- <?php echo sprintf("Backend execution time: %.4f seconds", getmicrotime() - $script_started) ?> -->
<?php } ?>

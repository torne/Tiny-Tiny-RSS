<?php
	require_once "sessions.php";
	require_once "modules/backend-rpc.php";
	
	header("Cache-Control: no-cache, must-revalidate");
	header("Pragma: no-cache");
	header("Expires: -1");
	
/*	if ($_GET["debug"]) {
		define('DEFAULT_ERROR_LEVEL', E_ALL);
	} else {
		define('DEFAULT_ERROR_LEVEL', E_ERROR | E_WARNING | E_PARSE);
	}
	
	error_reporting(DEFAULT_ERROR_LEVEL); */

	$op = $_REQUEST["op"];

	define('SCHEMA_VERSION', 11);

	require_once "sanity_check.php";
	require_once "config.php";
	
	require_once "db.php";
	require_once "db-prefs.php";
	require_once "functions.php";

	$print_exec_time = false;

	if ((!$op || $op == "rpc" || $op == "rss" || $op == "digestSend" ||
			$op == "globalUpdateFeeds") && !$_REQUEST["noxml"]) {
		header("Content-Type: application/xml");
	}

	if (!$op) {
		header("Content-Type: application/xml");
		print_error_xml(7); exit;
	}

	if (!$_SESSION["uid"] && $op != "globalUpdateFeeds" && $op != "rss" && $op != "getUnread") {

		if ($op == "rpc") {
			print_error_xml(6); die;
		} else {
			print "
			<html><body>
				<p>Error: Not logged in.</p>
				<script type=\"text/javascript\">
					if (parent.window != 'undefined') {
						parent.window.location = \"login.php\";		
					} else {
						window.location = \"login.php\";
					}
				</script>
			</body></html>
			";
		}
		exit;
	}

	$purge_intervals = array(
		0  => "Use default",
		-1 => "Never purge",
		5  => "1 week old",
		14 => "2 weeks old",
		31 => "1 month old",
		60 => "2 months old",
		90 => "3 months old");

	$update_intervals = array(
		0   => "Use default",
		-1  => "Disable updates",
		30  => "Each 30 minutes",
		60  => "Hourly",
		240 => "Each 4 hours",
		720 => "Each 12 hours",
		1440 => "Daily",
		10080 => "Weekly");

	$access_level_names = array(
		0 => "User", 
		10 => "Administrator");

	require_once "modules/pref-prefs.php";
	require_once "modules/popup-dialog.php";
	require_once "modules/help.php";
	require_once "modules/pref-feeds.php";
	require_once "modules/pref-filters.php";
	require_once "modules/pref-labels.php";
	require_once "modules/pref-users.php";
	require_once "modules/pref-feed-browser.php"; 

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

		$result = db_query($link, "SELECT rtl_content FROM ttrss_feeds
			WHERE id = '$feed_id' AND owner_uid = " . $_SESSION["uid"]);

		if (db_num_rows($result) == 1) {
			$rtl_content = sql_bool_to_bool(db_fetch_result($result, 0, "rtl_content"));
		} else {
			$rtl_content = false;
		}

		if ($rtl_content) {
			$rtl_tag = "dir=\"RTL\"";
			$rtl_class = "RTL";
		} else {
			$rtl_tag = "";
			$rtl_class = "";
		}

		$result = db_query($link, "UPDATE ttrss_user_entries 
			SET unread = false,last_read = NOW() 
			WHERE ref_id = '$id' AND feed_id = '$feed_id' AND owner_uid = " . $_SESSION["uid"]);

		$result = db_query($link, "SELECT title,link,content,feed_id,comments,int_id,
			SUBSTRING(updated,1,16) as updated,
			(SELECT icon_url FROM ttrss_feeds WHERE id = feed_id) as icon_url,
			num_comments,
			author
			FROM ttrss_entries,ttrss_user_entries
			WHERE	id = '$id' AND ref_id = id AND owner_uid = " . $_SESSION["uid"]);

		if ($result) {

			$link_target = "";

			if (get_pref($link, 'OPEN_LINKS_IN_NEW_WINDOW')) {
				$link_target = "target=\"_new\"";
			}

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
				$entry_comments = "<a $link_target href=\"$comments_url\">$num_comments comments</a>";
			} else {
				if ($line["comments"] && $line["link"] != $line["comments"]) {
					$entry_comments = "<a $link_target href=\"".$line["comments"]."\">comments</a>";
				}				
			}

			print "<div class=\"postReply\">";

			print "<div class=\"postHeader\"><table width=\"100%\">";

			$entry_author = $line["author"];

			if ($entry_author) {
				$entry_author = " - by $entry_author";
			}

			if ($line["link"]) {
				print "<tr><td width='70%'><a $link_target href=\"" . $line["link"] . "\">" . 
					$line["title"] . "</a>$entry_author</td>";
			} else {
				print "<tr><td width='30%'>" . $line["title"] . "$entry_author</td>";
			}

			$parsed_updated = date(get_pref($link, 'LONG_DATE_FORMAT'), 
				strtotime($line["updated"]));
		
			print "<td class=\"postDate$rtl_class\">$parsed_updated</td>";
						
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

			if (get_pref($link, 'OPEN_LINKS_IN_NEW_WINDOW')) {
				$line["content"] = preg_replace("/href=/i", "target=\"_new\" href=", $line["content"]);
			}

			$line["content"] = sanitize_rss($line["content"]);

			print $line["content"] . "</div>";
			
			print "</div>";

		}
	}

	if ($op == "viewfeed") {

		$feed = db_escape_string($_GET["feed"]);
		$subop = db_escape_string($_GET["subop"]);
		$view_mode = db_escape_string($_GET["view_mode"]);
		$limit = db_escape_string($_GET["limit"]);
		$cat_view = db_escape_string($_GET["cat"]);
		$next_unread_feed = db_escape_string($_GET["nuf"]);

		if ($subop == "undefined") $subop = "";

		if ($subop == "CatchupSelected") {
			$ids = split(",", db_escape_string($_GET["ids"]));
			$cmode = sprintf("%d", $_GET["cmode"]);

			catchupArticlesById($link, $ids, $cmode);
		}

		if ($subop == "ForceUpdate" && sprintf("%d", $feed) > 0) {
			update_generic_feed($link, $feed, $cat_view);
		}

		if ($subop == "MarkAllRead")  {
			catchup_feed($link, $feed, $cat_view);

			if (get_pref($link, 'ON_CATCHUP_SHOW_NEXT_FEED')) {
				if ($next_unread_feed) {
					$feed = $next_unread_feed;
				}
			}
		}

		if ($feed_id > 0) {		
			$result = db_query($link,
				"SELECT id FROM ttrss_feeds WHERE id = '$feed' LIMIT 1");
		
			if (db_num_rows($result) == 0) {
				print "<div align='center'>
					Feed not found.</div>";
				return;
			}
		}

		if (preg_match("/^-?[0-9][0-9]*$/", $feed) != false) {
	
			$result = db_query($link, "SELECT rtl_content FROM ttrss_feeds
				WHERE id = '$feed' AND owner_uid = " . $_SESSION["uid"]);

			if (db_num_rows($result) == 1) {
				$rtl_content = sql_bool_to_bool(db_fetch_result($result, 0, "rtl_content"));
			} else {
				$rtl_content = false;
			}
	
			if ($rtl_content) {
				$rtl_tag = "dir=\"RTL\"";
			} else {
				$rtl_tag = "";
			}
		} else {
			$rtl_tag = "";
			$rtl_content = false;
		}

		$script_dt_add = get_script_dt_add();

/*		print "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\">	
			<script type=\"text/javascript\" src=\"prototype.js\"></script>
			<script type=\"text/javascript\" src=\"functions.js?$script_dt_add\"></script>
			<script type=\"text/javascript\" src=\"viewfeed.js?$script_dt_add\"></script>
			<!--[if gte IE 5.5000]>
			<script type=\"text/javascript\" src=\"pngfix.js\"></script>
			<link rel=\"stylesheet\" type=\"text/css\" href=\"tt-rss-ie.css\">
			<![endif]-->
			</head><body $rtl_tag>
			<script type=\"text/javascript\">
			if (document.addEventListener) {
				document.addEventListener(\"DOMContentLoaded\", init, null);
			}
			window.onload = init;
			</script>"; */

		/// START /////////////////////////////////////////////////////////////////////////////////

		$search = db_escape_string($_GET["query"]);
		$search_mode = db_escape_string($_GET["search_mode"]);
		$match_on = db_escape_string($_GET["match_on"]);

		if (!$match_on) {
			$match_on = "both";
		}

		$qfh_ret = queryFeedHeadlines($link, $feed, $limit, $view_mode, $cat_view, $search, $search_mode, $match_on);

		$result = $qfh_ret[0];
		$feed_title = $qfh_ret[1];
		$feed_site_url = $qfh_ret[2];
		$last_error = $qfh_ret[3];
		
		/// STOP //////////////////////////////////////////////////////////////////////////////////

		print "<div id=\"headlinesContainer\" $rtl_tag>";

		if (!$result) {
			print "<div align='center'>
				Could not display feed (query failed). Please check label match syntax or local configuration.</div>";
			return;
		}
	
		if (db_num_rows($result) > 0) {

			print_headline_subtoolbar($link, $feed_site_url, $feed_title, false, 
				$rtl_content, $feed, $cat_view, $search, $match_on, $search_mode);

			print "<div id=\"headlinesInnerContainer\">";

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

#				$content_link = "<a target=\"_new\" href=\"".$line["link"]."\">" .
#					$line["title"] . "</a>";

				$content_link = "<a href=\"javascript:view($id,$feed_id);\">" .
					$line["title"] . "</a>";

#				$content_link = "<a href=\"javascript:viewContentUrl('".$line["link"]."');\">" .
#					$line["title"] . "</a>";

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

						if (get_pref($link, 'SHOW_CONTENT_PREVIEW') && !$rtl_tag) {
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

					print "<div style=\"float : right\">$updated_fmt,
						<a class=\"cdmToggleLink\"
							href=\"javascript:toggleUnread($id)\">Toggle unread</a>
					</div>";
					
					print "<a class=\"title\" 
						onclick=\"javascript:toggleUnread($id, 0)\"
						target=\"new\" href=\"".$line["link"]."\">".$line["title"]."</a>";

					if ($line["feed_title"]) {	
						print "&nbsp;(<a href='javascript:viewfeed($feed_id)'>".$line["feed_title"]."</a>)";
					}

					print "</div>";

					print "<div class=\"cdmContent\">" . $line["content_preview"] . "</div><br clear=\"all\">";

					print "<div style=\"float : right\">$marked_pic</div>
						<div lass=\"cdmFooter\">
							<input type=\"checkbox\" onclick=\"toggleSelectRowById(this, 
							'RROW-$id')\" class=\"feedCheckBox\" id=\"RCHK-$id\"></div>";

#					print "<div align=\"center\"><a class=\"cdmToggleLink\"
#							href=\"javascript:toggleUnread($id)\">
#							Toggle unread</a></div>";

					print "</div>";	

				}				
	
				++$lnum;
			}

			if (!get_pref($link, 'COMBINED_DISPLAY_MODE')) {			
				print "</table>";
			}

			print "</div>";

//			print_headline_subtoolbar($link, 
//				"javascript:catchupPage()", "Mark page as read", true, $rtl_content);


		} else {
			print "<div class='whiteBox'>No articles found.</div>";
		}

		print "</div>";
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
				WHERE owner_uid = id) AS stored_articles
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
		$access_level = db_fetch_result($result, 0, "access_level");
		$stored_articles = db_fetch_result($result, 0, "stored_articles");

#		print "<tr><td>Username</td><td>$login</td></tr>";
#		print "<tr><td>Access level</td><td>$access_level</td></tr>";
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

				generate_syndicated_feed($link, $feed, $is_cat, 
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

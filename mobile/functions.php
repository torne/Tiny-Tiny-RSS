<?

	function render_feeds_list($link) {

		$tags = $_GET["tags"];

		print "<div id=\"heading\">";

		if ($tags) {
			print "Tags <span id=\"headingAddon\">
				(<a href=\"tt-rss.php\">View feeds</a>, ";
		} else {
			print "Feeds <span id=\"headingAddon\">
				(<a href=\"tt-rss.php?tags=1\">View tags</a>, ";
		}
			
		print "<a href=\"logout.php\">Logout</a>)</span>";
		print "</div>";

		print "<ul class=\"feedList\">";

		$owner_uid = $_SESSION["uid"];

		if (!$tags) {

			/* virtual feeds */

			if (get_pref($link, 'ENABLE_FEED_CATS')) {
				print "<li class=\"feedCat\">Special</li>";
				print "<li class=\"feedCatHolder\"><ul class=\"feedCatList\">";
			}

			$result = db_query($link, "SELECT count(id) as num_starred 
				FROM ttrss_entries,ttrss_user_entries 
				WHERE marked = true AND 
				ttrss_user_entries.ref_id = ttrss_entries.id AND
				unread = true AND owner_uid = '$owner_uid'");
			$num_starred = db_fetch_result($result, 0, "num_starred");

			$class = "virt";

			if ($num_starred > 0) $class .= "Unread";

			printMobileFeedEntry(-1, $class, "Starred articles", $num_starred, 
				"../images/mark_set.png", $link);

			if (get_pref($link, 'ENABLE_FEED_CATS')) {
				print "</ul>";
			}

			if (GLOBAL_ENABLE_LABELS && get_pref($link, 'ENABLE_LABELS')) {
	
				$result = db_query($link, "SELECT id,sql_exp,description FROM
					ttrss_labels WHERE owner_uid = '$owner_uid' ORDER by description");
		
				if (db_num_rows($result) > 0) {
					if (get_pref($link, 'ENABLE_FEED_CATS')) {
						print "<li class=\"feedCat\">Labels</li>";
						print "<li class=\"feedCatHolder\"><ul class=\"feedCatList\">";
					} else {
//						print "<li><hr></li>";
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
	
					printMobileFeedEntry(-$line["id"]-11, 
						$class, $line["description"], $count, "../images/label.png", $link);
		
				}

				if (db_num_rows($result) > 0) {
					if (get_pref($link, 'ENABLE_FEED_CATS')) {
						print "</ul>";
					}
				}

			}

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
	
			$category = "";
	
			while ($line = db_fetch_assoc($result)) {
			
				$feed = db_unescape_string($line["title"]);
				$feed_id = $line["id"];	  
	
				$subop = $_GET["subop"];
				
				$total = $line["total"];
				$unread = $line["unread"];

				$rtl_content = sql_bool_to_bool($line["rtl_content"]);

				if ($rtl_content) {
					$rtl_tag = "dir=\"RTL\"";
				} else {
					$rtl_tag = "";
				}

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
						$holder_class = "feedCatHolder";
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
					
					print "<li class=\"feedCat\">
						<a href=\"?subop=tc&id=$cat_id\">$tmp_category</a>
							<a href=\"?go=vf&id=$cat_id&cat=true\">
								<span class=\"$catctr_class\">($cat_unread 
									unread)$ellipsis</span></a></li>";

					print "<li id=\"feedCatHolder\" class=\"$holder_class\">
						<ul class=\"feedCatList\">";
				}
	
				printMobileFeedEntry($feed_id, $class, $feed, $unread, 
					"../icons/$feed_id.ico", $link, $rtl_content);
	
				++$lnum;
			}

		} else {
			// tags

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
	
				printMobileFeedEntry($tag, $class, $tag, $unread, 
					"../images/tag.png", $link);
	
			} 

			
		}	
	}

	function printMobileFeedEntry($feed_id, $class, $feed_title, $unread, $icon_file, $link,
		$rtl_content = false) {

		if (file_exists($icon_file) && filesize($icon_file) > 0) {
				$feed_icon = "<img src=\"$icon_file\">";
		} else {
			$feed_icon = "<img src=\"../images/blank_icon.gif\">";
		}

		if ($rtl_content) {
			$rtl_tag = "dir=\"rtl\"";
		} else {
			$rtl_tag = "dir=\"ltr\"";
		}

		$feed = "<a href=\"?go=vf&id=$feed_id\">$feed_title</a>";

		print "<li class=\"$class\">";
		if (get_pref($link, 'ENABLE_FEED_ICONS')) {
			print "$feed_icon";
		}

		print "<span $rtl_tag>$feed</span> ";

		if ($unread != 0) {
			print "<span $rtl_tag>($unread)</span>";
		}
		
		print "</li>";

	}

	function render_headlines($link) {

		$feed = db_escape_string($_GET["id"]);
		$limit = db_escape_string($_GET["limit"]);
		$view_mode = db_escape_string($_GET["viewmode"]);
		$cat_view = db_escape_string($_GET["cat"]);

		if (!$view_mode) $view_mode = "Adaptive";
		if (!$limit) $limit = 30;

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
			$rtl_content = false;
			$rtl_tag = "";
		}

		print "<div id=\"headlines\" $rtl_tag>";

		if ($subop == "ForceUpdate" && sprintf("%d", $feed) > 0) {
			update_generic_feed($link, $feed, $cat_view);
		}

		if ($subop == "MarkAllRead")  {
			catchup_feed($link, $feed, $cat_view);
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
		} else if (preg_match("/^-?[0-9][0-9]*$/", $feed) == false) {
			$query_strategy_part = "ttrss_entries.id > 0";
			$vfeed_query_part = "(SELECT title FROM ttrss_feeds WHERE
				id = feed_id) as feed_title,";
		} else if ($feed >= 0 && $search && $search_mode == "This category") {

			$vfeed_query_part = "ttrss_feeds.title AS feed_title,";		

			$tmp_result = db_query($link, "SELECT id 
				FROM ttrss_feeds WHERE cat_id = 
					(SELECT cat_id FROM ttrss_feeds WHERE id = '$feed') AND id != '$feed'");

			$cat_siblings = array();

			if (db_num_rows($tmp_result) > 0) {
				while ($p = db_fetch_assoc($tmp_result)) {
					array_push($cat_siblings, "feed_id = " . $p["id"]);
				}

				$query_strategy_part = sprintf("(feed_id = %d OR %s)", 
					$feed, implode(" OR ", $cat_siblings));

			} else {
				$query_strategy_part = "ttrss_entries.id > 0";
			}
			
		} else if ($feed >= 0) {

			if ($cat_view) {

				if ($feed > 0) {
					$query_strategy_part = "cat_id = '$feed'";
				} else {
					$query_strategy_part = "cat_id IS NULL";
				}

				$vfeed_query_part = "ttrss_feeds.title AS feed_title,";

			} else {		
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
		} else if ($search && preg_match('/^-?[0-9][0-9]*$/', $feed) == false) {
			$feed_title = "Feed search results ($search, $feed)";
		} else if (preg_match('/^-?[0-9][0-9]*$/', $feed) == false) {
			$feed_title = $feed;
		} else if (preg_match('/^-?[0-9][0-9]*$/', $feed) != false && $feed >= 0) {

			if ($cat_view) {

				if ($feed != 0) {			
					$result = db_query($link, "SELECT title FROM ttrss_feed_categories
						WHERE id = '$feed' AND owner_uid = " . $_SESSION["uid"]);
					$feed_title = db_fetch_result($result, 0, "title");
				} else {
					$feed_title = "Uncategorized";
				}
			} else {
				
				$result = db_query($link, "SELECT title,site_url,last_error FROM ttrss_feeds 
					WHERE id = '$feed' AND owner_uid = " . $_SESSION["uid"]);
	
				$feed_title = db_fetch_result($result, 0, "title");
				$feed_site_url = db_fetch_result($result, 0, "site_url");
				$last_error = db_fetch_result($result, 0, "last_error");

			}

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

		if (preg_match("/^-?[0-9][0-9]*$/", $feed) != false) {

			if ($feed >= 0) {
				$feed_kind = "Feeds";
			} else {
				$feed_kind = "Labels";
			}

			$query = "SELECT 
					ttrss_entries.id,ttrss_entries.title,
					SUBSTRING(updated,1,16) as updated,
					unread,feed_id,marked,link,last_read,
					SUBSTRING(last_read,1,19) as last_read_noms,
					$vfeed_query_part
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
				$limit_query_part";
				
			$result = db_query($link, $query);

			if ($_GET["debug"]) print $query;

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

		print "<div id=\"heading\">";
		if (!$cat_view && file_exists("../icons/$feed.ico") && filesize("../icons/$feed.ico") > 0) {
			print "<img class=\"feedIcon\" src=\"../icons/$feed.ico\">";
		}
		
		print "$feed_title <span id=\"headingAddon\">(";
		print "<a href=\"tt-rss.php\">Back</a>, ";
		print "<a href=\"tt-rss.php?go=vf&id=$feed&subop=ForceUpdate\">Update</a>, ";
		print "<a href=\"tt-rss.php?go=vf&id=$feed&subop=MarkAllRead\">Mark as read</a>";
		print ")</span>";
		
		print "</div>";
	
		if (db_num_rows($result) > 0) {

			print "<ul class=\"headlines\">";

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
					$marked_pic = "<img class='marked' src=\"../images/mark_set.png\">";
				} else {
					$marked_pic = "<img class='marked' src=\"../images/mark_unset.png\">";
				}
	
				$content_link = "<a href=\"?go=view&id=$id&feed=$feed_id\">" .
					$line["title"] . "</a>";

				if (get_pref($link, 'HEADLINES_SMART_DATE')) {
					$updated_fmt = smart_date_time(strtotime($line["updated"]));
				} else {
					$short_date = get_pref($link, 'SHORT_DATE_FORMAT');
					$updated_fmt = date($short_date, strtotime($line["updated"]));
				}				
				
				print "<li class='$class'>";

				print "<a href=\"?go=vf&id=$feed_id&ts=$id\">$marked_pic</a>";

				print $content_link;
	
				if ($line["feed_title"]) {			
					print " (<a href='?go=vf&id=$feed_id'>".
							$line["feed_title"]."</a>)";
				}

				print "<span class='hlUpdated'> &mdash; $updated_fmt</span>";

				print "</li>";

	
				++$lnum;
			}

			print "</ul>";

		} else {
			print "<div align='center'>No articles found.</div>";
		}

	}

	function render_article($link) {

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
			WHERE	id = '$id' AND ref_id = id");

		if ($result) {

			$line = db_fetch_assoc($result);

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

			$tmp_result = db_query($link, "SELECT DISTINCT tag_name FROM
				ttrss_tags WHERE post_int_id = " . $line["int_id"] . "
				ORDER BY tag_name");
	
			$tags_str = "";
			$f_tags_str = "";

			$num_tags = 0;

			while ($tmp_line = db_fetch_assoc($tmp_result)) {
				$num_tags++;
				$tag = $tmp_line["tag_name"];				
				$tag_str = "<a href=\"?go=vf&id=$tag\">$tag</a>, "; 
				$tags_str .= $tag_str;
			}

			$tags_str = preg_replace("/, $/", "", $tags_str);

			$parsed_updated = date(get_pref($link, 'SHORT_DATE_FORMAT'), 
				strtotime($line["updated"]));

			print "<div id=\"heading\">";

			if (file_exists("../icons/$feed_id.ico") && filesize("../icons/$feed_id.ico") > 0) {
				print "<img class=\"feedIcon\" src=\"../icons/$feed_id.ico\">";
			}

			$feed_link = "<a href=\"tt-rss.php?go=vf&id=$feed_id\">Feed</a>";
			
			print truncate_string($line["title"], 30);
			print " <span id=\"headingAddon\">$parsed_updated ($feed_link)</span>";
			print "</div>";

			if ($num_tags > 0) {
				print "<div class=\"postTags\">Tags: $tags_str</div>";
			}

			print $line["content"]; 
		
		}

		print "</body></html>";



	}

?>

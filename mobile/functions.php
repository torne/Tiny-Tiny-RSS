<?php
	define('MOBILE_FEEDLIST_ENABLE_ICONS', false);
	define('TTRSS_SESSION_NAME', 'ttrss_m_sid');

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

		print "<a href=\"tt-rss.php?go=sform\">Search</a>, ";

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

			$num_fresh = getFeedUnread($link, -3);

			$class = "virt";

			if ($num_fresh > 0) $class .= "Unread";

			printMobileFeedEntry(-3, $class, __("Fresh articles"), $num_fresh, 
				"../images/fresh.png", $link);

			$num_starred = getFeedUnread($link, -1);

			$class = "virt";

			if ($num_starred > 0) $class .= "Unread";

			printMobileFeedEntry(-1, $class, __("Starred articles"), $num_starred, 
				"../images/mark_set.png", $link);

			$class = "virt";

			$num_published = getFeedUnread($link, -2);

			if ($num_published > 0) $class .= "Unread";

			printMobileFeedEntry(-2, $class, __("Published articles"), $num_published, 
				"../images/pub_set.png", $link);

			if (get_pref($link, 'ENABLE_FEED_CATS')) {
				print "</ul>";
			}

			if (GLOBAL_ENABLE_LABELS && get_pref($link, 'ENABLE_LABELS')) {
	
				$result = db_query($link, "SELECT id,description FROM
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
		
					$count = getFeedUnread($link, -$line["id"]-11);
	
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
				SUBSTRING(last_updated,1,19) AS last_updated_noms,
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
					ttrss_feeds.hidden = false AND
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

					$cat_id = sprintf("%d", $cat_id);
					$cat_unread = getCategoryUnread($link, $cat_id);

					print "<li class=\"feedCat\">
						<a href=\"?subop=tc&id=$cat_id\">$tmp_category</a>
							<a href=\"?go=vf&id=$cat_id&cat=true\">
								<span class=\"$catctr_class\">($cat_unread)$ellipsis</span>
							</a></li>";

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
#		if (get_pref($link, 'ENABLE_FEED_ICONS')) {
#			print "$feed_icon";
#		}

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
		$subop = $_GET["subop"];
		$catchup_op = $_GET["catchup_op"];

		if (!$view_mode) $view_mode = "Adaptive";
		if (!$limit) $limit = 30;
		if (!$feed) $feed = 0;

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
			update_generic_feed($link, $feed, $cat_view, true);
		}

		if ($subop == "MarkAllRead" || $catchup_op == "feed")  {
			catchup_feed($link, $feed, $cat_view);
		}

		if ($catchup_op == "selection") {
			$ids_to_mark = array_keys($_GET["sel_ids"]);
			if ($ids_to_mark) {
				foreach ($ids_to_mark as $id) {
					db_query($link, "UPDATE ttrss_user_entries SET 
						unread = false,last_read = NOW()
						WHERE ref_id = '$id' AND owner_uid = " . $_SESSION["uid"]);
				}
			}
		}

		if ($subop == "MarkPageRead" || $catchup_op == "page") {
			$ids_to_mark = $_SESSION["last_page_ids.$feed"];

			if ($ids_to_mark) {

				foreach ($ids_to_mark as $id) {
					db_query($link, "UPDATE ttrss_user_entries SET 
						unread = false,last_read = NOW()
						WHERE ref_id = '$id' AND owner_uid = " . $_SESSION["uid"]);
				}
			}
		}


		/// START /////////////////////////////////////////////////////////////////////////////////

		$search = db_escape_string($_GET["query"]);
		$search_mode = db_escape_string($_GET["search_mode"]);
		$match_on = db_escape_string($_GET["match_on"]);

		if (!$match_on) {
			$match_on = "both";
		}

		$real_offset = $offset * $limit;

		if ($_GET["debug"]) $timing_info = print_checkpoint("H0", $timing_info);

		$qfh_ret = queryFeedHeadlines($link, $feed, $limit, $view_mode, $cat_view, 
			$search, $search_mode, $match_on, false, $real_offset);

		if ($_GET["debug"]) $timing_info = print_checkpoint("H1", $timing_info);

		$result = $qfh_ret[0];
		$feed_title = $qfh_ret[1];
		$feed_site_url = $qfh_ret[2];
		$last_error = $qfh_ret[3];
		
		/// STOP //////////////////////////////////////////////////////////////////////////////////

		if (!$result) {
			print "<div align='center'>
				Could not display feed (query failed). Please check label match syntax or local configuration.</div>";
			return;
		}

		print "<div id=\"heading\">";
		#		if (!$cat_view && file_exists("../icons/$feed.ico") && filesize("../icons/$feed.ico") > 0) {
			#			print "<img class=\"feedIcon\" src=\"../icons/$feed.ico\">";
			#		}
		
		print "$feed_title <span id=\"headingAddon\">(";
		print "<a href=\"tt-rss.php\">Back</a>, ";
		print "<a href=\"tt-rss.php?go=sform&aid=$feed&ic=$cat_view\">Search</a>, ";
		print "<a href=\"tt-rss.php?go=vf&id=$feed&subop=ForceUpdate\">Update</a>";
#		print "Mark as read: ";
#		print "<a href=\"tt-rss.php?go=vf&id=$feed&subop=MarkAsRead\">Page</a>, ";
#		print "<a href=\"tt-rss.php?go=vf&id=$feed&subop=MarkAllRead\">Feed</a>";

		print ")</span>";
		
		print "</div>";
	
		if (db_num_rows($result) > 0) {

			print "<form method=\"GET\" action=\"tt-rss.php\">";
			print "<input type=\"hidden\" name=\"go\" value=\"vf\">";
			print "<input type=\"hidden\" name=\"id\" value=\"$feed\">";

			print "<ul class=\"headlines\">";

			$page_art_ids = array();
			
			$lnum = 0;
	
			error_reporting (DEFAULT_ERROR_LEVEL);
	
			$num_unread = 0;
	
			while ($line = db_fetch_assoc($result)) {

				$class = ($lnum % 2) ? "even" : "odd";
	
				$id = $line["id"];
				$feed_id = $line["feed_id"];

				array_push($page_art_ids, $id);
	
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

				if ($line["published"] == "t" || $line["published"] == "1") {
					$published_pic = "<img class='marked' src=\"../images/pub_set.gif\">";
				} else {
					$published_pic = "<img class='marked' src=\"../images/pub_unset.gif\">";
				}

				$content_link = "<a href=\"?go=view&id=$id&ret_feed=$feed&feed=$feed_id\">" .
					$line["title"] . "</a>";

				if (get_pref($link, 'HEADLINES_SMART_DATE')) {
					$updated_fmt = smart_date_time(strtotime($line["updated"]));
				} else {
					$short_date = get_pref($link, 'SHORT_DATE_FORMAT');
					$updated_fmt = date($short_date, strtotime($line["updated"]));
				}				
				
				print "<li class='$class' id=\"HROW-$id\">";

				print "<input type=\"checkbox\" name=\"sel_ids[$id]\" 
					onchange=\"toggleSelectRow(this, $id)\">";

				print "<a href=\"?go=vf&id=$feed&ts=$id\">$marked_pic</a>";
				print "<a href=\"?go=vf&id=$feed&tp=$id\">$published_pic</a>";

				print $content_link;
	
				if ($line["feed_title"]) {			
					print " (<a href='?go=vf&id=$feed_id'>".
							$line["feed_title"]."</a>)";
				}

				print "<span class='hlUpdated'> ($updated_fmt)</span>";

				print "</li>";

	
				++$lnum;
			}

			print "</ul>";

			print "<div class='footerAddon'>";

			$_SESSION["last_page_ids.$feed"] = $page_art_ids;

/*			print "<a href=\"tt-rss.php?go=vf&id=$feed&subop=MarkPageRead\">Page</a>, ";
			print "<a href=\"tt-rss.php?go=vf&id=$feed&subop=MarkAllRead\">Feed</a></div>"; */

			print "<select name=\"catchup_op\">
				<option value=\"selection\">Selection</option>
				<option value=\"page\">Page</option>
				<option value=\"feed\">Entire feed</option>
			</select>
			<input type=\"submit\" value=\"Mark as read\">";				

			print "</form>";

		} else {
			print "<div align='center'>No articles found.</div>";
		}

	}

	function render_article($link) {

		$id = db_escape_string($_GET["id"]);
		$feed_id = db_escape_string($_GET["feed"]);
		$ret_feed_id = db_escape_string($_GET["ret_feed"]);

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
			marked,published,
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

			#			if (file_exists("../icons/$feed_id.ico") && filesize("../icons/$feed_id.ico") > 0) {
				#				print "<img class=\"feedIcon\" src=\"../icons/$feed_id.ico\">";
				#			}

			$feed_link = "<a href=\"tt-rss.php?go=vf&id=$ret_feed_id\">Feed</a>";
			
			print "<a href=\"" . $line["link"] . "\">" . 
				truncate_string($line["title"], 30) . "</a>";
			print " <span id=\"headingAddon\">$parsed_updated ($feed_link)</span>";
			print "</div>";

			if ($num_tags > 0) {
				print "<div class=\"postTags\">Tags: $tags_str</div>";
			}

			if ($line["marked"] == "t" || $line["marked"] == "1") {
				$marked_pic = "<img class='marked' src=\"../images/mark_set.png\">";
			} else {
				$marked_pic = "<img class='marked' src=\"../images/mark_unset.png\">";
			}

			if ($line["published"] == "t" || $line["published"] == "1") {
				$published_pic = "<img class='marked' src=\"../images/pub_set.gif\">";
			} else {
				$published_pic = "<img class='marked' src=\"../images/pub_unset.gif\">";
			}

			print "<div class=\"postStarOps\">";
			print "<a href=\"?go=view&id=$id&ret_feed=$ret_feed_id&feed=$feed_id&sop=ts\">$marked_pic</a>";
			print "<a href=\"?go=view&id=$id&ret_feed=$ret_feed_id&feed=$feed_id&sop=tp\">$published_pic</a>";
			print "</div>";

			print sanitize_rss($link, $line["content"], true);; 
		
		}

		print "</body></html>";
	}

	function render_search_form($link, $active_feed_id = false, $is_cat = false) {

		print "<div id=\"heading\">";

		print "Search <span id=\"headingAddon\">
				(<a href=\"tt-rss.php\">Go back</a>)</span></div>";

		print "<form method=\"GET\" action=\"tt-rss.php\" class=\"searchForm\">";

		print "<input type=\"hidden\" name=\"go\" value=\"vf\">";
		print "<input type=\"hidden\" name=\"id\" value=\"$active_feed_id\">";
		print "<input type=\"hidden\" name=\"cat\" value=\"$is_cat\">";

		print "<table><tr><td>".__('Search:')."</td><td>";
		print "<input name=\"query\"></td></tr>";

		print "<tr><td>".__('Where:')."</td><td>";
		
		print "<select name=\"search_mode\">
			<option value=\"all_feeds\">".__('All feeds')."</option>";
			
		$feed_title = getFeedTitle($link, $active_feed_id);

		if (!$is_cat) {
			$feed_cat_title = getFeedCatTitle($link, $active_feed_id);
		} else {
			$feed_cat_title = getCategoryTitle($link, $active_feed_id);
		}
			
		if ($active_feed_id && !$is_cat) {				
			print "<option selected value=\"this_feed\">$feed_title</option>";
		} else {
			print "<option disabled>".__('This feed')."</option>";
		}

		if ($is_cat) {
		  	$cat_preselected = "selected";
		}

		if (get_pref($link, 'ENABLE_FEED_CATS') && ($active_feed_id > 0 || $is_cat)) {
			print "<option $cat_preselected value=\"this_cat\">$feed_cat_title</option>";
		} else {
			//print "<option disabled>".__('This category')."</option>";
		}

		print "</select></td></tr>"; 

		print "<tr><td>".__('Match on:')."</td><td>";

		$search_fields = array(
			"title" => __("Title"),
			"content" => __("Content"),
			"both" => __("Title or content"));

		print_select_hash("match_on", 3, $search_fields); 
				
		print "</td></tr></table>";

		print "<input type=\"submit\" value=\"".__('Search')."\">";

		print "</form>";

		print "</div>";
	}

	function toggleMarked($link, $ts_id) {
		$result = db_query($link, "UPDATE ttrss_user_entries SET marked = NOT marked
			WHERE ref_id = '$ts_id' AND owner_uid = " . $_SESSION["uid"]);
	}

	function togglePublished($link, $tp_id) {
		$result = db_query($link, "UPDATE ttrss_user_entries SET published = NOT published
			WHERE ref_id = '$tp_id' AND owner_uid = " . $_SESSION["uid"]);
	}
?>

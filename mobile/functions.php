<?

	function render_feeds_list($link, $tags = false) {

		print "<ul class=\"feedList\">";

		$owner_uid = $_SESSION["uid"];

		if (!$tags) {

			/* virtual feeds */

			if (get_pref($link, 'ENABLE_FEED_CATS')) {
				print "<li class=\"feedCat\">Special</li>";
				print "<li id=\"feedCatHolder\"><ul class=\"feedCatList\">";
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
						print "<li id=\"feedCatHolder\"><ul class=\"feedCatList\">";
					} else {
						print "<li><hr></li>";
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

//			if (!get_pref($link, 'ENABLE_FEED_CATS')) {
				print "<li><hr></li>";
//			}

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
	
			$total_unread = 0;

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
	
				$total_unread += $unread;

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
						$holder_class = "";
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
						<a href=\"FIXME\">$tmp_category</a>
							<a href=\"?go=vcat&id=$cat_id\">
								<span class=\"$catctr_class\">($cat_unread unread)$ellipsis</span></a></li>";

					// !!! NO SPACE before <ul...feedCatList - breaks firstChild DOM function
					// -> keyboard navigation, etc.
					print "<li id=\"feedCatHolder\" class=\"$holder_class\"><ul class=\"feedCatList\" id=\"FCATLIST-$cat_id\">";
				}
	
				printMobileFeedEntry($feed_id, $class, $feed, $unread, 
					"../icons/$feed_id.ico", $link, $rtl_content);
	
				++$lnum;
			}

		} else {
			print "Tags: function not implemented.";
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
			$fctr_class = "";
		} else {
			$fctr_class = "class=\"invisible\"";
		}

		print "<span $rtl_tag>($unread)</span>";
		
		print "</li>";

	}

?>

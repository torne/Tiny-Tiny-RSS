<?
	require_once 'config.php';

	define('MAGPIE_OUTPUT_ENCODING', 'UTF-8');

	function purge_old_posts($link) {
		if (PURGE_OLD_DAYS > 0) {

			if (DB_TYPE == "pgsql") {
				$result = db_query($link, "DELETE FROM ttrss_entries WHERE
					marked = false AND 
					date_entered < NOW() - INTERVAL '".PURGE_OLD_DAYS." days'");
			} else {
				$result = db_query($link, "DELETE FROM ttrss_entries WHERE
					marked = false AND 
					date_entered < DATE_SUB(NOW(), INTERVAL ".PURGE_OLD_DAYS." DAY)");
			}
		}
	}

	function update_all_feeds($link, $fetch) {

		if (WEB_DEMO_MODE) return;

		if (DAEMON_REFRESH_ONLY) {
			if (!$_GET["daemon"]) {
				return;
			}
		}

		db_query($link, "BEGIN");

		$result = db_query($link, "SELECT feed_url,id,
			substring(last_updated,1,19) as last_updated,
			update_interval FROM ttrss_feeds");

		while ($line = db_fetch_assoc($result)) {
			$upd_intl = $line["update_interval"];

			if (!$upd_intl || $upd_intl == 0) $upd_intl = DEFAULT_UPDATE_INTERVAL;

			if (!$line["last_updated"] || 
				time() - strtotime($line["last_updated"]) > ($upd_intl * 60)) {

				update_rss_feed($link, $line["feed_url"], $line["id"]);
			}
		}

		purge_old_posts($link);

		db_query($link, "COMMIT");

	}

	function check_feed_favicon($feed_url, $feed) {
		$feed_url = str_replace("http://", "", $feed_url);
		$feed_url = preg_replace("/\/.*$/", "", $feed_url);
		
		$icon_url = "http://$feed_url/favicon.ico";
		$icon_file = ICONS_DIR . "/$feed.ico";

		if (!file_exists($icon_file)) {
				
			error_reporting(0);
			$r = fopen($icon_url, "r");
			error_reporting (E_ERROR | E_WARNING | E_PARSE);

			if ($r) {
				$tmpfname = tempnam("/tmp", "ttrssicon");
			
				$t = fopen($tmpfname, "w");
				
				while (!feof($r)) {
					$buf = fread($r, 16384);
					fwrite($t, $buf);
				}
				
				fclose($r);
				fclose($t);

				error_reporting(0);
				if (!rename($tmpfname, $icon_file)) {
					unlink($tmpfname);
				}
				error_reporting (E_ERROR | E_WARNING | E_PARSE);

			}	
		}
	}

	function update_rss_feed($link, $feed_url, $feed) {

		if (WEB_DEMO_MODE) return;

		$feed = db_escape_string($feed);

		error_reporting(0);
		$rss = fetch_rss($feed_url);

		error_reporting (E_ERROR | E_WARNING | E_PARSE);

		db_query($link, "BEGIN");

		$feed = db_escape_string($feed);

		if ($rss) {

			if (ENABLE_FEED_ICONS) {	
				check_feed_favicon($feed_url, $feed);
			}
		
			$result = db_query($link, "SELECT title,icon_url FROM ttrss_feeds WHERE id = '$feed'");

			$registered_title = db_fetch_result($result, 0, "title");
			$orig_icon_url = db_fetch_result($result, 0, "icon_url");

			if (!$registered_title) {
				$feed_title = db_escape_string($rss->channel["title"]);
				db_query($link, "UPDATE ttrss_feeds SET title = '$feed_title' WHERE id = '$feed'");
			}

//			print "I: " . $rss->channel["image"]["url"];

			$icon_url = $rss->image["url"];

			if ($icon_url && !$orig_icon_url) {
				$icon_url = db_escape_string($icon_url);
				db_query($link, "UPDATE ttrss_feeds SET icon_url = '$icon_url' WHERE id = '$feed'");
			}


			$filters = array();

			$result = db_query($link, "SELECT reg_exp,
				(SELECT name FROM ttrss_filter_types
					WHERE id = filter_type) as name
				FROM ttrss_filters");

			while ($line = db_fetch_assoc($result)) {
				if (!$filters[$line["name"]]) $filters[$line["name"]] = array();
				array_push($filters[$line["name"]], $line["reg_exp"]);
			}

			foreach ($rss->items as $item) {
	
				$entry_guid = $item["id"];
	
				if (!$entry_guid) $entry_guid = $item["guid"];
				if (!$entry_guid) $entry_guid = $item["link"];

				if (!$entry_guid) continue;

				$entry_timestamp = "";

				$rss_2_date = $item['pubdate'];
				$rss_1_date = $item['dc']['date'];
				$atom_date = $item['issued'];
			
				if ($atom_date != "") $entry_timestamp = parse_w3cdtf($atom_date);
				if ($rss_1_date != "") $entry_timestamp = parse_w3cdtf($rss_1_date);
				if ($rss_2_date != "") $entry_timestamp = strtotime($rss_2_date);
				
				if ($entry_timestamp == "") {
					$entry_timestamp = time();
					$no_orig_date = 'true';
				} else {
					$no_orig_date = 'false';
				}

				$entry_timestamp_fmt = strftime("%Y/%m/%d %H:%M:%S", $entry_timestamp);

				$entry_title = $item["title"];
				$entry_link = $item["link"];

				if (!$entry_title) continue;
				if (!$entry_link) continue;

				$entry_content = $item["content:escaped"];

				if (!$entry_content) $entry_content = $item["content:encoded"];
				if (!$entry_content) $entry_content = $item["content"];
				if (!$entry_content) $entry_content = $item["description"];

//				if (!$entry_content) continue;

				// WTF
				if (is_array($entry_content)) {
					$entry_content = $entry_content["encoded"];
					if (!$entry_content) $entry_content = $entry_content["escaped"];
				}

//				print_r($item);
//				print_r($entry_content);

				$content_hash = "SHA1:" . sha1(strip_tags($entry_content));

				$entry_comments = $item["comments"];

				$entry_guid = db_escape_string($entry_guid);

				$result = db_query($link, "
					SELECT 
						id,last_read,no_orig_date,title,feed_id,content_hash,
						substring(updated,1,19) as updated
					FROM
						ttrss_entries 
					WHERE
						guid = '$entry_guid'");

//				print db_num_rows($result) . "$entry_guid<br/>";

				if (db_num_rows($result) == 0) {

					error_reporting(0);
					if (is_filtered($entry_title, $entry_content, $filters)) {
						continue;
					}
					error_reporting (E_ERROR | E_WARNING | E_PARSE);

					//$entry_guid = db_escape_string($entry_guid);
					$entry_content = db_escape_string($entry_content);
					$entry_title = db_escape_string($entry_title);
					$entry_link = db_escape_string($entry_link);
					$entry_comments = db_escape_string($entry_comments);

					$query = "INSERT 
						INTO ttrss_entries 
							(title, 
							guid, 
							link, 
							updated, 
							content, 
							content_hash,
							feed_id, 
							comments,							
							no_orig_date,
							date_entered) 
						VALUES
							('$entry_title', 
							'$entry_guid', 
							'$entry_link', 
							'$entry_timestamp_fmt', 
							'$entry_content', 
							'$content_hash',
							'$feed', 
							'$entry_comments',
							$no_orig_date,
							NOW())";

					$result = db_query($link, $query);

				} else {

					$orig_entry_id = db_fetch_result($result, 0, "id");			
					$orig_feed_id = db_fetch_result($result, 0, "feed_id");

//					print "OED: $orig_entry_id; OID: $orig_feed_id ; FID: $feed<br>";

					if ($orig_feed_id != $feed) {
//						print "<p>GUID $entry_guid: update from different feed ($orig_feed_id, $feed): $entry_guid [$entry_title]";
						continue;
					}

					$entry_is_modified = false;
					
					$orig_timestamp = strtotime(db_fetch_result($result, 0, "updated"));
						
					$orig_content_hash = db_fetch_result($result, 0, "content_hash");
					$orig_last_read = db_fetch_result($result, 0, "last_read");	
					$orig_no_orig_date = db_fetch_result($result, 0, "no_orig_date");
					$orig_title = db_fetch_result($result, 0, "title");

					$last_read_qpart = "";

					if ($orig_content_hash != $content_hash) {
//						print "$orig_content_hash :: $content_hash<br>";

						if (UPDATE_POST_ON_CHECKSUM_CHANGE) {
							$last_read_qpart = 'last_read = null,';
						}
						$entry_is_modified = true;						
					}

					if ($orig_title != $entry_title) {
						$entry_is_modified = true;
					}

					if ($orig_timestamp != $entry_timestamp && !$orig_no_orig_date) {
						$entry_is_modified = true;
					}

					if ($entry_is_modified) {

//						print "$entry_guid Modified!<br>";

						$entry_comments = db_escape_string($entry_comments);
						$entry_content = db_escape_string($entry_content);
						$entry_title = db_escape_string($entry_title);					
						$entry_link = db_escape_string($entry_link);

						$query = "UPDATE ttrss_entries 
							SET 
								$last_read_qpart 
								title = '$entry_title',
								link = '$entry_link', 
								updated = '$entry_timestamp_fmt',
								content = '$entry_content',
								comments = '$entry_comments',
								content_hash = '$content_hash'
							WHERE
								id = '$orig_entry_id'";

						$result = db_query($link, $query);
					}
				}

				/* taaaags */
				// <a href="http://technorati.com/tag/Xorg" rel="tag">Xorg</a>, //

				$entry_tags = null;

				preg_match_all("/<a.*?rel=.tag.*?>([^>]+)<\/a>/i", $entry_content,
					$entry_tags);

				$entry_tags = $entry_tags[1];

				if (count($entry_tags) > 0) {
				
					$result = db_query($link, "SELECT id FROM ttrss_entries 
						WHERE guid = '$entry_guid'");

					if (!$result || db_num_rows($result) != 1) {
						return;
					}

					$entry_id = db_fetch_result($result, 0, "id");
				
					foreach ($entry_tags as $tag) {
						$tag = db_escape_string(strtolower($tag));

						$result = db_query($link, "SELECT id FROM ttrss_tags		
							WHERE tag_name = '$tag' AND post_id = '$entry_id' LIMIT 1");

						if ($result && db_num_rows($result) == 0) {
							
//							print "tagging $entry_id as $tag<br>";

							db_query($link, "INSERT INTO ttrss_tags (tag_name,post_id)
								VALUES ('$tag', '$entry_id')");
						}							
					}
				}
			}

			db_query($link, "UPDATE ttrss_feeds 
				SET last_updated = NOW(), last_error = '' WHERE id = '$feed'");

		} else {
			$error_msg = db_escape_string(magpie_error());
			db_query($link, 
				"UPDATE ttrss_feeds SET last_error = '$error_msg', 
					last_updated = NOW() WHERE id = '$feed'");
		}

		db_query($link, "COMMIT");

	}

	function print_select($id, $default, $values, $attributes = "") {
		print "<select id=\"$id\" $attributes>";
		foreach ($values as $v) {
			if ($v == $default)
				$sel = " selected";
			 else
			 	$sel = "";
			
			print "<option$sel>$v</option>";
		}
		print "</select>";
	}

	function is_filtered($title, $content, $filters) {

		if ($filters["title"]) {
			foreach ($filters["title"] as $title_filter) {			
				if (preg_match("/$title_filter/i", $title)) 
					return true;
			}
		}

		if ($filters["content"]) {
			foreach ($filters["content"] as $content_filter) {			
				if (preg_match("/$content_filter/i", $content)) 
					return true;
			}
		}

		if ($filters["both"]) {
			foreach ($filters["both"] as $filter) {			
				if (preg_match("/$filter/i", $title) || preg_match("/$filter/i", $content)) 
					return true;
			}
		}

		return false;
	}

	function printFeedEntry($feed_id, $class, $feed_title, $unread, $icon_file) {

		if (file_exists($icon_file) && filesize($icon_file) > 0) {
				$feed_icon = "<img src=\"$icon_file\">";
		} else {
			$feed_icon = "<img src=\"images/blank_icon.gif\">";
		}

		$feed = "<a href=\"javascript:viewfeed('$feed_id', 0);\">$feed_title</a>";

		print "<li id=\"FEEDR-$feed_id\" class=\"$class\">";
		if (ENABLE_FEED_ICONS) {
			print "$feed_icon";
		}

		print "<span id=\"FEEDN-$feed_id\">$feed</span>";

		if ($unread != 0) {
			$fctr_class = "";
		} else {
			$fctr_class = "class=\"invisible\"";
		}

		print "<span $fctr_class id=\"FEEDCTR-$feed_id\">
			 (<span id=\"FEEDU-$feed_id\">$unread</span>)</span>";
		
		print "</li>";

	}

	function getmicrotime() {
		list($usec, $sec) = explode(" ",microtime());
		return ((float)$usec + (float)$sec);
	}

?>

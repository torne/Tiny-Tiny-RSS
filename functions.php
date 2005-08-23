<?
	require_once 'config.php';

	function update_all_feeds($link, $fetch) {

		pg_query("BEGIN");

		if (!$fetch) {

			$result = pg_query($link, "SELECT feed_url,id FROM ttrss_feeds WHERE
				last_updated is null OR title = '' OR
				EXTRACT(EPOCH FROM NOW()) - EXTRACT(EPOCH FROM last_updated) > " . 
				MIN_UPDATE_TIME);

		} else {

			$result = pg_query($link, "SELECT feed_url,id FROM ttrss_feeds");
		}

		$num_unread = 0;

		while ($line = pg_fetch_assoc($result)) {
			$num_unread += update_rss_feed($link, $line["feed_url"], $line["id"]);
		}

		pg_query("COMMIT");

	}

	function update_rss_feed($link, $feed_url, $feed) {

		error_reporting(0);
		$rss = fetch_rss($feed_url);
		error_reporting (E_ERROR | E_WARNING | E_PARSE);

		$num_unread = 0;
	
		if ($rss) {

			pg_query("BEGIN");

			$result = pg_query("SELECT title FROM ttrss_feeds WHERE id = '$feed'");

			$registered_title = pg_fetch_result($result, 0, "title");

			if (!$registered_title) {
				$feed_title = $rss->channel["title"];
				pg_query("UPDATE ttrss_feeds SET title = '$feed_title' WHERE id = '$feed'");
			}

			foreach ($rss->items as $item) {
	
				$entry_guid = $item["id"];
	
				if (!$entry_guid) $entry_guid = $item["guid"];
				if (!$entry_guid) $entry_guid = $item["link"];
	
				$entry_timestamp = "";

				$rss_2_date = $item['pubdate'];
				$rss_1_date = $item['dc']['date'];
				$atom_date = $item['issued'];
			
				$no_orig_date = 'false';
			
				if ($atom_date != "") $entry_timestamp = parse_w3cdtf($atom_date);
				if ($rss_1_date != "") $entry_timestamp = parse_w3cdtf($rss_1_date);
				if ($rss_2_date != "") $entry_timestamp = strtotime($rss_2_date);
//				if ($rss_3_date != "") $entry_timestamp = strtotime($rss_3_date);
				
				if ($entry_timestamp == "") {
					$entry_timestamp = time();
					$no_orig_date = 'true';
				}

				if (!$entry_timestamp) continue;

				$entry_title = $item["title"];
				$entry_link = $item["link"];

				if (!$entry_title) continue;
				if (!$entry_link) continue;

				$entry_content = $item["description"];
				if (!$entry_content) $entry_content = $item["content"];
	
				$entry_content = pg_escape_string($entry_content);
				$entry_title = pg_escape_string($entry_title);
	
				$content_md5 = md5($entry_content);
	
				$result = pg_query($link, "
					SELECT 
						id,unread,md5_hash,last_read,no_orig_date,title,
						EXTRACT(EPOCH FROM updated) as updated_timestamp
					FROM
						ttrss_entries 
					WHERE
						guid = '$entry_guid' OR md5_hash = '$content_md5'");
				
				if (pg_num_rows($result) == 0) {
	
					$entry_timestamp = strftime("%Y/%m/%d %H:%M:%S", $entry_timestamp);
	
					$query = "INSERT INTO ttrss_entries 
							(title, guid, link, updated, content, feed_id, 
								md5_hash, no_orig_date) 
						VALUES
							('$entry_title', '$entry_guid', '$entry_link', 
								'$entry_timestamp', '$entry_content', '$feed', 
								'$content_md5', $no_orig_date)";
	
					$result = pg_query($link, $query);

					if ($result) ++$num_unread;
	
				} else {
	
					$entry_id = pg_fetch_result($result, 0, "id");
					$updated_timestamp = pg_fetch_result($result, 0, "updated_timestamp");
					$entry_timestamp_fmt = strftime("%Y/%m/%d %H:%M:%S", $entry_timestamp);
					$last_read = pg_fetch_result($result, 0, "last_read");
	
					$unread = pg_fetch_result($result, 0, "unread");
					$md5_hash = pg_fetch_result($result, 0, "md5_hash");
					$no_orig_date = pg_fetch_result($result, 0, "no_orig_date");
					$orig_title = pg_fetch_result($result, 0, "title");

					// disable update detection for posts which didn't have correct
					// publishment date, because they will always register as updated
					// sadly this doesn't catch feed generators which input current date 
					// in posts all the time (some planets do this)

					if ($no_orig_date != 't' && (!$last_read || $md5_hash != $content_md5)) {
						$last_read_qpart = 'last_read = null,';
					} else {
						$last_read_qpart = '';
					}

					// mark post as updated on title change
					// maybe we should mark it as unread instead?

					if ($orig_title != $entry_title) {
						$last_read_qpart = 'last_read = null,';
					}

					// don't bother updating timestamps on posts with broken pubDate
					
					if ($no_orig_date != 't') {
						$update_timestamp_qpart = "updated = '$entry_timestamp_fmt',";
					}

					$query = "UPDATE ttrss_entries 
						SET 
							title ='$entry_title', 
							link = '$entry_link', 
							$update_timestamp_qpart
							$last_read_qpart
							content = '$entry_content',
							md5_hash = '$content_md5',
							unread = '$unread'
						WHERE
							id = '$entry_id'";
	
					$result = pg_query($link, $query);
	
					if ($result) ++$num_unread;
	
				}
	
			}

			if ($result) {
				$result = pg_query($link, "UPDATE ttrss_feeds SET last_updated = NOW()");
			}

			pg_query("COMMIT");

		}

	}




?>

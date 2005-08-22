<?
	require_once 'config.php';

	function update_all_feeds($link, $fetch) {

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

	}

	function update_rss_feed($link, $feed_url, $feed) {

		$rss = fetch_rss($feed_url);

		$num_unread = 0;
	
		if ($rss) {
	
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
			
				if ($atom_date != "") $entry_timestamp = parse_w3cdtf($atom_date);
				if ($rss_1_date != "") $entry_timestamp = parse_w3cdtf($rss_1_date);
				if ($rss_2_date != "") $entry_timestamp = strtotime($rss_2_date);
				if ($entry_timestamp == "") $entry_timestamp = 0;

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
						id,unread,md5_hash,
						EXTRACT(EPOCH FROM updated) as updated_timestamp
					FROM
						ttrss_entries 
					WHERE
						guid = '$entry_guid' OR md5_hash = '$content_md5'");
				
				if (pg_num_rows($result) == 0) {
	
					$entry_timestamp = strftime("%Y/%m/%d %H:%M:%S", $entry_timestamp);
	
					$query = "INSERT INTO ttrss_entries 
							(title, guid, link, updated, content, feed_id, md5_hash) 
						VALUES
							('$entry_title', '$entry_guid', '$entry_link', 
								'$entry_timestamp', '$entry_content', '$feed', 
								'$content_md5')";
	
					$result = pg_query($link, $query);

					if ($result) ++$num_unread;
	
				} else {
	
					$entry_id = pg_fetch_result($result, 0, "id");
					$updated_timestamp = pg_fetch_result($result, 0, "updated_timestamp");
					$entry_timestamp_fmt = strftime("%Y/%m/%d %H:%M:%S", $entry_timestamp);
	
					$unread = pg_fetch_result($result, 0, "unread");
					$md5_hash = pg_fetch_result($result, 0, "md5_hash");
					
					if ($md5_hash != $content_md5 && CONTENT_CHECK_MD5) 
						$unread = "true";
				
//					if ($unread || !CONTENT_CHECK_MD5) {
//						$updated_query_part = "updated = '$entry_timestamp',";
//					}

//					if ($updated_timestamp > $entry_timestamp) {
//						$unread = "true";
//						print "$updated_timestamp : $entry_timestamp<br>";
//					}			

					$query = "UPDATE ttrss_entries 
						SET 
							title ='$entry_title', 
							link = '$entry_link', 
							updated = '$entry_timestamp_fmt',
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

		}

	}




?>

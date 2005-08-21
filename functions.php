<?
	require_once 'config.php';

	function update_all_feeds($link) {

		$result = pg_query($link, "SELECT feed_url,id FROM ttrss_feeds WHERE
			last_updated is null OR
			EXTRACT(EPOCH FROM NOW()) - EXTRACT(EPOCH FROM last_updated) > " . 
			MIN_UPDATE_TIME);

		while ($line = pg_fetch_assoc($result)) {
			update_rss_feed($link, $line["feed_url"], $line["id"]);
		}

	}

	function update_rss_feed($link, $feed_url, $feed) {

		$rss = fetch_rss($feed_url);
	
		if ($rss) {

			foreach ($rss->items as $item) {
	
				$entry_guid = $item["id"];
	
				if (!$entry_guid) $entry_guid = $item["guid"];
				if (!$entry_guid) $entry_guid = $item["link"];
	
				$entry_timestamp = $item["pubdate"];
				if (!$entry_timestamp) $entry_timestamp = $item["modified"];
				if (!$entry_timestamp) $entry_timestamp = $item["updated"];
	
				$entry_timestamp = strtotime($entry_timestamp);
	
				$entry_title = $item["title"];
				$entry_link = $item["link"];
	
				$entry_content = $item["description"];
				if (!$entry_content) $entry_content = $item["content"];
	
				$entry_content = pg_escape_string($entry_content);
				$entry_title = pg_escape_string($entry_title);
	
				$content_md5 = md5($entry_content);
	
				$result = pg_query($link, "
					SELECT 
						id,unread,md5_hash
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
	
					pg_query($link, $query);
	
				} else {
	
					$entry_id = pg_fetch_result($result, 0, "id");
					$entry_timestamp = strftime("%Y/%m/%d %H:%M:%S", $entry_timestamp);
	
					$unread = pg_fetch_result($result, 0, "unread");
					$md5_hash = pg_fetch_result($result, 0, "md5_hash");
					
					if ($md5_hash != $content_md5 && CONTENT_CHECK_MD5) 
						$unread = "true";
				
					if ($unread || !CONTENT_CHECK_MD5) {
						$updated_query_part = "updated = '$entry_timestamp',";
					}
				
					$query = "UPDATE ttrss_entries 
						SET 
							title ='$entry_title', 
							link = '$entry_link', 
							$updated_query_part
							content = '$entry_content',
							md5_hash = '$content_md5',
							unread = '$unread'
						WHERE
							id = '$entry_id'";
	
					$result = pg_query($link, $query);
	

//						print "$entry_guid - $entry_timestamp - $entry_title - 
//							$entry_link - $entry_id<br>";
	
				}
	
			}

			$result = pg_query($link, "UPDATE ttrss_feeds SET last_updated = NOW()");

		}

	}




?>

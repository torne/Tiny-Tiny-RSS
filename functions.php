<?
	require_once 'config.php';

	function update_all_feeds($link, $fetch) {

		if (WEB_DEMO_MODE) return;

		pg_query("BEGIN");

		if (!$fetch) {

			$result = pg_query($link, "SELECT feed_url,id FROM ttrss_feeds WHERE
				last_updated is null OR title = '' OR
				EXTRACT(EPOCH FROM NOW()) - EXTRACT(EPOCH FROM last_updated) > " . 
				MIN_UPDATE_TIME);

		} else {

			$result = pg_query($link, "SELECT feed_url,id FROM ttrss_feeds");
		}

		while ($line = pg_fetch_assoc($result)) {
			update_rss_feed($link, $line["feed_url"], $line["id"]);
		}

		pg_query("COMMIT");

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

				rename($tmpfname, $icon_file);

			}	
		}
	}

	function update_rss_feed($link, $feed_url, $feed) {

		if (WEB_DEMO_MODE) return;

		error_reporting(0);
		$rss = fetch_rss($feed_url);
		error_reporting (E_ERROR | E_WARNING | E_PARSE);

		pg_query("BEGIN");
	
		if ($rss) {

			if (ENABLE_FEED_ICONS) {	
				check_feed_favicon($feed_url, $feed);
			}
		
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

				$entry_content = $item["description"];
				if (!$entry_content) $entry_content = $item["content:escaped"];
				if (!$entry_content) $entry_content = $item["content"];

				if (!$entry_content) continue;

				$content_hash = "SHA1:" . sha1(strip_tags($entry_content));

				$result = pg_query($link, "
					SELECT 
						id,last_read,no_orig_date,title,feed_id,content_hash,
						EXTRACT(EPOCH FROM updated) as updated_timestamp
					FROM
						ttrss_entries 
					WHERE
						guid = '$entry_guid'");

				if (pg_num_rows($result) == 0) {

					$entry_content = pg_escape_string($entry_content);
					$entry_title = pg_escape_string($entry_title);
					$entry_link = pg_escape_string($entry_link);

					$query = "INSERT 
						INTO ttrss_entries 
							(title, 
							guid, 
							link, 
							updated, 
							content, 
							content_hash,
							feed_id, 
							no_orig_date) 
						VALUES
							('$entry_title', 
							'$entry_guid', 
							'$entry_link', 
							'$entry_timestamp_fmt', 
							'$entry_content', 
							'$content_hash',
							'$feed', 
							$no_orig_date)";

					$result = pg_query($link, $query);

				} else {

					$orig_entry_id = pg_fetch_result($result, 0, "id");			
					$orig_feed_id = pg_fetch_result($result, 0, "feed_id");

					if ($orig_feed_id != $feed) {
//						print "<p>Update from different feed ($orig_feed_id, $feed): $entry_guid [$entry_title]";
						continue;
					}
					
					$orig_timestamp = pg_fetch_result($result, 0, "updated_timestamp");
					$orig_content_hash = pg_fetch_result($result, 0, "content_hash");
					$orig_last_read = pg_fetch_result($result, 0, "last_read");	
					$orig_no_orig_date = pg_fetch_result($result, 0, "no_orig_date");
					$orig_title = pg_fetch_result($result, 0, "title");

					if ($orig_title != $entry_title) {
						$last_read_qpart = 'last_read = null,';
					}

					if ($orig_content_hash != $content_hash) {
						$last_read_qpart = 'last_read = null,';
					}

//					if ($orig_timestamp < $entry_timestamp) {
//						$last_read_qpart = 'last_read = null,';
//					}

					$entry_content = pg_escape_string($entry_content);
					$entry_title = pg_escape_string($entry_title);
					$entry_link = pg_escape_string($entry_link);

					$query = "UPDATE ttrss_entries 
						SET 
							$last_read_qpart 
							title = '$entry_title',
							link = '$entry_link', 
							updated = '$entry_timestamp_fmt',
							content = '$entry_content',
							content_hash = '$content_hash'
						WHERE
							id = '$orig_entry_id'";

					$result = pg_query($link, $query);

				}
			}

			if ($result) {
				$result = pg_query($link, "UPDATE ttrss_feeds SET last_updated = NOW()");
			}

		}

		pg_query("COMMIT");

	}


?>

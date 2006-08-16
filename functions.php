<?

/*	if ($_GET["debug"]) {
		define('DEFAULT_ERROR_LEVEL', E_ALL);
	} else {
		define('DEFAULT_ERROR_LEVEL', E_ERROR | E_WARNING | E_PARSE);
	} */

	require_once 'config.php';
	require_once 'db-prefs.php';
	require_once 'compat.php';
	require_once 'errors.php';
	require_once 'version.php';

	if (RSS_BACKEND_TYPE == "magpie") {	
		require_once 'magpierss/rss_utils.inc';
	} else if (RSS_BACKEND_TYPE == "simplepie") {
		require_once 'simplepie/simplepie.inc';
	}

	define('MAGPIE_OUTPUT_ENCODING', 'UTF-8');

	function purge_feed($link, $feed_id, $purge_interval, $debug = false) {

		$rows = -1;

		if (DB_TYPE == "pgsql") {
/*			$result = db_query($link, "DELETE FROM ttrss_user_entries WHERE
				marked = false AND feed_id = '$feed_id' AND
				(SELECT date_entered FROM ttrss_entries WHERE
					id = ref_id) < NOW() - INTERVAL '$purge_interval days'"); */

			$pg_version = get_pgsql_version($link);

			if (preg_match("/^7\./", $pg_version) || preg_match("/^8\.0/", $pg_version)) {

				$result = db_query($link, "DELETE FROM ttrss_user_entries WHERE 
					ttrss_entries.id = ref_id AND 
					marked = false AND 
					feed_id = '$feed_id' AND 
					ttrss_entries.date_entered < NOW() - INTERVAL '$purge_interval days'");

			} else {

				$result = db_query($link, "DELETE FROM ttrss_user_entries 
					USING ttrss_entries 
					WHERE ttrss_entries.id = ref_id AND 
					marked = false AND 
					feed_id = '$feed_id' AND 
					ttrss_entries.date_entered < NOW() - INTERVAL '$purge_interval days'");
			}

			$rows = pg_affected_rows($result);
			
		} else {
		
/*			$result = db_query($link, "DELETE FROM ttrss_user_entries WHERE
				marked = false AND feed_id = '$feed_id' AND
				(SELECT date_entered FROM ttrss_entries WHERE 
					id = ref_id) < DATE_SUB(NOW(), INTERVAL $purge_interval DAY)"); */

			$result = db_query($link, "DELETE FROM ttrss_user_entries 
				USING ttrss_user_entries, ttrss_entries 
				WHERE ttrss_entries.id = ref_id AND 
				marked = false AND 
				feed_id = '$feed_id' AND 
				ttrss_entries.date_entered < DATE_SUB(NOW(), INTERVAL $purge_interval DAY)");
					
			$rows = mysql_affected_rows($link);

		}

		if ($debug) {
			print "Purged feed $feed_id ($purge_interval): deleted $rows articles\n";
		}
	}

	function global_purge_old_posts($link, $do_output = false, $limit = false) {

		$random_qpart = sql_random_function();

		if ($limit) {
			$limit_qpart = "LIMIT $limit";
		} else {
			$limit_qpart = "";
		}
		
		$result = db_query($link, 
			"SELECT id,purge_interval,owner_uid FROM ttrss_feeds 
				ORDER BY $random_qpart $limit_qpart");

		while ($line = db_fetch_assoc($result)) {

			$feed_id = $line["id"];
			$purge_interval = $line["purge_interval"];
			$owner_uid = $line["owner_uid"];

			if ($purge_interval == 0) {
			
				$tmp_result = db_query($link, 
					"SELECT value FROM ttrss_user_prefs WHERE
						pref_name = 'PURGE_OLD_DAYS' AND owner_uid = '$owner_uid'");

				if (db_num_rows($tmp_result) != 0) {			
					$purge_interval = db_fetch_result($tmp_result, 0, "value");
				}
			}

			if ($do_output) {
//				print "Feed $feed_id: purge interval = $purge_interval\n";
			}

			if ($purge_interval > 0) {
				purge_feed($link, $feed_id, $purge_interval, $do_output);
			}
		}	

		// purge orphaned posts in main content table
		db_query($link, "DELETE FROM ttrss_entries WHERE 
			(SELECT COUNT(int_id) FROM ttrss_user_entries WHERE ref_id = id) = 0");

	}

	function purge_old_posts($link) {

		$user_id = $_SESSION["uid"];
	
		$result = db_query($link, "SELECT id,purge_interval FROM ttrss_feeds 
			WHERE owner_uid = '$user_id'");

		while ($line = db_fetch_assoc($result)) {

			$feed_id = $line["id"];
			$purge_interval = $line["purge_interval"];

			if ($purge_interval == 0) $purge_interval = get_pref($link, 'PURGE_OLD_DAYS');

			if ($purge_interval > 0) {
				purge_feed($link, $feed_id, $purge_interval);
			}
		}	

		// purge orphaned posts in main content table
		db_query($link, "DELETE FROM ttrss_entries WHERE 
			(SELECT COUNT(int_id) FROM ttrss_user_entries WHERE ref_id = id) = 0");
	}

	function update_all_feeds($link, $fetch, $user_id = false, $force_daemon = false) {

		if (WEB_DEMO_MODE) return;

		if (!$user_id) {
			$user_id = $_SESSION["uid"];
			purge_old_posts($link);
		}

//		db_query($link, "BEGIN");

		if (MAX_UPDATE_TIME > 0) {
			if (DB_TYPE == "mysql") {
				$q_order = "RAND()";
			} else {
				$q_order = "RANDOM()";
			}
		} else {
			$q_order = "last_updated DESC";
		}

		$result = db_query($link, "SELECT feed_url,id,
			SUBSTRING(last_updated,1,19) AS last_updated,
			update_interval FROM ttrss_feeds WHERE owner_uid = '$user_id'
			ORDER BY $q_order");

		$upd_start = time();

		while ($line = db_fetch_assoc($result)) {
			$upd_intl = $line["update_interval"];

			if (!$upd_intl || $upd_intl == 0) {
				$upd_intl = get_pref($link, 'DEFAULT_UPDATE_INTERVAL', $user_id);
			}

			if ($upd_intl < 0) { 
				// Updates for this feed are disabled
				continue; 
			}

			if ($fetch || (!$line["last_updated"] || 
				time() - strtotime($line["last_updated"]) > ($upd_intl * 60))) {

//				print "<!-- feed: ".$line["feed_url"]." -->";

				update_rss_feed($link, $line["feed_url"], $line["id"], $force_daemon);

				$upd_elapsed = time() - $upd_start;

				if (MAX_UPDATE_TIME > 0 && $upd_elapsed > MAX_UPDATE_TIME) {
					return;
				}
			}
		}

//		db_query($link, "COMMIT");

	}

	function check_feed_favicon($feed_url, $feed, $link) {
		$feed_url = str_replace("http://", "", $feed_url);
		$feed_url = preg_replace("/\/.*$/", "", $feed_url);
		
		$icon_url = "http://$feed_url/favicon.ico";
		$icon_file = ICONS_DIR . "/$feed.ico";

		if (!file_exists($icon_file)) {
				
			error_reporting(0);
			$r = fopen($icon_url, "r");
			error_reporting (DEFAULT_ERROR_LEVEL);

			if ($r) {
				$tmpfname = tempnam(TMP_DIRECTORY, "ttrssicon");
			
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

				chmod($icon_file, 0644);
				
				error_reporting (DEFAULT_ERROR_LEVEL);

			}	
		}
	}

	function update_rss_feed($link, $feed_url, $feed, $ignore_daemon = false) {

		if (WEB_DEMO_MODE) return;

		if (DAEMON_REFRESH_ONLY && !$_GET["daemon"] && !$ignore_daemon) {
			return;			
		}

		$result = db_query($link, "SELECT update_interval,auth_login,auth_pass	
			FROM ttrss_feeds WHERE id = '$feed'");

		$auth_login = db_unescape_string(db_fetch_result($result, 0, "auth_login"));
		$auth_pass = db_unescape_string(db_fetch_result($result, 0, "auth_pass"));

		$update_interval = db_fetch_result($result, 0, "update_interval");

		if ($update_interval < 0) { return; }

		$feed = db_escape_string($feed);

		$fetch_url = $feed_url;

		if ($auth_login && $auth_pass) {
			$url_parts = array();
			preg_match("/(^[^:]*):\/\/(.*)/", $fetch_url, $url_parts);

			if ($url_parts[1] && $url_parts[2]) {
				$fetch_url = $url_parts[1] . "://$auth_login:$auth_pass@" . $url_parts[2];
			}

		}

		if (RSS_BACKEND_TYPE == "magpie") {		
			error_reporting(0);
			$rss = fetch_rss($fetch_url);
			error_reporting (DEFAULT_ERROR_LEVEL);
		} else if (RSS_BACKEND_TYPE == "simplepie") {
			
			if (!file_exists(SIMPLEPIE_CACHE_DIR)) {
					mkdir(SIMPLEPIE_CACHE_DIR);
			}

			$rss = new SimplePie();
			$rss->feed_url($fetch_url);
			$rss->cache_location(SIMPLEPIE_CACHE_DIR);
			$rss->init();
		}
		
		$feed = db_escape_string($feed);

		$rss_check = $rss;

		if (RSS_BACKEND_TYPE == "simplepie") {
			$rss_check = $rss->data;
		}
		
		if ($rss_check) {
			
//			db_query($link, "BEGIN");

			$result = db_query($link, "SELECT title,icon_url,site_url,owner_uid
				FROM ttrss_feeds WHERE id = '$feed'");

			$registered_title = db_fetch_result($result, 0, "title");
			$orig_icon_url = db_fetch_result($result, 0, "icon_url");
			$orig_site_url = db_fetch_result($result, 0, "site_url");

			$owner_uid = db_fetch_result($result, 0, "owner_uid");

			if (get_pref($link, 'ENABLE_FEED_ICONS', $owner_uid, false)) {	
				check_feed_favicon($feed_url, $feed, $link);
			}

			if (!$registered_title || $registered_title == "[Unknown]") {
			
				if (RSS_BACKEND_TYPE == "magpie") {				
					$feed_title = db_escape_string($rss->channel["title"]);
				} else {
					$feed_title = $rss->get_feed_title();
				}
				
				db_query($link, "UPDATE ttrss_feeds SET 
					title = '$feed_title' WHERE id = '$feed'");
			}

			if (RSS_BACKEND_TYPE == "magpie") {				
				$site_url = $rss->channel["link"];
				// weird, weird Magpie
				if (!$site_url) $site_url = db_escape_string($rss->channel["link_"]);
			} else {
				$site_url = $rss->get_feed_link();
			}

			if ($site_url && $orig_site_url != db_escape_string($site_url)) {
				db_query($link, "UPDATE ttrss_feeds SET 
					site_url = '$site_url' WHERE id = '$feed'");
			}

//			print "I: " . $rss->channel["image"]["url"];

			if (RSS_BACKEND_TYPE == "magpie") {
				$icon_url = $rss->image["url"];
			} else {
				$icon_url = $rss->get_image_url(); # FIXME
			}

			if ($icon_url && !$orig_icon_url != db_escape_string($icon_url)) {
				$icon_url = db_escape_string($icon_url);
				db_query($link, "UPDATE ttrss_feeds SET icon_url = '$icon_url' WHERE id = '$feed'");
			}


			$filters = array();

			$result = db_query($link, "SELECT reg_exp,
				ttrss_filter_types.name AS name,
				ttrss_filter_actions.name AS action
				FROM ttrss_filters,ttrss_filter_types,ttrss_filter_actions WHERE 					
					owner_uid = $owner_uid AND
					ttrss_filter_types.id = filter_type AND
					ttrss_filter_actions.id = action_id AND
				(feed_id IS NULL OR feed_id = '$feed')");

			while ($line = db_fetch_assoc($result)) {
				if (!$filters[$line["name"]]) $filters[$line["name"]] = array();

				$filter["reg_exp"] = $line["reg_exp"];
				$filter["action"] = $line["action"];
				
				array_push($filters[$line["name"]], $filter);
			}

			if (RSS_BACKEND_TYPE == "magpie") {			
				$iterator = $rss->items;

				if (!$iterator || !is_array($iterator)) $iterator = $rss->entries;
				if (!$iterator || !is_array($iterator)) $iterator = $rss;

			} else {
				$iterator = $rss->get_items();
			}

			if (!is_array($iterator)) {
				/* db_query($link, "UPDATE ttrss_feeds 
					SET last_error = 'Parse error: can\'t find any articles.'
						WHERE id = '$feed'"); */
				return; // WTF?
			}

			foreach ($iterator as $item) {

				if (RSS_BACKEND_TYPE == "magpie") {
				
					$entry_guid = $item["id"];
		
					if (!$entry_guid) $entry_guid = $item["guid"];
					if (!$entry_guid) $entry_guid = $item["link"];
	
					if (!$entry_guid) continue;
	
					$entry_timestamp = "";
	
					$rss_2_date = $item['pubdate'];
					$rss_1_date = $item['dc']['date'];
					$atom_date = $item['issued'];
					if (!$atom_date) $atom_date = $item['updated'];
				
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
	
					// strange Magpie workaround
					$entry_link = $item["link_"];
					if (!$entry_link) $entry_link = $item["link"];
	
					if (!$entry_title) continue;
					if (!$entry_link) continue;
	
					$entry_content = $item["content:escaped"];
	
					if (!$entry_content) $entry_content = $item["content:encoded"];
					if (!$entry_content) $entry_content = $item["content"];
					if (!$entry_content) $entry_content = $item["atom_content"];
					if (!$entry_content) $entry_content = $item["summary"];
					if (!$entry_content) $entry_content = $item["description"];
	
	//				if (!$entry_content) continue;
	
					// WTF
					if (is_array($entry_content)) {
						$entry_content = $entry_content["encoded"];
						if (!$entry_content) $entry_content = $entry_content["escaped"];
					}
	
	//				print_r($item);
	//				print_r(htmlspecialchars($entry_content));
	//				print "<br>";
	
					$entry_content_unescaped = $entry_content;
					$content_hash = "SHA1:" . sha1(strip_tags($entry_content));
	
					$entry_comments = $item["comments"];
	
					$entry_author = db_escape_string($item['dc']['creator']);
	
					$entry_guid = db_escape_string($entry_guid);
	
					$result = db_query($link, "SELECT id FROM	ttrss_entries 
						WHERE guid = '$entry_guid'");
	
					$entry_content = db_escape_string($entry_content);
					$entry_title = db_escape_string($entry_title);
					$entry_link = db_escape_string($entry_link);
					$entry_comments = db_escape_string($entry_comments);
	
					$num_comments = db_escape_string($item["slash"]["comments"]);
	
					if (!$num_comments) $num_comments = 0;

				} else if (RSS_BACKEND_TYPE == "simplepie") {

					$entry_guid = $item->get_id();

					if (!$entry_guid) {
							$entry_guid = $item->get_permalink();
					}
	
					if (!$entry_guid) continue;
					
					$entry_timestamp = $item->get_date("U");
					
					if ($entry_timestamp == "") {
						$entry_timestamp = time();
						$no_orig_date = 'true';
					} else {
						$no_orig_date = 'false';
					}
	
					$entry_timestamp_fmt = strftime("%Y/%m/%d %H:%M:%S", $entry_timestamp);
	
					$entry_title = $item->get_title();
					$entry_link = $item->get_permalink();
	
					if (!$entry_title) continue;
					if (!$entry_link) continue;

					$entry_content = $item->get_description();
						
//					print_r(htmlspecialchars($entry_content));
//					print "<br>";
	
					$entry_content_unescaped = $entry_content;
					$content_hash = "SHA1:" . sha1(strip_tags($entry_content));
	
					$entry_comments = ""; # FIXME
					
					$entry_author = $item->get_author(0);

					$entry_author = db_escape_string($entry_author->name);
					
					$entry_guid = db_escape_string($entry_guid);

					$result = db_query($link, "SELECT id FROM	ttrss_entries 
						WHERE guid = '$entry_guid'");
	
					$entry_content = db_escape_string($entry_content);
					$entry_title = db_escape_string($entry_title);
					$entry_link = db_escape_string($entry_link);
					$entry_comments = db_escape_string($entry_comments);
	
					$num_comments = 0; # FIXME
	
					if (!$num_comments) $num_comments = 0;

				}

				# sanitize content
				
				$entry_content = sanitize_rss($entry_content);
				$entry_title = sanitize_rss($entry_title);
				$entry_link = sanitize_rss($entry_link);
				$entry_comments = sanitize_rss($entry_comments);

				db_query($link, "BEGIN");

				if (db_num_rows($result) == 0) {

					// base post entry does not exist, create it

					$result = db_query($link,
						"INSERT INTO ttrss_entries 
							(title,
							guid,
							link,
							updated,
							content,
							content_hash,
							no_orig_date,
							date_entered,
							comments,
							num_comments,
							author)
						VALUES
							('$entry_title', 
							'$entry_guid', 
							'$entry_link',
							'$entry_timestamp_fmt', 
							'$entry_content', 
							'$content_hash',
							$no_orig_date, 
							NOW(), 
							'$entry_comments',
							'$num_comments',
							'$entry_author')");
				} else {
					// we keep encountering the entry in feeds, so we need to
					// update date_entered column so that we don't get horrible
					// dupes when the entry gets purged and reinserted again e.g.
					// in the case of SLOW SLOW OMG SLOW updating feeds

					$base_entry_id = db_fetch_result($result, 0, "id");

					db_query($link, "UPDATE ttrss_entries SET date_entered = NOW()
						WHERE id = '$base_entry_id'");
				}

				// now it should exist, if not - bad luck then

				$result = db_query($link, "SELECT 
						id,content_hash,no_orig_date,title,
						substring(date_entered,1,19) as date_entered,
						substring(updated,1,19) as updated,
						num_comments
					FROM 
						ttrss_entries 
					WHERE guid = '$entry_guid'");

				if (db_num_rows($result) == 1) {

					// this will be used below in update handler
					$orig_content_hash = db_fetch_result($result, 0, "content_hash");
					$orig_title = db_fetch_result($result, 0, "title");
					$orig_num_comments = db_fetch_result($result, 0, "num_comments");
					$orig_date_entered = strtotime(db_fetch_result($result, 
						0, "date_entered"));

					$ref_id = db_fetch_result($result, 0, "id");

					// check for user post link to main table

					// do we allow duplicate posts with same GUID in different feeds?
					if (get_pref($link, "ALLOW_DUPLICATE_POSTS", $owner_uid, false)) {
						$dupcheck_qpart = "AND feed_id = '$feed'";
					} else { 
						$dupcheck_qpart = "";
					}

//					error_reporting(0);

					$filter_name = get_filter_name($entry_title, $entry_content, 
						$entry_link, $filters);

					if ($filter_name == "filter") {
						continue;
					}

//					error_reporting (DEFAULT_ERROR_LEVEL);

					$result = db_query($link,
						"SELECT ref_id FROM ttrss_user_entries WHERE
							ref_id = '$ref_id' AND owner_uid = '$owner_uid'
							$dupcheck_qpart");
							
					// okay it doesn't exist - create user entry
					if (db_num_rows($result) == 0) {

						if ($filter_name != 'catchup') {
							$unread = 'true';
							$last_read_qpart = 'NULL';
						} else {
							$unread = 'false';
							$last_read_qpart = 'NOW()';
						}						

						if ($filter_name == 'mark') {
							$marked = 'true';
						} else {
							$marked = 'false';
						}
						
						$result = db_query($link,
							"INSERT INTO ttrss_user_entries 
								(ref_id, owner_uid, feed_id, unread, last_read, marked) 
							VALUES ('$ref_id', '$owner_uid', '$feed', $unread,
								$last_read_qpart, $marked)");
					}
					
					$post_needs_update = false;

					if (get_pref($link, "UPDATE_POST_ON_CHECKSUM_CHANGE", $owner_uid, false) &&
						($content_hash != $orig_content_hash)) {
						$post_needs_update = true;
					}

					if ($orig_title != $entry_title) {
						$post_needs_update = true;
					}

					if ($orig_num_comments != $num_comments) {
						$post_needs_update = true;
					}

//					this doesn't seem to be very reliable
//
//					if ($orig_timestamp != $entry_timestamp && !$orig_no_orig_date) {
//						$post_needs_update = true;
//					}

					// if post needs update, update it and mark all user entries 
					// linking to this post as updated					
					if ($post_needs_update) {

//						print "<!-- post $orig_title needs update : $post_needs_update -->";

						db_query($link, "UPDATE ttrss_entries 
							SET title = '$entry_title', content = '$entry_content',
								num_comments = '$num_comments'
							WHERE id = '$ref_id'");

						if (get_pref($link, "MARK_UNREAD_ON_UPDATE", $owner_uid, false)) {
							db_query($link, "UPDATE ttrss_user_entries 
								SET last_read = null, unread = true WHERE ref_id = '$ref_id'");
						} else {
							db_query($link, "UPDATE ttrss_user_entries 
								SET last_read = null WHERE ref_id = '$ref_id' AND unread = false");
						}

					}
				}

				db_query($link, "COMMIT");

				/* taaaags */
				// <a href="http://technorati.com/tag/Xorg" rel="tag">Xorg</a>, //

				$entry_tags = null;

				preg_match_all("/<a.*?href=.http:\/\/.*?technorati.com\/tag\/([^\"\'>]+)/i", 
					$entry_content_unescaped, $entry_tags);

//				print "<br>$entry_title : $entry_content_unescaped<br>";
//				print_r($entry_tags);
//				print "<br>";

				$entry_tags = $entry_tags[1];

				if (count($entry_tags) > 0) {
				
					db_query($link, "BEGIN");
			
					$result = db_query($link, "SELECT id,int_id 
						FROM ttrss_entries,ttrss_user_entries 
						WHERE guid = '$entry_guid' 
						AND feed_id = '$feed' AND ref_id = id
						AND owner_uid = '$owner_uid'");

					if (db_num_rows($result) == 1) {

						$entry_id = db_fetch_result($result, 0, "id");
						$entry_int_id = db_fetch_result($result, 0, "int_id");
						
						foreach ($entry_tags as $tag) {
							$tag = db_escape_string(strtolower($tag));

							$tag = str_replace("+", " ", $tag);	
							$tag = str_replace("technorati tag: ", "", $tag);
	
							$result = db_query($link, "SELECT id FROM ttrss_tags		
								WHERE tag_name = '$tag' AND post_int_id = '$entry_int_id' AND 
								owner_uid = '$owner_uid' LIMIT 1");
	
	//						print db_fetch_result($result, 0, "id");
	
							if ($result && db_num_rows($result) == 0) {
								
	//							print "tagging $entry_id as $tag<br>";
	
								db_query($link, "INSERT INTO ttrss_tags 
									(owner_uid,tag_name,post_int_id)
									VALUES ('$owner_uid','$tag', '$entry_int_id')");
							}							
						}
					}
					db_query($link, "COMMIT");
				} 
			} 

			db_query($link, "UPDATE ttrss_feeds 
				SET last_updated = NOW(), last_error = '' WHERE id = '$feed'");

//			db_query($link, "COMMIT");

		} else {
			$error_msg = db_escape_string(magpie_error());
			db_query($link, 
				"UPDATE ttrss_feeds SET last_error = '$error_msg', 
					last_updated = NOW() WHERE id = '$feed'");
		}

	}

	function print_select($id, $default, $values, $attributes = "") {
		print "<select name=\"$id\" id=\"$id\" $attributes>";
		foreach ($values as $v) {
			if ($v == $default)
				$sel = " selected";
			 else
			 	$sel = "";
			
			print "<option$sel>$v</option>";
		}
		print "</select>";
	}

	function print_select_hash($id, $default, $values, $attributes = "") {
		print "<select name=\"$id\" id='$id' $attributes>";
		foreach (array_keys($values) as $v) {
			if ($v == $default)
				$sel = "selected";
			 else
			 	$sel = "";
			
			print "<option $sel value=\"$v\">".$values[$v]."</option>";
		}

		print "</select>";
	}

	function get_filter_name($title, $content, $link, $filters) {

		if ($filters["title"]) {
			foreach ($filters["title"] as $filter) {
				$reg_exp = $filter["reg_exp"];			
				if (preg_match("/$reg_exp/i", $title)) {
					return $filter["action"];
				}
			}
		}

		if ($filters["content"]) {
			foreach ($filters["content"] as $filter) {
				$reg_exp = $filter["reg_exp"];			
				if (preg_match("/$reg_exp/i", $content)) {
					return $filter["action"];
				}		
			}
		}

		if ($filters["both"]) {
			foreach ($filters["both"] as $filter) {			
				$reg_exp = $filter["reg_exp"];		
				if (preg_match("/$reg_exp/i", $title) || 
					preg_match("/$reg_exp/i", $content)) {
					return $filter["action"];
				}
			}
		}

		if ($filters["link"]) {
			$reg_exp = $filter["reg_exp"];
			foreach ($filters["link"] as $filter) {
				$reg_exp = $filter["reg_exp"];
				if (preg_match("/$reg_exp/i", $link)) {
					return $filter["action"];
				}
			}
		}

		return false;
	}

	function printFeedEntry($feed_id, $class, $feed_title, $unread, $icon_file, $link,
		$rtl_content = false, $last_updated = false, $last_error = false) {

		if (file_exists($icon_file) && filesize($icon_file) > 0) {
				$feed_icon = "<img id=\"FIMG-$feed_id\" src=\"$icon_file\">";
		} else {
			$feed_icon = "<img id=\"FIMG-$feed_id\" src=\"images/blank_icon.gif\">";
		}

		if ($rtl_content) {
			$rtl_tag = "dir=\"rtl\"";
		} else {
			$rtl_tag = "dir=\"ltr\"";
		}

		$error_notify_msg = "";
		
		if ($last_error) {
			$link_title = "Error: $last_error ($last_updated)";
			$error_notify_msg = "(Error)";
		} else if ($last_updated) {
			$link_title = "Updated: $last_updated";
		}

		$feed = "<a title=\"$link_title\" id=\"FEEDL-$feed_id\" href=\"javascript:viewfeed('$feed_id', '', false);\">$feed_title</a>";

		print "<li id=\"FEEDR-$feed_id\" class=\"$class\">";
		if (get_pref($link, 'ENABLE_FEED_ICONS')) {
			print "$feed_icon";
		}

		print "<span $rtl_tag id=\"FEEDN-$feed_id\">$feed</span>";

		if ($unread != 0) {
			$fctr_class = "";
		} else {
			$fctr_class = "class=\"invisible\"";
		}

		print " <span $rtl_tag $fctr_class id=\"FEEDCTR-$feed_id\">
			 (<span id=\"FEEDU-$feed_id\">$unread</span>)</span>";

		if (get_pref($link, "EXTENDED_FEEDLIST")) {		 	 
			print "<div class=\"feedExtInfo\">
				<span id=\"FLUPD-$feed_id\">$last_updated $error_notify_msg</span></div>";
		}
		 	 
		print "</li>";

	}

	function getmicrotime() {
		list($usec, $sec) = explode(" ",microtime());
		return ((float)$usec + (float)$sec);
	}

	function print_radio($id, $default, $values, $attributes = "") {
		foreach ($values as $v) {
		
			if ($v == $default)
				$sel = "checked";
			 else
			 	$sel = "";

			if ($v == "Yes") {
				$sel .= " value=\"1\"";
			} else {
				$sel .= " value=\"0\"";
			}
			
			print "<input class=\"noborder\" 
				type=\"radio\" $sel $attributes name=\"$id\">&nbsp;$v&nbsp;";

		}
	}

	function initialize_user_prefs($link, $uid) {

		$uid = db_escape_string($uid);

		db_query($link, "BEGIN");

		$result = db_query($link, "SELECT pref_name,def_value FROM ttrss_prefs");
		
		$u_result = db_query($link, "SELECT pref_name 
			FROM ttrss_user_prefs WHERE owner_uid = '$uid'");

		$active_prefs = array();

		while ($line = db_fetch_assoc($u_result)) {
			array_push($active_prefs, $line["pref_name"]);			
		}

		while ($line = db_fetch_assoc($result)) {
			if (array_search($line["pref_name"], $active_prefs) === FALSE) {
//				print "adding " . $line["pref_name"] . "<br>";

				db_query($link, "INSERT INTO ttrss_user_prefs
					(owner_uid,pref_name,value) VALUES 
					('$uid', '".$line["pref_name"]."','".$line["def_value"]."')");

			}
		}

		db_query($link, "COMMIT");

	}

	function lookup_user_id($link, $user) {

		$result = db_query($link, "SELECT id FROM ttrss_users WHERE 
			login = '$login'");

		if (db_num_rows($result) == 1) {
			return db_fetch_result($result, 0, "id");
		} else {
			return false;
		}
	}

	function http_authenticate_user($link) {

		if (!$_SERVER["PHP_AUTH_USER"]) {

			header('WWW-Authenticate: Basic realm="Tiny Tiny RSS RSSGen"');
			header('HTTP/1.0 401 Unauthorized');
			exit;
					
		} else {
			$auth_result = authenticate_user($link, 
				$_SERVER["PHP_AUTH_USER"], $_SERVER["PHP_AUTH_PW"]);

			if (!$auth_result) {
				header('WWW-Authenticate: Basic realm="Tiny Tiny RSS RSSGen"');
				header('HTTP/1.0 401 Unauthorized');
				exit;
			}
		}

		return true;
	}

	function authenticate_user($link, $login, $password) {

		if (!SINGLE_USER_MODE) {

			$pwd_hash = 'SHA1:' . sha1($password);
	
			$result = db_query($link, "SELECT id,login,access_level FROM ttrss_users WHERE 
				login = '$login' AND pwd_hash = '$pwd_hash'");
	
			if (db_num_rows($result) == 1) {
				$_SESSION["uid"] = db_fetch_result($result, 0, "id");
				$_SESSION["name"] = db_fetch_result($result, 0, "login");
				$_SESSION["access_level"] = db_fetch_result($result, 0, "access_level");
	
				db_query($link, "UPDATE ttrss_users SET last_login = NOW() WHERE id = " . 
					$_SESSION["uid"]);
	
				$user_theme = get_user_theme_path($link);
	
				$_SESSION["theme"] = $user_theme;
				$_SESSION["ip_address"] = $_SERVER["REMOTE_ADDR"];
	
				initialize_user_prefs($link, $_SESSION["uid"]);
	
				return true;
			}
	
			return false;

		} else {

			$_SESSION["uid"] = 1;
			$_SESSION["name"] = "admin";

			$user_theme = get_user_theme_path($link);
	
			$_SESSION["theme"] = $user_theme;
			$_SESSION["ip_address"] = $_SERVER["REMOTE_ADDR"];
	
			initialize_user_prefs($link, $_SESSION["uid"]);
	
			return true;
		}
	}

	function make_password($length = 8) {

		$password = "";
		$possible = "0123456789abcdfghjkmnpqrstvwxyzABCDFGHJKMNPQRSTVWXYZ"; 
		
   	$i = 0; 
    
		while ($i < $length) { 
			$char = substr($possible, mt_rand(0, strlen($possible)-1), 1);
        
			if (!strstr($password, $char)) { 
				$password .= $char;
				$i++;
			}
		}
		return $password;
	}

	// this is called after user is created to initialize default feeds, labels
	// or whatever else
	
	// user preferences are checked on every login, not here

	function initialize_user($link, $uid) {

		db_query($link, "insert into ttrss_labels (owner_uid,sql_exp,description) 
			values ('$uid','unread = true', 'Unread articles')");

		db_query($link, "insert into ttrss_labels (owner_uid,sql_exp,description) 
			values ('$uid','last_read is null and unread = false', 'Updated articles')");
		
		db_query($link, "insert into ttrss_feeds (owner_uid,title,feed_url)
			values ('$uid', 'Tiny Tiny RSS: New Releases',
			'http://tt-rss.spb.ru/releases.rss')");

	}

	function logout_user() {
		session_destroy();
		if (isset($_COOKIE[session_name()])) {
		   setcookie(session_name(), '', time()-42000, '/');
		}
	}

	function get_script_urlpath() {
		return preg_replace('/\/[^\/]*$/', "", $_SERVER["REQUEST_URI"]);
	}

	function get_login_redirect() {
		$server = $_SERVER["SERVER_NAME"];

		if (ENABLE_LOGIN_SSL) {
			$protocol = "https";
		} else {
			$protocol = "http";
		}		

		$url_path = get_script_urlpath();

		$redirect_uri = "$protocol://$server$url_path/login.php";

		return $redirect_uri;
	}

	function validate_session($link) {
		if (SESSION_CHECK_ADDRESS && $_SESSION["uid"]) {
			if ($_SESSION["ip_address"]) {
				if ($_SESSION["ip_address"] != $_SERVER["REMOTE_ADDR"]) {
					return false;
				}
			}
		}
		return true;
	}

	function basic_nosid_redirect_check() {
		if (!SINGLE_USER_MODE) {
			if (!$_COOKIE[get_session_cookie_name()]) {
				$redirect_uri = get_login_redirect();
				$return_to = preg_replace('/.*?\//', '', $_SERVER["REQUEST_URI"]);
				header("Location: $redirect_uri?rt=$return_to");
				exit;
			}
		}
	}

	function login_sequence($link) {
		if (!SINGLE_USER_MODE) {

			if (!validate_session($link)) {
				logout_user();
				$redirect_uri = get_login_redirect();
				$return_to = preg_replace('/.*?\//', '', $_SERVER["REQUEST_URI"]);
				header("Location: $redirect_uri?rt=$return_to");
				exit;
			}

			if (!USE_HTTP_AUTH) {
				if (!$_SESSION["uid"]) {
					$redirect_uri = get_login_redirect();
					$return_to = preg_replace('/.*?\//', '', $_SERVER["REQUEST_URI"]);
					header("Location: $redirect_uri?rt=$return_to");
					exit;
				}
			} else {
				if (!$_SESSION["uid"]) {
					if (!$_SERVER["PHP_AUTH_USER"]) {

						header('WWW-Authenticate: Basic realm="Tiny Tiny RSS"');
						header('HTTP/1.0 401 Unauthorized');
						exit;
						
					} else {
						$auth_result = authenticate_user($link, 
							$_SERVER["PHP_AUTH_USER"], $_SERVER["PHP_AUTH_PW"]);

						if (!$auth_result) {
							header('WWW-Authenticate: Basic realm="Tiny Tiny RSS"');
							header('HTTP/1.0 401 Unauthorized');
							exit;
						}
					}
				}				
			}
		} else {
			return authenticate_user($link, "admin", null);
		}
	}

	function truncate_string($str, $max_len) {
		if (mb_strlen($str, "utf-8") > $max_len - 3) {
			return mb_substr($str, 0, $max_len, "utf-8") . "...";
		} else {
			return $str;
		}
	}

	function get_user_theme_path($link) {
		$result = db_query($link, "SELECT theme_path 
			FROM 
				ttrss_themes,ttrss_users
			WHERE ttrss_themes.id = theme_id AND ttrss_users.id = " . $_SESSION["uid"]);
		if (db_num_rows($result) != 0) {
			return db_fetch_result($result, 0, "theme_path");
		} else {
			return null;
		}
	}

	function smart_date_time($timestamp) {
		if (date("Y.m.d", $timestamp) == date("Y.m.d")) {
			return date("G:i", $timestamp);
		} else if (date("Y", $timestamp) == date("Y")) {
			return date("M d, G:i", $timestamp);
		} else {
			return date("Y/m/d G:i", $timestamp);
		}
	}

	function smart_date($timestamp) {
		if (date("Y.m.d", $timestamp) == date("Y.m.d")) {
			return "Today";
		} else if (date("Y", $timestamp) == date("Y")) {
			return date("D m", $timestamp);
		} else {
			return date("Y/m/d", $timestamp);
		}
	}

	function sql_bool_to_string($s) {
		if ($s == "t" || $s == "1") {
			return "true";
		} else {
			return "false";
		}
	}

	function sql_bool_to_bool($s) {
		if ($s == "t" || $s == "1") {
			return true;
		} else {
			return false;
		}
	}
	

	function toggleEvenOdd($a) {
		if ($a == "even") 
			return "odd";
		else
			return "even";
	}

	function sanity_check($link) {

		error_reporting(0);

		$error_code = 0;
		$result = db_query($link, "SELECT schema_version FROM ttrss_version");
		$schema_version = db_fetch_result($result, 0, "schema_version");

		if ($schema_version != SCHEMA_VERSION) {
			$error_code = 5;
		}

		if (DB_TYPE == "mysql") {
			$result = db_query($link, "SELECT true", false);
			if (db_num_rows($result) != 1) {
				$error_code = 10;
			}
		}

		error_reporting (DEFAULT_ERROR_LEVEL);

		if ($error_code != 0) {
			print_error_xml($error_code);
			return false;
		} else {
			return true;
		} 
	}

	function file_is_locked($filename) {
		error_reporting(0);
		$fp = fopen($filename, "r");
		error_reporting(DEFAULT_ERROR_LEVEL);
		if ($fp) {
			if (flock($fp, LOCK_EX | LOCK_NB)) {
				flock($fp, LOCK_UN);
				fclose($fp);
				return false;
			}
			fclose($fp);
			return true;
		}
		return false;
	}

	function make_lockfile($filename) {
		$fp = fopen($filename, "w");

		if (flock($fp, LOCK_EX | LOCK_NB)) {		
			return $fp;
		} else {
			return false;
		}
	}

	function sql_random_function() {
		if (DB_TYPE == "mysql") {
			return "RAND()";
		} else {
			return "RANDOM()";
		}
	}

	function catchup_feed($link, $feed, $cat_view) {

			if (preg_match("/^-?[0-9][0-9]*$/", $feed) != false) {
			
				if ($cat_view) {

					if ($feed > 0) {
						$cat_qpart = "cat_id = '$feed'";
					} else {
						$cat_qpart = "cat_id IS NULL";
					}
					
					$tmp_result = db_query($link, "SELECT id 
						FROM ttrss_feeds WHERE $cat_qpart AND owner_uid = " . 
						$_SESSION["uid"]);

					while ($tmp_line = db_fetch_assoc($tmp_result)) {

						$tmp_feed = $tmp_line["id"];

						db_query($link, "UPDATE ttrss_user_entries 
							SET unread = false,last_read = NOW() 
							WHERE feed_id = '$tmp_feed' AND owner_uid = " . $_SESSION["uid"]);
					}

				} else if ($feed > 0) {

					$tmp_result = db_query($link, "SELECT id 
						FROM ttrss_feeds WHERE parent_feed = '$feed'
						ORDER BY cat_id,title");

					$parent_ids = array();

					if (db_num_rows($tmp_result) > 0) {
						while ($p = db_fetch_assoc($tmp_result)) {
							array_push($parent_ids, "feed_id = " . $p["id"]);
						}

						$children_qpart = implode(" OR ", $parent_ids);
						
						db_query($link, "UPDATE ttrss_user_entries 
							SET unread = false,last_read = NOW() 
							WHERE (feed_id = '$feed' OR $children_qpart) 
							AND owner_uid = " . $_SESSION["uid"]);

					} else {						
						db_query($link, "UPDATE ttrss_user_entries 
							SET unread = false,last_read = NOW() 
							WHERE feed_id = '$feed' AND owner_uid = " . $_SESSION["uid"]);
					}
						
				} else if ($feed < 0 && $feed > -10) { // special, like starred

					if ($feed == -1) {
						db_query($link, "UPDATE ttrss_user_entries 
							SET unread = false,last_read = NOW()
							WHERE marked = true AND owner_uid = ".$_SESSION["uid"]);
					}
			
				} else if ($feed < -10) { // label

					// TODO make this more efficient

					$label_id = -$feed - 11;

					$tmp_result = db_query($link, "SELECT sql_exp FROM ttrss_labels
						WHERE id = '$label_id'");					

					if ($tmp_result) {
						$sql_exp = db_fetch_result($tmp_result, 0, "sql_exp");

						db_query($link, "BEGIN");

						$tmp2_result = db_query($link,
							"SELECT 
								int_id 
							FROM 
								ttrss_user_entries,ttrss_entries,ttrss_feeds
							WHERE
								ref_id = ttrss_entries.id AND 
								ttrss_user_entries.feed_id = ttrss_feeds.id AND
								$sql_exp AND
								ttrss_user_entries.owner_uid = " . $_SESSION["uid"]);

						while ($tmp_line = db_fetch_assoc($tmp2_result)) {
							db_query($link, "UPDATE 
								ttrss_user_entries 
							SET 
								unread = false, last_read = NOW()
							WHERE
								int_id = " . $tmp_line["int_id"]);
						}
								
						db_query($link, "COMMIT");

/*						db_query($link, "UPDATE ttrss_user_entries,ttrss_entries 
							SET unread = false,last_read = NOW()
							WHERE $sql_exp
							AND ref_id = id
							AND owner_uid = ".$_SESSION["uid"]); */
					}
				}
			} else { // tag
				db_query($link, "BEGIN");

				$tag_name = db_escape_string($feed);

				$result = db_query($link, "SELECT post_int_id FROM ttrss_tags
					WHERE tag_name = '$tag_name' AND owner_uid = " . $_SESSION["uid"]);

				while ($line = db_fetch_assoc($result)) {
					db_query($link, "UPDATE ttrss_user_entries SET
						unread = false, last_read = NOW() 
						WHERE int_id = " . $line["post_int_id"]);
				}
				db_query($link, "COMMIT");
			}
	}

	function update_generic_feed($link, $feed, $cat_view) {
			if ($cat_view) {

				if ($feed > 0) {
					$cat_qpart = "cat_id = '$feed'";
				} else {
					$cat_qpart = "cat_id IS NULL";
				}
				
				$tmp_result = db_query($link, "SELECT feed_url FROM ttrss_feeds
					WHERE $cat_qpart AND owner_uid = " . $_SESSION["uid"]);

				while ($tmp_line = db_fetch_assoc($tmp_result)) {					
					$feed_url = $tmp_line["feed_url"];
					update_rss_feed($link, $feed_url, $feed, ENABLE_UPDATE_DAEMON);
				}

			} else {
				$tmp_result = db_query($link, "SELECT feed_url FROM ttrss_feeds
					WHERE id = '$feed'");
				$feed_url = db_fetch_result($tmp_result, 0, "feed_url");				
				update_rss_feed($link, $feed_url, $feed, ENABLE_UPDATE_DAEMON);
			}
	}

	function getAllCounters($link) {
		getLabelCounters($link);
		getFeedCounters($link);
		getTagCounters($link);
		getGlobalCounters($link);
		if (get_pref($link, 'ENABLE_FEED_CATS')) {
			getCategoryCounters($link);
		}
	}	

	function getCategoryCounters($link) {
		$result = db_query($link, "SELECT cat_id,SUM((SELECT COUNT(int_id) 
				FROM ttrss_user_entries WHERE feed_id = ttrss_feeds.id 
					AND unread = true)) AS unread FROM ttrss_feeds 
			WHERE 
				hidden = false AND owner_uid = ".$_SESSION["uid"]." GROUP BY cat_id");

		while ($line = db_fetch_assoc($result)) {
			$line["cat_id"] = sprintf("%d", $line["cat_id"]);
			print "<counter type=\"category\" id=\"".$line["cat_id"]."\" counter=\"".
				$line["unread"]."\"/>";
		}
	}

	function getCategoryUnread($link, $cat) {

		if ($cat != 0) {
			$cat_query = "cat_id = '$cat'";
		} else {
			$cat_query = "cat_id IS NULL";
		}

		$result = db_query($link, "SELECT id FROM ttrss_feeds WHERE $cat_query 
				AND hidden = false
				AND owner_uid = " . $_SESSION["uid"]);

		$cat_feeds = array();
		while ($line = db_fetch_assoc($result)) {
			array_push($cat_feeds, "feed_id = " . $line["id"]);
		}

		if (count($cat_feeds) == 0) return 0;

		$match_part = implode(" OR ", $cat_feeds);

		$result = db_query($link, "SELECT COUNT(int_id) AS unread 
			FROM ttrss_user_entries 
			WHERE	unread = true AND ($match_part) AND owner_uid = " . $_SESSION["uid"]);

		$unread = 0;

		# this needs to be rewritten
		while ($line = db_fetch_assoc($result)) {
			$unread += $line["unread"];
		}

		return $unread;

	}

	function getFeedUnread($link, $feed, $is_cat = false) {
		$n_feed = sprintf("%d", $feed);

		if ($is_cat) {
			return getCategoryUnread($link, $n_feed);		
		} else if ($n_feed == -1) {
			$match_part = "marked = true";
		} else if ($n_feed > 0) {

			$result = db_query($link, "SELECT id FROM ttrss_feeds WHERE parent_feed = '$n_feed'
					AND hidden = false
					AND owner_uid = " . $_SESSION["uid"]);

			if (db_num_rows($result) > 0) {

				$linked_feeds = array();
				while ($line = db_fetch_assoc($result)) {
					array_push($linked_feeds, "feed_id = " . $line["id"]);
				}
				
				$match_part = implode(" OR ", $linked_feeds);

				$result = db_query($link, "SELECT COUNT(int_id) AS unread 
					FROM ttrss_user_entries
					WHERE	unread = true AND ($match_part) AND owner_uid = " . $_SESSION["uid"]);

				$unread = 0;

				# this needs to be rewritten
				while ($line = db_fetch_assoc($result)) {
					$unread += $line["unread"];
				}

				return $unread;

			} else {
				$match_part = "feed_id = '$n_feed'";
			}
		} else if ($feed < -10) {

			$label_id = -$feed - 11;

			$result = db_query($link, "SELECT sql_exp FROM ttrss_labels WHERE
				id = '$label_id' AND owner_uid = " . $_SESSION["uid"]);

			$match_part = db_fetch_result($result, 0, "sql_exp");
		}

		if ($match_part) {
		
			$result = db_query($link, "SELECT count(int_id) AS unread 
				FROM ttrss_user_entries,ttrss_feeds,ttrss_entries WHERE
				ttrss_user_entries.feed_id = ttrss_feeds.id AND
				ttrss_user_entries.ref_id = ttrss_entries.id AND 
				ttrss_feeds.hidden = false AND
				unread = true AND ($match_part) AND ttrss_user_entries.owner_uid = " . $_SESSION["uid"]);
				
		} else {
		
			$result = db_query($link, "SELECT COUNT(post_int_id) AS unread
				FROM ttrss_tags,ttrss_user_entries 
				WHERE tag_name = '$feed' AND post_int_id = int_id AND unread = true AND
					ttrss_tags.owner_uid = " . $_SESSION["uid"]);
		}
		
		$unread = db_fetch_result($result, 0, "unread");

		return $unread;
	}

	/* FIXME this needs reworking */

	function getGlobalUnread($link) {
		$result = db_query($link, "SELECT count(ttrss_entries.id) as c_id FROM ttrss_entries,ttrss_user_entries,ttrss_feeds
			WHERE unread = true AND 
			ttrss_user_entries.feed_id = ttrss_feeds.id AND
			ttrss_user_entries.ref_id = ttrss_entries.id AND 
			hidden = false AND
			ttrss_user_entries.owner_uid = " . $_SESSION["uid"]);
		$c_id = db_fetch_result($result, 0, "c_id");
		return $c_id;
	}

	function getGlobalCounters($link, $global_unread = -1) {
		if ($global_unread == -1) {	
			$global_unread = getGlobalUnread($link);
		}
		print "<counter type=\"global\" id='global-unread' 
			counter='$global_unread'/>";

		$result = db_query($link, "SELECT COUNT(id) AS fn FROM 
			ttrss_feeds WHERE owner_uid = " . $_SESSION["uid"]);

		$subscribed_feeds = db_fetch_result($result, 0, "fn");

		print "<counter type=\"global\" id='subscribed-feeds' 
			counter='$subscribed_feeds'/>";

	}

	function getTagCounters($link, $smart_mode = SMART_RPC_COUNTERS) {

		if ($smart_mode) {
			if (!$_SESSION["tctr_last_value"]) {
				$_SESSION["tctr_last_value"] = array();
			}
		}

		$old_counters = $_SESSION["tctr_last_value"];

		$tctrs_modified = false;

/*		$result = db_query($link, "SELECT tag_name,count(ttrss_entries.id) AS count
			FROM ttrss_tags,ttrss_entries,ttrss_user_entries WHERE
			ttrss_user_entries.ref_id = ttrss_entries.id AND 
			ttrss_tags.owner_uid = ".$_SESSION["uid"]." AND
			post_int_id = ttrss_user_entries.int_id AND unread = true GROUP BY tag_name 
		UNION
			select tag_name,0 as count FROM ttrss_tags
			WHERE ttrss_tags.owner_uid = ".$_SESSION["uid"]); */

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

			$tag = htmlspecialchars($tag);

			if (!$smart_mode || $old_counters[$tag] != $unread) {			
				$old_counters[$tag] = $unread;
				$tctrs_modified = true;
				print "<counter type=\"tag\" id=\"$tag\" counter=\"$unread\"/>";
			}

		} 

		if ($smart_mode && $tctrs_modified) {
			$_SESSION["tctr_last_value"] = $old_counters;
		}

	}

	function getLabelCounters($link, $smart_mode = SMART_RPC_COUNTERS, $ret_mode = false) {

		if ($smart_mode) {
			if (!$_SESSION["lctr_last_value"]) {
				$_SESSION["lctr_last_value"] = array();
			}
		}

		$ret_arr = array();
		
		$old_counters = $_SESSION["lctr_last_value"];
		$lctrs_modified = false;

		$result = db_query($link, "SELECT count(ttrss_entries.id) as count FROM ttrss_entries,ttrss_user_entries,ttrss_feeds
			WHERE marked = true AND ttrss_user_entries.ref_id = ttrss_entries.id AND 
			ttrss_user_entries.feed_id = ttrss_feeds.id AND
			unread = true AND ttrss_user_entries.owner_uid = ".$_SESSION["uid"]);

		$count = db_fetch_result($result, 0, "count");

		if (!$ret_mode) {
			print "<counter type=\"label\" id=\"-1\" counter=\"$count\"/>";
		} else {
			$ret_arr["-1"]["counter"] = $count;
			$ret_arr["-1"]["description"] = "Starred";
		}

		$result = db_query($link, "SELECT owner_uid,id,sql_exp,description FROM
			ttrss_labels WHERE owner_uid = ".$_SESSION["uid"]." ORDER by description");
	
		while ($line = db_fetch_assoc($result)) {

			$id = -$line["id"] - 11;

			$label_name = $line["description"];

			error_reporting (0);

			$tmp_result = db_query($link, "SELECT count(ttrss_entries.id) as count FROM ttrss_user_entries,ttrss_entries,ttrss_feeds
				WHERE (" . $line["sql_exp"] . ") AND unread = true AND 
				ttrss_feeds.hidden = false AND
				ttrss_user_entries.feed_id = ttrss_feeds.id AND
				ttrss_user_entries.ref_id = ttrss_entries.id AND 
				ttrss_user_entries.owner_uid = ".$_SESSION["uid"]);

			$count = db_fetch_result($tmp_result, 0, "count");

			if (!$smart_mode || $old_counters[$id] != $count) {	
				$old_counters[$id] = $count;
				$lctrs_modified = true;
				if (!$ret_mode) {
					print "<counter type=\"label\" id=\"$id\" counter=\"$count\"/>";
				} else {
					$ret_arr[$id]["counter"] = $count;
					$ret_arr[$id]["description"] = $label_name;
				}
			}

			error_reporting (DEFAULT_ERROR_LEVEL);
		}

		if ($smart_mode && $lctrs_modified) {
			$_SESSION["lctr_last_value"] = $old_counters;
		}

		return $ret_arr;
	}

/*	function getFeedCounter($link, $id) {
	
		$result = db_query($link, "SELECT 
				count(id) as count,last_error
			FROM ttrss_entries,ttrss_user_entries,ttrss_feeds
			WHERE feed_id = '$id' AND unread = true
			AND ttrss_user_entries.feed_id = ttrss_feeds.id
			AND ttrss_user_entries.ref_id = ttrss_entries.id");
	
			$count = db_fetch_result($result, 0, "count");
			$last_error = htmlspecialchars(db_fetch_result($result, 0, "last_error"));
			
			print "<counter type=\"feed\" id=\"$id\" counter=\"$count\" error=\"$last_error\"/>";		
	} */

	function getFeedCounters($link, $smart_mode = SMART_RPC_COUNTERS) {

		if ($smart_mode) {
			if (!$_SESSION["fctr_last_value"]) {
				$_SESSION["fctr_last_value"] = array();
			}
		}

		$old_counters = $_SESSION["fctr_last_value"];

		$result = db_query($link, "SELECT id,last_error,parent_feed,
			SUBSTRING(last_updated,1,19) AS last_updated,
			(SELECT count(id) 
				FROM ttrss_entries,ttrss_user_entries 
				WHERE feed_id = ttrss_feeds.id AND 
					ttrss_user_entries.ref_id = ttrss_entries.id
				AND unread = true AND owner_uid = ".$_SESSION["uid"].") as count
			FROM ttrss_feeds WHERE owner_uid = ".$_SESSION["uid"] . "
				AND parent_feed IS NULL");

		$fctrs_modified = false;

		$short_date = get_pref($link, 'SHORT_DATE_FORMAT');

		while ($line = db_fetch_assoc($result)) {
		
			$id = $line["id"];
			$count = $line["count"];
			$last_error = htmlspecialchars($line["last_error"]);

			if (get_pref($link, 'HEADLINES_SMART_DATE')) {
				$last_updated = smart_date_time(strtotime($line["last_updated"]));
			} else {
				$last_updated = date($short_date, strtotime($line["last_updated"]));
			}				

			$has_img = is_file(ICONS_DIR . "/$id.ico");

			$tmp_result = db_query($link,
				"SELECT id,COUNT(unread) AS unread
				FROM ttrss_feeds LEFT JOIN ttrss_user_entries 
					ON (ttrss_feeds.id = ttrss_user_entries.feed_id) 
				WHERE parent_feed = '$id' AND unread = true GROUP BY ttrss_feeds.id");
			
			if (db_num_rows($tmp_result) > 0) {				
				while ($l = db_fetch_assoc($tmp_result)) {
					$count += $l["unread"];
				}
			}

			if (!$smart_mode || $old_counters[$id] != $count) {
				$old_counters[$id] = $count;
				$fctrs_modified = true;

				if ($last_error) {
					$error_part = "error=\"$last_error\"";
				} else {
					$error_part = "";
				}

				if ($has_img) {
					$has_img_part = "hi=\"$has_img\"";
				} else {
					$has_img_part = "";
				}				

				print "<counter type=\"feed\" id=\"$id\" counter=\"$count\" $has_img_part $error_part updated=\"$last_updated\"/>";
			}
		}

		if ($smart_mode && $fctrs_modified) {
			$_SESSION["fctr_last_value"] = $old_counters;
		}
	}

	function get_script_dt_add() {
		if (strpos(VERSION, "99") === false) {
			return VERSION;
		} else {
			return time();
		}
	}

	function get_pgsql_version($link) {
		$result = db_query($link, "SELECT version() AS version");
		$version = split(" ", db_fetch_result($result, 0, "version"));
		return $version[1];
	}

	function print_error_xml($code, $add_msg = "") {
		global $ERRORS;

		$error_msg = $ERRORS[$code];
		
		if ($add_msg) {
			$error_msg = "$error_msg; $add_msg";
		}
		
		print "<rpc-reply>";
		print "<error error-code=\"$code\" error-msg=\"$error_msg\"/>";
		print "</rpc-reply>";
	}

	function subscribe_to_feed($link, $feed_link, $cat_id = 0) {
	
		if ($cat_id == "0" || !$cat_id) {
			$cat_qpart = "NULL";
		} else {
			$cat_qpart = "'$cat_id'";
		}
	
		$result = db_query($link,
			"SELECT id FROM ttrss_feeds 
			WHERE feed_url = '$feed_link' AND owner_uid = ".$_SESSION["uid"]);
	
		if (db_num_rows($result) == 0) {
			
			$result = db_query($link,
				"INSERT INTO ttrss_feeds (owner_uid,feed_url,title,cat_id) 
				VALUES ('".$_SESSION["uid"]."', '$feed_link', 
				'[Unknown]', $cat_qpart)");
	
			$result = db_query($link,
				"SELECT id FROM ttrss_feeds WHERE feed_url = '$feed_link' 
				AND owner_uid = " . $_SESSION["uid"]);
	
			$feed_id = db_fetch_result($result, 0, "id");
	
			if ($feed_id) {
				update_rss_feed($link, $feed_link, $feed_id, true);
			}

			return true;
		} else {
			return false;
		}
	}

	function print_feed_select($link, $id, $default_id = "", 
		$attributes = "", $include_all_feeds = true) {

		print "<select id=\"$id\" name=\"$id\" $attributes>";
		if ($include_all_feeds) { 
			print "<option value=\"0\">All feeds</option>";
		}
	
		$result = db_query($link, "SELECT id,title FROM ttrss_feeds
			WHERE owner_uid = ".$_SESSION["uid"]." ORDER BY title");

		if (db_num_rows($result) > 0 && $include_all_feeds) {
			print "<option disabled>--------</option>";
		}

		while ($line = db_fetch_assoc($result)) {
			if ($line["id"] == $default_id) {
				$is_selected = "selected";
			} else {
				$is_selected = "";
			}
			printf("<option $is_selected value='%d'>%s</option>", 
				$line["id"], htmlspecialchars(db_unescape_string($line["title"])));
		}
	
		print "</select>";
	}

	function print_feed_cat_select($link, $id, $default_id = "", 
		$attributes = "", $include_all_cats = true) {
		
		print "<select id=\"$id\" name=\"$id\" $attributes>";

		if ($include_all_cats) {
			print "<option value=\"0\">Uncategorized</option>";
		}

		$result = db_query($link, "SELECT id,title FROM ttrss_feed_categories
			WHERE owner_uid = ".$_SESSION["uid"]." ORDER BY title");

		if (db_num_rows($result) > 0 && $include_all_cats) {
			print "<option disabled>--------</option>";
		}

		while ($line = db_fetch_assoc($result)) {
			if ($line["id"] == $default_id) {
				$is_selected = "selected";
			} else {
				$is_selected = "";
			}
			printf("<option $is_selected value='%d'>%s</option>", 
				$line["id"], htmlspecialchars(db_unescape_string($line["title"])));
		}

		print "</select>";
	}
	
	function checkbox_to_sql_bool($val) {
		return ($val == "on") ? "true" : "false";
	}

	function getFeedCatTitle($link, $id) {
		if ($id == -1) {
			return "Special";
		} else if ($id < -10) {
			return "Labels";
		} else if ($id > 0) {
			$result = db_query($link, "SELECT ttrss_feed_categories.title 
				FROM ttrss_feeds, ttrss_feed_categories WHERE ttrss_feeds.id = '$id' AND
					cat_id = ttrss_feed_categories.id");
			if (db_num_rows($result) == 1) {
				return db_fetch_result($result, 0, "title");
			} else {
				return "Uncategorized";
			}
		} else {
			return "getFeedCatTitle($id) failed";
		}

	}

	function getFeedTitle($link, $id) {
		if ($id == -1) {
			return "Starred articles";
		} else if ($id < -10) {
			$label_id = -10 - $id;
			$result = db_query($link, "SELECT description FROM ttrss_labels WHERE id = '$label_id'");
			if (db_num_rows($result) == 1) {
				return db_fetch_result($result, 0, "description");
			} else {
				return "Unknown label ($label_id)";
			}

		} else if ($id > 0) {
			$result = db_query($link, "SELECT title FROM ttrss_feeds WHERE id = '$id'");
			if (db_num_rows($result) == 1) {
				return db_fetch_result($result, 0, "title");
			} else {
				return "Unknown feed ($id)";
			}
		} else {
			return "getFeedTitle($id) failed";
		}

	}

	function get_session_cookie_name() {
		return ((!defined('TTRSS_SESSION_NAME')) ? "ttrss_sid" : TTRSS_SESSION_NAME);
	}

	function print_init_params($link) {
		print "<init-params>";
		if ($_SESSION["stored-params"]) {
			foreach (array_keys($_SESSION["stored-params"]) as $key) {
				if ($key) {
					$value = htmlspecialchars($_SESSION["stored-params"][$key]);
					print "<param key=\"$key\" value=\"$value\"/>";
				}
			}
		}

		print "<param key=\"daemon_enabled\" value=\"" . ENABLE_UPDATE_DAEMON . "\"/>";
		print "<param key=\"feeds_frame_refresh\" value=\"" . FEEDS_FRAME_REFRESH . "\"/>";
		print "<param key=\"daemon_refresh_only\" value=\"" . DAEMON_REFRESH_ONLY . "\"/>";

		print "<param key=\"on_catchup_show_next_feed\" value=\"" . 
			get_pref($link, "ON_CATCHUP_SHOW_NEXT_FEED") . "\"/>";

		print "<param key=\"hide_read_feeds\" value=\"" . 
			sprintf("%d", get_pref($link, "HIDE_READ_FEEDS")) . "\"/>";

		print "<param key=\"feeds_sort_by_unread\" value=\"" . 
			sprintf("%d", get_pref($link, "FEEDS_SORT_BY_UNREAD")) . "\"/>";

		print "</init-params>";
	}

	function print_runtime_info($link) {
		print "<runtime-info>";
		if (ENABLE_UPDATE_DAEMON) {
			print "<param key=\"daemon_is_running\" value=\"".
				sprintf("%d", file_is_locked("update_daemon.lock")) . "\"/>";
		}
		print "</runtime-info>";
	}

	function getSearchSql($search, $match_on) {

		$search_query_part = "";

		$keywords = split(" ", $search);
		$query_keywords = array();

		if ($match_on == "both") {

			foreach ($keywords as $k) {
				array_push($query_keywords, "(UPPER(ttrss_entries.title) LIKE UPPER('%$k%')
					OR UPPER(ttrss_entries.content) LIKE UPPER('%$k%'))");
			}

			$search_query_part = implode("AND", $query_keywords) . " AND ";

		} else if ($match_on == "title") {

			foreach ($keywords as $k) {
				array_push($query_keywords, "(UPPER(ttrss_entries.title) LIKE UPPER('%$k%'))");
			}

			$search_query_part = implode("AND", $query_keywords) . " AND ";

		} else if ($match_on == "content") {

			foreach ($keywords as $k) {
				array_push($query_keywords, "(UPPER(ttrss_entries.content) LIKE UPPER('%$k%'))");
			}
		}

		$search_query_part = implode("AND", $query_keywords);

		return $search_query_part;
	}

	function queryFeedHeadlines($link, $feed, $limit, $view_mode, $cat_view, $search, $search_mode, $match_on, $override_order = false) {
			if ($search) {
			
				$search_query_part = getSearchSql($search, $match_on);
				$search_query_part .= " AND ";

			} else {
				$search_query_part = "";
			}

			$view_query_part = "";
	
			if ($view_mode == "adaptive") {
				if ($search) {
					$view_query_part = " ";
				} else if ($feed != -1) {
					$unread = getFeedUnread($link, $feed, $cat_view);
					if ($unread > 0) {
						$view_query_part = " unread = true AND ";
					}
				}
			}
	
			if ($view_mode == "marked") {
				$view_query_part = " marked = true AND ";
			}
	
			if ($view_mode == "unread") {
				$view_query_part = " unread = true AND ";
			}
	
			if ($limit > 0) {
				$limit_query_part = "LIMIT " . $limit;
			} 

			$vfeed_query_part = "";
	
			// override query strategy and enable feed display when searching globally
			if ($search && $search_mode == "all_feeds") {
				$query_strategy_part = "ttrss_entries.id > 0";
				$vfeed_query_part = "ttrss_feeds.title AS feed_title,";		
			} else if (preg_match("/^-?[0-9][0-9]*$/", $feed) == false) {
				$query_strategy_part = "ttrss_entries.id > 0";
				$vfeed_query_part = "(SELECT title FROM ttrss_feeds WHERE
					id = feed_id) as feed_title,";
			} else if ($feed >= 0 && $search && $search_mode == "this_cat") {
	
				$vfeed_query_part = "ttrss_feeds.title AS feed_title,";		

				$tmp_result = false;

				if ($cat_view) {
					$tmp_result = db_query($link, "SELECT id 
						FROM ttrss_feeds WHERE cat_id = '$feed'");
				} else {
					$tmp_result = db_query($link, "SELECT id
						FROM ttrss_feeds WHERE cat_id = (SELECT cat_id FROM ttrss_feeds 
							WHERE id = '$feed') AND id != '$feed'");
				}
	
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

			if (get_pref($link, 'REVERSE_HEADLINES')) {
				$order_by = "updated";
			} else {	
				$order_by = "updated DESC";
			}

			if ($override_order) {
				$order_by = $override_order;
			}
	
			$feed_title = "";

			if ($search && $search_mode == "all_feeds") {
				$feed_title = "Global search results ($search)";
			} else if ($search && preg_match('/^-?[0-9][0-9]*$/', $feed) == false) {
				$feed_title = "Tag search results ($search, $feed)";
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

					if ($search) {
						$feed_title = "Category search results ($search, $feed_title)";
					}

				} else {
					
					$result = db_query($link, "SELECT title,site_url,last_error FROM ttrss_feeds 
						WHERE id = '$feed' AND owner_uid = " . $_SESSION["uid"]);
		
					$feed_title = db_fetch_result($result, 0, "title");
					$feed_site_url = db_fetch_result($result, 0, "site_url");
					$last_error = db_fetch_result($result, 0, "last_error");

					if ($search) {
						$feed_title = "Feed search results ($search, $feed_title)";
					}
				}
	
			} else if ($feed == -1) {
				$feed_title = "Starred articles";
			} else if ($feed < -10) {
				$label_id = -$feed - 11;
				$result = db_query($link, "SELECT description FROM ttrss_labels
					WHERE id = '$label_id'");
				$feed_title = db_fetch_result($result, 0, "description");

				if ($search) {
					$feed_title = "Label search results ($search, $feed_title)";
				}
			} else {
				$feed_title = "?";
			}

			$feed_title = db_unescape_string($feed_title);

			if ($feed < -10) error_reporting (0);

			if (preg_match("/^-?[0-9][0-9]*$/", $feed) != false) {
	
				if ($feed >= 0) {
					$feed_kind = "Feeds";
				} else {
					$feed_kind = "Labels";
				}
	
				$content_query_part = "content as content_preview,";
				
				$query = "SELECT 
						ttrss_entries.id,ttrss_entries.title,
						SUBSTRING(updated,1,16) as updated,
						unread,feed_id,marked,link,last_read,
						SUBSTRING(last_read,1,19) as last_read_noms,
						$vfeed_query_part
						$content_query_part
						SUBSTRING(updated,1,19) as updated_noms
					FROM
						ttrss_entries,ttrss_user_entries,ttrss_feeds
					WHERE
					ttrss_feeds.hidden = false AND
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

			return array($result, $feed_title, $feed_site_url, $last_error);
			
	}

	function generate_syndicated_feed($link, $feed, $is_cat,
		$search, $search_mode, $match_on) {

		$qfh_ret = queryFeedHeadlines($link, $feed, 
				30, false, $is_cat, $search, $search_mode, $match_on, "updated DESC");

		$result = $qfh_ret[0];
		$feed_title = htmlspecialchars($qfh_ret[1]);
		$feed_site_url = $qfh_ret[2];
		$last_error = $qfh_ret[3];

		print "<rss version=\"2.0\">
			<channel>
			<title>$feed_title</title>
			<link>$feed_site_url</link>
			<generator>Tiny Tiny RSS v".VERSION."</generator>";

		while ($line = db_fetch_assoc($result)) {
			print "<item>";
			print "<link>" . htmlspecialchars($line["link"]) . "</link>";

			$rfc822_date = date('r', strtotime($line["updated"]));

			print "<pubDate>$rfc822_date</pubDate>";

			print "<title>" . 
				htmlspecialchars($line["title"]) . "</title>";

			print "<description>" . 
				htmlspecialchars($line["content_preview"]) . "</description>";

			print "</item>";
		}

		print "</channel></rss>";

	}

	function getCategoryTitle($link, $cat_id) {

		$result = db_query($link, "SELECT title FROM ttrss_feed_categories WHERE
			id = '$cat_id'");

		if (db_num_rows($result) == 1) {
			return db_fetch_result($result, 0, "title");
		} else {
			return "Uncategorized";
		}
	}

	function sanitize_rss($str) {
		$res = "";

		$res = preg_replace('/<script.*?>/i', 
					"<p class=\"scriptWarn\">", $str);

		$res = preg_replace('/<\/script>/i', 
			"</p>", $res);

		return $res;
	}
?>

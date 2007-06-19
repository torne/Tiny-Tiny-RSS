<?php

/*	if ($_GET["debug"]) {
		define('DEFAULT_ERROR_LEVEL', E_ALL);
	} else {
		define('DEFAULT_ERROR_LEVEL', E_ERROR | E_WARNING | E_PARSE);
	} */

	require_once 'config.php';

	if (ENABLE_TRANSLATIONS == true) { 
		require_once "accept-to-gettext.php";
		require_once "gettext/gettext.inc";

		function startup_gettext() {
	
			# Get locale from Accept-Language header
			$lang = al2gt(array("en_US", "ru_RU"), "text/html");
	
			if ($lang) {
				_setlocale(LC_MESSAGES, $lang);
				_bindtextdomain("messages", "locale");
				_textdomain("messages");
				_bind_textdomain_codeset("messages", "UTF-8");
			}
		}

		startup_gettext();

	} else {
		function __($msg) {
			return $msg;
		}
		function startup_gettext() {
			// no-op
			return true;
		}
	}

	require_once 'db-prefs.php';
	require_once 'compat.php';
	require_once 'errors.php';
	require_once 'version.php';

	define('MAGPIE_USER_AGENT_EXT', ' (Tiny Tiny RSS/' . VERSION . ')');
	define('MAGPIE_OUTPUT_ENCODING', 'UTF-8');

	require_once "magpierss/rss_fetch.inc";
	require_once 'magpierss/rss_utils.inc';

	include_once "tw/tw-config.php";
	include_once "tw/tw.php";
	include_once TW_SETUP . "paranoya.php";

	$tw_parser = new twParser();

	function _debug($msg) {
		$ts = strftime("%H:%M:%S", time());
		print "[$ts] $msg\n";
	}

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
			_debug("Purged feed $feed_id ($purge_interval): deleted $rows articles");
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
				$upd_intl = get_pref($link, 'DEFAULT_UPDATE_INTERVAL', $user_id, false);
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

	function fetch_file_contents($url) {
		if (USE_CURL_FOR_ICONS) {
			$tmpfile = tempnam(TMP_DIRECTORY, "ttrss-tmp");

			$ch = curl_init($url);
			$fp = fopen($tmpfile, "w");

			if ($fp) {
				curl_setopt($ch, CURLOPT_FILE, $fp);
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
				curl_setopt($ch, CURLOPT_TIMEOUT, 45);
				curl_exec($ch);
				curl_close($ch);
				fclose($fp);					
			}

			$contents =  file_get_contents($tmpfile);
			unlink($tmpfile);

			return $contents;

		} else {
			return file_get_contents($url);
		}

	}

	// adapted from wordpress favicon plugin by Jeff Minard (http://thecodepro.com/)
	// http://dev.wp-plugins.org/file/favatars/trunk/favatars.php

	function get_favicon_url($url) {

		if ($html = @fetch_file_contents($url)) {

			if ( preg_match('/<link[^>]+rel="(?:shortcut )?icon"[^>]+?href="([^"]+?)"/si', $html, $matches)) {
				// Attempt to grab a favicon link from their webpage url
				$linkUrl = html_entity_decode($matches[1]);

				if (substr($linkUrl, 0, 1) == '/') {
					$urlParts = parse_url($url);
					$faviconURL = $urlParts['scheme'].'://'.$urlParts['host'].$linkUrl;
				} else if (substr($linkUrl, 0, 7) == 'http://') {
					$faviconURL = $linkUrl;
				} else if (substr($url, -1, 1) == '/') {
					$faviconURL = $url.$linkUrl;
				} else {
					$faviconURL = $url.'/'.$linkUrl;
				}

			} else {
				// If unsuccessful, attempt to "guess" the favicon location
				$urlParts = parse_url($url);
				$faviconURL = $urlParts['scheme'].'://'.$urlParts['host'].'/favicon.ico';
			}
		}

		// Run a test to see if what we have attempted to get actually exists.
		if(USE_CURL_FOR_ICONS || url_validate($faviconURL)) {
			return $faviconURL;
		} else {
			return false;
		}
	}

	function url_validate($link) {
                
		$url_parts = @parse_url($link);

		if ( empty( $url_parts["host"] ) )
				return false;

		if ( !empty( $url_parts["path"] ) ) {
				$documentpath = $url_parts["path"];
		} else {
				$documentpath = "/";
		}

		if ( !empty( $url_parts["query"] ) )
				$documentpath .= "?" . $url_parts["query"];

		$host = $url_parts["host"];
		$port = $url_parts["port"];
		
		if ( empty($port) )
				$port = "80";

		$socket = @fsockopen( $host, $port, $errno, $errstr, 30 );
		
		if ( !$socket )
				return false;
				
		fwrite ($socket, "HEAD ".$documentpath." HTTP/1.0\r\nHost: $host\r\n\r\n");

		$http_response = fgets( $socket, 22 );

		$responses = "/(200 OK)|(30[0-9] Moved)/";
		if ( preg_match($responses, $http_response) ) {
				fclose($socket);
				return true;
		} else {
				return false;
		}

	} 

	function check_feed_favicon($site_url, $feed, $link) {
		$favicon_url = get_favicon_url($site_url);

#		print "FAVICON [$site_url]: $favicon_url\n";

		error_reporting(0);

		$icon_file = ICONS_DIR . "/$feed.ico";

		if ($favicon_url && !file_exists($icon_file)) {
			$contents = fetch_file_contents($favicon_url);

			$fp = fopen($icon_file, "w");

			if ($fp) {
				fwrite($fp, $contents);
				fclose($fp);
				chmod($icon_file, 0644);
			}
		}

		error_reporting(DEFAULT_ERROR_LEVEL);

	}

	function update_rss_feed($link, $feed_url, $feed, $ignore_daemon = false) {

		if (DAEMON_REFRESH_ONLY && !$_GET["daemon"] && !$ignore_daemon) {
			return;			
		}

		if (defined('DAEMON_EXTENDED_DEBUG')) {
			_debug("update_rss_feed: start");
		}

		$result = db_query($link, "SELECT update_interval,auth_login,auth_pass	
			FROM ttrss_feeds WHERE id = '$feed'");

		$auth_login = db_fetch_result($result, 0, "auth_login");
		$auth_pass = db_fetch_result($result, 0, "auth_pass");

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

		if (defined('DAEMON_EXTENDED_DEBUG')) {
			_debug("update_rss_feed: fetching...");
		}

		if (!defined('DAEMON_EXTENDED_DEBUG')) {
			error_reporting(0);
		}

		$rss = fetch_rss($fetch_url);

		if (defined('DAEMON_EXTENDED_DEBUG')) {
			_debug("update_rss_feed: fetch done, parsing...");
		} else {
			error_reporting (DEFAULT_ERROR_LEVEL);
		}

		$feed = db_escape_string($feed);

		if ($rss) {

			if (defined('DAEMON_EXTENDED_DEBUG')) {
				_debug("update_rss_feed: processing feed data...");
			}

//			db_query($link, "BEGIN");

			$result = db_query($link, "SELECT title,icon_url,site_url,owner_uid
				FROM ttrss_feeds WHERE id = '$feed'");

			$registered_title = db_fetch_result($result, 0, "title");
			$orig_icon_url = db_fetch_result($result, 0, "icon_url");
			$orig_site_url = db_fetch_result($result, 0, "site_url");

			$owner_uid = db_fetch_result($result, 0, "owner_uid");

			if (get_pref($link, 'ENABLE_FEED_ICONS', $owner_uid, false)) {	
				if (defined('DAEMON_EXTENDED_DEBUG')) {
					_debug("update_rss_feed: checking favicon...");
				}
				check_feed_favicon($rss->channel["link"], $feed, $link);
			}

			if (!$registered_title || $registered_title == "[Unknown]") {
			
				$feed_title = db_escape_string($rss->channel["title"]);
				
				db_query($link, "UPDATE ttrss_feeds SET 
					title = '$feed_title' WHERE id = '$feed'");
			}

			$site_url = $rss->channel["link"];
			// weird, weird Magpie
			if (!$site_url) $site_url = db_escape_string($rss->channel["link_"]);

			if ($site_url && $orig_site_url != db_escape_string($site_url)) {
				db_query($link, "UPDATE ttrss_feeds SET 
					site_url = '$site_url' WHERE id = '$feed'");
			}

//			print "I: " . $rss->channel["image"]["url"];

			$icon_url = $rss->image["url"];

			if ($icon_url && !$orig_icon_url != db_escape_string($icon_url)) {
				$icon_url = db_escape_string($icon_url);
				db_query($link, "UPDATE ttrss_feeds SET icon_url = '$icon_url' WHERE id = '$feed'");
			}

			if (defined('DAEMON_EXTENDED_DEBUG')) {
				_debug("update_rss_feed: loading filters...");
			}

			$filters = array();

			$result = db_query($link, "SELECT reg_exp,
				ttrss_filter_types.name AS name,
				ttrss_filter_actions.name AS action,
				inverse,
				action_param
				FROM ttrss_filters,ttrss_filter_types,ttrss_filter_actions WHERE 					
					enabled = true AND
					owner_uid = $owner_uid AND
					ttrss_filter_types.id = filter_type AND
					ttrss_filter_actions.id = action_id AND
				(feed_id IS NULL OR feed_id = '$feed') ORDER BY reg_exp");

			while ($line = db_fetch_assoc($result)) {
				if (!$filters[$line["name"]]) $filters[$line["name"]] = array();

				$filter["reg_exp"] = $line["reg_exp"];
				$filter["action"] = $line["action"];
				$filter["action_param"] = $line["action_param"];
				$filter["inverse"] = sql_bool_to_bool($line["inverse"]);
			
				array_push($filters[$line["name"]], $filter);
			}

			$iterator = $rss->items;

			if (!$iterator || !is_array($iterator)) $iterator = $rss->entries;
			if (!$iterator || !is_array($iterator)) $iterator = $rss;

			if (!is_array($iterator)) {
				/* db_query($link, "UPDATE ttrss_feeds 
					SET last_error = 'Parse error: can\'t find any articles.'
					WHERE id = '$feed'"); */

				// clear any errors and mark feed as updated if fetched okay
				// even if it's blank

				if (defined('DAEMON_EXTENDED_DEBUG')) {
					_debug("update_rss_feed: entry iterator is not an array, no articles?");
				}

				db_query($link, "UPDATE ttrss_feeds 
					SET last_updated = NOW(), last_error = '' WHERE id = '$feed'");

				return; // no articles
			}

			if (defined('DAEMON_EXTENDED_DEBUG')) {
				_debug("update_rss_feed: processing articles...");
			}

			foreach ($iterator as $item) {

				$entry_guid = $item["id"];

				if (!$entry_guid) $entry_guid = $item["guid"];
				if (!$entry_guid) $entry_guid = $item["link"];
				if (!$entry_guid) $entry_guid = make_guid_from_title($item["title"]);

				if (defined('DAEMON_EXTENDED_DEBUG')) {
					_debug("update_rss_feed: guid $entry_guid");
				}

				if (!$entry_guid) continue;

				$entry_timestamp = "";

				$rss_2_date = $item['pubdate'];
				$rss_1_date = $item['dc']['date'];
				$atom_date = $item['issued'];
				if (!$atom_date) $atom_date = $item['updated'];
			
				if ($atom_date != "") $entry_timestamp = parse_w3cdtf($atom_date);
				if ($rss_1_date != "") $entry_timestamp = parse_w3cdtf($rss_1_date);
				if ($rss_2_date != "") $entry_timestamp = strtotime($rss_2_date);
				
				if ($entry_timestamp == "" || $entry_timestamp == -1 || !$entry_timestamp) {
					$entry_timestamp = time();
					$no_orig_date = 'true';
				} else {
					$no_orig_date = 'false';
				}

				$entry_timestamp_fmt = strftime("%Y/%m/%d %H:%M:%S", $entry_timestamp);

				$entry_title = trim(strip_tags($item["title"]));

				// strange Magpie workaround
				$entry_link = $item["link_"];
				if (!$entry_link) $entry_link = $item["link"];

				if (!$entry_title) continue;
#					if (!$entry_link) continue;

				$entry_link = strip_tags($entry_link);

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

				$entry_comments = strip_tags($item["comments"]);

				$entry_author = db_escape_string(strip_tags($item['dc']['creator']));

				if ($item['author']) {

					if (is_array($item['author'])) {

						if (!$entry_author) {
							$entry_author = db_escape_string(strip_tags($item['author']['name']));
						}

						if (!$entry_author) {
							$entry_author = db_escape_string(strip_tags($item['author']['email']));
						}
					}

					if (!$entry_author) {
						$entry_author = db_escape_string(strip_tags($item['author']));
					}
				}

				if (preg_match('/^[\t\n\r ]*$/', $entry_author)) $entry_author = '';

				$entry_guid = db_escape_string(strip_tags($entry_guid));

				$result = db_query($link, "SELECT id FROM	ttrss_entries 
					WHERE guid = '$entry_guid'");

				$entry_content = db_escape_string($entry_content);

				$content_hash = "SHA1:" . sha1(strip_tags($entry_content));

				$entry_title = db_escape_string($entry_title);
				$entry_link = db_escape_string($entry_link);
				$entry_comments = db_escape_string($entry_comments);

				$num_comments = db_escape_string($item["slash"]["comments"]);

				if (!$num_comments) $num_comments = 0;

				// parse <category> entries into tags

				$t_ctr = $item['category#'];

				$additional_tags = false;

				if ($t_ctr == 0) {
					$additional_tags = false;
				} else if ($t_ctr == 1) {
					$additional_tags = array($item['category']);
				} else {
					$additional_tags = array();
					for ($i = 0; $i <= $t_ctr; $i++ ) {
						if ($item["category#$i"]) {
							array_push($additional_tags, $item["category#$i"]);
						}
					}
				}

				// parse <dc:subject> elements

				$t_ctr = $item['dc']['subject#'];

				if ($t_ctr == 1) {
					$additional_tags = array($item['dc']['subject']);
				} else if ($t_ctr > 1) {
					$additional_tags = array();
					for ($i = 0; $i <= $t_ctr; $i++ ) {
						if ($item['dc']["subject#$i"]) {
							array_push($additional_tags, $item['dc']["subject#$i"]);
						}
					}
				}

				# sanitize content
				
//				$entry_content = sanitize_rss($entry_content);

				if (defined('DAEMON_EXTENDED_DEBUG')) {
					_debug("update_rss_feed: done collecting data [TITLE:$entry_title]");
				}

				db_query($link, "BEGIN");

				if (db_num_rows($result) == 0) {

					if (defined('DAEMON_EXTENDED_DEBUG')) {
						_debug("update_rss_feed: base guid not found");
					}

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

					if (defined('DAEMON_EXTENDED_DEBUG')) {
						_debug("update_rss_feed: base guid found, checking for user record");
					}

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

					$article_filters = get_article_filters($filters, $entry_title, 
							$entry_content, $entry_link);

					if (defined('DAEMON_EXTENDED_DEBUG')) {
						_debug("update_rss_feed: article filters: ");
						if (count($article_filters) != 0) {
							print_r($article_filters);
						}
					}

					if (find_article_filter($article_filters, "filter")) {
						continue;
					}

//					error_reporting (DEFAULT_ERROR_LEVEL);

					$result = db_query($link,
						"SELECT ref_id FROM ttrss_user_entries WHERE
							ref_id = '$ref_id' AND owner_uid = '$owner_uid'
							$dupcheck_qpart");

					// okay it doesn't exist - create user entry
					if (db_num_rows($result) == 0) {

						if (defined('DAEMON_EXTENDED_DEBUG')) {
							_debug("update_rss_feed: user record not found, creating...");
						}

						if (!find_article_filter($article_filters, 'catchup')) {
							$unread = 'true';
							$last_read_qpart = 'NULL';
						} else {
							$unread = 'false';
							$last_read_qpart = 'NOW()';
						}						

						if (find_article_filter($article_filters, 'mark')) {
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
//						print "<!-- [$entry_title] $content_hash vs $orig_content_hash -->";
						$post_needs_update = true;
					}

					if (db_escape_string($orig_title) != $entry_title) {
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

						if (defined('DAEMON_EXTENDED_DEBUG')) {
							_debug("update_rss_feed: post $entry_guid needs update...");
						}

//						print "<!-- post $orig_title needs update : $post_needs_update -->";

						db_query($link, "UPDATE ttrss_entries 
							SET title = '$entry_title', content = '$entry_content',
								content_hash = '$content_hash',
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

				if (defined('DAEMON_EXTENDED_DEBUG')) {
					_debug("update_rss_feed: looking for tags...");
				}

				/* taaaags */
				// <a href="..." rel="tag">Xorg</a>, //

				$entry_tags = null;

				preg_match_all("/<a.*?rel=['\"]tag['\"].*?>([^<]+)<\/a>/i", 
					$entry_content_unescaped, $entry_tags);

/*				print "<p><br/>$entry_title : $entry_content_unescaped<br>";
				print_r($entry_tags);
				print "<br/></p>"; */

				$entry_tags = $entry_tags[1];

				# check for manual tags

				$tag_filter = find_article_filter($article_filters, "tag"); 

				if ($tag_filter) {

					$manual_tags = trim_array(split(",", $tag_filter[1]));

					foreach ($manual_tags as $tag) {
						if (tag_is_valid($tag)) {
							array_push($entry_tags, $tag);
						}
					}
				}

				$boring_tags = trim_array(split(",", mb_strtolower(get_pref($link, 
					'BLACKLISTED_TAGS', $owner_uid, ''), 'utf-8')));

				if ($additional_tags && is_array($additional_tags)) {
					foreach ($additional_tags as $tag) {
						if (tag_is_valid($tag) && 
								array_search($tag, $boring_tags) === FALSE) {
							array_push($entry_tags, $tag);
						}
					}
				} 

//				print "<p>TAGS: "; print_r($entry_tags); print "</p>";

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

							$tag = sanitize_tag($tag);
							$tag = db_escape_string($tag);

							if (!tag_is_valid($tag)) continue;
							
							$result = db_query($link, "SELECT id FROM ttrss_tags		
								WHERE tag_name = '$tag' AND post_int_id = '$entry_int_id' AND 
								owner_uid = '$owner_uid' LIMIT 1");
	
	//						print db_fetch_result($result, 0, "id");
	
							if ($result && db_num_rows($result) == 0) {
								
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

		if (defined('DAEMON_EXTENDED_DEBUG')) {
			_debug("update_rss_feed: done");
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

	function get_article_filters($filters, $title, $content, $link) {
		$matches = array();

		if ($filters["title"]) {
			foreach ($filters["title"] as $filter) {
				$reg_exp = $filter["reg_exp"];		
				$inverse = $filter["inverse"];	
				if ((!$inverse && preg_match("/$reg_exp/i", $title)) || 
						($inverse && !preg_match("/$reg_exp/i", $title))) {

					array_push($matches, array($filter["action"], $filter["action_param"]));
				}
			}
		}

		if ($filters["content"]) {
			foreach ($filters["content"] as $filter) {
				$reg_exp = $filter["reg_exp"];
				$inverse = $filter["inverse"];

				if ((!$inverse && preg_match("/$reg_exp/i", $content)) || 
						($inverse && !preg_match("/$reg_exp/i", $content))) {

					array_push($matches, array($filter["action"], $filter["action_param"]));
				}		
			}
		}

		if ($filters["both"]) {
			foreach ($filters["both"] as $filter) {			
				$reg_exp = $filter["reg_exp"];		
				$inverse = $filter["inverse"];

				if ($inverse) {
					if (!preg_match("/$reg_exp/i", $title) || !preg_match("/$reg_exp/i", $content)) {
						array_push($matches, array($filter["action"], $filter["action_param"]));
					}
				} else {
					if (preg_match("/$reg_exp/i", $title) || preg_match("/$reg_exp/i", $content)) {
						array_push($matches, array($filter["action"], $filter["action_param"]));
					}
				}
			}
		}

		if ($filters["link"]) {
			$reg_exp = $filter["reg_exp"];
			foreach ($filters["link"] as $filter) {
				$reg_exp = $filter["reg_exp"];
				$inverse = $filter["inverse"];

				if ((!$inverse && preg_match("/$reg_exp/i", $link)) || 
						($inverse && !preg_match("/$reg_exp/i", $link))) {
						
					array_push($matches, array($filter["action"], $filter["action_param"]));
				}
			}
		}

		return $matches;
	}

	function find_article_filter($filters, $filter_name) {
		foreach ($filters as $f) {
			if ($f[0] == $filter_name) {
				return $f;
			};
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

		$feed = "<a title=\"$link_title\" id=\"FEEDL-$feed_id\" 
			href=\"javascript:viewfeed('$feed_id', '', false, '', false, 0);\">$feed_title</a>";

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

	function authenticate_user($link, $login, $password, $force_auth = false) {

		if (!SINGLE_USER_MODE) {

			$pwd_hash = 'SHA1:' . sha1($password);

			if ($force_auth && defined('_DEBUG_USER_SWITCH')) {
				$query = "SELECT id,login,access_level
	            FROM ttrss_users WHERE
		         login = '$login'";
			} else {
				$query = "SELECT id,login,access_level
	            FROM ttrss_users WHERE
		         login = '$login' AND pwd_hash = '$pwd_hash'";
			}

			$result = db_query($link, $query);
	
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

		db_query($link, "insert into ttrss_feeds (owner_uid,title,feed_url)
			values ('$uid', 'Tiny Tiny RSS: Forum',
			'http://tt-rss.spb.ru/forum/rss.php')");
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

	function validate_session($link) {
		if (SESSION_CHECK_ADDRESS && $_SESSION["uid"]) {
			if ($_SESSION["ip_address"]) {
				if ($_SESSION["ip_address"] != $_SERVER["REMOTE_ADDR"]) {
					$_SESSION["login_error_msg"] = "Session failed to validate (incorrect IP)";
					return false;
				}
			}
		}

/*		if ($_SESSION["cookie_lifetime"] && $_SESSION["uid"]) {

			//print_r($_SESSION);

			if (time() > $_SESSION["cookie_lifetime"]) {
				return false;
			}
		} */

		return true;
	}

	function login_sequence($link, $mobile = false) {
		if (!SINGLE_USER_MODE) {

			if (defined('_DEBUG_USER_SWITCH') && $_SESSION["uid"]) {
				$swu = db_escape_string($_REQUEST["swu"]);
				if ($swu) {
					$_SESSION["prefs_cache"] = false;
					return authenticate_user($link, $swu, null, true);
				}
			}

			$login_action = $_POST["login_action"];

			# try to authenticate user if called from login form			
			if ($login_action == "do_login") {
				$login = $_POST["login"];
				$password = $_POST["password"];
				$remember_me = $_POST["remember_me"];

				if (authenticate_user($link, $login, $password)) {
					$_POST["password"] = "";

					header("Location: " . $_SERVER["REQUEST_URI"]);
					exit;

					return;
				} else {
					$_SESSION["login_error_msg"] = "Incorrect username or password";
				}
			}

//			print session_id();
//			print_r($_SESSION);

			if (!$_SESSION["uid"] || !validate_session($link)) {
				render_login_form($link, $mobile);
				exit;
			} else {
				/* bump login timestamp */
				db_query($link, "UPDATE ttrss_users SET last_login = NOW() WHERE id = " . 
					$_SESSION["uid"]);
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
			return date("Y/m/d, G:i", $timestamp);
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

	function getAllCounters($link, $omode = "tflc") {
/*		getLabelCounters($link);
		getFeedCounters($link);
		getTagCounters($link);
		getGlobalCounters($link);
		if (get_pref($link, 'ENABLE_FEED_CATS')) {
			getCategoryCounters($link);
		} */

		if (!$omode) $omode = "tflc";

		getGlobalCounters($link);

		if (strchr($omode, "l")) getLabelCounters($link);
		if (strchr($omode, "f")) getFeedCounters($link);
		if (strchr($omode, "t")) getTagCounters($link);
		if (strchr($omode, "c")) {			
			if (get_pref($link, 'ENABLE_FEED_CATS')) {
				getCategoryCounters($link);
			}
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

			$result = db_query($link, "SELECT id FROM ttrss_feeds 
					WHERE parent_feed = '$n_feed'
					AND hidden = false
					AND owner_uid = " . $_SESSION["uid"]);

			if (db_num_rows($result) > 0) {

				$linked_feeds = array();
				while ($line = db_fetch_assoc($result)) {
					array_push($linked_feeds, "feed_id = " . $line["id"]);
				}

				array_push($linked_feeds, "feed_id = $n_feed");
				
				$match_part = implode(" OR ", $linked_feeds);

				$result = db_query($link, "SELECT COUNT(int_id) AS unread 
					FROM ttrss_user_entries
					WHERE	unread = true AND ($match_part) 
					AND owner_uid = " . $_SESSION["uid"]);

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

	function getGlobalUnread($link, $user_id = false) {

		if (!$user_id) {
			$user_id = $_SESSION["uid"];
		}

		$result = db_query($link, "SELECT count(ttrss_entries.id) as c_id FROM ttrss_entries,ttrss_user_entries,ttrss_feeds
			WHERE unread = true AND 
			ttrss_user_entries.feed_id = ttrss_feeds.id AND
			ttrss_user_entries.ref_id = ttrss_entries.id AND 
			hidden = false AND
			ttrss_user_entries.owner_uid = '$user_id'");
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
			WHERE owner_uid = ".$_SESSION['uid']." GROUP BY tag_name ORDER BY tag_name");
			
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

/*		$result = db_query($link, "SELECT id,last_error,parent_feed,
			SUBSTRING(last_updated,1,19) AS last_updated,
			(SELECT count(id) 
				FROM ttrss_entries,ttrss_user_entries 
				WHERE feed_id = ttrss_feeds.id AND 
					ttrss_user_entries.ref_id = ttrss_entries.id
				AND unread = true AND owner_uid = ".$_SESSION["uid"].") as count
			FROM ttrss_feeds WHERE owner_uid = ".$_SESSION["uid"] . "
			AND parent_feed IS NULL"); */

		$result = db_query($link, "SELECT ttrss_feeds.id,
				SUBSTRING(ttrss_feeds.last_updated,1,19) AS last_updated, 
				last_error, 
				COUNT(ttrss_entries.id) AS count 
			FROM ttrss_feeds 
				LEFT JOIN ttrss_user_entries ON (ttrss_user_entries.feed_id = ttrss_feeds.id 
					AND ttrss_user_entries.owner_uid = ttrss_feeds.owner_uid 
					AND ttrss_user_entries.unread = true) 
				LEFT JOIN ttrss_entries ON (ttrss_user_entries.ref_id = ttrss_entries.id) 
			WHERE ttrss_feeds.owner_uid = ".$_SESSION["uid"]."
				AND parent_feed IS NULL 
			GROUP BY ttrss_feeds.id, ttrss_feeds.title, ttrss_feeds.last_updated, last_error");

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
		if (strpos(VERSION, ".99") === false) {
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

	function subscribe_to_feed($link, $feed_link, $cat_id = 0, 
			$auth_login = '', $auth_pass = '') {

		# check for feed:http://url
		$feed_link = trim(preg_replace("/^feed:/", "", $feed_link));

		# check for feed://URL
		if (strpos($feed_link, "//") === 0) {
			$feed_link = "http:$feed_link";
		}

		if ($feed_link == "") return;

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
				"INSERT INTO ttrss_feeds 
					(owner_uid,feed_url,title,cat_id, auth_login,auth_pass) 
				VALUES ('".$_SESSION["uid"]."', '$feed_link', 
				'[Unknown]', $cat_qpart, '$auth_login', '$auth_pass')");
	
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
				$line["id"], htmlspecialchars($line["title"]));
		}
	
		print "</select>";
	}

	function print_feed_cat_select($link, $id, $default_id = "", 
		$attributes = "", $include_all_cats = true) {
		
		print "<select id=\"$id\" name=\"$id\" $attributes>";

		if ($include_all_cats) {
			print "<option value=\"0\">".__('Uncategorized')."</option>";
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
				$line["id"], htmlspecialchars($line["title"]));
		}

		print "</select>";
	}
	
	function checkbox_to_sql_bool($val) {
		return ($val == "on") ? "true" : "false";
	}

	function getFeedCatTitle($link, $id) {
		if ($id == -1) {
			return __("Special");
		} else if ($id < -10) {
			return __("Labels");
		} else if ($id > 0) {
			$result = db_query($link, "SELECT ttrss_feed_categories.title 
				FROM ttrss_feeds, ttrss_feed_categories WHERE ttrss_feeds.id = '$id' AND
					cat_id = ttrss_feed_categories.id");
			if (db_num_rows($result) == 1) {
				return db_fetch_result($result, 0, "title");
			} else {
				return __("Uncategorized");
			}
		} else {
			return "getFeedCatTitle($id) failed";
		}

	}

	function getFeedTitle($link, $id) {
		if ($id == -1) {
			return __("Starred articles");
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
			(int) get_pref($link, "HIDE_READ_FEEDS") . "\"/>";

		print "<param key=\"feeds_sort_by_unread\" value=\"" . 
			(int) get_pref($link, "FEEDS_SORT_BY_UNREAD") . "\"/>";

		print "<param key=\"confirm_feed_catchup\" value=\"" . 
			(int) get_pref($link, "CONFIRM_FEED_CATCHUP") . "\"/>";

		print "<param key=\"cdm_auto_catchup\" value=\"" . 
			(int) get_pref($link, "CDM_AUTO_CATCHUP") . "\"/>";

		print "<param key=\"icons_url\" value=\"" . ICONS_URL . "\"/>";

		print "<param key=\"cookie_lifetime\" value=\"" . SESSION_COOKIE_LIFETIME . "\"/>";

		print "<param key=\"default_view_mode\" value=\"" . 
			get_pref($link, "_DEFAULT_VIEW_MODE") . "\"/>";

		print "<param key=\"default_view_limit\" value=\"" . 
			(int) get_pref($link, "_DEFAULT_VIEW_LIMIT") . "\"/>";

		print "<param key=\"prefs_active_tab\" value=\"" . 
			get_pref($link, "_PREFS_ACTIVE_TAB") . "\"/>";

		print "<param key=\"infobox_disable_overlay\" value=\"" . 
			get_pref($link, "_INFOBOX_DISABLE_OVERLAY") . "\"/>";

		print "</init-params>";
	}

	function print_runtime_info($link) {
		print "<runtime-info>";
		if (ENABLE_UPDATE_DAEMON) {
			print "<param key=\"daemon_is_running\" value=\"".
				sprintf("%d", file_is_locked("update_daemon.lock")) . "\"/>";
		}
		if (CHECK_FOR_NEW_VERSION && $_SESSION["access_level"] >= 10) {
			
			if ($_SESSION["last_version_check"] + 600 < time()) {
				$new_version_details = check_for_update($link);

				print "<param key=\"new_version_available\" value=\"".
					sprintf("%d", $new_version_details != ""). "\"/>";

				$_SESSION["last_version_check"] = time();
			}
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

	function queryFeedHeadlines($link, $feed, $limit, $view_mode, $cat_view, $search, $search_mode, $match_on, $override_order = false, $offset = 0) {

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

				if (!$query_strategy_part) {
					return false;
				}

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
				$feed_title = __("Search results")." ($search)";
			} else if ($search && preg_match('/^-?[0-9][0-9]*$/', $feed) == false) {
				$feed_title = __("Search results")." ($search, $feed)";
			} else if (preg_match('/^-?[0-9][0-9]*$/', $feed) == false) {
				$feed_title = $feed;
			} else if (preg_match('/^-?[0-9][0-9]*$/', $feed) != false && $feed >= 0) {
	
				if ($cat_view) {

					if ($feed != 0) {			
						$result = db_query($link, "SELECT title FROM ttrss_feed_categories
							WHERE id = '$feed' AND owner_uid = " . $_SESSION["uid"]);
						$feed_title = db_fetch_result($result, 0, "title");
					} else {
						$feed_title = __("Uncategorized");
					}

					if ($search) {
						$feed_title = __("Searched for")." $search ($feed_title)";
					}

				} else {
					
					$result = db_query($link, "SELECT title,site_url,last_error FROM ttrss_feeds 
						WHERE id = '$feed' AND owner_uid = " . $_SESSION["uid"]);
		
					$feed_title = db_fetch_result($result, 0, "title");
					$feed_site_url = db_fetch_result($result, 0, "site_url");
					$last_error = db_fetch_result($result, 0, "last_error");

					if ($search) {
						$feed_title = __("Searched for") . " $search ($feed_title)";
					}
				}
	
			} else if ($feed == -1) {
				$feed_title = __("Starred articles");
			} else if ($feed < -10) {
				$label_id = -$feed - 11;
				$result = db_query($link, "SELECT description FROM ttrss_labels
					WHERE id = '$label_id'");
				$feed_title = db_fetch_result($result, 0, "description");

				if ($search) {
					$feed_title = __("Searched for") . " $search ($feed_title)";
				}
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
	
				$content_query_part = "content as content_preview,";

				if ($limit_query_part) {
					$offset_query_part = "OFFSET $offset";
				}

				$query = "SELECT 
						guid,
						ttrss_entries.id,ttrss_entries.title,
						updated,
						unread,feed_id,marked,link,last_read,
						SUBSTRING(last_read,1,19) as last_read_noms,
						$vfeed_query_part
						$content_query_part
						SUBSTRING(updated,1,19) as updated_noms,
						author
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
					$limit_query_part $offset_query_part";
					
				$result = db_query($link, $query);
	
				if ($_GET["debug"]) print $query;
	
			} else {
				// browsing by tag
	
				$feed_kind = "Tags";
	
				$result = db_query($link, "SELECT
					guid,
					ttrss_entries.id as id,title,
					updated,
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
 			print "<id>" . htmlspecialchars($line["guid"]) . "</id>";
 			print "<link>" . htmlspecialchars($line["link"]) . "</link>";

			$tags = get_article_tags($link, $line["id"]);

			foreach ($tags as $tag) {
				print "<category>" . htmlspecialchars($tag) . "</category>";
			}

 			$rfc822_date = date('r', strtotime($line["updated"]));
  
 			print "<pubDate>$rfc822_date</pubDate>";
 
 			print "<title>" . 
 				htmlspecialchars($line["title"]) . "</title>";
  
 			print "<description><![CDATA[" . 
 				$line["content_preview"] . "]]></description>";
  
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

	// http://ru2.php.net/strip-tags

	function strip_tags_long($textstring, $allowed){
	while($textstring != strip_tags($textstring, $allowed))
    {
    while (strlen($textstring) != 0)
         {
         if (strlen($textstring) > 1024) {
              $otherlen = 1024;
         } else {
              $otherlen = strlen($textstring);
         }
         $temptext = strip_tags(substr($textstring,0,$otherlen), $allowed);
         $safetext .= $temptext;
         $textstring = substr_replace($textstring,'',0,$otherlen);
         }  
    $textstring = $safetext;
    }
	return $textstring;
	}


	function sanitize_rss($link, $str, $force_strip_tags = false) {
		$res = $str;

		if (get_pref($link, "STRIP_UNSAFE_TAGS") || $force_strip_tags) {
			global $tw_parser;
			global $tw_paranoya_setup;

			$res = $tw_parser->strip_tags($res, $tw_paranoya_setup);

//			$res = preg_replace("/\r\n|\n|\r/", "", $res);
//			$res = strip_tags_long($res, "<p><a><i><em><b><strong><blockquote><br><img><div><span>");			
		}

		return $res;
	}

	function send_headlines_digests($link, $limit = 100) {

		if (!DIGEST_ENABLE) return false;

		$user_limit = DIGEST_EMAIL_LIMIT;
		$days = 1;

		print "Sending digests, batch of max $user_limit users, days = $days, headline limit = $limit\n\n";

		if (DB_TYPE == "pgsql") {
			$interval_query = "last_digest_sent < NOW() - INTERVAL '$days days'";
		} else if (DB_TYPE == "mysql") {
			$interval_query = "last_digest_sent < DATE_SUB(NOW(), INTERVAL $days DAY)";
		}

		$result = db_query($link, "SELECT id,email FROM ttrss_users 
				WHERE email != '' AND (last_digest_sent IS NULL OR $interval_query)");

		while ($line = db_fetch_assoc($result)) {
			if (get_pref($link, 'DIGEST_ENABLE', $line['id'], false)) {
				print "Sending digest for UID:" . $line['id'] . " - " . $line["email"] . " ... ";

				$tuple = prepare_headlines_digest($link, $line["id"], $days, $limit);
				$digest = $tuple[0];
				$headlines_count = $tuple[1];

				if ($headlines_count > 0) {
					$rc = mail($line["login"] . " <" . $line["email"] . ">",
						"[tt-rss] New headlines for last 24 hours", $digest,
						"From: " . MAIL_FROM . "\n".
						"Content-Type: text/plain; charset=\"utf-8\"\n".
						"Content-Transfer-Encoding: 8bit\n");
					print "RC=$rc\n";
					db_query($link, "UPDATE ttrss_users SET last_digest_sent = NOW() 
							WHERE id = " . $line["id"]);
				} else {
					print "No headlines\n";
				}
			}
		}

//		$digest = prepare_headlines_digest($link, $user_id, $days, $limit);

	}

	function prepare_headlines_digest($link, $user_id, $days = 1, $limit = 100) {
		$tmp =  __("New headlines for last 24 hours, as of ") . date("Y/m/d H:m") . "\n";	
		$tmp .= "=======================================================\n\n";

		if (DB_TYPE == "pgsql") {
			$interval_query = "ttrss_entries.date_entered > NOW() - INTERVAL '$days days'";
		} else if (DB_TYPE == "mysql") {
			$interval_query = "ttrss_entries.date_entered > DATE_SUB(NOW(), INTERVAL $days DAY)";
		}

		$result = db_query($link, "SELECT ttrss_entries.title,
				ttrss_feeds.title AS feed_title,
				date_entered,
				link,
				SUBSTRING(last_updated,1,19) AS last_updated
			FROM 
				ttrss_user_entries,ttrss_entries,ttrss_feeds 
			WHERE 
				ref_id = ttrss_entries.id AND feed_id = ttrss_feeds.id 
				AND include_in_digest = true
				AND $interval_query
				AND ttrss_user_entries.owner_uid = $user_id
				AND unread = true ORDER BY ttrss_feeds.title, date_entered DESC
			LIMIT $limit");

		$cur_feed_title = "";

		$headlines_count = db_num_rows($result);

		while ($line = db_fetch_assoc($result)) {
			$updated = smart_date_time(strtotime($line["last_updated"]));
			$feed_title = $line["feed_title"];

			if ($cur_feed_title != $feed_title) {
				$cur_feed_title = $feed_title;

				$tmp .= "$feed_title\n\n";
			}

			$tmp .= " * " . trim($line["title"]) . " - $updated\n";
			$tmp .= "   " . trim($line["link"]) . "\n";
			$tmp .= "\n";
		}

		$tmp .= "--- \n";
		$tmp .= __("You have been sent this email because you have enabled daily digests in Tiny Tiny RSS at ") . 
			DIGEST_HOSTNAME . "\n".
			__("To unsubscribe, visit your configuration options or contact instance owner.\n");
			

		return array($tmp, $headlines_count);
	}

	function check_for_update($link, $brief_fmt = true) {
		$releases_feed = "http://tt-rss.spb.ru/releases.rss";

		if (!CHECK_FOR_NEW_VERSION || $_SESSION["access_level"] < 10) {
			return;
		}

		error_reporting(0);
		$rss = fetch_rss($releases_feed);
		error_reporting (DEFAULT_ERROR_LEVEL);

		if ($rss) {

			$items = $rss->items;

			if (!$items || !is_array($items)) $items = $rss->entries;
			if (!$items || !is_array($items)) $items = $rss;

			if (!is_array($items) || count($items) == 0) {
				return;
			}			

			$latest_item = $items[0];

			$latest_version = trim(preg_replace("/(Milestone)|(completed)/", "", $latest_item["title"]));

			$release_url = sanitize_rss($link, $latest_item["link"]);
			$content = sanitize_rss($link, $latest_item["description"]);

			if (version_compare(VERSION, $latest_version) == -1) {
				if ($brief_fmt) {
					return format_notice("<a href=\"javascript:showBlockElement('milestoneDetails')\">	
						New version of Tiny-Tiny RSS ($latest_version) is available (click for details)</a>
						<div id=\"milestoneDetails\">$content</div>");
				} else {
					return "New version of Tiny-Tiny RSS ($latest_version) is available:
						<div class='milestoneDetails'>$content</div>
						Visit <a target=\"_new\" href=\"http://tt-rss.spb.ru/\">official site</a> for
						download and update information.";	
				}

			}			
		}
	}

	function markArticlesById($link, $ids, $cmode) {

		$tmp_ids = array();

		foreach ($ids as $id) {
			array_push($tmp_ids, "ref_id = '$id'");
		}

		$ids_qpart = join(" OR ", $tmp_ids);

		if ($cmode == 0) {
			db_query($link, "UPDATE ttrss_user_entries SET 
			marked = false,last_read = NOW()
			WHERE ($ids_qpart) AND owner_uid = " . $_SESSION["uid"]);
		} else if ($cmode == 1) {
			db_query($link, "UPDATE ttrss_user_entries SET 
			marked = true
			WHERE ($ids_qpart) AND owner_uid = " . $_SESSION["uid"]);
		} else {
			db_query($link, "UPDATE ttrss_user_entries SET 
			marked = NOT marked,last_read = NOW()
			WHERE ($ids_qpart) AND owner_uid = " . $_SESSION["uid"]);
		}
	}

	function catchupArticlesById($link, $ids, $cmode) {

		$tmp_ids = array();

		foreach ($ids as $id) {
			array_push($tmp_ids, "ref_id = '$id'");
		}

		$ids_qpart = join(" OR ", $tmp_ids);

		if ($cmode == 0) {
			db_query($link, "UPDATE ttrss_user_entries SET 
			unread = false,last_read = NOW()
			WHERE ($ids_qpart) AND owner_uid = " . $_SESSION["uid"]);
		} else if ($cmode == 1) {
			db_query($link, "UPDATE ttrss_user_entries SET 
			unread = true
			WHERE ($ids_qpart) AND owner_uid = " . $_SESSION["uid"]);
		} else {
			db_query($link, "UPDATE ttrss_user_entries SET 
			unread = NOT unread,last_read = NOW()
			WHERE ($ids_qpart) AND owner_uid = " . $_SESSION["uid"]);
		}
	}

	function catchupArticleById($link, $id, $cmode) {

		if ($cmode == 0) {
			db_query($link, "UPDATE ttrss_user_entries SET 
			unread = false,last_read = NOW()
			WHERE ref_id = '$id' AND owner_uid = " . $_SESSION["uid"]);
		} else if ($cmode == 1) {
			db_query($link, "UPDATE ttrss_user_entries SET 
			unread = true
			WHERE ref_id = '$id' AND owner_uid = " . $_SESSION["uid"]);
		} else {
			db_query($link, "UPDATE ttrss_user_entries SET 
			unread = NOT unread,last_read = NOW()
			WHERE ref_id = '$id' AND owner_uid = " . $_SESSION["uid"]);
		}
	}

	function make_guid_from_title($title) {
		return preg_replace("/[ \"\',.:;]/", "-", 
			mb_strtolower(strip_tags($title), 'utf-8'));
	}

	function print_headline_subtoolbar($link, $feed_site_url, $feed_title, 
			$bottom = false, $rtl_content = false, $feed_id = 0,
			$is_cat = false, $search = false, $match_on = false,
			$search_mode = false, $offset = 0, $limit = 0) {

			$user_page_offset = $offset + 1;

			if (!$bottom) {
				$class = "headlinesSubToolbar";
				$tid = "headlineActionsTop";
			} else {
				$class = "headlinesSubToolbar";
				$tid = "headlineActionsBottom";
			}

			print "<table class=\"$class\" id=\"$tid\"
				width=\"100%\" cellspacing=\"0\" cellpadding=\"0\"><tr>";

			if ($rtl_content) {
				$rtl_cpart = "RTL";
			} else {
				$rtl_cpart = "";
			}

			$page_prev_link = "javascript:viewFeedGoPage(-1)";
			$page_next_link = "javascript:viewFeedGoPage(1)";
			$page_first_link = "javascript:viewFeedGoPage(0)";

			$catchup_page_link = "javascript:catchupPage()";
			$catchup_feed_link = "javascript:catchupCurrentFeed()";

			if (!get_pref($link, 'COMBINED_DISPLAY_MODE')) {

				$sel_all_link = "javascript:selectTableRowsByIdPrefix('headlinesList', 'RROW-', 'RCHK-', true, '', true)";
				$sel_unread_link = "javascript:selectTableRowsByIdPrefix('headlinesList', 'RROW-', 'RCHK-', true, 'Unread', true)";
				$sel_none_link = "javascript:selectTableRowsByIdPrefix('headlinesList', 'RROW-', 'RCHK-', false)";

				$tog_unread_link = "javascript:selectionToggleUnread()";
				$tog_marked_link = "javascript:selectionToggleMarked()";

			} else {

				$sel_all_link = "javascript:cdmSelectArticles('all')";
				$sel_unread_link = "javascript:cdmSelectArticles('unread')";
				$sel_none_link = "javascript:cdmSelectArticles('none')";

				$tog_unread_link = "javascript:selectionToggleUnread(true)";
				$tog_marked_link = "javascript:selectionToggleMarked(true)";

			}

			if (strpos($_SESSION["client.userAgent"], "MSIE") === false) {

				print "<td class=\"headlineActions$rtl_cpart\">
					<ul class=\"headlineDropdownMenu\">
					<li class=\"top2\">
					".__('Select:')."
						<a href=\"$sel_all_link\">".__('All')."</a>,
						<a href=\"$sel_unread_link\">".__('Unread')."</a>,
						<a href=\"$sel_none_link\">".__('None')."</a></li>
					<li class=\"vsep\">&nbsp;</li>
					<li class=\"top\">Toggle<ul>
						<li onclick=\"$tog_unread_link\">".__('Unread')."</li>
						<li onclick=\"$tog_marked_link\">".__('Starred')."</li></ul></li>
					<li class=\"vsep\">&nbsp;</li>
					<li class=\"top\"><a href=\"$catchup_page_link\">".__('Mark as read')."</a><ul>
						<li onclick=\"$catchup_page_link\">".__('This page')."</li>
						<li onclick=\"$catchup_feed_link\">".__('Entire feed')."</li></ul></li>
					<li class=\"vsep\">&nbsp;</li>";

					if ($limit != 0 && !$search) {
						print "
						<li class=\"top\"><a href=\"$page_next_link\">".__('Next page')."</a><ul>
							<li onclick=\"$page_prev_link\">".__('Previous page')."</li>
							<li onclick=\"$page_first_link\">".__('First page')."</li></ul></li>
							</ul>";
						}

					if ($search && $feed_id >= 0 && get_pref($link, 'ENABLE_LABELS') && GLOBAL_ENABLE_LABELS) {
						print "<li class=\"top3\">
							<a href=\"javascript:labelFromSearch('$search', '$search_mode',
								'$match_on', '$feed_id', '$is_cat');\">
								".__('Convert to label')."</a></td>";
					}
					print "	
					</td>"; 

			} else {
			// old style subtoolbar:

				print "<td class=\"headlineActions$rtl_cpart\">".
					__('Select:')."
								<a href=\"$sel_all_link\">".__('All')."</a>,
								<a href=\"$sel_unread_link\">".__('Unread')."</a>,
								<a href=\"$sel_none_link\">".__('None')."</a>
						&nbsp;&nbsp;".
						__('Toggle:')." <a href=\"$tog_unread_link\">".__('Unread')."</a>,
							<a href=\"$tog_marked_link\">".__('Starred')."</a>
						&nbsp;&nbsp;".
						__('Mark as read:')."
							<a href=\"#\" onclick=\"$catchup_page_link\">".__('Page')."</a>,
							<a href=\"#\" onclick=\"$catchup_feed_link\">".__('Feed')."</a>";

				if ($search && $feed_id >= 0 && get_pref($link, 'ENABLE_LABELS') && GLOBAL_ENABLE_LABELS) {

					print "&nbsp;&nbsp;
							<a href=\"javascript:labelFromSearch('$search', '$search_mode',
								'$match_on', '$feed_id', '$is_cat');\">
							".__('Convert to label')."</a>";
				}

				print "</td>";  

			}

/*			if ($search && $feed_id >= 0 && get_pref($link, 'ENABLE_LABELS') && GLOBAL_ENABLE_LABELS) {
				print "<td class=\"headlineActions$rtl_cpart\">
					<a href=\"javascript:labelFromSearch('$search', '$search_mode',
							'$match_on', '$feed_id', '$is_cat');\">
						".__('Convert to Label')."</a></td>";
} */

			print "<td class=\"headlineTitle$rtl_cpart\">";
		
			if ($feed_site_url) {
				if (!$bottom) {
					$target = "target=\"_blank\"";
				}
				print "<a $target href=\"$feed_site_url\">$feed_title</a>";
			} else {
				print $feed_title;
			}

			if ($search) {
				$search_q = "&q=$search&m=$match_on&smode=$search_mode";
			}

			if ($user_page_offset > 1) {
				print " [$user_page_offset] ";
			}

			if (!$bottom) {
				print "
					<a target=\"_new\" 
						href=\"backend.php?op=rss&id=$feed_id&is_cat=$is_cat$search_q\">
						<img class=\"noborder\" 
							alt=\"".__('Generated feed')."\" src=\"images/feed-icon-12x12.png\">
					</a>";
			}
				
			print "</td>";
			print "</tr></table>";

		}

	function outputFeedList($link, $tags = false) {

		print "<ul class=\"feedList\" id=\"feedList\">";

		$owner_uid = $_SESSION["uid"];

		/* virtual feeds */

		if (get_pref($link, 'ENABLE_FEED_CATS')) {
			print "<li class=\"feedCat\">".__('Special')."</li>";
			print "<li id=\"feedCatHolder\" class=\"feedCatHolder\"><ul class=\"feedCatList\">";
		}

		$num_starred = getFeedUnread($link, -1);

		$class = "virt";

		if ($num_starred > 0) $class .= "Unread";

		printFeedEntry(-1, $class, __("Starred articles"), $num_starred, 
			"images/mark_set.png", $link);

		if (get_pref($link, 'ENABLE_FEED_CATS')) {
			print "</ul>";
		}

		if (!$tags) {

			if (GLOBAL_ENABLE_LABELS && get_pref($link, 'ENABLE_LABELS')) {
	
				$result = db_query($link, "SELECT id,sql_exp,description FROM
					ttrss_labels WHERE owner_uid = '$owner_uid' ORDER by description");
		
				if (db_num_rows($result) > 0) {
					if (get_pref($link, 'ENABLE_FEED_CATS')) {
						print "<li class=\"feedCat\">".__('Labels')."</li>";
						print "<li id=\"feedCatHolder\" class=\"feedCatHolder\"><ul class=\"feedCatList\">";
					} else {
						print "<li><hr></li>";
					}
				}
		
				while ($line = db_fetch_assoc($result)) {
	
					error_reporting (0);

					$label_id = -$line['id'] - 11;
					$count = getFeedUnread($link, $label_id);

					$class = "label";
	
					if ($count > 0) {
						$class .= "Unread";
					}
					
					error_reporting (DEFAULT_ERROR_LEVEL);
	
					printFeedEntry($label_id, 
						$class, $line["description"], 
						$count, "images/label.png", $link);
		
				}

				if (db_num_rows($result) > 0) {
					if (get_pref($link, 'ENABLE_FEED_CATS')) {
						print "</ul>";
					}
				}

			}

			if (!get_pref($link, 'ENABLE_FEED_CATS')) {
				print "<li><hr></li>";
			}

			if (get_pref($link, 'ENABLE_FEED_CATS')) {
				if (get_pref($link, "FEEDS_SORT_BY_UNREAD")) {
					$order_by_qpart = "category,unread DESC,title";
				} else {
					$order_by_qpart = "category,title";
				}
			} else {
				if (get_pref($link, "FEEDS_SORT_BY_UNREAD")) {
					$order_by_qpart = "unread DESC,title";
				} else {		
					$order_by_qpart = "title";
				}
			}

			$result = db_query($link, "SELECT ttrss_feeds.*,
				SUBSTRING(last_updated,1,19) AS last_updated_noms,
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
	
			$total_unread = 0;

			$category = "";

			$short_date = get_pref($link, 'SHORT_DATE_FORMAT');
	
			while ($line = db_fetch_assoc($result)) {
			
				$feed = trim($line["title"]);

				if (!$feed) $feed = "[Untitled]";

				$feed_id = $line["id"];	  
	
				$subop = $_GET["subop"];
				
				$unread = $line["unread"];

				if (get_pref($link, 'HEADLINES_SMART_DATE')) {
					$last_updated = smart_date_time(strtotime($line["last_updated_noms"]));
				} else {
					$last_updated = date($short_date, strtotime($line["last_updated_noms"]));
				}

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
					$tmp_category = __("Uncategorized");
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
					if ($category == __("Uncategorized")) {
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

					$cat_id = sprintf("%d", $cat_id);

					$cat_unread = getCategoryUnread($link, $cat_id);

					$catctr_class = ($cat_unread > 0) ? "catCtrHasUnread" : "catCtrNoUnread";

					print "<li class=\"feedCat\" id=\"FCAT-$cat_id\">
						<a id=\"FCATN-$cat_id\" href=\"javascript:toggleCollapseCat($cat_id)\">$tmp_category</a>
							<a href=\"#\" onclick=\"javascript:viewCategory($cat_id)\" id=\"FCAP-$cat_id\">
							<span id=\"FCATCTR-$cat_id\" title=\"Click to browse category\" 
							class=\"$catctr_class\">($cat_unread)</span> $ellipsis
							</a></li>";

					// !!! NO SPACE before <ul...feedCatList - breaks firstChild DOM function
					// -> keyboard navigation, etc.
					print "<li id=\"feedCatHolder\" class=\"$holder_class\"><ul class=\"feedCatList\" id=\"FCATLIST-$cat_id\">";
				}
	
				printFeedEntry($feed_id, $class, $feed, $unread, 
					ICONS_DIR."/$feed_id.ico", $link, $rtl_content, 
					$last_updated, $line["last_error"]);
	
				++$lnum;
			}

			if (db_num_rows($result) == 0) {
				print "<li>".__('No feeds to display.')."</li>";
			}

		} else {

			// tags

/*			$result = db_query($link, "SELECT tag_name,count(ttrss_entries.id) AS count
				FROM ttrss_tags,ttrss_entries,ttrss_user_entries WHERE
				post_int_id = ttrss_user_entries.int_id AND 
				unread = true AND ref_id = ttrss_entries.id
				AND ttrss_tags.owner_uid = '$owner_uid' GROUP BY tag_name	
			UNION
				select tag_name,0 as count FROM ttrss_tags WHERE owner_uid = '$owner_uid'
			ORDER BY tag_name"); */

			if (get_pref($link, 'ENABLE_FEED_CATS')) {
				print "<li class=\"feedCat\">".__('Tags')."</li>";
				print "<li id=\"feedCatHolder\"><ul class=\"feedCatList\">";
			}

			$result = db_query($link, "SELECT tag_name,SUM((SELECT COUNT(int_id) 
				FROM ttrss_user_entries WHERE int_id = post_int_id 
					AND unread = true)) AS count FROM ttrss_tags 
				WHERE owner_uid = ".$_SESSION['uid']." GROUP BY tag_name ORDER BY tag_name");

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
	
				printFeedEntry($tag, $class, $tag, $unread, "images/tag.png", $link);
	
			} 

			if (db_num_rows($result) == 0) {
				print "<li>No tags to display.</li>";
			}

			if (get_pref($link, 'ENABLE_FEED_CATS')) {
				print "</ul>";
			}

		}

		print "</ul>";

	}

	function get_article_tags($link, $id) {

		$a_id = db_escape_string($id);

		$tmp_result = db_query($link, "SELECT DISTINCT tag_name, 
			owner_uid as owner FROM
			ttrss_tags WHERE post_int_id = (SELECT int_id FROM ttrss_user_entries WHERE
				ref_id = '$a_id' LIMIT 1) ORDER BY tag_name");

		$tags = array();	
	
		while ($tmp_line = db_fetch_assoc($tmp_result)) {
			array_push($tags, $tmp_line["tag_name"]);				
		}

		return $tags;
	}

	function trim_value(&$value) {
		$value = trim($value);
	}	

	function trim_array($array) {
		$tmp = $array;
		array_walk($tmp, 'trim_value');
		return $tmp;
	}

	function tag_is_valid($tag) {
		if ($tag == '') return false;
		if (preg_match("/^[0-9]*$/", $tag)) return false;

		$tag = iconv("utf-8", "utf-8", $tag);
		if (!$tag) return false;

		return true;
	}

	function render_login_form($link, $mobile = false) {
		if (!$mobile) {
			require_once "login_form.php";
		} else {
			require_once "mobile/login_form.php";
		}
	}

	// from http://developer.apple.com/internet/safari/faq.html
	function no_cache_incantation() {
		header("Expires: Mon, 22 Dec 1980 00:00:00 GMT"); // Happy birthday to me :)
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); // always modified
		header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0"); // HTTP/1.1
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma: no-cache"); // HTTP/1.0
	}

	function format_warning($msg, $id = "") {
		return "<div class=\"warning\" id=\"$id\"> 
			<img src=\"images/sign_excl.png\">$msg</div>";
	}

	function format_notice($msg) {
		return "<div class=\"notice\"> 
			<img src=\"images/sign_info.png\">$msg</div>";
	}

	function format_error($msg) {
		return "<div class=\"error\"> 
			<img src=\"images/sign_excl.png\">$msg</div>";
	}

	function print_notice($msg) {
		return print format_notice($msg);
	}

	function print_warning($msg) {
		return print format_warning($msg);
	}

	function print_error($msg) {
		return print format_error($msg);
	}


	function T_sprintf() {
		$args = func_get_args();
		return vsprintf(__(array_shift($args)), $args);
	}

	function outputArticleXML($link, $id, $feed_id, $mark_as_read = true) {

		/* we can figure out feed_id from article id anyway, why do we
		 * pass feed_id here? */

		$result = db_query($link, "SELECT feed_id FROM ttrss_user_entries
			WHERE ref_id = '$id'");

		$feed_id = db_fetch_result($result, 0, "feed_id");

		print "<article id='$id'><![CDATA[";

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

		if ($mark_as_read) {
			$result = db_query($link, "UPDATE ttrss_user_entries 
				SET unread = false,last_read = NOW() 
				WHERE ref_id = '$id' AND owner_uid = " . $_SESSION["uid"]);
		}

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

			print "<div class=\"postHeader\">";

			$entry_author = $line["author"];

			if ($entry_author) {
				$entry_author = __(" - by ") . $entry_author;
			}

			$parsed_updated = date(get_pref($link, 'LONG_DATE_FORMAT'), 
				strtotime($line["updated"]));
		
			print "<div class=\"postDate$rtl_class\">$parsed_updated</div>";

			if ($line["link"]) {
				print "<div clear='both'><a $link_target href=\"" . $line["link"] . "\">" . 
					$line["title"] . "</a>$entry_author</div>";
			} else {
				print "<div clear='both'>" . $line["title"] . "$entry_author</div>";
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
				$tag_escaped = str_replace("'", "\\'", $tag);

				$tag_str = "<a href=\"javascript:viewfeed('$tag_escaped')\">$tag</a>, ";
				
				if ($num_tags == 6) {
					$tags_str .= "<a href=\"javascript:showBlockElement('allEntryTags')\">...</a>";
				} else if ($num_tags < 6) {
					$tags_str .= $tag_str;
				}
				$f_tags_str .= $tag_str;
			}

			$tags_str = preg_replace("/, $/", "", $tags_str);
			$f_tags_str = preg_replace("/, $/", "", $f_tags_str);

			if (!$entry_comments) $entry_comments = "&nbsp;"; # placeholder

			if (!$tags_str) $tags_str = '<span class="tagList">'.__('no tags').'</span>';

			print "<div style='float : right'>$tags_str 
				<a title=\"Edit tags for this article\" 
					href=\"javascript:editArticleTags($id, $feed_id)\">(+)</a></div>
				<div clear='both'>$entry_comments</div>";

			print "</div>";

			print "<div class=\"postIcon\">" . $feed_icon . "</div>";
			print "<div class=\"postContent\">";
			
			if (db_num_rows($tmp_result) > 0) {
				print "<div id=\"allEntryTags\">".__('Tags:')." $f_tags_str</div>";
			}

			$line["content"] = sanitize_rss($link, $line["content"]);

			if (get_pref($link, 'OPEN_LINKS_IN_NEW_WINDOW')) {
				$line["content"] = preg_replace("/href=/i", "target=\"_new\" href=", $line["content"]);
			}

			print $line["content"] . "</div>";
			
			print "</div>";

		}

		print "]]></article>";

	}

	function outputHeadlinesList($link, $feed, $subop, $view_mode, $limit, $cat_view,
					$next_unread_feed, $offset) {

		$timing_info = getmicrotime();

		$topmost_article_ids = array();

		if (!$offset) $offset = 0;

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
				print "<div align='center'>".__('Feed not found.')."</div>";				
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

		print "<div id=\"headlinesContainer\" $rtl_tag>";

		if (!$result) {
			print "<div align='center'>".__("Could not display feed (query failed). Please check label match syntax or local configuration.")."</div>";
			return;
		}

		print_headline_subtoolbar($link, $feed_site_url, $feed_title, false, 
			$rtl_content, $feed, $cat_view, $search, $match_on, $search_mode, 
			$offset, $limit);

		print "<div id=\"headlinesInnerContainer\">";

		if (db_num_rows($result) > 0) {

#			print "\{$offset}";

			if (!get_pref($link, 'COMBINED_DISPLAY_MODE')) {
				print "<table class=\"headlinesList\" id=\"headlinesList\" 
					cellspacing=\"0\">";
			}

			$lnum = 0;
	
			error_reporting (DEFAULT_ERROR_LEVEL);
	
			$num_unread = 0;
	
			while ($line = db_fetch_assoc($result)) {

				$class = ($lnum % 2) ? "even" : "odd";
	
				$id = $line["id"];
				$feed_id = $line["feed_id"];

				if (count($topmost_article_ids) < 5) {
					array_push($topmost_article_ids, $id);
				}

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
					$marked_pic = "<img id=\"FMPIC-$id\" src=\"images/mark_set.png\" 
						class=\"markedPic\"
						alt=\"Reset mark\" onclick='javascript:tMark($id)'>";
				} else {
					$marked_pic = "<img id=\"FMPIC-$id\" src=\"images/mark_unset.png\" 
						class=\"markedPic\"
						alt=\"Set mark\" onclick='javascript:tMark($id)'>";
				}

#				$content_link = "<a target=\"_new\" href=\"".$line["link"]."\">" .
#					$line["title"] . "</a>";

				$content_link = "<a href=\"javascript:view($id,$feed_id);\">" .
					$line["title"] . "</a>";

#				$content_link = "<a href=\"javascript:viewContentUrl('".$line["link"]."');\">" .
#					$line["title"] . "</a>";

				if (get_pref($link, 'HEADLINES_SMART_DATE')) {
					$updated_fmt = smart_date_time(strtotime($line["updated_noms"]));
				} else {
					$short_date = get_pref($link, 'SHORT_DATE_FORMAT');
					$updated_fmt = date($short_date, strtotime($line["updated_noms"]));
				}				

				if (get_pref($link, 'SHOW_CONTENT_PREVIEW')) {
					$content_preview = truncate_string(strip_tags($line["content_preview"]), 
						100);
				}

				$entry_author = $line["author"];

				if ($entry_author) {
					$entry_author = " - by $entry_author";
				}

				if (!get_pref($link, 'COMBINED_DISPLAY_MODE')) {
					
					print "<tr class='$class' id='RROW-$id'>";
		
					print "<td class='hlUpdPic'>$update_pic</td>";
		
					print "<td class='hlSelectRow'>
						<input type=\"checkbox\" onclick=\"tSR(this)\"
							id=\"RCHK-$id\">
						</td>";
		
					print "<td class='hlMarkedPic'>$marked_pic</td>";

					if ($line["feed_title"]) {			
						print "<td class='hlContent'>$content_link</td>";
						print "<td class='hlFeed'>
							<a href=\"javascript:viewfeed($feed_id, '', false)\">".
								$line["feed_title"]."</a>&nbsp;</td>";
					} else {			
						print "<td class='hlContent' valign='middle'>";

						print "<a href=\"javascript:view($id,$feed_id);\">" .
							$line["title"];

						if (get_pref($link, 'SHOW_CONTENT_PREVIEW')) {
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

					print "<div class=\"articleUpdated\">$updated_fmt</div>";
					
					print "<a class=\"title\" 
						onclick=\"javascript:toggleUnread($id, 0)\"
						target=\"new\" href=\"".$line["link"]."\">".$line["title"]."</a>";

					print $entry_author;

					if ($line["feed_title"]) {	
						print "&nbsp;(<a href='javascript:viewfeed($feed_id)'>".$line["feed_title"]."</a>)";
					}

					print "</div>";

					print "<div class=\"cdmContent\">" . $line["content_preview"] . "</div><br clear=\"all\">";

					print "<div class=\"cdmFooter\">";

					print "$marked_pic";

					print "<input type=\"checkbox\" onclick=\"toggleSelectRowById(this, 
							'RROW-$id')\" class=\"feedCheckBox\" id=\"RCHK-$id\">";

					$tags = get_article_tags($link, $id);

					$tags_str = "";

					foreach ($tags as $tag) {
						$num_tags++;
						$tags_str .= "<a href=\"javascript:viewfeed('$tag')\">$tag</a>, "; 
					}

					$tags_str = preg_replace("/, $/", "", $tags_str);

					if ($tags_str == "") $tags_str = "no tags";
	
					print " $tags_str <a title=\"Edit tags for this article\" 
							href=\"javascript:editArticleTags($id, $feed_id, true)\">(+)</a>";

					print "</div>";

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

//			print_headline_subtoolbar($link, 
//				"javascript:catchupPage()", "Mark page as read", true, $rtl_content);


		} else {
			print "<div class='whiteBox'>".__('No articles found.')."</div>";
		}

		print "</div>";

		print "</div>";

		return $topmost_article_ids;
	}

// from here: http://www.roscripts.com/Create_tag_cloud-71.html

	function printTagCloud($link) {

		/* get first ref_id to count from */

		/*

		$query = "";

		if (DB_TYPE == "pgsql") {
			$query = "SELECT MIN(id) AS id FROM ttrss_user_entries, ttrss_entries 
				WHERE int_id = id AND owner_uid = ".$_SESSION["uid"]."
			  	AND date_entered > NOW() - INTERVAL '30 days'";
		} else {
			$query = "SELECT MIN(id) AS id FROM ttrss_user_entries, ttrss_entries 
				WHERE int_id = id AND owner_uid = ".$_SESSION["uid"]." 
				AND date_entered > DATE_SUB(NOW(), INTERVAL 30 DAY)";
		}

		$result = db_query($link, $query);
		$first_id = db_fetch_result($result, 0, "id"); */

		//AND post_int_id >= '$first_id'
		$query = "SELECT tag_name, COUNT(post_int_id) AS count 
			FROM ttrss_tags WHERE owner_uid = ".$_SESSION["uid"]." 
			GROUP BY tag_name ORDER BY count DESC LIMIT 50";

		$result = db_query($link, $query);

		$tags = array();

		while ($line = db_fetch_assoc($result)) {
			$tags[$line["tag_name"]] = $line["count"];
		}

		ksort($tags);

		$max_size = 32; // max font size in pixels
		$min_size = 11; // min font size in pixels
		   
		// largest and smallest array values
		$max_qty = max(array_values($tags));
		$min_qty = min(array_values($tags));
		   
		// find the range of values
		$spread = $max_qty - $min_qty;
		if ($spread == 0) { // we don't want to divide by zero
				$spread = 1;
		}
		   
		// set the font-size increment
		$step = ($max_size - $min_size) / ($spread);
		   
		// loop through the tag array
		foreach ($tags as $key => $value) {
			// calculate font-size
			// find the $value in excess of $min_qty
			// multiply by the font-size increment ($size)
			// and add the $min_size set above
			$size = round($min_size + (($value - $min_qty) * $step));

			$key_escaped = str_replace("'", "\\'", $key);

			echo "<a href=\"javascript:viewfeed('$key_escaped') \" style=\"font-size: " . 
				$size . "px\" title=\"$value articles tagged with " . 
				$key . '">' . $key . '</a> ';
		}
	}

	function print_checkpoint($n, $s) {
		$ts = getmicrotime();	
		echo sprintf("<!-- CP[$n] %.4f seconds -->", $ts - $s);
		return $ts;
	}

	function sanitize_tag($tag) {
		$tag = trim($tag);

		$tag = mb_strtolower($tag, 'utf-8');

		$tag = preg_replace('/[\"\+\>\<]/', "", $tag);	

//		$tag = str_replace('"', "", $tag);	
//		$tag = str_replace("+", " ", $tag);	
		$tag = str_replace("technorati tag: ", "", $tag);

		return $tag;
	}
?>

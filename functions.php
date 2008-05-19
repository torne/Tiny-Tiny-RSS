<?php

/*	if ($_GET["debug"]) {
		define('DEFAULT_ERROR_LEVEL', E_ALL);
	} else {
		define('DEFAULT_ERROR_LEVEL', E_ERROR | E_WARNING | E_PARSE);
	} */

	require_once 'config.php';

	if (DB_TYPE == "pgsql") {
		define('SUBSTRING_FOR_DATE', 'SUBSTRING_FOR_DATE');
	} else {
		define('SUBSTRING_FOR_DATE', 'SUBSTRING');
	}

	/**
	 * Return available translations names.
	 * 
	 * @access public
	 * @return array A array of available translations.
	 */
	function get_translations() {
		$tr = array(
					"auto"  => "Detect automatically",
					"en_US" => "English",
					"fr_FR" => "Français",
					"hu_HU" => "Magyar (Hungarian)",
					"nb_NO" => "Norwegian bokmål",
					"ru_RU" => "Русский",
					"pt_BR" => "Portuguese/Brazil",
					"zh_CN" => "Simplified Chinese");

		return $tr;
	}

	if (ENABLE_TRANSLATIONS == true) { // If translations are enabled.
		require_once "accept-to-gettext.php";
		require_once "gettext/gettext.inc";

		function startup_gettext() {
	
			# Get locale from Accept-Language header
			$lang = al2gt(array_keys(get_translations()), "text/html");

			if (defined('_TRANSLATION_OVERRIDE_DEFAULT')) {
				$lang = _TRANSLATION_OVERRIDE_DEFAULT;
			}

			if ($_COOKIE["ttrss_lang"] && $_COOKIE["ttrss_lang"] != "auto") {				
				$lang = $_COOKIE["ttrss_lang"];
			}

			if ($lang) {
				if (defined('LC_MESSAGES')) {
					_setlocale(LC_MESSAGES, $lang);
				} else if (defined('LC_ALL')) {
					_setlocale(LC_ALL, $lang);
				} else {
					die("can't setlocale(): please set ENABLE_TRANSLATIONS to false in config.php");
				}
				_bindtextdomain("messages", "locale");
				_textdomain("messages");
				_bind_textdomain_codeset("messages", "UTF-8");
			}
		}

		startup_gettext();

	} else { // If translations are enabled.
		function __($msg) {
			return $msg;
		}
		function startup_gettext() {
			// no-op
			return true;
		}
	} // If translations are enabled.

	require_once 'db-prefs.php';
	require_once 'compat.php';
	require_once 'errors.php';
	require_once 'version.php';

	require_once 'phpmailer/class.phpmailer.php';

	define('MAGPIE_USER_AGENT_EXT', ' (Tiny Tiny RSS/' . VERSION . ')');
	define('MAGPIE_OUTPUT_ENCODING', 'UTF-8');

	require_once "simplepie/simplepie.inc";
	require_once "magpierss/rss_fetch.inc";
	require_once 'magpierss/rss_utils.inc';

	/**
	 * Print a timestamped debug message.
	 * 
	 * @param string $msg The debug message.
	 * @return void
	 */
	function _debug($msg) {
		$ts = strftime("%H:%M:%S", time());
		if (function_exists('posix_getpid')) {
			$ts = "$ts/" . posix_getpid();
		}
		print "[$ts] $msg\n";
	} // function _debug

	/**
	 * Purge a feed old posts.
	 * 
	 * @param mixed $link A database connection.
	 * @param mixed $feed_id The id of the purged feed.
	 * @param mixed $purge_interval Olderness of purged posts.
	 * @param boolean $debug Set to True to enable the debug. False by default.
	 * @access public
	 * @return void
	 */
	function purge_feed($link, $feed_id, $purge_interval, $debug = false) {

		if (!$purge_interval) $purge_interval = feed_purge_interval($link, $feed_id);

		$rows = -1;

		$result = db_query($link, 
			"SELECT owner_uid FROM ttrss_feeds WHERE id = '$feed_id'");

		$owner_uid = false;

		if (db_num_rows($result) == 1) {
			$owner_uid = db_fetch_result($result, 0, "owner_uid");
		}

		if (!$owner_uid) return;

		$purge_unread = get_pref($link, "PURGE_UNREAD_ARTICLES",
			$owner_uid, false);

		if (!$purge_unread) $query_limit = " unread = false AND ";

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
					$query_limit
					ttrss_entries.date_entered < NOW() - INTERVAL '$purge_interval days'");

			} else {

				$result = db_query($link, "DELETE FROM ttrss_user_entries 
					USING ttrss_entries 
					WHERE ttrss_entries.id = ref_id AND 
					marked = false AND 
					feed_id = '$feed_id' AND 
					$query_limit
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
				$query_limit
				ttrss_entries.date_entered < DATE_SUB(NOW(), INTERVAL $purge_interval DAY)");
					
			$rows = mysql_affected_rows($link);

		}

		if ($debug) {
			_debug("Purged feed $feed_id ($purge_interval): deleted $rows articles");
		}
	} // function purge_feed

	/**
	 * Purge old posts from old feeds.
	 * 
	 * @param mixed $link A database connection
	 * @param boolean $do_output Set to true to enable printed output, false by default.
	 * @param integer $limit The maximal number of removed posts.
	 * @access public
	 * @return void
	 */
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
		$result = db_query($link, "DELETE FROM ttrss_entries WHERE 
			(SELECT COUNT(int_id) FROM ttrss_user_entries WHERE ref_id = id) = 0");

		if ($do_output) {
			$rows = db_affected_rows($link, $result);
			_debug("Purged $rows orphaned posts.");
		}

	} // function global_purge_old_posts

	function feed_purge_interval($link, $feed_id) {

		$result = db_query($link, "SELECT purge_interval, owner_uid FROM ttrss_feeds 
			WHERE id = '$feed_id'");

		if (db_num_rows($result) == 1) {
			$purge_interval = db_fetch_result($result, 0, "purge_interval");
			$owner_uid = db_fetch_result($result, 0, "owner_uid");

			if ($purge_interval == 0) $purge_interval = get_pref($link, 
				'PURGE_OLD_DAYS', $user_id);

			return $purge_interval;

		} else {
			return -1;
		}
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

	function get_feed_update_interval($link, $feed_id) {
		$result = db_query($link, "SELECT owner_uid, update_interval FROM
			ttrss_feeds WHERE id = '$feed_id'");

		if (db_num_rows($result) == 1) {
			$update_interval = db_fetch_result($result, 0, "update_interval");
			$owner_uid = db_fetch_result($result, 0, "owner_uid");

			if ($update_interval != 0) {
				return $update_interval;
			} else {
				return get_pref($link, 'DEFAULT_UPDATE_INTERVAL', $owner_uid, false);
			}

		} else {
			return -1;
		}
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
			".SUBSTRING_FOR_DATE."(last_updated,1,19) AS last_updated,
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

	/**
	 * Try to determine the favicon URL for a feed.
	 * adapted from wordpress favicon plugin by Jeff Minard (http://thecodepro.com/)
	 * http://dev.wp-plugins.org/file/favatars/trunk/favatars.php
	 * 
	 * @param string $url A feed or page URL
	 * @access public
	 * @return mixed The favicon URL, or false if none was found.
	 */
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
	} // function get_favicon_url

	/**
	 * Check if a link is a valid and working URL.
	 * 
	 * @param mixed $link A URL to check
	 * @access public
	 * @return boolean True if the URL is valid, false otherwise.
	 */
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

	} // function url_validate

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

		if (!$_GET["daemon"] && !$ignore_daemon) {
			return false;
		}

		if (defined('DAEMON_EXTENDED_DEBUG') || $_GET['xdebug']) {
			_debug("update_rss_feed: start");
		}

		if (!$ignore_daemon) {

			if (DB_TYPE == "pgsql") {
					$updstart_thresh_qpart = "(ttrss_feeds.last_update_started IS NULL OR ttrss_feeds.last_update_started < NOW() - INTERVAL '120 seconds')";
				} else {
					$updstart_thresh_qpart = "(ttrss_feeds.last_update_started IS NULL OR ttrss_feeds.last_update_started < DATE_SUB(NOW(), INTERVAL 120 SECOND))";
				}			
	
			$result = db_query($link, "SELECT id,update_interval,auth_login,
				auth_pass,cache_images,update_method
				FROM ttrss_feeds WHERE id = '$feed' AND $updstart_thresh_qpart");

		} else {

			$result = db_query($link, "SELECT id,update_interval,auth_login,
				auth_pass,cache_images,update_method
				FROM ttrss_feeds WHERE id = '$feed'");

		}

		if (db_num_rows($result) == 0) {
			if (defined('DAEMON_EXTENDED_DEBUG') || $_GET['xdebug']) {
				_debug("update_rss_feed: feed $feed [$feed_url] NOT FOUND/SKIPPED");
			}		
			return false;
		}

		$update_method = db_fetch_result($result, 0, "update_method");

		db_query($link, "UPDATE ttrss_feeds SET last_update_started = NOW()
			WHERE id = '$feed'");

		$auth_login = db_fetch_result($result, 0, "auth_login");
		$auth_pass = db_fetch_result($result, 0, "auth_pass");

		if (ALLOW_SELECT_UPDATE_METHOD) {
			if (ENABLE_SIMPLEPIE) {
				$use_simplepie = $update_method != 1;
			} else {
				$use_simplepie = $update_method == 2;
			}
		} else {
			$use_simplepie = ENABLE_SIMPLEPIE;
		}

		if (defined('DAEMON_EXTENDED_DEBUG') || $_GET['xdebug']) {
			_debug("use simplepie: $use_simplepie (feed setting: $update_method)\n");
		}

		if (!$use_simplepie) {
			$auth_login = urlencode($auth_login);
			$auth_pass = urlencode($auth_pass);
		}

		$update_interval = db_fetch_result($result, 0, "update_interval");
		$cache_images = sql_bool_to_bool(db_fetch_result($result, 0, "cache_images"));

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

		if (defined('DAEMON_EXTENDED_DEBUG') || $_GET['xdebug']) {
			_debug("update_rss_feed: fetching [$fetch_url]...");
		}

		if (!defined('DAEMON_EXTENDED_DEBUG') && !$_GET['xdebug']) {
			error_reporting(0);
		}

		if (!$use_simplepie) {
			$rss = fetch_rss($fetch_url);
		} else {
			if (!is_dir(SIMPLEPIE_CACHE_DIR)) {
				mkdir(SIMPLEPIE_CACHE_DIR);
			}

			$rss = new SimplePie();
			$rss->set_useragent(SIMPLEPIE_USERAGENT . MAGPIE_USER_AGENT_EXT);
#			$rss->set_timeout(10);
			$rss->set_feed_url($fetch_url);
			$rss->set_output_encoding('UTF-8');

			if (SIMPLEPIE_CACHE_IMAGES && $cache_images) {
				if (defined('DAEMON_EXTENDED_DEBUG') || $_GET['xdebug']) {
					_debug("enabling image cache");
				}

				$rss->set_image_handler('./image.php', 'i');
			}

			if (defined('DAEMON_EXTENDED_DEBUG') || $_GET['xdebug']) {
				_debug("feed update interval (sec): " .
					get_feed_update_interval($link, $feed)*60);
			}

			if (is_dir(SIMPLEPIE_CACHE_DIR)) {
				$rss->set_cache_location(SIMPLEPIE_CACHE_DIR);
				$rss->set_cache_duration(get_feed_update_interval($link, $feed) * 60);
			}

			$rss->init();
		}

//		print_r($rss);

		if (defined('DAEMON_EXTENDED_DEBUG') || $_GET['xdebug']) {
			_debug("update_rss_feed: fetch done, parsing...");
		} else {
			error_reporting (DEFAULT_ERROR_LEVEL);
		}

		$feed = db_escape_string($feed);

		if ($use_simplepie) {
			$fetch_ok = !$rss->error();
		} else {
			$fetch_ok = !!$rss;
		}

		if ($fetch_ok) {

			if (defined('DAEMON_EXTENDED_DEBUG') || $_GET['xdebug']) {
				_debug("update_rss_feed: processing feed data...");
			}

//			db_query($link, "BEGIN");

			$result = db_query($link, "SELECT title,icon_url,site_url,owner_uid
				FROM ttrss_feeds WHERE id = '$feed'");

			$registered_title = db_fetch_result($result, 0, "title");
			$orig_icon_url = db_fetch_result($result, 0, "icon_url");
			$orig_site_url = db_fetch_result($result, 0, "site_url");

			$owner_uid = db_fetch_result($result, 0, "owner_uid");

			if ($use_simplepie) {
				$site_url = $rss->get_link();
			} else {
				$site_url = $rss->channel["link"];
			}

			if (get_pref($link, 'ENABLE_FEED_ICONS', $owner_uid, false)) {	
				if (defined('DAEMON_EXTENDED_DEBUG') || $_GET['xdebug']) {
					_debug("update_rss_feed: checking favicon...");
				}

				check_feed_favicon($site_url, $feed, $link);
			}

			if (!$registered_title || $registered_title == "[Unknown]") {

				if ($use_simplepie) {
					$feed_title = db_escape_string($rss->get_title());
				} else {
					$feed_title = db_escape_string($rss->channel["title"]);
				}

				if (defined('DAEMON_EXTENDED_DEBUG') || $_GET['xdebug']) {
					_debug("update_rss_feed: registering title: $feed_title");
				}
				
				db_query($link, "UPDATE ttrss_feeds SET 
					title = '$feed_title' WHERE id = '$feed'");
			}

			// weird, weird Magpie
			if (!$use_simplepie) {
				if (!$site_url) $site_url = db_escape_string($rss->channel["link_"]);
			}

			if ($site_url && $orig_site_url != db_escape_string($site_url)) {
				db_query($link, "UPDATE ttrss_feeds SET 
					site_url = '$site_url' WHERE id = '$feed'");
			}

//			print "I: " . $rss->channel["image"]["url"];

			if (!$use_simplepie) {
				$icon_url = $rss->image["url"];
			} else {
				$icon_url = $rss->get_image_url();
			}

			if ($icon_url && !$orig_icon_url != db_escape_string($icon_url)) {
				$icon_url = db_escape_string($icon_url);
				db_query($link, "UPDATE ttrss_feeds SET icon_url = '$icon_url' WHERE id = '$feed'");
			}

			if (defined('DAEMON_EXTENDED_DEBUG') || $_GET['xdebug']) {
				_debug("update_rss_feed: loading filters...");
			}

			$filters = load_filters($link, $feed, $owner_uid);

			if ($use_simplepie) {
				$iterator = $rss->get_items();
			} else {
				$iterator = $rss->items;
				if (!$iterator || !is_array($iterator)) $iterator = $rss->entries;
				if (!$iterator || !is_array($iterator)) $iterator = $rss;
			}

			if (!is_array($iterator)) {
				/* db_query($link, "UPDATE ttrss_feeds 
					SET last_error = 'Parse error: can\'t find any articles.'
					WHERE id = '$feed'"); */

				// clear any errors and mark feed as updated if fetched okay
				// even if it's blank

				if (defined('DAEMON_EXTENDED_DEBUG') || $_GET['xdebug']) {
					_debug("update_rss_feed: entry iterator is not an array, no articles?");
				}

				db_query($link, "UPDATE ttrss_feeds 
					SET last_updated = NOW(), last_error = '' WHERE id = '$feed'");

				return; // no articles
			}

			if (defined('DAEMON_EXTENDED_DEBUG') || $_GET['xdebug']) {
				_debug("update_rss_feed: processing articles...");
			}

			foreach ($iterator as $item) {

				if ($_GET['xdebug']) {
					print_r($item);

				}

				if ($use_simplepie) {
					$entry_guid = $item->get_id();
					if (!$entry_guid) $entry_guid = $item->get_link();
					if (!$entry_guid) $entry_guid = make_guid_from_title($item->get_title());

				} else {

					$entry_guid = $item["id"];

					if (!$entry_guid) $entry_guid = $item["guid"];
					if (!$entry_guid) $entry_guid = $item["link"];
					if (!$entry_guid) $entry_guid = make_guid_from_title($item["title"]);
				}

				if (defined('DAEMON_EXTENDED_DEBUG') || $_GET['xdebug']) {
					_debug("update_rss_feed: guid $entry_guid");
				}

				if (!$entry_guid) continue;

				$entry_timestamp = "";

				if ($use_simplepie) {
					$entry_timestamp = strtotime($item->get_date());
				} else {
					$rss_2_date = $item['pubdate'];
					$rss_1_date = $item['dc']['date'];
					$atom_date = $item['issued'];
					if (!$atom_date) $atom_date = $item['updated'];
			
					if ($atom_date != "") $entry_timestamp = parse_w3cdtf($atom_date);
					if ($rss_1_date != "") $entry_timestamp = parse_w3cdtf($rss_1_date);
					if ($rss_2_date != "") $entry_timestamp = strtotime($rss_2_date);
				}

				if (defined('DAEMON_EXTENDED_DEBUG') || $_GET['xdebug']) {
					_debug("update_rss_feed: date $entry_timestamp");
				}

				if ($entry_timestamp == "" || $entry_timestamp == -1 || !$entry_timestamp) {
					$entry_timestamp = time();
					$no_orig_date = 'true';
				} else {
					$no_orig_date = 'false';
				}

				$entry_timestamp_fmt = strftime("%Y/%m/%d %H:%M:%S", $entry_timestamp);

				if ($use_simplepie) {
					$entry_title = $item->get_title();
				} else {
					$entry_title = trim(strip_tags($item["title"]));
				}

				if ($use_simplepie) {
					$entry_link = $item->get_link();
				} else {
					// strange Magpie workaround
					$entry_link = $item["link_"];
					if (!$entry_link) $entry_link = $item["link"];
				}

				if (defined('DAEMON_EXTENDED_DEBUG') || $_GET['xdebug']) {
					_debug("update_rss_feed: title $entry_title");
				}

				if (!$entry_title) $entry_title = date("Y-m-d H:i:s", $entry_timestamp);;

				$entry_link = strip_tags($entry_link);

				if ($use_simplepie) {
					$entry_content = $item->get_content();
					if (!$entry_content) $entry_content = $item->get_description();
				} else {
					$entry_content = $item["content:escaped"];

					if (!$entry_content) $entry_content = $item["content:encoded"];
					if (!$entry_content) $entry_content = $item["content"];

					// Magpie bugs are getting ridiculous
					if (trim($entry_content) == "Array") $entry_content = false;

					if (!$entry_content) $entry_content = $item["atom_content"];
					if (!$entry_content) $entry_content = $item["summary"];
					if (!$entry_content) $entry_content = $item["description"];

					// WTF
					if (is_array($entry_content)) {
						$entry_content = $entry_content["encoded"];
						if (!$entry_content) $entry_content = $entry_content["escaped"];
					} 
				}

				if ($_GET["xdebug"]) {
					print "update_rss_feed: content: ";
					print_r(htmlspecialchars($entry_content));
				}

				$entry_content_unescaped = $entry_content;

				if ($use_simplepie) {
					$entry_comments = strip_tags($item->data["comments"]);
					if ($item->get_author()) {
						$entry_author_item = $item->get_author();
						$entry_author = $entry_author_item->get_name();
						if (!$entry_author) $entry_author = $entry_author_item->get_email();

						$entry_author = db_escape_string($entry_author);
					}
				} else {
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
				}

				if (preg_match('/^[\t\n\r ]*$/', $entry_author)) $entry_author = '';

				$entry_guid = db_escape_string(strip_tags($entry_guid));
				$entry_guid = mb_substr($entry_guid, 0, 250);

				$result = db_query($link, "SELECT id FROM	ttrss_entries 
					WHERE guid = '$entry_guid'");

				$entry_content = db_escape_string($entry_content);

				$content_hash = "SHA1:" . sha1(strip_tags($entry_content));

				$entry_title = db_escape_string($entry_title);
				$entry_link = db_escape_string($entry_link);
				$entry_comments = mb_substr(db_escape_string($entry_comments), 0, 250);
				$entry_author = mb_substr($entry_author, 0, 250);

				if ($use_simplepie) {
					$num_comments = 0; #FIXME#
				} else {
					$num_comments = db_escape_string($item["slash"]["comments"]);
				}

				if (!$num_comments) $num_comments = 0;

				// parse <category> entries into tags

				if ($use_simplepie) {

					$additional_tags = array();
					$additional_tags_src = $item->get_categories();
					
					if (is_array($additional_tags_src)) {
						foreach ($additional_tags_src as $tobj) {
							array_push($additional_tags, $tobj->get_term());
						}
					}

					if (defined('DAEMON_EXTENDED_DEBUG') || $_GET['xdebug']) {
						_debug("update_rss_feed: category tags:");
						print_r($additional_tags);
					}

				} else {

					$t_ctr = $item['category#'];

					$additional_tags = false;
	
					if ($t_ctr == 0) {
						$additional_tags = false;
					} else if ($t_ctr > 0) {
						$additional_tags = array($item['category']);

						if ($item['category@term']) {
							array_push($additional_tags, $item['category@term']);
						}

						for ($i = 0; $i <= $t_ctr; $i++ ) {
							if ($item["category#$i"]) {
								array_push($additional_tags, $item["category#$i"]);
							}

							if ($item["category#$i@term"]) {
								array_push($additional_tags, $item["category#$i@term"]);
							}
						}
					}
	
					// parse <dc:subject> elements
	
					$t_ctr = $item['dc']['subject#'];
	
					if ($t_ctr > 0) {
						$additional_tags = array($item['dc']['subject']);

						for ($i = 0; $i <= $t_ctr; $i++ ) {
							if ($item['dc']["subject#$i"]) {
								array_push($additional_tags, $item['dc']["subject#$i"]);
							}
						}
					}
				}

				// enclosures

				$enclosures = array();

				if ($use_simplepie) {
					$encs = $item->get_enclosures();

					if (is_array($encs)) {
						foreach ($encs as $e) {
							$e_item = array(
								$e->link, $e->type, $e->length);
	
							array_push($enclosures, $e_item);
						}
					}

				} else {
					$e_ctr = $item['enclosure#'];

					if ($e_ctr > 0) {
						$e_item = array($item['enclosure@url'],
							$item['enclosure@type'],
							$item['enclosure@length']);

						array_push($enclosures, $e_item);

						for ($i = 0; $i <= $e_ctr; $i++ ) {

							if ($item["enclosure#$i@url"]) {
								$e_item = array($item["enclosure#$i@url"],
									$item["enclosure#$i@type"],
									$item["enclosure#$i@length"]);
								array_push($enclosures, $e_item);
							}
						}
					}

				}

				# sanitize content
				
				$entry_content = sanitize_article_content($entry_content);
				$entry_title = sanitize_article_content($entry_title);

				if (defined('DAEMON_EXTENDED_DEBUG') || $_GET['xdebug']) {
					_debug("update_rss_feed: done collecting data [TITLE:$entry_title]");
				}

				db_query($link, "BEGIN");

				if (db_num_rows($result) == 0) {

					if (defined('DAEMON_EXTENDED_DEBUG') || $_GET['xdebug']) {
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
						".SUBSTRING_FOR_DATE."(date_entered,1,19) as date_entered,
						".SUBSTRING_FOR_DATE."(updated,1,19) as updated,
						num_comments
					FROM 
						ttrss_entries 
					WHERE guid = '$entry_guid'");

				$entry_ref_id = 0;
				$entry_int_id = 0;

				if (db_num_rows($result) == 1) {

					if (defined('DAEMON_EXTENDED_DEBUG') || $_GET['xdebug']) {
						_debug("update_rss_feed: base guid found, checking for user record");
					}

					// this will be used below in update handler
					$orig_content_hash = db_fetch_result($result, 0, "content_hash");
					$orig_title = db_fetch_result($result, 0, "title");
					$orig_num_comments = db_fetch_result($result, 0, "num_comments");
					$orig_date_entered = strtotime(db_fetch_result($result, 
						0, "date_entered"));

					$ref_id = db_fetch_result($result, 0, "id");
					$entry_ref_id = $ref_id;

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

					if (defined('DAEMON_EXTENDED_DEBUG') || $_GET['xdebug']) {
						_debug("update_rss_feed: article filters: ");
						if (count($article_filters) != 0) {
							print_r($article_filters);
						}
					}

					if (find_article_filter($article_filters, "filter")) {
						continue;
					}

//					error_reporting (DEFAULT_ERROR_LEVEL);

					$score = calculate_article_score($article_filters);

					if (defined('DAEMON_EXTENDED_DEBUG') || $_GET['xdebug']) {
						_debug("update_rss_feed: initial score: $score");
					}

					$result = db_query($link,
						"SELECT ref_id, int_id FROM ttrss_user_entries WHERE
							ref_id = '$ref_id' AND owner_uid = '$owner_uid'
							$dupcheck_qpart");

					// okay it doesn't exist - create user entry
					if (db_num_rows($result) == 0) {

						if (defined('DAEMON_EXTENDED_DEBUG') || $_GET['xdebug']) {
							_debug("update_rss_feed: user record not found, creating...");
						}

						if ($score >= -500 && !find_article_filter($article_filters, 'catchup')) {
							$unread = 'true';
							$last_read_qpart = 'NULL';
						} else {
							$unread = 'false';
							$last_read_qpart = 'NOW()';
						}						

						if (find_article_filter($article_filters, 'mark') || $score > 1000) {
							$marked = 'true';
						} else {
							$marked = 'false';
						}

						if (find_article_filter($article_filters, 'publish')) {
							$published = 'true';
						} else {
							$published = 'false';
						}

						$result = db_query($link,
							"INSERT INTO ttrss_user_entries 
								(ref_id, owner_uid, feed_id, unread, last_read, marked, 
									published, score) 
							VALUES ('$ref_id', '$owner_uid', '$feed', $unread,
								$last_read_qpart, $marked, $published, '$score')");

						$result = db_query($link, 
							"SELECT int_id FROM ttrss_user_entries WHERE
								ref_id = '$ref_id' AND owner_uid = '$owner_uid' AND
								feed_id = '$feed' LIMIT 1");

						if (db_num_rows($result) == 1) {
							$entry_int_id = db_fetch_result($result, 0, "int_id");
						}
					} else {
						$entry_ref_id = db_fetch_result($result, 0, "ref_id");
						$entry_int_id = db_fetch_result($result, 0, "int_id");
					}

					if (defined('DAEMON_EXTENDED_DEBUG') || $_GET['xdebug']) {
						_debug("update_rss_feed: RID: $entry_ref_id, IID: $entry_int_id");
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

				if (defined('DAEMON_EXTENDED_DEBUG') || $_GET['xdebug']) {
					_debug("update_rss_feed: looking for enclosures...");
				}

				if (defined('DAEMON_EXTENDED_DEBUG') || $_GET['xdebug']) {
					print_r($enclosures);
				}

				db_query($link, "BEGIN");

				foreach ($enclosures as $enc) {
					$enc_url = db_escape_string($enc[0]);
					$enc_type = db_escape_string($enc[1]);
					$enc_dur = db_escape_string($enc[2]);

					$result = db_query($link, "SELECT id FROM ttrss_enclosures
						WHERE content_url = '$enc_url' AND post_id = '$entry_ref_id'");

					if (db_num_rows($result) == 0) {
						db_query($link, "INSERT INTO ttrss_enclosures
							(content_url, content_type, title, duration, post_id) VALUES
							('$enc_url', '$enc_type', '', '$enc_dur', '$entry_ref_id')");
					}
				}

				db_query($link, "COMMIT");

				if (defined('DAEMON_EXTENDED_DEBUG') || $_GET['xdebug']) {
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

				if (defined('DAEMON_EXTENDED_DEBUG') || $_GET['xdebug']) {
					print_r($entry_tags);
				}

				if (count($entry_tags) > 0) {
				
					db_query($link, "BEGIN");
			
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

					db_query($link, "COMMIT");
				} 

				if (defined('DAEMON_EXTENDED_DEBUG') || $_GET['xdebug']) {
					_debug("update_rss_feed: article processed");
				}
			} 

			db_query($link, "UPDATE ttrss_feeds 
				SET last_updated = NOW(), last_error = '' WHERE id = '$feed'");

//			db_query($link, "COMMIT");

		} else {

			if ($use_simplepie) {
				$error_msg = mb_substr($rss->error(), 0, 250);
			} else {
				$error_msg = mb_substr(magpie_error(), 0, 250);
			}

			if (defined('DAEMON_EXTENDED_DEBUG') || $_GET['xdebug']) {
				_debug("update_rss_feed: error fetching feed: $error_msg");
			}

			$error_msg = db_escape_string($error_msg);

			db_query($link, 
				"UPDATE ttrss_feeds SET last_error = '$error_msg', 
					last_updated = NOW() WHERE id = '$feed'");
		}

		if ($use_simplepie) {
			unset($rss);
		}

		if (defined('DAEMON_EXTENDED_DEBUG') || $_GET['xdebug']) {
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

	function calculate_article_score($filters) {
		$score = 0;

		foreach ($filters as $f) {
			if ($f[0] == "score") {
				$score += $f[1];
			};
		}
		return $score;
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

	function print_radio($id, $default, $true_is, $values, $attributes = "") {
		foreach ($values as $v) {
		
			if ($v == $default)
				$sel = "checked";
			 else
			 	$sel = "";

			if ($v == $true_is) {
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

		error_log("http_authenticate_user: ".$_SERVER["PHP_AUTH_USER"]."\n", 3, '/tmp/tt-rss.log');

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

			$pwd_hash1 = encrypt_password($password);
			$pwd_hash2 = encrypt_password($password, $login);

			if (defined('ALLOW_REMOTE_USER_AUTH') && ALLOW_REMOTE_USER_AUTH 
					&& $_SERVER["REMOTE_USER"]) {

				$login = db_escape_string($_SERVER["REMOTE_USER"]);

				$query = "SELECT id,login,access_level
	            FROM ttrss_users WHERE
					login = '$login'";

			} else {
				$query = "SELECT id,login,access_level,pwd_hash
	            FROM ttrss_users WHERE
					login = '$login' AND (pwd_hash = '$pwd_hash1' OR
						pwd_hash = '$pwd_hash2')";
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
				$_SESSION["pwd_hash"] = db_fetch_result($result, 0, "pwd_hash");
	
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
		if (SINGLE_USER_MODE) { 
			return true;
		}

		if (SESSION_CHECK_ADDRESS && $_SESSION["uid"]) {
			if ($_SESSION["ip_address"]) {
				if ($_SESSION["ip_address"] != $_SERVER["REMOTE_ADDR"]) {
					$_SESSION["login_error_msg"] = "Session failed to validate (incorrect IP)";
					return false;
				}
			}
		}

		if ($_SESSION["uid"]) {

			$result = db_query($link, 
				"SELECT pwd_hash FROM ttrss_users WHERE id = '".$_SESSION["uid"]."'");

			$pwd_hash = db_fetch_result($result, 0, "pwd_hash");

			if ($pwd_hash != $_SESSION["pwd_hash"]) {
				return false;
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

					$_SESSION["language"] = $_POST["language"];

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

				if ($_SESSION["language"] && SESSION_COOKIE_LIFETIME > 0) {
					setcookie("ttrss_lang", $_SESSION["language"], 
						time() + SESSION_COOKIE_LIFETIME);
				}
			}

		} else {
			return authenticate_user($link, "admin", null);
		}
	}

	function truncate_string($str, $max_len) {
		if (mb_strlen($str, "utf-8") > $max_len - 3) {
			return mb_substr($str, 0, $max_len, "utf-8") . "&hellip;";
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
		if (function_exists('flock')) {
			error_reporting(0);
			$fp = fopen(LOCK_DIRECTORY . "/$filename", "r");
			error_reporting(DEFAULT_ERROR_LEVEL);
			if ($fp) {
				if (flock($fp, LOCK_EX | LOCK_NB)) {
					flock($fp, LOCK_UN);
					fclose($fp);
					return false;
				}
				fclose($fp);
				return true;
			} else {
				return false;
			}
		}
		return true; // consider the file always locked and skip the test
	}

	function make_lockfile($filename) {
		$fp = fopen(LOCK_DIRECTORY . "/$filename", "w");

		if (flock($fp, LOCK_EX | LOCK_NB)) {		
			return $fp;
		} else {
			return false;
		}
	}

	function make_stampfile($filename) {
		$fp = fopen(LOCK_DIRECTORY . "/$filename", "w");

		if (flock($fp, LOCK_EX | LOCK_NB)) {
			fwrite($fp, time() . "\n");
			flock($fp, LOCK_UN);
			fclose($fp);
			return true;
		} else {
			return false;
		}
	}

	function read_stampfile($filename) {

		error_reporting(0);
		$fp = fopen(LOCK_DIRECTORY . "/$filename", "r");
		error_reporting (DEFAULT_ERROR_LEVEL);

		if ($fp) {
			if (flock($fp, LOCK_EX)) {
				$stamp = fgets($fp);
				flock($fp, LOCK_UN);
				fclose($fp);
				return $stamp;
			} else {
				return false;
			}
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

					if ($feed == -2) {
						db_query($link, "UPDATE ttrss_user_entries 
							SET unread = false,last_read = NOW()
							WHERE published = true AND owner_uid = ".$_SESSION["uid"]);
					}

					if ($feed == -3) {

						$intl = get_pref($link, "FRESH_ARTICLE_MAX_AGE");

						if (DB_TYPE == "pgsql") {
							$match_part = "updated > NOW() - INTERVAL '$intl hour' "; 
						} else {
							$match_part = "updated > DATE_SUB(NOW(), 
								INTERVAL $intl HOUR) ";
						}

						$result = db_query($link, "SELECT id FROM ttrss_entries, 
							ttrss_user_entries WHERE $match_part AND
							unread = true AND
						  	ttrss_user_entries.ref_id = ttrss_entries.id AND	
							owner_uid = ".$_SESSION["uid"]);

						$affected_ids = array();

						while ($line = db_fetch_assoc($result)) {
							array_push($affected_ids, $line["id"]);
						}

						catchupArticlesById($link, $affected_ids, 0);
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

	function update_generic_feed($link, $feed, $cat_view, $force_update = false) {
			if ($cat_view) {

				if ($feed > 0) {
					$cat_qpart = "cat_id = '$feed'";
				} else {
					$cat_qpart = "cat_id IS NULL";
				}
				
				$tmp_result = db_query($link, "SELECT id,feed_url FROM ttrss_feeds
					WHERE $cat_qpart AND owner_uid = " . $_SESSION["uid"]);

				while ($tmp_line = db_fetch_assoc($tmp_result)) {					
					$feed_url = $tmp_line["feed_url"];
					$feed_id = $tmp_line["id"];
					update_rss_feed($link, $feed_url, $feed_id, $force_update);
				}

			} else {
				$tmp_result = db_query($link, "SELECT feed_url FROM ttrss_feeds
					WHERE id = '$feed'");
				$feed_url = db_fetch_result($tmp_result, 0, "feed_url");				
				update_rss_feed($link, $feed_url, $feed, $force_update);
			}
	}

	function getAllCounters($link, $omode = "flc", $active_feed = false) {
/*		getLabelCounters($link);
		getFeedCounters($link);
		getTagCounters($link);
		getGlobalCounters($link);
		if (get_pref($link, 'ENABLE_FEED_CATS')) {
			getCategoryCounters($link);
		} */

		if (!$omode) $omode = "flc";

		getGlobalCounters($link);

		if (strchr($omode, "l")) getLabelCounters($link);
		if (strchr($omode, "f")) getFeedCounters($link, SMART_RPC_COUNTERS, $active_feed);
		if (strchr($omode, "t")) getTagCounters($link);
		if (strchr($omode, "c")) {			
			if (get_pref($link, 'ENABLE_FEED_CATS')) {
				getCategoryCounters($link);
			}
		}
	}	

	function getCategoryCounters($link) {
		# two special categories are -1 and -2 (all virtuals; all labels)

		$ctr = getCategoryUnread($link, -1);

		print "<counter type=\"category\" id=\"-1\" counter=\"$ctr\"/>";

		$ctr = getCategoryUnread($link, -2);

		print "<counter type=\"category\" id=\"-2\" counter=\"$ctr\"/>";

		$age_qpart = getMaxAgeSubquery();

		$result = db_query($link, "SELECT cat_id,SUM((SELECT COUNT(int_id) 
				FROM ttrss_user_entries, ttrss_entries WHERE feed_id = ttrss_feeds.id 
					AND id = ref_id AND $age_qpart 
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

		if ($cat >= 0) {

			if ($cat != 0) {
				$cat_query = "cat_id = '$cat'";
			} else {
				$cat_query = "cat_id IS NULL";
			}

			$age_qpart = getMaxAgeSubquery();

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
				FROM ttrss_user_entries,ttrss_entries 
				WHERE	unread = true AND ($match_part) AND id = ref_id 
				AND $age_qpart AND owner_uid = " . $_SESSION["uid"]);
	
			$unread = 0;
	
			# this needs to be rewritten
			while ($line = db_fetch_assoc($result)) {
				$unread += $line["unread"];
			}
	
			return $unread;
		} else if ($cat == -1) {
			return getFeedUnread($link, -1) + getFeedUnread($link, -2) + getFeedUnread($link, -3);
		} else if ($cat == -2) {

			$rv = getLabelCounters($link, false, true);
			$ctr = 0;

			foreach (array_keys($rv) as $k) {
				if ($k < -10) {
					$ctr += $rv[$k]["counter"];
				}
			}

			return $ctr;
		}
	}

	function getMaxAgeSubquery($days = COUNTERS_MAX_AGE) {
		if (DB_TYPE == "pgsql") {
			return "ttrss_entries.date_entered > 
				NOW() - INTERVAL '$days days'";
		} else {
			return "ttrss_entries.date_entered > 
				DATE_SUB(NOW(), INTERVAL $days DAY)";
		}
	}

	function getFeedUnread($link, $feed, $is_cat = false) {
		$n_feed = sprintf("%d", $feed);

		$age_qpart = getMaxAgeSubquery();

		if ($is_cat) {
			return getCategoryUnread($link, $n_feed);		
		} else if ($n_feed == -1) {
			$match_part = "marked = true";
		} else if ($n_feed == -2) {
			$match_part = "published = true";
		} else if ($n_feed == -3) {
			$match_part = "unread = true";

			$intl = get_pref($link, "FRESH_ARTICLE_MAX_AGE");

			if (DB_TYPE == "pgsql") {
				$match_part .= " AND updated > NOW() - INTERVAL '$intl hour' "; 
			} else {
				$match_part .= " AND updated > DATE_SUB(NOW(), INTERVAL $intl HOUR) ";
			}

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
					FROM ttrss_user_entries,ttrss_entries
					WHERE	unread = true AND
					ttrss_user_entries.ref_id = ttrss_entries.id AND
					$age_qpart AND
					($match_part) AND
					owner_uid = " . $_SESSION["uid"]);

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
				$age_qpart AND
				unread = true AND ($match_part) AND ttrss_user_entries.owner_uid = " . $_SESSION["uid"]);
				
		} else {
		
			$result = db_query($link, "SELECT COUNT(post_int_id) AS unread
				FROM ttrss_tags,ttrss_user_entries,ttrss_entries 
				WHERE tag_name = '$feed' AND post_int_id = int_id AND ref_id = ttrss_entries.id 
				AND unread = true AND $age_qpart AND
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

		$age_qpart = getMaxAgeSubquery();

		$result = db_query($link, "SELECT count(ttrss_entries.id) as c_id FROM ttrss_entries,ttrss_user_entries,ttrss_feeds
			WHERE unread = true AND 
			ttrss_user_entries.feed_id = ttrss_feeds.id AND
			ttrss_user_entries.ref_id = ttrss_entries.id AND 
			hidden = false AND
			$age_qpart AND
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

		$age_qpart = getMaxAgeSubquery();

		$result = db_query($link, "SELECT tag_name,SUM((SELECT COUNT(int_id) 
			FROM ttrss_user_entries,ttrss_entries WHERE int_id = post_int_id 
				AND ref_id = id AND $age_qpart
				AND unread = true)) AS count FROM ttrss_tags 
				WHERE owner_uid = ".$_SESSION['uid']." GROUP BY tag_name 
				ORDER BY count DESC LIMIT 55");
			
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

		$age_qpart = getMaxAgeSubquery();

		if ($smart_mode) {
			if (!$_SESSION["lctr_last_value"]) {
				$_SESSION["lctr_last_value"] = array();
			}
		}

		$ret_arr = array();
		
		$old_counters = $_SESSION["lctr_last_value"];
		$lctrs_modified = false;

		$count = getFeedUnread($link, -1);

		if (!$ret_mode) {
			print "<counter type=\"label\" id=\"-1\" counter=\"$count\"/>";
		} else {
			$ret_arr["-1"]["counter"] = $count;
			$ret_arr["-1"]["description"] = __("Starred articles");
		}

		$count = getFeedUnread($link, -2);

		if (!$ret_mode) {
			print "<counter type=\"label\" id=\"-2\" counter=\"$count\"/>";
		} else {
			$ret_arr["-2"]["counter"] = $count;
			$ret_arr["-2"]["description"] = __("Published articles");
		}

		$count = getFeedUnread($link, -3);

		if (!$ret_mode) {
			print "<counter type=\"label\" id=\"-3\" counter=\"$count\"/>";
		} else {
			$ret_arr["-3"]["counter"] = $count;
			$ret_arr["-3"]["description"] = __("Fresh articles");
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
				$age_qpart AND
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

	function getFeedCounters($link, $smart_mode = SMART_RPC_COUNTERS, $active_feed = false) {

		$age_qpart = getMaxAgeSubquery();

		if ($smart_mode) {
			if (!$_SESSION["fctr_last_value"]) {
				$_SESSION["fctr_last_value"] = array();
			}
		}

		$old_counters = $_SESSION["fctr_last_value"];

/*		$result = db_query($link, "SELECT id,last_error,parent_feed,
			".SUBSTRING_FOR_DATE."(last_updated,1,19) AS last_updated,
			(SELECT count(id) 
				FROM ttrss_entries,ttrss_user_entries 
				WHERE feed_id = ttrss_feeds.id AND 
					ttrss_user_entries.ref_id = ttrss_entries.id
				AND unread = true AND owner_uid = ".$_SESSION["uid"].") as count
			FROM ttrss_feeds WHERE owner_uid = ".$_SESSION["uid"] . "
			AND parent_feed IS NULL"); */

		$query = "SELECT ttrss_feeds.id,
				ttrss_feeds.title,
				".SUBSTRING_FOR_DATE."(ttrss_feeds.last_updated,1,19) AS last_updated, 
				last_error, 
				COUNT(ttrss_entries.id) AS count 
			FROM ttrss_feeds 
				LEFT JOIN ttrss_user_entries ON (ttrss_user_entries.feed_id = ttrss_feeds.id 
					AND ttrss_user_entries.owner_uid = ttrss_feeds.owner_uid 
					AND ttrss_user_entries.unread = true) 
				LEFT JOIN ttrss_entries ON (ttrss_user_entries.ref_id = ttrss_entries.id AND
					$age_qpart) 
			WHERE ttrss_feeds.owner_uid = ".$_SESSION["uid"]."  
				AND parent_feed IS NULL 
			GROUP BY ttrss_feeds.id, ttrss_feeds.title, ttrss_feeds.last_updated, last_error";

		$result = db_query($link, $query);
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

			$last_updated = htmlspecialchars($last_updated);

			$has_img = is_file(ICONS_DIR . "/$id.ico");

			$tmp_result = db_query($link,
				"SELECT ttrss_feeds.id,COUNT(unread) AS unread
				FROM ttrss_feeds LEFT JOIN ttrss_user_entries 
					ON (ttrss_feeds.id = ttrss_user_entries.feed_id) 
				LEFT JOIN ttrss_entries ON (ttrss_user_entries.ref_id = ttrss_entries.id) 
				WHERE parent_feed = '$id' AND $age_qpart AND unread = true GROUP BY ttrss_feeds.id");
			
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

				if ($active_feed && $id == $active_feed) {
					$has_title_part = "title=\"" . htmlspecialchars($line["title"]) . "\"";
				} else {
					$has_title_part = "";
				}

				print "<counter type=\"feed\" id=\"$id\" counter=\"$count\" $has_img_part $error_part updated=\"$last_updated\" $has_title_part/>";
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
			print "<option value=\"0\">".__('All feeds')."</option>";
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
		} else if ($id == -2) {
			return __("Published articles");
		} else if ($id == -3) {
			return __("Fresh articles");
		} else if ($id < -10) {
			$label_id = -$id - 11;
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

		print "<param key=\"theme\" value=\"".$_SESSION["theme"]."\"/>";
		print "<param key=\"daemon_enabled\" value=\"" . ENABLE_UPDATE_DAEMON . "\"/>";
		print "<param key=\"feeds_frame_refresh\" value=\"" . FEEDS_FRAME_REFRESH . "\"/>";
		print "<param key=\"daemon_refresh_only\" value=\"true\"/>";

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

		print "<param key=\"icons_location\" value=\"" . 
			ICONS_URL . "\"/>";

		print "<param key=\"hide_read_shows_special\" value=\"" . 
			(int) get_pref($link, "HIDE_READ_SHOWS_SPECIAL") . "\"/>";

		print "<param key=\"hide_feedlist\" value=\"" .
			(int) get_pref($link, "HIDE_FEEDLIST") . "\"/>";

		print "</init-params>";
	}

	function print_runtime_info($link) {
		print "<runtime-info>";

		if (ENABLE_UPDATE_DAEMON) {
			print "<param key=\"daemon_is_running\" value=\"".
				sprintf("%d", file_is_locked("update_daemon.lock")) . "\"/>";

			if (time() - $_SESSION["daemon_stamp_check"] > 30) {

				$stamp = (int)read_stampfile("update_daemon.stamp");

//				print "<param key=\"daemon_stamp_delta\" value=\"$stamp_delta\"/>";

				if ($stamp) {
					$stamp_delta = time() - $stamp;

					if ($stamp_delta > 1800) {
						$stamp_check = 0;
					} else {
						$stamp_check = 1;
						$_SESSION["daemon_stamp_check"] = time();
					}

					print "<param key=\"daemon_stamp_ok\" value=\"$stamp_check\"/>";

					$stamp_fmt = date("Y.m.d, G:i", $stamp);

					print "<param key=\"daemon_stamp\" value=\"$stamp_fmt\"/>";
				}
			}
		}

		if (CHECK_FOR_NEW_VERSION && $_SESSION["access_level"] >= 10) {
			
			if ($_SESSION["last_version_check"] + 7200 < time()) {
				$new_version_details = check_for_update($link);

				print "<param key=\"new_version_available\" value=\"".
					sprintf("%d", $new_version_details != ""). "\"/>";

				$_SESSION["last_version_check"] = time();
			}
		}

//		print "<param key=\"new_version_available\" value=\"1\"/>";

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

	function queryFeedHeadlines($link, $feed, $limit, $view_mode, $cat_view, $search, $search_mode, $match_on, $override_order = false, $offset = 0, $owner_uid = 0) {

		if (!$owner_uid) $owner_uid = $_SESSION["uid"];

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
			} else if ($feed == -2) { // published virtual feed
				$query_strategy_part = "published = true";
				$vfeed_query_part = "ttrss_feeds.title AS feed_title,";
			} else if ($feed == -3) { // fresh virtual feed
				$query_strategy_part = "unread = true";

				$intl = get_pref($link, "FRESH_ARTICLE_MAX_AGE", $owner_uid);

				if (DB_TYPE == "pgsql") {
					$query_strategy_part .= " AND updated > NOW() - INTERVAL '$intl hour' "; 
				} else {
					$query_strategy_part .= " AND updated > DATE_SUB(NOW(), INTERVAL $intl HOUR) ";
				}

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

			if (get_pref($link, 'REVERSE_HEADLINES', $owner_uid)) {
				$order_by = "updated";
			} else {	
				$order_by = "updated DESC";
			}

			$order_by = "score DESC, $order_by";

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
							WHERE id = '$feed' AND owner_uid = $owner_uid");
						$feed_title = db_fetch_result($result, 0, "title");
					} else {
						$feed_title = __("Uncategorized");
					}

					if ($search) {
						$feed_title = __("Searched for")." $search ($feed_title)";
					}

				} else {
					
					$result = db_query($link, "SELECT title,site_url,last_error FROM ttrss_feeds 
						WHERE id = '$feed' AND owner_uid = $owner_uid");
		
					$feed_title = db_fetch_result($result, 0, "title");
					$feed_site_url = db_fetch_result($result, 0, "site_url");
					$last_error = db_fetch_result($result, 0, "last_error");

					if ($search) {
						$feed_title = __("Searched for") . " $search ($feed_title)";
					}
				}
	
			} else if ($feed == -1) {
				$feed_title = __("Starred articles");
				if ($search) {	$feed_title = __("Searched for") . " $search ($feed_title)"; }
			} else if ($feed == -2) {
				$feed_title = __("Published articles");
				if ($search) {	$feed_title = __("Searched for") . " $search ($feed_title)"; }
			} else if ($feed == -3) {
				$feed_title = __("Fresh articles");
				if ($search) {	$feed_title = __("Searched for") . " $search ($feed_title)"; }
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

			$content_query_part = "content as content_preview,";

			if (preg_match("/^-?[0-9][0-9]*$/", $feed) != false) {
	
				if ($feed >= 0) {
					$feed_kind = "Feeds";
				} else {
					$feed_kind = "Labels";
				}
	
				if ($limit_query_part) {
					$offset_query_part = "OFFSET $offset";
				}

				if ($vfeed_query_part && get_pref($link, 'VFEED_GROUP_BY_FEED', $owner_uid)) {
					if (!$override_order) {
						$order_by = "ttrss_feeds.title, $order_by";	
					}

					// Special output for Fresh feed

/*					if ($feed == -3) {
						$group_limit_part = "(select count(*) from 
							ttrss_user_entries AS t1, ttrss_entries AS t2 where
								t1.ref_id = t2.id and t1.owner_uid = 2 and
								t1.feed_id = ttrss_user_entries.feed_id and
								t2.updated > ttrss_entries.updated) <= 5 AND";
} */
				}

				$query = "SELECT 
						guid,
						ttrss_entries.id,ttrss_entries.title,
						updated,
						unread,feed_id,marked,published,link,last_read,
						".SUBSTRING_FOR_DATE."(last_read,1,19) as last_read_noms,
						$vfeed_query_part
						$content_query_part
						".SUBSTRING_FOR_DATE."(updated,1,19) as updated_noms,
						author,score
					FROM
						ttrss_entries,ttrss_user_entries,ttrss_feeds
					WHERE
					$group_limit_part
					ttrss_feeds.hidden = false AND 
					ttrss_user_entries.feed_id = ttrss_feeds.id AND
					ttrss_user_entries.ref_id = ttrss_entries.id AND
					ttrss_user_entries.owner_uid = '$owner_uid' AND
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
					".SUBSTRING_FOR_DATE."(last_read,1,19) as last_read_noms,
					$vfeed_query_part
					$content_query_part
					".SUBSTRING_FOR_DATE."(updated,1,19) as updated_noms,
					score
					FROM
						ttrss_entries,ttrss_user_entries,ttrss_tags
					WHERE
						ref_id = ttrss_entries.id AND 
						ttrss_user_entries.owner_uid = '$owner_uid' AND
						post_int_id = int_id AND tag_name = '$feed' AND
						$view_query_part
						$search_query_part
						$query_strategy_part ORDER BY $order_by
					$limit_query_part");	
			}

			return array($result, $feed_title, $feed_site_url, $last_error);
			
	}

	function generate_syndicated_feed($link, $owner_uid, $feed, $is_cat,
		$search, $search_mode, $match_on) {

		$qfh_ret = queryFeedHeadlines($link, $feed, 
			30, false, $is_cat, $search, $search_mode, $match_on, "updated DESC", 0,
			$owner_uid);

		$result = $qfh_ret[0];
		$feed_title = htmlspecialchars($qfh_ret[1]);
		$feed_site_url = $qfh_ret[2];
		$last_error = $qfh_ret[3];

//		if (!$feed_site_url) $feed_site_url = "http://localhost/";

		print "<?xml version=\"1.0\" encoding=\"utf-8\"?>
			<?xml-stylesheet type=\"text/xsl\" href=\"rss.xsl\"?>
			<rss version=\"2.0\">
 			<channel>
 			<title>$feed_title</title>
			<link>$feed_site_url</link>
			<description>Feed generated by Tiny Tiny RSS</description>";
 
 		while ($line = db_fetch_assoc($result)) {
 			print "<item>";
 			print "<guid>" . htmlspecialchars($line["guid"]) . "</guid>";
 			print "<link>" . htmlspecialchars($line["link"]) . "</link>";

			$tags = get_article_tags($link, $line["id"], $owner_uid);

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

		if ($cat_id == -1) {
			return __("Special");
		} else if ($cat_id == -2) {
			return __("Labels");
		} else {

			$result = db_query($link, "SELECT title FROM ttrss_feed_categories WHERE
				id = '$cat_id'");

			if (db_num_rows($result) == 1) {
				return db_fetch_result($result, 0, "title");
			} else {
				return "Uncategorized";
			}
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

			$res = strip_tags_long($res, 
				"<p><a><i><em><b><strong><blockquote><br><img><div><span><ul><ol><li>");

//			$res = preg_replace("/\r\n|\n|\r/", "", $res);
//			$res = strip_tags_long($res, "<p><a><i><em><b><strong><blockquote><br><img><div><span>");			
		}

		return $res;
	}

	/**
	 * Send by mail a digest of last articles.
	 * 
	 * @param mixed $link The database connection.
	 * @param integer $limit The maximum number of articles by digest.
	 * @return boolean Return false if digests are not enabled.
	 */
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

				$do_catchup = get_pref($link, 'DIGEST_CATCHUP', $line['id'], false);

				$tuple = prepare_headlines_digest($link, $line["id"], $days, $limit);
				$digest = $tuple[0];
				$headlines_count = $tuple[1];
				$affected_ids = $tuple[2];
				$digest_text = $tuple[3];

				if ($headlines_count > 0) {

					$mail = new PHPMailer();

					$mail->PluginDir = "phpmailer/";
					$mail->SetLanguage("en", "phpmailer/language/");

					$mail->CharSet = "UTF-8";

					$mail->From = DIGEST_FROM_ADDRESS;
					$mail->FromName = DIGEST_FROM_NAME;
					$mail->AddAddress($line["email"], $line["login"]);

					if (DIGEST_SMTP_HOST) {
						$mail->Host = DIGEST_SMTP_HOST;
						$mail->Mailer = "smtp";
						$mail->Username = DIGEST_SMTP_LOGIN;
						$mail->Password = DIGEST_SMTP_PASSWORD;
					}

					$mail->IsHTML(true);
					$mail->Subject = DIGEST_SUBJECT;
					$mail->Body = $digest;
					$mail->AltBody = $digest_text;

					$rc = $mail->Send();

					if (!$rc) print "ERROR: " . $mail->ErrorInfo;

					print "RC=$rc\n";

					if ($rc && $do_catchup) {
						print "Marking affected articles as read...\n";
						catchupArticlesById($link, $affected_ids, 0, $line["id"]);
					}

					db_query($link, "UPDATE ttrss_users SET last_digest_sent = NOW() 
							WHERE id = " . $line["id"]);
				} else {
					print "No headlines\n";
				}
			}
		}

		print "All done.\n";

	}

	function prepare_headlines_digest($link, $user_id, $days = 1, $limit = 100) {

		require_once "MiniTemplator.class.php";

		$tpl = new MiniTemplator;
		$tpl_t = new MiniTemplator;

		$tpl->readTemplateFromFile("templates/digest_template_html.txt");
		$tpl_t->readTemplateFromFile("templates/digest_template.txt");

		$tpl->setVariable('CUR_DATE', date('Y/m/d'));
		$tpl->setVariable('CUR_TIME', date('G:i'));

		$tpl_t->setVariable('CUR_DATE', date('Y/m/d'));
		$tpl_t->setVariable('CUR_TIME', date('G:i'));

		$affected_ids = array();

		if (DB_TYPE == "pgsql") {
			$interval_query = "ttrss_entries.date_entered > NOW() - INTERVAL '$days days'";
		} else if (DB_TYPE == "mysql") {
			$interval_query = "ttrss_entries.date_entered > DATE_SUB(NOW(), INTERVAL $days DAY)";
		}

		$result = db_query($link, "SELECT ttrss_entries.title,
				ttrss_feeds.title AS feed_title,
				date_entered,
				ttrss_user_entries.ref_id,
				link,
				SUBSTRING(content, 1, 120) AS excerpt,
				".SUBSTRING_FOR_DATE."(last_updated,1,19) AS last_updated
			FROM 
				ttrss_user_entries,ttrss_entries,ttrss_feeds 
			WHERE 
				ref_id = ttrss_entries.id AND feed_id = ttrss_feeds.id 
				AND include_in_digest = true
				AND $interval_query
				AND hidden = false
				AND ttrss_user_entries.owner_uid = $user_id
				AND unread = true 
			ORDER BY ttrss_feeds.title, date_entered DESC
			LIMIT $limit");

		$cur_feed_title = "";

		$headlines_count = db_num_rows($result);

		$headlines = array();

		while ($line = db_fetch_assoc($result)) {
			array_push($headlines, $line);
		}

		for ($i = 0; $i < sizeof($headlines); $i++) {	

			$line = $headlines[$i];

			array_push($affected_ids, $line["ref_id"]);

			$updated = smart_date_time(strtotime($line["last_updated"]));

			$tpl->setVariable('FEED_TITLE', $line["feed_title"]);
			$tpl->setVariable('ARTICLE_TITLE', $line["title"]);
			$tpl->setVariable('ARTICLE_LINK', $line["link"]);
			$tpl->setVariable('ARTICLE_UPDATED', $updated);
			$tpl->setVariable('ARTICLE_EXCERPT', 
				truncate_string(strip_tags($line["excerpt"]), 100));

			$tpl->addBlock('article');

			$tpl_t->setVariable('FEED_TITLE', $line["feed_title"]);
			$tpl_t->setVariable('ARTICLE_TITLE', $line["title"]);
			$tpl_t->setVariable('ARTICLE_LINK', $line["link"]);
			$tpl_t->setVariable('ARTICLE_UPDATED', $updated);
//			$tpl_t->setVariable('ARTICLE_EXCERPT', 
//				truncate_string(strip_tags($line["excerpt"]), 100));

			$tpl_t->addBlock('article');

			if ($headlines[$i]['feed_title'] != $headlines[$i+1]['feed_title']) {
				$tpl->addBlock('feed');
				$tpl_t->addBlock('feed');
			}

		}

		$tpl->addBlock('digest');
		$tpl->generateOutputToString($tmp);

		$tpl_t->addBlock('digest');
		$tpl_t->generateOutputToString($tmp_t);

		return array($tmp, $headlines_count, $affected_ids, $tmp_t);
	}

	function check_for_update($link, $brief_fmt = true) {
		$releases_feed = "http://tt-rss.spb.ru/releases.rss";

		if (!CHECK_FOR_NEW_VERSION || $_SESSION["access_level"] < 10) {
			return;
		}

		error_reporting(0);
		if (ENABLE_SIMPLEPIE) {
			$rss = new SimplePie();
			$rss->set_useragent(SIMPLEPIE_USERAGENT . MAGPIE_USER_AGENT_EXT);
//			$rss->set_timeout(MAGPIE_FETCH_TIME_OUT);
			$rss->set_feed_url($fetch_url);
			$rss->set_output_encoding('UTF-8');
			$rss->init();
		} else {
			$rss = fetch_rss($releases_feed);
		}
		error_reporting (DEFAULT_ERROR_LEVEL);

		if ($rss) {

			if (ENABLE_SIMPLEPIE) {
				$items = $rss->get_items();
			} else {
				$items = $rss->items;

				if (!$items || !is_array($items)) $items = $rss->entries;
				if (!$items || !is_array($items)) $items = $rss;
			}

			if (!is_array($items) || count($items) == 0) {
				return;
			}			

			$latest_item = $items[0];

			if (ENABLE_SIMPLEPIE) {
				$last_title = $latest_item->get_title();
			} else {
				$last_title = $latest_item["title"];
			}

			$latest_version = trim(preg_replace("/(Milestone)|(completed)/", "", $last_title));

			if (ENABLE_SIMPLEPIE) {
				$release_url = sanitize_rss($link, $latest_item->get_link());
				$content = sanitize_rss($link, $latest_item->get_description());
			} else {
				$release_url = sanitize_rss($link, $latest_item["link"]);
				$content = sanitize_rss($link, $latest_item["description"]);
			}

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

	function publishArticlesById($link, $ids, $cmode) {

		$tmp_ids = array();

		foreach ($ids as $id) {
			array_push($tmp_ids, "ref_id = '$id'");
		}

		$ids_qpart = join(" OR ", $tmp_ids);

		if ($cmode == 0) {
			db_query($link, "UPDATE ttrss_user_entries SET 
			published = false,last_read = NOW()
			WHERE ($ids_qpart) AND owner_uid = " . $_SESSION["uid"]);
		} else if ($cmode == 1) {
			db_query($link, "UPDATE ttrss_user_entries SET 
			published = true
			WHERE ($ids_qpart) AND owner_uid = " . $_SESSION["uid"]);
		} else {
			db_query($link, "UPDATE ttrss_user_entries SET 
			published = NOT published,last_read = NOW()
			WHERE ($ids_qpart) AND owner_uid = " . $_SESSION["uid"]);
		}
	}

	function catchupArticlesById($link, $ids, $cmode, $owner_uid = false) {

		if (!$owner_uid) $owner_uid = $_SESSION["uid"];

		$tmp_ids = array();

		foreach ($ids as $id) {
			array_push($tmp_ids, "ref_id = '$id'");
		}

		$ids_qpart = join(" OR ", $tmp_ids);

		if ($cmode == 0) {
			db_query($link, "UPDATE ttrss_user_entries SET 
			unread = false,last_read = NOW()
			WHERE ($ids_qpart) AND owner_uid = $owner_uid");
		} else if ($cmode == 1) {
			db_query($link, "UPDATE ttrss_user_entries SET 
			unread = true
			WHERE ($ids_qpart) AND owner_uid = $owner_uid");
		} else {
			db_query($link, "UPDATE ttrss_user_entries SET 
			unread = NOT unread,last_read = NOW()
			WHERE ($ids_qpart) AND owner_uid = $owner_uid");
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
			$search_mode = false, $offset = 0, $limit = 0, 
			$dashboard_menu = 0, $disable_feed = 0, $feed_small_icon = 0) {

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
			$catchup_sel_link = "javascript:catchupSelection()";

			if (!get_pref($link, 'COMBINED_DISPLAY_MODE')) {

				$sel_all_link = "javascript:selectTableRowsByIdPrefix('headlinesList', 'RROW-', 'RCHK-', true, '', true)";
				$sel_unread_link = "javascript:selectTableRowsByIdPrefix('headlinesList', 'RROW-', 'RCHK-', true, 'Unread', true)";
				$sel_none_link = "javascript:selectTableRowsByIdPrefix('headlinesList', 'RROW-', 'RCHK-', false)";

				$tog_unread_link = "javascript:selectionToggleUnread()";
				$tog_marked_link = "javascript:selectionToggleMarked()";
				$tog_published_link = "javascript:selectionTogglePublished()";

			} else {

				$sel_all_link = "javascript:cdmSelectArticles('all')";
				$sel_unread_link = "javascript:cdmSelectArticles('unread')";
				$sel_none_link = "javascript:cdmSelectArticles('none')";

				$tog_unread_link = "javascript:selectionToggleUnread(true)";
				$tog_marked_link = "javascript:selectionToggleMarked(true)";
				$tog_published_link = "javascript:selectionTogglePublished(true)";

			}

			if (!$dashboard_menu) {

				if (strpos($_SESSION["client.userAgent"], "MSIE") === false) {

					print "<td class=\"headlineActions$rtl_cpart\">
						<ul class=\"headlineDropdownMenu\">
						<li class=\"top2\">
						".__('Select:')."
							<a href=\"$sel_all_link\">".__('All')."</a>,
							<a href=\"$sel_unread_link\">".__('Unread')."</a>,
							<a href=\"$sel_none_link\">".__('None')."</a></li>
						<li class=\"vsep\">&nbsp;</li>
						<li class=\"top\">".__('Actions...')."<ul>
							<li><span class=\"insensitive\">".__('Selection toggle:')."</span></li>
							<li onclick=\"$tog_unread_link\">&nbsp;&nbsp;".__('Unread')."</li>
							<li onclick=\"$tog_marked_link\">&nbsp;&nbsp;".__('Starred')."</li>
							<li onclick=\"$tog_published_link\">&nbsp;&nbsp;".__('Published')."</li>
							<li><span class=\"insensitive\">--------</span></li>
							<li><span class=\"insensitive\">".__('Mark as read:')."</span></li>
							<li onclick=\"$catchup_sel_link\">&nbsp;&nbsp;".__('Selection')."</li>";

/*				if (!get_pref($link, 'COMBINED_DISPLAY_MODE')) {
	
					print "
						<li onclick=\"catchupRelativeToArticle(0)\">&nbsp;&nbsp;".__("Above active article")."</li>
						<li onclick=\"catchupRelativeToArticle(1)\">&nbsp;&nbsp;".__("Below active article")."</li>";
				} else {
					print "
						<li><span class=\"insensitive\">&nbsp;&nbsp;".__("Above active article")."</span></li>
						<li><span class=\"insensitive\">&nbsp;&nbsp;".__("Below active article")."</span></li>";

				} */

				print "<li onclick=\"$catchup_feed_link\">&nbsp;&nbsp;".__('Entire feed')."</li>";

				print "<li><span class=\"insensitive\">--------</span></li>";
				print "<li><span class=\"insensitive\">".__('Other actions:')."</span></li>";
		

				if ($search && $feed_id >= 0 && get_pref($link, 'ENABLE_LABELS') && GLOBAL_ENABLE_LABELS) {
					print "
						<li onclick=\"javascript:labelFromSearch('$search', '$search_mode',
							'$match_on', '$feed_id', '$is_cat');\">&nbsp;&nbsp;
							".__('Search to label')."</li>";
				} else {
					print "<li><span class=\"insensitive\">&nbsp;&nbsp;".__('Search to label')."</li>";

				}
				
				print	"</ul></li></ul>";
				print "</td>"; 
	
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
			} else { // dashboard menu actions

				// not implemented
				print "</td>";
			}

			print "<td class=\"headlineTitle$rtl_cpart\">";

			print "<span id=\"subtoolbar_search\" 
				style=\"display : none\"><input 
				id=\"subtoolbar_search_box\"
				onblur=\"javascript:enableHotkeys();\" 
				onfocus=\"javascript:disableHotkeys();\"
				onchange=\"subtoolbarSearch()\"
				onkeyup=\"subtoolbarSearch()\" type=\"search\"></span>";

			print "<span id=\"subtoolbar_ftitle\">";

			if ($feed_site_url) {
				if (!$bottom) {
					$target = "target=\"_new\"";
				}
				print "<a $target href=\"$feed_site_url\">".
					truncate_string($feed_title,30)."</a>";
			} else {
				print $feed_title;
			}

			if ($search) {
				$search_q = "&q=$search&m=$match_on&smode=$search_mode";
			}

			if ($user_page_offset > 1) {
				print " [$user_page_offset] ";
			}

			if (!$bottom && !$disable_feed) {
				print "
					<a target=\"_new\" 
						href=\"backend.php?op=rss&id=$feed_id&is_cat=$is_cat$search_q\">
						<img class=\"noborder\" 
							alt=\"".__('Generated feed')."\" src=\"images/feed-icon-12x12.png\">
					</a>";
			} else if ($feed_small_icon) {
				print "<img class=\"noborder\" alt=\"\" src=\"images/$feed_small_icon\">";
			}

			print "</span>";

			print "</td>";
			print "</tr></table>";

		}

	function printCategoryHeader($link, $cat_id, $hidden = false, $can_browse = true) {

			$tmp_category = getCategoryTitle($link, $cat_id);
			$cat_unread = getCategoryUnread($link, $cat_id);

			if ($hidden) {
				$holder_style = "display:none;";
				$ellipsis = "…";
			} else {
				$holder_style = "";
				$ellipsis = "";
			}

			$catctr_class = ($cat_unread > 0) ? "catCtrHasUnread" : "catCtrNoUnread";

			print "<li class=\"feedCat\" id=\"FCAT-$cat_id\">
				<a id=\"FCATN-$cat_id\" href=\"javascript:toggleCollapseCat($cat_id)\">$tmp_category</a>";

			if ($can_browse) {
				print "<a href=\"#\" onclick=\"javascript:viewCategory($cat_id)\" id=\"FCAP-$cat_id\">";
			} else {
				print "<span id=\"FCAP-$cat_id\">";
			}

			print " <span id=\"FCATCTR-$cat_id\" 
				class=\"$catctr_class\">($cat_unread)</span> $ellipsis";

			if ($can_browse) {
				print "</a>";
			} else {
				print "</span>";
			}

			print "</li>";

			print "<li id=\"feedCatHolder\" class=\"$holder_class\"><ul class=\"feedCatList\" id=\"FCATLIST-$cat_id\" style='$holder_style'>";
	}
	
	function outputFeedList($link, $tags = false) {

		print "<ul class=\"feedList\" id=\"feedList\">";

		$owner_uid = $_SESSION["uid"];

		/* virtual feeds */

		if (get_pref($link, 'ENABLE_FEED_CATS')) {

			if ($_COOKIE["ttrss_vf_vclps"] == 1) {
				$cat_hidden = true;
			} else {
				$cat_hidden = false;
			}

#			print "<li class=\"feedCat\">".__('Special')."</li>";
#			print "<li id=\"feedCatHolder\" class=\"feedCatHolder\"><ul class=\"feedCatList\">";		
#			print "<li class=\"feedCat\">".
#				"<a id=\"FCATN--1\" href=\"javascript:toggleCollapseCat(-1)\">".
#				__('Special')."</a> <span id='FCAP--1'>$ellipsis</span></li>";
#
#			print "<li id=\"feedCatHolder\" class=\"feedCatHolder\">
#				<ul class=\"feedCatList\" id='FCATLIST--1' style='$holder_style'>";

#			$cat_unread = getCategoryUnread($link, -1);
#			$tmp_category = __("Special");
#			$catctr_class = ($cat_unread > 0) ? "catCtrHasUnread" : "catCtrNoUnread";

			printCategoryHeader($link, -1, $cat_hidden, false);
		}

		if (defined('_ENABLE_DASHBOARD')) {
			printFeedEntry(-4, "virt", __("Dashboard"), 0, 
				"images/tag.png", $link);
		}

		$num_starred = getFeedUnread($link, -1);
		$num_published = getFeedUnread($link, -2);
		$num_fresh = getFeedUnread($link, -3);

		$class = "virt";

		if ($num_fresh > 0) $class .= "Unread";

		printFeedEntry(-3, $class, __("Fresh articles"), $num_fresh, 
			"images/fresh.png", $link);

		$class = "virt";

		if ($num_starred > 0) $class .= "Unread";

		$is_ie = (strpos($_SESSION["client.userAgent"], "MSIE") !== false);

		if ($is_ie) {
			$mark_img_ext = "gif";
		} else {
			$mark_img_ext = "png";
		}

		printFeedEntry(-1, $class, __("Starred articles"), $num_starred, 
			"images/mark_set.$mark_img_ext", $link);

		$class = "virt";

		if ($num_published > 0) $class .= "Unread";

		printFeedEntry(-2, $class, __("Published articles"), $num_published, 
			"images/pub_set.gif", $link);

		if (get_pref($link, 'ENABLE_FEED_CATS')) {
			print "</ul>";
		}

		if (!$tags) {

			if (GLOBAL_ENABLE_LABELS && get_pref($link, 'ENABLE_LABELS')) {
	
				$result = db_query($link, "SELECT id,sql_exp,description FROM
					ttrss_labels WHERE owner_uid = '$owner_uid' ORDER by description");
		
				if (db_num_rows($result) > 0) {
					if (get_pref($link, 'ENABLE_FEED_CATS')) {

						if ($_COOKIE["ttrss_vf_lclps"] == 1) {
							$cat_hidden = true;
						} else {
							$cat_hidden = false;
						}

						printCategoryHeader($link, -2, $cat_hidden, false);

#						print "<li class=\"feedCat\">".
#							"<a id=\"FCATN--2\" href=\"javascript:toggleCollapseCat(-2)\">".
#							__('Labels')."</a> <span id='FCAP--2'>$ellipsis</span></li>";
#
#						print "<li id=\"feedCatHolder\" class=\"feedCatHolder\"><ul class=\"feedCatList\" id='FCATLIST--2' style='$holder_style'>";
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

			$age_qpart = getMaxAgeSubquery();

			$query = "SELECT ttrss_feeds.*,
				".SUBSTRING_FOR_DATE."(last_updated,1,19) AS last_updated_noms,
				(SELECT COUNT(id) FROM ttrss_entries,ttrss_user_entries
					WHERE feed_id = ttrss_feeds.id AND unread = true
						AND $age_qpart
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
				ORDER BY $order_by_qpart"; 

			$result = db_query($link, $query);

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
						$holder_class = "feedCatHolder";
						$holder_style = "display:none;";
						$ellipsis = "…";
					} else {
						$holder_class = "feedCatHolder";
						$holder_style = "";
						$ellipsis = "";
					}

					$cat_id = sprintf("%d", $cat_id);

					$cat_unread = getCategoryUnread($link, $cat_id);

					$catctr_class = ($cat_unread > 0) ? "catCtrHasUnread" : "catCtrNoUnread";

					print "<li class=\"feedCat\" id=\"FCAT-$cat_id\">
						<a id=\"FCATN-$cat_id\" href=\"javascript:toggleCollapseCat($cat_id)\">$tmp_category</a>
							<a href=\"#\" onclick=\"javascript:viewCategory($cat_id)\" id=\"FCAP-$cat_id\">
							<span id=\"FCATCTR-$cat_id\" 
							class=\"$catctr_class\">($cat_unread)</span> $ellipsis
							</a></li>";

					print "<li id=\"feedCatHolder\" class=\"$holder_class\"><ul class=\"feedCatList\" id=\"FCATLIST-$cat_id\" style='$holder_style'>";
				}
	
				printFeedEntry($feed_id, $class, $feed, $unread, 
					ICONS_URL."/$feed_id.ico", $link, $rtl_content, 
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

			$age_qpart = getMaxAgeSubquery();

			$result = db_query($link, "SELECT tag_name,SUM((SELECT COUNT(int_id) 
				FROM ttrss_user_entries,ttrss_entries WHERE int_id = post_int_id 
					AND ref_id = id AND $age_qpart
					AND unread = true)) AS count FROM ttrss_tags 
					WHERE owner_uid = ".$_SESSION['uid']." GROUP BY tag_name 
					ORDER BY count DESC LIMIT 50");

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

	function get_article_tags($link, $id, $owner_uid = 0) {

		$a_id = db_escape_string($id);

		if (!$owner_uid) $owner_uid = $_SESSION["uid"];

		$tmp_result = db_query($link, "SELECT DISTINCT tag_name, 
			owner_uid as owner FROM
			ttrss_tags WHERE post_int_id = (SELECT int_id FROM ttrss_user_entries WHERE
				ref_id = '$a_id' AND owner_uid = '$owner_uid' LIMIT 1) ORDER BY tag_name");

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
			<img src=\"images/sign_excl.gif\">$msg</div>";
	}

	function format_notice($msg) {
		return "<div class=\"notice\"> 
			<img src=\"images/sign_info.gif\">$msg</div>";
	}

	function format_error($msg) {
		return "<div class=\"error\"> 
			<img src=\"images/sign_excl.gif\">$msg</div>";
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
			".SUBSTRING_FOR_DATE."(updated,1,16) as updated,
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
				$entry_author = __(" - ") . $entry_author;
			}

			$parsed_updated = date(get_pref($link, 'LONG_DATE_FORMAT'), 
				strtotime($line["updated"]));
		
			print "<div class=\"postDate$rtl_class\">$parsed_updated</div>";

			if ($line["link"]) {
				print "<div clear='both'><a $link_target href=\"" . $line["link"] . "\">" . 
					$line["title"] . "</a><span class='author'>$entry_author</span></div>";
			} else {
				print "<div clear='both'>" . $line["title"] . "$entry_author</div>";
			}

/*			$tmp_result = db_query($link, "SELECT DISTINCT tag_name FROM
				ttrss_tags WHERE post_int_id = " . $line["int_id"] . "
				ORDER BY tag_name"); */

			$tags = get_article_tags($link, $id);
	
			$tags_str = "";
			$f_tags_str = "";

			$num_tags = 0;

			if ($_SESSION["theme"] == "3pane") {
				$tag_limit = 3;
			} else {
				$tag_limit = 6;
			}

			foreach ($tags as $tag) {
				$num_tags++;
				$tag_escaped = str_replace("'", "\\'", $tag);

				$tag_str = "<a href=\"javascript:viewfeed('$tag_escaped')\">$tag</a>, ";
				
				if ($num_tags == $tag_limit) {
					$tags_str .= "&hellip;";

				} else if ($num_tags < $tag_limit) {
					$tags_str .= $tag_str;
				}
				$f_tags_str .= $tag_str;
			}

			$tags_str = preg_replace("/, $/", "", $tags_str);
			$f_tags_str = preg_replace("/, $/", "", $f_tags_str);

			$all_tags_div = "<span class='cdmAllTagsCtr'>&hellip;<div class='cdmAllTags'>All Tags: $f_tags_str</div></span>";
			$tags_str = preg_replace("/\.\.\.$/", "$all_tags_div", $tags_str);

			if (!$entry_comments) $entry_comments = "&nbsp;"; # placeholder

			if (!$tags_str) $tags_str = '<span class="tagList">'.__('no tags').'</span>';

			print "<div style='float : right'>
				<img src='images/tag.png' class='tagsPic' alt='Tags' title='Tags'>
				$tags_str 
				<a title=\"Edit tags for this article\" 
					href=\"javascript:editArticleTags($id, $feed_id)\">(+)</a></div>
				<div clear='both'>$entry_comments</div>";

			print "</div>";

			print "<div class=\"postIcon\">" . $feed_icon . "</div>";
			print "<div class=\"postContent\">";
			
			#print "<div id=\"allEntryTags\">".__('Tags:')." $f_tags_str</div>";

			$line["content"] = sanitize_rss($link, $line["content"]);

			if (get_pref($link, 'OPEN_LINKS_IN_NEW_WINDOW')) {
				$line["content"] = preg_replace("/href=/i", "target=\"_new\" href=", $line["content"]);
			}

			print $line["content"];

			$result = db_query($link, "SELECT * FROM ttrss_enclosures WHERE
				post_id = '$id' AND content_url != ''");

			if (db_num_rows($result) > 0) {
				print "<div class=\"postEnclosures\">";

				if (db_num_rows($result) == 1) {
					print __("Attachment:") . " ";
				} else {
					print __("Attachments:") . " ";
				}

				$entries = array();

				while ($line = db_fetch_assoc($result)) {

					$url = $line["content_url"];
					$ctype = $line["content_type"];

					if (!$ctype) $ctype = __("unknown type");

					$filename = substr($url, strrpos($url, "/")+1);

					$entry = "<a target=\"_blank\" href=\"" . htmlspecialchars($url) . "\">" .
						$filename . " (" . $ctype . ")" . "</a>";

					array_push($entries, $entry);
				}

				print join(", ", $entries);

				print "</div>";
			}
		
			print "</div>";
			
			print "</div>";

		}

		print "]]></article>";

	}

	function outputHeadlinesList($link, $feed, $subop, $view_mode, $limit, $cat_view,
					$next_unread_feed, $offset, $vgr_last_feed = false) {

		$disable_cache = false;

		$timing_info = getmicrotime();

		$topmost_article_ids = array();

		if (!$offset) {
			$offset = 0;
		}

		if ($subop == "undefined") $subop = "";

		$subop_split = split(":", $subop);

		if ($subop == "CatchupSelected") {
			$ids = split(",", db_escape_string($_GET["ids"]));
			$cmode = sprintf("%d", $_GET["cmode"]);

			catchupArticlesById($link, $ids, $cmode);
		}

		if ($subop == "ForceUpdate" && sprintf("%d", $feed) > 0) {
			update_generic_feed($link, $feed, $cat_view, true);
		}

		if ($subop == "MarkAllRead")  {
			catchup_feed($link, $feed, $cat_view);

			if (get_pref($link, 'ON_CATCHUP_SHOW_NEXT_FEED')) {
				if ($next_unread_feed) {
					$feed = $next_unread_feed;
				}
			}
		}

		if ($subop_split[0] == "MarkAllReadGR")  {
			catchup_feed($link, $subop_split[1], false);
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

		if ($search) { 
			$disable_cache = true;
		}

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

		$vgroup_last_feed = $vgr_last_feed;

		if ($feed == -2) {
			$feed_site_url = article_publish_url($link);
		}

		/// STOP //////////////////////////////////////////////////////////////////////////////////

		if (!$offset) {
			print "<div id=\"headlinesContainer\" $rtl_tag>";

			if (!$result) {
				print "<div align='center'>".__("Could not display feed (query failed). Please check label match syntax or local configuration.")."</div>";
				return;
			}

			print_headline_subtoolbar($link, $feed_site_url, $feed_title, false, 
				$rtl_content, $feed, $cat_view, $search, $match_on, $search_mode, 
				$offset, $limit);

			print "<div id=\"headlinesInnerContainer\" onscroll=\"headlines_scroll_handler()\">";
		}

		$headlines_count = db_num_rows($result);

		if (db_num_rows($result) > 0) {

#			print "\{$offset}";

			if (!get_pref($link, 'COMBINED_DISPLAY_MODE') && !$offset) {
				print "<table class=\"headlinesList\" id=\"headlinesList\" 
					cellspacing=\"0\">";
			}

			$lnum = $limit*$offset;

			error_reporting (DEFAULT_ERROR_LEVEL);
	
			$num_unread = 0;
			$cur_feed_title = '';

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

				$is_ie = (strpos($_SESSION["client.userAgent"], "MSIE") !== false);

				if ($is_ie) {
					$mark_img_ext = "gif";
				} else {
					$mark_img_ext = "png";
				}

				if ($line["marked"] == "t" || $line["marked"] == "1") {
					$marked_pic = "<img id=\"FMPIC-$id\" src=\"images/mark_set.$mark_img_ext\" 
						class=\"markedPic\"
						alt=\"Unstar article\" onclick='javascript:tMark($id)'>";
				} else {
					$marked_pic = "<img id=\"FMPIC-$id\" src=\"images/mark_unset.$mark_img_ext\" 
						class=\"markedPic\"
						alt=\"Star article\" onclick='javascript:tMark($id)'>";
				}

				if ($line["published"] == "t" || $line["published"] == "1") {
					$published_pic = "<img id=\"FPPIC-$id\" src=\"images/pub_set.gif\" 
						class=\"markedPic\"
						alt=\"Unpublish article\" onclick='javascript:tPub($id)'>";
				} else {
					$published_pic = "<img id=\"FPPIC-$id\" src=\"images/pub_unset.gif\" 
						class=\"markedPic\"
						alt=\"Publish article\" onclick='javascript:tPub($id)'>";
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

				$score = $line["score"];

				$score_pic = get_score_pic($score);

				$score_title = __("(Click to change)");

				$score_pic = "<img class='hlScorePic' src=\"images/$score_pic\" 
					onclick=\"adjustArticleScore($id, $score)\" title=\"$score $score_title\">";

				if ($score > 500) {
					$hlc_suffix = "H";
				} else if ($score < -100) {
					$hlc_suffix = "L";
				} else {
					$hlc_suffix = "";
				}

				$entry_author = $line["author"];

				if ($entry_author) {
					$entry_author = " - $entry_author";
				}

				if (!get_pref($link, 'COMBINED_DISPLAY_MODE')) {

					if (get_pref($link, 'VFEED_GROUP_BY_FEED')) {
						if ($feed_id != $vgroup_last_feed && $line["feed_title"]) {

							$cur_feed_title = $line["feed_title"];
							$vgroup_last_feed = $feed_id;

							$cur_feed_title = htmlspecialchars($cur_feed_title);

							$vf_catchup_link = "(<a onclick='javascript:catchupFeedInGroup($feed_id, \"$cur_feed_title\");' href='#'>mark as read</a>)";

							print "<tr class='feedTitle'><td colspan='7'>".
								"<a href=\"javascript:viewfeed($feed_id, '', false)\">".
								$line["feed_title"]."</a> $vf_catchup_link:</td></tr>";
						}
					}

					$mouseover_attrs = "onmouseover='postMouseIn($id)' 
						onmouseout='postMouseOut($id)'";

					print "<tr class='$class' id='RROW-$id' $mouseover_attrs>";
		
					print "<td class='hlUpdPic'>$update_pic</td>";
		
					print "<td class='hlSelectRow'>
						<input type=\"checkbox\" onclick=\"tSR(this)\"
							id=\"RCHK-$id\">
						</td>";
		
					print "<td class='hlMarkedPic'>$marked_pic</td>";
					print "<td class='hlMarkedPic'>$published_pic</td>";

#					if ($line["feed_title"]) {			
#						print "<td class='hlContent'>$content_link</td>";
#						print "<td class='hlFeed'>
#							<a href=\"javascript:viewfeed($feed_id, '', false)\">".
#								truncate_string($line["feed_title"],30)."</a>&nbsp;</td>";
#					} else {			

					print "<td onclick='javascript:view($id,$feed_id)' class='hlContent$hlc_suffix' valign='middle'>";

					print "<a id=\"RTITLE-$id\" href=\"javascript:view($id,$feed_id);\">" .
						$line["title"];

					if (get_pref($link, 'SHOW_CONTENT_PREVIEW')) {
						if ($content_preview) {
							print "<span class=\"contentPreview\"> - $content_preview</span>";
						}
					}

					print "</a>";

#							<a href=\"javascript:viewfeed($feed_id, '', false)\">".
#							$line["feed_title"]."</a>	

					if (!get_pref($link, 'VFEED_GROUP_BY_FEED')) {
						if ($line["feed_title"]) {			
							print "<span class=\"hlFeed\">
								(<a href=\"javascript:viewfeed($feed_id, '', false)\">".
								$line["feed_title"]."</a>)
							</span>";
						}
					}


					print "</td>";
					
#					}
					
					print "<td class=\"hlUpdated\" onclick='javascript:view($id,$feed_id)'><nobr>$updated_fmt&nbsp;</nobr></td>";

					print "<td class='hlMarkedPic'>$score_pic</td>";
	
					print "</tr>";

				} else {

					if (get_pref($link, 'VFEED_GROUP_BY_FEED') && $line["feed_title"]) {
						if ($feed_id != $vgroup_last_feed) {

							$cur_feed_title = $line["feed_title"];
							$vgroup_last_feed = $feed_id;

							$cur_feed_title = htmlspecialchars($cur_feed_title);

							$vf_catchup_link = "(<a onclick='javascript:catchupFeedInGroup($feed_id, \"$cur_feed_title\");' href='#'>mark as read</a>)";

							print "<div class='cdmFeedTitle'>".
								"<a href=\"javascript:viewfeed($feed_id, '', false)\">".
								$line["feed_title"]."</a> $vf_catchup_link</div>";
						}
					}

					if ($is_unread) {
						$add_class = "Unread";
					} else {
						$add_class = "";
					}	

					$expand_cdm = get_pref($link, 'CDM_EXPANDED');

					if ($expand_cdm && $score >= -100) {
						$cdm_cstyle = "";
					} else {
						$cdm_cstyle = "style=\"display : none\"";
					}

					$mouseover_attrs = "onmouseover='postMouseIn($id)' 
						onmouseout='postMouseOut($id)'";

					print "<div class=\"cdmArticle$add_class\" 
						id=\"RROW-$id\"
						onclick='cdmClicked(this)'
						$mouseover_attrs'>";

					print "<div class=\"cdmHeader\">";

					print "<div class=\"articleUpdated\">$updated_fmt $score_pic</div>";

					print "<span id=\"RTITLE-$id\" class=\"titleWrap$hlc_suffix\"><a class=\"title\" 
						onclick=\"javascript:toggleUnread($id, 0)\"
						target=\"_blank\" href=\"".$line["link"]."\">".$line["title"]."</a>
						";

					print $entry_author;

					if (!$expand_cdm || $score < -100) {
						print "&nbsp;<a id=\"CICH-$id\" 
							href=\"javascript:cdmExpandArticle($id)\">
							(".__('Show article').")</a>";
					} 


					if (!get_pref($link, 'VFEED_GROUP_BY_FEED')) {
						if ($line["feed_title"]) {	
							print "&nbsp;(<a href='javascript:viewfeed($feed_id)'>".$line["feed_title"]."</a>)";
						}
					}

					print "</span></div>";

					if (get_pref($link, 'OPEN_LINKS_IN_NEW_WINDOW')) {
						$line["content_preview"] = preg_replace("/href=/i", 
							"target=\"_new\" href=", $line["content_preview"]);
					}

					print "<div class=\"cdmContent\" id=\"CICD-$id\" $cdm_cstyle>";

//					print "<div class=\"cdmInnerContent\" id=\"CICD-$id\" $cdm_cstyle>";

					print $line["content_preview"];

					$e_result = db_query($link, "SELECT * FROM ttrss_enclosures WHERE
						post_id = '$id' AND content_url != ''");

					if (db_num_rows($e_result) > 0) {
				print "<div class=\"cdmEnclosures\">";

				if (db_num_rows($e_result) == 1) {
					print __("Attachment:") . " ";
				} else {
					print __("Attachments:") . " ";
				}

				$entries = array();

				while ($e_line = db_fetch_assoc($e_result)) {

					$url = $e_line["content_url"];
					$ctype = $e_line["content_type"];
					if (!$ctype) $ctype = __("unknown type");

					$filename = substr($url, strrpos($url, "/")+1);

					$entry = "<a target=\"_blank\" href=\"" . htmlspecialchars($url) . "\">" .
						$filename . " (" . $ctype . ")" . "</a>";

					array_push($entries, $entry);
				}

				print join(", ", $entries);

				print "</div>";
			}

					print "<br clear='both'>";
//					print "</div>";

/*					if (!$expand_cdm) {
						print "<a id=\"CICH-$id\" 
							href=\"javascript:cdmExpandArticle($id)\">
							Show article</a>";
					} */

					print "</div>";

					print "<div class=\"cdmFooter\"><span class='s0'>";

					/* print "<div class=\"markedPic\">Star it: $marked_pic</div>"; */

					print __("Select:").
							" <input type=\"checkbox\" onclick=\"toggleSelectRowById(this, 
							'RROW-$id')\" class=\"feedCheckBox\" id=\"RCHK-$id\">";

					print "</span><span class='s1'>$marked_pic</span> ";
					print "<span class='s1'>$published_pic</span> ";

					$tags = get_article_tags($link, $id);

					$tags_str = "";
					$full_tags_str = "";
					$num_tags = 0;

					foreach ($tags as $tag) {
						$num_tags++;
						$full_tags_str .= "<a href=\"javascript:viewfeed('$tag')\">$tag</a>, "; 
						if ($num_tags < 5) {
							$tags_str .= "<a href=\"javascript:viewfeed('$tag')\">$tag</a>, "; 
						} else if ($num_tags == 5) {
							$tags_str .= "&hellip;";
						}
					}

					$tags_str = preg_replace("/, $/", "", $tags_str);
					$full_tags_str = preg_replace("/, $/", "", $full_tags_str);

					$all_tags_div = "<span class='cdmAllTagsCtr'>&hellip;<div class='cdmAllTags'>All Tags: $full_tags_str</div></span>";

					$tags_str = preg_replace("/\.\.\.$/", "$all_tags_div", $tags_str);


					if ($tags_str == "") $tags_str = "no tags";

//					print "<img src='images/tag.png' class='markedPic'>";

					print "<span class='s1'>
						<img class='tagsPic' src='images/tag.png' alt='Tags' 
							title='Tags'> $tags_str <a title=\"Edit tags for this article\" 
							href=\"javascript:editArticleTags($id, $feed_id, true)\">(+)</a>";

					print "</span>";

					print "<span class='s2'>Toggle: <a class=\"cdmToggleLink\"
							href=\"javascript:toggleUnread($id)\">
							Unread</a></span>";

					print "</div>";
					print "</div>";	

				}				
	
				++$lnum;
			}

			if (!get_pref($link, 'COMBINED_DISPLAY_MODE') && !$offset) {			
				print "</table>";
			}

//			print_headline_subtoolbar($link, 
//				"javascript:catchupPage()", "Mark page as read", true, $rtl_content);


		} else {
			$message = "";

			switch ($view_mode) {
				case "unread":
					$message = __("No unread articles found to display.");
					break;
				case "marked":
					$message = __("No starred articles found to display.");
					break;
				default:
					$message = __("No articles found to display.");
			}

			if (!$offset) print "<div class='whiteBox'>$message</div>";
		}

		if (!$offset) {
			print "</div>";
			print "</div>";
		}

		return array($topmost_article_ids, $headlines_count, $feed, $disable_cache, $vgroup_last_feed);
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

	function generate_publish_key() {
		return sha1(uniqid(rand(), true));
	}

	function article_publish_url($link) {

		$url_path = ($_SERVER['HTTPS'] != "on" ? 'http://' :  'https://') . $_SERVER["HTTP_HOST"] . parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

		$url_path .= "?op=publish&key=" . get_pref($link, "_PREFS_PUBLISH_KEY");

		return $url_path;
	}

	/**
	 * Purge a feed contents, marked articles excepted.
	 * 
	 * @param mixed $link The database connection.
	 * @param integer $id The id of the feed to purge.
	 * @return void
	 */
	function clear_feed_articles($link, $id) {
		$result = db_query($link, "DELETE FROM ttrss_user_entries
			WHERE feed_id = '$id' AND marked = false AND owner_uid = " . $_SESSION["uid"]);

		$result = db_query($link, "DELETE FROM ttrss_entries WHERE 
			(SELECT COUNT(int_id) FROM ttrss_user_entries WHERE ref_id = id) = 0");
	} // function clear_feed_articles

	/**
	 * Compute the Mozilla Firefox feed adding URL from server HOST and REQUEST_URI.
	 *
	 * @return string The Mozilla Firefox feed adding URL.
	 */
	function add_feed_url() {
		$url_path = ($_SERVER['HTTPS'] != "on" ? 'http://' :  'https://') . $_SERVER["HTTP_HOST"] . parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
		$url_path .= "?op=pref-feeds&quiet=1&subop=add&feed_url=%s";
		return $url_path;
	} // function add_feed_url

	/**
	 * Encrypt a password in SHA1.
	 * 
	 * @param string $pass The password to encrypt.
	 * @param string $login A optionnal login.
	 * @return string The encrypted password.
	 */
	function encrypt_password($pass, $login = '') {
		if ($login) {
			return "SHA1X:" . sha1("$login:$pass");
		} else {
			return "SHA1:" . sha1($pass);
		}
	} // function encrypt_password

	/**
	 * Update a feed batch.
	 * Used by daemons to update n feeds by run.
	 * Only update feed needing a update, and not being processed
	 * by another process.
	 * 
	 * @param mixed $link Database link
	 * @param integer $limit Maximum number of feeds in update batch. Default to DAEMON_FEED_LIMIT.
	 * @param boolean $from_http Set to true if you call this function from http to disable cli specific code.
	 * @param boolean $debug Set to false to disable debug output. Default to true.
	 * @return void
	 */
	function update_daemon_common($link, $limit = DAEMON_FEED_LIMIT, $from_http = false, $debug = true) {
		// Process all other feeds using last_updated and interval parameters

		// Test if the user has loggued in recently. If not, it does not update its feeds.
		if (DAEMON_UPDATE_LOGIN_LIMIT > 0) {
			if (DB_TYPE == "pgsql") {
				$login_thresh_qpart = "AND ttrss_users.last_login >= NOW() - INTERVAL '".DAEMON_UPDATE_LOGIN_LIMIT." days'";
			} else {
				$login_thresh_qpart = "AND ttrss_users.last_login >= DATE_SUB(NOW(), INTERVAL ".DAEMON_UPDATE_LOGIN_LIMIT." DAY)";
			}			
		} else {
			$login_thresh_qpart = "";
		}

		// Test if the feed need a update (update interval exceded).
		if (DB_TYPE == "pgsql") {
			$update_limit_qpart = "AND ((
					ttrss_feeds.update_interval = 0
					AND ttrss_feeds.last_updated < NOW() - CAST((ttrss_user_prefs.value || ' minutes') AS INTERVAL)
				) OR (
					ttrss_feeds.update_interval > 0
					AND ttrss_feeds.last_updated < NOW() - CAST((ttrss_feeds.update_interval || ' minutes') AS INTERVAL)
				) OR ttrss_feeds.last_updated IS NULL)";
		} else {
			$update_limit_qpart = "AND ((
					ttrss_feeds.update_interval = 0
					AND ttrss_feeds.last_updated < DATE_SUB(NOW(), INTERVAL CONVERT(ttrss_user_prefs.value, SIGNED INTEGER) MINUTE)
				) OR (
					ttrss_feeds.update_interval > 0
					AND ttrss_feeds.last_updated < DATE_SUB(NOW(), INTERVAL ttrss_feeds.update_interval MINUTE)
				) OR ttrss_feeds.last_updated IS NULL)";
		}

		// Test if feed is currently being updated by another process.
		if (DB_TYPE == "pgsql") {
			$updstart_thresh_qpart = "AND (ttrss_feeds.last_update_started IS NULL OR ttrss_feeds.last_update_started < NOW() - INTERVAL '120 seconds')";
		} else {
			$updstart_thresh_qpart = "AND (ttrss_feeds.last_update_started IS NULL OR ttrss_feeds.last_update_started < DATE_SUB(NOW(), INTERVAL 120 SECOND))";
		}

		// Test if there is a limit to number of updated feeds
		$query_limit = "";
		if($limit) $query_limit = sprintf("LIMIT %d", $limit);

		$random_qpart = sql_random_function();

		// We search for feed needing update.
		$result = db_query($link, "SELECT ttrss_feeds.feed_url,ttrss_feeds.id, ttrss_feeds.owner_uid,
				".SUBSTRING_FOR_DATE."(ttrss_feeds.last_updated,1,19) AS last_updated,
				ttrss_feeds.update_interval 
			FROM 
				ttrss_feeds, ttrss_users, ttrss_user_prefs
			WHERE
				ttrss_feeds.owner_uid = ttrss_users.id
				AND ttrss_users.id = ttrss_user_prefs.owner_uid
				AND ttrss_user_prefs.pref_name = 'DEFAULT_UPDATE_INTERVAL'
				$login_thresh_qpart $update_limit_qpart
			 $updstart_thresh_qpart
			ORDER BY $random_qpart $query_limit");

		$user_prefs_cache = array();

		if($debug) _debug(sprintf("Scheduled %d feeds to update...\n", db_num_rows($result)));

		// Here is a little cache magic in order to minimize risk of double feed updates.
		$feeds_to_update = array();
		while ($line = db_fetch_assoc($result)) {
			$feeds_to_update[$line['id']] = $line;
		}

		// We update the feed last update started date before anything else.
		// There is no lag due to feed contents downloads
		// It prevent an other process to update the same feed.
		$feed_ids = array_keys($feeds_to_update);
		if($feed_ids) {
			db_query($link, sprintf("UPDATE ttrss_feeds SET last_update_started = NOW()
				WHERE id IN (%s)", implode(',', $feed_ids)));
		}

		// For each feed, we call the feed update function.
		while ($line = array_pop($feeds_to_update)) {

			if($debug) _debug("Feed: " . $line["feed_url"] . ", " . $line["last_updated"]);

			// We setup a alarm to alert if the feed take more than 300s to update.
			// => HANG alarm.
			if(!$from_http && function_exists('pcntl_alarm')) pcntl_alarm(300);
			update_rss_feed($link, $line["feed_url"], $line["id"], true);
			// Cancel the alarm (the update went well)
			if(!$from_http && function_exists('pcntl_alarm')) pcntl_alarm(0);

			sleep(1); // prevent flood (FIXME make this an option?)
		}

	// Send feed digests by email if needed.
	if (DAEMON_SENDS_DIGESTS) send_headlines_digests($link);

	} // function update_daemon_common

	function generate_dashboard_feed($link) {

		print "<div id=\"headlinesContainer\">";

		print_headline_subtoolbar($link, "", "Dashboard", 
			false, false, -4, false, false, false,
			false, 0, 0, true, true, "tag.png");

		print "<div id=\"headlinesInnerContainer\" class=\"dashboard\">";
		print "<div>There is <b>666</b> unread articles in <b>666</b> feeds.</div>";
		print "</div>";

		print "</div>";

		print "]]></headlines>";
		print "<headlines-count value=\"0\"/>";
		print "<headlines-unread value=\"0\"/>";
		print "<disable-cache value=\"1\"/>";

		print "<articles>";
		print "</articles>";
	}

	function sanitize_article_content($text) {
		# we don't support CDATA sections in articles, they break our own escaping
		$text = preg_replace("/\[\[CDATA/", "", $text);
		$text = preg_replace("/\]\]\>/", "", $text);
		return $text;
	}

	function load_filters($link, $feed, $owner_uid, $action_id = false) {
		$filters = array();

		if ($action_id) $ftype_query_part = "action_id = '$action_id' AND";

		$result = db_query($link, "SELECT reg_exp,
			ttrss_filter_types.name AS name,
			ttrss_filter_actions.name AS action,
			inverse,
			action_param
			FROM ttrss_filters,ttrss_filter_types,ttrss_filter_actions WHERE 					
				enabled = true AND
				$ftype_query_part
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

		return $filters;
	}

	function get_score_pic($score) {
		if ($score > 0) { 
			return "score_high.png"; 
		} else if ($score < 0) {
			return "score_low.png"; 
		} else { 
			return "score_neutral.png"; 
		}
	}

	function rounded_table_start($classname, $header = "&nbsp;") {
		print "<table width='100%' class='$classname' cellspacing='0' cellpadding='0'>";
		print "<tr><td class='c1'>&nbsp;</td><td class='top'>$header</td><td class='c2'>&nbsp;</tr>";
		print "<tr><td class='left'>&nbsp;</td><td class='content'>";
	}

	function rounded_table_end($footer = "&nbsp;") {
		print "</td><td class='right'>&nbsp;</td></tr>";
		print "<tr><td class='c4'>&nbsp;</td><td class='bottom'>$footer</td><td class='c3'>&nbsp;</tr>";
		print "</table>";
	}

?>

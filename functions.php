<?php

	date_default_timezone_set('UTC');
	if (defined('E_DEPRECATED')) {
		error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
	} else {
		error_reporting(E_ALL & ~E_NOTICE);
	}

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
					"ca_CA" => "Català",
					"en_US" => "English",
					"es_ES" => "Español",
					"de_DE" => "Deutsch",
					"fr_FR" => "Français",
					"hu_HU" => "Magyar (Hungarian)",
					"it_IT" => "Italiano",
					"ja_JP" => "日本語 (Japanese)",
					"nb_NO" => "Norwegian bokmål",
					"ru_RU" => "Русский",
					"pt_BR" => "Portuguese/Brazil",
					"zh_CN" => "Simplified Chinese");

		return $tr;
	}

	if (ENABLE_TRANSLATIONS == true) { // If translations are enabled.
		require_once "lib/accept-to-gettext.php";
		require_once "lib/gettext/gettext.inc";

		function startup_gettext() {
	
			# Get locale from Accept-Language header
			$lang = al2gt(array_keys(get_translations()), "text/html");

			if (defined('_TRANSLATION_OVERRIDE_DEFAULT')) {
				$lang = _TRANSLATION_OVERRIDE_DEFAULT;
			}

			if ($_COOKIE["ttrss_lang"] && $_COOKIE["ttrss_lang"] != "auto") {				
				$lang = $_COOKIE["ttrss_lang"];
			}

			/* In login action of mobile version */
			if ($_POST["language"] && defined('MOBILE_VERSION')) {
				$lang = $_POST["language"];
				$_COOKIE["ttrss_lang"] = $lang;
			}

			if ($lang) {
				if (defined('LC_MESSAGES')) {
					_setlocale(LC_MESSAGES, $lang);
				} else if (defined('LC_ALL')) {
					_setlocale(LC_ALL, $lang);
				} else {
					die("can't setlocale(): please set ENABLE_TRANSLATIONS to false in config.php");
				}

				if (defined('MOBILE_VERSION')) {
					_bindtextdomain("messages", "../locale");
				} else {
					_bindtextdomain("messages", "locale");
				}

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

	if (defined('MEMCACHE_SERVER')) {
		$memcache = new Memcache;
		$memcache->connect(MEMCACHE_SERVER, 11211);
	}

	require_once 'db-prefs.php';
	require_once 'compat.php';
	require_once 'errors.php';
	require_once 'version.php';

	require_once 'lib/phpmailer/class.phpmailer.php';
	require_once 'lib/sphinxapi.php';

	define('MAGPIE_USER_AGENT_EXT', ' (Tiny Tiny RSS/' . VERSION . ')');
	define('MAGPIE_OUTPUT_ENCODING', 'UTF-8');
	define('MAGPIE_CACHE_AGE', 60*15); // 15 minutes

	require_once "lib/simplepie/simplepie.inc";
	require_once "lib/magpierss/rss_fetch.inc";
	require_once 'lib/magpierss/rss_utils.inc';
	require_once 'lib/htmlpurifier/library/HTMLPurifier.auto.php';

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

		if ($purge_interval == -1 || !$purge_interval) {
			if ($owner_uid) {
				ccache_update($link, $feed_id, $owner_uid);
			}
			return;
		}

		if (!$owner_uid) return;

		if (FORCE_ARTICLE_PURGE == 0) {
			$purge_unread = get_pref($link, "PURGE_UNREAD_ARTICLES",
				$owner_uid, false);
		} else {
			$purge_unread = true;
			$purge_interval = FORCE_ARTICLE_PURGE;
		}

		if (!$purge_unread) $query_limit = " unread = false AND ";

		if (DB_TYPE == "pgsql") {
/*			$result = db_query($link, "DELETE FROM ttrss_user_entries WHERE
				marked = false AND feed_id = '$feed_id' AND
				(SELECT date_updated FROM ttrss_entries WHERE
					id = ref_id) < NOW() - INTERVAL '$purge_interval days'"); */

			$pg_version = get_pgsql_version($link);

			if (preg_match("/^7\./", $pg_version) || preg_match("/^8\.0/", $pg_version)) {

				$result = db_query($link, "DELETE FROM ttrss_user_entries WHERE 
					ttrss_entries.id = ref_id AND 
					marked = false AND 
					feed_id = '$feed_id' AND 
					$query_limit
					ttrss_entries.date_updated < NOW() - INTERVAL '$purge_interval days'");

			} else {

				$result = db_query($link, "DELETE FROM ttrss_user_entries 
					USING ttrss_entries 
					WHERE ttrss_entries.id = ref_id AND 
					marked = false AND 
					feed_id = '$feed_id' AND 
					$query_limit
					ttrss_entries.date_updated < NOW() - INTERVAL '$purge_interval days'");
			}

			$rows = pg_affected_rows($result);
			
		} else {
		
/*			$result = db_query($link, "DELETE FROM ttrss_user_entries WHERE
				marked = false AND feed_id = '$feed_id' AND
				(SELECT date_updated FROM ttrss_entries WHERE 
					id = ref_id) < DATE_SUB(NOW(), INTERVAL $purge_interval DAY)"); */

			$result = db_query($link, "DELETE FROM ttrss_user_entries 
				USING ttrss_user_entries, ttrss_entries 
				WHERE ttrss_entries.id = ref_id AND 
				marked = false AND 
				feed_id = '$feed_id' AND 
				$query_limit
				ttrss_entries.date_updated < DATE_SUB(NOW(), INTERVAL $purge_interval DAY)");
					
			$rows = mysql_affected_rows($link);

		}

		ccache_update($link, $feed_id, $owner_uid);

		if ($debug) {
			_debug("Purged feed $feed_id ($purge_interval): deleted $rows articles");
		}
	} // function purge_feed

	/**
	 * Purge old posts from old feeds. Not used anymore, purging is done after feed update.
	 * 
	 * @param mixed $link A database connection
	 * @param boolean $do_output Set to true to enable printed output, false by default.
	 * @param integer $limit The maximal number of removed posts.
	 * @access public
	 * @return void
	 */
	/* function global_purge_old_posts($link, $do_output = false, $limit = false) {

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

			if ($purge_interval > 0 || FORCE_ARTICLE_PURGE) {
				purge_feed($link, $feed_id, $purge_interval, $do_output);
			}
		}	

		purge_orphans($link, $do_output);

	} // function global_purge_old_posts */

	function feed_purge_interval($link, $feed_id) {

		$result = db_query($link, "SELECT purge_interval, owner_uid FROM ttrss_feeds 
			WHERE id = '$feed_id'");

		if (db_num_rows($result) == 1) {
			$purge_interval = db_fetch_result($result, 0, "purge_interval");
			$owner_uid = db_fetch_result($result, 0, "owner_uid");

			if ($purge_interval == 0) $purge_interval = get_pref($link, 
				'PURGE_OLD_DAYS', $owner_uid);

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

		purge_orphans($link);
	}

	function purge_orphans($link, $do_output = false) {

		// purge orphaned posts in main content table
		$result = db_query($link, "DELETE FROM ttrss_entries WHERE 
			(SELECT COUNT(int_id) FROM ttrss_user_entries WHERE ref_id = id) = 0");

		if ($do_output) {
			$rows = db_affected_rows($link, $result);
			_debug("Purged $rows orphaned posts.");
		}
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

	function fetch_file_contents($url, $type = false) {
		if (USE_CURL_FOR_ICONS) {
			$ch = curl_init($url);

			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
			curl_setopt($ch, CURLOPT_TIMEOUT, 45);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_MAXREDIRS, 20);
			curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

			$contents = @curl_exec($ch);
			if ($contents === false) {
				curl_close($ch);
				return false;
			}

			$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
			curl_close($ch);

			if ($type && strpos($content_type, "$type") === false) {
				return false;
			}

			return $contents;
		} else {
			return @file_get_contents($url);
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

		$favicon_url = false;

		if ($html = @fetch_file_contents($url)) {

			libxml_use_internal_errors(true);

			$doc = new DOMDocument();
			$doc->loadHTML($html);
			$xpath = new DOMXPath($doc);
			$entries = $xpath->query('/html/head/link[@rel="shortcut icon"]');

			if (count($entries) > 0) {
				foreach ($entries as $entry) {
					$favicon_url = rewrite_relative_url($url, $entry->getAttribute("href"));
					break;
				}
			}		
		}

		if (!$favicon_url)
			$favicon_url = rewrite_relative_url($url, "/favicon.ico");

		// Run a test to see if what we have attempted to get actually exists.
		if(USE_CURL_FOR_ICONS || url_validate($favicon_url)) {
			return $favicon_url;
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

		$responses = "/(200 OK)|(30[123])/";
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

		$icon_file = ICONS_DIR . "/$feed.ico";

		if ($favicon_url && !file_exists($icon_file)) {
			$contents = fetch_file_contents($favicon_url, "image");

			if ($contents) {
				$fp = fopen($icon_file, "w");

				if ($fp) {
					fwrite($fp, $contents);
					fclose($fp);
					chmod($icon_file, 0644);
				}
			}
		}
	}

	function update_rss_feed($link, $feed, $ignore_daemon = false) {

		global $memcache;

		/* Update all feeds with the same URL to utilize memcache */

		if ($memcache) {
			$result = db_query($link, "SELECT f1.id 
				FROM ttrss_feeds AS f1, ttrss_feeds AS f2 
				WHERE	f2.feed_url = f1.feed_url AND f2.id = '$feed'");

			while ($line = db_fetch_assoc($result)) {
				update_rss_feed_real($link, $line["id"], $ignore_daemon);
			}
		} else {
			update_rss_feed_real($link, $feed, $ignore_daemon);
		}
	}

	function update_rss_feed_real($link, $feed, $ignore_daemon = false) {

		global $memcache;

		if (!$_REQUEST["daemon"] && !$ignore_daemon) {
			return false;
		}

		if (defined('DAEMON_EXTENDED_DEBUG') || $_REQUEST['xdebug']) {
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
				feed_url,auth_pass,cache_images,update_method,last_updated
				FROM ttrss_feeds WHERE id = '$feed'");

		}

		if (db_num_rows($result) == 0) {
			if (defined('DAEMON_EXTENDED_DEBUG') || $_REQUEST['xdebug']) {
				_debug("update_rss_feed: feed $feed NOT FOUND/SKIPPED");
			}		
			return false;
		}

		$update_method = db_fetch_result($result, 0, "update_method");
		$last_updated = db_fetch_result($result, 0, "last_updated");

		db_query($link, "UPDATE ttrss_feeds SET last_update_started = NOW()
			WHERE id = '$feed'");

		$auth_login = db_fetch_result($result, 0, "auth_login");
		$auth_pass = db_fetch_result($result, 0, "auth_pass");

		if (DEFAULT_UPDATE_METHOD == "1") {
			$use_simplepie = $update_method != 1;
		} else {
			$use_simplepie = $update_method == 2;
		}

		if (defined('DAEMON_EXTENDED_DEBUG') || $_REQUEST['xdebug']) {
			_debug("use simplepie: $use_simplepie (feed setting: $update_method)\n");
		}

		if (!$use_simplepie) {
			$auth_login = urlencode($auth_login);
			$auth_pass = urlencode($auth_pass);
		}

		$update_interval = db_fetch_result($result, 0, "update_interval");
		$cache_images = sql_bool_to_bool(db_fetch_result($result, 0, "cache_images"));
		$fetch_url = db_fetch_result($result, 0, "feed_url");

		if ($update_interval < 0) { return; }

		$feed = db_escape_string($feed);

		if ($auth_login && $auth_pass) {
			$url_parts = array();
			preg_match("/(^[^:]*):\/\/(.*)/", $fetch_url, $url_parts);

			if ($url_parts[1] && $url_parts[2]) {
				$fetch_url = $url_parts[1] . "://$auth_login:$auth_pass@" . $url_parts[2];
			}

		}

		if (defined('DAEMON_EXTENDED_DEBUG') || $_REQUEST['xdebug']) {
			_debug("update_rss_feed: fetching [$fetch_url]...");
		}

		$obj_id = md5("FDATA:$use_simplepie:$fetch_url");

		if ($memcache && $obj = $memcache->get($obj_id)) {

			if (defined('DAEMON_EXTENDED_DEBUG') || $_REQUEST['xdebug']) {
				_debug("update_rss_feed: data found in memcache.");
			}

			$rss = $obj;

		} else {

			if (!$use_simplepie) {
				$rss = @fetch_rss($fetch_url);
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
					if (defined('DAEMON_EXTENDED_DEBUG') || $_REQUEST['xdebug']) {
						_debug("enabling image cache");
					}
	
					$rss->set_image_handler('./image.php', 'i');
				}
	
				if (defined('DAEMON_EXTENDED_DEBUG') || $_REQUEST['xdebug']) {
					_debug("feed update interval (sec): " .
						get_feed_update_interval($link, $feed)*60);
				}
	
				if (is_dir(SIMPLEPIE_CACHE_DIR)) {
					$rss->set_cache_location(SIMPLEPIE_CACHE_DIR);
					$rss->set_cache_duration(get_feed_update_interval($link, $feed) * 60);
				}
	
				$rss->init();
			}

			if ($memcache && $rss) $memcache->add($obj_id, $rss, 0, 300);
		}

//		print_r($rss);

		if (defined('DAEMON_EXTENDED_DEBUG') || $_REQUEST['xdebug']) {
			_debug("update_rss_feed: fetch done, parsing...");
		}

		$feed = db_escape_string($feed);

		if ($use_simplepie) {
			$fetch_ok = !$rss->error();
		} else {
			$fetch_ok = !!$rss;
		}

		if ($fetch_ok) {

			if (defined('DAEMON_EXTENDED_DEBUG') || $_REQUEST['xdebug']) {
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

			if (defined('DAEMON_EXTENDED_DEBUG') || $_REQUEST['xdebug']) {
				_debug("update_rss_feed: checking favicon...");
			}

			check_feed_favicon($site_url, $feed, $link);

			if (!$registered_title || $registered_title == "[Unknown]") {

				if ($use_simplepie) {
					$feed_title = db_escape_string($rss->get_title());
				} else {
					$feed_title = db_escape_string($rss->channel["title"]);
				}

				if (defined('DAEMON_EXTENDED_DEBUG') || $_REQUEST['xdebug']) {
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
				$icon_url = db_escape_string($rss->image["url"]);
			} else {
				$icon_url = db_escape_string($rss->get_image_url());
			}

			if ($icon_url && $orig_icon_url != $icon_url) { 
				if (USE_CURL_FOR_ICONS || url_validate($icon_url)) {
					db_query($link, "UPDATE ttrss_feeds SET icon_url = '$icon_url' WHERE id = '$feed'");
				}
			}

			if (defined('DAEMON_EXTENDED_DEBUG') || $_REQUEST['xdebug']) {
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

				if (defined('DAEMON_EXTENDED_DEBUG') || $_REQUEST['xdebug']) {
					_debug("update_rss_feed: entry iterator is not an array, no articles?");
				}

				db_query($link, "UPDATE ttrss_feeds 
					SET last_updated = NOW(), last_error = '' WHERE id = '$feed'");

				return; // no articles
			}

			if (defined('DAEMON_EXTENDED_DEBUG') || $_REQUEST['xdebug']) {
				_debug("update_rss_feed: processing articles...");
			}

			foreach ($iterator as $item) {

				if ($_REQUEST['xdebug'] == 2) {
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

				if (defined('DAEMON_EXTENDED_DEBUG') || $_REQUEST['xdebug']) {
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

				if ($entry_timestamp == "" || $entry_timestamp == -1 || !$entry_timestamp) {
					$entry_timestamp = time();
					$no_orig_date = 'true';
				} else {
					$no_orig_date = 'false';
				}

				$entry_timestamp_fmt = strftime("%Y/%m/%d %H:%M:%S", $entry_timestamp);

				if (defined('DAEMON_EXTENDED_DEBUG') || $_REQUEST['xdebug']) {
					_debug("update_rss_feed: date $entry_timestamp [$entry_timestamp_fmt]");
				}

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

				if (defined('DAEMON_EXTENDED_DEBUG') || $_REQUEST['xdebug']) {
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
					if (!$entry_content) $entry_content = $item["content"]["encoded"];
					if (!$entry_content) $entry_content = $item["content"];

					// Magpie bugs are getting ridiculous
					if (trim($entry_content) == "Array") $entry_content = false;

					if (!$entry_content) $entry_content = $item["atom_content"];
					if (!$entry_content) $entry_content = $item["summary"];

					if (!$entry_content || 
						strlen($entry_content) < strlen($item["description"])) {
							$entry_content = $item["description"];
					};

					// WTF
					if (is_array($entry_content)) {
						$entry_content = $entry_content["encoded"];
						if (!$entry_content) $entry_content = $entry_content["escaped"];
					} 
				}

				if ($_REQUEST["xdebug"] == 2) {
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

				if (defined('DAEMON_EXTENDED_DEBUG') || $_REQUEST['xdebug']) {
					_debug("update_rss_feed: looking for tags [1]...");
				}

				// parse <category> entries into tags

				$additional_tags = array();

				if ($use_simplepie) {

					$additional_tags_src = $item->get_categories();
					
					if (is_array($additional_tags_src)) {
						foreach ($additional_tags_src as $tobj) {
							array_push($additional_tags, $tobj->get_term());
						}
					}

					if (defined('DAEMON_EXTENDED_DEBUG') || $_REQUEST['xdebug']) {
						_debug("update_rss_feed: category tags:");
						print_r($additional_tags);
					}

				} else {

					$t_ctr = $item['category#'];

					if ($t_ctr == 0) {
						$additional_tags = array();
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
						array_push($additional_tags, $item['dc']['subject']);

						for ($i = 0; $i <= $t_ctr; $i++ ) {
							if ($item['dc']["subject#$i"]) {
								array_push($additional_tags, $item['dc']["subject#$i"]);
							}
						}
					}
				}

				if (defined('DAEMON_EXTENDED_DEBUG') || $_REQUEST['xdebug']) {
					_debug("update_rss_feed: looking for tags [2]...");
				}

				/* taaaags */
				// <a href="..." rel="tag">Xorg</a>, //

				$entry_tags = null;

				preg_match_all("/<a.*?rel=['\"]tag['\"].*?\>([^<]+)<\/a>/i",
					$entry_content_unescaped, $entry_tags);

				$entry_tags = $entry_tags[1];

				$entry_tags = array_merge($entry_tags, $additional_tags);
				$entry_tags = array_unique($entry_tags);

				for ($i = 0; $i < count($entry_tags); $i++)
					$entry_tags[$i] = mb_strtolower($entry_tags[$i], 'utf-8');

				if (defined('DAEMON_EXTENDED_DEBUG') || $_REQUEST['xdebug']) {
					_debug("update_rss_feed: unfiltered tags found:");
					print_r($entry_tags);
				}

				# sanitize content
				
				$entry_content = sanitize_article_content($entry_content);
				$entry_title = sanitize_article_content($entry_title);

				if (defined('DAEMON_EXTENDED_DEBUG') || $_REQUEST['xdebug']) {
					_debug("update_rss_feed: done collecting data [TITLE:$entry_title]");
				}

				db_query($link, "BEGIN");

				if (db_num_rows($result) == 0) {

					if (defined('DAEMON_EXTENDED_DEBUG') || $_REQUEST['xdebug']) {
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
							date_updated,
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
							NOW(),
							'$entry_comments',
							'$num_comments',
							'$entry_author')");
				} else {
					// we keep encountering the entry in feeds, so we need to
					// update date_updated column so that we don't get horrible
					// dupes when the entry gets purged and reinserted again e.g.
					// in the case of SLOW SLOW OMG SLOW updating feeds

					$base_entry_id = db_fetch_result($result, 0, "id");

					db_query($link, "UPDATE ttrss_entries SET date_updated = NOW()
						WHERE id = '$base_entry_id'");
				}

				// now it should exist, if not - bad luck then

				$result = db_query($link, "SELECT 
						id,content_hash,no_orig_date,title,
						".SUBSTRING_FOR_DATE."(date_updated,1,19) as date_updated,
						".SUBSTRING_FOR_DATE."(updated,1,19) as updated,
						num_comments
					FROM 
						ttrss_entries 
					WHERE guid = '$entry_guid'");

				$entry_ref_id = 0;
				$entry_int_id = 0;

				if (db_num_rows($result) == 1) {

					if (defined('DAEMON_EXTENDED_DEBUG') || $_REQUEST['xdebug']) {
						_debug("update_rss_feed: base guid found, checking for user record");
					}

					// this will be used below in update handler
					$orig_content_hash = db_fetch_result($result, 0, "content_hash");
					$orig_title = db_fetch_result($result, 0, "title");
					$orig_num_comments = db_fetch_result($result, 0, "num_comments");
					$orig_date_updated = strtotime(db_fetch_result($result, 
						0, "date_updated"));

					$ref_id = db_fetch_result($result, 0, "id");
					$entry_ref_id = $ref_id;

					// check for user post link to main table

					// do we allow duplicate posts with same GUID in different feeds?
					if (get_pref($link, "ALLOW_DUPLICATE_POSTS", $owner_uid, false)) {
						$dupcheck_qpart = "AND (feed_id = '$feed' OR feed_id IS NULL)";
					} else { 
						$dupcheck_qpart = "";
					}

					/* Collect article tags here so we could filter by them: */

					$article_filters = get_article_filters($filters, $entry_title, 
						$entry_content, $entry_link, $entry_timestamp, $entry_author, 
						$entry_tags);

					if (defined('DAEMON_EXTENDED_DEBUG') || $_REQUEST['xdebug']) {
						_debug("update_rss_feed: article filters: ");
						if (count($article_filters) != 0) {
							print_r($article_filters);
						}
					}

					if (find_article_filter($article_filters, "filter")) {
						db_query($link, "COMMIT"); // close transaction in progress
						continue;
					}

					$score = calculate_article_score($article_filters);

					if (defined('DAEMON_EXTENDED_DEBUG') || $_REQUEST['xdebug']) {
						_debug("update_rss_feed: initial score: $score");
					}

					$query = "SELECT ref_id, int_id FROM ttrss_user_entries WHERE
							ref_id = '$ref_id' AND owner_uid = '$owner_uid'
							$dupcheck_qpart";

//					if ($_REQUEST["xdebug"]) print "$query\n";

					$result = db_query($link, $query);

					// okay it doesn't exist - create user entry
					if (db_num_rows($result) == 0) {

						if (defined('DAEMON_EXTENDED_DEBUG') || $_REQUEST['xdebug']) {
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
									published, score, tag_cache, label_cache) 
							VALUES ('$ref_id', '$owner_uid', '$feed', $unread,
								$last_read_qpart, $marked, $published, '$score', '', '')");

						$result = db_query($link, 
							"SELECT int_id FROM ttrss_user_entries WHERE
								ref_id = '$ref_id' AND owner_uid = '$owner_uid' AND
								feed_id = '$feed' LIMIT 1");

						if (db_num_rows($result) == 1) {
							$entry_int_id = db_fetch_result($result, 0, "int_id");
						}
					} else {
						if (defined('DAEMON_EXTENDED_DEBUG') || $_REQUEST['xdebug']) {
							_debug("update_rss_feed: user record FOUND");
						}

						$entry_ref_id = db_fetch_result($result, 0, "ref_id");
						$entry_int_id = db_fetch_result($result, 0, "int_id");
					}

					if (defined('DAEMON_EXTENDED_DEBUG') || $_REQUEST['xdebug']) {
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

				if (defined('DAEMON_EXTENDED_DEBUG') || $_REQUEST['xdebug']) {
					_debug("update_rss_feed: assigning labels...");
				}

				assign_article_to_labels($link, $entry_ref_id, $article_filters,
					$owner_uid);

				if (defined('DAEMON_EXTENDED_DEBUG') || $_REQUEST['xdebug']) {
					_debug("update_rss_feed: looking for enclosures...");
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
					// <enclosure>

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

					// <media:content>
					// can there be many of those? yes -fox

					$m_ctr = $item['media']['content#'];

					if ($m_ctr > 0) {
						$e_item = array($item['media']['content@url'],
							$item['media']['content@medium'],
							$item['media']['content@length']);

						array_push($enclosures, $e_item);

						for ($i = 0; $i <= $m_ctr; $i++ ) {

							if ($item["media"]["content#$i@url"]) {
								$e_item = array($item["media"]["content#$i@url"],
									$item["media"]["content#$i@medium"],
									$item["media"]["content#$i@length"]);
								array_push($enclosures, $e_item);
							}
						}

					}
				}


				if (defined('DAEMON_EXTENDED_DEBUG') || $_REQUEST['xdebug']) {
					_debug("update_rss_feed: article enclosures:");
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

				// check for manual tags (we have to do it here since they're loaded from filters)

				foreach ($article_filters as $f) {
					if ($f[0] == "tag") {

						$manual_tags = trim_array(split(",", $f[1]));

						foreach ($manual_tags as $tag) {
							if (tag_is_valid($tag)) {
								array_push($entry_tags, $tag);
							}
						}
					}
				}

				// Skip boring tags

				$boring_tags = trim_array(split(",", mb_strtolower(get_pref($link, 
					'BLACKLISTED_TAGS', $owner_uid, ''), 'utf-8')));

				$filtered_tags = array();
				$tags_to_cache = array();

				if ($entry_tags && is_array($entry_tags)) {
					foreach ($entry_tags as $tag) {
						if (array_search($tag, $boring_tags) === false) {
							array_push($filtered_tags, $tag);
						}
					}
				} 

				$filtered_tags = array_unique($filtered_tags);

				if (defined('DAEMON_EXTENDED_DEBUG') || $_REQUEST['xdebug']) {
					_debug("update_rss_feed: filtered article tags:");
					print_r($filtered_tags);
				}

				// Save article tags in the database

				if (count($filtered_tags) > 0) {
				
					db_query($link, "BEGIN");
			
					foreach ($filtered_tags as $tag) {

						$tag = sanitize_tag($tag);
						$tag = db_escape_string($tag);

						if (!tag_is_valid($tag)) continue;
						
						$result = db_query($link, "SELECT id FROM ttrss_tags		
							WHERE tag_name = '$tag' AND post_int_id = '$entry_int_id' AND 
							owner_uid = '$owner_uid' LIMIT 1");

							if ($result && db_num_rows($result) == 0) {
								
								db_query($link, "INSERT INTO ttrss_tags 
									(owner_uid,tag_name,post_int_id)
									VALUES ('$owner_uid','$tag', '$entry_int_id')");
							}

						array_push($tags_to_cache, $tag);
					}

					/* update the cache */
							
					$tags_to_cache = array_unique($tags_to_cache);
		
					$tags_str = db_escape_string(join(",", $tags_to_cache));

					db_query($link, "UPDATE ttrss_user_entries 
						SET tag_cache = '$tags_str' WHERE ref_id = '$entry_ref_id'
						AND owner_uid = $owner_uid");

					db_query($link, "COMMIT");
				} 

				if (defined('DAEMON_EXTENDED_DEBUG') || $_REQUEST['xdebug']) {
					_debug("update_rss_feed: article processed");
				}
			} 

			if (!$last_updated) {
				if (defined('DAEMON_EXTENDED_DEBUG') || $_REQUEST['xdebug']) {
					_debug("update_rss_feed: new feed, catching it up...");
				}
				catchup_feed($link, $feed, false, $owner_uid);
			}

			purge_feed($link, $feed, 0);

			db_query($link, "UPDATE ttrss_feeds 
				SET last_updated = NOW(), last_error = '' WHERE id = '$feed'");

//			db_query($link, "COMMIT");

		} else {

			if ($use_simplepie) {
				$error_msg = mb_substr($rss->error(), 0, 250);
			} else {
				$error_msg = mb_substr(magpie_error(), 0, 250);
			}

			if (defined('DAEMON_EXTENDED_DEBUG') || $_REQUEST['xdebug']) {
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

		if (defined('DAEMON_EXTENDED_DEBUG') || $_REQUEST['xdebug']) {
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
				$sel = 'selected="selected"';
			 else
			 	$sel = "";
			
			print "<option $sel value=\"$v\">".$values[$v]."</option>";
		}

		print "</select>";
	}

	function get_article_filters($filters, $title, $content, $link, $timestamp, $author, $tags) {
		$matches = array();

		if ($filters["title"]) {
			foreach ($filters["title"] as $filter) {
				$reg_exp = $filter["reg_exp"];		
				$inverse = $filter["inverse"];	
				if ((!$inverse && @preg_match("/$reg_exp/i", $title)) || 
						($inverse && !@preg_match("/$reg_exp/i", $title))) {

					array_push($matches, array($filter["action"], $filter["action_param"]));
				}
			}
		}

		if ($filters["content"]) {
			foreach ($filters["content"] as $filter) {
				$reg_exp = $filter["reg_exp"];
				$inverse = $filter["inverse"];

				if ((!$inverse && @preg_match("/$reg_exp/i", $content)) || 
						($inverse && !@preg_match("/$reg_exp/i", $content))) {

					array_push($matches, array($filter["action"], $filter["action_param"]));
				}		
			}
		}

		if ($filters["both"]) {
			foreach ($filters["both"] as $filter) {			
				$reg_exp = $filter["reg_exp"];		
				$inverse = $filter["inverse"];

				if ($inverse) {
					if (!@preg_match("/$reg_exp/i", $title) && !preg_match("/$reg_exp/i", $content)) {
						array_push($matches, array($filter["action"], $filter["action_param"]));
					}
				} else {
					if (@preg_match("/$reg_exp/i", $title) || preg_match("/$reg_exp/i", $content)) {
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

				if ((!$inverse && @preg_match("/$reg_exp/i", $link)) || 
						($inverse && !@preg_match("/$reg_exp/i", $link))) {
						
					array_push($matches, array($filter["action"], $filter["action_param"]));
				}
			}
		}

		if ($filters["date"]) {
			$reg_exp = $filter["reg_exp"];
			foreach ($filters["date"] as $filter) {
				$date_modifier = $filter["filter_param"];
				$inverse = $filter["inverse"];
				$check_timestamp = strtotime($filter["reg_exp"]);

				# no-op when timestamp doesn't parse to prevent misfires

				if ($check_timestamp) {
					$match_ok = false;

					if ($date_modifier == "before" && $timestamp < $check_timestamp ||
						$date_modifier == "after" && $timestamp > $check_timestamp) {
							$match_ok = true;
					}

					if ($inverse) $match_ok = !$match_ok;

					if ($match_ok) {
						array_push($matches, array($filter["action"], $filter["action_param"]));
					}
				}
			}
		} 

		if ($filters["author"]) {
			foreach ($filters["author"] as $filter) {
				$reg_exp = $filter["reg_exp"];		
				$inverse = $filter["inverse"];	
				if ((!$inverse && @preg_match("/$reg_exp/i", $author)) || 
						($inverse && !@preg_match("/$reg_exp/i", $author))) {

					array_push($matches, array($filter["action"], $filter["action_param"]));
				}
			}
		}

		if ($filters["tag"]) {

			$tag_string = join(",", $tags);

			foreach ($filters["tag"] as $filter) {
				$reg_exp = $filter["reg_exp"];
				$inverse = $filter["inverse"];

				if ((!$inverse && @preg_match("/$reg_exp/i", $tag_string)) || 
						($inverse && !@preg_match("/$reg_exp/i", $tag_string))) {

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

	function assign_article_to_labels($link, $id, $filters, $owner_uid) {
		foreach ($filters as $f) {
			if ($f[0] == "label") {
				label_add_article($link, $id, $f[1], $owner_uid);
			};
		}
	}

	function printFeedEntry($feed_id, $class, $feed_title, $unread, $icon_file, $link,
		$rtl_content = false, $last_updated = false, $last_error = false,
		$fg_content = false, $bg_content = false) {

		if (!$feed_title) $feed_title = getFeedTitle($link, $feed_id, false);
		if (!$unread) $unread = getFeedUnread($link, $feed_id);	

		if ($unread > 0) $class .= "Unread";

		if (!$icon_file) $icon_file = getFeedIcon($feed_id);

		if (strpos($icon_file, "images") !== false) {
			$icon_file = theme_image($link, $icon_file);
		}

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

		$feed_title = truncate_string($feed_title, 30);

		$feed = "<span class='feedlink' title=\"$link_title\" id=\"FEEDL-$feed_id\" href=\"#\"
			onclick=\"viewfeed('$feed_id');\">$feed_title</span>";

/*		if ($feed_id < -10) {
			$bg_color = "#00ccff";
			$fg_color = "white";
		}

		if ($fg_color || $bg_color) {
			$color_str = "<div class='labelColorIndicator'
				style='color : $fg_color; background-color : $bg_color'>l</div>";
		} 

		print $color_str; */

		print "<li id=\"FEEDR-$feed_id\" class=\"$class\">";
		print "$feed_icon";
		print "<span $rtl_tag id=\"FEEDN-$feed_id\">$feed</span>";

		if ($unread != 0) {
			$fctr_class = "class=\"feedCtrHasUnread\"";
		} else {
			$fctr_class = "class=\"feedCtrNoUnread\"";
		}

		print " <span $rtl_tag $fctr_class id=\"FEEDCTR-$feed_id\">
			 (<span id=\"FEEDU-$feed_id\">$unread</span>)</span>";

		if (get_pref($link, "EXTENDED_FEEDLIST")) {		 	 
			$total = getFeedArticles($link, $feed_id);
			print "<div class=\"feedExtInfo\">
				<span id=\"FLUPD-$feed_id\">$last_updated ($total total) $error_notify_msg</span></div>";
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

	function initialize_user_prefs($link, $uid, $profile = false) {

		$uid = db_escape_string($uid);

		if (!$profile) {
			$profile = "NULL";
			$profile_qpart = "AND profile IS NULL";
		} else {
			$profile_qpart = "AND profile = '$profile'";
		}

		if (get_schema_version($link) < 63) $profile_qpart = "";

		db_query($link, "BEGIN");

		$result = db_query($link, "SELECT pref_name,def_value FROM ttrss_prefs");
		
		$u_result = db_query($link, "SELECT pref_name 
			FROM ttrss_user_prefs WHERE owner_uid = '$uid' $profile_qpart");

		$active_prefs = array();

		while ($line = db_fetch_assoc($u_result)) {
			array_push($active_prefs, $line["pref_name"]);			
		}

		while ($line = db_fetch_assoc($result)) {
			if (array_search($line["pref_name"], $active_prefs) === FALSE) {
//				print "adding " . $line["pref_name"] . "<br>";

				if (get_schema_version($link) < 63) {
					db_query($link, "INSERT INTO ttrss_user_prefs
						(owner_uid,pref_name,value) VALUES 
						('$uid', '".$line["pref_name"]."','".$line["def_value"]."')");

				} else {
					db_query($link, "INSERT INTO ttrss_user_prefs
						(owner_uid,pref_name,value, profile) VALUES 
						('$uid', '".$line["pref_name"]."','".$line["def_value"]."', $profile)");
				}

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

//		error_log("http_authenticate_user: ".$_SERVER["PHP_AUTH_USER"]."\n", 3, '/tmp/tt-rss.log');

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
			$login = db_escape_string($login);

			if (defined('ALLOW_REMOTE_USER_AUTH') && ALLOW_REMOTE_USER_AUTH 
					&& $_SERVER["REMOTE_USER"] && $login != "admin") {

				$login = db_escape_string($_SERVER["REMOTE_USER"]);

				$query = "SELECT id,login,access_level,pwd_hash
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
	
				$_SESSION["ip_address"] = $_SERVER["REMOTE_ADDR"];
				$_SESSION["pwd_hash"] = db_fetch_result($result, 0, "pwd_hash");

				$_SESSION["last_version_check"] = time();
	
				initialize_user_prefs($link, $_SESSION["uid"]);
	
				return true;
			}
	
			return false;

		} else {

			$_SESSION["uid"] = 1;
			$_SESSION["name"] = "admin";

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

		db_query($link, "insert into ttrss_feeds (owner_uid,title,feed_url)
			values ('$uid', 'Tiny Tiny RSS: New Releases',
			'http://tt-rss.org/releases.rss')");

		db_query($link, "insert into ttrss_feeds (owner_uid,title,feed_url)
			values ('$uid', 'Tiny Tiny RSS: Forum',
				'http://tt-rss.org/forum/rss.php')");
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
					$_SESSION["login_error_msg"] = __("Session failed to validate (incorrect IP)");
					return false;
				}
			}
		}

		if ($_SESSION["ref_schema_version"] != get_schema_version($link, true)) {
			return false;
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

			$login_action = $_POST["login_action"];

			# try to authenticate user if called from login form			
			if ($login_action == "do_login") {
				$login = $_POST["login"];
				$password = $_POST["password"];
				$remember_me = $_POST["remember_me"];

				if (authenticate_user($link, $login, $password)) {
					$_POST["password"] = "";

					$_SESSION["language"] = $_POST["language"];
					$_SESSION["ref_schema_version"] = get_schema_version($link, true);
					$_SESSION["bw_limit"] = !!$_POST["bw_limit"];

					if ($_POST["profile"]) {

						$profile = db_escape_string($_POST["profile"]);

						$result = db_query($link, "SELECT id FROM ttrss_settings_profiles
							WHERE id = '$profile' AND owner_uid = " . $_SESSION["uid"]);

						if (db_num_rows($result) != 0) {
							$_SESSION["profile"] = $profile;
							$_SESSION["prefs_cache"] = array();
						}
					}

					header("Location: " . $_SERVER["REQUEST_URI"]);
					exit;

					return;
				} else {
					$_SESSION["login_error_msg"] = __("Incorrect username or password");
				}
			}

			if (!$_SESSION["uid"] || !validate_session($link)) {
				render_login_form($link, $mobile);
				//header("Location: login.php");
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

	function theme_image($link, $filename) {
		if ($link) {
			$theme_path = get_user_theme_path($link);

			if ($theme_path && is_file($theme_path.$filename)) {
				return $theme_path.$filename;
			} else {
				return $filename;
			}
		} else {
			return $filename;
		}
	}

	function get_user_theme($link) {

		if (get_schema_version($link) >= 63 && $_SESSION["uid"]) {
			$theme_name = get_pref($link, "_THEME_ID");
			if (is_dir("themes/$theme_name")) {
				return $theme_name;
			} else {
				return '';
			}
		} else {
			return '';
		}

	}

	function get_user_theme_path($link) {
		$theme_path = '';

		if (get_schema_version($link) >= 63 && $_SESSION["uid"]) {
			$theme_name = get_pref($link, "_THEME_ID");

			if ($theme_name && is_dir("themes/$theme_name")) {
				$theme_path = "themes/$theme_name/";
			} else {
				$theme_name = '';
			}
		} else {
			$theme_path = '';
		}

		return $theme_path;
	}

	function get_user_theme_options($link) {
		$t = get_user_theme_path($link);

		if ($t) {
			if (is_file("$t/theme.ini")) {
				$ini = parse_ini_file("$t/theme.ini", true);
				if ($ini['theme']['version']) {
					return $ini['theme']['options'];
				}
			}
		}
		return '';
	}


	function get_all_themes() {
		$themes = glob("themes/*");

		asort($themes);

		$rv = array();

		foreach ($themes as $t) {
			if (is_file("$t/theme.ini")) {
				$ini = parse_ini_file("$t/theme.ini", true);
				if ($ini['theme']['version'] && !$ini['theme']['disabled']) {
					$entry = array();
					$entry["path"] = $t;
					$entry["base"] = basename($t);
					$entry["name"] = $ini['theme']['name'];
					$entry["version"] = $ini['theme']['version'];
					$entry["author"] = $ini['theme']['author'];
					$entry["options"] = $ini['theme']['options'];
					array_push($rv, $entry);
				}
			}
		}

		return $rv;
	}

	function make_local_datetime($link, $timestamp, $long, $owner_uid = false,
					$no_smart_dt = false) {

		if (!$owner_uid) $owner_uid = $_SESSION['uid'];
		if (!$timestamp) $timestamp = '1970-01-01 0:00';

		$user_tz_string = get_pref($link, 'USER_TIMEZONE', $owner_uid);

		try {
			$user_tz = new DateTimeZone($user_tz_string);
		} catch (Exception $e) {
			$user_tz = new DateTimeZone('UTC');
		}

		# We store date in UTC internally
		$dt = new DateTime($timestamp, new DateTimeZone('UTC'));
		$user_timestamp = $dt->format('U') + $user_tz->getOffset($dt);

		if (!$no_smart_dt && get_pref($link, 'HEADLINES_SMART_DATE', $owner_uid)) {
			return smart_date_time($link, $user_timestamp, 
				$user_tz->getOffset($dt), $owner_uid);
		} else {
			if ($long)
				$format = get_pref($link, 'LONG_DATE_FORMAT', $owner_uid);
			else
				$format = get_pref($link, 'SHORT_DATE_FORMAT', $owner_uid);

			return date($format, $user_timestamp);
		}
	}

	function smart_date_time($link, $timestamp, $tz_offset = 0, $owner_uid = false) {
		if (!$owner_uid) $owner_uid = $_SESSION['uid'];

		if (date("Y.m.d", $timestamp) == date("Y.m.d", time() + $tz_offset)) {
			return date("G:i", $timestamp);
		} else if (date("Y", $timestamp) == date("Y", time() + $tz_offset)) {
			$format = get_pref($link, 'SHORT_DATE_FORMAT', $owner_uid);
			return date($format, $timestamp);
		} else {
			$format = get_pref($link, 'LONG_DATE_FORMAT', $owner_uid);
			return date($format, $timestamp);
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
	
	function bool_to_sql_bool($s) {
		if ($s) {
			return "true";
		} else {
			return "false";
		}
	}

	function toggleEvenOdd($a) {
		if ($a == "even") 
			return "odd";
		else
			return "even";
	}

	function get_schema_version($link, $nocache = false) {
		if (!$_SESSION["schema_version"] || $nocache) {
			$result = db_query($link, "SELECT schema_version FROM ttrss_version");
			$version = db_fetch_result($result, 0, "schema_version");
			$_SESSION["schema_version"] = $version;
			return $version;
		} else {
			return $_SESSION["schema_version"];
		}
	}

	function sanity_check($link) {

		$error_code = 0;
		$schema_version = get_schema_version($link);

		if ($schema_version != SCHEMA_VERSION) {
			$error_code = 5;
		}

		if (DB_TYPE == "mysql") {
			$result = db_query($link, "SELECT true", false);
			if (db_num_rows($result) != 1) {
				$error_code = 10;
			}
		}

		if (db_escape_string("testTEST") != "testTEST") {
			$error_code = 12;
		}

		if ($error_code != 0) {
			print_error_xml($error_code);
			return false;
		} else {
			return true;
		}
	}

	function file_is_locked($filename) {
		if (function_exists('flock')) {
			$fp = @fopen(LOCK_DIRECTORY . "/$filename", "r");
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
			if (function_exists('posix_getpid')) {
				fwrite($fp, posix_getpid() . "\n");
			}
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

	function sql_random_function() {
		if (DB_TYPE == "mysql") {
			return "RAND()";
		} else {
			return "RANDOM()";
		}
	}

	function catchup_feed($link, $feed, $cat_view, $owner_uid = false) {

			if (!$owner_uid) $owner_uid = $_SESSION['uid'];

			if (preg_match("/^-?[0-9][0-9]*$/", $feed) != false) {

				if ($cat_view) {

					if ($feed >= 0) {

						if ($feed > 0) {
							$cat_qpart = "cat_id = '$feed'";
						} else {
							$cat_qpart = "cat_id IS NULL";
						}
					
						$tmp_result = db_query($link, "SELECT id 
							FROM ttrss_feeds WHERE $cat_qpart AND owner_uid = $owner_uid");

						while ($tmp_line = db_fetch_assoc($tmp_result)) {

							$tmp_feed = $tmp_line["id"];

							db_query($link, "UPDATE ttrss_user_entries 
								SET unread = false,last_read = NOW() 
								WHERE feed_id = '$tmp_feed' AND owner_uid = $owner_uid");
						}
					} else if ($feed == -2) {


						db_query($link, "UPDATE ttrss_user_entries 
							SET unread = false,last_read = NOW() WHERE (SELECT COUNT(*) 
								FROM ttrss_user_labels2 WHERE article_id = ref_id) > 0 
							AND unread = true AND owner_uid = $owner_uid");
					}

				} else if ($feed > 0) {

					db_query($link, "UPDATE ttrss_user_entries 
							SET unread = false,last_read = NOW() 
							WHERE feed_id = '$feed' AND owner_uid = $owner_uid");
					
				} else if ($feed < 0 && $feed > -10) { // special, like starred

					if ($feed == -1) {
						db_query($link, "UPDATE ttrss_user_entries 
							SET unread = false,last_read = NOW()
							WHERE marked = true AND owner_uid = $owner_uid");
					}

					if ($feed == -2) {
						db_query($link, "UPDATE ttrss_user_entries 
							SET unread = false,last_read = NOW()
							WHERE published = true AND owner_uid = $owner_uid");
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
							owner_uid = $owner_uid");

						$affected_ids = array();

						while ($line = db_fetch_assoc($result)) {
							array_push($affected_ids, $line["id"]);
						}

						catchupArticlesById($link, $affected_ids, 0);
					}

					if ($feed == -4) {
						db_query($link, "UPDATE ttrss_user_entries 
							SET unread = false,last_read = NOW()
							WHERE owner_uid = $owner_uid");
					}

				} else if ($feed < -10) { // label

					$label_id = -$feed - 11;

					db_query($link, "UPDATE ttrss_user_entries, ttrss_user_labels2 
						SET unread = false, last_read = NOW() 
							WHERE label_id = '$label_id' AND unread = true
							AND owner_uid = '$owner_uid' AND ref_id = article_id");

				}

				ccache_update($link, $feed, $owner_uid, $cat_view);

			} else { // tag
				db_query($link, "BEGIN");

				$tag_name = db_escape_string($feed);

				$result = db_query($link, "SELECT post_int_id FROM ttrss_tags
					WHERE tag_name = '$tag_name' AND owner_uid = $owner_uid");

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
				
				$tmp_result = db_query($link, "SELECT id FROM ttrss_feeds
					WHERE $cat_qpart AND owner_uid = " . $_SESSION["uid"]);

				while ($tmp_line = db_fetch_assoc($tmp_result)) {					
					$feed_id = $tmp_line["id"];
					update_rss_feed($link, $feed_id, $force_update);
				}

			} else {
				update_rss_feed($link, $feed, $force_update);
			}
	}

	function getAllCounters($link, $omode = "flc", $active_feed = false) {

		if (!$omode) $omode = "flc";

		$data = getGlobalCounters($link);
		
		$data = array_merge($data, getVirtCounters($link));

		if (strchr($omode, "l")) $data = array_merge($data, getLabelCounters($link));
		if (strchr($omode, "f")) $data = array_merge($data, getFeedCounters($link, $active_feed));
		if (strchr($omode, "t")) $data = array_merge($data, getTagCounters($link));
		if (strchr($omode, "c")) {			
			if (get_pref($link, 'ENABLE_FEED_CATS')) {
				$data = array_merge($data, getCategoryCounters($link));
			}
		}

		return $data;
	}	

	function getCategoryCounters($link) {
		$ret_arr = array();

		/* Labels category */

		$cv = array("id" => -2, "kind" => "cat",
			"counter" => getCategoryUnread($link, -2));

		array_push($ret_arr, $cv);

		$age_qpart = getMaxAgeSubquery();

		$result = db_query($link, "SELECT id AS cat_id, value AS unread 
			FROM ttrss_feed_categories, ttrss_cat_counters_cache 
			WHERE ttrss_cat_counters_cache.feed_id = id AND 
			ttrss_feed_categories.owner_uid = " . $_SESSION["uid"]);

		while ($line = db_fetch_assoc($result)) {
			$line["cat_id"] = (int) $line["cat_id"];

			$cv = array("id" => $line["cat_id"], "kind" => "cat",
				"counter" => $line["unread"]);

			array_push($ret_arr, $cv);
		}

		/* Special case: NULL category doesn't actually exist in the DB */

		$cv = array("id" => 0, "kind" => "cat",
			"counter" => ccache_find($link, 0, $_SESSION["uid"], true));

		array_push($ret_arr, $cv);

		return $ret_arr;
	}

	function getCategoryUnread($link, $cat, $owner_uid = false) {

		if (!$owner_uid) $owner_uid = $_SESSION["uid"];

		if ($cat >= 0) {

			if ($cat != 0) {
				$cat_query = "cat_id = '$cat'";
			} else {
				$cat_query = "cat_id IS NULL";
			}

			$age_qpart = getMaxAgeSubquery();

			$result = db_query($link, "SELECT id FROM ttrss_feeds WHERE $cat_query 
					AND owner_uid = " . $owner_uid);
	
			$cat_feeds = array();
			while ($line = db_fetch_assoc($result)) {
				array_push($cat_feeds, "feed_id = " . $line["id"]);
			}
	
			if (count($cat_feeds) == 0) return 0;
	
			$match_part = implode(" OR ", $cat_feeds);
	
			$result = db_query($link, "SELECT COUNT(int_id) AS unread 
				FROM ttrss_user_entries,ttrss_entries 
				WHERE	unread = true AND ($match_part) AND id = ref_id 
				AND $age_qpart AND owner_uid = " . $owner_uid);
	
			$unread = 0;
	
			# this needs to be rewritten
			while ($line = db_fetch_assoc($result)) {
				$unread += $line["unread"];
			}
	
			return $unread;
		} else if ($cat == -1) {
			return getFeedUnread($link, -1) + getFeedUnread($link, -2) + getFeedUnread($link, -3) + getFeedUnread($link, 0);
		} else if ($cat == -2) {

			$result = db_query($link, "
				SELECT COUNT(unread) AS unread FROM 
					ttrss_user_entries, ttrss_labels2, ttrss_user_labels2, ttrss_feeds 
				WHERE label_id = ttrss_labels2.id AND article_id = ref_id AND 
					ttrss_labels2.owner_uid = '$owner_uid'
					AND unread = true AND feed_id = ttrss_feeds.id
					AND ttrss_user_entries.owner_uid = '$owner_uid'");

			$unread = db_fetch_result($result, 0, "unread");

			return $unread;

		} 
	}

	function getMaxAgeSubquery($days = COUNTERS_MAX_AGE) {
		if (DB_TYPE == "pgsql") {
			return "ttrss_entries.date_updated > 
				NOW() - INTERVAL '$days days'";
		} else {
			return "ttrss_entries.date_updated > 
				DATE_SUB(NOW(), INTERVAL $days DAY)";
		}
	}

	function getFeedUnread($link, $feed, $is_cat = false) {
		return getFeedArticles($link, $feed, $is_cat, true, $_SESSION["uid"]);
	}

	function getLabelUnread($link, $label_id, $owner_uid = false) {
		if (!$owner_uid) $owner_uid = $_SESSION["uid"];

		$result = db_query($link, "
			SELECT COUNT(unread) AS unread FROM 
				ttrss_user_entries, ttrss_labels2, ttrss_user_labels2, ttrss_feeds 
			WHERE label_id = ttrss_labels2.id AND article_id = ref_id AND 
				ttrss_labels2.owner_uid = '$owner_uid' AND ttrss_labels2.id = '$label_id'
				AND unread = true AND feed_id = ttrss_feeds.id
				AND ttrss_user_entries.owner_uid = '$owner_uid'");

		if (db_num_rows($result) != 0) {
			return db_fetch_result($result, 0, "unread");
		} else {
			return 0;
		}
	}

	function getFeedArticles($link, $feed, $is_cat = false, $unread_only = false,
		$owner_uid = false) {

		$n_feed = (int) $feed;

		if (!$owner_uid) $owner_uid = $_SESSION["uid"];

		if ($unread_only) {
			$unread_qpart = "unread = true";
		} else {
			$unread_qpart = "true";
		}

		$age_qpart = getMaxAgeSubquery();

		if ($is_cat) {
			return getCategoryUnread($link, $n_feed, $owner_uid);		
		} if ($feed != "0" && $n_feed == 0) {

			$feed = db_escape_string($feed);

			$result = db_query($link, "SELECT SUM((SELECT COUNT(int_id)
				FROM ttrss_user_entries,ttrss_entries WHERE int_id = post_int_id 
					AND ref_id = id AND $age_qpart
					AND $unread_qpart)) AS count FROM ttrss_tags 
				WHERE owner_uid = $owner_uid AND tag_name = '$feed'");
			return db_fetch_result($result, 0, "count");

		} else if ($n_feed == -1) {
			$match_part = "marked = true";
		} else if ($n_feed == -2) {
			$match_part = "published = true";
		} else if ($n_feed == -3) {
			$match_part = "unread = true";

			$intl = get_pref($link, "FRESH_ARTICLE_MAX_AGE", $owner_uid);

			if (DB_TYPE == "pgsql") {
				$match_part .= " AND updated > NOW() - INTERVAL '$intl hour' "; 
			} else {
				$match_part .= " AND updated > DATE_SUB(NOW(), INTERVAL $intl HOUR) ";
			}
		} else if ($n_feed == -4) {
			$match_part = "true";
		} else if ($n_feed >= 0) {

			if ($n_feed != 0) {
				$match_part = "feed_id = '$n_feed'";
			} else {
				$match_part = "feed_id IS NULL";
			}

		} else if ($feed < -10) {

			$label_id = -$feed - 11;

			return getLabelUnread($link, $label_id, $owner_uid);

		}

		if ($match_part) {

			if ($n_feed != 0) {
				$from_qpart = "ttrss_user_entries,ttrss_feeds,ttrss_entries";
				$feeds_qpart = "ttrss_user_entries.feed_id = ttrss_feeds.id AND";
			} else {
				$from_qpart = "ttrss_user_entries,ttrss_entries";
				$feeds_qpart = '';
			}

			$query = "SELECT count(int_id) AS unread 
				FROM $from_qpart WHERE
				ttrss_user_entries.ref_id = ttrss_entries.id AND 
				$age_qpart AND
				$feeds_qpart
				$unread_qpart AND ($match_part) AND ttrss_user_entries.owner_uid = $owner_uid";

			$result = db_query($link, $query);
				
		} else {
		
			$result = db_query($link, "SELECT COUNT(post_int_id) AS unread
				FROM ttrss_tags,ttrss_user_entries,ttrss_entries 
				WHERE tag_name = '$feed' AND post_int_id = int_id AND ref_id = ttrss_entries.id 
				AND $unread_qpart AND $age_qpart AND
					ttrss_tags.owner_uid = " . $owner_uid);
		}
		
		$unread = db_fetch_result($result, 0, "unread");

		return $unread;
	}

	function getGlobalUnread($link, $user_id = false) {

		if (!$user_id) {
			$user_id = $_SESSION["uid"];
		}

		$result = db_query($link, "SELECT SUM(value) AS c_id FROM ttrss_counters_cache
			WHERE owner_uid = '$user_id' AND feed_id > 0");

		$c_id = db_fetch_result($result, 0, "c_id"); 

		return $c_id;
	}

	function getGlobalCounters($link, $global_unread = -1) {
		$ret_arr = array();

		if ($global_unread == -1) {	
			$global_unread = getGlobalUnread($link);
		}

		$cv = array("id" => "global-unread", 
			"counter" => $global_unread);

		array_push($ret_arr, $cv);

		$result = db_query($link, "SELECT COUNT(id) AS fn FROM 
			ttrss_feeds WHERE owner_uid = " . $_SESSION["uid"]);

		$subscribed_feeds = db_fetch_result($result, 0, "fn");

		$cv = array("id" => "subscribed-feeds", 
			"counter" => $subscribed_feeds);

		array_push($ret_arr, $cv);

		return $ret_arr;
	}

	function getSubscribedFeeds($link) {
		$result = db_query($link, "SELECT COUNT(id) AS fn FROM 
			ttrss_feeds WHERE owner_uid = " . $_SESSION["uid"]);

		return db_fetch_result($result, 0, "fn");
	}

	function getTagCounters($link) {
		
		$ret_arr = array();

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

			$cv = array("id" => $tag,
				"kind" => "tag",
				"counter" => $unread);

			array_push($ret_arr, $cv);
		}

		return $ret_arr;	
	}

	function getVirtCounters($link) {

		$ret_arr = array();

		for ($i = 0; $i >= -4; $i--) {

			$count = getFeedUnread($link, $i);

			$cv = array("id" => $i,
				"counter" => $count);
	
			if (get_pref($link, 'EXTENDED_FEEDLIST'))
				$cv["xmsg"] = getFeedArticles($link, $i)." ".__("total");

			array_push($ret_arr, $cv);
		} 

		return $ret_arr;
	}

	function getLabelCounters($link, $descriptions = false) {

		$ret_arr = array();

		$age_qpart = getMaxAgeSubquery();

		$owner_uid = $_SESSION["uid"];

		$result = db_query($link, "SELECT id, caption FROM ttrss_labels2
			WHERE owner_uid = '$owner_uid'");
	
		while ($line = db_fetch_assoc($result)) {

			$id = -$line["id"] - 11;

			$label_name = $line["caption"];
			$count = getFeedUnread($link, $id);

			$cv = array("id" => $id,
				"counter" => $count);

			if ($descriptions)
				$cv["description"] = $label_name;

			if (get_pref($link, 'EXTENDED_FEEDLIST'))
				$cv["xmsg"] = getFeedArticles($link, $id)." ".__("total");

			array_push($ret_arr, $cv);
		}
	
		return $ret_arr;
	}

	function getFeedCounters($link, $active_feed = false) {

		$ret_arr = array();

		$age_qpart = getMaxAgeSubquery();

		$query = "SELECT ttrss_feeds.id,
				ttrss_feeds.title,
				".SUBSTRING_FOR_DATE."(ttrss_feeds.last_updated,1,19) AS last_updated, 
				last_error, value AS count
			FROM ttrss_feeds, ttrss_counters_cache
			WHERE ttrss_feeds.owner_uid = ".$_SESSION["uid"]."  
				AND ttrss_counters_cache.feed_id = id";

		$result = db_query($link, $query);
		$fctrs_modified = false;

		while ($line = db_fetch_assoc($result)) {
		
			$id = $line["id"];
			$count = $line["count"];
			$last_error = htmlspecialchars($line["last_error"]);

			$last_updated = make_local_datetime($link, $line['last_updated'], false);

			$has_img = feed_has_icon($id);

			$cv = array("id" => $id,
				"updated" => $last_updated,
				"counter" => $count,
				"has_img" => (int) $has_img);

			if ($last_error)
				$cv["error"] = $last_error;

			if (get_pref($link, 'EXTENDED_FEEDLIST'))
				$cv["xmsg"] = getFeedArticles($link, $id)." ".__("total");

			if ($active_feed && $id == $active_feed)
				$cv["title"] = truncate_string($line["title"], 30);

			array_push($ret_arr, $cv);

		}

		return $ret_arr;
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

	/**
	 * Subscribes the user to the given feed
	 *
	 * @param resource $link       Database connection
	 * @param string   $url        Feed URL to subscribe to
	 * @param integer  $cat_id     Category ID the feed shall be added to
	 * @param string   $auth_login (optional) Feed username
	 * @param string   $auth_pass  (optional) Feed password
	 *
	 * @return integer Status code:
	 *                 0 - OK, Feed already exists
	 *                 1 - OK, Feed added
	 *                 2 - Invalid URL
	 *                 3 - URL content is HTML, no feeds available
	 *                 4 - URL content is HTML which contains multiple feeds.
	 *                     Here you should call extractfeedurls in rpc-backend
	 *                     to get all possible feeds.
	 *                 5 - Couldn't download the URL content.
	 */
	function subscribe_to_feed($link, $url, $cat_id = 0, 
			$auth_login = '', $auth_pass = '') {

		$url = fix_url($url);
		if (!validate_feed_url($url)) return 2;
		if (!fetch_file_contents($url)) return 5;

		if (url_is_html($url)) {
			$feedUrls = get_feeds_from_html($url);
			if (count($feedUrls) == 0) {
				return 3;
			} else if (count($feedUrls) > 1) {
				return 4;
			}
			//use feed url as new URL
			$url = key($feedUrls);
		}

		if ($cat_id == "0" || !$cat_id) {
			$cat_qpart = "NULL";
		} else {
			$cat_qpart = "'$cat_id'";
		}
	
		$result = db_query($link,
			"SELECT id FROM ttrss_feeds 
			WHERE feed_url = '$url' AND owner_uid = ".$_SESSION["uid"]);
	
		if (db_num_rows($result) == 0) {
			$result = db_query($link,
				"INSERT INTO ttrss_feeds 
					(owner_uid,feed_url,title,cat_id, auth_login,auth_pass) 
				VALUES ('".$_SESSION["uid"]."', '$url', 
				'[Unknown]', $cat_qpart, '$auth_login', '$auth_pass')");
	
			$result = db_query($link,
				"SELECT id FROM ttrss_feeds WHERE feed_url = '$url' 
					AND owner_uid = " . $_SESSION["uid"]);
	
			$feed_id = db_fetch_result($result, 0, "id");
	
			if ($feed_id) {
				update_rss_feed($link, $feed_id, true);
			}

			return 1;
		} else {
			return 0;
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
				$is_selected = "selected=\"1\"";
			} else {
				$is_selected = "";
			}

			$title = truncate_string(htmlspecialchars($line["title"]), 40);

			printf("<option $is_selected value='%d'>%s</option>", 
				$line["id"], $title);
		}
	
		print "</select>";
	}

	function print_feed_cat_select($link, $id, $default_id = "", 
		$attributes = "", $include_all_cats = true) {
		
		print "<select id=\"$id\" name=\"$id\" default=\"$default_id\" onchange=\"catSelectOnChange(this)\" $attributes>";

		if ($include_all_cats) {
			print "<option value=\"0\">".__('Uncategorized')."</option>";
		}

		$result = db_query($link, "SELECT id,title FROM ttrss_feed_categories
			WHERE owner_uid = ".$_SESSION["uid"]." ORDER BY title");

		if (db_num_rows($result) > 0 && $include_all_cats) {
			print "<option disabled=\"1\">--------</option>";
		}

		while ($line = db_fetch_assoc($result)) {
			if ($line["id"] == $default_id) {
				$is_selected = "selected=\"1\"";
			} else {
				$is_selected = "";			
			}

			if ($line["title"])
				printf("<option $is_selected value='%d'>%s</option>", 
					$line["id"], htmlspecialchars($line["title"]));
		}

		print "<option value=\"ADD_CAT\">" .__("Add category...") . "</option>";

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

	function getFeedIcon($id) {
		switch ($id) {
		case 0:
			return "images/archive.png";
			break;
		case -1:
			return "images/mark_set.png";
			break;
		case -2:
			return "images/pub_set.png";
			break;
		case -3:
			return "images/fresh.png";
			break;
		case -4:
			return "images/tag.png";
			break;
		default:
			if ($id < -10) {
				return "images/label.png";
			} else {
				return ICONS_URL . "/$id.ico";
			}
			break;
		}
	}

	function getFeedTitle($link, $id) {
		if ($id == -1) {
			return __("Starred articles");
		} else if ($id == -2) {
			return __("Published articles");
		} else if ($id == -3) {
			return __("Fresh articles");
		} else if ($id == -4) {
			return __("All articles");
		} else if ($id === 0 || $id === "0") {
			return __("Archived articles");
		} else if ($id < -10) {
			$label_id = -$id - 11;
			$result = db_query($link, "SELECT caption FROM ttrss_labels2 WHERE id = '$label_id'");
			if (db_num_rows($result) == 1) {
				return db_fetch_result($result, 0, "caption");
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
			return $id;
		}
	}

	function get_session_cookie_name() {
		return ((!defined('TTRSS_SESSION_NAME')) ? "ttrss_sid" : TTRSS_SESSION_NAME);
	}

	function make_init_param($param, $value) {
		return array("param" => $param, "value" => $value);
	}

	function make_init_params($link) {
		$params = array();

		$params["theme"] = get_user_theme($link);
		$params["theme_options"] = get_user_theme_options($link);
		$params["daemon_enabled"] = ENABLE_UPDATE_DAEMON;

		$params["sign_progress"] = theme_image($link, "images/indicator_white.gif");
		$params["sign_progress_tiny"] = theme_image($link, "images/indicator_tiny.gif");
		$params["sign_excl"] = theme_image($link, "images/sign_excl.png");
		$params["sign_info"] = theme_image($link, "images/sign_info.png");

		foreach (array("ON_CATCHUP_SHOW_NEXT_FEED", "HIDE_READ_FEEDS",
			"ENABLE_FEED_CATS", "FEEDS_SORT_BY_UNREAD", "CONFIRM_FEED_CATCHUP",
			"CDM_AUTO_CATCHUP", "FRESH_ARTICLE_MAX_AGE", 
			"HIDE_READ_SHOWS_SPECIAL", "HIDE_FEEDLIST") as $param) {

				 $params[strtolower($param)] = (int) get_pref($link, $param);
		 }

		$params["icons_url"] = ICONS_URL;
		$params["cookie_lifetime"] = SESSION_COOKIE_LIFETIME;
		$params["default_view_mode"] = get_pref($link, "_DEFAULT_VIEW_MODE");
		$params["default_view_limit"] = (int) get_pref($link, "_DEFAULT_VIEW_LIMIT");
		$params["default_view_order_by"] = get_pref($link, "_DEFAULT_VIEW_ORDER_BY");
		$params["prefs_active_tab"] = get_pref($link, "_PREFS_ACTIVE_TAB");
		$params["infobox_disable_overlay"] = get_pref($link, "_INFOBOX_DISABLE_OVERLAY");
		$params["bw_limit"] = (int) $_SESSION["bw_limit"];
		$params["offline_enabled"] = (int) get_pref($link, "ENABLE_OFFLINE_READING");

		$result = db_query($link, "SELECT COUNT(*) AS cf FROM
			ttrss_feeds WHERE owner_uid = " . $_SESSION["uid"]);

		$num_feeds = db_fetch_result($result, 0, "cf");

		$params["num_feeds"] = (int) $num_feeds;
		$params["collapsed_feedlist"] = (int) get_pref($link, "_COLLAPSED_FEEDLIST");

		return $params;
	}

	function print_runtime_info($link) {
		print "<runtime-info><![CDATA[";
		print json_encode(make_runtime_info($link));
		print "]]></runtime-info>";
	}

	function make_runtime_info($link) {
		$result = db_query($link, "SELECT COUNT(*) AS cf FROM
			ttrss_feeds WHERE owner_uid = " . $_SESSION["uid"]);

		$num_feeds = db_fetch_result($result, 0, "cf");

		$data = array();

		$data['num_feeds'] = (int) $num_feeds;
		$data['last_article_id'] = getLastArticleId($link);
		$data['cdm_expanded'] = get_pref($link, 'CDM_EXPANDED');

		if (ENABLE_UPDATE_DAEMON) {

			$data['daemon_is_running'] = (int) file_is_locked("update_daemon.lock");

			if (time() - $_SESSION["daemon_stamp_check"] > 30) {

				$stamp = (int) @file_get_contents(LOCK_DIRECTORY . "/update_daemon.stamp");

				if ($stamp) {
					$stamp_delta = time() - $stamp;

					if ($stamp_delta > 1800) {
						$stamp_check = 0;
					} else {
						$stamp_check = 1;
						$_SESSION["daemon_stamp_check"] = time();
					}

					$data['daemon_stamp_ok'] = $stamp_check;

					$stamp_fmt = date("Y.m.d, G:i", $stamp);

					$data['daemon_stamp'] = $stamp_fmt;
				}
			}
		}

		if (CHECK_FOR_NEW_VERSION && $_SESSION["access_level"] >= 10) {
			
			if ($_SESSION["last_version_check"] + 86400 + rand(-1000, 1000) < time()) {
				$new_version_details = @check_for_update($link);

				$data['new_version_available'] = (int) $new_version_details != "";

				$_SESSION["last_version_check"] = time();
			}
		}

		return $data;
	}

	function getSearchSql($search, $match_on) {

		$search_query_part = "";

		$keywords = split(" ", $search);
		$query_keywords = array();

		if ($match_on == "both") {

			foreach ($keywords as $k) {
				if (strpos($k, "-") === 0) {
					$k = substr($k, 1);
					$not = "NOT";
				} else {
					$not = "";
				}

				array_push($query_keywords, "(UPPER(ttrss_entries.title) $not LIKE UPPER('%$k%')
					OR UPPER(ttrss_entries.content) $not LIKE UPPER('%$k%'))");
			}

			$search_query_part = implode("AND", $query_keywords) . " AND ";

		} else if ($match_on == "title") {

			foreach ($keywords as $k) {
				if (strpos($k, "-") === 0) {
					$k = substr($k, 1);
					$not = "NOT";
				} else {
					$not = "";
				}

				array_push($query_keywords, "(UPPER(ttrss_entries.title) $not LIKE UPPER('%$k%'))");
			}

			$search_query_part = implode("AND", $query_keywords) . " AND ";

		} else if ($match_on == "content") {

			foreach ($keywords as $k) {
				if (strpos($k, "-") === 0) {
					$k = substr($k, 1);
					$not = "NOT";
				} else {
					$not = "";
				}

				array_push($query_keywords, "(UPPER(ttrss_entries.content) $not LIKE UPPER('%$k%'))");
			}
		}

		$search_query_part = implode("AND", $query_keywords);

		return $search_query_part;
	}

	function queryFeedHeadlines($link, $feed, $limit, $view_mode, $cat_view, $search, $search_mode, $match_on, $override_order = false, $offset = 0, $owner_uid = 0) {

		if (!$owner_uid) $owner_uid = $_SESSION["uid"];

		$ext_tables_part = "";

			if ($search) {

				if (SPHINX_ENABLED) {
					$ids = join(",", @sphinx_search($search, 0, 500));

					if ($ids) 
						$search_query_part = "ref_id IN ($ids) AND ";
					else
						$search_query_part = "ref_id = -1 AND ";

				} else {
					$search_query_part = getSearchSql($search, $match_on);
					$search_query_part .= " AND ";
				}				

			} else {
				$search_query_part = "";
			}

			$view_query_part = "";
	
			if ($view_mode == "adaptive" || $view_query_part == "noscores") {
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

			if ($view_mode == "published") {
				$view_query_part = " published = true AND ";
			}

			if ($view_mode == "unread") {
				$view_query_part = " unread = true AND ";
			}

			if ($view_mode == "updated") {
				$view_query_part = " (last_read is null and unread = false) AND ";
			}

			if ($limit > 0) {
				$limit_query_part = "LIMIT " . $limit;
			} 

			$vfeed_query_part = "";
	
			// override query strategy and enable feed display when searching globally
			if ($search && $search_mode == "all_feeds") {
				$query_strategy_part = "ttrss_entries.id > 0";
				$vfeed_query_part = "ttrss_feeds.title AS feed_title,";		
			/* tags */
			} else if (preg_match("/^-?[0-9][0-9]*$/", $feed) == false) {
				$query_strategy_part = "ttrss_entries.id > 0";
				$vfeed_query_part = "(SELECT title FROM ttrss_feeds WHERE
					id = feed_id) as feed_title,";
			} else if ($feed > 0 && $search && $search_mode == "this_cat") {
	
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
				
			} else if ($feed > 0) {
	
				if ($cat_view) {

					if ($feed > 0) {
						$query_strategy_part = "cat_id = '$feed'";
					} else {
						$query_strategy_part = "cat_id IS NULL";
					}
	
					$vfeed_query_part = "ttrss_feeds.title AS feed_title,";

				} else {		
					$query_strategy_part = "feed_id = '$feed'";
				}
			} else if ($feed == 0 && !$cat_view) { // archive virtual feed
				$query_strategy_part = "feed_id IS NULL";
			} else if ($feed == 0 && $cat_view) { // uncategorized
				$query_strategy_part = "cat_id IS NULL";
				$vfeed_query_part = "ttrss_feeds.title AS feed_title,";
			} else if ($feed == -1) { // starred virtual feed
				$query_strategy_part = "marked = true";
				$vfeed_query_part = "ttrss_feeds.title AS feed_title,";
			} else if ($feed == -2) { // published virtual feed OR labels category

				if (!$cat_view) {
					$query_strategy_part = "published = true";
					$vfeed_query_part = "ttrss_feeds.title AS feed_title,";
				} else {
					$vfeed_query_part = "ttrss_feeds.title AS feed_title,";

					$ext_tables_part = ",ttrss_labels2,ttrss_user_labels2";
	
					$query_strategy_part = "ttrss_labels2.id = ttrss_user_labels2.label_id AND
						ttrss_user_labels2.article_id = ref_id";

				}

			} else if ($feed == -3) { // fresh virtual feed
				$query_strategy_part = "unread = true";

				$intl = get_pref($link, "FRESH_ARTICLE_MAX_AGE", $owner_uid);

				if (DB_TYPE == "pgsql") {
					$query_strategy_part .= " AND updated > NOW() - INTERVAL '$intl hour' "; 
				} else {
					$query_strategy_part .= " AND updated > DATE_SUB(NOW(), INTERVAL $intl HOUR) ";
				}

				$vfeed_query_part = "ttrss_feeds.title AS feed_title,";
			} else if ($feed == -4) { // all articles virtual feed
				$query_strategy_part = "true";
				$vfeed_query_part = "ttrss_feeds.title AS feed_title,";
			} else if ($feed <= -10) { // labels
				$label_id = -$feed - 11;

				$query_strategy_part = "label_id = '$label_id' AND
					ttrss_labels2.id = ttrss_user_labels2.label_id AND
					ttrss_user_labels2.article_id = ref_id";

				$vfeed_query_part = "ttrss_feeds.title AS feed_title,";
				$ext_tables_part = ",ttrss_labels2,ttrss_user_labels2";
 
			} else {
				$query_strategy_part = "id > 0"; // dumb
			}

			if (get_pref($link, "SORT_HEADLINES_BY_FEED_DATE", $owner_uid)) {
				$date_sort_field = "updated";
			} else {
				$date_sort_field = "date_entered";
			}

			if (get_pref($link, 'REVERSE_HEADLINES', $owner_uid)) {
				$order_by = "$date_sort_field";
			} else {	
				$order_by = "$date_sort_field DESC";
			}

			if ($view_mode != "noscores") {
				$order_by = "score DESC, $order_by";
			}

			if ($override_order) {
				$order_by = $override_order;
			}
	
			$feed_title = "";

			if ($search) {
				$feed_title = "Search results";
			} else {
				if ($cat_view) {
					$feed_title = getCategoryTitle($link, $feed);
				} else {
					if ((int)$feed == $feed && $feed > 0) {
						$result = db_query($link, "SELECT title,site_url,last_error 
							FROM ttrss_feeds WHERE id = '$feed' AND owner_uid = $owner_uid");
		
						$feed_title = db_fetch_result($result, 0, "title");
						$feed_site_url = db_fetch_result($result, 0, "site_url");
						$last_error = db_fetch_result($result, 0, "last_error");
					} else {
						$feed_title = getFeedTitle($link, $feed);
					} 
				}
			}

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
				}

				if ($feed != "0") {
					$from_qpart = "ttrss_entries,ttrss_user_entries,ttrss_feeds$ext_tables_part";
					$feed_check_qpart = "ttrss_user_entries.feed_id = ttrss_feeds.id AND";

				} else {
					$from_qpart = "ttrss_entries,ttrss_user_entries$ext_tables_part
						LEFT JOIN ttrss_feeds ON (feed_id = ttrss_feeds.id)";
				}

				$query = "SELECT DISTINCT 
						date_entered,
						guid,
						ttrss_entries.id,ttrss_entries.title,
						updated,
						note,
						unread,feed_id,marked,published,link,last_read,orig_feed_id,
						".SUBSTRING_FOR_DATE."(last_read,1,19) as last_read_noms,
						$vfeed_query_part
						$content_query_part
						".SUBSTRING_FOR_DATE."(updated,1,19) as updated_noms,
						author,score
					FROM
						$from_qpart
					WHERE
					$feed_check_qpart
					ttrss_user_entries.ref_id = ttrss_entries.id AND
					ttrss_user_entries.owner_uid = '$owner_uid' AND
					$search_query_part
					$view_query_part
					$query_strategy_part ORDER BY $order_by
					$limit_query_part $offset_query_part";

				if ($_REQUEST["debug"]) print $query;

				$result = db_query($link, $query);
	
			} else {
				// browsing by tag
	
				$feed_kind = "Tags";
	
				$result = db_query($link, "SELECT
					guid,
					note,
					ttrss_entries.id as id,title,
					updated,
					unread,feed_id,orig_feed_id,
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
		$limit, $search, $search_mode, $match_on, $view_mode = false) {

		$note_style = 	"float : right; background-color : #fff7d5; border-width : 1px; ".
			"padding : 5px; border-style : dashed; border-color : #e7d796;".
			"margin-bottom : 1em; color : #9a8c59;";

		if (!$limit) $limit = 30;

		if (get_pref($link, "SORT_HEADLINES_BY_FEED_DATE", $owner_uid)) {
			$date_sort_field = "updated";
		} else {
			$date_sort_field = "date_entered";
		}

		$qfh_ret = queryFeedHeadlines($link, $feed, 
			$limit, $view_mode, $is_cat, $search, $search_mode, 
			$match_on, "$date_sort_field DESC", 0, $owner_uid);

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

			if ($line["author"]) {
				print "<author>" . htmlspecialchars($line["author"]) . "</author>";
			}
 
 			print "<title><![CDATA[" . 
 				htmlspecialchars($line["title"]) . "]]></title>";
  
			print "<description><![CDATA[";

			if ($line["note"]) {
				print "<div style='$note_style'>";
				print $line["note"];
				print "</div>";
			}

			print sanitize_rss($link, $line["content_preview"], false, $owner_uid);
			print "]]></description>";
	
			$enclosures = get_article_enclosures($link, $line["id"]);

			foreach ($enclosures as $e) {
				$type = htmlspecialchars($e['content_type']);
				$url = htmlspecialchars($e['content_url']);
				$length = $e['duration'];
				print "<enclosure url=\"$url\" type=\"$type\" length=\"$length\"/>";
			}

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

	function strip_tags_long($string, $allowed) {

		$config = HTMLPurifier_Config::createDefault();

		$config->set('HTML', 'Allowed', $allowed);
		$purifier = new HTMLPurifier($config);

		return $purifier->purify($string);		

	}

	// http://ru2.php.net/strip-tags

/*	function strip_tags_long($textstring, $allowed){
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
} */


	function sanitize_rss($link, $str, $force_strip_tags = false, $owner = false, $site_url = false) {

		if (!$owner) $owner = $_SESSION["uid"];

		$res = trim($str); if (!$res) return '';

		if (get_pref($link, "STRIP_UNSAFE_TAGS", $owner) || $force_strip_tags) {

//			$res = strip_tags_long($res, 
//				"<p><a><i><em><b><strong><code><pre><blockquote><br><img><ul><ol><li>");

			$res = strip_tags_long($res, 
				"p,a[href],i,em,b,strong,code,pre,blockquote,br,img[src|alt|title],ul,ol,li,h1,h2,h3,h4");

		}

		if (get_pref($link, "STRIP_IMAGES", $owner)) {
			$res = preg_replace('/<img[^>]+>/is', '', $res);
		}

		$charset_hack = '<head>
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
		</head>';

		$res = trim($res); if (!$res) return '';

		libxml_use_internal_errors(true);

		$doc = new DOMDocument();
		$doc->loadHTML($charset_hack . $res);
		$xpath = new DOMXPath($doc);
	
		$entries = $xpath->query('(//a[@href]|//img[@src])');

		foreach ($entries as $entry) {

			if ($site_url) {

				if ($entry->hasAttribute('href'))
					$entry->setAttribute('href',
						rewrite_relative_url($site_url, $entry->getAttribute('href')));
		
				if ($entry->hasAttribute('src'))
					$entry->setAttribute('src',
						rewrite_relative_url($site_url, $entry->getAttribute('src')));
			}

			if (strtolower($entry->nodeName) == "a") {
				if (get_pref($link, 'OPEN_LINKS_IN_NEW_WINDOW', $owner)) {
					$entry->setAttribute("target", "_blank");
				}
			}

			if (strtolower($entry->nodeName) == "img") {
				$br = $doc->createElement("br");

				if ($entry->parentNode->nextSibling)
					$entry->parentNode->insertBefore($br, $entry->nextSibling);

			}
		}
	
		$node = $doc->getElementsByTagName('body')->item(0);

		return $doc->saveXML($node); 
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

					$mail->PluginDir = "lib/phpmailer/";
					$mail->SetLanguage("en", "lib/phpmailer/language/");

					$mail->CharSet = "UTF-8";

					$mail->From = DIGEST_FROM_ADDRESS;
					$mail->FromName = DIGEST_FROM_NAME;
					$mail->AddAddress($line["email"], $line["login"]);

					if (DIGEST_SMTP_HOST) {
						$mail->Host = DIGEST_SMTP_HOST;
						$mail->Mailer = "smtp";
						$mail->SMTPAuth = DIGEST_SMTP_LOGIN != '';
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
				} else {
					print "No headlines\n";
				}

				db_query($link, "UPDATE ttrss_users SET last_digest_sent = NOW() 
					WHERE id = " . $line["id"]);
			}
		}

		print "All done.\n";

	}

	function prepare_headlines_digest($link, $user_id, $days = 1, $limit = 100) {

		require_once "lib/MiniTemplator.class.php";

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
			$interval_query = "ttrss_entries.date_updated > NOW() - INTERVAL '$days days'";
		} else if (DB_TYPE == "mysql") {
			$interval_query = "ttrss_entries.date_updated > DATE_SUB(NOW(), INTERVAL $days DAY)";
		}

		$result = db_query($link, "SELECT ttrss_entries.title,
				ttrss_feeds.title AS feed_title,
				date_updated,
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
				AND ttrss_user_entries.owner_uid = $user_id
				AND unread = true 
			ORDER BY ttrss_feeds.title, date_updated DESC
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

			$updated = make_local_datetime($link, $line['last_updated'], false,
				$user_id);

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

	function check_for_update($link) {
		$releases_feed = "http://tt-rss.org/releases.rss";

		if (!CHECK_FOR_NEW_VERSION || $_SESSION["access_level"] < 10) {
			return;
		}

		if (DEFAULT_UPDATE_METHOD == "1") {
			$rss = new SimplePie();
			$rss->set_useragent(SIMPLEPIE_USERAGENT . MAGPIE_USER_AGENT_EXT);
			$rss->set_feed_url($fetch_url);
			$rss->set_output_encoding('UTF-8');
			$rss->init();
		} else {
			$rss = fetch_rss($releases_feed);
		}

		if ($rss) {

			if (DEFAULT_UPDATE_METHOD == "1") {
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

			if (DEFAULT_UPDATE_METHOD == "1") {
				$last_title = $latest_item->get_title();
			} else {
				$last_title = $latest_item["title"];
			}

			$latest_version = trim(preg_replace("/(Milestone)|(completed)/", "", $last_title));

			if (DEFAULT_UPDATE_METHOD == "1") {
				$release_url = sanitize_rss($link, $latest_item->get_link());
				$content = sanitize_rss($link, $latest_item->get_description());
			} else {
				$release_url = sanitize_rss($link, $latest_item["link"]);
				$content = sanitize_rss($link, $latest_item["description"]);
			}

			if (version_compare(VERSION, $latest_version) == -1) {
				return sprintf("New version of Tiny-Tiny RSS (%s) is available:", 
					$latest_version)."<div class='milestoneDetails'>$content</div>";
			} else {
				return false;
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
		if (count($ids) == 0) return;

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

		/* update ccache */

		$result = db_query($link, "SELECT DISTINCT feed_id FROM ttrss_user_entries
			WHERE ($ids_qpart) AND owner_uid = $owner_uid");

		while ($line = db_fetch_assoc($result)) {
			ccache_update($link, $line["feed_id"], $owner_uid);
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

		$feed_id = getArticleFeed($link, $id);
		ccache_update($link, $feed_id, $_SESSION["uid"]);
	}

	function make_guid_from_title($title) {
		return preg_replace("/[ \"\',.:;]/", "-", 
			mb_strtolower(strip_tags($title), 'utf-8'));
	}

	function print_headline_subtoolbar($link, $feed_site_url, $feed_title, 
			$feed_id, $is_cat, $search, $match_on,
			$search_mode, $view_mode) {

			print "<div class=\"headlinesSubToolbar\">";

			$page_prev_link = "javascript:viewFeedGoPage(-1)";
			$page_next_link = "javascript:viewFeedGoPage(1)";
			$page_first_link = "javascript:viewFeedGoPage(0)";

			$catchup_page_link = "javascript:catchupPage()";
			$catchup_feed_link = "javascript:catchupCurrentFeed()";
			$catchup_sel_link = "javascript:catchupSelection()";

			$archive_sel_link = "javascript:archiveSelection()";
			$delete_sel_link = "javascript:deleteSelection()";

			$sel_all_link = "javascript:selectArticles('all')";
			$sel_unread_link = "javascript:selectArticles('unread')";
			$sel_none_link = "javascript:selectArticles('none')";
			$sel_inv_link = "javascript:selectArticles('invert')";

			$tog_unread_link = "javascript:selectionToggleUnread()";
			$tog_marked_link = "javascript:selectionToggleMarked()";
			$tog_published_link = "javascript:selectionTogglePublished()";

			print "<div id=\"subtoolbar_ftitle\">";

			if ($feed_site_url) {
				$target = "target=\"_blank\"";
				print "<a title=\"".__("Visit the website")."\" $target href=\"$feed_site_url\">".
					truncate_string($feed_title,30)."</a>";
			} else {
				if ($feed_id < -10) {
					$label_id = -11-$feed_id;

					$result = db_query($link, "SELECT fg_color, bg_color
						FROM ttrss_labels2 WHERE id = '$label_id' AND owner_uid = " .
						$_SESSION["uid"]);

					if (db_num_rows($result) != 0) {
						$fg_color = db_fetch_result($result, 0, "fg_color");
						$bg_color = db_fetch_result($result, 0, "bg_color");

						print "<span style='background : $bg_color; color : $fg_color'>";
						print $feed_title;
						print "</span>";
					} else {
						print $feed_title;
					}

				} else {
					print $feed_title;
				}
			}

			if ($search) {
				$search_q = "&q=$search&m=$match_on&smode=$search_mode";
			} else {
				$search_q = "";
			}

			// Adaptive doesn't really make any sense for generated feeds
			// All Articles is the default, so no need to insert it either
			if ($view_mode == "adaptive" || $view_mode == "all_articles") 
				$view_mode = "";
			else
				$view_mode = "&view-mode=$view_mode";

			$rss_link = htmlspecialchars(get_self_url_prefix() . 
				"/backend.php?op=rss&id=$feed_id&is_cat=$is_cat$view_mode$search_q");

			#print "
			#	<a target=\"_blank\"
			#		title=\"".__("View as RSS feed")."\"
			#		href=\"$rss_link\">
			#		<img class=\"noborder\" src=\"images/feed-icon-12x12.png\"></a>";

			print "
				<a href=\"#\"
					title=\"".__("View as RSS feed")."\"
					onclick=\"displayDlg('generatedFeed', '$feed_id:$is_cat:$rss_link')\">
					<img class=\"noborder\" style=\"vertical-align : middle\" src=\"images/feed-icon-12x12.png\"></a>";

			print "</div>";

			print __('Select:')."
				<a href=\"#\" onclick=\"$sel_all_link\">".__('All')."</a>,
				<a href=\"#\" onclick=\"$sel_unread_link\">".__('Unread')."</a>,
				<a href=\"#\" onclick=\"$sel_inv_link\">".__('Invert')."</a>,
				<a href=\"#\" onclick=\"$sel_none_link\">".__('None')."</a></li>";

			print "&nbsp;&nbsp;";

			print "<span 
				onmouseover=\"enable_selection(false)\" 
            onmouseout=\"enable_selection(true)\"
				onclick=\"toggleHeadlineActions()\" id=\"headlineActionsDrop\">".
				__("Actions...") . "&nbsp;&nbsp;<img width='11' height'7' 
				src=\"images/down_arrow.png\">
				</span>";

			print "<ul id=\"headlineActionsBody\" style=\"display : none\">";

			print "<li class=\"insensitive\">".__('Selection toggle:')."</li>
				<li onclick=\"$tog_unread_link\">&nbsp;&nbsp;".__('Unread')."</li>
				<li onclick=\"$tog_marked_link\">&nbsp;&nbsp;".__('Starred')."</li>
				<li onclick=\"$tog_published_link\">&nbsp;&nbsp;".__('Published')."</li>
				<li class=\"insensitive\">".__('Selection:')."</li>
				<li onclick=\"$catchup_sel_link\">&nbsp;&nbsp;".__('Mark as read')."</li>";

//			print "<li onclick=\"$catchup_feed_link\">&nbsp;&nbsp;".__('Entire feed').
//				"</li>";

			if ($feed_id != "0") {
				print "<li onclick=\"$archive_sel_link\">&nbsp;&nbsp;".__('Archive')."</li>";
			} else {
				print "<li onclick=\"$archive_sel_link\">&nbsp;&nbsp;".__('Move back')."</li>";
				print "<li onclick=\"$delete_sel_link\">&nbsp;&nbsp;".__('Delete')."</li>";

			} 

			print "<li onclick=\"emailArticle(false)\">&nbsp;&nbsp;".
				__('Forward by email')."</li>";

			//print "<li><span class=\"insensitive\">--------</span></li>";
			print "<li class=\"insensitive\">".__('Assign label:')."</li>";

			print_labels_headlines_dropdown($link, $feed_id);

			print "<li class=\"insensitive\">".__('Feed:')."</li>";
			print "<li onclick=\"displayDlg('generatedFeed', '$feed_id:$is_cat:$rss_link')\">&nbsp;&nbsp;".__('View as RSS')."</li>";

			print "</ul>";

			print "</div>";
		}

	function printCategoryHeader($link, $cat_id, $hidden = false, $can_browse = true, 
					$title_override = false) {

			if (!$title_override)
				$tmp_category = getCategoryTitle($link, $cat_id);
			else
				$tmp_category = $title_override;

			if ($cat_id > 0) {
				$cat_unread = ccache_find($link, $cat_id, $_SESSION["uid"], true);
			} else if ($cat_id == 0 || $cat_id == -2) {
				$cat_unread = getCategoryUnread($link, $cat_id);
			}

			if ($hidden) {
				$holder_style = "display:none;";
				$ellipsis = "…";
				$collapse_pic = "cat-uncollapse.png";
			} else {
				$holder_style = "";
				$ellipsis = "";
				$collapse_pic = "cat-collapse.png";
			}

			$catctr_class = ($cat_unread > 0) ? "catCtrHasUnread" : "catCtrNoUnread";

			if ($can_browse) {
				$browse_cat_link = "onclick=\"javascript:viewCategory($cat_id)\"";
				$inner_title_class = "catTitle";
			} else {
				$browse_cat_link = "";
				$inner_title_class = "catTitleNL";
			}

			$cat_class = "feedCat";

			print "<li class=\"$cat_class\" id=\"FCAT-$cat_id\">
				<img onclick=\"toggleCollapseCat($cat_id)\" class=\"catCollapse\"
					title=\"".__('Click to collapse category')."\"
					src=\"images/$collapse_pic?\"><span class=\"$inner_title_class\" 
					id=\"FCATN-$cat_id\" $browse_cat_link/>$tmp_category</span>";

			print "<span id=\"FCAP-$cat_id\">";

			print " <span id=\"FCATCTR-$cat_id\" 
				class=\"$catctr_class\">($cat_unread)</span> $ellipsis";

			print "</span>";

			//print "</li>";

			print "<ul class=\"feedCatList\" id=\"FCATLIST-$cat_id\" style='$holder_style'>";

	}
	
	function outputFeedList($link, $tags = false) {

		print "<ul class=\"feedList\" id=\"feedList\">";

		$owner_uid = $_SESSION["uid"];

		/* virtual feeds */

		if (get_pref($link, 'ENABLE_FEED_CATS')) {

			$cat_hidden = get_pref($link, "_COLLAPSED_SPECIAL");

			printCategoryHeader($link, -1, $cat_hidden, false);
		}

		foreach (array(-4, -3, -1, -2, 0) as $i) {
			printFeedEntry($i, "virt", false, false, 
				false, $link);
		}

		if (get_pref($link, 'ENABLE_FEED_CATS')) {
			print "</ul></li>";
		}

		if (!$tags) {


				$result = db_query($link, "SELECT * FROM
					ttrss_labels2 WHERE owner_uid = '$owner_uid' ORDER by caption");
		
				if (db_num_rows($result) > 0) {
					if (get_pref($link, 'ENABLE_FEED_CATS')) {

						$cat_hidden = get_pref($link, "_COLLAPSED_LABELS");

						printCategoryHeader($link, -2, $cat_hidden, true);

					} else {
						print "<li><hr></li>";
					}
				}
		
				while ($line = db_fetch_assoc($result)) {
	
					$label_id = -$line['id'] - 11;
					$count = getFeedUnread($link, $label_id);
	
					printFeedEntry($label_id, 
						"label", $line["caption"], 
						$count, false, $link, 
						false, false, false,
						$line['fg_color'], $line['bg_color']);
		
				}

				if (db_num_rows($result) > 0) {
					if (get_pref($link, 'ENABLE_FEED_CATS')) {
						print "</ul>";
					}
				} 


			if (!get_pref($link, 'ENABLE_FEED_CATS')) {
				print "<li><hr></li>";
			}

			if (get_pref($link, 'ENABLE_FEED_CATS')) {
				if (get_pref($link, "FEEDS_SORT_BY_UNREAD")) {
					$order_by_qpart = "order_id,category,unread DESC,title";
				} else {
					$order_by_qpart = "order_id,category,title";
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
				cat_id,last_error,
				ttrss_feed_categories.title AS category,
				ttrss_feed_categories.collapsed,
				value AS unread	
				FROM ttrss_feeds LEFT JOIN ttrss_feed_categories
					ON (ttrss_feed_categories.id = cat_id)			
				LEFT JOIN ttrss_counters_cache 
					ON
						(ttrss_feeds.id = feed_id)
				WHERE 
					ttrss_feeds.owner_uid = '$owner_uid'
				ORDER BY $order_by_qpart"; 

			$result = db_query($link, $query);

			$actid = $_REQUEST["actid"];
	
			/* real feeds */
	
			$lnum = 0;
	
			$total_unread = 0;

			$category = "";
	
			while ($line = db_fetch_assoc($result)) {
			
				$feed = htmlspecialchars(trim($line["title"]));

				if (!$feed) $feed = "[Untitled]";

				$feed_id = $line["id"];	  
				$unread = $line["unread"];
	
				$subop = $_REQUEST["subop"];

				$last_updated = make_local_datetime($link, $line['last_updated_noms'], 
					false);

				$rtl_content = sql_bool_to_bool($line["rtl_content"]);

				if ($rtl_content) {
					$rtl_tag = "dir=\"RTL\"";
				} else {
					$rtl_tag = "";
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
	
				if ($actid == $feed_id) {
					$class .= "Selected";
				}
	
				$total_unread += $unread;

				if ($category != $tmp_category && get_pref($link, 'ENABLE_FEED_CATS')) {
				
					if ($category) {
						print "</ul></li>";
					}
				
					$category = $tmp_category;

					$collapsed = sql_bool_to_bool($line["collapsed"]);

					// workaround for NULL category
					if ($category == __("Uncategorized")) {
						$collapsed = get_pref($link, "_COLLAPSED_UNCAT");
					}

					$cat_id = (int) $cat_id;

					printCategoryHeader($link, $cat_id, $collapsed, true);

				}
	
				printFeedEntry($feed_id, $class, $feed, $unread, 
					false, $link, $rtl_content, 
					$last_updated, $line["last_error"]);
	
				++$lnum;
			}

			if (db_num_rows($result) == 0) {

				if (!get_pref($link, 'ENABLE_FEED_CATS')) {
					print "<li style='text-align : center'><a href=\"#\"
					  onclick=\"quickAddFeed()\">".
						__('Subscribe to feed...')."</a></li>";
				} else {
					printCategoryHeader($link, -1, false, false, "Feeds");

					print "<li><a href=\"#\"
					  onclick=\"quickAddFeed()\">".
						__('Subscribe to feed...')."</a></li>";

					print "</ul>";
				}
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
				print "<ul class=\"feedCatList\">";
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

		global $memcache;

		$a_id = db_escape_string($id);

		if (!$owner_uid) $owner_uid = $_SESSION["uid"];

		$query = "SELECT DISTINCT tag_name, 
			owner_uid as owner FROM
			ttrss_tags WHERE post_int_id = (SELECT int_id FROM ttrss_user_entries WHERE
			ref_id = '$a_id' AND owner_uid = '$owner_uid' LIMIT 1) ORDER BY tag_name";

		$obj_id = md5("TAGS:$owner_uid:$id");
		$tags = array();	

		if ($memcache && $obj = $memcache->get($obj_id)) {
			$tags = $obj;
		} else {
			/* check cache first */

			$result = db_query($link, "SELECT tag_cache FROM ttrss_user_entries
				WHERE ref_id = '$id' AND owner_uid = " . $_SESSION["uid"]);

			$tag_cache = db_fetch_result($result, 0, "tag_cache");

			if ($tag_cache) {
				$tags = explode(",", $tag_cache);
			} else {

				/* do it the hard way */

				$tmp_result = db_query($link, $query);

				while ($tmp_line = db_fetch_assoc($tmp_result)) {
					array_push($tags, $tmp_line["tag_name"]);				
				}

				/* update the cache */

				$tags_str = db_escape_string(join(",", $tags));

				db_query($link, "UPDATE ttrss_user_entries 
					SET tag_cache = '$tags_str' WHERE ref_id = '$id'
					AND owner_uid = " . $_SESSION["uid"]);
			}

			if ($memcache) $memcache->add($obj_id, $tags, 0, 3600);
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
		if (mb_strlen($tag) > 250) return false;

		if (function_exists('iconv')) {
			$tag = iconv("utf-8", "utf-8", $tag);
		}

		if (!$tag) return false;

		return true;
	}

	function render_login_form($link, $mobile = 0) {
		switch ($mobile) {
		case 0:
			require_once "login_form.php";
			break;
		case 1:
			require_once "mobile/login_form.php";
			break;
		case 2:
			require_once "mobile/classic/login_form.php";
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
		global $link;
		return "<div class=\"warning\" id=\"$id\"> 
			<img src=\"".theme_image($link, "images/sign_excl.png")."\">$msg</div>";
	}

	function format_notice($msg) {
		global $link;
		return "<div class=\"notice\" id=\"$id\"> 
			<img src=\"".theme_image($link, "images/sign_info.png")."\">$msg</div>";
	}

	function format_error($msg) {
		global $link;
		return "<div class=\"error\" id=\"$id\"> 
			<img src=\"".theme_image($link, "images/sign_excl.png")."\">$msg</div>";
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

	function format_inline_player($link, $url, $ctype) {

		$entry = "";

		if (($ctype == __("audio/mpeg")) &&  (get_pref($link, "ENABLE_FLASH_PLAYER")) ) {  
                     
			$entry .= "<object type=\"application/x-shockwave-flash\" 
				data=\"extras/button/musicplayer.swf?song_url=$url\" 
				width=\"17\" height=\"17\"> 
					<param name=\"movie\" value=\"extras/button/musicplayer.swf?song_url=$url\" /> </object>";  
		}

		/*

		if (substr($ctype,0,6)=="audio/" || $ctype=="application/ogg" || $ctype=="application/x-ogg") {
			$entry .= "<audio controls=\"controls\"><source src=\"$url\" type=\"$ctype\" />";
			if (($ctype == __("audio/mpeg")) && 
				(get_pref($link, "ENABLE_FLASH_PLAYER")) ) { 
				$entry .= "<span><object type=\"application/x-shockwave-flash\" data=\"extras/button/musicplayer.swf?song_url=$url\" width=\"17\" height=\"17\"> <param name=\"movie\" value=\"extras/button/musicplayer.swf?song_url=$url\" /> </object></span>";
			}
			$entry .= "</audio> ";
			if (($ctype == __("audio/mpeg")) && 
				(get_pref($link, "ENABLE_FLASH_PLAYER")) ) {
				$entry .= "<a id='switchToFlashLink' href='#' onclick='return switchToFlash(this)'>".__('Switch to Flash Player')."</a>";	
				$entry .= "<script type='text/javascript'>html5AudioOrFlash('$ctype');</script>"; 
			}
		} elseif (substr($ctype,0,6)=="video/") {
			$entry .= "<video controls=\"controls\"><source src=\"$url\" type=\"$ctype\" />";
			$entry .= "</video>";
		} */



		return $entry;
	}

	function outputArticleXML($link, $id, $feed_id, $mark_as_read = true,
		$zoom_mode = false) {

		/* we can figure out feed_id from article id anyway, why do we
		 * pass feed_id here? let's ignore the argument :( */

		$result = db_query($link, "SELECT feed_id FROM ttrss_user_entries
			WHERE ref_id = '$id'");

		$feed_id = (int) db_fetch_result($result, 0, "feed_id");

		if (!$zoom_mode) { print "<article id='$id'><![CDATA["; };

		$result = db_query($link, "SELECT rtl_content, always_display_enclosures FROM ttrss_feeds
			WHERE id = '$feed_id' AND owner_uid = " . $_SESSION["uid"]);

		if (db_num_rows($result) == 1) {
			$rtl_content = sql_bool_to_bool(db_fetch_result($result, 0, "rtl_content"));
			$always_display_enclosures = sql_bool_to_bool(db_fetch_result($result, 0, "always_display_enclosures"));
		} else {
			$rtl_content = false;
			$always_display_enclosures = false;
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

			ccache_update($link, $feed_id, $_SESSION["uid"]);
		}

		$result = db_query($link, "SELECT title,link,content,feed_id,comments,int_id,
			".SUBSTRING_FOR_DATE."(updated,1,16) as updated,
			(SELECT icon_url FROM ttrss_feeds WHERE id = feed_id) as icon_url,
			(SELECT site_url FROM ttrss_feeds WHERE id = feed_id) as site_url,
			num_comments,
			author,
			orig_feed_id,
			note
			FROM ttrss_entries,ttrss_user_entries
			WHERE	id = '$id' AND ref_id = id AND owner_uid = " . $_SESSION["uid"]);

		if ($result) {

			$line = db_fetch_assoc($result);

			if ($line["icon_url"]) {
				$feed_icon = "<img class=\"feedIcon\" src=\"" . $line["icon_url"] . "\">";
			} else {
				$feed_icon = "&nbsp;";
			}

			$feed_site_url = $line['site_url'];

			$num_comments = $line["num_comments"];
			$entry_comments = "";

			if ($num_comments > 0) {
				if ($line["comments"]) {
					$comments_url = $line["comments"];
				} else {
					$comments_url = $line["link"];
				}
				$entry_comments = "<a target='_blank' href=\"$comments_url\">$num_comments comments</a>";
			} else {
				if ($line["comments"] && $line["link"] != $line["comments"]) {
					$entry_comments = "<a target='_blank' href=\"".$line["comments"]."\">comments</a>";
				}				
			}

			if ($zoom_mode) {
				header("Content-Type: text/html");
				print "<html><head>
						<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\"/>
						<title>Tiny Tiny RSS - ".$line["title"]."</title>
						<link rel=\"stylesheet\" type=\"text/css\" href=\"tt-rss.css\">
					</head><body>";
			}


			print "<div class=\"postReply\">";
			print "<div class=\"postHeader\">";

			$entry_author = $line["author"];

			if ($entry_author) {
				$entry_author = __(" - ") . $entry_author;
			}

			$parsed_updated = make_local_datetime($link, $line["updated"], true, 
				false, true);

			print "<div class=\"postDate$rtl_class\">$parsed_updated</div>";

			if ($line["link"]) {
				print "<div clear='both'><a target='_blank' href=\"" . $line["link"] . "\">" . 
					$line["title"] . "</a><span class='author'>$entry_author</span></div>";
			} else {
				print "<div clear='both'>" . $line["title"] . "$entry_author</div>";
			}

			$tags_str = format_tags_string(get_article_tags($link, $id), $id);

			if (!$entry_comments) $entry_comments = "&nbsp;"; # placeholder

			print "<div style='float : right'>
				<img src='".theme_image($link, 'images/tag.png')."' 
				class='tagsPic' alt='Tags' title='Tags'>&nbsp;";

			if (!$zoom_mode) {
				print "<span id=\"ATSTR-$id\">$tags_str</span>
					<a title=\"".__('Edit tags for this article')."\" 
					href=\"#\" onclick=\"editArticleTags($id, $feed_id)\">(+)</a>";

				print "<img src=\"".theme_image($link, 'images/art-zoom.png')."\" 
						class='tagsPic' style=\"cursor : pointer\"
						onclick=\"zoomToArticle($id)\"
						alt='Zoom' title='".__('Show article summary in new window')."'>";

				$note_escaped = htmlspecialchars($line['note'], ENT_QUOTES);

				print "<img src=\"".theme_image($link, 'images/art-pub-note.png')."\" 
						class='tagsPic' style=\"cursor : pointer\"
						onclick=\"publishWithNote($id, '$note_escaped')\"
						alt='PubNote' title='".__('Publish article with a note')."'>";

				if (DIGEST_ENABLE) {
					print "<img src=\"".theme_image($link, 'images/art-email.png')."\" 
						class='tagsPic' style=\"cursor : pointer\"
						onclick=\"emailArticle($id)\"
						alt='Zoom' title='".__('Forward by email')."'>";
				}

			} else {
				$tags_str = strip_tags($tags_str);
				print "<span id=\"ATSTR-$id\">$tags_str</span>";
			}
			print "</div>";
			print "<div clear='both'>$entry_comments</div>";

			if ($line["orig_feed_id"]) {

				$tmp_result = db_query($link, "SELECT * FROM ttrss_archived_feeds
					WHERE id = ".$line["orig_feed_id"]);

				if (db_num_rows($tmp_result) != 0) {

					print "<div clear='both'>";
					print __("Originally from:");

					print "&nbsp;";

					$tmp_line = db_fetch_assoc($tmp_result);

					print "<a target='_blank' 
						href=' " . htmlspecialchars($tmp_line['site_url']) . "'>" .
						$tmp_line['title'] . "</a>";

					print "&nbsp;";

					print "<a target='_blank' href='" . htmlspecialchars($tmp_line['feed_url']) . "'>";
					print "<img title='".__('Feed URL')."'class='tinyFeedIcon' src='images/pub_set.gif'></a>";

					print "</div>";
				}
			}

			print "</div>";

			print "<div class=\"postIcon\">" . 
				"<a target=\"_blank\" title=\"".__("Visit the website")."\"$ 
				href=\"".htmlspecialchars($feed_site_url)."\">".
				$feed_icon . "</a></div>";

			print "<div class=\"postContent\">";

			$article_content = sanitize_rss($link, $line["content"], false, false,
				$feed_site_url);

			print "<div id=\"POSTNOTE-$id\">";
				if ($line['note']) {
					print format_article_note($id, $line['note']);
				}
			print "</div>";

			print $article_content;

			print_article_enclosures($link, $id, $always_display_enclosures, 
				$article_content);

			print "</div>";

			print "</div>";

		}

		if (!$zoom_mode) { 
			print "]]></article>"; 
		} else {
			print "
				<div style=\"text-align : center\">
				<button onclick=\"return window.close()\">".
					__("Close this window")."</button></div>";
			print "</body></html>";

		}

	}

	function outputHeadlinesList($link, $feed, $subop, $view_mode, $limit, $cat_view,
					$next_unread_feed, $offset, $vgr_last_feed = false, 
					$override_order = false) {

		$disable_cache = false;

		$timing_info = getmicrotime();

		$topmost_article_ids = array();

		if (!$offset) {
			$offset = 0;
		}

		if ($subop == "undefined") $subop = "";

		$subop_split = split(":", $subop);

		if ($subop == "CatchupSelected") {
			$ids = split(",", db_escape_string($_REQUEST["ids"]));
			$cmode = sprintf("%d", $_REQUEST["cmode"]);

			catchupArticlesById($link, $ids, $cmode);
		}

		if ($subop == "ForceUpdate" && sprintf("%d", $feed) > 0 && !$cat_view) {
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

		// FIXME: might break tag display?

		if ($feed > 0 && !$cat_view) {		
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

		/// START /////////////////////////////////////////////////////////////////////////////////

		@$search = db_escape_string($_REQUEST["query"]);

		if ($search) { 
			$disable_cache = true;
		}

		@$search_mode = db_escape_string($_REQUEST["search_mode"]);
		@$match_on = db_escape_string($_REQUEST["match_on"]);

		if (!$match_on) {
			$match_on = "both";
		}

		$real_offset = $offset * $limit;

		if ($_REQUEST["debug"]) $timing_info = print_checkpoint("H0", $timing_info);

		$qfh_ret = queryFeedHeadlines($link, $feed, $limit, $view_mode, $cat_view, 
			$search, $search_mode, $match_on, $override_order, $real_offset);

		if ($_REQUEST["debug"]) $timing_info = print_checkpoint("H1", $timing_info);

		$result = $qfh_ret[0];
		$feed_title = $qfh_ret[1];
		$feed_site_url = $qfh_ret[2];
		$last_error = $qfh_ret[3];

		$vgroup_last_feed = $vgr_last_feed;

/*		if ($feed == -2) {
			$feed_site_url = article_publish_url($link);
		} */

		/// STOP //////////////////////////////////////////////////////////////////////////////////

		if (!$offset) {
			print "<div id=\"headlinesContainer\" $rtl_tag>";

			if (!$result) {
				print "<div align='center'>".__("Could not display feed (query failed). Please check label match syntax or local configuration.")."</div>";
				return;
			}

			if (db_num_rows($result) > 0) {
				print_headline_subtoolbar($link, $feed_site_url, $feed_title,
					$feed, $cat_view, $search, $match_on, $search_mode, $view_mode);

				print "<div id=\"headlinesInnerContainer\" onscroll=\"headlines_scroll_handler()\">";

			}
		}

		$headlines_count = db_num_rows($result);

		if (db_num_rows($result) > 0) {

#			print "\{$offset}";

			if (!get_pref($link, 'COMBINED_DISPLAY_MODE') && !$offset) {
				print "<table class=\"headlinesList\" id=\"headlinesList\" 
					cellspacing=\"0\">";
			}

			$lnum = $limit*$offset;

			$num_unread = 0;
			$cur_feed_title = '';

			$fresh_intl = get_pref($link, "FRESH_ARTICLE_MAX_AGE") * 60 * 60;

			while ($line = db_fetch_assoc($result)) {

				$class = ($lnum % 2) ? "even" : "odd";
	
				$id = $line["id"];
				$feed_id = $line["feed_id"];

				$labels = get_article_labels($link, $id);

				$labels_str = "<span id=\"HLLCTR-$id\">";
				$labels_str .= format_article_labels($labels, $id);
				$labels_str .= "</span>";
	
				if (count($topmost_article_ids) < 3) {
					array_push($topmost_article_ids, $id);
				}

				if ($line["last_read"] == "" && !sql_bool_to_bool($line["unread"])) {
	
					$update_pic = "<img id='FUPDPIC-$id' src=\"".
						theme_image($link, 'images/updated.png')."\" 
						alt=\"Updated\">";
				} else {
					$update_pic = "<img id='FUPDPIC-$id' src=\"images/blank_icon.gif\" 
						alt=\"Updated\">";
				}

				if (sql_bool_to_bool($line["unread"]) && 
					time() - strtotime($line["updated_noms"]) < $fresh_intl) {

					$update_pic = "<img id='FUPDPIC-$id' src=\"".
						theme_image($link, 'images/fresh_sign.png')."\" alt=\"Fresh\">";
				}
	
				if ($line["unread"] == "t" || $line["unread"] == "1") {
					$class .= "Unread";
					++$num_unread;
					$is_unread = true;
				} else {
					$is_unread = false;
				}

				if ($line["marked"] == "t" || $line["marked"] == "1") {
					$marked_pic = "<img id=\"FMPIC-$id\" 
						src=\"".theme_image($link, 'images/mark_set.png')."\" 
						class=\"markedPic\" alt=\"Unstar article\" 
						onclick='javascript:tMark($id)'>";
				} else {
					$marked_pic = "<img id=\"FMPIC-$id\" 
						src=\"".theme_image($link, 'images/mark_unset.png')."\" 
						class=\"markedPic\" alt=\"Star article\" 
						onclick='javascript:tMark($id)'>";
				}

				if ($line["published"] == "t" || $line["published"] == "1") {
					$published_pic = "<img id=\"FPPIC-$id\" src=\"".theme_image($link, 
						'images/pub_set.png')."\" 
						class=\"markedPic\"
						alt=\"Unpublish article\" onclick='javascript:tPub($id)'>";
				} else {
					$published_pic = "<img id=\"FPPIC-$id\" src=\"".theme_image($link,
						'images/pub_unset.png')."\" 
						class=\"markedPic\"
						alt=\"Publish article\" onclick='javascript:tPub($id)'>";
				}

#				$content_link = "<a target=\"_blank\" href=\"".$line["link"]."\">" .
#					$line["title"] . "</a>";

#				$content_link = "<a 
#					href=\"" . htmlspecialchars($line["link"]) . "\"
#					onclick=\"view($id,$feed_id);\">" .
#					$line["title"] . "</a>";

#				$content_link = "<a href=\"javascript:viewContentUrl('".$line["link"]."');\">" .
#					$line["title"] . "</a>";

				$updated_fmt = make_local_datetime($link, $line["updated_noms"], false);	

				if (get_pref($link, 'SHOW_CONTENT_PREVIEW')) {
					$content_preview = truncate_string(strip_tags($line["content_preview"]), 
						100);
				}

				$score = $line["score"];

				$score_pic = theme_image($link, 
					"images/" . get_score_pic($score));

/*				$score_title = __("(Click to change)");
				$score_pic = "<img class='hlScorePic' src=\"images/$score_pic\" 
					onclick=\"adjustArticleScore($id, $score)\" title=\"$score $score_title\">"; */

				$score_pic = "<img class='hlScorePic' src=\"$score_pic\" 
					title=\"$score\">";

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

				$has_feed_icon = feed_has_icon($feed_id);

				if ($has_feed_icon) {
					$feed_icon_img = "<img class=\"tinyFeedIcon\" src=\"".ICONS_URL."/$feed_id.ico\" alt=\"\">";
				} else {
					//$feed_icon_img = "<img class=\"tinyFeedIcon\" src=\"images/blank_icon.gif\" alt=\"\">";
					$feed_icon_img = "";
				}

				if (!get_pref($link, 'COMBINED_DISPLAY_MODE')) {

					if (get_pref($link, 'VFEED_GROUP_BY_FEED')) {
						if ($feed_id != $vgroup_last_feed && $line["feed_title"]) {

							$cur_feed_title = $line["feed_title"];
							$vgroup_last_feed = $feed_id;

							$cur_feed_title = htmlspecialchars($cur_feed_title);

							$vf_catchup_link = "(<a onclick='javascript:catchupFeedInGroup($feed_id);' href='#'>".__('mark as read')."</a>)";

							print "<tr class='feedTitle'><td colspan='7'>".
								"<div style=\"float : right\">$feed_icon_img</div>".
								"<a href=\"#\" onclick=\"viewfeed($feed_id)\">".
								$line["feed_title"]."</a> $vf_catchup_link</td></tr>";
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

					print "<td onclick='return hlClicked(event,$id)' 
						class='hlContent$hlc_suffix' valign='middle' id='HLC-$id'>";

					print "<a id=\"RTITLE-$id\" 
						href=\"" . htmlspecialchars($line["link"]) . "\"
						onclick=\"return false\">" .
						$line["title"];

					if (get_pref($link, 'SHOW_CONTENT_PREVIEW')) {
						if ($content_preview) {
							print "<span class=\"contentPreview\"> - $content_preview</span>";
						}
					}

					print "</a>";

					print $labels_str;

					/* if (!get_pref($link, 'VFEED_GROUP_BY_FEED')) {
						if (@$line["feed_title"]) {			
							print "<span class=\"hlFeed\">
								(<a href=\"#\" onclick=\"viewfeed($feed_id)\">".
								$line["feed_title"]."</a>)
							</span>";
						}
					} */

					print "</td>";

		
					print "<td class=\"hlUpdated\" 
						onclick='return hlClicked(event,$id)'><nobr>$updated_fmt&nbsp;
					</nobr></td>";

					print "<td class='hlMarkedPic'>$score_pic</td>";

					if (@$line["feed_title"] && !get_pref($link, 'VFEED_GROUP_BY_FEED')) {
						print "<td onclick=\"viewfeed($feed_id)\" 
							title=\"".htmlspecialchars($line['feed_title'])."\"
							class=\"hlFeedIcon\">$feed_icon_img</td>";
					}

					print "</tr>";

				} else {

					if (get_pref($link, 'VFEED_GROUP_BY_FEED') && $line["feed_title"]) {
						if ($feed_id != $vgroup_last_feed) {

							$cur_feed_title = $line["feed_title"];
							$vgroup_last_feed = $feed_id;

							$cur_feed_title = htmlspecialchars($cur_feed_title);

							$vf_catchup_link = "(<a onclick='javascript:catchupFeedInGroup($feed_id);' href='#'>".__('mark as read')."</a>)";

							$has_feed_icon = feed_has_icon($feed_id);

							if ($has_feed_icon) {
								$feed_icon_img = "<img class=\"tinyFeedIcon\" src=\"".ICONS_URL."/$feed_id.ico\" alt=\"\">";
							} else {
								//$feed_icon_img = "<img class=\"tinyFeedIcon\" src=\"images/blank_icon.gif\" alt=\"\">";
							}

							print "<div class='cdmFeedTitle'>".
								"<div style=\"float : right\">$feed_icon_img</div>".
								"<a href=\"#\" onclick=\"viewfeed($feed_id)\">".
								$line["feed_title"]."</a> $vf_catchup_link</div>";
						}
					}

					if ($is_unread) {
						$add_class = "Unread";
					} else {
						$add_class = "";
					}	

					$expand_cdm = get_pref($link, 'CDM_EXPANDED');

					$mouseover_attrs = "onmouseover='postMouseIn($id)' 
						onmouseout='postMouseOut($id)'";

					print "<div class=\"$class\" 
						id=\"RROW-$id\"						
						$mouseover_attrs'>";

					print "<div class=\"cdmHeader\">";

					print "<div style='float : right'>";
					print "<span class='updated'>$updated_fmt</span>";
					print "$marked_pic";
					print "$published_pic";
					print "$score_pic";

					if (!get_pref($link, "VFEED_GROUP_BY_FEED") && $line["feed_title"]) {
						print "<span style=\"cursor : pointer\" 
							title=\"".htmlspecialchars($line["feed_title"])."\"
							onclick=\"viewfeed($feed_id)\">$feed_icon_img</span>";
					}
					print "</div>";
	
					print "<input type=\"checkbox\" onclick=\"toggleSelectRowById(this, 
							'RROW-$id')\" class=\"feedCheckBox\" id=\"RCHK-$id\"/>";

					print "<span id=\"RTITLE-$id\" 
						onclick=\"return cdmExpandArticle($id)\"
						class=\"titleWrap$hlc_suffix\">
						<a class=\"title\"
						target=\"_blank\" href=\"".$line["link"]."\">".$line["title"]."</a>
						</span>";

					print $labels_str;

					if (!$expand_cdm)
						$content_hidden = "style=\"display : none\"";
					else
						$excerpt_hidden = "style=\"display : none\"";

					print "<span $excerpt_hidden
						onclick=\"return cdmExpandArticle($id)\"
						id=\"CEXC-$id\" class=\"cdmExcerpt\"> - $content_preview</span>";

					print "</div>";

					print "<div class=\"cdmContent\" $content_hidden
						onclick=\"return cdmClicked(event, $id);\"
						id=\"CICD-$id\">";

					print "<div class=\"cdmContentInner\">";

			if ($line["orig_feed_id"]) {

				$tmp_result = db_query($link, "SELECT * FROM ttrss_archived_feeds
					WHERE id = ".$line["orig_feed_id"]);

						if (db_num_rows($tmp_result) != 0) {
		
							print "<div clear='both'>";
							print __("Originally from:");
		
							print "&nbsp;";
		
							$tmp_line = db_fetch_assoc($tmp_result);
		
							print "<a target='_blank' 
								href=' " . htmlspecialchars($tmp_line['site_url']) . "'>" .
								$tmp_line['title'] . "</a>";
		
							print "&nbsp;";
		
							print "<a target='_blank' href='" . htmlspecialchars($tmp_line['feed_url']) . "'>";
							print "<img title='".__('Feed URL')."'class='tinyFeedIcon' src='images/pub_set.gif'></a>";
		
							print "</div>";
						}
					}

					// FIXME: make this less of a hack

					$feed_site_url = false;

					if ($line["feed_id"]) {
						$tmp_result = db_query($link, "SELECT site_url FROM ttrss_feeds
							WHERE id = " . $line["feed_id"]);

						if (db_num_rows($tmp_result) == 1) {
							$feed_site_url = db_fetch_result($tmp_result, 0, "site_url");
						}
					}

					$article_content = sanitize_rss($link, $line["content_preview"], 
						false, false, $feed_site_url);

					if (!$article_content) $article_content = "&nbsp;";

					print "<div id=\"POSTNOTE-$id\">";
					if ($line['note']) {
						print format_article_note($id, $line['note']);
					}
					print "</div>";

					print $article_content;

					$tmp_result = db_query($link, "SELECT always_display_enclosures FROM
						ttrss_feeds WHERE id = ".
						(($line['feed_id'] == null) ? $line['orig_feed_id'] : 
								$line['feed_id'])." AND owner_uid = ".$_SESSION["uid"]);

					$always_display_enclosures = sql_bool_to_bool(db_fetch_result($tmp_result, 
						0, "always_display_enclosures"));

					print_article_enclosures($link, $id, $always_display_enclosures,
						$article_content);

					print "</div>";

					print "<div class=\"cdmFooter\">";

					$tags_str = format_tags_string(get_article_tags($link, $id), $id);

					print "<img src='".theme_image($link,
							'images/tag.png')."' alt='Tags' title='Tags'>
						<span id=\"ATSTR-$id\">$tags_str</span>
						<a title=\"".__('Edit tags for this article')."\" 
						href=\"#\" onclick=\"editArticleTags($id, $feed_id, true)\">(+)</a>";

					print "<div style=\"float : right\">";

					print "<img src=\"images/art-zoom.png\" 
						onclick=\"zoomToArticle($id)\"
						style=\"cursor : pointer\"
						alt='Zoom' 
						title='".__('Show article summary in new window')."'>";

					if (DIGEST_ENABLE) {
						print "<img src=\"".theme_image($link, 'images/art-email.png')."\" 
							style=\"cursor : pointer\"
							onclick=\"emailArticle($id)\"
							alt='Zoom' title='".__('Forward by email')."'>";
					}

					$note_escaped = htmlspecialchars($line['note'], ENT_QUOTES);

					print "<img src=\"images/art-pub-note.png\"
						style=\"cursor : pointer\" style=\"cursor : pointer\"
						onclick=\"publishWithNote($id, '$note_escaped')\"
						alt='PubNote' title='".__('Publish article with a note')."'>";

					print "<img src=\"images/digest_checkbox.png\"
						style=\"cursor : pointer\" style=\"cursor : pointer\"
						onclick=\"dismissArticle($id)\"
						alt='Dismiss' title='".__('Dismiss article')."'>";

					print "</div>";
					print "</div>";

					print "</div>";

					print "</div>";

				} 
	
				++$lnum;
			} 

			if (!get_pref($link, 'COMBINED_DISPLAY_MODE') && !$offset) {			
				print "</table>";
			}

		} else {
			$message = "";

			switch ($view_mode) {
				case "unread":
					$message = __("No unread articles found to display.");
					break;
				case "updated":
					$message = __("No updated articles found to display.");
					break;
				case "marked":
					$message = __("No starred articles found to display.");
					break;
				default:
					if ($feed < -10) {
						$message = __("No articles found to display. You can assign articles to labels manually (see the Actions menu above) or use a filter.");
					} else {
						$message = __("No articles found to display.");
					}
			}

			if (!$offset && $message) {
				print "<div class='whiteBox'>$message";

				print "<p class=\"small\"><span class=\"insensitive\">";

				$result = db_query($link, "SELECT ".SUBSTRING_FOR_DATE."(MAX(last_updated), 1, 19) AS last_updated FROM ttrss_feeds
					WHERE owner_uid = " . $_SESSION['uid']);
		
				$last_updated = db_fetch_result($result, 0, "last_updated");
				$last_updated = make_local_datetime($link, $last_updated, false);
		
				printf(__("Feeds last updated at %s"), $last_updated);
		
				$result = db_query($link, "SELECT COUNT(id) AS num_errors
					FROM ttrss_feeds WHERE last_error != '' AND owner_uid = ".$_SESSION["uid"]);
		
				$num_errors = db_fetch_result($result, 0, "num_errors");
		
				if ($num_errors > 0) {
					print "<br/>";
					print "<a class=\"insensitive\" href=\"#\" onclick=\"showFeedsWithErrors()\">".
						__('Some feeds have update errors (click for details)')."</a>";
				}
				print "</span></p></div>";
			}
		}

		if (!$offset) {
			if ($headlines_count > 0) print "</div>";
			print "</div>";
		}

		return array($topmost_article_ids, $headlines_count, $feed, $disable_cache, $vgroup_last_feed);
	}

// from here: http://www.roscripts.com/Create_tag_cloud-71.html

	function printTagCloud($link) {

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

	function get_self_url_prefix() {

		$url_path = "";
	
		if ($_SERVER['HTTPS'] != "on") {
			$url_path = "http://";
		} else {
			$url_path = "https://";
		}

		$url_path .= $_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']);

		return $url_path;

	}
	function opml_publish_url($link){

		$url_path = get_self_url_prefix();
		$url_path .= "/opml.php?op=publish&key=" . 
			get_feed_access_key($link, 'OPML:Publish', false, $_SESSION["uid"]);

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

		if ($id != 0) {
			$result = db_query($link, "DELETE FROM ttrss_user_entries
			WHERE feed_id = '$id' AND marked = false AND owner_uid = " . $_SESSION["uid"]);
		} else {
			$result = db_query($link, "DELETE FROM ttrss_user_entries
			WHERE feed_id IS NULL AND marked = false AND owner_uid = " . $_SESSION["uid"]);
		}

		$result = db_query($link, "DELETE FROM ttrss_entries WHERE 
			(SELECT COUNT(int_id) FROM ttrss_user_entries WHERE ref_id = id) = 0");

		ccache_update($link, $id, $_SESSION['uid']);
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

			update_rss_feed($link, $line["id"], true);

			sleep(1); // prevent flood (FIXME make this an option?)
		}

		// Send feed digests by email if needed.
		if (DAEMON_SENDS_DIGESTS) send_headlines_digests($link);

		purge_orphans($link);

	} // function update_daemon_common

	function sanitize_article_content($text) {
		# we don't support CDATA sections in articles, they break our own escaping
		$text = preg_replace("/\[\[CDATA/", "", $text);
		$text = preg_replace("/\]\]\>/", "", $text);
		return $text;
	}

	function load_filters($link, $feed, $owner_uid, $action_id = false) {
		$filters = array();

		global $memcache;

		$obj_id = md5("FILTER:$feed:$owner_uid:$action_id");

		if ($memcache && $obj = $memcache->get($obj_id)) {

			return $obj;

		} else {

			if ($action_id) $ftype_query_part = "action_id = '$action_id' AND";
	
			$result = db_query($link, "SELECT reg_exp,
				ttrss_filter_types.name AS name,
				ttrss_filter_actions.name AS action,
				inverse,
				action_param,
				filter_param
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
					$filter["filter_param"] = $line["filter_param"];
					$filter["inverse"] = sql_bool_to_bool($line["inverse"]);
				
					array_push($filters[$line["name"]], $filter);
				}

			if ($memcache) $memcache->add($obj_id, $filters, 0, 3600*8);

			return $filters;
		}
	}

	function get_score_pic($score) {
		if ($score > 100) { 
			return "score_high.png"; 
		} else if ($score > 0) { 
			return "score_half_high.png";
		} else if ($score < -100) {
			return "score_low.png";
		} else if ($score < 0) {
			return "score_half_low.png";
		} else { 
			return "score_neutral.png";
		}
	}

	function rounded_table_start($classname, $header = "&nbsp;") {
		print "<table width='100%' class='$classname' cellspacing='0' cellpadding='0'>";
		print "<tr><td class='c1'>&nbsp;</td><td class='top'>$header</td><td class='c2'>&nbsp;</td></tr>";
		print "<tr><td class='left'>&nbsp;</td><td class='content'>";
	}

	function rounded_table_end($footer = "&nbsp;") {
		print "</td><td class='right'>&nbsp;</td></tr>";
		print "<tr><td class='c4'>&nbsp;</td><td class='bottom'>$footer</td><td class='c3'>&nbsp;</td></tr>";
		print "</table>";
	}

	function feed_has_icon($id) {
		return is_file(ICONS_DIR . "/$id.ico") && filesize(ICONS_DIR . "/$id.ico") > 0;
	}

	function init_connection($link) {
		if (DB_TYPE == "pgsql") {
			pg_query($link, "set client_encoding = 'UTF-8'");
			pg_set_client_encoding("UNICODE");
			pg_query($link, "set datestyle = 'ISO, european'");
			pg_query($link, "set TIME ZONE 0");
		} else {
			db_query($link, "SET time_zone = '+0:0'");

			if (defined('MYSQL_CHARSET') && MYSQL_CHARSET) {
				db_query($link, "SET NAMES " . MYSQL_CHARSET);
	//			db_query($link, "SET CHARACTER SET " . MYSQL_CHARSET);
			}
		}
	}

	function update_feedbrowser_cache($link) {

		$result = db_query($link, "SELECT feed_url,title, COUNT(id) AS subscribers
	  		FROM ttrss_feeds WHERE (SELECT COUNT(id) = 0 FROM ttrss_feeds AS tf 
				WHERE tf.feed_url = ttrss_feeds.feed_url 
				AND (private IS true OR feed_url LIKE '%:%@%/%')) 
				GROUP BY feed_url, title ORDER BY subscribers DESC LIMIT 1000");
	
		db_query($link, "BEGIN");
	
		db_query($link, "DELETE FROM ttrss_feedbrowser_cache");
	
		$count = 0;
	
		while ($line = db_fetch_assoc($result)) {
			$subscribers = db_escape_string($line["subscribers"]);
			$feed_url = db_escape_string($line["feed_url"]);
			$title = db_escape_string($line["title"]);

			$tmp_result = db_query($link, "SELECT subscribers FROM
				ttrss_feedbrowser_cache WHERE feed_url = '$feed_url'");

			if (db_num_rows($tmp_result) == 0) {

				db_query($link, "INSERT INTO ttrss_feedbrowser_cache 
					(feed_url, title, subscribers) VALUES ('$feed_url', 
						'$title', '$subscribers')");

				++$count;

			}
	
		}
	
		db_query($link, "COMMIT");

		return $count;

	}

	function ccache_zero($link, $feed_id, $owner_uid) {
		db_query($link, "UPDATE ttrss_counters_cache SET
			value = 0, updated = NOW() WHERE
			feed_id = '$feed_id' AND owner_uid = '$owner_uid'");
	}

	function ccache_zero_all($link, $owner_uid) {
		db_query($link, "UPDATE ttrss_counters_cache SET
			value = 0 WHERE owner_uid = '$owner_uid'");

		db_query($link, "UPDATE ttrss_cat_counters_cache SET
			value = 0 WHERE owner_uid = '$owner_uid'");
	}

	function ccache_remove($link, $feed_id, $owner_uid, $is_cat = false) {

		if (!$is_cat) {
			$table = "ttrss_counters_cache";
		} else {
			$table = "ttrss_cat_counters_cache";
		}

		db_query($link, "DELETE FROM $table WHERE
			feed_id = '$feed_id' AND owner_uid = '$owner_uid'");

	}

	function ccache_update_all($link, $owner_uid) {

		if (get_pref($link, 'ENABLE_FEED_CATS', $owner_uid)) {

			$result = db_query($link, "SELECT feed_id FROM ttrss_cat_counters_cache
				WHERE feed_id > 0 AND owner_uid = '$owner_uid'");

			while ($line = db_fetch_assoc($result)) {
				ccache_update($link, $line["feed_id"], $owner_uid, true);
			}

			/* We have to manually include category 0 */

			ccache_update($link, 0, $owner_uid, true);

		} else {
			$result = db_query($link, "SELECT feed_id FROM ttrss_counters_cache
				WHERE feed_id > 0 AND owner_uid = '$owner_uid'");

			while ($line = db_fetch_assoc($result)) {
				print ccache_update($link, $line["feed_id"], $owner_uid);

			}

		}
	}

	function ccache_find($link, $feed_id, $owner_uid, $is_cat = false, 
		$no_update = false) {

		if (!is_numeric($feed_id)) return;

		if (!$is_cat) {
			$table = "ttrss_counters_cache";
			if ($feed_id > 0) {
				$tmp_result = db_query($link, "SELECT owner_uid FROM ttrss_feeds 
					WHERE id = '$feed_id'");
				$owner_uid = db_fetch_result($tmp_result, 0, "owner_uid");
			}
		} else {
			$table = "ttrss_cat_counters_cache";
		}

		if (DB_TYPE == "pgsql") {
			$date_qpart = "updated > NOW() - INTERVAL '15 minutes'";
		} else if (DB_TYPE == "mysql") {
			$date_qpart = "updated > DATE_SUB(NOW(), INTERVAL 15 MINUTE)";
		}

		$result = db_query($link, "SELECT value FROM $table
			WHERE owner_uid = '$owner_uid' AND feed_id = '$feed_id' 
			LIMIT 1");

		if (db_num_rows($result) == 1) {
			return db_fetch_result($result, 0, "value");
		} else {
			if ($no_update) {
				return -1;
			} else {
				return ccache_update($link, $feed_id, $owner_uid, $is_cat);
			}
		}

	}

	function ccache_update($link, $feed_id, $owner_uid, $is_cat = false, 
		$update_pcat = true) {

		if (!is_numeric($feed_id)) return;

		if (!$is_cat && $feed_id > 0) {
			$tmp_result = db_query($link, "SELECT owner_uid FROM ttrss_feeds 
				WHERE id = '$feed_id'");
			$owner_uid = db_fetch_result($tmp_result, 0, "owner_uid");
		}

		$prev_unread = ccache_find($link, $feed_id, $owner_uid, $is_cat, true);

		/* When updating a label, all we need to do is recalculate feed counters
		 * because labels are not cached */

		if ($feed_id < 0) {
			ccache_update_all($link, $owner_uid);
			return;
		}

		if (!$is_cat) {
			$table = "ttrss_counters_cache";
		} else {
			$table = "ttrss_cat_counters_cache";
		}

		if ($is_cat && $feed_id >= 0) {
			if ($feed_id != 0) {
				$cat_qpart = "cat_id = '$feed_id'";
			} else {
				$cat_qpart = "cat_id IS NULL";
			}

			/* Recalculate counters for child feeds */

			$result = db_query($link, "SELECT id FROM ttrss_feeds
						WHERE owner_uid = '$owner_uid' AND $cat_qpart");

			while ($line = db_fetch_assoc($result)) {
				ccache_update($link, $line["id"], $owner_uid, false, false);
			}

			$result = db_query($link, "SELECT SUM(value) AS sv 
				FROM ttrss_counters_cache, ttrss_feeds 
				WHERE id = feed_id AND $cat_qpart AND 
				ttrss_feeds.owner_uid = '$owner_uid'");

			$unread = (int) db_fetch_result($result, 0, "sv");

		} else {
			$unread = (int) getFeedArticles($link, $feed_id, $is_cat, true, $owner_uid);
		}

		db_query($link, "BEGIN");

		$result = db_query($link, "SELECT feed_id FROM $table
			WHERE owner_uid = '$owner_uid' AND feed_id = '$feed_id' LIMIT 1");

		if (db_num_rows($result) == 1) {
			db_query($link, "UPDATE $table SET
				value = '$unread', updated = NOW() WHERE
				feed_id = '$feed_id' AND owner_uid = '$owner_uid'");

		} else {
			db_query($link, "INSERT INTO $table
				(feed_id, value, owner_uid, updated) 
				VALUES 
				($feed_id, $unread, $owner_uid, NOW())");
		}

		db_query($link, "COMMIT");

		if ($feed_id > 0 && $prev_unread != $unread) {

			if (!$is_cat) {

				/* Update parent category */

				if ($update_pcat) {

					$result = db_query($link, "SELECT cat_id FROM ttrss_feeds
						WHERE owner_uid = '$owner_uid' AND id = '$feed_id'");

					$cat_id = (int) db_fetch_result($result, 0, "cat_id");

					ccache_update($link, $cat_id, $owner_uid, true);

				}
			}
		} else if ($feed_id < 0) {
			ccache_update_all($link, $owner_uid);
		}

		return $unread;
	}

	function label_find_id($link, $label, $owner_uid) {
		$result = db_query($link, 
			"SELECT id FROM ttrss_labels2 WHERE caption = '$label' 
				AND owner_uid = '$owner_uid' LIMIT 1");

		if (db_num_rows($result) == 1) {
			return db_fetch_result($result, 0, "id");
		} else {
			return 0;
		}
	}

	function get_article_labels($link, $id) {
		global $memcache;

		$obj_id = md5("LABELS:$id:" . $_SESSION["uid"]);

		$rv = array();

		if ($memcache && $obj = $memcache->get($obj_id)) {
			return $obj;
		} else {

			$result = db_query($link, "SELECT label_cache FROM
				ttrss_user_entries WHERE ref_id = '$id' AND owner_uid = " .
				$_SESSION["uid"]);

			$label_cache = db_fetch_result($result, 0, "label_cache");

			if ($label_cache) {

				$label_cache = json_decode($label_cache, true);

				if ($label_cache["no-labels"] == 1)
					return $rv;
				else
					return $label_cache;
			}

			$result = db_query($link, 
				"SELECT DISTINCT label_id,caption,fg_color,bg_color 
					FROM ttrss_labels2, ttrss_user_labels2 
				WHERE id = label_id 
					AND article_id = '$id' 
					AND owner_uid = ".$_SESSION["uid"] . "
				ORDER BY caption");

			while ($line = db_fetch_assoc($result)) {
				$rk = array($line["label_id"], $line["caption"], $line["fg_color"],
					$line["bg_color"]);
				array_push($rv, $rk);
			}
			if ($memcache) $memcache->add($obj_id, $rv, 0, 3600);

			if (count($rv) > 0) 
				label_update_cache($link, $id, $rv);
			else
				label_update_cache($link, $id, array("no-labels" => 1));
		}

		return $rv;
	}


	function label_find_caption($link, $label, $owner_uid) {
		$result = db_query($link, 
			"SELECT caption FROM ttrss_labels2 WHERE id = '$label' 
				AND owner_uid = '$owner_uid' LIMIT 1");

		if (db_num_rows($result) == 1) {
			return db_fetch_result($result, 0, "caption");
		} else {
			return "";
		}
	}

	function label_update_cache($link, $id, $labels = false, $force = false) {

		if ($force)
			label_clear_cache($link, $id);

		if (!$labels) 
			$labels = get_article_labels($link, $id);

		$labels = db_escape_string(json_encode($labels));

		db_query($link, "UPDATE ttrss_user_entries SET
			label_cache = '$labels' WHERE ref_id = '$id'");

	}

	function label_clear_cache($link, $id) {

		db_query($link, "UPDATE ttrss_user_entries SET
			label_cache = '' WHERE ref_id = '$id'");

	}

	function label_remove_article($link, $id, $label, $owner_uid) {

		$label_id = label_find_id($link, $label, $owner_uid);

		if (!$label_id) return;

		$result = db_query($link, 
			"DELETE FROM ttrss_user_labels2
			WHERE 
				label_id = '$label_id' AND
				article_id = '$id'");

		label_clear_cache($link, $id);
	}

	function label_add_article($link, $id, $label, $owner_uid) {

		global $memcache;

		if ($memcache) {
			$obj_id = md5("LABELS:$id:$owner_uid");
			$memcache->delete($obj_id);
		}

		$label_id = label_find_id($link, $label, $owner_uid);

		if (!$label_id) return;

		$result = db_query($link, 
			"SELECT 
				article_id FROM ttrss_labels2, ttrss_user_labels2
			WHERE 
				label_id = id AND 
				label_id = '$label_id' AND
				article_id = '$id' AND owner_uid = '$owner_uid'
			LIMIT 1");

		if (db_num_rows($result) == 0) {
			db_query($link, "INSERT INTO ttrss_user_labels2 
				(label_id, article_id) VALUES ('$label_id', '$id')");
		}

		label_clear_cache($link, $id);	

	}

	function label_remove($link, $id, $owner_uid) {
		global $memcache;

		if (!$owner_uid) $owner_uid = $_SESSION["uid"];

		if ($memcache) {
			$obj_id = md5("LABELS:$id:$owner_uid");
			$memcache->delete($obj_id);
		}

		db_query($link, "BEGIN");

		$result = db_query($link, "SELECT caption FROM ttrss_labels2
			WHERE id = '$id'");

		$caption = db_fetch_result($result, 0, "caption");

		$result = db_query($link, "DELETE FROM ttrss_labels2 WHERE id = '$id'
			AND owner_uid = " . $owner_uid);

		if (db_affected_rows($link, $result) != 0 && $caption) {

			/* Remove access key for the label */

			$ext_id = -11 - $id;

			db_query($link, "DELETE FROM ttrss_access_keys WHERE
				feed_id = '$ext_id' AND owner_uid = $owner_uid");

			/* Disable filters that reference label being removed */
	
			db_query($link, "UPDATE ttrss_filters SET
				enabled = false WHERE action_param = '$caption'
					AND action_id = 7
					AND owner_uid = " . $owner_uid);

			/* Remove cached data */

			db_query($link, "UPDATE ttrss_user_entries SET label_cache = ''
				WHERE label_cache LIKE '%$caption%' AND owner_uid = " . $owner_uid);

		}

		db_query($link, "COMMIT");
	}

	function label_create($link, $caption) {

		db_query($link, "BEGIN");

		$result = false;

		$result = db_query($link, "SELECT id FROM ttrss_labels2
			WHERE caption = '$caption' AND owner_uid =  ". $_SESSION["uid"]);

		if (db_num_rows($result) == 0) {
			$result = db_query($link,
				"INSERT INTO ttrss_labels2 (caption,owner_uid) 
					VALUES ('$caption', '".$_SESSION["uid"]."')");

			$result = db_affected_rows($link, $result) != 0;
		}

		db_query($link, "COMMIT");

		return $result;
	}

	function print_labels_headlines_dropdown($link, $feed_id) {
		print "<li onclick=\"javascript:addLabel()\">
			&nbsp;&nbsp;".__("Create label...")."</li>";

		$result = db_query($link, "SELECT id, caption FROM ttrss_labels2 WHERE
			owner_uid = '".$_SESSION["uid"]."' ORDER BY caption");

		while ($line = db_fetch_assoc($result)) {

			$label_id = $line["id"];
			$label_caption = $line["caption"];
			$id = $line["id"];

			if ($feed_id < -10 && $feed_id == -11-$label_id) {
				print "<li id=\"LHDL-$id\" 
					onclick=\"javascript:selectionRemoveLabel($label_id)\">
					&nbsp;&nbsp;$label_caption ".__('(remove)')."</li>";
			} else {					
				print "<li id=\"LHDL-$id\" 
					onclick=\"javascript:selectionAssignLabel($label_id)\">
					&nbsp;&nbsp;$label_caption</li>";
			}
		}
	}

	function format_tags_string($tags, $id) {

		$tags_str = "";
		$tags_nolinks_str = "";

		$num_tags = 0;

/*		if (get_user_theme($link) == "3pane") {
			$tag_limit = 3;
		} else {
			$tag_limit = 6;
		} */

		$tag_limit = 6;

		$formatted_tags = array();

		foreach ($tags as $tag) {
			$num_tags++;
			$tag_escaped = str_replace("'", "\\'", $tag);

			if (mb_strlen($tag) > 30) {
				$tag = truncate_string($tag, 30);
			}

			$tag_str = "<a href=\"javascript:viewfeed('$tag_escaped')\">$tag</a>";

			array_push($formatted_tags, $tag_str);

			$tmp_tags_str = implode(", ", $formatted_tags);
				
			if ($num_tags == $tag_limit || mb_strlen($tmp_tags_str) > 150) {
				break;
			}
		}

		$tags_str = implode(", ", $formatted_tags);

		if ($num_tags < count($tags)) {
			$tags_str .= ", &hellip;";
		}

		if ($num_tags == 0) {
			$tags_str = __("no tags");
		}

		return $tags_str;

	}

	function format_article_labels($labels, $id) {

		$labels_str = "";

		foreach ($labels as $l) {
			$labels_str .= sprintf("<span class='hlLabelRef' 
				style='color : %s; background-color : %s'>%s</span>",
					$l[2], $l[3], $l[1]);
			}

		return $labels_str;

	}

	function format_article_note($id, $note) {

		$note_escaped = htmlspecialchars($note, ENT_QUOTES);

		$str = "<div class='articleNote'>";
		$str .= $note;
		$str .= "<div class='articleNoteOps'>";
		$str .= "<a href=\"javascript:publishWithNote($id, '$note_escaped')\">".
			__('edit note')."</a>";
		$str .= "</div>";
		$str .= "</div>";

		return $str;
	}

	function toggle_collapse_cat($link, $cat_id) {
		if ($cat_id > 0) {
			db_query($link, "UPDATE ttrss_feed_categories SET
				collapsed = NOT collapsed WHERE id = '$cat_id' AND owner_uid = " . 
				$_SESSION["uid"]);
		} else {
			$pref_name = '';

			switch ($cat_id) {
			case -1:
				$pref_name = '_COLLAPSED_SPECIAL';
				break;
			case -2:
				$pref_name = '_COLLAPSED_LABELS';
				break;
			case 0:
				$pref_name = '_COLLAPSED_UNCAT';
				break;
			}

			if ($pref_name) {
				if (get_pref($link, $pref_name)) {
					set_pref($link, $pref_name, 'false');
				} else {
					set_pref($link, $pref_name, 'true');
				}
			}
		}
	}

	function remove_feed($link, $id, $owner_uid) {

		if ($id > 0) {

			/* save starred articles in Archived feed */

			db_query($link, "BEGIN");

			/* prepare feed if necessary */

			$result = db_query($link, "SELECT id FROM ttrss_archived_feeds
				WHERE id = '$id'");

			if (db_num_rows($result) == 0) {
				db_query($link, "INSERT INTO ttrss_archived_feeds 
					(id, owner_uid, title, feed_url, site_url)
				SELECT id, owner_uid, title, feed_url, site_url from ttrss_feeds
			  	WHERE id = '$id'");
			}

			db_query($link, "UPDATE ttrss_user_entries SET feed_id = NULL,
				orig_feed_id = '$id' WHERE feed_id = '$id' AND 
					marked = true AND owner_uid = $owner_uid");

			/* Remove access key for the feed */

			db_query($link, "DELETE FROM ttrss_access_keys WHERE
				feed_id = '$id' AND owner_uid = $owner_uid");

			/* remove the feed */

			db_query($link, "DELETE FROM ttrss_feeds 
					WHERE id = '$id' AND owner_uid = $owner_uid");

			db_query($link, "COMMIT");

/*			if (file_exists(ICONS_DIR . "/$id.ico")) {
				unlink(ICONS_DIR . "/$id.ico");
			} */

			ccache_remove($link, $id, $owner_uid);

		} else {
			label_remove($link, -11-$id, $owner_uid);
			ccache_remove($link, -11-$id, $owner_uid);
		}
	}

	function add_feed_category($link, $feed_cat) {

		if (!$feed_cat) return false;

		db_query($link, "BEGIN");

		$result = db_query($link,
			"SELECT id FROM ttrss_feed_categories
			WHERE title = '$feed_cat' AND owner_uid = ".$_SESSION["uid"]);

		if (db_num_rows($result) == 0) {
			
			$result = db_query($link,
				"INSERT INTO ttrss_feed_categories (owner_uid,title) 
				VALUES ('".$_SESSION["uid"]."', '$feed_cat')");

			db_query($link, "COMMIT");

			return true;
		}

		return false;
	}			

	function remove_feed_category($link, $id, $owner_uid) {

		db_query($link, "DELETE FROM ttrss_feed_categories
			WHERE id = '$id' AND owner_uid = $owner_uid");

		ccache_remove($link, $id, $owner_uid, true);
	}

	function archive_article($link, $id, $owner_uid) {
		db_query($link, "BEGIN");

		$result = db_query($link, "SELECT feed_id FROM ttrss_user_entries
			WHERE ref_id = '$id' AND owner_uid = $owner_uid");

		if (db_num_rows($result) != 0) {

			/* prepare the archived table */

			$feed_id = (int) db_fetch_result($result, 0, "feed_id");

			if ($feed_id) {
				$result = db_query($link, "SELECT id FROM ttrss_archived_feeds
					WHERE id = '$feed_id'");

				if (db_num_rows($result) == 0) {
					db_query($link, "INSERT INTO ttrss_archived_feeds 
						(id, owner_uid, title, feed_url, site_url)
					SELECT id, owner_uid, title, feed_url, site_url from ttrss_feeds
				  	WHERE id = '$feed_id'");
				}

				db_query($link, "UPDATE ttrss_user_entries 
					SET orig_feed_id = feed_id, feed_id = NULL
					WHERE ref_id = '$id' AND owner_uid = " . $_SESSION["uid"]);
			}
		}

		db_query($link, "COMMIT");
	}

	function getArticleFeed($link, $id) {
		$result = db_query($link, "SELECT feed_id FROM ttrss_user_entries 
			WHERE ref_id = '$id' AND owner_uid = " . $_SESSION["uid"]);

		if (db_num_rows($result) != 0) {
			return db_fetch_result($result, 0, "feed_id");
		} else {
			return 0;
		}
	}

	function make_url_from_parts($parts) {
		$url = $parts['scheme'] . '://' . $parts['host'];

		if ($parts['path']) $url .= $parts['path'];
		if ($parts['query']) $url .= '?' . $parts['query'];

		return $url;
	}

	/**
	 * Fixes incomplete URLs by prepending "http://".
	 * Also replaces feed:// with http://, and
	 * prepends a trailing slash if the url is a domain name only.
	 *
	 * @param string $url Possibly incomplete URL
	 *
	 * @return string Fixed URL.
	 */
	function fix_url($url) {
		if (strpos($url, '://') === false) {
			$url = 'http://' . $url;
		} else if (substr($url, 0, 5) == 'feed:') {
			$url = 'http:' . substr($url, 5);
		}

		//prepend slash if the URL has no slash in it
		// "http://www.example" -> "http://www.example/"
		if (strpos($url, '/', strpos($url, ':') + 3) === false) {
			$url .= '/';
		}
		return $url;
	}

	function validate_feed_url($url) {
		$parts = parse_url($url);

		return ($parts['scheme'] == 'http' || $parts['scheme'] == 'feed' || $parts['scheme'] == 'https');

	}

	function get_article_enclosures($link, $id) {

		global $memcache;

		$query = "SELECT * FROM ttrss_enclosures 
			WHERE post_id = '$id' AND content_url != ''";

		$obj_id = md5("ENCLOSURES:$id");

		$rv = array();

		if ($memcache && $obj = $memcache->get($obj_id)) {
			$rv = $obj;
		} else {
			$result = db_query($link, $query);

			if (db_num_rows($result) > 0) {
				while ($line = db_fetch_assoc($result)) {
					array_push($rv, $line);
				}
				if ($memcache) $memcache->add($obj_id, $rv, 0, 3600);
			}
		}

		return $rv;
	}

	function api_get_feeds($link, $cat_id, $unread_only, $limit, $offset) {

			$feeds = array();

			/* Labels */

			if ($cat_id == -4 || $cat_id == -2) {
				$counters = getLabelCounters($link, true);

				foreach (array_values($counters) as $cv) {

					$unread = $cv["counter"];
	
					if ($unread || !$unread_only) {
	
						$row = array(
								"id" => $cv["id"],
								"title" => $cv["description"],
								"unread" => $cv["counter"],
								"cat_id" => -2,
							);
	
						array_push($feeds, $row);
					}
				}
			}

			/* Virtual feeds */

			if ($cat_id == -4 || $cat_id == -1) {
				foreach (array(-1, -2, -3, -4, 0) as $i) {
					$unread = getFeedUnread($link, $i);

					if ($unread || !$unread_only) {
						$title = getFeedTitle($link, $i);

						$row = array(
								"id" => $i,
								"title" => $title,
								"unread" => $unread,
								"cat_id" => -1,
							);
						array_push($feeds, $row);
					}

				}			
			}

			/* Real feeds */

			if ($limit) {
				$limit_qpart = "LIMIT $limit OFFSET $offset";
			} else {
				$limit_qpart = "";
			}

			if ($cat_id == -4 || $cat_id == -3) {
				$result = db_query($link, "SELECT 
					id, feed_url, cat_id, title, ".
						SUBSTRING_FOR_DATE."(last_updated,1,19) AS last_updated
						FROM ttrss_feeds WHERE owner_uid = " . $_SESSION["uid"] . 
						" ORDER BY cat_id, title " . $limit_qpart);
			} else {

				if ($cat_id)
					$cat_qpart = "cat_id = '$cat_id'";
				else
					$cat_qpart = "cat_id IS NULL";

				$result = db_query($link, "SELECT 
					id, feed_url, cat_id, title, ".
						SUBSTRING_FOR_DATE."(last_updated,1,19) AS last_updated
						FROM ttrss_feeds WHERE 
						$cat_qpart AND owner_uid = " . $_SESSION["uid"] . 
						" ORDER BY cat_id, title " . $limit_qpart);
			}

			while ($line = db_fetch_assoc($result)) {

				$unread = getFeedUnread($link, $line["id"]);

				$has_icon = feed_has_icon($line['id']);

				if ($unread || !$unread_only) {

					$row = array(
							"feed_url" => $line["feed_url"],
							"title" => $line["title"],
							"id" => (int)$line["id"],
							"unread" => (int)$unread,
							"has_icon" => $has_icon,
							"cat_id" => (int)$line["cat_id"],
							"last_updated" => strtotime($line["last_updated"])
						);
	
					array_push($feeds, $row);
				}
			}

		return $feeds;
	}

	function api_get_headlines($link, $feed_id, $limit, $offset,
					$filter, $is_cat, $show_excerpt, $show_content, $view_mode, $order) {

			/* do not rely on params below */

			$search = db_escape_string($_REQUEST["search"]);
			$search_mode = db_escape_string($_REQUEST["search_mode"]);
			$match_on = db_escape_string($_REQUEST["match_on"]);
			
			$qfh_ret = queryFeedHeadlines($link, $feed_id, $limit, 
				$view_mode, $is_cat, $search, $search_mode, $match_on,
				$order, $offset);

			$result = $qfh_ret[0];
			$feed_title = $qfh_ret[1];

			$headlines = array();

			while ($line = db_fetch_assoc($result)) {
				$is_updated = ($line["last_read"] == "" && 
					($line["unread"] != "t" && $line["unread"] != "1"));

				$headline_row = array(
						"id" => (int)$line["id"],
						"unread" => sql_bool_to_bool($line["unread"]),
						"marked" => sql_bool_to_bool($line["marked"]),
						"published" => sql_bool_to_bool($line["published"]),
						"updated" => strtotime($line["updated"]),
						"is_updated" => $is_updated,
						"title" => $line["title"],
						"link" => $line["link"],
						"feed_id" => $line["feed_id"],
						"tags" => get_article_tags($link, $line["id"]),
					);

				if ($show_excerpt) {
					$excerpt = truncate_string(strip_tags($line["content_preview"]), 100);
					$headline_row["excerpt"] = $excerpt;
				}

				if ($show_content) {
					$headline_row["content"] = $line["content_preview"];
				}

				array_push($headlines, $headline_row);
			}

			return $headlines;
	}

	function generate_dashboard_feed($link) {
		print "<headlines id=\"-5\" is_cat=\"\">";

		print '<![CDATA[<div id="headlinesContainer">';

		print "<div class='whiteBox'>".__('No feed selected.');

		print "<p class=\"small\"><span class=\"insensitive\">";

		$result = db_query($link, "SELECT ".SUBSTRING_FOR_DATE."(MAX(last_updated), 1, 19) AS last_updated FROM ttrss_feeds
			WHERE owner_uid = " . $_SESSION['uid']);

		$last_updated = db_fetch_result($result, 0, "last_updated");
		$last_updated = make_local_datetime($link, $last_updated, false);

		printf(__("Feeds last updated at %s"), $last_updated);

		$result = db_query($link, "SELECT COUNT(id) AS num_errors
			FROM ttrss_feeds WHERE last_error != '' AND owner_uid = ".$_SESSION["uid"]);

		$num_errors = db_fetch_result($result, 0, "num_errors");

		if ($num_errors > 0) {
			print "<br/>";
			print "<a class=\"insensitive\" href=\"#\" onclick=\"showFeedsWithErrors()\">".
				__('Some feeds have update errors (click for details)')."</a>";
		}
		print "</span></p>";

		print "</div>]]>";
		print "</headlines>";

		print "<headlines-info><![CDATA[";

		$info = array("count" => 0,
			"vgroup_last_feed" => '',
			"unread" => 0,
			"disable_cache" => true);

		print json_encode($info);

		print "]]></headlines-info>";

	}

	function save_email_address($link, $email) {
		// FIXME: implement persistent storage of emails

		if (!$_SESSION['stored_emails']) 
			$_SESSION['stored_emails'] = array();

		if (!in_array($email, $_SESSION['stored_emails']))
			array_push($_SESSION['stored_emails'], $email);
	}

	function update_feed_access_key($link, $feed_id, $is_cat, $owner_uid = false) {
		if (!$owner_uid) $owner_uid = $_SESSION["uid"];

		$sql_is_cat = bool_to_sql_bool($is_cat);

		$result = db_query($link, "SELECT access_key FROM ttrss_access_keys 
			WHERE feed_id = '$feed_id'	AND is_cat = $sql_is_cat 
			AND owner_uid = " . $owner_uid);

		if (db_num_rows($result) == 1) {
			$key = db_escape_string(sha1(uniqid(rand(), true)));

			db_query($link, "UPDATE ttrss_access_keys SET access_key = '$key'
				WHERE feed_id = '$feed_id' AND is_cat = $sql_is_cat 
				AND owner_uid = " . $owner_uid);

			return $key;

		} else {
			return get_feed_access_key($link, $feed_id, $is_cat, $owner_uid);
		}
	}

	function get_feed_access_key($link, $feed_id, $is_cat, $owner_uid = false) {

		if (!$owner_uid) $owner_uid = $_SESSION["uid"];

		$sql_is_cat = bool_to_sql_bool($is_cat);

		$result = db_query($link, "SELECT access_key FROM ttrss_access_keys 
			WHERE feed_id = '$feed_id'	AND is_cat = $sql_is_cat 
			AND owner_uid = " . $owner_uid);

		if (db_num_rows($result) == 1) {
			return db_fetch_result($result, 0, "access_key");
		} else {
			$key = db_escape_string(sha1(uniqid(rand(), true)));

			$result = db_query($link, "INSERT INTO ttrss_access_keys 
				(access_key, feed_id, is_cat, owner_uid)
				VALUES ('$key', '$feed_id', $sql_is_cat, '$owner_uid')");

			return $key;
		}
		return false;
	}

	/**
	 * Extracts RSS/Atom feed URLs from the given HTML URL.
	 *
	 * @param string $url HTML page URL
	 *
	 * @return array Array of feeds. Key is the full URL, value the title
	 */
	function get_feeds_from_html($url)
	{
		$url     = fix_url($url);
		$baseUrl = substr($url, 0, strrpos($url, '/') + 1);

		libxml_use_internal_errors(true);

		$doc = new DOMDocument();
		$doc->loadHTMLFile($url);
		$xpath = new DOMXPath($doc);
		$entries = $xpath->query('/html/head/link[@rel="alternate"]');
		$feedUrls = array();
		foreach ($entries as $entry) {
			if ($entry->hasAttribute('href')) {
				$title = $entry->getAttribute('title');
				if ($title == '') {
					$title = $entry->getAttribute('type');
				}
				$feedUrl = rewrite_relative_url(
					$baseUrl, $entry->getAttribute('href')
				);
				$feedUrls[$feedUrl] = $title;
			}
		}
		return $feedUrls;
	}

	/**
	 * Checks if the content behind the given URL is a HTML file
	 *
	 * @param string $url URL to check
	 *
	 * @return boolean True if the URL contains HTML content
	 */
	function url_is_html($url) {
		$content = substr(fetch_file_contents($url, false), 0, 1000);
		if (stripos($content, '<html>') === false
			&& stripos($content, '<html ') === false
		) {
			return false;
		}

		return true;
	}

	function print_label_select($link, $name, $value, $style = "") {

		$result = db_query($link, "SELECT caption FROM ttrss_labels2
			WHERE owner_uid = '".$_SESSION["uid"]."' ORDER BY caption");

		print "<select default=\"$value\" name=\"" . htmlspecialchars($name) . 
			"\" style=\"$style\" onchange=\"labelSelectOnChange(this)\" >";

		while ($line = db_fetch_assoc($result)) {

			$issel = ($line["caption"] == $value) ? "selected=\"1\"" : "";

			print "<option $issel>" . htmlspecialchars($line["caption"]) . "</option>";

		}

		print "<option value=\"ADD_LABEL\">" .__("Add label...") . "</option>";

		print "</select>";


	}

	function print_article_enclosures($link, $id, $always_display_enclosures, 
					$article_content) {

		$result = get_article_enclosures($link, $id);
	
		if (count($result) > 0) {
	
			$entries_html = array();
			$entries = array();
	
			foreach ($result as $line) {
	
				$url = $line["content_url"];
				$ctype = $line["content_type"];
	
				if (!$ctype) $ctype = __("unknown type");
	
				$filename = substr($url, strrpos($url, "/")+1);
	
				$entry = format_inline_player($link, $url, $ctype);
	
				$entry .= " <a target=\"_blank\" href=\"" . htmlspecialchars($url) . "\">" .
					$filename . " (" . $ctype . ")" . "</a>";
	
				array_push($entries_html, $entry);
	
				$entry = array();
	
				$entry["type"] = $ctype;
				$entry["filename"] = $filename;
				$entry["url"] = $url;
	
				array_push($entries, $entry);
			}
	
			print "<div class=\"postEnclosures\">";
	
			if (!get_pref($link, "STRIP_IMAGES")) {
				if ($always_display_enclosures ||
							!preg_match("/<img/i", $article_content)) {
								
					foreach ($entries as $entry) {
	
						if (preg_match("/image/", $entry["type"]) ||
								preg_match("/\.(jpg|png|gif|bmp)/i", $entry["filename"])) {
	
								print "<p><img
								alt=\"".htmlspecialchars($entry["filename"])."\"
								src=\"" .htmlspecialchars($entry["url"]) . "\"/></p>";
						}
					}
				}
			}
	
			if (count($entries) == 1) {
				print __("Attachment:") . " ";
			} else {
				print __("Attachments:") . " ";
			}
	
			print join(", ", $entries_html);
	
			print "</div>";
		}
	}

	function getLastArticleId($link) {
		$result = db_query($link, "SELECT MAX(ref_id) AS id FROM ttrss_user_entries
			WHERE owner_uid = " . $_SESSION["uid"]);

		if (db_num_rows($result) == 1) {
			return db_fetch_result($result, 0, "id");
		} else {
			return -1;
		}
	}

	function build_url($parts) {
		return $parts['scheme'] . "://" . $parts['host'] . $parts['path'];
	}

	/**
	 * Converts a (possibly) relative URL to a absolute one.
	 *
	 * @param string $url     Base URL (i.e. from where the document is)
	 * @param string $rel_url Possibly relative URL in the document
	 *
	 * @return string Absolute URL
	 */
	function rewrite_relative_url($url, $rel_url) {
		if (strpos($rel_url, "://") !== false) {
			return $rel_url;
		} else if (strpos($rel_url, "/") === 0) 
		{
			$parts = parse_url($url);
			$parts['path'] = $rel_url;

			return build_url($parts);

		} else {
			$parts = parse_url($url);
			if (!isset($parts['path'])) {
				$parts['path'] = '/';
			}
			$dir = $parts['path'];
			if (substr($dir, -1) !== '/') {
				$dir = dirname($parts['path']);
				$dir !== '/' && $dir .= '/';
			}
			$parts['path'] = $dir . $rel_url;

			return build_url($parts);
		}
	}

	function sphinx_search($query, $offset = 0, $limit = 30) {
		$sphinxClient = new SphinxClient();

		$sphinxClient->SetServer('localhost', 9312);
		$sphinxClient->SetConnectTimeout(1);

		$sphinxClient->SetFieldWeights(array('title' => 70, 'content' => 30, 
			'feed_title' => 20));

		$sphinxClient->SetMatchMode(SPH_MATCH_EXTENDED2);
		$sphinxClient->SetRankingMode(SPH_RANK_PROXIMITY_BM25);
		$sphinxClient->SetLimits($offset, $limit, 1000);
		$sphinxClient->SetArrayResult(false);
		$sphinxClient->SetFilter('owner_uid', array($_SESSION['uid']));
		
		$result = $sphinxClient->Query($query, SPHINX_INDEX);

		$ids = array();

		if (is_array($result['matches'])) {
			foreach (array_keys($result['matches']) as $int_id) {
				$ref_id = $result['matches'][$int_id]['attrs']['ref_id'];
				array_push($ids, $ref_id);
			}
		}

		return $ids;
	}

?>

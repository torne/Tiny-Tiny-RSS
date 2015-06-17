<?php
	define_default('DAEMON_UPDATE_LOGIN_LIMIT', 30);
	define_default('DAEMON_FEED_LIMIT', 500);
	define_default('DAEMON_SLEEP_INTERVAL', 120);
	define_default('_MIN_CACHE_IMAGE_SIZE', 1024);

	function calculate_article_hash($article, $pluginhost) {
		$tmp = "";

		foreach ($article as $k => $v) {
			if ($k != "feed" && isset($v)) {
				$tmp .= sha1("$k:" . (is_array($v) ? implode(",", $v) : $v));
			}
		}

		return sha1(implode(",", $pluginhost->get_plugin_names()) . $tmp);
	}

	function update_feedbrowser_cache() {

		$result = db_query("SELECT feed_url, site_url, title, COUNT(id) AS subscribers
	  		FROM ttrss_feeds WHERE (SELECT COUNT(id) = 0 FROM ttrss_feeds AS tf
				WHERE tf.feed_url = ttrss_feeds.feed_url
				AND (private IS true OR auth_login != '' OR auth_pass != '' OR feed_url LIKE '%:%@%/%'))
				GROUP BY feed_url, site_url, title ORDER BY subscribers DESC LIMIT 1000");

		db_query("BEGIN");

		db_query("DELETE FROM ttrss_feedbrowser_cache");

		$count = 0;

		while ($line = db_fetch_assoc($result)) {
			$subscribers = db_escape_string($line["subscribers"]);
			$feed_url = db_escape_string($line["feed_url"]);
			$title = db_escape_string($line["title"]);
			$site_url = db_escape_string($line["site_url"]);

			$tmp_result = db_query("SELECT subscribers FROM
				ttrss_feedbrowser_cache WHERE feed_url = '$feed_url'");

			if (db_num_rows($tmp_result) == 0) {

				db_query("INSERT INTO ttrss_feedbrowser_cache
					(feed_url, site_url, title, subscribers) VALUES ('$feed_url',
						'$site_url', '$title', '$subscribers')");

				++$count;

			}

		}

		db_query("COMMIT");

		return $count;

	}


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
	function update_daemon_common($limit = DAEMON_FEED_LIMIT, $from_http = false, $debug = true) {
		// Process all other feeds using last_updated and interval parameters

		$schema_version = get_schema_version();

		if ($schema_version != SCHEMA_VERSION) {
			die("Schema version is wrong, please upgrade the database.\n");
		}

		define('PREFS_NO_CACHE', true);

		// Test if the user has loggued in recently. If not, it does not update its feeds.
		if (!SINGLE_USER_MODE && DAEMON_UPDATE_LOGIN_LIMIT > 0) {
			if (DB_TYPE == "pgsql") {
				$login_thresh_qpart = "AND ttrss_users.last_login >= NOW() - INTERVAL '".DAEMON_UPDATE_LOGIN_LIMIT." days'";
			} else {
				$login_thresh_qpart = "AND ttrss_users.last_login >= DATE_SUB(NOW(), INTERVAL ".DAEMON_UPDATE_LOGIN_LIMIT." DAY)";
			}
		} else {
			$login_thresh_qpart = "";
		}

		// Test if the feed need a update (update interval exceeded).
		if (DB_TYPE == "pgsql") {
			$update_limit_qpart = "AND ((
					ttrss_feeds.update_interval = 0
					AND ttrss_user_prefs.value != '-1'
					AND ttrss_feeds.last_updated < NOW() - CAST((ttrss_user_prefs.value || ' minutes') AS INTERVAL)
				) OR (
					ttrss_feeds.update_interval > 0
					AND ttrss_feeds.last_updated < NOW() - CAST((ttrss_feeds.update_interval || ' minutes') AS INTERVAL)
				) OR (ttrss_feeds.last_updated IS NULL
					AND ttrss_user_prefs.value != '-1')
				OR (last_updated = '1970-01-01 00:00:00'
					AND ttrss_user_prefs.value != '-1'))";
		} else {
			$update_limit_qpart = "AND ((
					ttrss_feeds.update_interval = 0
					AND ttrss_user_prefs.value != '-1'
					AND ttrss_feeds.last_updated < DATE_SUB(NOW(), INTERVAL CONVERT(ttrss_user_prefs.value, SIGNED INTEGER) MINUTE)
				) OR (
					ttrss_feeds.update_interval > 0
					AND ttrss_feeds.last_updated < DATE_SUB(NOW(), INTERVAL ttrss_feeds.update_interval MINUTE)
				) OR (ttrss_feeds.last_updated IS NULL
					AND ttrss_user_prefs.value != '-1')
				OR (last_updated = '1970-01-01 00:00:00'
					AND ttrss_user_prefs.value != '-1'))";
		}

		// Test if feed is currently being updated by another process.
		if (DB_TYPE == "pgsql") {
			$updstart_thresh_qpart = "AND (ttrss_feeds.last_update_started IS NULL OR ttrss_feeds.last_update_started < NOW() - INTERVAL '10 minutes')";
		} else {
			$updstart_thresh_qpart = "AND (ttrss_feeds.last_update_started IS NULL OR ttrss_feeds.last_update_started < DATE_SUB(NOW(), INTERVAL 10 MINUTE))";
		}

		// Test if there is a limit to number of updated feeds
		$query_limit = "";
		if($limit) $query_limit = sprintf("LIMIT %d", $limit);

		$query = "SELECT DISTINCT ttrss_feeds.feed_url, ttrss_feeds.last_updated
			FROM
				ttrss_feeds, ttrss_users, ttrss_user_prefs
			WHERE
				ttrss_feeds.owner_uid = ttrss_users.id
				AND ttrss_user_prefs.profile IS NULL
				AND ttrss_users.id = ttrss_user_prefs.owner_uid
				AND ttrss_user_prefs.pref_name = 'DEFAULT_UPDATE_INTERVAL'
				$login_thresh_qpart $update_limit_qpart
				$updstart_thresh_qpart
				ORDER BY last_updated $query_limit";

		// We search for feed needing update.
		$result = db_query($query);

		if($debug) _debug(sprintf("Scheduled %d feeds to update...", db_num_rows($result)));

		// Here is a little cache magic in order to minimize risk of double feed updates.
		$feeds_to_update = array();
		while ($line = db_fetch_assoc($result)) {
			array_push($feeds_to_update, db_escape_string($line['feed_url']));
		}

		// We update the feed last update started date before anything else.
		// There is no lag due to feed contents downloads
		// It prevent an other process to update the same feed.

		if(count($feeds_to_update) > 0) {
			$feeds_quoted = array();

			foreach ($feeds_to_update as $feed) {
				array_push($feeds_quoted, "'" . db_escape_string($feed) . "'");
			}

			db_query(sprintf("UPDATE ttrss_feeds SET last_update_started = NOW()
				WHERE feed_url IN (%s)", implode(',', $feeds_quoted)));
		}

		$nf = 0;
		$bstarted = microtime(true);

		// For each feed, we call the feed update function.
		foreach ($feeds_to_update as $feed) {
			if($debug) _debug("Base feed: $feed");

			//update_rss_feed($line["id"], true);

			// since we have the data cached, we can deal with other feeds with the same url

			$tmp_result = db_query("SELECT DISTINCT ttrss_feeds.id,last_updated,ttrss_feeds.owner_uid
			FROM ttrss_feeds, ttrss_users, ttrss_user_prefs WHERE
				ttrss_user_prefs.owner_uid = ttrss_feeds.owner_uid AND
				ttrss_users.id = ttrss_user_prefs.owner_uid AND
				ttrss_user_prefs.pref_name = 'DEFAULT_UPDATE_INTERVAL' AND
				ttrss_user_prefs.profile IS NULL AND
				feed_url = '".db_escape_string($feed)."' AND
				(ttrss_feeds.update_interval > 0 OR
					ttrss_user_prefs.value != '-1')
				$login_thresh_qpart
			ORDER BY ttrss_feeds.id $query_limit");

			if (db_num_rows($tmp_result) > 0) {
				$rss = false;

				while ($tline = db_fetch_assoc($tmp_result)) {
					if($debug) _debug(" => " . $tline["last_updated"] . ", " . $tline["id"] . " " . $tline["owner_uid"]);

					$fstarted = microtime(true);
					$rss = update_rss_feed($tline["id"], true, false);
					_debug_suppress(false);

					_debug(sprintf("    %.4f (sec)", microtime(true) - $fstarted));

					++$nf;
				}
			}
		}

		if ($nf > 0) {
			_debug(sprintf("Processed %d feeds in %.4f (sec), %.4f (sec/feed avg)", $nf,
				microtime(true) - $bstarted, (microtime(true) - $bstarted) / $nf));
		}

		require_once "digest.php";

		// Send feed digests by email if needed.
		send_headlines_digests($debug);

		return $nf;

	} // function update_daemon_common

	// this is used when subscribing
	function set_basic_feed_info($feed) {

		$feed = db_escape_string($feed);

		$result = db_query("SELECT feed_url,auth_pass,auth_pass_encrypted
					FROM ttrss_feeds WHERE id = '$feed'");

		$auth_pass_encrypted = sql_bool_to_bool(db_fetch_result($result,
			0, "auth_pass_encrypted"));

		$auth_login = db_fetch_result($result, 0, "auth_login");
		$auth_pass = db_fetch_result($result, 0, "auth_pass");

		if ($auth_pass_encrypted) {
			require_once "crypt.php";
			$auth_pass = decrypt_string($auth_pass);
		}

		$fetch_url = db_fetch_result($result, 0, "feed_url");

		$feed_data = fetch_file_contents($fetch_url, false,
			$auth_login, $auth_pass, false,
			FEED_FETCH_TIMEOUT_TIMEOUT,
			0);

		global $fetch_curl_used;

		if (!$fetch_curl_used) {
			$tmp = @gzdecode($feed_data);

			if ($tmp) $feed_data = $tmp;
		}

		$feed_data = trim($feed_data);

		$rss = new FeedParser($feed_data);
		$rss->init();

		if (!$rss->error()) {

			$result = db_query("SELECT title, site_url FROM ttrss_feeds WHERE id = '$feed'");

			$registered_title = db_fetch_result($result, 0, "title");
			$orig_site_url = db_fetch_result($result, 0, "site_url");

			$site_url = db_escape_string(mb_substr(rewrite_relative_url($fetch_url, $rss->get_link()), 0, 245));
			$feed_title = db_escape_string(mb_substr($rss->get_title(), 0, 199));

			if ($feed_title && (!$registered_title || $registered_title == "[Unknown]")) {
				db_query("UPDATE ttrss_feeds SET
					title = '$feed_title' WHERE id = '$feed'");
			}

			if ($site_url && $orig_site_url != $site_url) {
				db_query("UPDATE ttrss_feeds SET
							site_url = '$site_url' WHERE id = '$feed'");
			}
		}
	}

	// ignore_daemon is not used
	function update_rss_feed($feed, $ignore_daemon = false, $no_cache = false, $rss = false) {

		$debug_enabled = defined('DAEMON_EXTENDED_DEBUG') || $_REQUEST['xdebug'];

		_debug_suppress(!$debug_enabled);
		_debug("start", $debug_enabled);

		$result = db_query("SELECT id,update_interval,auth_login,
			feed_url,auth_pass,cache_images,
			mark_unread_on_update, owner_uid,
			pubsub_state, auth_pass_encrypted,
			(SELECT max(date_entered) FROM
				ttrss_entries, ttrss_user_entries where ref_id = id AND feed_id = '$feed') AS last_article_timestamp
			FROM ttrss_feeds WHERE id = '$feed'");

		if (db_num_rows($result) == 0) {
			_debug("feed $feed NOT FOUND/SKIPPED", $debug_enabled);
			return false;
		}

		$last_article_timestamp = @strtotime(db_fetch_result($result, 0, "last_article_timestamp"));

		if (defined('_DISABLE_HTTP_304'))
			$last_article_timestamp = 0;

		$owner_uid = db_fetch_result($result, 0, "owner_uid");
		$mark_unread_on_update = sql_bool_to_bool(db_fetch_result($result,
			0, "mark_unread_on_update"));
		$pubsub_state = db_fetch_result($result, 0, "pubsub_state");
		$auth_pass_encrypted = sql_bool_to_bool(db_fetch_result($result,
			0, "auth_pass_encrypted"));

		db_query("UPDATE ttrss_feeds SET last_update_started = NOW()
			WHERE id = '$feed'");

		$auth_login = db_fetch_result($result, 0, "auth_login");
		$auth_pass = db_fetch_result($result, 0, "auth_pass");

		if ($auth_pass_encrypted) {
			require_once "crypt.php";
			$auth_pass = decrypt_string($auth_pass);
		}

		$cache_images = sql_bool_to_bool(db_fetch_result($result, 0, "cache_images"));
		$fetch_url = db_fetch_result($result, 0, "feed_url");

		$feed = db_escape_string($feed);

		$date_feed_processed = date('Y-m-d H:i');

		$cache_filename = CACHE_DIR . "/simplepie/" . sha1($fetch_url) . ".xml";

		$pluginhost = new PluginHost();
		$pluginhost->set_debug($debug_enabled);
		$user_plugins = get_pref("_ENABLED_PLUGINS", $owner_uid);

		$pluginhost->load(PLUGINS, PluginHost::KIND_ALL);
		$pluginhost->load($user_plugins, PluginHost::KIND_USER, $owner_uid);
		$pluginhost->load_data();

		if ($rss && is_object($rss) && get_class($rss) == "FeedParser") {
			_debug("using previously initialized parser object");
		} else {
			$rss_hash = false;

			$force_refetch = isset($_REQUEST["force_refetch"]);

			foreach ($pluginhost->get_hooks(PluginHost::HOOK_FETCH_FEED) as $plugin) {
				$feed_data = $plugin->hook_fetch_feed($feed_data, $fetch_url, $owner_uid, $feed, $last_article_timestamp, $auth_login, $auth_pass);
			}

			// try cache
			if (!$feed_data &&
				file_exists($cache_filename) &&
				is_readable($cache_filename) &&
				!$auth_login && !$auth_pass &&
				filemtime($cache_filename) > time() - 30) {

				_debug("using local cache [$cache_filename].", $debug_enabled);

				@$feed_data = file_get_contents($cache_filename);

				if ($feed_data) {
					$rss_hash = sha1($feed_data);
				}

			} else {
				_debug("local cache will not be used for this feed", $debug_enabled);
			}

			// fetch feed from source
			if (!$feed_data) {
				_debug("fetching [$fetch_url]...", $debug_enabled);
				_debug("If-Modified-Since: ".gmdate('D, d M Y H:i:s \G\M\T', $last_article_timestamp), $debug_enabled);

				$feed_data = fetch_file_contents($fetch_url, false,
					$auth_login, $auth_pass, false,
					$no_cache ? FEED_FETCH_NO_CACHE_TIMEOUT : FEED_FETCH_TIMEOUT,
					$force_refetch ? 0 : $last_article_timestamp);

				global $fetch_curl_used;

				if (!$fetch_curl_used) {
					$tmp = @gzdecode($feed_data);

					if ($tmp) $feed_data = $tmp;
				}

				$feed_data = trim($feed_data);

				_debug("fetch done.", $debug_enabled);

				// cache vanilla feed data for re-use
				if ($feed_data && !$auth_pass && !$auth_login && is_writable(CACHE_DIR . "/simplepie")) {
					$new_rss_hash = sha1($feed_data);

					if ($new_rss_hash != $rss_hash) {
						_debug("saving $cache_filename", $debug_enabled);
						@file_put_contents($cache_filename, $feed_data);
					}
				}
			}

			if (!$feed_data) {
				global $fetch_last_error;
				global $fetch_last_error_code;

				_debug("unable to fetch: $fetch_last_error [$fetch_last_error_code]", $debug_enabled);

				$error_escaped = '';

				// If-Modified-Since
				if ($fetch_last_error_code != 304) {
					$error_escaped = db_escape_string($fetch_last_error);
				} else {
					_debug("source claims data not modified, nothing to do.", $debug_enabled);
				}

				db_query(
					"UPDATE ttrss_feeds SET last_error = '$error_escaped',
						last_updated = NOW() WHERE id = '$feed'");

				return;
			}
		}

		foreach ($pluginhost->get_hooks(PluginHost::HOOK_FEED_FETCHED) as $plugin) {
			$feed_data = $plugin->hook_feed_fetched($feed_data, $fetch_url, $owner_uid, $feed);
		}

		// set last update to now so if anything *simplepie* crashes later we won't be
		// continuously failing on the same feed
		//db_query("UPDATE ttrss_feeds SET last_updated = NOW() WHERE id = '$feed'");

		if (!$rss) {
			$rss = new FeedParser($feed_data);
			$rss->init();
		}

		if (DETECT_ARTICLE_LANGUAGE) {
			require_once "lib/languagedetect/LanguageDetect.php";

			$lang = new Text_LanguageDetect();
			$lang->setNameMode(2);
		}

//		print_r($rss);

		$feed = db_escape_string($feed);

		if (!$rss->error()) {

			// We use local pluginhost here because we need to load different per-user feed plugins
			$pluginhost->run_hooks(PluginHost::HOOK_FEED_PARSED, "hook_feed_parsed", $rss);

			_debug("processing feed data...", $debug_enabled);

//			db_query("BEGIN");

			if (DB_TYPE == "pgsql") {
				$favicon_interval_qpart = "favicon_last_checked < NOW() - INTERVAL '12 hour'";
			} else {
				$favicon_interval_qpart = "favicon_last_checked < DATE_SUB(NOW(), INTERVAL 12 HOUR)";
			}

			$result = db_query("SELECT owner_uid,favicon_avg_color,
				(favicon_last_checked IS NULL OR $favicon_interval_qpart) AS
						favicon_needs_check
				FROM ttrss_feeds WHERE id = '$feed'");

			$favicon_needs_check = sql_bool_to_bool(db_fetch_result($result, 0,
				"favicon_needs_check"));
			$favicon_avg_color = db_fetch_result($result, 0, "favicon_avg_color");

			$owner_uid = db_fetch_result($result, 0, "owner_uid");

			$site_url = db_escape_string(mb_substr(rewrite_relative_url($fetch_url, $rss->get_link()), 0, 245));

			_debug("site_url: $site_url", $debug_enabled);
			_debug("feed_title: " . $rss->get_title(), $debug_enabled);

			if ($favicon_needs_check || $force_refetch) {

				/* terrible hack: if we crash on floicon shit here, we won't check
				 * the icon avgcolor again (unless the icon got updated) */

				$favicon_file = ICONS_DIR . "/$feed.ico";
				$favicon_modified = @filemtime($favicon_file);

				_debug("checking favicon...", $debug_enabled);

				check_feed_favicon($site_url, $feed);
				$favicon_modified_new = @filemtime($favicon_file);

				if ($favicon_modified_new > $favicon_modified)
					$favicon_avg_color = '';

				if (file_exists($favicon_file) && function_exists("imagecreatefromstring") && $favicon_avg_color == '') {
						require_once "colors.php";

						db_query("UPDATE ttrss_feeds SET favicon_avg_color = 'fail' WHERE
							id = '$feed'");

						$favicon_color = db_escape_string(
							calculate_avg_color($favicon_file));

						$favicon_colorstring = ",favicon_avg_color = '".$favicon_color."'";
				} else if ($favicon_avg_color == 'fail') {
					_debug("floicon failed on this file, not trying to recalculate avg color", $debug_enabled);
				}

				db_query("UPDATE ttrss_feeds SET favicon_last_checked = NOW()
					$favicon_colorstring
					WHERE id = '$feed'");
			}

			_debug("loading filters & labels...", $debug_enabled);

			$filters = load_filters($feed, $owner_uid);

			_debug("" . count($filters) . " filters loaded.", $debug_enabled);

			$items = $rss->get_items();

			if (!is_array($items)) {
				_debug("no articles found.", $debug_enabled);

				db_query("UPDATE ttrss_feeds
					SET last_updated = NOW(), last_error = '' WHERE id = '$feed'");

				return; // no articles
			}

			if ($pubsub_state != 2 && PUBSUBHUBBUB_ENABLED) {

				_debug("checking for PUSH hub...", $debug_enabled);

				$feed_hub_url = false;

				$links = $rss->get_links('hub');

				if ($links && is_array($links)) {
					foreach ($links as $l) {
						$feed_hub_url = $l;
						break;
					}
				}

				_debug("feed hub url: $feed_hub_url", $debug_enabled);

				$feed_self_url = $fetch_url;

				$links = $rss->get_links('self');

				if ($links && is_array($links)) {
					foreach ($links as $l) {
						$feed_self_url = $l;
						break;
					}
				}

				_debug("feed self url = $feed_self_url");

				if ($feed_hub_url && $feed_self_url && function_exists('curl_init') &&
					!ini_get("open_basedir")) {

					require_once 'lib/pubsubhubbub/subscriber.php';

					$callback_url = get_self_url_prefix() .
						"/public.php?op=pubsub&id=$feed";

					$s = new Subscriber($feed_hub_url, $callback_url);

					$rc = $s->subscribe($feed_self_url);

					_debug("feed hub url found, subscribe request sent. [rc=$rc]", $debug_enabled);

					db_query("UPDATE ttrss_feeds SET pubsub_state = 1
						WHERE id = '$feed'");
				}
			}

			_debug("processing articles...", $debug_enabled);

			foreach ($items as $item) {
				if ($_REQUEST['xdebug'] == 3) {
					print_r($item);
				}

				$entry_guid = $item->get_id();
				if (!$entry_guid) $entry_guid = $item->get_link();
				if (!$entry_guid) $entry_guid = make_guid_from_title($item->get_title());
				if (!$entry_guid) continue;

				$entry_guid = "$owner_uid,$entry_guid";

				$entry_guid_hashed = db_escape_string('SHA1:' . sha1($entry_guid));

				_debug("guid $entry_guid / $entry_guid_hashed", $debug_enabled);

				$entry_timestamp = "";

				$entry_timestamp = $item->get_date();

				_debug("orig date: " . $item->get_date(), $debug_enabled);

				if ($entry_timestamp == -1 || !$entry_timestamp || $entry_timestamp > time()) {
					$entry_timestamp = time();
				}

				$entry_timestamp_fmt = strftime("%Y/%m/%d %H:%M:%S", $entry_timestamp);

				_debug("date $entry_timestamp [$entry_timestamp_fmt]", $debug_enabled);

//				$entry_title = html_entity_decode($item->get_title(), ENT_COMPAT, 'UTF-8');
//				$entry_title = decode_numeric_entities($entry_title);
				$entry_title = $item->get_title();

				$entry_link = rewrite_relative_url($site_url, $item->get_link());

				_debug("title $entry_title", $debug_enabled);
				_debug("link $entry_link", $debug_enabled);

				if (!$entry_title) $entry_title = date("Y-m-d H:i:s", $entry_timestamp);;

				$entry_content = $item->get_content();
				if (!$entry_content) $entry_content = $item->get_description();

				if ($_REQUEST["xdebug"] == 2) {
					print "content: ";
					print $entry_content;
					print "\n";
				}

				$entry_language = "";

				if (DETECT_ARTICLE_LANGUAGE) {
					$entry_language = $lang->detect($entry_title . " " . $entry_content, 1);

					if (count($entry_language) > 0) {
						$possible = array_keys($entry_language);
						$entry_language = $possible[0];

						_debug("detected language: $entry_language", $debug_enabled);
					} else {
						$entry_language = "";
					}
				}

				$entry_comments = $item->get_comments_url();
				$entry_author = $item->get_author();

				$entry_guid = db_escape_string(mb_substr($entry_guid, 0, 245));

				$entry_comments = db_escape_string(mb_substr(trim($entry_comments), 0, 245));
				$entry_author = db_escape_string(mb_substr(trim($entry_author), 0, 245));

				$num_comments = (int) $item->get_comments_count();

				_debug("author $entry_author", $debug_enabled);
				_debug("num_comments: $num_comments", $debug_enabled);
				_debug("looking for tags...", $debug_enabled);

				// parse <category> entries into tags

				$additional_tags = array();

				$additional_tags_src = $item->get_categories();

				if (is_array($additional_tags_src)) {
					foreach ($additional_tags_src as $tobj) {
						array_push($additional_tags, $tobj);
					}
				}

				$entry_tags = array_unique($additional_tags);

				for ($i = 0; $i < count($entry_tags); $i++)
					$entry_tags[$i] = mb_strtolower($entry_tags[$i], 'utf-8');

				_debug("tags found: " . join(",", $entry_tags), $debug_enabled);

				_debug("done collecting data.", $debug_enabled);

				$result = db_query("SELECT id, content_hash FROM ttrss_entries
					WHERE guid = '".db_escape_string($entry_guid)."' OR guid = '$entry_guid_hashed'");

				if (db_num_rows($result) != 0) {
					$base_entry_id = db_fetch_result($result, 0, "id");
					$entry_stored_hash = db_fetch_result($result, 0, "content_hash");
					$article_labels = get_article_labels($base_entry_id, $owner_uid);
				} else {
					$base_entry_id = false;
					$entry_stored_hash = "";
					$article_labels = array();
				}

				$article = array("owner_uid" => $owner_uid, // read only
					"guid" => $entry_guid, // read only
					"guid_hashed" => $entry_guid_hashed, // read only
					"title" => $entry_title,
					"content" => $entry_content,
					"link" => $entry_link,
					"labels" => $article_labels, // current limitation: can add labels to article, can't remove them
					"tags" => $entry_tags,
					"author" => $entry_author,
					"force_catchup" => false, // ugly hack for the time being
					"score_modifier" => 0, // no previous value, plugin should recalculate score modifier based on content if needed
					"language" => $entry_language, // read only
					"feed" => array("id" => $feed,
						"fetch_url" => $fetch_url,
						"site_url" => $site_url)
					);

				$entry_plugin_data = "";
				$entry_current_hash = calculate_article_hash($article, $pluginhost);

				_debug("article hash: $entry_current_hash [stored=$entry_stored_hash]", $debug_enabled);

				if ($entry_current_hash == $entry_stored_hash && !isset($_REQUEST["force_rehash"])) {
					_debug("stored article seems up to date [IID: $base_entry_id], updating timestamp only", $debug_enabled);

					// we keep encountering the entry in feeds, so we need to
					// update date_updated column so that we don't get horrible
					// dupes when the entry gets purged and reinserted again e.g.
					// in the case of SLOW SLOW OMG SLOW updating feeds

					$base_entry_id = db_fetch_result($result, 0, "id");

					db_query("UPDATE ttrss_entries SET date_updated = NOW()
						WHERE id = '$base_entry_id'");

                    // if we allow duplicate posts, we have to continue to
                    // create the user entries for this feed
                    if (!get_pref("ALLOW_DUPLICATE_POSTS", $owner_uid, false)) {
                        continue;
                    }
				}

				_debug("hash differs, applying plugin filters:", $debug_enabled);

				foreach ($pluginhost->get_hooks(PluginHost::HOOK_ARTICLE_FILTER) as $plugin) {
					_debug("... " . get_class($plugin), $debug_enabled);

					$start = microtime(true);
					$article = $plugin->hook_article_filter($article);

					_debug("=== " . sprintf("%.4f (sec)", microtime(true) - $start), $debug_enabled);

					$entry_plugin_data .= mb_strtolower(get_class($plugin)) . ",";
				}

				$entry_plugin_data = db_escape_string($entry_plugin_data);

				_debug("plugin data: $entry_plugin_data", $debug_enabled);

				// Workaround: 4-byte unicode requires utf8mb4 in MySQL. See https://tt-rss.org/forum/viewtopic.php?f=1&t=3377&p=20077#p20077
				if (DB_TYPE == "mysql") {
					foreach ($article as $k => $v) {
						$article[$k] = preg_replace('/[\x{10000}-\x{10FFFF}]/u', "\xEF\xBF\xBD", $v);
					}
				}

				$entry_tags = $article["tags"];
				$entry_guid = db_escape_string($entry_guid);
				$entry_title = db_escape_string($article["title"]);
				$entry_author = db_escape_string($article["author"]);
				$entry_link = db_escape_string($article["link"]);
				$entry_content = $article["content"]; // escaped below
				$entry_force_catchup = $article["force_catchup"];
				$article_labels = $article["labels"];
				$entry_score_modifier = (int) $article["score_modifier"];

				if ($debug_enabled) {
					_debug("article labels:", $debug_enabled);
					print_r($article_labels);
				}

				_debug("force catchup: $entry_force_catchup");

				if ($cache_images && is_writable(CACHE_DIR . '/images'))
					cache_images($entry_content, $site_url, $debug_enabled);

				$entry_content = db_escape_string($entry_content, false);

				db_query("BEGIN");

				$result = db_query("SELECT id FROM	ttrss_entries
					WHERE (guid = '$entry_guid' OR guid = '$entry_guid_hashed')");

				if (db_num_rows($result) == 0) {

					_debug("base guid [$entry_guid] not found", $debug_enabled);

					// base post entry does not exist, create it

					$result = db_query(
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
							plugin_data,
							lang,
							author)
						VALUES
							('$entry_title',
							'$entry_guid_hashed',
							'$entry_link',
							'$entry_timestamp_fmt',
							'$entry_content',
							'$entry_current_hash',
							false,
							NOW(),
							'$date_feed_processed',
							'$entry_comments',
							'$num_comments',
							'$entry_plugin_data',
							'$entry_language',
							'$entry_author')");

				} else {
					$base_entry_id = db_fetch_result($result, 0, "id");
				}

				// now it should exist, if not - bad luck then

				$result = db_query("SELECT id FROM ttrss_entries
					WHERE guid = '$entry_guid' OR guid = '$entry_guid_hashed'");

				$entry_ref_id = 0;
				$entry_int_id = 0;

				if (db_num_rows($result) == 1) {

					_debug("base guid found, checking for user record", $debug_enabled);

					$ref_id = db_fetch_result($result, 0, "id");
					$entry_ref_id = $ref_id;

					/* $stored_guid = db_fetch_result($result, 0, "guid");
					if ($stored_guid != $entry_guid_hashed) {
						if ($debug_enabled) _debug("upgrading compat guid to hashed one", $debug_enabled);

						db_query("UPDATE ttrss_entries SET guid = '$entry_guid_hashed' WHERE
							id = '$ref_id'");
					} */

					// check for user post link to main table

					// do we allow duplicate posts with same GUID in different feeds?
					if (get_pref("ALLOW_DUPLICATE_POSTS", $owner_uid, false)) {
						$dupcheck_qpart = "AND (feed_id = '$feed' OR feed_id IS NULL)";
					} else {
						$dupcheck_qpart = "";
					}

					/* Collect article tags here so we could filter by them: */

					$article_filters = get_article_filters($filters, $entry_title,
						$entry_content, $entry_link, $entry_timestamp, $entry_author,
						$entry_tags);

					if ($debug_enabled) {
						_debug("article filters: ", $debug_enabled);
						if (count($article_filters) != 0) {
							print_r($article_filters);
						}
					}

					if (find_article_filter($article_filters, "filter")) {
						db_query("COMMIT"); // close transaction in progress
						continue;
					}

					$score = calculate_article_score($article_filters) + $entry_score_modifier;

					_debug("initial score: $score [including plugin modifier: $entry_score_modifier]", $debug_enabled);

					$query = "SELECT ref_id, int_id FROM ttrss_user_entries WHERE
							ref_id = '$ref_id' AND owner_uid = '$owner_uid'
							$dupcheck_qpart";

//					if ($_REQUEST["xdebug"]) print "$query\n";

					$result = db_query($query);

					// okay it doesn't exist - create user entry
					if (db_num_rows($result) == 0) {

						_debug("user record not found, creating...", $debug_enabled);

						if ($score >= -500 && !find_article_filter($article_filters, 'catchup') && !$entry_force_catchup) {
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

						// N-grams

						/* if (DB_TYPE == "pgsql" and defined('_NGRAM_TITLE_DUPLICATE_THRESHOLD')) {

							$result = db_query("SELECT COUNT(*) AS similar FROM
									ttrss_entries,ttrss_user_entries
								WHERE ref_id = id AND updated >= NOW() - INTERVAL '7 day'
									AND similarity(title, '$entry_title') >= "._NGRAM_TITLE_DUPLICATE_THRESHOLD."
									AND owner_uid = $owner_uid");

							$ngram_similar = db_fetch_result($result, 0, "similar");

							_debug("N-gram similar results: $ngram_similar", $debug_enabled);

							if ($ngram_similar > 0) {
								$unread = 'false';
							}
						} */

						$last_marked = ($marked == 'true') ? 'NOW()' : 'NULL';
						$last_published = ($published == 'true') ? 'NOW()' : 'NULL';

						$result = db_query(
							"INSERT INTO ttrss_user_entries
								(ref_id, owner_uid, feed_id, unread, last_read, marked,
								published, score, tag_cache, label_cache, uuid,
								last_marked, last_published)
							VALUES ('$ref_id', '$owner_uid', '$feed', $unread,
								$last_read_qpart, $marked, $published, '$score', '', '',
								'', $last_marked, $last_published)");

						if (PUBSUBHUBBUB_HUB && $published == 'true') {
							$rss_link = get_self_url_prefix() .
								"/public.php?op=rss&id=-2&key=" .
								get_feed_access_key(-2, false, $owner_uid);

							$p = new Publisher(PUBSUBHUBBUB_HUB);

							/* $pubsub_result = */ $p->publish_update($rss_link);
						}

						$result = db_query(
							"SELECT int_id FROM ttrss_user_entries WHERE
								ref_id = '$ref_id' AND owner_uid = '$owner_uid' AND
								feed_id = '$feed' LIMIT 1");

						if (db_num_rows($result) == 1) {
							$entry_int_id = db_fetch_result($result, 0, "int_id");
						}
					} else {
						_debug("user record FOUND", $debug_enabled);

						$entry_ref_id = db_fetch_result($result, 0, "ref_id");
						$entry_int_id = db_fetch_result($result, 0, "int_id");
					}

					_debug("RID: $entry_ref_id, IID: $entry_int_id", $debug_enabled);

					db_query("UPDATE ttrss_entries
						SET title = '$entry_title',
							content = '$entry_content',
							content_hash = '$entry_current_hash',
							updated = '$entry_timestamp_fmt',
							num_comments = '$num_comments',
							plugin_data = '$entry_plugin_data',
							author = '$entry_author',
							lang = '$entry_language'
						WHERE id = '$ref_id'");

					// update aux data
					db_query("UPDATE ttrss_user_entries
							SET score = '$score' WHERE ref_id = '$ref_id'");

					if ($mark_unread_on_update) {
						db_query("UPDATE ttrss_user_entries
							SET last_read = null, unread = true WHERE ref_id = '$ref_id'");
					}
				}

				db_query("COMMIT");

				_debug("assigning labels [other]...", $debug_enabled);

				foreach ($article_labels as $label) {
					label_add_article($entry_ref_id, $label[1], $owner_uid);
				}

				_debug("assigning labels [filters]...", $debug_enabled);

				assign_article_to_label_filters($entry_ref_id, $article_filters,
					$owner_uid, $article_labels);

				_debug("looking for enclosures...", $debug_enabled);

				// enclosures

				$enclosures = array();

				$encs = $item->get_enclosures();

				if (is_array($encs)) {
					foreach ($encs as $e) {
						$e_item = array(
							$e->link, $e->type, $e->length, $e->title, $e->width, $e->height);
						array_push($enclosures, $e_item);
					}
				}

				if ($debug_enabled) {
					_debug("article enclosures:", $debug_enabled);
					print_r($enclosures);
				}

				db_query("BEGIN");

//				debugging
//				db_query("DELETE FROM ttrss_enclosures WHERE post_id = '$entry_ref_id'");

				foreach ($enclosures as $enc) {
					$enc_url = db_escape_string($enc[0]);
					$enc_type = db_escape_string($enc[1]);
					$enc_dur = db_escape_string($enc[2]);
					$enc_title = db_escape_string($enc[3]);
					$enc_width = intval($enc[4]);
					$enc_height = intval($enc[5]);

					$result = db_query("SELECT id FROM ttrss_enclosures
						WHERE content_url = '$enc_url' AND post_id = '$entry_ref_id'");

					if (db_num_rows($result) == 0) {
						db_query("INSERT INTO ttrss_enclosures
							(content_url, content_type, title, duration, post_id, width, height) VALUES
							('$enc_url', '$enc_type', '$enc_title', '$enc_dur', '$entry_ref_id', $enc_width, $enc_height)");
					}
				}

				db_query("COMMIT");

				// check for manual tags (we have to do it here since they're loaded from filters)

				foreach ($article_filters as $f) {
					if ($f["type"] == "tag") {

						$manual_tags = trim_array(explode(",", $f["param"]));

						foreach ($manual_tags as $tag) {
							if (tag_is_valid($tag)) {
								array_push($entry_tags, $tag);
							}
						}
					}
				}

				// Skip boring tags

				$boring_tags = trim_array(explode(",", mb_strtolower(get_pref(
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

				if ($debug_enabled) {
					_debug("filtered article tags:", $debug_enabled);
					print_r($filtered_tags);
				}

				// Save article tags in the database

				if (count($filtered_tags) > 0) {

					db_query("BEGIN");

					foreach ($filtered_tags as $tag) {

						$tag = sanitize_tag($tag);
						$tag = db_escape_string($tag);

						if (!tag_is_valid($tag)) continue;

						$result = db_query("SELECT id FROM ttrss_tags
							WHERE tag_name = '$tag' AND post_int_id = '$entry_int_id' AND
							owner_uid = '$owner_uid' LIMIT 1");

							if ($result && db_num_rows($result) == 0) {

								db_query("INSERT INTO ttrss_tags
									(owner_uid,tag_name,post_int_id)
									VALUES ('$owner_uid','$tag', '$entry_int_id')");
							}

						array_push($tags_to_cache, $tag);
					}

					/* update the cache */

					$tags_to_cache = array_unique($tags_to_cache);

					$tags_str = db_escape_string(join(",", $tags_to_cache));

					db_query("UPDATE ttrss_user_entries
						SET tag_cache = '$tags_str' WHERE ref_id = '$entry_ref_id'
						AND owner_uid = $owner_uid");

					db_query("COMMIT");
				}

				_debug("article processed", $debug_enabled);
			}

			_debug("purging feed...", $debug_enabled);

			purge_feed($feed, 0, $debug_enabled);

			db_query("UPDATE ttrss_feeds
				SET last_updated = NOW(), last_error = '' WHERE id = '$feed'");

//			db_query("COMMIT");

		} else {

			$error_msg = db_escape_string(mb_substr($rss->error(), 0, 245));

			_debug("fetch error: $error_msg", $debug_enabled);

			if (count($rss->errors()) > 1) {
				foreach ($rss->errors() as $error) {
					_debug("+ $error");
				}
			}

			db_query(
				"UPDATE ttrss_feeds SET last_error = '$error_msg',
				last_updated = NOW() WHERE id = '$feed'");

			unset($rss);
		}

		_debug("done", $debug_enabled);

		return $rss;
	}

	function cache_images($html, $site_url, $debug) {
		libxml_use_internal_errors(true);

		$charset_hack = '<head>
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
		</head>';

		$doc = new DOMDocument();
		$doc->loadHTML($charset_hack . $html);
		$xpath = new DOMXPath($doc);

		$entries = $xpath->query('(//img[@src])');

		foreach ($entries as $entry) {
			if ($entry->hasAttribute('src')) {
				$src = rewrite_relative_url($site_url, $entry->getAttribute('src'));

				$local_filename = CACHE_DIR . "/images/" . sha1($src) . ".png";

				if ($debug) _debug("cache_images: downloading: $src to $local_filename");

				if (!file_exists($local_filename)) {
					$file_content = fetch_file_contents($src);

					if ($file_content && strlen($file_content) > _MIN_CACHE_IMAGE_SIZE) {
						file_put_contents($local_filename, $file_content);
					}
				}
			}
		}
	}

	function expire_error_log($debug) {
		if ($debug) _debug("Removing old error log entries...");

		if (DB_TYPE == "pgsql") {
			db_query("DELETE FROM ttrss_error_log
				WHERE created_at < NOW() - INTERVAL '7 days'");
		} else {
			db_query("DELETE FROM ttrss_error_log
				WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
		}

	}

	function expire_lock_files($debug) {
		//if ($debug) _debug("Removing old lock files...");

		$num_deleted = 0;

		if (is_writable(LOCK_DIRECTORY)) {
			$files = glob(LOCK_DIRECTORY . "/*.lock");

			if ($files) {
				foreach ($files as $file) {
					if (!file_is_locked(basename($file)) && time() - filemtime($file) > 86400*2) {
						unlink($file);
						++$num_deleted;
					}
				}
			}
		}

		if ($debug) _debug("Removed $num_deleted old lock files.");
	}

	function expire_cached_files($debug) {
		foreach (array("simplepie", "images", "export", "upload") as $dir) {
			$cache_dir = CACHE_DIR . "/$dir";

//			if ($debug) _debug("Expiring $cache_dir");

			$num_deleted = 0;

			if (is_writable($cache_dir)) {
				$files = glob("$cache_dir/*");

				if ($files) {
					foreach ($files as $file) {
						if (time() - filemtime($file) > 86400*7) {
							unlink($file);

							++$num_deleted;
						}
					}
				}
			}

			if ($debug) _debug("$cache_dir: removed $num_deleted files.");
		}
	}

	/**
	* Source: http://www.php.net/manual/en/function.parse-url.php#104527
	* Returns the url query as associative array
	*
	* @param    string    query
	* @return    array    params
	*/
	function convertUrlQuery($query) {
		$queryParts = explode('&', $query);

		$params = array();

		foreach ($queryParts as $param) {
			$item = explode('=', $param);
			$params[$item[0]] = $item[1];
		}

		return $params;
	}

	function get_article_filters($filters, $title, $content, $link, $timestamp, $author, $tags) {
		$matches = array();

		foreach ($filters as $filter) {
			$match_any_rule = $filter["match_any_rule"];
			$inverse = $filter["inverse"];
			$filter_match = false;

			foreach ($filter["rules"] as $rule) {
				$match = false;
				$reg_exp = str_replace('/', '\/', $rule["reg_exp"]);
				$rule_inverse = $rule["inverse"];

				if (!$reg_exp)
					continue;

				switch ($rule["type"]) {
				case "title":
					$match = @preg_match("/$reg_exp/i", $title);
					break;
				case "content":
					// we don't need to deal with multiline regexps
					$content = preg_replace("/[\r\n\t]/", "", $content);

					$match = @preg_match("/$reg_exp/i", $content);
					break;
				case "both":
					// we don't need to deal with multiline regexps
					$content = preg_replace("/[\r\n\t]/", "", $content);

					$match = (@preg_match("/$reg_exp/i", $title) || @preg_match("/$reg_exp/i", $content));
					break;
				case "link":
					$match = @preg_match("/$reg_exp/i", $link);
					break;
				case "author":
					$match = @preg_match("/$reg_exp/i", $author);
					break;
				case "tag":
					foreach ($tags as $tag) {
						if (@preg_match("/$reg_exp/i", $tag)) {
							$match = true;
							break;
						}
					}
					break;
				}

				if ($rule_inverse) $match = !$match;

				if ($match_any_rule) {
					if ($match) {
						$filter_match = true;
						break;
					}
				} else {
					$filter_match = $match;
					if (!$match) {
						break;
					}
				}
			}

			if ($inverse) $filter_match = !$filter_match;

			if ($filter_match) {
				foreach ($filter["actions"] AS $action) {
					array_push($matches, $action);

					// if Stop action encountered, perform no further processing
					if ($action["type"] == "stop") return $matches;
				}
			}
		}

		return $matches;
	}

	function find_article_filter($filters, $filter_name) {
		foreach ($filters as $f) {
			if ($f["type"] == $filter_name) {
				return $f;
			};
		}
		return false;
	}

	function find_article_filters($filters, $filter_name) {
		$results = array();

		foreach ($filters as $f) {
			if ($f["type"] == $filter_name) {
				array_push($results, $f);
			};
		}
		return $results;
	}

	function calculate_article_score($filters) {
		$score = 0;

		foreach ($filters as $f) {
			if ($f["type"] == "score") {
				$score += $f["param"];
			};
		}
		return $score;
	}

	function labels_contains_caption($labels, $caption) {
		foreach ($labels as $label) {
			if ($label[1] == $caption) {
				return true;
			}
		}

		return false;
	}

	function assign_article_to_label_filters($id, $filters, $owner_uid, $article_labels) {
		foreach ($filters as $f) {
			if ($f["type"] == "label") {
				if (!labels_contains_caption($article_labels, $f["param"])) {
					label_add_article($id, $f["param"], $owner_uid);
				}
			}
		}
	}

	function make_guid_from_title($title) {
		return preg_replace("/[ \"\',.:;]/", "-",
			mb_strtolower(strip_tags($title), 'utf-8'));
	}

	/* function verify_feed_xml($feed_data) {
		libxml_use_internal_errors(true);
		$doc = new DOMDocument();
		$doc->loadXML($feed_data);
		$error = libxml_get_last_error();
		libxml_clear_errors();
		return $error;
	} */

	function cleanup_counters_cache($debug) {
		$result = db_query("DELETE FROM ttrss_counters_cache
			WHERE feed_id > 0 AND
			(SELECT COUNT(id) FROM ttrss_feeds WHERE
				id = feed_id AND
				ttrss_counters_cache.owner_uid = ttrss_feeds.owner_uid) = 0");
		$frows = db_affected_rows($result);

		$result = db_query("DELETE FROM ttrss_cat_counters_cache
			WHERE feed_id > 0 AND
			(SELECT COUNT(id) FROM ttrss_feed_categories WHERE
				id = feed_id AND
				ttrss_cat_counters_cache.owner_uid = ttrss_feed_categories.owner_uid) = 0");
		$crows = db_affected_rows($result);

		_debug("Removed $frows (feeds) $crows (cats) orphaned counter cache entries.");
	}

	function housekeeping_common($debug) {
		expire_cached_files($debug);
		expire_lock_files($debug);
		expire_error_log($debug);

		$count = update_feedbrowser_cache();
		_debug("Feedbrowser updated, $count feeds processed.");

		purge_orphans( true);
		cleanup_counters_cache($debug);
		$rc = cleanup_tags( 14, 50000);

		_debug("Cleaned $rc cached tags.");

		PluginHost::getInstance()->run_hooks(PluginHost::HOOK_HOUSE_KEEPING, "hook_house_keeping", "");

	}
?>

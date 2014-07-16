<?php
	function make_init_params() {
		$params = array();

		foreach (array("ON_CATCHUP_SHOW_NEXT_FEED", "HIDE_READ_FEEDS",
			"ENABLE_FEED_CATS", "FEEDS_SORT_BY_UNREAD", "CONFIRM_FEED_CATCHUP",
			"CDM_AUTO_CATCHUP", "FRESH_ARTICLE_MAX_AGE",
			"HIDE_READ_SHOWS_SPECIAL", "COMBINED_DISPLAY_MODE") as $param) {

				 $params[strtolower($param)] = (int) get_pref($param);
		 }

		$params["icons_url"] = ICONS_URL;
		$params["cookie_lifetime"] = SESSION_COOKIE_LIFETIME;
		$params["default_view_mode"] = get_pref("_DEFAULT_VIEW_MODE");
		$params["default_view_limit"] = (int) get_pref("_DEFAULT_VIEW_LIMIT");
		$params["default_view_order_by"] = get_pref("_DEFAULT_VIEW_ORDER_BY");
		$params["bw_limit"] = (int) $_SESSION["bw_limit"];
		$params["label_base_index"] = (int) LABEL_BASE_INDEX;
		$params["theme"] = get_pref("USER_CSS_THEME", false, false);
		$params["plugins"] = implode(", ", PluginHost::getInstance()->get_plugin_names());

		$params["php_platform"] = PHP_OS;
		$params["php_version"] = PHP_VERSION;

		$params["sanity_checksum"] = sha1(file_get_contents("include/sanity_check.php"));

		$result = db_query("SELECT MAX(id) AS mid, COUNT(*) AS nf FROM
			ttrss_feeds WHERE owner_uid = " . $_SESSION["uid"]);

		$max_feed_id = db_fetch_result($result, 0, "mid");
		$num_feeds = db_fetch_result($result, 0, "nf");

		$params["max_feed_id"] = (int) $max_feed_id;
		$params["num_feeds"] = (int) $num_feeds;

		$params["hotkeys"] = get_hotkeys_map();

		$params["csrf_token"] = $_SESSION["csrf_token"];
		$params["widescreen"] = (int) $_COOKIE["ttrss_widescreen"];

		$params['simple_update'] = defined('SIMPLE_UPDATE_MODE') && SIMPLE_UPDATE_MODE;

		return $params;
	}

	function get_hotkeys_info() {
		$hotkeys = array(
			__("Navigation") => array(
				"next_feed" => __("Open next feed"),
				"prev_feed" => __("Open previous feed"),
				"next_article" => __("Open next article"),
				"prev_article" => __("Open previous article"),
				"next_article_noscroll" => __("Open next article (don't scroll long articles)"),
				"prev_article_noscroll" => __("Open previous article (don't scroll long articles)"),
				"next_article_noexpand" => __("Move to next article (don't expand or mark read)"),
				"prev_article_noexpand" => __("Move to previous article (don't expand or mark read)"),
				"search_dialog" => __("Show search dialog")),
			__("Article") => array(
				"toggle_mark" => __("Toggle starred"),
				"toggle_publ" => __("Toggle published"),
				"toggle_unread" => __("Toggle unread"),
				"edit_tags" => __("Edit tags"),
				"dismiss_selected" => __("Dismiss selected"),
				"dismiss_read" => __("Dismiss read"),
				"open_in_new_window" => __("Open in new window"),
				"catchup_below" => __("Mark below as read"),
				"catchup_above" => __("Mark above as read"),
				"article_scroll_down" => __("Scroll down"),
				"article_scroll_up" => __("Scroll up"),
				"select_article_cursor" => __("Select article under cursor"),
				"email_article" => __("Email article"),
				"close_article" => __("Close/collapse article"),
				"toggle_expand" => __("Toggle article expansion (combined mode)"),
				"toggle_widescreen" => __("Toggle widescreen mode"),
				"toggle_embed_original" => __("Toggle embed original")),
			__("Article selection") => array(
				"select_all" => __("Select all articles"),
				"select_unread" => __("Select unread"),
				"select_marked" => __("Select starred"),
				"select_published" => __("Select published"),
				"select_invert" => __("Invert selection"),
				"select_none" => __("Deselect everything")),
			__("Feed") => array(
				"feed_refresh" => __("Refresh current feed"),
				"feed_unhide_read" => __("Un/hide read feeds"),
				"feed_subscribe" => __("Subscribe to feed"),
				"feed_edit" => __("Edit feed"),
				"feed_catchup" => __("Mark as read"),
				"feed_reverse" => __("Reverse headlines"),
				"feed_debug_update" => __("Debug feed update"),
				"catchup_all" => __("Mark all feeds as read"),
				"cat_toggle_collapse" => __("Un/collapse current category"),
				"toggle_combined_mode" => __("Toggle combined mode"),
				"toggle_cdm_expanded" => __("Toggle auto expand in combined mode")),
			__("Go to") => array(
				"goto_all" => __("All articles"),
				"goto_fresh" => __("Fresh"),
				"goto_marked" => __("Starred"),
				"goto_published" => __("Published"),
				"goto_tagcloud" => __("Tag cloud"),
				"goto_prefs" => __("Preferences")),
			__("Other") => array(
				"create_label" => __("Create label"),
				"create_filter" => __("Create filter"),
				"collapse_sidebar" => __("Un/collapse sidebar"),
				"help_dialog" => __("Show help dialog"))
			);

		foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_HOTKEY_INFO) as $plugin) {
			$hotkeys = $plugin->hook_hotkey_info($hotkeys);
		}

		return $hotkeys;
	}

	function get_hotkeys_map() {
		$hotkeys = array(
//			"navigation" => array(
				"k" => "next_feed",
				"j" => "prev_feed",
				"n" => "next_article",
				"p" => "prev_article",
				"(38)|up" => "prev_article",
				"(40)|down" => "next_article",
//				"^(38)|Ctrl-up" => "prev_article_noscroll",
//				"^(40)|Ctrl-down" => "next_article_noscroll",
				"(191)|/" => "search_dialog",
//			"article" => array(
				"s" => "toggle_mark",
				"*s" => "toggle_publ",
				"u" => "toggle_unread",
				"*t" => "edit_tags",
				"*d" => "dismiss_selected",
				"*x" => "dismiss_read",
				"o" => "open_in_new_window",
				"c p" => "catchup_below",
				"c n" => "catchup_above",
				"*n" => "article_scroll_down",
				"*p" => "article_scroll_up",
				"*(38)|Shift+up" => "article_scroll_up",
				"*(40)|Shift+down" => "article_scroll_down",
				"a *w" => "toggle_widescreen",
				"a e" => "toggle_embed_original",
				"e" => "email_article",
				"a q" => "close_article",
//			"article_selection" => array(
				"a a" => "select_all",
				"a u" => "select_unread",
				"a *u" => "select_marked",
				"a p" => "select_published",
				"a i" => "select_invert",
				"a n" => "select_none",
//			"feed" => array(
				"f r" => "feed_refresh",
				"f a" => "feed_unhide_read",
				"f s" => "feed_subscribe",
				"f e" => "feed_edit",
				"f q" => "feed_catchup",
				"f x" => "feed_reverse",
				"f *d" => "feed_debug_update",
				"f *c" => "toggle_combined_mode",
				"f c" => "toggle_cdm_expanded",
				"*q" => "catchup_all",
				"x" => "cat_toggle_collapse",
//			"goto" => array(
				"g a" => "goto_all",
				"g f" => "goto_fresh",
				"g s" => "goto_marked",
				"g p" => "goto_published",
				"g t" => "goto_tagcloud",
				"g *p" => "goto_prefs",
//			"other" => array(
				"(9)|Tab" => "select_article_cursor", // tab
				"c l" => "create_label",
				"c f" => "create_filter",
				"c s" => "collapse_sidebar",
				"^(191)|Ctrl+/" => "help_dialog",
			);

		if (get_pref('COMBINED_DISPLAY_MODE')) {
			$hotkeys["^(38)|Ctrl-up"] = "prev_article_noscroll";
			$hotkeys["^(40)|Ctrl-down"] = "next_article_noscroll";
		}

		foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_HOTKEY_MAP) as $plugin) {
			$hotkeys = $plugin->hook_hotkey_map($hotkeys);
		}

		$prefixes = array();

		foreach (array_keys($hotkeys) as $hotkey) {
			$pair = explode(" ", $hotkey, 2);

			if (count($pair) > 1 && !in_array($pair[0], $prefixes)) {
				array_push($prefixes, $pair[0]);
			}
		}

		return array($prefixes, $hotkeys);
	}

	function make_runtime_info() {
		$data = array();

		$result = db_query("SELECT MAX(id) AS mid, COUNT(*) AS nf FROM
			ttrss_feeds WHERE owner_uid = " . $_SESSION["uid"]);

		$max_feed_id = db_fetch_result($result, 0, "mid");
		$num_feeds = db_fetch_result($result, 0, "nf");

		$data["max_feed_id"] = (int) $max_feed_id;
		$data["num_feeds"] = (int) $num_feeds;

		$data['last_article_id'] = getLastArticleId();
		$data['cdm_expanded'] = get_pref('CDM_EXPANDED');

		$data['dep_ts'] = calculate_dep_timestamp();
		$data['reload_on_ts_change'] = !defined('_NO_RELOAD_ON_TS_CHANGE');

		if (file_exists(LOCK_DIRECTORY . "/update_daemon.lock")) {

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

		if ($_SESSION["last_version_check"] + 86400 + rand(-1000, 1000) < time()) {
				$new_version_details = @check_for_update();

				$data['new_version_available'] = (int) ($new_version_details != false);

				$_SESSION["last_version_check"] = time();
				$_SESSION["version_data"] = $new_version_details;
		}

		return $data;
	}

	function search_to_sql($search) {

		$search_query_part = "";

		$keywords = str_getcsv($search, " ");
		$query_keywords = array();
		$search_words = array();

		foreach ($keywords as $k) {
			if (strpos($k, "-") === 0) {
				$k = substr($k, 1);
				$not = "NOT";
			} else {
				$not = "";
			}

			$commandpair = explode(":", mb_strtolower($k), 2);

			switch ($commandpair[0]) {
			case "title":
				if ($commandpair[1]) {
					array_push($query_keywords, "($not (LOWER(ttrss_entries.title) LIKE '%".
						db_escape_string(mb_strtolower($commandpair[1]))."%'))");
				} else {
					array_push($query_keywords, "(UPPER(ttrss_entries.title) $not LIKE UPPER('%$k%')
							OR UPPER(ttrss_entries.content) $not LIKE UPPER('%$k%'))");
					array_push($search_words, $k);
				}
				break;
			case "author":
				if ($commandpair[1]) {
					array_push($query_keywords, "($not (LOWER(author) LIKE '%".
						db_escape_string(mb_strtolower($commandpair[1]))."%'))");
				} else {
					array_push($query_keywords, "(UPPER(ttrss_entries.title) $not LIKE UPPER('%$k%')
							OR UPPER(ttrss_entries.content) $not LIKE UPPER('%$k%'))");
					array_push($search_words, $k);
				}
				break;
			case "note":
				if ($commandpair[1]) {
					if ($commandpair[1] == "true")
						array_push($query_keywords, "($not (note IS NOT NULL AND note != ''))");
					else if ($commandpair[1] == "false")
						array_push($query_keywords, "($not (note IS NULL OR note = ''))");
					else
						array_push($query_keywords, "($not (LOWER(note) LIKE '%".
							db_escape_string(mb_strtolower($commandpair[1]))."%'))");
				} else {
					array_push($query_keywords, "(UPPER(ttrss_entries.title) $not LIKE UPPER('%$k%')
							OR UPPER(ttrss_entries.content) $not LIKE UPPER('%$k%'))");
					if (!$not) array_push($search_words, $k);
				}
				break;
			case "star":

				if ($commandpair[1]) {
					if ($commandpair[1] == "true")
						array_push($query_keywords, "($not (marked = true))");
					else
						array_push($query_keywords, "($not (marked = false))");
				} else {
					array_push($query_keywords, "(UPPER(ttrss_entries.title) $not LIKE UPPER('%$k%')
							OR UPPER(ttrss_entries.content) $not LIKE UPPER('%$k%'))");
					if (!$not) array_push($search_words, $k);
				}
				break;
			case "pub":
				if ($commandpair[1]) {
					if ($commandpair[1] == "true")
						array_push($query_keywords, "($not (published = true))");
					else
						array_push($query_keywords, "($not (published = false))");

				} else {
					array_push($query_keywords, "(UPPER(ttrss_entries.title) $not LIKE UPPER('%$k%')
							OR UPPER(ttrss_entries.content) $not LIKE UPPER('%$k%'))");
					if (!$not) array_push($search_words, $k);
				}
				break;
			default:
				if (strpos($k, "@") === 0) {

					$user_tz_string = get_pref('USER_TIMEZONE', $_SESSION['uid']);
					$orig_ts = strtotime(substr($k, 1));
					$k = date("Y-m-d", convert_timestamp($orig_ts, $user_tz_string, 'UTC'));

					//$k = date("Y-m-d", strtotime(substr($k, 1)));

					array_push($query_keywords, "(".SUBSTRING_FOR_DATE."(updated,1,LENGTH('$k')) $not = '$k')");
				} else {
					array_push($query_keywords, "(UPPER(ttrss_entries.title) $not LIKE UPPER('%$k%')
							OR UPPER(ttrss_entries.content) $not LIKE UPPER('%$k%'))");

					if (!$not) array_push($search_words, $k);
				}
			}
		}

		$search_query_part = implode("AND", $query_keywords);

		return array($search_query_part, $search_words);
	}

	function getParentCategories($cat, $owner_uid) {
		$rv = array();

		$result = db_query("SELECT parent_cat FROM ttrss_feed_categories
			WHERE id = '$cat' AND parent_cat IS NOT NULL AND owner_uid = $owner_uid");

		while ($line = db_fetch_assoc($result)) {
			array_push($rv, $line["parent_cat"]);
			$rv = array_merge($rv, getParentCategories($line["parent_cat"], $owner_uid));
		}

		return $rv;
	}

	function getChildCategories($cat, $owner_uid) {
		$rv = array();

		$result = db_query("SELECT id FROM ttrss_feed_categories
			WHERE parent_cat = '$cat' AND owner_uid = $owner_uid");

		while ($line = db_fetch_assoc($result)) {
			array_push($rv, $line["id"]);
			$rv = array_merge($rv, getChildCategories($line["id"], $owner_uid));
		}

		return $rv;
	}

	function queryFeedHeadlines($feed, $limit, $view_mode, $cat_view, $search, $search_mode, $override_order = false, $offset = 0, $owner_uid = 0, $filter = false, $since_id = 0, $include_children = false, $ignore_vfeed_group = false, $override_strategy = false, $override_vfeed = false, $start_ts = false) {

		if (!$owner_uid) $owner_uid = $_SESSION["uid"];

		$ext_tables_part = "";
		$search_words = array();

			if ($search) {
				foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_SEARCH) as $plugin) {
					list($search_query_part, $search_words) = $plugin->hook_search($search);
					break;
				}

				// fall back in case of no plugins
				if (!$search_query_part) {
					list($search_query_part, $search_words) = search_to_sql($search);
				}
				$search_query_part .= " AND ";
			} else {
				$search_query_part = "";
			}

			if ($filter) {

				if (DB_TYPE == "pgsql") {
					$query_strategy_part .= " AND updated > NOW() - INTERVAL '14 days' ";
				} else {
					$query_strategy_part .= " AND updated > DATE_SUB(NOW(), INTERVAL 14 DAY) ";
				}

				$override_order = "updated DESC";

				$filter_query_part = filter_to_sql($filter, $owner_uid);

				// Try to check if SQL regexp implementation chokes on a valid regexp


				$result = db_query("SELECT true AS true_val
                                        FROM ttrss_entries
                                        JOIN ttrss_user_entries ON ttrss_entries.id = ttrss_user_entries.ref_id
                                        JOIN ttrss_feeds ON ttrss_feeds.id = ttrss_user_entries.feed_id
					WHERE $filter_query_part LIMIT 1", false);

				if ($result) {
					$test = db_fetch_result($result, 0, "true_val");

					if (!$test) {
						$filter_query_part = "false AND";
					} else {
						$filter_query_part .= " AND";
					}
				} else {
					$filter_query_part = "false AND";
				}

			} else {
				$filter_query_part = "";
			}

			if ($since_id) {
				$since_id_part = "ttrss_entries.id > $since_id AND ";
			} else {
				$since_id_part = "";
			}

			$view_query_part = "";

			if ($view_mode == "adaptive") {
				if ($search) {
					$view_query_part = " ";
				} else if ($feed != -1) {

					$unread = getFeedUnread($feed, $cat_view);

					if ($cat_view && $feed > 0 && $include_children)
						$unread += getCategoryChildrenUnread($feed);

					if ($unread > 0)
			        $view_query_part = " unread = true AND ";

				}
			}

			if ($view_mode == "marked") {
				$view_query_part = " marked = true AND ";
			}

			if ($view_mode == "has_note") {
				$view_query_part = " (note IS NOT NULL AND note != '') AND ";
			}

			if ($view_mode == "published") {
				$view_query_part = " published = true AND ";
			}

			if ($view_mode == "unread" && $feed != -6) {
				$view_query_part = " unread = true AND ";
			}

			if ($limit > 0) {
				$limit_query_part = "LIMIT " . $limit;
			}

			$allow_archived = false;

			$vfeed_query_part = "";

			// override query strategy and enable feed display when searching globally
			if ($search && $search_mode == "all_feeds") {
				$query_strategy_part = "true";
				$vfeed_query_part = "ttrss_feeds.title AS feed_title,";
			/* tags */
			} else if (!is_numeric($feed)) {
				$query_strategy_part = "true";
				$vfeed_query_part = "(SELECT title FROM ttrss_feeds WHERE
					id = feed_id) as feed_title,";
			} else if ($search && $search_mode == "this_cat") {
				$vfeed_query_part = "ttrss_feeds.title AS feed_title,";

				if ($feed > 0) {
					if ($include_children) {
						$subcats = getChildCategories($feed, $owner_uid);
						array_push($subcats, $feed);
						$cats_qpart = join(",", $subcats);
					} else {
						$cats_qpart = $feed;
					}

					$query_strategy_part = "ttrss_feeds.cat_id IN ($cats_qpart)";

				} else {
					$query_strategy_part = "ttrss_feeds.cat_id IS NULL";
				}

			} else if ($feed > 0) {

				if ($cat_view) {

					if ($feed > 0) {
						if ($include_children) {
							# sub-cats
							$subcats = getChildCategories($feed, $owner_uid);

							array_push($subcats, $feed);
							$query_strategy_part = "cat_id IN (".
									implode(",", $subcats).")";

						} else {
							$query_strategy_part = "cat_id = '$feed'";
						}

					} else {
						$query_strategy_part = "cat_id IS NULL";
					}

					$vfeed_query_part = "ttrss_feeds.title AS feed_title,";

				} else {
					$query_strategy_part = "feed_id = '$feed'";
				}
			} else if ($feed == 0 && !$cat_view) { // archive virtual feed
				$query_strategy_part = "feed_id IS NULL";
				$allow_archived = true;
			} else if ($feed == 0 && $cat_view) { // uncategorized
				$query_strategy_part = "cat_id IS NULL AND feed_id IS NOT NULL";
				$vfeed_query_part = "ttrss_feeds.title AS feed_title,";
			} else if ($feed == -1) { // starred virtual feed
				$query_strategy_part = "marked = true";
				$vfeed_query_part = "ttrss_feeds.title AS feed_title,";
				$allow_archived = true;

				if (!$override_order) {
					$override_order = "last_marked DESC, date_entered DESC, updated DESC";
				}

			} else if ($feed == -2) { // published virtual feed OR labels category

				if (!$cat_view) {
					$query_strategy_part = "published = true";
					$vfeed_query_part = "ttrss_feeds.title AS feed_title,";
					$allow_archived = true;

					if (!$override_order) {
						$override_order = "last_published DESC, date_entered DESC, updated DESC";
					}

				} else {
					$vfeed_query_part = "ttrss_feeds.title AS feed_title,";

					$ext_tables_part = ",ttrss_labels2,ttrss_user_labels2";

					$query_strategy_part = "ttrss_labels2.id = ttrss_user_labels2.label_id AND
						ttrss_user_labels2.article_id = ref_id";

				}
			} else if ($feed == -6) { // recently read
				$query_strategy_part = "unread = false AND last_read IS NOT NULL";
				$vfeed_query_part = "ttrss_feeds.title AS feed_title,";
				$allow_archived = true;
				$ignore_vfeed_group = true;

				if (!$override_order) $override_order = "last_read DESC";

/*			} else if ($feed == -7) { // shared
				$query_strategy_part = "uuid != ''";
				$vfeed_query_part = "ttrss_feeds.title AS feed_title,";
				$allow_archived = true; */
			} else if ($feed == -3) { // fresh virtual feed
				$query_strategy_part = "unread = true AND score >= 0";

				$intl = get_pref("FRESH_ARTICLE_MAX_AGE", $owner_uid);

				if (DB_TYPE == "pgsql") {
					$query_strategy_part .= " AND date_entered > NOW() - INTERVAL '$intl hour' ";
				} else {
					$query_strategy_part .= " AND date_entered > DATE_SUB(NOW(), INTERVAL $intl HOUR) ";
				}

				$vfeed_query_part = "ttrss_feeds.title AS feed_title,";
			} else if ($feed == -4) { // all articles virtual feed
				$allow_archived = true;
				$query_strategy_part = "true";
				$vfeed_query_part = "ttrss_feeds.title AS feed_title,";
			} else if ($feed <= LABEL_BASE_INDEX) { // labels
				$label_id = feed_to_label_id($feed);

				$query_strategy_part = "label_id = '$label_id' AND
					ttrss_labels2.id = ttrss_user_labels2.label_id AND
					ttrss_user_labels2.article_id = ref_id";

				$vfeed_query_part = "ttrss_feeds.title AS feed_title,";
				$ext_tables_part = ",ttrss_labels2,ttrss_user_labels2";
				$allow_archived = true;

			} else {
				$query_strategy_part = "true";
			}

			$order_by = "score DESC, date_entered DESC, updated DESC";

			if ($view_mode == "unread_first") {
				$order_by = "unread DESC, $order_by";
			}

			if ($override_order) {
				$order_by = $override_order;
			}

			if ($override_strategy) {
				$query_strategy_part = $override_strategy;
			}

			if ($override_vfeed) {
				$vfeed_query_part = $override_vfeed;
			}

			$feed_title = "";

			if ($search) {
				$feed_title = T_sprintf("Search results: %s", $search);
			} else {
				if ($cat_view) {
					$feed_title = getCategoryTitle($feed);
				} else {
					if (is_numeric($feed) && $feed > 0) {
						$result = db_query("SELECT title,site_url,last_error,last_updated
							FROM ttrss_feeds WHERE id = '$feed' AND owner_uid = $owner_uid");

						$feed_title = db_fetch_result($result, 0, "title");
						$feed_site_url = db_fetch_result($result, 0, "site_url");
						$last_error = db_fetch_result($result, 0, "last_error");
						$last_updated = db_fetch_result($result, 0, "last_updated");
					} else {
						$feed_title = getFeedTitle($feed);
					}
				}
			}


			$content_query_part = "content, ";


			if (is_numeric($feed)) {

				if ($feed >= 0) {
					$feed_kind = "Feeds";
				} else {
					$feed_kind = "Labels";
				}

				if ($limit_query_part) {
					$offset_query_part = "OFFSET $offset";
				}

				// proper override_order applied above
				if ($vfeed_query_part && !$ignore_vfeed_group && get_pref('VFEED_GROUP_BY_FEED', $owner_uid)) {
					if (!$override_order) {
						$order_by = "ttrss_feeds.title, $order_by";
					} else {
						$order_by = "ttrss_feeds.title, $override_order";
					}
				}

				if (!$allow_archived) {
					$from_qpart = "ttrss_entries,ttrss_user_entries,ttrss_feeds$ext_tables_part";
					$feed_check_qpart = "ttrss_user_entries.feed_id = ttrss_feeds.id AND";

				} else {
					$from_qpart = "ttrss_entries$ext_tables_part,ttrss_user_entries
						LEFT JOIN ttrss_feeds ON (feed_id = ttrss_feeds.id)";
				}

				if ($vfeed_query_part)
					$vfeed_query_part .= "favicon_avg_color,";

				if ($start_ts) {
					$start_ts_formatted = date("Y/m/d H:i:s", strtotime($start_ts));
					$start_ts_query_part = "date_entered >= '$start_ts_formatted' AND";
				} else {
					$start_ts_query_part = "";
				}

				$query = "SELECT DISTINCT
						date_entered,
						guid,
						ttrss_entries.id,ttrss_entries.title,
						updated,
						label_cache,
						tag_cache,
						always_display_enclosures,
						site_url,
						note,
						num_comments,
						comments,
						int_id,
						uuid,
						lang,
						hide_images,
						unread,feed_id,marked,published,link,last_read,orig_feed_id,
						last_marked, last_published,
						$vfeed_query_part
						$content_query_part
						author,score
					FROM
						$from_qpart
					WHERE
					$feed_check_qpart
					ttrss_user_entries.ref_id = ttrss_entries.id AND
					ttrss_user_entries.owner_uid = '$owner_uid' AND
					$search_query_part
					$start_ts_query_part
					$filter_query_part
					$view_query_part
					$since_id_part
					$query_strategy_part ORDER BY $order_by
					$limit_query_part $offset_query_part";

				if ($_REQUEST["debug"]) print $query;

				$result = db_query($query);

			} else {
				// browsing by tag

				$select_qpart = "SELECT DISTINCT " .
								"date_entered," .
								"guid," .
								"note," .
								"ttrss_entries.id as id," .
								"title," .
								"updated," .
								"unread," .
								"feed_id," .
								"orig_feed_id," .
								"marked," .
								"num_comments, " .
								"comments, " .
								"tag_cache," .
								"label_cache," .
								"link," .
								"lang," .
								"uuid," .
								"last_read," .
								"(SELECT hide_images FROM ttrss_feeds WHERE id = feed_id) AS hide_images," .
								"last_marked, last_published, " .
								$since_id_part .
								$vfeed_query_part .
								$content_query_part .
								"score ";

				$feed_kind = "Tags";
				$all_tags = explode(",", $feed);
				if ($search_mode == 'any') {
					$tag_sql = "tag_name in (" . implode(", ", array_map("db_quote", $all_tags)) . ")";
					$from_qpart = " FROM ttrss_entries,ttrss_user_entries,ttrss_tags ";
					$where_qpart = " WHERE " .
								   "ref_id = ttrss_entries.id AND " .
								   "ttrss_user_entries.owner_uid = $owner_uid AND " .
								   "post_int_id = int_id AND $tag_sql AND " .
								   $view_query_part .
								   $search_query_part .
								   $query_strategy_part . " ORDER BY $order_by " .
								   $limit_query_part;

				} else {
					$i = 1;
					$sub_selects = array();
					$sub_ands = array();
					foreach ($all_tags as $term) {
						array_push($sub_selects, "(SELECT post_int_id from ttrss_tags WHERE tag_name = " . db_quote($term) . " AND owner_uid = $owner_uid) as A$i");
						$i++;
					}
					if ($i > 2) {
						$x = 1;
						$y = 2;
						do {
							array_push($sub_ands, "A$x.post_int_id = A$y.post_int_id");
							$x++;
							$y++;
						} while ($y < $i);
					}
					array_push($sub_ands, "A1.post_int_id = ttrss_user_entries.int_id and ttrss_user_entries.owner_uid = $owner_uid");
					array_push($sub_ands, "ttrss_user_entries.ref_id = ttrss_entries.id");
					$from_qpart = " FROM " . implode(", ", $sub_selects) . ", ttrss_user_entries, ttrss_entries";
					$where_qpart = " WHERE " . implode(" AND ", $sub_ands);
				}
				//				error_log("TAG SQL: " . $tag_sql);
				// $tag_sql = "tag_name = '$feed'";   DEFAULT way

				//				error_log("[". $select_qpart . "][" . $from_qpart . "][" .$where_qpart . "]");
				$result = db_query($select_qpart . $from_qpart . $where_qpart);
			}

			return array($result, $feed_title, $feed_site_url, $last_error, $last_updated, $search_words);

	}

	function sanitize($str, $force_remove_images = false, $owner = false, $site_url = false, $highlight_words = false, $article_id = false) {
		if (!$owner) $owner = $_SESSION["uid"];

		$res = trim($str); if (!$res) return '';

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

				if ($entry->hasAttribute('href')) {
					$entry->setAttribute('href',
						rewrite_relative_url($site_url, $entry->getAttribute('href')));

					$entry->setAttribute('rel', 'noreferrer');
				}

				if ($entry->hasAttribute('src')) {
					$src = rewrite_relative_url($site_url, $entry->getAttribute('src'));

					$cached_filename = CACHE_DIR . '/images/' . sha1($src) . '.png';

					if (file_exists($cached_filename)) {
						$src = SELF_URL_PATH . '/image.php?hash=' . sha1($src);
					}

					$entry->setAttribute('src', $src);
				}

				if ($entry->nodeName == 'img') {
					if (($owner && get_pref("STRIP_IMAGES", $owner)) ||
							$force_remove_images || $_SESSION["bw_limit"]) {

						$p = $doc->createElement('p');

						$a = $doc->createElement('a');
						$a->setAttribute('href', $entry->getAttribute('src'));

						$a->appendChild(new DOMText($entry->getAttribute('src')));
						$a->setAttribute('target', '_blank');

						$p->appendChild($a);

						$entry->parentNode->replaceChild($p, $entry);
					}
				}
			}

			if (strtolower($entry->nodeName) == "a") {
				$entry->setAttribute("target", "_blank");
			}
		}

		$entries = $xpath->query('//iframe');
		foreach ($entries as $entry) {
			$entry->setAttribute('sandbox', 'allow-scripts');

		}

		$allowed_elements = array('a', 'address', 'audio', 'article', 'aside',
			'b', 'bdi', 'bdo', 'big', 'blockquote', 'body', 'br',
			'caption', 'cite', 'center', 'code', 'col', 'colgroup',
			'data', 'dd', 'del', 'details', 'div', 'dl', 'font',
			'dt', 'em', 'footer', 'figure', 'figcaption',
			'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'html', 'i',
			'img', 'ins', 'kbd', 'li', 'main', 'mark', 'nav', 'noscript',
			'ol', 'p', 'pre', 'q', 'ruby', 'rp', 'rt', 's', 'samp', 'section',
			'small', 'source', 'span', 'strike', 'strong', 'sub', 'summary',
			'sup', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'time',
			'tr', 'track', 'tt', 'u', 'ul', 'var', 'wbr', 'video' );

		if ($_SESSION['hasSandbox']) $allowed_elements[] = 'iframe';

		$disallowed_attributes = array('id', 'style', 'class');

		foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_SANITIZE) as $plugin) {
			$retval = $plugin->hook_sanitize($doc, $site_url, $allowed_elements, $disallowed_attributes, $article_id);
			if (is_array($retval)) {
				$doc = $retval[0];
				$allowed_elements = $retval[1];
				$disallowed_attributes = $retval[2];
			} else {
				$doc = $retval;
			}
		}

		$doc->removeChild($doc->firstChild); //remove doctype
		$doc = strip_harmful_tags($doc, $allowed_elements, $disallowed_attributes);

		if ($highlight_words) {
			foreach ($highlight_words as $word) {

				// http://stackoverflow.com/questions/4081372/highlight-keywords-in-a-paragraph

				$elements = $xpath->query("//*/text()");

				foreach ($elements as $child) {

					$fragment = $doc->createDocumentFragment();
					$text = $child->textContent;

					while (($pos = mb_stripos($text, $word)) !== false) {
						$fragment->appendChild(new DomText(mb_substr($text, 0, $pos)));
						$word = mb_substr($text, $pos, mb_strlen($word));
						$highlight = $doc->createElement('span');
						$highlight->appendChild(new DomText($word));
						$highlight->setAttribute('class', 'highlight');
						$fragment->appendChild($highlight);
						$text = mb_substr($text, $pos + mb_strlen($word));
					}

					if (!empty($text)) $fragment->appendChild(new DomText($text));

					$child->parentNode->replaceChild($fragment, $child);
				}
			}
		}

		$res = $doc->saveHTML();

		return $res;
	}

	function strip_harmful_tags($doc, $allowed_elements, $disallowed_attributes) {
		$xpath = new DOMXPath($doc);
		$entries = $xpath->query('//*');

		foreach ($entries as $entry) {
			if (!in_array($entry->nodeName, $allowed_elements)) {
				$entry->parentNode->removeChild($entry);
			}

			if ($entry->hasAttributes()) {
				$attrs_to_remove = array();

				foreach ($entry->attributes as $attr) {

					if (strpos($attr->nodeName, 'on') === 0) {
						array_push($attrs_to_remove, $attr);
					}

					if (in_array($attr->nodeName, $disallowed_attributes)) {
						array_push($attrs_to_remove, $attr);
					}
				}

				foreach ($attrs_to_remove as $attr) {
					$entry->removeAttributeNode($attr);
				}
			}
		}

		return $doc;
	}

	function check_for_update() {
		if (CHECK_FOR_NEW_VERSION && $_SESSION['access_level'] >= 10) {
			$version_url = "http://tt-rss.org/version.php?ver=" . VERSION .
				"&iid=" . sha1(SELF_URL_PATH);

			$version_data = @fetch_file_contents($version_url);

			if ($version_data) {
				$version_data = json_decode($version_data, true);
				if ($version_data && $version_data['version']) {
					if (version_compare(VERSION_STATIC, $version_data['version']) == -1) {
						return $version_data;
					}
				}
			}
		}
		return false;
	}

	function catchupArticlesById($ids, $cmode, $owner_uid = false) {

		if (!$owner_uid) $owner_uid = $_SESSION["uid"];
		if (count($ids) == 0) return;

		$tmp_ids = array();

		foreach ($ids as $id) {
			array_push($tmp_ids, "ref_id = '$id'");
		}

		$ids_qpart = join(" OR ", $tmp_ids);

		if ($cmode == 0) {
			db_query("UPDATE ttrss_user_entries SET
			unread = false,last_read = NOW()
			WHERE ($ids_qpart) AND owner_uid = $owner_uid");
		} else if ($cmode == 1) {
			db_query("UPDATE ttrss_user_entries SET
			unread = true
			WHERE ($ids_qpart) AND owner_uid = $owner_uid");
		} else {
			db_query("UPDATE ttrss_user_entries SET
			unread = NOT unread,last_read = NOW()
			WHERE ($ids_qpart) AND owner_uid = $owner_uid");
		}

		/* update ccache */

		$result = db_query("SELECT DISTINCT feed_id FROM ttrss_user_entries
			WHERE ($ids_qpart) AND owner_uid = $owner_uid");

		while ($line = db_fetch_assoc($result)) {
			ccache_update($line["feed_id"], $owner_uid);
		}
	}

	function get_article_tags($id, $owner_uid = 0, $tag_cache = false) {

		$a_id = db_escape_string($id);

		if (!$owner_uid) $owner_uid = $_SESSION["uid"];

		$query = "SELECT DISTINCT tag_name,
			owner_uid as owner FROM
			ttrss_tags WHERE post_int_id = (SELECT int_id FROM ttrss_user_entries WHERE
			ref_id = '$a_id' AND owner_uid = '$owner_uid' LIMIT 1) ORDER BY tag_name";

		$tags = array();

		/* check cache first */

		if ($tag_cache === false) {
			$result = db_query("SELECT tag_cache FROM ttrss_user_entries
				WHERE ref_id = '$id' AND owner_uid = $owner_uid");

			$tag_cache = db_fetch_result($result, 0, "tag_cache");
		}

		if ($tag_cache) {
			$tags = explode(",", $tag_cache);
		} else {

			/* do it the hard way */

			$tmp_result = db_query($query);

			while ($tmp_line = db_fetch_assoc($tmp_result)) {
				array_push($tags, $tmp_line["tag_name"]);
			}

			/* update the cache */

			$tags_str = db_escape_string(join(",", $tags));

			db_query("UPDATE ttrss_user_entries
				SET tag_cache = '$tags_str' WHERE ref_id = '$id'
				AND owner_uid = $owner_uid");
		}

		return $tags;
	}

	function trim_array($array) {
		$tmp = $array;
		array_walk($tmp, 'trim');
		return $tmp;
	}

	function tag_is_valid($tag) {
		if ($tag == '') return false;
		if (preg_match("/^[0-9]*$/", $tag)) return false;
		if (mb_strlen($tag) > 250) return false;

		if (!$tag) return false;

		return true;
	}

	function render_login_form() {
		header('Cache-Control: public');

		require_once "login_form.php";
		exit;
	}

	function format_warning($msg, $id = "") {
		return "<div class=\"warning\" id=\"$id\">
			<span><img src=\"images/alert.png\"></span><span>$msg</span></div>";
	}

	function format_notice($msg, $id = "") {
		return "<div class=\"notice\" id=\"$id\">
			<span><img src=\"images/information.png\"></span><span>$msg</span></div>";
	}

	function format_error($msg, $id = "") {
		return "<div class=\"error\" id=\"$id\">
			<span><img src=\"images/alert.png\"></span><span>$msg</span></div>";
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

	function format_inline_player($url, $ctype) {

		$entry = "";

		$url = htmlspecialchars($url);

		if (strpos($ctype, "audio/") === 0) {

			if ($_SESSION["hasAudio"] && (strpos($ctype, "ogg") !== false ||
				$_SESSION["hasMp3"])) {

				$entry .= "<audio preload=\"none\" controls>
					<source type=\"$ctype\" src=\"$url\"></source>
					</audio>";

			} else {

				$entry .= "<object type=\"application/x-shockwave-flash\"
					data=\"lib/button/musicplayer.swf?song_url=$url\"
					width=\"17\" height=\"17\" style='float : left; margin-right : 5px;'>
					<param name=\"movie\"
						value=\"lib/button/musicplayer.swf?song_url=$url\" />
					</object>";
			}

			if ($entry) $entry .= "&nbsp; <a target=\"_blank\"
				href=\"$url\">" . basename($url) . "</a>";

			return $entry;

		}

		return "";

/*		$filename = substr($url, strrpos($url, "/")+1);

		$entry .= " <a target=\"_blank\" href=\"" . htmlspecialchars($url) . "\">" .
			$filename . " (" . $ctype . ")" . "</a>"; */

	}

	function format_article($id, $mark_as_read = true, $zoom_mode = false, $owner_uid = false) {
		if (!$owner_uid) $owner_uid = $_SESSION["uid"];

		$rv = array();

		$rv['id'] = $id;

		/* we can figure out feed_id from article id anyway, why do we
		 * pass feed_id here? let's ignore the argument :(*/

		$result = db_query("SELECT feed_id FROM ttrss_user_entries
			WHERE ref_id = '$id'");

		$feed_id = (int) db_fetch_result($result, 0, "feed_id");

		$rv['feed_id'] = $feed_id;

		//if (!$zoom_mode) { print "<article id='$id'><![CDATA["; };

		if ($mark_as_read) {
			$result = db_query("UPDATE ttrss_user_entries
				SET unread = false,last_read = NOW()
				WHERE ref_id = '$id' AND owner_uid = $owner_uid");

			ccache_update($feed_id, $owner_uid);
		}

		$result = db_query("SELECT id,title,link,content,feed_id,comments,int_id,lang,
			".SUBSTRING_FOR_DATE."(updated,1,16) as updated,
			(SELECT site_url FROM ttrss_feeds WHERE id = feed_id) as site_url,
			(SELECT title FROM ttrss_feeds WHERE id = feed_id) as feed_title,
			(SELECT hide_images FROM ttrss_feeds WHERE id = feed_id) as hide_images,
			(SELECT always_display_enclosures FROM ttrss_feeds WHERE id = feed_id) as always_display_enclosures,
			num_comments,
			tag_cache,
			author,
			orig_feed_id,
			note
			FROM ttrss_entries,ttrss_user_entries
			WHERE	id = '$id' AND ref_id = id AND owner_uid = $owner_uid");

		if ($result) {

			$line = db_fetch_assoc($result);

			$line["tags"] = get_article_tags($id, $owner_uid, $line["tag_cache"]);
			unset($line["tag_cache"]);

			$line["content"] = sanitize($line["content"],
				sql_bool_to_bool($line['hide_images']),
				$owner_uid, $line["site_url"], false, $line["id"]);

			foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_RENDER_ARTICLE) as $p) {
				$line = $p->hook_render_article($line);
			}

			$num_comments = $line["num_comments"];
			$entry_comments = "";

			if ($num_comments > 0) {
				if ($line["comments"]) {
					$comments_url = htmlspecialchars($line["comments"]);
				} else {
					$comments_url = htmlspecialchars($line["link"]);
				}
				$entry_comments = "<a class=\"postComments\"
					target='_blank' href=\"$comments_url\">$num_comments ".
					_ngettext("comment", "comments", $num_comments)."</a>";

			} else {
				if ($line["comments"] && $line["link"] != $line["comments"]) {
					$entry_comments = "<a class=\"postComments\" target='_blank' href=\"".htmlspecialchars($line["comments"])."\">".__("comments")."</a>";
				}
			}

			if ($zoom_mode) {
				header("Content-Type: text/html");
				$rv['content'] .= "<html><head>
						<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\"/>
						<title>Tiny Tiny RSS - ".$line["title"]."</title>".
						stylesheet_tag("css/tt-rss.css").
						stylesheet_tag("css/zoom.css").
						stylesheet_tag("css/dijit.css")."

						<link rel=\"shortcut icon\" type=\"image/png\" href=\"images/favicon.png\">
						<link rel=\"icon\" type=\"image/png\" sizes=\"72x72\" href=\"images/favicon-72px.png\">

						<script type=\"text/javascript\">
						function openSelectedAttachment(elem) {
							try {
								var url = elem[elem.selectedIndex].value;

								if (url) {
									window.open(url);
									elem.selectedIndex = 0;
								}

							} catch (e) {
								exception_error(\"openSelectedAttachment\", e);
							}
						}
					</script>
					</head><body id=\"ttrssZoom\">";
			}

			$rv['content'] .= "<div class=\"postReply\" id=\"POST-$id\">";

			$rv['content'] .= "<div class=\"postHeader\" id=\"POSTHDR-$id\">";

			$entry_author = $line["author"];

			if ($entry_author) {
				$entry_author = __(" - ") . $entry_author;
			}

			$parsed_updated = make_local_datetime($line["updated"], true,
				$owner_uid, true);

			if (!$zoom_mode)
				$rv['content'] .= "<div class=\"postDate\">$parsed_updated</div>";

			if ($line["link"]) {
				$rv['content'] .= "<div class='postTitle'><a target='_blank'
					title=\"".htmlspecialchars($line['title'])."\"
					href=\"" .
					htmlspecialchars($line["link"]) . "\">" .
					$line["title"] . "</a>" .
					"<span class='author'>$entry_author</span></div>";
			} else {
				$rv['content'] .= "<div class='postTitle'>" . $line["title"] . "$entry_author</div>";
			}

			if ($zoom_mode) {
				$feed_title = "<a href=\"".htmlspecialchars($line["site_url"]).
					"\" target=\"_blank\">".
					htmlspecialchars($line["feed_title"])."</a>";

				$rv['content'] .= "<div class=\"postFeedTitle\">$feed_title</div>";

				$rv['content'] .= "<div class=\"postDate\">$parsed_updated</div>";
			}

			$tags_str = format_tags_string($line["tags"], $id);
			$tags_str_full = join(", ", $line["tags"]);

			if (!$tags_str_full) $tags_str_full = __("no tags");

			if (!$entry_comments) $entry_comments = "&nbsp;"; # placeholder

			$rv['content'] .= "<div class='postTags' style='float : right'>
				<img src='images/tag.png'
				class='tagsPic' alt='Tags' title='Tags'>&nbsp;";

			if (!$zoom_mode) {
				$rv['content'] .= "<span id=\"ATSTR-$id\">$tags_str</span>
					<a title=\"".__('Edit tags for this article')."\"
					href=\"#\" onclick=\"editArticleTags($id, $feed_id)\">(+)</a>";

				$rv['content'] .= "<div dojoType=\"dijit.Tooltip\"
					id=\"ATSTRTIP-$id\" connectId=\"ATSTR-$id\"
					position=\"below\">$tags_str_full</div>";

				foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_ARTICLE_BUTTON) as $p) {
					$rv['content'] .= $p->hook_article_button($line);
				}

			} else {
				$tags_str = strip_tags($tags_str);
				$rv['content'] .= "<span id=\"ATSTR-$id\">$tags_str</span>";
			}
			$rv['content'] .= "</div>";
			$rv['content'] .= "<div clear='both'>";

			foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_ARTICLE_LEFT_BUTTON) as $p) {
				$rv['content'] .= $p->hook_article_left_button($line);
			}

			$rv['content'] .= "$entry_comments</div>";

			if ($line["orig_feed_id"]) {

				$tmp_result = db_query("SELECT * FROM ttrss_archived_feeds
					WHERE id = ".$line["orig_feed_id"]);

				if (db_num_rows($tmp_result) != 0) {

					$rv['content'] .= "<div clear='both'>";
					$rv['content'] .= __("Originally from:");

					$rv['content'] .= "&nbsp;";

					$tmp_line = db_fetch_assoc($tmp_result);

					$rv['content'] .= "<a target='_blank'
						href=' " . htmlspecialchars($tmp_line['site_url']) . "'>" .
						$tmp_line['title'] . "</a>";

					$rv['content'] .= "&nbsp;";

					$rv['content'] .= "<a target='_blank' href='" . htmlspecialchars($tmp_line['feed_url']) . "'>";
					$rv['content'] .= "<img title='".__('Feed URL')."' class='tinyFeedIcon' src='images/pub_set.png'></a>";

					$rv['content'] .= "</div>";
				}
			}

			$rv['content'] .= "</div>";

			$rv['content'] .= "<div id=\"POSTNOTE-$id\">";
				if ($line['note']) {
					$rv['content'] .= format_article_note($id, $line['note'], !$zoom_mode);
				}
			$rv['content'] .= "</div>";

			if (!$line['lang']) $line['lang'] = 'en';

			$rv['content'] .= "<div class=\"postContent\" lang=\"".$line['lang']."\">";

			$rv['content'] .= $line["content"];
			$rv['content'] .= format_article_enclosures($id,
				sql_bool_to_bool($line["always_display_enclosures"]),
				$line["content"],
				sql_bool_to_bool($line["hide_images"]));

			$rv['content'] .= "</div>";

			$rv['content'] .= "</div>";

		}

		if ($zoom_mode) {
			$rv['content'] .= "
				<div class='footer'>
				<button onclick=\"return window.close()\">".
					__("Close this window")."</button></div>";
			$rv['content'] .= "</body></html>";
		}

		return $rv;

	}

	function print_checkpoint($n, $s) {
		$ts = microtime(true);
		echo sprintf("<!-- CP[$n] %.4f seconds -->\n", $ts - $s);
		return $ts;
	}

	function sanitize_tag($tag) {
		$tag = trim($tag);

		$tag = mb_strtolower($tag, 'utf-8');

		$tag = preg_replace('/[\'\"\+\>\<]/', "", $tag);

//		$tag = str_replace('"', "", $tag);
//		$tag = str_replace("+", " ", $tag);
		$tag = str_replace("technorati tag: ", "", $tag);

		return $tag;
	}

	function get_self_url_prefix() {
		if (strrpos(SELF_URL_PATH, "/") === strlen(SELF_URL_PATH)-1) {
			return substr(SELF_URL_PATH, 0, strlen(SELF_URL_PATH)-1);
		} else {
			return SELF_URL_PATH;
		}
	}

	/**
	 * Compute the Mozilla Firefox feed adding URL from server HOST and REQUEST_URI.
	 *
	 * @return string The Mozilla Firefox feed adding URL.
	 */
	function add_feed_url() {
		//$url_path = ($_SERVER['HTTPS'] != "on" ? 'http://' :  'https://') . $_SERVER["HTTP_HOST"] . parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

		$url_path = get_self_url_prefix() .
			"/public.php?op=subscribe&feed_url=%s";
		return $url_path;
	} // function add_feed_url

	function encrypt_password($pass, $salt = '', $mode2 = false) {
		if ($salt && $mode2) {
			return "MODE2:" . hash('sha256', $salt . $pass);
		} else if ($salt) {
			return "SHA1X:" . sha1("$salt:$pass");
		} else {
			return "SHA1:" . sha1($pass);
		}
	} // function encrypt_password

	function load_filters($feed_id, $owner_uid, $action_id = false) {
		$filters = array();

		$cat_id = (int)getFeedCategory($feed_id);

		if ($cat_id == 0)
			$null_cat_qpart = "cat_id IS NULL OR";
		else
			$null_cat_qpart = "";

		$result = db_query("SELECT * FROM ttrss_filters2 WHERE
			owner_uid = $owner_uid AND enabled = true ORDER BY order_id, title");

		$check_cats = join(",", array_merge(
			getParentCategories($cat_id, $owner_uid),
			array($cat_id)));

		while ($line = db_fetch_assoc($result)) {
			$filter_id = $line["id"];

			$result2 = db_query("SELECT
				r.reg_exp, r.inverse, r.feed_id, r.cat_id, r.cat_filter, t.name AS type_name
				FROM ttrss_filters2_rules AS r,
				ttrss_filter_types AS t
				WHERE
					($null_cat_qpart (cat_id IS NULL AND cat_filter = false) OR cat_id IN ($check_cats)) AND
					(feed_id IS NULL OR feed_id = '$feed_id') AND
					filter_type = t.id AND filter_id = '$filter_id'");

			$rules = array();
			$actions = array();

			while ($rule_line = db_fetch_assoc($result2)) {
#				print_r($rule_line);

				$rule = array();
				$rule["reg_exp"] = $rule_line["reg_exp"];
				$rule["type"] = $rule_line["type_name"];
				$rule["inverse"] = sql_bool_to_bool($rule_line["inverse"]);

				array_push($rules, $rule);
			}

			$result2 = db_query("SELECT a.action_param,t.name AS type_name
				FROM ttrss_filters2_actions AS a,
				ttrss_filter_actions AS t
				WHERE
					action_id = t.id AND filter_id = '$filter_id'");

			while ($action_line = db_fetch_assoc($result2)) {
#				print_r($action_line);

				$action = array();
				$action["type"] = $action_line["type_name"];
				$action["param"] = $action_line["action_param"];

				array_push($actions, $action);
			}


			$filter = array();
			$filter["match_any_rule"] = sql_bool_to_bool($line["match_any_rule"]);
			$filter["inverse"] = sql_bool_to_bool($line["inverse"]);
			$filter["rules"] = $rules;
			$filter["actions"] = $actions;

			if (count($rules) > 0 && count($actions) > 0) {
				array_push($filters, $filter);
			}
		}

		return $filters;
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

	function feed_has_icon($id) {
		return is_file(ICONS_DIR . "/$id.ico") && filesize(ICONS_DIR . "/$id.ico") > 0;
	}

	function init_plugins() {
		PluginHost::getInstance()->load(PLUGINS, PluginHost::KIND_ALL);

		return true;
	}

	function format_tags_string($tags, $id) {
		if (!is_array($tags) || count($tags) == 0) {
			return __("no tags");
		} else {
			$maxtags = min(5, count($tags));

			for ($i = 0; $i < $maxtags; $i++) {
				$tags_str .= "<a class=\"tag\" href=\"#\" onclick=\"viewfeed('".$tags[$i]."')\">" . $tags[$i] . "</a>, ";
			}

			$tags_str = mb_substr($tags_str, 0, mb_strlen($tags_str)-2);

			if (count($tags) > $maxtags)
				$tags_str .= ", &hellip;";

			return $tags_str;
		}
	}

	function format_article_labels($labels, $id) {

		if (!is_array($labels)) return '';

		$labels_str = "";

		foreach ($labels as $l) {
			$labels_str .= sprintf("<span class='hlLabelRef'
				style='color : %s; background-color : %s'>%s</span>",
					$l[2], $l[3], $l[1]);
			}

		return $labels_str;

	}

	function format_article_note($id, $note, $allow_edit = true) {

		$str = "<div class='articleNote'	onclick=\"editArticleNote($id)\">
			<div class='noteEdit' onclick=\"editArticleNote($id)\">".
			($allow_edit ? __('(edit note)') : "")."</div>$note</div>";

		return $str;
	}


	function get_feed_category($feed_cat, $parent_cat_id = false) {
		if ($parent_cat_id) {
			$parent_qpart = "parent_cat = '$parent_cat_id'";
			$parent_insert = "'$parent_cat_id'";
		} else {
			$parent_qpart = "parent_cat IS NULL";
			$parent_insert = "NULL";
		}

		$result = db_query(
			"SELECT id FROM ttrss_feed_categories
			WHERE $parent_qpart AND title = '$feed_cat' AND owner_uid = ".$_SESSION["uid"]);

		if (db_num_rows($result) == 0) {
			return false;
		} else {
			return db_fetch_result($result, 0, "id");
		}
	}

	function add_feed_category($feed_cat, $parent_cat_id = false) {

		if (!$feed_cat) return false;

		db_query("BEGIN");

		if ($parent_cat_id) {
			$parent_qpart = "parent_cat = '$parent_cat_id'";
			$parent_insert = "'$parent_cat_id'";
		} else {
			$parent_qpart = "parent_cat IS NULL";
			$parent_insert = "NULL";
		}

		$feed_cat = mb_substr($feed_cat, 0, 250);

		$result = db_query(
			"SELECT id FROM ttrss_feed_categories
			WHERE $parent_qpart AND title = '$feed_cat' AND owner_uid = ".$_SESSION["uid"]);

		if (db_num_rows($result) == 0) {

			$result = db_query(
				"INSERT INTO ttrss_feed_categories (owner_uid,title,parent_cat)
				VALUES ('".$_SESSION["uid"]."', '$feed_cat', $parent_insert)");

			db_query("COMMIT");

			return true;
		}

		return false;
	}

	function getArticleFeed($id) {
		$result = db_query("SELECT feed_id FROM ttrss_user_entries
			WHERE ref_id = '$id' AND owner_uid = " . $_SESSION["uid"]);

		if (db_num_rows($result) != 0) {
			return db_fetch_result($result, 0, "feed_id");
		} else {
			return 0;
		}
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

		if ($url != "http:///")
			return $url;
		else
			return '';
	}

	function validate_feed_url($url) {
		$parts = parse_url($url);

		return ($parts['scheme'] == 'http' || $parts['scheme'] == 'feed' || $parts['scheme'] == 'https');

	}

	function get_article_enclosures($id) {

		$query = "SELECT * FROM ttrss_enclosures
			WHERE post_id = '$id' AND content_url != ''";

		$rv = array();

		$result = db_query($query);

		if (db_num_rows($result) > 0) {
			while ($line = db_fetch_assoc($result)) {
				array_push($rv, $line);
			}
		}

		return $rv;
	}

	/* function save_email_address($email) {
		// FIXME: implement persistent storage of emails

		if (!$_SESSION['stored_emails'])
			$_SESSION['stored_emails'] = array();

		if (!in_array($email, $_SESSION['stored_emails']))
			array_push($_SESSION['stored_emails'], $email);
	} */


	function get_feed_access_key($feed_id, $is_cat, $owner_uid = false) {

		if (!$owner_uid) $owner_uid = $_SESSION["uid"];

		$sql_is_cat = bool_to_sql_bool($is_cat);

		$result = db_query("SELECT access_key FROM ttrss_access_keys
			WHERE feed_id = '$feed_id'	AND is_cat = $sql_is_cat
			AND owner_uid = " . $owner_uid);

		if (db_num_rows($result) == 1) {
			return db_fetch_result($result, 0, "access_key");
		} else {
			$key = db_escape_string(uniqid(base_convert(rand(), 10, 36)));

			$result = db_query("INSERT INTO ttrss_access_keys
				(access_key, feed_id, is_cat, owner_uid)
				VALUES ('$key', '$feed_id', $sql_is_cat, '$owner_uid')");

			return $key;
		}
		return false;
	}

	function get_feeds_from_html($url, $content)
	{
		$url     = fix_url($url);
		$baseUrl = substr($url, 0, strrpos($url, '/') + 1);

		libxml_use_internal_errors(true);

		$doc = new DOMDocument();
		$doc->loadHTML($content);
		$xpath = new DOMXPath($doc);
		$entries = $xpath->query('/html/head/link[@rel="alternate" and '.
			'(contains(@type,"rss") or contains(@type,"atom"))]|/html/head/link[@rel="feed"]');
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

	function is_html($content) {
		return preg_match("/<html|DOCTYPE html/i", substr($content, 0, 20)) !== 0;
	}

	function url_is_html($url, $login = false, $pass = false) {
		return is_html(fetch_file_contents($url, false, $login, $pass));
	}

	function print_label_select($name, $value, $attributes = "") {

		$result = db_query("SELECT caption FROM ttrss_labels2
			WHERE owner_uid = '".$_SESSION["uid"]."' ORDER BY caption");

		print "<select default=\"$value\" name=\"" . htmlspecialchars($name) .
			"\" $attributes onchange=\"labelSelectOnChange(this)\" >";

		while ($line = db_fetch_assoc($result)) {

			$issel = ($line["caption"] == $value) ? "selected=\"1\"" : "";

			print "<option value=\"".htmlspecialchars($line["caption"])."\"
				$issel>" . htmlspecialchars($line["caption"]) . "</option>";

		}

#		print "<option value=\"ADD_LABEL\">" .__("Add label...") . "</option>";

		print "</select>";


	}

	function format_article_enclosures($id, $always_display_enclosures,
					$article_content, $hide_images = false) {

		$result = get_article_enclosures($id);
		$rv = '';

		foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_FORMAT_ENCLOSURES) as $plugin) {
			$retval = $plugin->hook_format_enclosures($rv, $result, $id, $always_display_enclosures, $article_content, $hide_images);
			if (is_array($retval)) {
				$rv = $retval[0];
				$result = $retval[1];
			} else {
				$rv = $retval;
			}
		}

		if ($rv === '' && !empty($result)) {
			$entries_html = array();
			$entries = array();
			$entries_inline = array();

			foreach ($result as $line) {

				$url = $line["content_url"];
				$ctype = $line["content_type"];
				$title = $line["title"];
				$width = $line["width"];
				$height = $line["height"];

				if (!$ctype) $ctype = __("unknown type");

				$filename = substr($url, strrpos($url, "/")+1);

				$player = format_inline_player($url, $ctype);

				if ($player) array_push($entries_inline, $player);

#				$entry .= " <a target=\"_blank\" href=\"" . htmlspecialchars($url) . "\">" .
#					$filename . " (" . $ctype . ")" . "</a>";

				$entry = "<div onclick=\"window.open('".htmlspecialchars($url)."')\"
					dojoType=\"dijit.MenuItem\">$filename ($ctype)</div>";

				array_push($entries_html, $entry);

				$entry = array();

				$entry["type"] = $ctype;
				$entry["filename"] = $filename;
				$entry["url"] = $url;
				$entry["title"] = $title;
				$entry["width"] = $width;
				$entry["height"] = $height;

				array_push($entries, $entry);
			}

			if ($_SESSION['uid'] && !get_pref("STRIP_IMAGES") && !$_SESSION["bw_limit"]) {
				if ($always_display_enclosures ||
							!preg_match("/<img/i", $article_content)) {

					foreach ($entries as $entry) {

						if (preg_match("/image/", $entry["type"]) ||
								preg_match("/\.(jpg|png|gif|bmp)/i", $entry["filename"])) {

								if (!$hide_images) {
									$encsize = '';
									if ($entry['height'] > 0)
										$encsize .= ' height="' . intval($entry['width']) . '"';
									if ($entry['width'] > 0)
										$encsize .= ' width="' . intval($entry['height']) . '"';
									$rv .= "<p><img
									alt=\"".htmlspecialchars($entry["filename"])."\"
									src=\"" .htmlspecialchars($entry["url"]) . "\"
									" . $encsize . " /></p>";
								} else {
									$rv .= "<p><a target=\"_blank\"
									href=\"".htmlspecialchars($entry["url"])."\"
									>" .htmlspecialchars($entry["url"]) . "</a></p>";
								}

								if ($entry['title']) {
									$rv.= "<div class=\"enclosure_title\">${entry['title']}</div>";
								}
						}
					}
				}
			}

			if (count($entries_inline) > 0) {
				$rv .= "<hr clear='both'/>";
				foreach ($entries_inline as $entry) { $rv .= $entry; };
				$rv .= "<hr clear='both'/>";
			}

			$rv .= "<select class=\"attachments\" onchange=\"openSelectedAttachment(this)\">".
				"<option value=''>" . __('Attachments')."</option>";

			foreach ($entries as $entry) {
				if ($entry["title"])
					$title = "&mdash; " . truncate_string($entry["title"], 30);
				else
					$title = "";

				$rv .= "<option value=\"".htmlspecialchars($entry["url"])."\">" . htmlspecialchars($entry["filename"]) . "$title</option>";

			};

			$rv .= "</select>";
		}

		return $rv;
	}

	function getLastArticleId() {
		$result = db_query("SELECT MAX(ref_id) AS id FROM ttrss_user_entries
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
		if (strpos($rel_url, ":") !== false) {
			return $rel_url;
		} else if (strpos($rel_url, "://") !== false) {
			return $rel_url;
		} else if (strpos($rel_url, "//") === 0) {
			# protocol-relative URL (rare but they exist)
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

	function cleanup_tags($days = 14, $limit = 1000) {

		if (DB_TYPE == "pgsql") {
			$interval_query = "date_updated < NOW() - INTERVAL '$days days'";
		} else if (DB_TYPE == "mysql") {
			$interval_query = "date_updated < DATE_SUB(NOW(), INTERVAL $days DAY)";
		}

		$tags_deleted = 0;

		while ($limit > 0) {
			$limit_part = 500;

			$query = "SELECT ttrss_tags.id AS id
				FROM ttrss_tags, ttrss_user_entries, ttrss_entries
				WHERE post_int_id = int_id AND $interval_query AND
				ref_id = ttrss_entries.id AND tag_cache != '' LIMIT $limit_part";

			$result = db_query($query);

			$ids = array();

			while ($line = db_fetch_assoc($result)) {
				array_push($ids, $line['id']);
			}

			if (count($ids) > 0) {
				$ids = join(",", $ids);

				$tmp_result = db_query("DELETE FROM ttrss_tags WHERE id IN ($ids)");
				$tags_deleted += db_affected_rows($tmp_result);
			} else {
				break;
			}

			$limit -= $limit_part;
		}

		return $tags_deleted;
	}

	function print_user_stylesheet() {
		$value = get_pref('USER_STYLESHEET');

		if ($value) {
			print "<style type=\"text/css\">";
			print str_replace("<br/>", "\n", $value);
			print "</style>";
		}

	}

	function filter_to_sql($filter, $owner_uid) {
		$query = array();

		if (DB_TYPE == "pgsql")
			$reg_qpart = "~";
		else
			$reg_qpart = "REGEXP";

		foreach ($filter["rules"] AS $rule) {
			$rule['reg_exp'] = str_replace('/', '\/', $rule["reg_exp"]);
			$regexp_valid = preg_match('/' . $rule['reg_exp'] . '/',
				$rule['reg_exp']) !== FALSE;

			if ($regexp_valid) {

				$rule['reg_exp'] = db_escape_string($rule['reg_exp']);

					switch ($rule["type"]) {
					case "title":
						$qpart = "LOWER(ttrss_entries.title) $reg_qpart LOWER('".
							$rule['reg_exp'] . "')";
						break;
					case "content":
						$qpart = "LOWER(ttrss_entries.content) $reg_qpart LOWER('".
							$rule['reg_exp'] . "')";
						break;
					case "both":
						$qpart = "LOWER(ttrss_entries.title) $reg_qpart LOWER('".
							$rule['reg_exp'] . "') OR LOWER(" .
							"ttrss_entries.content) $reg_qpart LOWER('" . $rule['reg_exp'] . "')";
						break;
					case "tag":
						$qpart = "LOWER(ttrss_user_entries.tag_cache) $reg_qpart LOWER('".
							$rule['reg_exp'] . "')";
						break;
					case "link":
						$qpart = "LOWER(ttrss_entries.link) $reg_qpart LOWER('".
							$rule['reg_exp'] . "')";
						break;
					case "author":
						$qpart = "LOWER(ttrss_entries.author) $reg_qpart LOWER('".
							$rule['reg_exp'] . "')";
						break;
				}

				if (isset($rule['inverse'])) $qpart = "NOT ($qpart)";

				if (isset($rule["feed_id"]) && $rule["feed_id"] > 0) {
					$qpart .= " AND feed_id = " . db_escape_string($rule["feed_id"]);
				}

				if (isset($rule["cat_id"])) {

					if ($rule["cat_id"] > 0) {
						$children = getChildCategories($rule["cat_id"], $owner_uid);
						array_push($children, $rule["cat_id"]);

						$children = join(",", $children);

						$cat_qpart = "cat_id IN ($children)";
					} else {
						$cat_qpart = "cat_id IS NULL";
					}

					$qpart .= " AND $cat_qpart";
				}

				$qpart .= " AND feed_id IS NOT NULL";

				array_push($query, "($qpart)");

			}
		}

		if (count($query) > 0) {
			$fullquery = "(" . join($filter["match_any_rule"] ? "OR" : "AND", $query) . ")";
		} else {
			$fullquery = "(false)";
		}

		if ($filter['inverse']) $fullquery = "(NOT $fullquery)";

		return $fullquery;
	}

	if (!function_exists('gzdecode')) {
		function gzdecode($string) { // no support for 2nd argument
			return file_get_contents('compress.zlib://data:who/cares;base64,'.
				base64_encode($string));
		}
	}

	function get_random_bytes($length) {
		if (function_exists('openssl_random_pseudo_bytes')) {
			return openssl_random_pseudo_bytes($length);
		} else {
			$output = "";

			for ($i = 0; $i < $length; $i++)
				$output .= chr(mt_rand(0, 255));

			return $output;
		}
	}

	function read_stdin() {
		$fp = fopen("php://stdin", "r");

		if ($fp) {
			$line = trim(fgets($fp));
			fclose($fp);
			return $line;
		}

		return null;
	}

	function tmpdirname($path, $prefix) {
		// Use PHP's tmpfile function to create a temporary
		// directory name. Delete the file and keep the name.
		$tempname = tempnam($path,$prefix);
		if (!$tempname)
			return false;

		if (!unlink($tempname))
			return false;

       return $tempname;
	}

	function getFeedCategory($feed) {
		$result = db_query("SELECT cat_id FROM ttrss_feeds
			WHERE id = '$feed'");

		if (db_num_rows($result) > 0) {
			return db_fetch_result($result, 0, "cat_id");
		} else {
			return false;
		}

	}

	function implements_interface($class, $interface) {
		return in_array($interface, class_implements($class));
	}

	function geturl($url, $depth = 0){

		if ($depth == 20) return $url;

		if (!function_exists('curl_init'))
			return user_error('CURL Must be installed for geturl function to work. Ask your host to enable it or uncomment extension=php_curl.dll in php.ini', E_USER_ERROR);

		$curl = curl_init();
		$header[0] = "Accept: text/xml,application/xml,application/xhtml+xml,";
		$header[0] .= "text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5";
		$header[] = "Cache-Control: max-age=0";
		$header[] = "Connection: keep-alive";
		$header[] = "Keep-Alive: 300";
		$header[] = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7";
		$header[] = "Accept-Language: en-us,en;q=0.5";
		$header[] = "Pragma: ";

		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 5.1; rv:5.0) Gecko/20100101 Firefox/5.0 Firefox/5.0');
		curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
		curl_setopt($curl, CURLOPT_HEADER, true);
		curl_setopt($curl, CURLOPT_NOBODY, true);
		curl_setopt($curl, CURLOPT_REFERER, $url);
		curl_setopt($curl, CURLOPT_ENCODING, 'gzip,deflate');
		curl_setopt($curl, CURLOPT_AUTOREFERER, true);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		//curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true); //CURLOPT_FOLLOWLOCATION Disabled...
		curl_setopt($curl, CURLOPT_TIMEOUT, 60);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

		if (defined('_CURL_HTTP_PROXY')) {
			curl_setopt($curl, CURLOPT_PROXY, _CURL_HTTP_PROXY);
		}

		if ((OPENSSL_VERSION_NUMBER >= 0x0090808f) && (OPENSSL_VERSION_NUMBER < 0x10000000)) {
			curl_setopt($curl, CURLOPT_SSLVERSION, 3);
		}

		$html = curl_exec($curl);

		$status = curl_getinfo($curl);

		if($status['http_code']!=200){
			if($status['http_code'] == 301 || $status['http_code'] == 302) {
				curl_close($curl);
				list($header) = explode("\r\n\r\n", $html, 2);
				$matches = array();
				preg_match("/(Location:|URI:)[^(\n)]*/", $header, $matches);
				$url = trim(str_replace($matches[1],"",$matches[0]));
				$url_parsed = parse_url($url);
				return (isset($url_parsed))? geturl($url, $depth + 1):'';
			}

			global $fetch_last_error;

			$fetch_last_error = curl_errno($curl) . " " . curl_error($curl);
			curl_close($curl);

#			$oline='';
#			foreach($status as $key=>$eline){$oline.='['.$key.']'.$eline.' ';}
#			$line =$oline." \r\n ".$url."\r\n-----------------\r\n";
#			$handle = @fopen('./curl.error.log', 'a');
#			fwrite($handle, $line);
			return FALSE;
		}
		curl_close($curl);
		return $url;
	}

	function get_minified_js($files) {
		require_once 'lib/jshrink/Minifier.php';

		$rv = '';

		foreach ($files as $js) {
			if (!isset($_GET['debug'])) {
				$cached_file = CACHE_DIR . "/js/".basename($js).".js";

				if (file_exists($cached_file) &&
						is_readable($cached_file) &&
						filemtime($cached_file) >= filemtime("js/$js.js")) {

					$rv .= file_get_contents($cached_file);

				} else {
					$minified = JShrink\Minifier::minify(file_get_contents("js/$js.js"));
					file_put_contents($cached_file, $minified);
					$rv .= $minified;
				}
			} else {
				$rv .= file_get_contents("js/$js.js");
			}
		}

		return $rv;
	}

	function stylesheet_tag($filename) {
		$timestamp = filemtime($filename);

		return "<link rel=\"stylesheet\" type=\"text/css\" href=\"$filename?$timestamp\"/>\n";
	}

	function javascript_tag($filename) {
		$query = "";

		if (!(strpos($filename, "?") === FALSE)) {
			$query = substr($filename, strpos($filename, "?")+1);
			$filename = substr($filename, 0, strpos($filename, "?"));
		}

		$timestamp = filemtime($filename);

		if ($query) $timestamp .= "&$query";

		return "<script type=\"text/javascript\" charset=\"utf-8\" src=\"$filename?$timestamp\"></script>\n";
	}

	function calculate_dep_timestamp() {
		$files = array_merge(glob("js/*.js"), glob("css/*.css"));

		$max_ts = -1;

		foreach ($files as $file) {
			if (filemtime($file) > $max_ts) $max_ts = filemtime($file);
		}

		return $max_ts;
	}

	function T_js_decl($s1, $s2) {
		if ($s1 && $s2) {
			$s1 = preg_replace("/\n/", "", $s1);
			$s2 = preg_replace("/\n/", "", $s2);

			$s1 = preg_replace("/\"/", "\\\"", $s1);
			$s2 = preg_replace("/\"/", "\\\"", $s2);

			return "T_messages[\"$s1\"] = \"$s2\";\n";
		}
	}

	function init_js_translations() {

	print 'var T_messages = new Object();

		function __(msg) {
			if (T_messages[msg]) {
				return T_messages[msg];
			} else {
				return msg;
			}
		}

		function ngettext(msg1, msg2, n) {
			return __((parseInt(n) > 1) ? msg2 : msg1);
		}';

		$l10n = _get_reader();

		for ($i = 0; $i < $l10n->total; $i++) {
			$orig = $l10n->get_original_string($i);
			if(strpos($orig, "\000") !== FALSE) { // Plural forms
				$key = explode(chr(0), $orig);
				print T_js_decl($key[0], _ngettext($key[0], $key[1], 1)); // Singular
				print T_js_decl($key[1], _ngettext($key[0], $key[1], 2)); // Plural
			} else {
				$translation = __($orig);
				print T_js_decl($orig, $translation);
			}
		}
	}

	function label_to_feed_id($label) {
		return LABEL_BASE_INDEX - 1 - abs($label);
	}

	function feed_to_label_id($feed) {
		return LABEL_BASE_INDEX - 1 + abs($feed);
	}

	function format_libxml_error($error) {
		return T_sprintf("LibXML error %s at line %d (column %d): %s",
				$error->code, $error->line, $error->column,
				$error->message);
	}
?>

<?php

class API extends Handler {

	const API_LEVEL  = 12;

	const STATUS_OK  = 0;
	const STATUS_ERR = 1;

	private $seq;

	function before($method) {
		if (parent::before($method)) {
			header("Content-Type: text/json");

			if (!$_SESSION["uid"] && $method != "login" && $method != "isloggedin") {
				$this->wrap(self::STATUS_ERR, array("error" => 'NOT_LOGGED_IN'));
				return false;
			}

			if ($_SESSION["uid"] && $method != "logout" && !get_pref('ENABLE_API_ACCESS')) {
				$this->wrap(self::STATUS_ERR, array("error" => 'API_DISABLED'));
				return false;
			}

			$this->seq = (int) $_REQUEST['seq'];

			return true;
		}
		return false;
	}

	function wrap($status, $reply) {
		print json_encode(array("seq" => $this->seq,
			"status" => $status,
			"content" => $reply));
	}

	function getVersion() {
		$rv = array("version" => VERSION);
		$this->wrap(self::STATUS_OK, $rv);
	}

	function getApiLevel() {
		$rv = array("level" => self::API_LEVEL);
		$this->wrap(self::STATUS_OK, $rv);
	}

	function login() {
		@session_destroy();
		@session_start();

		$login = $this->dbh->escape_string($_REQUEST["user"]);
		$password = $_REQUEST["password"];
		$password_base64 = base64_decode($_REQUEST["password"]);

		if (SINGLE_USER_MODE) $login = "admin";

		$result = $this->dbh->query("SELECT id FROM ttrss_users WHERE login = '$login'");

		if ($this->dbh->num_rows($result) != 0) {
			$uid = $this->dbh->fetch_result($result, 0, "id");
		} else {
			$uid = 0;
		}

		if (!$uid) {
			$this->wrap(self::STATUS_ERR, array("error" => "LOGIN_ERROR"));
			return;
		}

		if (get_pref("ENABLE_API_ACCESS", $uid)) {
			if (authenticate_user($login, $password)) {               // try login with normal password
				$this->wrap(self::STATUS_OK, array("session_id" => session_id(),
					"api_level" => self::API_LEVEL));
			} else if (authenticate_user($login, $password_base64)) { // else try with base64_decoded password
				$this->wrap(self::STATUS_OK,	array("session_id" => session_id(),
					"api_level" => self::API_LEVEL));
			} else {                                                         // else we are not logged in
				user_error("Failed login attempt for $login from {$_SERVER['REMOTE_ADDR']}", E_USER_WARNING);
				$this->wrap(self::STATUS_ERR, array("error" => "LOGIN_ERROR"));
			}
		} else {
			$this->wrap(self::STATUS_ERR, array("error" => "API_DISABLED"));
		}

	}

	function logout() {
		logout_user();
		$this->wrap(self::STATUS_OK, array("status" => "OK"));
	}

	function isLoggedIn() {
		$this->wrap(self::STATUS_OK, array("status" => $_SESSION["uid"] != ''));
	}

	function getUnread() {
		$feed_id = $this->dbh->escape_string($_REQUEST["feed_id"]);
		$is_cat = $this->dbh->escape_string($_REQUEST["is_cat"]);

		if ($feed_id) {
			$this->wrap(self::STATUS_OK, array("unread" => getFeedUnread($feed_id, $is_cat)));
		} else {
			$this->wrap(self::STATUS_OK, array("unread" => getGlobalUnread()));
		}
	}

	/* Method added for ttrss-reader for Android */
	function getCounters() {
		$this->wrap(self::STATUS_OK, getAllCounters());
	}

	function getFeeds() {
		$cat_id = $this->dbh->escape_string($_REQUEST["cat_id"]);
		$unread_only = sql_bool_to_bool($_REQUEST["unread_only"]);
		$limit = (int) $this->dbh->escape_string($_REQUEST["limit"]);
		$offset = (int) $this->dbh->escape_string($_REQUEST["offset"]);
		$include_nested = sql_bool_to_bool($_REQUEST["include_nested"]);

		$feeds = $this->api_get_feeds($cat_id, $unread_only, $limit, $offset, $include_nested);

		$this->wrap(self::STATUS_OK, $feeds);
	}

	function getCategories() {
		$unread_only = sql_bool_to_bool($_REQUEST["unread_only"]);
		$enable_nested = sql_bool_to_bool($_REQUEST["enable_nested"]);
		$include_empty = sql_bool_to_bool($_REQUEST['include_empty']);

		// TODO do not return empty categories, return Uncategorized and standard virtual cats

		if ($enable_nested)
			$nested_qpart = "parent_cat IS NULL";
		else
			$nested_qpart = "true";

		$result = $this->dbh->query("SELECT
				id, title, order_id, (SELECT COUNT(id) FROM
				ttrss_feeds WHERE
				ttrss_feed_categories.id IS NOT NULL AND cat_id = ttrss_feed_categories.id) AS num_feeds,
			(SELECT COUNT(id) FROM
				ttrss_feed_categories AS c2 WHERE
				c2.parent_cat = ttrss_feed_categories.id) AS num_cats
			FROM ttrss_feed_categories
			WHERE $nested_qpart AND owner_uid = " .
			$_SESSION["uid"]);

		$cats = array();

		while ($line = $this->dbh->fetch_assoc($result)) {
			if ($include_empty || $line["num_feeds"] > 0 || $line["num_cats"] > 0) {
				$unread = getFeedUnread($line["id"], true);

				if ($enable_nested)
					$unread += getCategoryChildrenUnread($line["id"]);

				if ($unread || !$unread_only) {
					array_push($cats, array("id" => $line["id"],
						"title" => $line["title"],
						"unread" => $unread,
						"order_id" => (int) $line["order_id"],
					));
				}
			}
		}

		foreach (array(-2,-1,0) as $cat_id) {
			if ($include_empty || !$this->isCategoryEmpty($cat_id)) {
				$unread = getFeedUnread($cat_id, true);

				if ($unread || !$unread_only) {
					array_push($cats, array("id" => $cat_id,
						"title" => getCategoryTitle($cat_id),
						"unread" => $unread));
				}
			}
		}

		$this->wrap(self::STATUS_OK, $cats);
	}

	function getHeadlines() {
		$feed_id = $this->dbh->escape_string($_REQUEST["feed_id"]);
		if ($feed_id != "") {

			if (is_numeric($feed_id)) $feed_id = (int) $feed_id;

			$limit = (int)$this->dbh->escape_string($_REQUEST["limit"]);

			if (!$limit || $limit >= 200) $limit = 200;

			$offset = (int)$this->dbh->escape_string($_REQUEST["skip"]);
			$filter = $this->dbh->escape_string($_REQUEST["filter"]);
			$is_cat = sql_bool_to_bool($_REQUEST["is_cat"]);
			$show_excerpt = sql_bool_to_bool($_REQUEST["show_excerpt"]);
			$show_content = sql_bool_to_bool($_REQUEST["show_content"]);
			/* all_articles, unread, adaptive, marked, updated */
			$view_mode = $this->dbh->escape_string($_REQUEST["view_mode"]);
			$include_attachments = sql_bool_to_bool($_REQUEST["include_attachments"]);
			$since_id = (int)$this->dbh->escape_string($_REQUEST["since_id"]);
			$include_nested = sql_bool_to_bool($_REQUEST["include_nested"]);
			$sanitize_content = !isset($_REQUEST["sanitize"]) ||
				sql_bool_to_bool($_REQUEST["sanitize"]);
			$force_update = sql_bool_to_bool($_REQUEST["force_update"]);
			$has_sandbox = sql_bool_to_bool($_REQUEST["has_sandbox"]);
			$excerpt_length = (int)$this->dbh->escape_string($_REQUEST["excerpt_length"]);
			$check_first_id = (int)$this->dbh->escape_string($_REQUEST["check_first_id"]);
			$include_header = sql_bool_to_bool($_REQUEST["include_header"]);

			$_SESSION['hasSandbox'] = $has_sandbox;

			$override_order = false;
			switch ($_REQUEST["order_by"]) {
				case "title":
					$override_order = "ttrss_entries.title";
					break;
				case "date_reverse":
					$override_order = "score DESC, date_entered, updated";
					break;
				case "feed_dates":
					$override_order = "updated DESC";
					break;
			}

			/* do not rely on params below */

			$search = $this->dbh->escape_string($_REQUEST["search"]);

			list($headlines, $headlines_header) = $this->api_get_headlines($feed_id, $limit, $offset,
				$filter, $is_cat, $show_excerpt, $show_content, $view_mode, $override_order,
				$include_attachments, $since_id, $search,
				$include_nested, $sanitize_content, $force_update, $excerpt_length, $check_first_id);

			if ($include_header) {
				$this->wrap(self::STATUS_OK, array($headlines_header, $headlines));
			} else {
				$this->wrap(self::STATUS_OK, $headlines);
			}
		} else {
			$this->wrap(self::STATUS_ERR, array("error" => 'INCORRECT_USAGE'));
		}
	}

	function updateArticle() {
		$article_ids = array_filter(explode(",", $this->dbh->escape_string($_REQUEST["article_ids"])), is_numeric);
		$mode = (int) $this->dbh->escape_string($_REQUEST["mode"]);
		$data = $this->dbh->escape_string($_REQUEST["data"]);
		$field_raw = (int)$this->dbh->escape_string($_REQUEST["field"]);

		$field = "";
		$set_to = "";

		switch ($field_raw) {
			case 0:
				$field = "marked";
				$additional_fields = ",last_marked = NOW()";
				break;
			case 1:
				$field = "published";
				$additional_fields = ",last_published = NOW()";
				break;
			case 2:
				$field = "unread";
				$additional_fields = ",last_read = NOW()";
				break;
			case 3:
				$field = "note";
		};

		switch ($mode) {
			case 1:
				$set_to = "true";
				break;
			case 0:
				$set_to = "false";
				break;
			case 2:
				$set_to = "NOT $field";
				break;
		}

		if ($field == "note") $set_to = "'$data'";

		if ($field && $set_to && count($article_ids) > 0) {

			$article_ids = join(", ", $article_ids);

			$result = $this->dbh->query("UPDATE ttrss_user_entries SET $field = $set_to $additional_fields WHERE ref_id IN ($article_ids) AND owner_uid = " . $_SESSION["uid"]);

			$num_updated = $this->dbh->affected_rows($result);

			if ($num_updated > 0 && $field == "unread") {
				$result = $this->dbh->query("SELECT DISTINCT feed_id FROM ttrss_user_entries
					WHERE ref_id IN ($article_ids)");

				while ($line = $this->dbh->fetch_assoc($result)) {
					ccache_update($line["feed_id"], $_SESSION["uid"]);
				}
			}

			if ($num_updated > 0 && $field == "published") {
				if (PUBSUBHUBBUB_HUB) {
					$rss_link = get_self_url_prefix() .
						"/public.php?op=rss&id=-2&key=" .
						get_feed_access_key(-2, false);

					$p = new Publisher(PUBSUBHUBBUB_HUB);
					$pubsub_result = $p->publish_update($rss_link);
				}
			}

			$this->wrap(self::STATUS_OK, array("status" => "OK",
				"updated" => $num_updated));

		} else {
			$this->wrap(self::STATUS_ERR, array("error" => 'INCORRECT_USAGE'));
		}

	}

	function getArticle() {

		$article_id = join(",", array_filter(explode(",", $this->dbh->escape_string($_REQUEST["article_id"])), is_numeric));

		if ($article_id) {

			$query = "SELECT id,title,link,content,feed_id,comments,int_id,
				marked,unread,published,score,note,lang,
				".SUBSTRING_FOR_DATE."(updated,1,16) as updated,
				author,(SELECT title FROM ttrss_feeds WHERE id = feed_id) AS feed_title
				FROM ttrss_entries,ttrss_user_entries
				WHERE	id IN ($article_id) AND ref_id = id AND owner_uid = " .
					$_SESSION["uid"] ;

			$result = $this->dbh->query($query);

			$articles = array();

			if ($this->dbh->num_rows($result) != 0) {

				while ($line = $this->dbh->fetch_assoc($result)) {

					$attachments = get_article_enclosures($line['id']);

					$article = array(
						"id" => $line["id"],
						"title" => $line["title"],
						"link" => $line["link"],
						"labels" => get_article_labels($line['id']),
						"unread" => sql_bool_to_bool($line["unread"]),
						"marked" => sql_bool_to_bool($line["marked"]),
						"published" => sql_bool_to_bool($line["published"]),
						"comments" => $line["comments"],
						"author" => $line["author"],
						"updated" => (int) strtotime($line["updated"]),
						"content" => $line["content"],
						"feed_id" => $line["feed_id"],
						"attachments" => $attachments,
						"score" => (int)$line["score"],
						"feed_title" => $line["feed_title"],
						"note" => $line["note"],
						"lang" => $line["lang"]
					);

					foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_RENDER_ARTICLE_API) as $p) {
						$article = $p->hook_render_article_api(array("article" => $article));
					}


					array_push($articles, $article);

				}
			}

			$this->wrap(self::STATUS_OK, $articles);
		} else {
			$this->wrap(self::STATUS_ERR, array("error" => 'INCORRECT_USAGE'));
		}
	}

	function getConfig() {
		$config = array(
			"icons_dir" => ICONS_DIR,
			"icons_url" => ICONS_URL);

		$config["daemon_is_running"] = file_is_locked("update_daemon.lock");

		$result = $this->dbh->query("SELECT COUNT(*) AS cf FROM
			ttrss_feeds WHERE owner_uid = " . $_SESSION["uid"]);

		$num_feeds = $this->dbh->fetch_result($result, 0, "cf");

		$config["num_feeds"] = (int)$num_feeds;

		$this->wrap(self::STATUS_OK, $config);
	}

	function updateFeed() {
		require_once "include/rssfuncs.php";

		$feed_id = (int) $this->dbh->escape_string($_REQUEST["feed_id"]);

		update_rss_feed($feed_id, true);

		$this->wrap(self::STATUS_OK, array("status" => "OK"));
	}

	function catchupFeed() {
		$feed_id = $this->dbh->escape_string($_REQUEST["feed_id"]);
		$is_cat = $this->dbh->escape_string($_REQUEST["is_cat"]);

		catchup_feed($feed_id, $is_cat);

		$this->wrap(self::STATUS_OK, array("status" => "OK"));
	}

	function getPref() {
		$pref_name = $this->dbh->escape_string($_REQUEST["pref_name"]);

		$this->wrap(self::STATUS_OK, array("value" => get_pref($pref_name)));
	}

	function getLabels() {
		//$article_ids = array_filter(explode(",", $this->dbh->escape_string($_REQUEST["article_ids"])), is_numeric);

		$article_id = (int)$_REQUEST['article_id'];

		$rv = array();

		$result = $this->dbh->query("SELECT id, caption, fg_color, bg_color
			FROM ttrss_labels2
			WHERE owner_uid = '".$_SESSION['uid']."' ORDER BY caption");

		if ($article_id)
			$article_labels = get_article_labels($article_id);
		else
			$article_labels = array();

		while ($line = $this->dbh->fetch_assoc($result)) {

			$checked = false;
			foreach ($article_labels as $al) {
				if (feed_to_label_id($al[0]) == $line['id']) {
					$checked = true;
					break;
				}
			}

			array_push($rv, array(
				"id" => (int)label_to_feed_id($line['id']),
				"caption" => $line['caption'],
				"fg_color" => $line['fg_color'],
				"bg_color" => $line['bg_color'],
				"checked" => $checked));
		}

		$this->wrap(self::STATUS_OK, $rv);
	}

	function setArticleLabel() {

		$article_ids = array_filter(explode(",", $this->dbh->escape_string($_REQUEST["article_ids"])), is_numeric);
		$label_id = (int) $this->dbh->escape_string($_REQUEST['label_id']);
		$assign = (bool) $this->dbh->escape_string($_REQUEST['assign']) == "true";

		$label = $this->dbh->escape_string(label_find_caption(
			feed_to_label_id($label_id), $_SESSION["uid"]));

		$num_updated = 0;

		if ($label) {

			foreach ($article_ids as $id) {

				if ($assign)
					label_add_article($id, $label, $_SESSION["uid"]);
				else
					label_remove_article($id, $label, $_SESSION["uid"]);

				++$num_updated;

			}
		}

		$this->wrap(self::STATUS_OK, array("status" => "OK",
			"updated" => $num_updated));

	}

	function index($method) {
		$plugin = PluginHost::getInstance()->get_api_method(strtolower($method));

		if ($plugin && method_exists($plugin, $method)) {
			$reply = $plugin->$method();

			$this->wrap($reply[0], $reply[1]);

		} else {
			$this->wrap(self::STATUS_ERR, array("error" => 'UNKNOWN_METHOD', "method" => $method));
		}
	}

	function shareToPublished() {
		$title = $this->dbh->escape_string(strip_tags($_REQUEST["title"]));
		$url = $this->dbh->escape_string(strip_tags($_REQUEST["url"]));
		$content = $this->dbh->escape_string(strip_tags($_REQUEST["content"]));

		if (Article::create_published_article($title, $url, $content, "", $_SESSION["uid"])) {
			$this->wrap(self::STATUS_OK, array("status" => 'OK'));
		} else {
			$this->wrap(self::STATUS_ERR, array("error" => 'Publishing failed'));
		}
	}

	static function api_get_feeds($cat_id, $unread_only, $limit, $offset, $include_nested = false) {

			$feeds = array();

			/* Labels */

			if ($cat_id == -4 || $cat_id == -2) {
				$counters = getLabelCounters(true);

				foreach (array_values($counters) as $cv) {

					$unread = $cv["counter"];

					if ($unread || !$unread_only) {

						$row = array(
								"id" => (int) $cv["id"],
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
				foreach (array(-1, -2, -3, -4, -6, 0) as $i) {
					$unread = getFeedUnread($i);

					if ($unread || !$unread_only) {
						$title = getFeedTitle($i);

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

			/* Child cats */

			if ($include_nested && $cat_id) {
				$result = db_query("SELECT
					id, title FROM ttrss_feed_categories
					WHERE parent_cat = '$cat_id' AND owner_uid = " . $_SESSION["uid"] .
				" ORDER BY id, title");

				while ($line = db_fetch_assoc($result)) {
					$unread = getFeedUnread($line["id"], true) +
						getCategoryChildrenUnread($line["id"]);

					if ($unread || !$unread_only) {
						$row = array(
								"id" => (int) $line["id"],
								"title" => $line["title"],
								"unread" => $unread,
								"is_cat" => true,
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
				$result = db_query("SELECT
					id, feed_url, cat_id, title, order_id, ".
						SUBSTRING_FOR_DATE."(last_updated,1,19) AS last_updated
						FROM ttrss_feeds WHERE owner_uid = " . $_SESSION["uid"] .
						" ORDER BY cat_id, title " . $limit_qpart);
			} else {

				if ($cat_id)
					$cat_qpart = "cat_id = '$cat_id'";
				else
					$cat_qpart = "cat_id IS NULL";

				$result = db_query("SELECT
					id, feed_url, cat_id, title, order_id, ".
						SUBSTRING_FOR_DATE."(last_updated,1,19) AS last_updated
						FROM ttrss_feeds WHERE
						$cat_qpart AND owner_uid = " . $_SESSION["uid"] .
						" ORDER BY cat_id, title " . $limit_qpart);
			}

			while ($line = db_fetch_assoc($result)) {

				$unread = getFeedUnread($line["id"]);

				$has_icon = feed_has_icon($line['id']);

				if ($unread || !$unread_only) {

					$row = array(
							"feed_url" => $line["feed_url"],
							"title" => $line["title"],
							"id" => (int)$line["id"],
							"unread" => (int)$unread,
							"has_icon" => $has_icon,
							"cat_id" => (int)$line["cat_id"],
							"last_updated" => (int) strtotime($line["last_updated"]),
							"order_id" => (int) $line["order_id"],
						);

					array_push($feeds, $row);
				}
			}

		return $feeds;
	}

	static function api_get_headlines($feed_id, $limit, $offset,
				$filter, $is_cat, $show_excerpt, $show_content, $view_mode, $order,
				$include_attachments, $since_id,
				$search = "", $include_nested = false, $sanitize_content = true,
				$force_update = false, $excerpt_length = 100, $check_first_id = false) {

			if ($force_update && $feed_id > 0 && is_numeric($feed_id)) {
				// Update the feed if required with some basic flood control

				$result = db_query(
					"SELECT cache_images,".SUBSTRING_FOR_DATE."(last_updated,1,19) AS last_updated
						FROM ttrss_feeds WHERE id = '$feed_id'");

				if (db_num_rows($result) != 0) {
					$last_updated = strtotime(db_fetch_result($result, 0, "last_updated"));
					$cache_images = sql_bool_to_bool(db_fetch_result($result, 0, "cache_images"));

					if (!$cache_images && time() - $last_updated > 120) {
						include "rssfuncs.php";
						update_rss_feed($feed_id, true, true);
					} else {
						db_query("UPDATE ttrss_feeds SET last_updated = '1970-01-01', last_update_started = '1970-01-01'
							WHERE id = '$feed_id'");
					}
				}
			}

			/*$qfh_ret = queryFeedHeadlines($feed_id, $limit,
				$view_mode, $is_cat, $search, false,
				$order, $offset, 0, false, $since_id, $include_nested);*/

			//function queryFeedHeadlines($feed, $limit,
			// $view_mode, $cat_view, $search, $search_mode,
			// $override_order = false, $offset = 0, $owner_uid = 0, $filter = false, $since_id = 0, $include_children = false,
			// $ignore_vfeed_group = false, $override_strategy = false, $override_vfeed = false, $start_ts = false, $check_top_id = false) {

			$params = array(
				"feed" => $feed_id,
				"limit" => $limit,
				"view_mode" => $view_mode,
				"cat_view" => $is_cat,
				"search" => $search,
				"override_order" => $order,
				"offset" => $offset,
				"since_id" => $since_id,
				"include_children" => $include_nested,
				"check_first_id" => $check_first_id
			);

			$qfh_ret = queryFeedHeadlines($params);

			$result = $qfh_ret[0];
			$feed_title = $qfh_ret[1];
			$first_id = $qfh_ret[6];

			$headlines = array();

			$headlines_header = array(
				'id' => $feed_id,
				'first_id' => $first_id,
				'is_cat' => $is_cat);

			if (!is_numeric($result)) {
				while ($line = db_fetch_assoc($result)) {
					$line["content_preview"] = truncate_string(strip_tags($line["content"]), $excerpt_length);
					foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_QUERY_HEADLINES) as $p) {
						$line = $p->hook_query_headlines($line, $excerpt_length, true);
					}

					$is_updated = ($line["last_read"] == "" &&
						($line["unread"] != "t" && $line["unread"] != "1"));

					$tags = explode(",", $line["tag_cache"]);

					$label_cache = $line["label_cache"];
					$labels = false;

					if ($label_cache) {
						$label_cache = json_decode($label_cache, true);

						if ($label_cache) {
							if ($label_cache["no-labels"] == 1)
								$labels = array();
							else
								$labels = $label_cache;
						}
					}

					if (!is_array($labels)) $labels = get_article_labels($line["id"]);

					//if (!$tags) $tags = get_article_tags($line["id"]);
					//if (!$labels) $labels = get_article_labels($line["id"]);

					$headline_row = array(
						"id" => (int)$line["id"],
						"unread" => sql_bool_to_bool($line["unread"]),
						"marked" => sql_bool_to_bool($line["marked"]),
						"published" => sql_bool_to_bool($line["published"]),
						"updated" => (int)strtotime($line["updated"]),
						"is_updated" => $is_updated,
						"title" => $line["title"],
						"link" => $line["link"],
						"feed_id" => $line["feed_id"],
						"tags" => $tags,
					);

					if ($include_attachments)
						$headline_row['attachments'] = get_article_enclosures(
							$line['id']);

					if ($show_excerpt)
						$headline_row["excerpt"] = $line["content_preview"];

					if ($show_content) {

						if ($sanitize_content) {
							$headline_row["content"] = sanitize(
								$line["content"],
								sql_bool_to_bool($line['hide_images']),
								false, $line["site_url"], false, $line["id"]);
						} else {
							$headline_row["content"] = $line["content"];
						}
					}

					// unify label output to ease parsing
					if ($labels["no-labels"] == 1) $labels = array();

					$headline_row["labels"] = $labels;

					$headline_row["feed_title"] = $line["feed_title"] ? $line["feed_title"] :
						$feed_title;

					$headline_row["comments_count"] = (int)$line["num_comments"];
					$headline_row["comments_link"] = $line["comments"];

					$headline_row["always_display_attachments"] = sql_bool_to_bool($line["always_display_enclosures"]);

					$headline_row["author"] = $line["author"];

					$headline_row["score"] = (int)$line["score"];
					$headline_row["note"] = $line["note"];
					$headline_row["lang"] = $line["lang"];

					foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_RENDER_ARTICLE_API) as $p) {
						$headline_row = $p->hook_render_article_api(array("headline" => $headline_row));
					}

					array_push($headlines, $headline_row);
				}
			} else if (is_numeric($result) && $result == -1) {
				$headlines_header['first_id_changed'] = true;
			}

			return array($headlines, $headlines_header);
	}

	function unsubscribeFeed() {
		$feed_id = (int) $this->dbh->escape_string($_REQUEST["feed_id"]);

		$result = $this->dbh->query("SELECT id FROM ttrss_feeds WHERE
			id = '$feed_id' AND owner_uid = ".$_SESSION["uid"]);

		if ($this->dbh->num_rows($result) != 0) {
			Pref_Feeds::remove_feed($feed_id, $_SESSION["uid"]);
			$this->wrap(self::STATUS_OK, array("status" => "OK"));
		} else {
			$this->wrap(self::STATUS_ERR, array("error" => "FEED_NOT_FOUND"));
		}
	}

	function subscribeToFeed() {
		$feed_url = $this->dbh->escape_string($_REQUEST["feed_url"]);
		$category_id = (int) $this->dbh->escape_string($_REQUEST["category_id"]);
		$login = $this->dbh->escape_string($_REQUEST["login"]);
		$password = $this->dbh->escape_string($_REQUEST["password"]);

		if ($feed_url) {
			$rc = subscribe_to_feed($feed_url, $category_id, $login, $password);

			$this->wrap(self::STATUS_OK, array("status" => $rc));
		} else {
			$this->wrap(self::STATUS_ERR, array("error" => 'INCORRECT_USAGE'));
		}
	}

	function getFeedTree() {
		$include_empty = sql_bool_to_bool($_REQUEST['include_empty']);

		$pf = new Pref_Feeds($_REQUEST);

		$_REQUEST['mode'] = 2;
		$_REQUEST['force_show_empty'] = $include_empty;

		if ($pf){
			$data = $pf->makefeedtree();
			$this->wrap(self::STATUS_OK, array("categories" => $data));
		} else {
			$this->wrap(self::STATUS_ERR, array("error" =>
				'UNABLE_TO_INSTANTIATE_OBJECT'));
		}

	}

	// only works for labels or uncategorized for the time being
	private function isCategoryEmpty($id) {

		if ($id == -2) {
			$result = $this->dbh->query("SELECT COUNT(*) AS count FROM ttrss_labels2
				WHERE owner_uid = " . $_SESSION["uid"]);

			return $this->dbh->fetch_result($result, 0, "count") == 0;

		} else if ($id == 0) {
			$result = $this->dbh->query("SELECT COUNT(*) AS count FROM ttrss_feeds
				WHERE cat_id IS NULL AND owner_uid = " . $_SESSION["uid"]);

			return $this->dbh->fetch_result($result, 0, "count") == 0;

		}

		return false;
	}


}

?>

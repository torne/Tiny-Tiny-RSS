<?php

class API extends Handler {

	const API_LEVEL  = 4;

	const STATUS_OK  = 0;
	const STATUS_ERR = 1;

	private $seq;

	function before($method) {
		if (parent::before($method)) {
			header("Content-Type: text/json");

			if (!$_SESSION["uid"] && $method != "login" && $method != "isloggedin") {
				print $this->wrap(self::STATUS_ERR, array("error" => 'NOT_LOGGED_IN'));
				return false;
			}

			if ($_SESSION["uid"] && $method != "logout" && !get_pref($this->link, 'ENABLE_API_ACCESS')) {
				print $this->wrap(self::STATUS_ERR, array("error" => 'API_DISABLED'));
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
		print $this->wrap(self::STATUS_OK, $rv);
	}

	function getApiLevel() {
		$rv = array("level" => self::API_LEVEL);
		print $this->wrap(self::STATUS_OK, $rv);
	}

	function login() {
		$login = db_escape_string($_REQUEST["user"]);
		$password = $_REQUEST["password"];
		$password_base64 = base64_decode($_REQUEST["password"]);

		if (SINGLE_USER_MODE) $login = "admin";

		$result = db_query($this->link, "SELECT id FROM ttrss_users WHERE login = '$login'");

		if (db_num_rows($result) != 0) {
			$uid = db_fetch_result($result, 0, "id");
		} else {
			$uid = 0;
		}

		if (!$uid) {
			print $this->wrap(self::STATUS_ERR, array("error" => "LOGIN_ERROR"));
			return;
		}

		if (get_pref($this->link, "ENABLE_API_ACCESS", $uid)) {
			if (authenticate_user($this->link, $login, $password)) {               // try login with normal password
				print $this->wrap(self::STATUS_OK, array("session_id" => session_id(),
					"api_level" => self::API_LEVEL));
			} else if (authenticate_user($this->link, $login, $password_base64)) { // else try with base64_decoded password
				print $this->wrap(self::STATUS_OK,	array("session_id" => session_id(),
					"api_level" => self::API_LEVEL));
			} else {                                                         // else we are not logged in
				print $this->wrap(self::STATUS_ERR, array("error" => "LOGIN_ERROR"));
			}
		} else {
			print $this->wrap(self::STATUS_ERR, array("error" => "API_DISABLED"));
		}

	}

	function logout() {
		logout_user();
		print $this->wrap(self::STATUS_OK, array("status" => "OK"));
	}

	function isLoggedIn() {
		print $this->wrap(self::STATUS_OK, array("status" => $_SESSION["uid"] != ''));
	}

	function getUnread() {
		$feed_id = db_escape_string($_REQUEST["feed_id"]);
		$is_cat = db_escape_string($_REQUEST["is_cat"]);

		if ($feed_id) {
			print $this->wrap(self::STATUS_OK, array("unread" => getFeedUnread($this->link, $feed_id, $is_cat)));
		} else {
			print $this->wrap(self::STATUS_OK, array("unread" => getGlobalUnread($this->link)));
		}
	}

	/* Method added for ttrss-reader for Android */
	function getCounters() {
		print $this->wrap(self::STATUS_OK, getAllCounters($this->link));
	}

	function getFeeds() {
		$cat_id = db_escape_string($_REQUEST["cat_id"]);
		$unread_only = sql_bool_to_bool($_REQUEST["unread_only"]);
		$limit = (int) db_escape_string($_REQUEST["limit"]);
		$offset = (int) db_escape_string($_REQUEST["offset"]);
		$include_nested = sql_bool_to_bool($_REQUEST["include_nested"]);

		$feeds = $this->api_get_feeds($this->link, $cat_id, $unread_only, $limit, $offset, $include_nested);

		print $this->wrap(self::STATUS_OK, $feeds);
	}

	function getCategories() {
		$unread_only = sql_bool_to_bool($_REQUEST["unread_only"]);
		$enable_nested = sql_bool_to_bool($_REQUEST["enable_nested"]);

		// TODO do not return empty categories, return Uncategorized and standard virtual cats

		if ($enable_nested)
			$nested_qpart = "parent_cat IS NULL";
		else
			$nested_qpart = "true";

		$result = db_query($this->link, "SELECT
				id, title, order_id, (SELECT COUNT(id) FROM
				ttrss_feeds WHERE
				ttrss_feed_categories.id IS NOT NULL AND cat_id = ttrss_feed_categories.id) AS num_feeds
			FROM ttrss_feed_categories
			WHERE $nested_qpart AND owner_uid = " .
			$_SESSION["uid"]);

		$cats = array();

		while ($line = db_fetch_assoc($result)) {
			if ($line["num_feeds"] > 0) {
				$unread = getFeedUnread($this->link, $line["id"], true);

				if ($enable_nested)
					$unread += getCategoryChildrenUnread($this->link, $line["id"]);

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
			$unread = getFeedUnread($this->link, $cat_id, true);

			if ($unread || !$unread_only) {
				array_push($cats, array("id" => $cat_id,
					"title" => getCategoryTitle($this->link, $cat_id),
					"unread" => $unread));
			}
		}

		print $this->wrap(self::STATUS_OK, $cats);
	}

	function getHeadlines() {
		$feed_id = db_escape_string($_REQUEST["feed_id"]);
		if ($feed_id != "") {

			$limit = (int)db_escape_string($_REQUEST["limit"]);

			if (!$limit || $limit >= 60) $limit = 60;

			$offset = (int)db_escape_string($_REQUEST["skip"]);
			$filter = db_escape_string($_REQUEST["filter"]);
			$is_cat = sql_bool_to_bool($_REQUEST["is_cat"]);
			$show_excerpt = sql_bool_to_bool($_REQUEST["show_excerpt"]);
			$show_content = sql_bool_to_bool($_REQUEST["show_content"]);
			/* all_articles, unread, adaptive, marked, updated */
			$view_mode = db_escape_string($_REQUEST["view_mode"]);
			$include_attachments = sql_bool_to_bool($_REQUEST["include_attachments"]);
			$since_id = (int)db_escape_string($_REQUEST["since_id"]);
			$include_nested = sql_bool_to_bool($_REQUEST["include_nested"]);
			$sanitize_content = true;

			/* do not rely on params below */

			$search = db_escape_string($_REQUEST["search"]);
			$search_mode = db_escape_string($_REQUEST["search_mode"]);

			$headlines = $this->api_get_headlines($this->link, $feed_id, $limit, $offset,
				$filter, $is_cat, $show_excerpt, $show_content, $view_mode, false,
				$include_attachments, $since_id, $search, $search_mode,
				$include_nested, $sanitize_content);

			print $this->wrap(self::STATUS_OK, $headlines);
		} else {
			print $this->wrap(self::STATUS_ERR, array("error" => 'INCORRECT_USAGE'));
		}
	}

	function updateArticle() {
		$article_ids = array_filter(explode(",", db_escape_string($_REQUEST["article_ids"])), is_numeric);
		$mode = (int) db_escape_string($_REQUEST["mode"]);
		$data = db_escape_string($_REQUEST["data"]);
		$field_raw = (int)db_escape_string($_REQUEST["field"]);

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

			$result = db_query($this->link, "UPDATE ttrss_user_entries SET $field = $set_to $additional_fields WHERE ref_id IN ($article_ids) AND owner_uid = " . $_SESSION["uid"]);

			$num_updated = db_affected_rows($this->link, $result);

			if ($num_updated > 0 && $field == "unread") {
				$result = db_query($this->link, "SELECT DISTINCT feed_id FROM ttrss_user_entries
					WHERE ref_id IN ($article_ids)");

				while ($line = db_fetch_assoc($result)) {
					ccache_update($this->link, $line["feed_id"], $_SESSION["uid"]);
				}
			}

			if ($num_updated > 0 && $field == "published") {
				if (PUBSUBHUBBUB_HUB) {
					$rss_link = get_self_url_prefix() .
						"/public.php?op=rss&id=-2&key=" .
						get_feed_access_key($this->link, -2, false);

					$p = new Publisher(PUBSUBHUBBUB_HUB);
					$pubsub_result = $p->publish_update($rss_link);
				}
			}

			print $this->wrap(self::STATUS_OK, array("status" => "OK",
				"updated" => $num_updated));

		} else {
			print $this->wrap(self::STATUS_ERR, array("error" => 'INCORRECT_USAGE'));
		}

	}

	function getArticle() {

		$article_id = join(",", array_filter(explode(",", db_escape_string($_REQUEST["article_id"])), is_numeric));

		$query = "SELECT id,title,link,content,cached_content,feed_id,comments,int_id,
			marked,unread,published,
			".SUBSTRING_FOR_DATE."(updated,1,16) as updated,
			author
			FROM ttrss_entries,ttrss_user_entries
			WHERE	id IN ($article_id) AND ref_id = id AND owner_uid = " .
				$_SESSION["uid"] ;

		$result = db_query($this->link, $query);

		$articles = array();

		if (db_num_rows($result) != 0) {

			while ($line = db_fetch_assoc($result)) {

				$attachments = get_article_enclosures($this->link, $line['id']);

				$article = array(
					"id" => $line["id"],
					"title" => $line["title"],
					"link" => $line["link"],
					"labels" => get_article_labels($this->link, $line['id']),
					"unread" => sql_bool_to_bool($line["unread"]),
					"marked" => sql_bool_to_bool($line["marked"]),
					"published" => sql_bool_to_bool($line["published"]),
					"comments" => $line["comments"],
					"author" => $line["author"],
					"updated" => (int) strtotime($line["updated"]),
					"content" => $line["cached_content"] != "" ? $line["cached_content"] : $line["content"],
					"feed_id" => $line["feed_id"],
					"attachments" => $attachments
				);

				array_push($articles, $article);

			}
		}

		print $this->wrap(self::STATUS_OK, $articles);

	}

	function getConfig() {
		$config = array(
			"icons_dir" => ICONS_DIR,
			"icons_url" => ICONS_URL);

		$config["daemon_is_running"] = file_is_locked("update_daemon.lock");

		$result = db_query($this->link, "SELECT COUNT(*) AS cf FROM
			ttrss_feeds WHERE owner_uid = " . $_SESSION["uid"]);

		$num_feeds = db_fetch_result($result, 0, "cf");

		$config["num_feeds"] = (int)$num_feeds;

		print $this->wrap(self::STATUS_OK, $config);
	}

	function updateFeed() {
		$feed_id = db_escape_string($_REQUEST["feed_id"]);

		update_rss_feed($this->link, $feed_id, true);

		print $this->wrap(self::STATUS_OK, array("status" => "OK"));
	}

	function catchupFeed() {
		$feed_id = db_escape_string($_REQUEST["feed_id"]);
		$is_cat = db_escape_string($_REQUEST["is_cat"]);

		catchup_feed($this->link, $feed_id, $is_cat);

		print $this->wrap(self::STATUS_OK, array("status" => "OK"));
	}

	function getPref() {
		$pref_name = db_escape_string($_REQUEST["pref_name"]);

		print $this->wrap(self::STATUS_OK, array("value" => get_pref($this->link, $pref_name)));
	}

	function getLabels() {
		//$article_ids = array_filter(explode(",", db_escape_string($_REQUEST["article_ids"])), is_numeric);

		$article_id = (int)$_REQUEST['article_id'];

		$rv = array();

		$result = db_query($this->link, "SELECT id, caption, fg_color, bg_color
			FROM ttrss_labels2
			WHERE owner_uid = '".$_SESSION['uid']."' ORDER BY caption");

		if ($article_id)
			$article_labels = get_article_labels($this->link, $article_id);
		else
			$article_labels = array();

		while ($line = db_fetch_assoc($result)) {

			$checked = false;
			foreach ($article_labels as $al) {
				if ($al[0] == $line['id']) {
					$checked = true;
					break;
				}
			}

			array_push($rv, array(
				"id" => (int)$line['id'],
				"caption" => $line['caption'],
				"fg_color" => $line['fg_color'],
				"bg_color" => $line['bg_color'],
				"checked" => $checked));
		}

		print $this->wrap(self::STATUS_OK, $rv);
	}

	function setArticleLabel() {

		$article_ids = array_filter(explode(",", db_escape_string($_REQUEST["article_ids"])), is_numeric);
		$label_id = (int) db_escape_string($_REQUEST['label_id']);
		$assign = (bool) db_escape_string($_REQUEST['assign']) == "true";

		$label = db_escape_string(label_find_caption($this->link,
			$label_id, $_SESSION["uid"]));

		$num_updated = 0;

		if ($label) {

			foreach ($article_ids as $id) {

				if ($assign)
					label_add_article($this->link, $id, $label, $_SESSION["uid"]);
				else
					label_remove_article($this->link, $id, $label, $_SESSION["uid"]);

				++$num_updated;

			}
		}

		print $this->wrap(self::STATUS_OK, array("status" => "OK",
			"updated" => $num_updated));

	}

	function index() {
		print $this->wrap(self::STATUS_ERR, array("error" => 'UNKNOWN_METHOD'));
	}

	function shareToPublished() {
		$title = db_escape_string(strip_tags($_REQUEST["title"]));
		$url = db_escape_string(strip_tags($_REQUEST["url"]));
		$content = db_escape_string(strip_tags($_REQUEST["content"]));

		if (Article::create_published_article($this->link, $title, $url, $content, "", $_SESSION["uid"])) {
			print $this->wrap(self::STATUS_OK, array("status" => 'OK'));
		} else {
			print $this->wrap(self::STATUS_ERR, array("error" => 'Publishing failed'));
		}
	}

	static function api_get_feeds($link, $cat_id, $unread_only, $limit, $offset, $include_nested = false) {

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
				foreach (array(-1, -2, -3, -4, -6, 0) as $i) {
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

			/* Child cats */

			if ($include_nested && $cat_id) {
				$result = db_query($link, "SELECT
					id, title FROM ttrss_feed_categories
					WHERE parent_cat = '$cat_id' AND owner_uid = " . $_SESSION["uid"] .
				" ORDER BY id, title");

				while ($line = db_fetch_assoc($result)) {
					$unread = getFeedUnread($link, $line["id"], true) +
						getCategoryChildrenUnread($link, $line["id"]);

					if ($unread || !$unread_only) {
						$row = array(
								"id" => $line["id"],
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
				$result = db_query($link, "SELECT
					id, feed_url, cat_id, title, order_id, ".
						SUBSTRING_FOR_DATE."(last_updated,1,19) AS last_updated
						FROM ttrss_feeds WHERE owner_uid = " . $_SESSION["uid"] .
						" ORDER BY cat_id, title " . $limit_qpart);
			} else {

				if ($cat_id)
					$cat_qpart = "cat_id = '$cat_id'";
				else
					$cat_qpart = "cat_id IS NULL";

				$result = db_query($link, "SELECT
					id, feed_url, cat_id, title, order_id, ".
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
							"last_updated" => (int) strtotime($line["last_updated"]),
							"order_id" => (int) $line["order_id"],
						);

					array_push($feeds, $row);
				}
			}

		return $feeds;
	}

	static function api_get_headlines($link, $feed_id, $limit, $offset,
				$filter, $is_cat, $show_excerpt, $show_content, $view_mode, $order,
				$include_attachments, $since_id,
				$search = "", $search_mode = "",
				$include_nested = false, $sanitize_content = true) {

			$qfh_ret = queryFeedHeadlines($link, $feed_id, $limit,
				$view_mode, $is_cat, $search, $search_mode,
				$order, $offset, 0, false, $since_id, $include_nested);

			$result = $qfh_ret[0];
			$feed_title = $qfh_ret[1];

			$headlines = array();

			while ($line = db_fetch_assoc($result)) {
				$is_updated = ($line["last_read"] == "" &&
					($line["unread"] != "t" && $line["unread"] != "1"));

				$tags = explode(",", $line["tag_cache"]);
				$labels = json_decode($line["label_cache"], true);

				//if (!$tags) $tags = get_article_tags($link, $line["id"]);
				//if (!$labels) $labels = get_article_labels($link, $line["id"]);

				$headline_row = array(
						"id" => (int)$line["id"],
						"unread" => sql_bool_to_bool($line["unread"]),
						"marked" => sql_bool_to_bool($line["marked"]),
						"published" => sql_bool_to_bool($line["published"]),
						"updated" => (int) strtotime($line["updated"]),
						"is_updated" => $is_updated,
						"title" => $line["title"],
						"link" => $line["link"],
						"feed_id" => $line["feed_id"],
						"tags" => $tags,
					);

					if ($include_attachments)
						$headline_row['attachments'] = get_article_enclosures($link,
							$line['id']);

				if ($show_excerpt) {
					$excerpt = truncate_string(strip_tags($line["content_preview"]), 100);
					$headline_row["excerpt"] = $excerpt;
				}

				if ($show_content) {

					if ($line["cached_content"] != "") {
						$line["content_preview"] =& $line["cached_content"];
					}

					if ($sanitize_content) {
						$headline_row["content"] = sanitize($link,
							$line["content_preview"],
							sql_bool_to_bool($line['hide_images']),
							false, $line["site_url"]);
					} else {
						$headline_row["content"] = $line["content_preview"];
					}
				}

				// unify label output to ease parsing
				if ($labels["no-labels"] == 1) $labels = array();

				$headline_row["labels"] = $labels;

				$headline_row["feed_title"] = $line["feed_title"];

				$headline_row["comments_count"] = (int)$line["num_comments"];
				$headline_row["comments_link"] = $line["comments"];

				$headline_row["always_display_attachments"] = sql_bool_to_bool($line["always_display_enclosures"]);

				global $pluginhost;
				foreach ($pluginhost->get_hooks($pluginhost::HOOK_RENDER_ARTICLE_API) as $p) {
					$headline_row = $p->hook_render_article_api($headline_row);
				}

				array_push($headlines, $headline_row);
			}

			return $headlines;
	}

}

?>

<?php
class RPC extends Handler_Protected {

	function csrf_ignore($method) {
		$csrf_ignored = array("sanitycheck", "completelabels");

		return array_search($method, $csrf_ignored) !== false;
	}

	function setprofile() {
		$id = db_escape_string($_REQUEST["id"]);

		$_SESSION["profile"] = $id;
		$_SESSION["prefs_cache"] = array();
	}

	function remprofiles() {
		$ids = explode(",", db_escape_string(trim($_REQUEST["ids"])));

		foreach ($ids as $id) {
			if ($_SESSION["profile"] != $id) {
				db_query($this->link, "DELETE FROM ttrss_settings_profiles WHERE id = '$id' AND
							owner_uid = " . $_SESSION["uid"]);
			}
		}
	}

	// Silent
	function addprofile() {
		$title = db_escape_string(trim($_REQUEST["title"]));
		if ($title) {
			db_query($this->link, "BEGIN");

			$result = db_query($this->link, "SELECT id FROM ttrss_settings_profiles
				WHERE title = '$title' AND owner_uid = " . $_SESSION["uid"]);

			if (db_num_rows($result) == 0) {

				db_query($this->link, "INSERT INTO ttrss_settings_profiles (title, owner_uid)
							VALUES ('$title', ".$_SESSION["uid"] .")");

				$result = db_query($this->link, "SELECT id FROM ttrss_settings_profiles WHERE
					title = '$title'");

				if (db_num_rows($result) != 0) {
					$profile_id = db_fetch_result($result, 0, "id");

					if ($profile_id) {
						initialize_user_prefs($this->link, $_SESSION["uid"], $profile_id);
					}
				}
			}

			db_query($this->link, "COMMIT");
		}
	}

	// Silent
	function saveprofile() {
		$id = db_escape_string($_REQUEST["id"]);
		$title = db_escape_string(trim($_REQUEST["value"]));

		if ($id == 0) {
			print __("Default profile");
			return;
		}

		if ($title) {
			db_query($this->link, "BEGIN");

			$result = db_query($this->link, "SELECT id FROM ttrss_settings_profiles
				WHERE title = '$title' AND owner_uid =" . $_SESSION["uid"]);

			if (db_num_rows($result) == 0) {
				db_query($this->link, "UPDATE ttrss_settings_profiles
							SET title = '$title' WHERE id = '$id' AND
							owner_uid = " . $_SESSION["uid"]);
				print $title;
			} else {
				$result = db_query($this->link, "SELECT title FROM ttrss_settings_profiles
							WHERE id = '$id' AND owner_uid =" . $_SESSION["uid"]);
				print db_fetch_result($result, 0, "title");
			}

			db_query($this->link, "COMMIT");
		}
	}

	// Silent
	function remarchive() {
		$ids = explode(",", db_escape_string($_REQUEST["ids"]));

		foreach ($ids as $id) {
			$result = db_query($this->link, "DELETE FROM ttrss_archived_feeds WHERE
		(SELECT COUNT(*) FROM ttrss_user_entries
							WHERE orig_feed_id = '$id') = 0 AND
		id = '$id' AND owner_uid = ".$_SESSION["uid"]);

			$rc = db_affected_rows($this->link, $result);
		}
	}

	function addfeed() {
		$feed = db_escape_string($_REQUEST['feed']);
		$cat = db_escape_string($_REQUEST['cat']);
		$login = db_escape_string($_REQUEST['login']);
		$pass = db_escape_string($_REQUEST['pass']);
		$need_auth = db_escape_string($_REQUEST['need_auth']) != "";

		$rc = subscribe_to_feed($this->link, $feed, $cat, $login, $pass, $need_auth);

		print json_encode(array("result" => $rc));
	}

	function togglepref() {
		$key = db_escape_string($_REQUEST["key"]);
		set_pref($this->link, $key, !get_pref($this->link, $key));
		$value = get_pref($this->link, $key);

		print json_encode(array("param" =>$key, "value" => $value));
	}

	function setpref() {
		// set_pref escapes input, so no need to double escape it here
		$key = $_REQUEST['key'];
		$value = str_replace("\n", "<br/>", $_REQUEST['value']);

		set_pref($this->link, $key, $value, $_SESSION['uid'], $key != 'USER_STYLESHEET');

		print json_encode(array("param" =>$key, "value" => $value));
	}

	function mark() {
		$mark = $_REQUEST["mark"];
		$id = db_escape_string($_REQUEST["id"]);

		if ($mark == "1") {
			$mark = "true";
		} else {
			$mark = "false";
		}

		$result = db_query($this->link, "UPDATE ttrss_user_entries SET marked = $mark
					WHERE ref_id = '$id' AND owner_uid = " . $_SESSION["uid"]);

		print json_encode(array("message" => "UPDATE_COUNTERS"));
	}

	function delete() {
		$ids = db_escape_string($_REQUEST["ids"]);

		$result = db_query($this->link, "DELETE FROM ttrss_user_entries
		WHERE ref_id IN ($ids) AND owner_uid = " . $_SESSION["uid"]);

		print json_encode(array("message" => "UPDATE_COUNTERS"));
	}

	function unarchive() {
		$ids = db_escape_string($_REQUEST["ids"]);

		$result = db_query($this->link, "UPDATE ttrss_user_entries
					SET feed_id = orig_feed_id, orig_feed_id = NULL
					WHERE ref_id IN ($ids) AND owner_uid = " . $_SESSION["uid"]);

		print json_encode(array("message" => "UPDATE_COUNTERS"));
	}

	function archive() {
		$ids = explode(",", db_escape_string($_REQUEST["ids"]));

		foreach ($ids as $id) {
			archive_article($this->link, $id, $_SESSION["uid"]);
		}

		print json_encode(array("message" => "UPDATE_COUNTERS"));
	}

	function publ() {
		$pub = $_REQUEST["pub"];
		$id = db_escape_string($_REQUEST["id"]);
		$note = trim(strip_tags(db_escape_string($_REQUEST["note"])));

		if ($pub == "1") {
			$pub = "true";
		} else {
			$pub = "false";
		}

		$result = db_query($this->link, "UPDATE ttrss_user_entries SET
			published = $pub, last_read = NOW()
			WHERE ref_id = '$id' AND owner_uid = " . $_SESSION["uid"]);

		$pubsub_result = false;

		if (PUBSUBHUBBUB_HUB) {
			$rss_link = get_self_url_prefix() .
				"/public.php?op=rss&id=-2&key=" .
				get_feed_access_key($this->link, -2, false);

			$p = new Publisher(PUBSUBHUBBUB_HUB);

			$pubsub_result = $p->publish_update($rss_link);
		}

		print json_encode(array("message" => "UPDATE_COUNTERS",
			"pubsub_result" => $pubsub_result));
	}

	function getAllCounters() {
		$last_article_id = (int) $_REQUEST["last_article_id"];

		$reply = array();

		 if ($seq) $reply['seq'] = $seq;

		 if ($last_article_id != getLastArticleId($this->link)) {
				$omode = $_REQUEST["omode"];

			if ($omode != "T")
				$reply['counters'] = getAllCounters($this->link, $omode);
			else
				$reply['counters'] = getGlobalCounters($this->link);
		 }

		 $reply['runtime-info'] = make_runtime_info($this->link);

		 print json_encode($reply);
	}

	/* GET["cmode"] = 0 - mark as read, 1 - as unread, 2 - toggle */
	function catchupSelected() {
		$ids = explode(",", db_escape_string($_REQUEST["ids"]));
		$cmode = sprintf("%d", $_REQUEST["cmode"]);

		catchupArticlesById($this->link, $ids, $cmode);

		print json_encode(array("message" => "UPDATE_COUNTERS"));
	}

	function markSelected() {
		$ids = explode(",", db_escape_string($_REQUEST["ids"]));
		$cmode = sprintf("%d", $_REQUEST["cmode"]);

		markArticlesById($this->link, $ids, $cmode);

		print json_encode(array("message" => "UPDATE_COUNTERS"));
	}

	function publishSelected() {
		$ids = explode(",", db_escape_string($_REQUEST["ids"]));
		$cmode = sprintf("%d", $_REQUEST["cmode"]);

		publishArticlesById($this->link, $ids, $cmode);

		print json_encode(array("message" => "UPDATE_COUNTERS"));
	}

	function sanityCheck() {
		$_SESSION["hasAudio"] = $_REQUEST["hasAudio"] === "true";

		$reply = array();

		$reply['error'] = sanity_check($this->link);

		if ($reply['error']['code'] == 0) {
			$reply['init-params'] = make_init_params($this->link);
			$reply['runtime-info'] = make_runtime_info($this->link);
		}

		print json_encode($reply);
	}

	function setArticleTags() {

		$id = db_escape_string($_REQUEST["id"]);

		$tags_str = db_escape_string($_REQUEST["tags_str"]);
		$tags = array_unique(trim_array(explode(",", $tags_str)));

		db_query($this->link, "BEGIN");

		$result = db_query($this->link, "SELECT int_id FROM ttrss_user_entries WHERE
				ref_id = '$id' AND owner_uid = '".$_SESSION["uid"]."' LIMIT 1");

		if (db_num_rows($result) == 1) {

			$tags_to_cache = array();

			$int_id = db_fetch_result($result, 0, "int_id");

			db_query($this->link, "DELETE FROM ttrss_tags WHERE
				post_int_id = $int_id AND owner_uid = '".$_SESSION["uid"]."'");

			foreach ($tags as $tag) {
				$tag = sanitize_tag($tag);

				if (!tag_is_valid($tag)) {
					continue;
				}

				if (preg_match("/^[0-9]*$/", $tag)) {
					continue;
				}

				//					print "<!-- $id : $int_id : $tag -->";

				if ($tag != '') {
					db_query($this->link, "INSERT INTO ttrss_tags
								(post_int_id, owner_uid, tag_name) VALUES ('$int_id', '".$_SESSION["uid"]."', '$tag')");
				}

				array_push($tags_to_cache, $tag);
			}

			/* update tag cache */

			sort($tags_to_cache);
			$tags_str = join(",", $tags_to_cache);

			db_query($this->link, "UPDATE ttrss_user_entries
				SET tag_cache = '$tags_str' WHERE ref_id = '$id'
						AND owner_uid = " . $_SESSION["uid"]);
		}

		db_query($this->link, "COMMIT");

		$tags = get_article_tags($this->link, $id);
		$tags_str = format_tags_string($tags, $id);
		$tags_str_full = join(", ", $tags);

		if (!$tags_str_full) $tags_str_full = __("no tags");

		print json_encode(array("tags_str" => array("id" => $id,
				"content" => $tags_str, "content_full" => $tags_str_full)));
	}

	function regenOPMLKey() {
		update_feed_access_key($this->link, 'OPML:Publish',
		false, $_SESSION["uid"]);

		$new_link = opml_publish_url($this->link);

		print json_encode(array("link" => $new_link));
	}

	function completeLabels() {
		$search = db_escape_string($_REQUEST["search"]);

		$result = db_query($this->link, "SELECT DISTINCT caption FROM
				ttrss_labels2
				WHERE owner_uid = '".$_SESSION["uid"]."' AND
				LOWER(caption) LIKE LOWER('$search%') ORDER BY caption
				LIMIT 5");

		print "<ul>";
		while ($line = db_fetch_assoc($result)) {
			print "<li>" . $line["caption"] . "</li>";
		}
		print "</ul>";
	}


	function completeTags() {
		$search = db_escape_string($_REQUEST["search"]);

		$result = db_query($this->link, "SELECT DISTINCT tag_name FROM ttrss_tags
				WHERE owner_uid = '".$_SESSION["uid"]."' AND
				tag_name LIKE '$search%' ORDER BY tag_name
				LIMIT 10");

		print "<ul>";
		while ($line = db_fetch_assoc($result)) {
			print "<li>" . $line["tag_name"] . "</li>";
		}
		print "</ul>";
	}

	function purge() {
		$ids = explode(",", db_escape_string($_REQUEST["ids"]));
		$days = sprintf("%d", $_REQUEST["days"]);

		foreach ($ids as $id) {

			$result = db_query($this->link, "SELECT id FROM ttrss_feeds WHERE
				id = '$id' AND owner_uid = ".$_SESSION["uid"]);

			if (db_num_rows($result) == 1) {
				purge_feed($this->link, $id, $days);
			}
		}
	}

	function getArticles() {
		$ids = explode(",", db_escape_string($_REQUEST["ids"]));
		$articles = array();

		foreach ($ids as $id) {
			if ($id) {
				array_push($articles, format_article($this->link, $id, 0, false));
			}
		}

		print json_encode($articles);
	}

	function checkDate() {
		$date = db_escape_string($_REQUEST["date"]);
		$date_parsed = strtotime($date);

		print json_encode(array("result" => (bool)$date_parsed,
			"date" => date("c", $date_parsed)));
	}

	function assigntolabel() {
		return $this->labelops(true);
	}

	function removefromlabel() {
		return $this->labelops(false);
	}

	function labelops($assign) {
		$reply = array();

		$ids = explode(",", db_escape_string($_REQUEST["ids"]));
		$label_id = db_escape_string($_REQUEST["lid"]);

		$label = db_escape_string(label_find_caption($this->link, $label_id,
		$_SESSION["uid"]));

		$reply["info-for-headlines"] = array();

		if ($label) {

			foreach ($ids as $id) {

				if ($assign)
					label_add_article($this->link, $id, $label, $_SESSION["uid"]);
				else
					label_remove_article($this->link, $id, $label, $_SESSION["uid"]);

				$labels = get_article_labels($this->link, $id, $_SESSION["uid"]);

				array_push($reply["info-for-headlines"],
				array("id" => $id, "labels" => format_article_labels($labels, $id)));

			}
		}

		$reply["message"] = "UPDATE_COUNTERS";

		print json_encode($reply);
	}

	function updateFeedBrowser() {
		$search = db_escape_string($_REQUEST["search"]);
		$limit = db_escape_string($_REQUEST["limit"]);
		$mode = (int) db_escape_string($_REQUEST["mode"]);

		require_once "feedbrowser.php";

		print json_encode(array("content" =>
			make_feed_browser($this->link, $search, $limit, $mode),
				"mode" => $mode));
	}

	// Silent
	function massSubscribe() {

		$payload = json_decode($_REQUEST["payload"], false);
		$mode = $_REQUEST["mode"];

		if (!$payload || !is_array($payload)) return;

		if ($mode == 1) {
			foreach ($payload as $feed) {

				$title = db_escape_string($feed[0]);
				$feed_url = db_escape_string($feed[1]);

				$result = db_query($this->link, "SELECT id FROM ttrss_feeds WHERE
					feed_url = '$feed_url' AND owner_uid = " . $_SESSION["uid"]);

				if (db_num_rows($result) == 0) {
					$result = db_query($this->link, "INSERT INTO ttrss_feeds
									(owner_uid,feed_url,title,cat_id,site_url)
									VALUES ('".$_SESSION["uid"]."',
									'$feed_url', '$title', NULL, '')");
				}
			}
		} else if ($mode == 2) {
			// feed archive
			foreach ($payload as $id) {
				$result = db_query($this->link, "SELECT * FROM ttrss_archived_feeds
					WHERE id = '$id' AND owner_uid = " . $_SESSION["uid"]);

				if (db_num_rows($result) != 0) {
					$site_url = db_escape_string(db_fetch_result($result, 0, "site_url"));
					$feed_url = db_escape_string(db_fetch_result($result, 0, "feed_url"));
					$title = db_escape_string(db_fetch_result($result, 0, "title"));

					$result = db_query($this->link, "SELECT id FROM ttrss_feeds WHERE
						feed_url = '$feed_url' AND owner_uid = " . $_SESSION["uid"]);

					if (db_num_rows($result) == 0) {
						$result = db_query($this->link, "INSERT INTO ttrss_feeds
										(owner_uid,feed_url,title,cat_id,site_url)
									VALUES ('$id','".$_SESSION["uid"]."',
									'$feed_url', '$title', NULL, '$site_url')");
					}
				}
			}
		}
	}

	function catchupFeed() {
		$feed_id = db_escape_string($_REQUEST['feed_id']);
		$is_cat = db_escape_string($_REQUEST['is_cat']) == "true";
		$max_id = (int) db_escape_string($_REQUEST['max_id']);

		catchup_feed($this->link, $feed_id, $is_cat, false, $max_id);

		print json_encode(array("message" => "UPDATE_COUNTERS"));
	}

	function quickAddCat() {
		$cat = db_escape_string($_REQUEST["cat"]);

		add_feed_category($this->link, $cat);

		$result = db_query($this->link, "SELECT id FROM ttrss_feed_categories WHERE
			title = '$cat' AND owner_uid = " . $_SESSION["uid"]);

		if (db_num_rows($result) == 1) {
			$id = db_fetch_result($result, 0, "id");
		} else {
			$id = 0;
		}

		print_feed_cat_select($this->link, "cat_id", $id);
	}

	function regenFeedKey() {
		$feed_id = db_escape_string($_REQUEST['id']);
		$is_cat = db_escape_string($_REQUEST['is_cat']) == "true";

		$new_key = update_feed_access_key($this->link, $feed_id, $is_cat);

		print json_encode(array("link" => $new_key));
	}

	// Silent
	function clearKeys() {
		db_query($this->link, "DELETE FROM ttrss_access_keys WHERE
			owner_uid = " . $_SESSION["uid"]);
	}

	// Silent
	function clearArticleKeys() {
		db_query($this->link, "UPDATE ttrss_user_entries SET uuid = '' WHERE
			owner_uid = " . $_SESSION["uid"]);

		return;
	}

	function verifyRegexp() {
		$reg_exp = $_REQUEST["reg_exp"];

		$status = @preg_match("/$reg_exp/i", "TEST") !== false;

		print json_encode(array("status" => $status));
	}

	/* function buttonPlugin() {
		$pclass = "button_" . basename($_REQUEST['plugin']);
		$method = $_REQUEST['plugin_method'];

		if (class_exists($pclass)) {
			$plugin = new $pclass($this->link);
			if (method_exists($plugin, $method)) {
				return $plugin->$method();
			}
		}
	} */

	function genHash() {
		$hash = sha1(uniqid(rand(), true));

		print json_encode(array("hash" => $hash));
	}

	function batchAddFeeds() {
		$cat_id = db_escape_string($_REQUEST['cat']);
		$feeds = explode("\n", db_escape_string($_REQUEST['feeds']));
		$login = db_escape_string($_REQUEST['login']);
		$pass = db_escape_string($_REQUEST['pass']);
		$need_auth = db_escape_string($_REQUEST['need_auth']) != "";

		foreach ($feeds as $feed) {
			$feed = trim($feed);

			if (validate_feed_url($feed)) {

				db_query($this->link, "BEGIN");

				if ($cat_id == "0" || !$cat_id) {
					$cat_qpart = "NULL";
				} else {
					$cat_qpart = "'$cat_id'";
				}

				$result = db_query($this->link,
					"SELECT id FROM ttrss_feeds
					WHERE feed_url = '$feed' AND owner_uid = ".$_SESSION["uid"]);

				if (db_num_rows($result) == 0) {
					$result = db_query($this->link,
						"INSERT INTO ttrss_feeds
							(owner_uid,feed_url,title,cat_id,auth_login,auth_pass,update_method)
						VALUES ('".$_SESSION["uid"]."', '$feed',
							'[Unknown]', $cat_qpart, '$login', '$pass', 0)");
				}

				db_query($this->link, "COMMIT");
			}
		}
	}

	function setScore() {
		$ids = db_escape_string($_REQUEST['id']);
		$score = (int)db_escape_string($_REQUEST['score']);

		db_query($this->link, "UPDATE ttrss_user_entries SET
			score = '$score' WHERE ref_id IN ($ids) AND owner_uid = " . $_SESSION["uid"]);

		print json_encode(array("id" => $id,
			"score_pic" => theme_image($link, get_score_pic($score))));
	}

	function setpanelmode() {
		$wide = (int) $_REQUEST["wide"];

		$_SESSION["widescreen"] = $wide;

		print json_encode(array("wide" => $wide));
	}

	function updaterandomfeed() {
		// Test if the feed need a update (update interval exceded).
		if (DB_TYPE == "pgsql") {
			$update_limit_qpart = "AND ((
					ttrss_feeds.update_interval = 0
					AND ttrss_feeds.last_updated < NOW() - CAST((ttrss_user_prefs.value || ' minutes') AS INTERVAL)
				) OR (
					ttrss_feeds.update_interval > 0
					AND ttrss_feeds.last_updated < NOW() - CAST((ttrss_feeds.update_interval || ' minutes') AS INTERVAL)
				) OR ttrss_feeds.last_updated IS NULL
				OR last_updated = '1970-01-01 00:00:00')";
		} else {
			$update_limit_qpart = "AND ((
					ttrss_feeds.update_interval = 0
					AND ttrss_feeds.last_updated < DATE_SUB(NOW(), INTERVAL CONVERT(ttrss_user_prefs.value, SIGNED INTEGER) MINUTE)
				) OR (
					ttrss_feeds.update_interval > 0
					AND ttrss_feeds.last_updated < DATE_SUB(NOW(), INTERVAL ttrss_feeds.update_interval MINUTE)
				) OR ttrss_feeds.last_updated IS NULL
				OR last_updated = '1970-01-01 00:00:00')";
		}

		// Test if feed is currently being updated by another process.
		if (DB_TYPE == "pgsql") {
			$updstart_thresh_qpart = "AND (ttrss_feeds.last_update_started IS NULL OR ttrss_feeds.last_update_started < NOW() - INTERVAL '5 minutes')";
		} else {
			$updstart_thresh_qpart = "AND (ttrss_feeds.last_update_started IS NULL OR ttrss_feeds.last_update_started < DATE_SUB(NOW(), INTERVAL 5 MINUTE))";
		}

		$random_qpart = sql_random_function();

		// We search for feed needing update.
		$result = db_query($this->link, "SELECT ttrss_feeds.feed_url,ttrss_feeds.id
			FROM
				ttrss_feeds, ttrss_users, ttrss_user_prefs
			WHERE
				ttrss_feeds.owner_uid = ttrss_users.id
				AND ttrss_users.id = ttrss_user_prefs.owner_uid
				AND ttrss_user_prefs.pref_name = 'DEFAULT_UPDATE_INTERVAL'
				AND ttrss_feeds.owner_uid = ".$_SESSION["uid"]."
				$update_limit_qpart $updstart_thresh_qpart
			ORDER BY $random_qpart LIMIT 30");

		$feed_id = -1;

		require_once "rssfuncs.php";

		$num_updated = 0;

		$tstart = time();

		while ($line = db_fetch_assoc($result)) {
			$feed_id = $line["id"];

			if (time() - $tstart < ini_get("max_execution_time") * 0.7) {
				update_rss_feed($this->link, $feed_id, true);
				++$num_updated;
			} else {
				break;
			}
		}

		if ($num_updated > 0) {
			print json_encode(array("message" => "UPDATE_COUNTERS",
				"num_updated" => $num_updated));
		} else {
			print json_encode(array("message" => "NOTHING_TO_UPDATE"));
		}

	}

}
?>

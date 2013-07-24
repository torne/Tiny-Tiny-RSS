<?php
class RPC extends Handler_Protected {

	function csrf_ignore($method) {
		$csrf_ignored = array("sanitycheck", "completelabels");

		return array_search($method, $csrf_ignored) !== false;
	}

	function setprofile() {
		$id = $this->dbh->escape_string($_REQUEST["id"]);

		$_SESSION["profile"] = $id;
	}

	function remprofiles() {
		$ids = explode(",", $this->dbh->escape_string(trim($_REQUEST["ids"])));

		foreach ($ids as $id) {
			if ($_SESSION["profile"] != $id) {
				$this->dbh->query("DELETE FROM ttrss_settings_profiles WHERE id = '$id' AND
							owner_uid = " . $_SESSION["uid"]);
			}
		}
	}

	// Silent
	function addprofile() {
		$title = $this->dbh->escape_string(trim($_REQUEST["title"]));
		if ($title) {
			$this->dbh->query("BEGIN");

			$result = $this->dbh->query("SELECT id FROM ttrss_settings_profiles
				WHERE title = '$title' AND owner_uid = " . $_SESSION["uid"]);

			if ($this->dbh->num_rows($result) == 0) {

				$this->dbh->query("INSERT INTO ttrss_settings_profiles (title, owner_uid)
							VALUES ('$title', ".$_SESSION["uid"] .")");

				$result = $this->dbh->query("SELECT id FROM ttrss_settings_profiles WHERE
					title = '$title'");

				if ($this->dbh->num_rows($result) != 0) {
					$profile_id = $this->dbh->fetch_result($result, 0, "id");

					if ($profile_id) {
						initialize_user_prefs($_SESSION["uid"], $profile_id);
					}
				}
			}

			$this->dbh->query("COMMIT");
		}
	}

	// Silent
	function saveprofile() {
		$id = $this->dbh->escape_string($_REQUEST["id"]);
		$title = $this->dbh->escape_string(trim($_REQUEST["value"]));

		if ($id == 0) {
			print __("Default profile");
			return;
		}

		if ($title) {
			$this->dbh->query("BEGIN");

			$result = $this->dbh->query("SELECT id FROM ttrss_settings_profiles
				WHERE title = '$title' AND owner_uid =" . $_SESSION["uid"]);

			if ($this->dbh->num_rows($result) == 0) {
				$this->dbh->query("UPDATE ttrss_settings_profiles
							SET title = '$title' WHERE id = '$id' AND
							owner_uid = " . $_SESSION["uid"]);
				print $title;
			} else {
				$result = $this->dbh->query("SELECT title FROM ttrss_settings_profiles
							WHERE id = '$id' AND owner_uid =" . $_SESSION["uid"]);
				print $this->dbh->fetch_result($result, 0, "title");
			}

			$this->dbh->query("COMMIT");
		}
	}

	// Silent
	function remarchive() {
		$ids = explode(",", $this->dbh->escape_string($_REQUEST["ids"]));

		foreach ($ids as $id) {
			$result = $this->dbh->query("DELETE FROM ttrss_archived_feeds WHERE
		(SELECT COUNT(*) FROM ttrss_user_entries
							WHERE orig_feed_id = '$id') = 0 AND
		id = '$id' AND owner_uid = ".$_SESSION["uid"]);

			$rc = $this->dbh->affected_rows($result);
		}
	}

	function addfeed() {
		$feed = $this->dbh->escape_string($_REQUEST['feed']);
		$cat = $this->dbh->escape_string($_REQUEST['cat']);
		$login = $this->dbh->escape_string($_REQUEST['login']);
		$pass = trim($_REQUEST['pass']); // escaped later

		$rc = subscribe_to_feed($feed, $cat, $login, $pass);

		print json_encode(array("result" => $rc));
	}

	function togglepref() {
		$key = $this->dbh->escape_string($_REQUEST["key"]);
		set_pref($key, !get_pref($key));
		$value = get_pref($key);

		print json_encode(array("param" =>$key, "value" => $value));
	}

	function setpref() {
		// set_pref escapes input, so no need to double escape it here
		$key = $_REQUEST['key'];
		$value = str_replace("\n", "<br/>", $_REQUEST['value']);

		set_pref($key, $value, $_SESSION['uid'], $key != 'USER_STYLESHEET');

		print json_encode(array("param" =>$key, "value" => $value));
	}

	function mark() {
		$mark = $_REQUEST["mark"];
		$id = $this->dbh->escape_string($_REQUEST["id"]);

		if ($mark == "1") {
			$mark = "true";
		} else {
			$mark = "false";
		}

		$result = $this->dbh->query("UPDATE ttrss_user_entries SET marked = $mark,
					last_marked = NOW()
					WHERE ref_id = '$id' AND owner_uid = " . $_SESSION["uid"]);

		print json_encode(array("message" => "UPDATE_COUNTERS"));
	}

	function delete() {
		$ids = $this->dbh->escape_string($_REQUEST["ids"]);

		$result = $this->dbh->query("DELETE FROM ttrss_user_entries
		WHERE ref_id IN ($ids) AND owner_uid = " . $_SESSION["uid"]);

		purge_orphans();

		print json_encode(array("message" => "UPDATE_COUNTERS"));
	}

	function unarchive() {
		$ids = explode(",", $_REQUEST["ids"]);

		foreach ($ids as $id) {
			$id = $this->dbh->escape_string(trim($id));
			$this->dbh->query("BEGIN");

			$result = $this->dbh->query("SELECT feed_url,site_url,title FROM ttrss_archived_feeds
				WHERE id = (SELECT orig_feed_id FROM ttrss_user_entries WHERE ref_id = $id
				AND owner_uid = ".$_SESSION["uid"].")");

			if ($this->dbh->num_rows($result) != 0) {
				$feed_url = $this->dbh->escape_string(db_fetch_result($result, 0, "feed_url"));
				$site_url = $this->dbh->escape_string(db_fetch_result($result, 0, "site_url"));
				$title = $this->dbh->escape_string(db_fetch_result($result, 0, "title"));

				$result = $this->dbh->query("SELECT id FROM ttrss_feeds WHERE feed_url = '$feed_url'
					AND owner_uid = " .$_SESSION["uid"]);

				if ($this->dbh->num_rows($result) == 0) {

					if (!$title) $title = '[Unknown]';

					$result = $this->dbh->query(
						"INSERT INTO ttrss_feeds
							(owner_uid,feed_url,site_url,title,cat_id,auth_login,auth_pass,update_method)
							VALUES (".$_SESSION["uid"].",
							'$feed_url',
							'$site_url',
							'$title',
							NULL, '', '', 0)");

					$result = $this->dbh->query(
						"SELECT id FROM ttrss_feeds WHERE feed_url = '$feed_url'
						AND owner_uid = ".$_SESSION["uid"]);

					if ($this->dbh->num_rows($result) != 0) {
						$feed_id = $this->dbh->fetch_result($result, 0, "id");
					}

				} else {
					$feed_id = $this->dbh->fetch_result($result, 0, "id");
				}

				if ($feed_id) {
					$result = $this->dbh->query("UPDATE ttrss_user_entries
						SET feed_id = '$feed_id', orig_feed_id = NULL
						WHERE ref_id = $id AND owner_uid = " . $_SESSION["uid"]);
				}
			}

			$this->dbh->query("COMMIT");
		}

		print json_encode(array("message" => "UPDATE_COUNTERS"));
	}

	function archive() {
		$ids = explode(",", $this->dbh->escape_string($_REQUEST["ids"]));

		foreach ($ids as $id) {
			$this->archive_article($id, $_SESSION["uid"]);
		}

		print json_encode(array("message" => "UPDATE_COUNTERS"));
	}

	private function archive_article($id, $owner_uid) {
		$this->dbh->query("BEGIN");

		$result = $this->dbh->query("SELECT feed_id FROM ttrss_user_entries
			WHERE ref_id = '$id' AND owner_uid = $owner_uid");

		if ($this->dbh->num_rows($result) != 0) {

			/* prepare the archived table */

			$feed_id = (int) $this->dbh->fetch_result($result, 0, "feed_id");

			if ($feed_id) {
				$result = $this->dbh->query("SELECT id FROM ttrss_archived_feeds
					WHERE id = '$feed_id'");

				if ($this->dbh->num_rows($result) == 0) {
					$this->dbh->query("INSERT INTO ttrss_archived_feeds
						(id, owner_uid, title, feed_url, site_url)
					SELECT id, owner_uid, title, feed_url, site_url from ttrss_feeds
				  	WHERE id = '$feed_id'");
				}

				$this->dbh->query("UPDATE ttrss_user_entries
					SET orig_feed_id = feed_id, feed_id = NULL
					WHERE ref_id = '$id' AND owner_uid = " . $_SESSION["uid"]);
			}
		}

		$this->dbh->query("COMMIT");
	}

	function publ() {
		$pub = $_REQUEST["pub"];
		$id = $this->dbh->escape_string($_REQUEST["id"]);
		$note = trim(strip_tags($this->dbh->escape_string($_REQUEST["note"])));

		if ($pub == "1") {
			$pub = "true";
		} else {
			$pub = "false";
		}

		$result = $this->dbh->query("UPDATE ttrss_user_entries SET
			published = $pub, last_published = NOW()
			WHERE ref_id = '$id' AND owner_uid = " . $_SESSION["uid"]);

		$pubsub_result = false;

		if (PUBSUBHUBBUB_HUB) {
			$rss_link = get_self_url_prefix() .
				"/public.php?op=rss&id=-2&key=" .
				get_feed_access_key(-2, false);

			$p = new Publisher(PUBSUBHUBBUB_HUB);

			$pubsub_result = $p->publish_update($rss_link);
		}

		print json_encode(array("message" => "UPDATE_COUNTERS",
			"pubsub_result" => $pubsub_result));
	}

	function getAllCounters() {
		$last_article_id = (int) $_REQUEST["last_article_id"];

		$reply = array();

		if (!empty($_REQUEST['seq'])) $reply['seq'] = (int) $_REQUEST['seq'];

		if ($last_article_id != getLastArticleId()) {
			$reply['counters'] = getAllCounters();
		}

		$reply['runtime-info'] = make_runtime_info();

		print json_encode($reply);
	}

	/* GET["cmode"] = 0 - mark as read, 1 - as unread, 2 - toggle */
	function catchupSelected() {
		$ids = explode(",", $this->dbh->escape_string($_REQUEST["ids"]));
		$cmode = sprintf("%d", $_REQUEST["cmode"]);

		catchupArticlesById($ids, $cmode);

		print json_encode(array("message" => "UPDATE_COUNTERS", "ids" => $ids));
	}

	function markSelected() {
		$ids = explode(",", $this->dbh->escape_string($_REQUEST["ids"]));
		$cmode = sprintf("%d", $_REQUEST["cmode"]);

		$this->markArticlesById($ids, $cmode);

		print json_encode(array("message" => "UPDATE_COUNTERS"));
	}

	function publishSelected() {
		$ids = explode(",", $this->dbh->escape_string($_REQUEST["ids"]));
		$cmode = sprintf("%d", $_REQUEST["cmode"]);

		$this->publishArticlesById($ids, $cmode);

		print json_encode(array("message" => "UPDATE_COUNTERS"));
	}

	function sanityCheck() {
		$_SESSION["hasAudio"] = $_REQUEST["hasAudio"] === "true";
		$_SESSION["hasSandbox"] = $_REQUEST["hasSandbox"] === "true";
		$_SESSION["hasMp3"] = $_REQUEST["hasMp3"] === "true";
		$_SESSION["clientTzOffset"] = $_REQUEST["clientTzOffset"];

		$reply = array();

		$reply['error'] = sanity_check();

		if ($reply['error']['code'] == 0) {
			$reply['init-params'] = make_init_params();
			$reply['runtime-info'] = make_runtime_info();
		}

		print json_encode($reply);
	}

	function completeLabels() {
		$search = $this->dbh->escape_string($_REQUEST["search"]);

		$result = $this->dbh->query("SELECT DISTINCT caption FROM
				ttrss_labels2
				WHERE owner_uid = '".$_SESSION["uid"]."' AND
				LOWER(caption) LIKE LOWER('$search%') ORDER BY caption
				LIMIT 5");

		print "<ul>";
		while ($line = $this->dbh->fetch_assoc($result)) {
			print "<li>" . $line["caption"] . "</li>";
		}
		print "</ul>";
	}

	function purge() {
		$ids = explode(",", $this->dbh->escape_string($_REQUEST["ids"]));
		$days = sprintf("%d", $_REQUEST["days"]);

		foreach ($ids as $id) {

			$result = $this->dbh->query("SELECT id FROM ttrss_feeds WHERE
				id = '$id' AND owner_uid = ".$_SESSION["uid"]);

			if ($this->dbh->num_rows($result) == 1) {
				purge_feed($id, $days);
			}
		}
	}

	function updateFeedBrowser() {
		$search = $this->dbh->escape_string($_REQUEST["search"]);
		$limit = $this->dbh->escape_string($_REQUEST["limit"]);
		$mode = (int) $this->dbh->escape_string($_REQUEST["mode"]);

		require_once "feedbrowser.php";

		print json_encode(array("content" =>
			make_feed_browser($search, $limit, $mode),
				"mode" => $mode));
	}

	// Silent
	function massSubscribe() {

		$payload = json_decode($_REQUEST["payload"], false);
		$mode = $_REQUEST["mode"];

		if (!$payload || !is_array($payload)) return;

		if ($mode == 1) {
			foreach ($payload as $feed) {

				$title = $this->dbh->escape_string($feed[0]);
				$feed_url = $this->dbh->escape_string($feed[1]);

				$result = $this->dbh->query("SELECT id FROM ttrss_feeds WHERE
					feed_url = '$feed_url' AND owner_uid = " . $_SESSION["uid"]);

				if ($this->dbh->num_rows($result) == 0) {
					$result = $this->dbh->query("INSERT INTO ttrss_feeds
									(owner_uid,feed_url,title,cat_id,site_url)
									VALUES ('".$_SESSION["uid"]."',
									'$feed_url', '$title', NULL, '')");
				}
			}
		} else if ($mode == 2) {
			// feed archive
			foreach ($payload as $id) {
				$result = $this->dbh->query("SELECT * FROM ttrss_archived_feeds
					WHERE id = '$id' AND owner_uid = " . $_SESSION["uid"]);

				if ($this->dbh->num_rows($result) != 0) {
					$site_url = $this->dbh->escape_string(db_fetch_result($result, 0, "site_url"));
					$feed_url = $this->dbh->escape_string(db_fetch_result($result, 0, "feed_url"));
					$title = $this->dbh->escape_string(db_fetch_result($result, 0, "title"));

					$result = $this->dbh->query("SELECT id FROM ttrss_feeds WHERE
						feed_url = '$feed_url' AND owner_uid = " . $_SESSION["uid"]);

					if ($this->dbh->num_rows($result) == 0) {
						$result = $this->dbh->query("INSERT INTO ttrss_feeds
										(owner_uid,feed_url,title,cat_id,site_url)
									VALUES ('$id','".$_SESSION["uid"]."',
									'$feed_url', '$title', NULL, '$site_url')");
					}
				}
			}
		}
	}

	function catchupFeed() {
		$feed_id = $this->dbh->escape_string($_REQUEST['feed_id']);
		$is_cat = $this->dbh->escape_string($_REQUEST['is_cat']) == "true";
		$mode = $this->dbh->escape_string($_REQUEST['mode']);

		catchup_feed($feed_id, $is_cat, false, false, $mode);

		print json_encode(array("message" => "UPDATE_COUNTERS"));
	}

	function quickAddCat() {
		$cat = $this->dbh->escape_string($_REQUEST["cat"]);

		add_feed_category($cat);

		$result = $this->dbh->query("SELECT id FROM ttrss_feed_categories WHERE
			title = '$cat' AND owner_uid = " . $_SESSION["uid"]);

		if ($this->dbh->num_rows($result) == 1) {
			$id = $this->dbh->fetch_result($result, 0, "id");
		} else {
			$id = 0;
		}

		print_feed_cat_select("cat_id", $id, '');
	}

	function setpanelmode() {
		$wide = (int) $_REQUEST["wide"];

		setcookie("ttrss_widescreen", $wide,
			time() + COOKIE_LIFETIME_LONG);

		print json_encode(array("wide" => $wide));
	}

	static function updaterandomfeed_real($dbh) {

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
		$result = $dbh->query("SELECT ttrss_feeds.feed_url,ttrss_feeds.id
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

		while ($line = $dbh->fetch_assoc($result)) {
			$feed_id = $line["id"];

			if (time() - $tstart < ini_get("max_execution_time") * 0.7) {
				update_rss_feed($feed_id, true);
				++$num_updated;
			} else {
				break;
			}
		}

		// Purge orphans and cleanup tags
		purge_orphans();
		cleanup_tags(14, 50000);

		if ($num_updated > 0) {
			print json_encode(array("message" => "UPDATE_COUNTERS",
				"num_updated" => $num_updated));
		} else {
			print json_encode(array("message" => "NOTHING_TO_UPDATE"));
		}

	}

	function updaterandomfeed() {
		RPC::updaterandomfeed_real($this->dbh);
	}

	private function markArticlesById($ids, $cmode) {

		$tmp_ids = array();

		foreach ($ids as $id) {
			array_push($tmp_ids, "ref_id = '$id'");
		}

		$ids_qpart = join(" OR ", $tmp_ids);

		if ($cmode == 0) {
			$this->dbh->query("UPDATE ttrss_user_entries SET
			marked = false, last_marked = NOW()
			WHERE ($ids_qpart) AND owner_uid = " . $_SESSION["uid"]);
		} else if ($cmode == 1) {
			$this->dbh->query("UPDATE ttrss_user_entries SET
			marked = true, last_marked = NOW()
			WHERE ($ids_qpart) AND owner_uid = " . $_SESSION["uid"]);
		} else {
			$this->dbh->query("UPDATE ttrss_user_entries SET
			marked = NOT marked,last_marked = NOW()
			WHERE ($ids_qpart) AND owner_uid = " . $_SESSION["uid"]);
		}
	}

	private function publishArticlesById($ids, $cmode) {

		$tmp_ids = array();

		foreach ($ids as $id) {
			array_push($tmp_ids, "ref_id = '$id'");
		}

		$ids_qpart = join(" OR ", $tmp_ids);

		if ($cmode == 0) {
			$this->dbh->query("UPDATE ttrss_user_entries SET
			published = false,last_published = NOW()
			WHERE ($ids_qpart) AND owner_uid = " . $_SESSION["uid"]);
		} else if ($cmode == 1) {
			$this->dbh->query("UPDATE ttrss_user_entries SET
			published = true,last_published = NOW()
			WHERE ($ids_qpart) AND owner_uid = " . $_SESSION["uid"]);
		} else {
			$this->dbh->query("UPDATE ttrss_user_entries SET
			published = NOT published,last_published = NOW()
			WHERE ($ids_qpart) AND owner_uid = " . $_SESSION["uid"]);
		}

		if (PUBSUBHUBBUB_HUB) {
			$rss_link = get_self_url_prefix() .
				"/public.php?op=rss&id=-2&key=" .
				get_feed_access_key(-2, false);

			$p = new Publisher(PUBSUBHUBBUB_HUB);

			$pubsub_result = $p->publish_update($rss_link);
		}
	}

	function getlinktitlebyid() {
		$id = $this->dbh->escape_string($_REQUEST['id']);

		$result = $this->dbh->query("SELECT link, title FROM ttrss_entries, ttrss_user_entries
			WHERE ref_id = '$id' AND ref_id = id AND owner_uid = ". $_SESSION["uid"]);

		if ($this->dbh->num_rows($result) != 0) {
			$link = $this->dbh->fetch_result($result, 0, "link");
			$title = $this->dbh->fetch_result($result, 0, "title");

			echo json_encode(array("link" => $link, "title" => $title));
		} else {
			echo json_encode(array("error" => "ARTICLE_NOT_FOUND"));
		}
	}

	function log() {
		$logmsg = $this->dbh->escape_string($_REQUEST['logmsg']);

		if ($logmsg) {
			Logger::get()->log_error(E_USER_WARNING,
				$logmsg, '[client-js]', 0, false);
		}

		echo json_encode(array("message" => "HOST_ERROR_LOGGED"));

	}
}
?>

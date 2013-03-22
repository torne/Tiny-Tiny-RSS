<?php
class Article extends Handler_Protected {

	function csrf_ignore($method) {
		$csrf_ignored = array("redirect");

		return array_search($method, $csrf_ignored) !== false;
	}

	function redirect() {
		$id = db_escape_string($this->link, $_REQUEST['id']);

		$result = db_query($this->link, "SELECT link FROM ttrss_entries, ttrss_user_entries
						WHERE id = '$id' AND id = ref_id AND owner_uid = '".$_SESSION['uid']."'
						LIMIT 1");

		if (db_num_rows($result) == 1) {
			$article_url = db_fetch_result($result, 0, 'link');
			$article_url = str_replace("\n", "", $article_url);

			header("Location: $article_url");
			return;

		} else {
			print_error(__("Article not found."));
		}
	}

	function view() {
		$id = db_escape_string($this->link, $_REQUEST["id"]);
		$cids = explode(",", db_escape_string($this->link, $_REQUEST["cids"]));
		$mode = db_escape_string($this->link, $_REQUEST["mode"]);
		$omode = db_escape_string($this->link, $_REQUEST["omode"]);

		// in prefetch mode we only output requested cids, main article
		// just gets marked as read (it already exists in client cache)

		$articles = array();

		if ($mode == "") {
			array_push($articles, format_article($this->link, $id, false));
		} else if ($mode == "zoom") {
			array_push($articles, format_article($this->link, $id, true, true));
		} else if ($mode == "raw") {
			if ($_REQUEST['html']) {
				header("Content-Type: text/html");
				print '<link rel="stylesheet" type="text/css" href="tt-rss.css"/>';
			}

			$article = format_article($this->link, $id, false);
			print $article['content'];
			return;
		}

		$this->catchupArticleById($this->link, $id, 0);

		if (!$_SESSION["bw_limit"]) {
			foreach ($cids as $cid) {
				if ($cid) {
					array_push($articles, format_article($this->link, $cid, false, false));
				}
			}
		}

		print json_encode($articles);
	}

	private function catchupArticleById($link, $id, $cmode) {

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

	static function create_published_article($link, $title, $url, $content, $labels_str,
			$owner_uid) {

		$guid = sha1($url . $owner_uid); // include owner_uid to prevent global GUID clash
		$content_hash = sha1($content);

		if ($labels_str != "") {
			$labels = explode(",", $labels_str);
		} else {
			$labels = array();
		}

		$rc = false;

		if (!$title) $title = $url;
		if (!$title && !$url) return false;

		if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) return false;

		db_query($link, "BEGIN");

		// only check for our user data here, others might have shared this with different content etc
		$result = db_query($link, "SELECT id FROM ttrss_entries, ttrss_user_entries WHERE
			link = '$url' AND ref_id = id AND owner_uid = '$owner_uid' LIMIT 1");

		if (db_num_rows($result) != 0) {
			$ref_id = db_fetch_result($result, 0, "id");

			$result = db_query($link, "SELECT int_id FROM ttrss_user_entries WHERE
				ref_id = '$ref_id' AND owner_uid = '$owner_uid' LIMIT 1");

			if (db_num_rows($result) != 0) {
				$int_id = db_fetch_result($result, 0, "int_id");

				db_query($link, "UPDATE ttrss_entries SET
					content = '$content', content_hash = '$content_hash' WHERE id = '$ref_id'");

				db_query($link, "UPDATE ttrss_user_entries SET published = true WHERE
						int_id = '$int_id' AND owner_uid = '$owner_uid'");
			} else {

				db_query($link, "INSERT INTO ttrss_user_entries
					(ref_id, uuid, feed_id, orig_feed_id, owner_uid, published, tag_cache, label_cache, last_read, note, unread)
					VALUES
					('$ref_id', '', NULL, NULL, $owner_uid, true, '', '', NOW(), '', false)");
			}

			if (count($labels) != 0) {
				foreach ($labels as $label) {
					label_add_article($link, $ref_id, trim($label), $owner_uid);
				}
			}

			$rc = true;

		} else {
			$result = db_query($link, "INSERT INTO ttrss_entries
				(title, guid, link, updated, content, content_hash, date_entered, date_updated)
				VALUES
				('$title', '$guid', '$url', NOW(), '$content', '$content_hash', NOW(), NOW())");

			$result = db_query($link, "SELECT id FROM ttrss_entries WHERE guid = '$guid'");

			if (db_num_rows($result) != 0) {
				$ref_id = db_fetch_result($result, 0, "id");

				db_query($link, "INSERT INTO ttrss_user_entries
					(ref_id, uuid, feed_id, orig_feed_id, owner_uid, published, tag_cache, label_cache, last_read, note, unread)
					VALUES
					('$ref_id', '', NULL, NULL, $owner_uid, true, '', '', NOW(), '', false)");

				if (count($labels) != 0) {
					foreach ($labels as $label) {
						label_add_article($link, $ref_id, trim($label), $owner_uid);
					}
				}

				$rc = true;
			}
		}

		db_query($link, "COMMIT");

		return $rc;
	}



}

<?php
class Article extends Handler_Protected {

	function csrf_ignore($method) {
		$csrf_ignored = array("redirect");

		return array_search($method, $csrf_ignored) !== false;
	}

	function redirect() {
		$id = db_escape_string($_REQUEST['id']);

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
		$id = db_escape_string($_REQUEST["id"]);
		$cids = explode(",", db_escape_string($_REQUEST["cids"]));
		$mode = db_escape_string($_REQUEST["mode"]);
		$omode = db_escape_string($_REQUEST["omode"]);

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


}

<?php
class Public_Handler extends Handler {

	private function generate_syndicated_feed($owner_uid, $feed, $is_cat,
		$limit, $search, $search_mode, $match_on, $view_mode = false) {

		require_once "lib/MiniTemplator.class.php";

		$note_style = 	"background-color : #fff7d5;
			border-width : 1px; ".
			"padding : 5px; border-style : dashed; border-color : #e7d796;".
			"margin-bottom : 1em; color : #9a8c59;";

		if (!$limit) $limit = 30;

		if (get_pref($this->link, "SORT_HEADLINES_BY_FEED_DATE", $owner_uid)) {
			$date_sort_field = "updated";
		} else {
			$date_sort_field = "date_entered";
		}

		$qfh_ret = queryFeedHeadlines($this->link, $feed,
			$limit, $view_mode, $is_cat, $search, $search_mode,
			$match_on, "$date_sort_field DESC", 0, $owner_uid);

		$result = $qfh_ret[0];
		$feed_title = htmlspecialchars($qfh_ret[1]);
		$feed_site_url = $qfh_ret[2];
		$last_error = $qfh_ret[3];

		$feed_self_url = get_self_url_prefix() .
			"/public.php?op=rss&id=-2&key=" .
			get_feed_access_key($this->link, -2, false);

		if (!$feed_site_url) $feed_site_url = get_self_url_prefix();

		$tpl = new MiniTemplator;

		$tpl->readTemplateFromFile("templates/generated_feed.txt");

		$tpl->setVariable('FEED_TITLE', $feed_title);
		$tpl->setVariable('VERSION', VERSION);
		$tpl->setVariable('FEED_URL', htmlspecialchars($feed_self_url));

		if (PUBSUBHUBBUB_HUB && $feed == -2) {
			$tpl->setVariable('HUB_URL', htmlspecialchars(PUBSUBHUBBUB_HUB));
			$tpl->addBlock('feed_hub');
		}

		$tpl->setVariable('SELF_URL', htmlspecialchars(get_self_url_prefix()));

 		while ($line = db_fetch_assoc($result)) {
			$tpl->setVariable('ARTICLE_ID', htmlspecialchars($line['link']));
			$tpl->setVariable('ARTICLE_LINK', htmlspecialchars($line['link']));
			$tpl->setVariable('ARTICLE_TITLE', htmlspecialchars($line['title']));
			$tpl->setVariable('ARTICLE_EXCERPT',
				truncate_string(strip_tags($line["content_preview"]), 100, '...'));

			$content = sanitize($this->link, $line["content_preview"], false, $owner_uid);

			if ($line['note']) {
				$content = "<div style=\"$note_style\">Article note: " . $line['note'] . "</div>" .
					$content;
			}

			$tpl->setVariable('ARTICLE_CONTENT', $content);

			$tpl->setVariable('ARTICLE_UPDATED', date('c', strtotime($line["updated"])));
			$tpl->setVariable('ARTICLE_AUTHOR', htmlspecialchars($line['author']));

			$tags = get_article_tags($this->link, $line["id"], $owner_uid);

			foreach ($tags as $tag) {
				$tpl->setVariable('ARTICLE_CATEGORY', htmlspecialchars($tag));
				$tpl->addBlock('category');
			}

			$enclosures = get_article_enclosures($this->link, $line["id"]);

			foreach ($enclosures as $e) {
				$type = htmlspecialchars($e['content_type']);
				$url = htmlspecialchars($e['content_url']);
				$length = $e['duration'];

				$tpl->setVariable('ARTICLE_ENCLOSURE_URL', $url);
				$tpl->setVariable('ARTICLE_ENCLOSURE_TYPE', $type);
				$tpl->setVariable('ARTICLE_ENCLOSURE_LENGTH', $length);

				$tpl->addBlock('enclosure');
			}

			$tpl->addBlock('entry');
		}

		$tmp = "";

		$tpl->addBlock('feed');
		$tpl->generateOutputToString($tmp);

		print $tmp;
	}

	function getUnread() {
		$login = db_escape_string($_REQUEST["login"]);
		$fresh = $_REQUEST["fresh"] == "1";

		$result = db_query($this->link, "SELECT id FROM ttrss_users WHERE login = '$login'");

		if (db_num_rows($result) == 1) {
			$uid = db_fetch_result($result, 0, "id");

			print getGlobalUnread($this->link, $uid);

			if ($fresh) {
				print ";";
				print getFeedArticles($this->link, -3, false, true, $uid);
			}

		} else {
			print "-1;User not found";
		}

	}

	function getProfiles() {
		$login = db_escape_string($_REQUEST["login"]);
		$password = db_escape_string($_REQUEST["password"]);

		if (authenticate_user($this->link, $login, $password)) {
			$result = db_query($this->link, "SELECT * FROM ttrss_settings_profiles
				WHERE owner_uid = " . $_SESSION["uid"] . " ORDER BY title");

			print "<select style='width: 100%' name='profile'>";

			print "<option value='0'>" . __("Default profile") . "</option>";

			while ($line = db_fetch_assoc($result)) {
				$id = $line["id"];
				$title = $line["title"];

				print "<option value='$id'>$title</option>";
			}

			print "</select>";

			$_SESSION = array();
		}
	}

	function pubsub() {
		$mode = db_escape_string($_REQUEST['hub_mode']);
		$feed_id = (int) db_escape_string($_REQUEST['id']);
		$feed_url = db_escape_string($_REQUEST['hub_topic']);

		if (!PUBSUBHUBBUB_ENABLED) {
			header('HTTP/1.0 404 Not Found');
			echo "404 Not found";
			return;
		}

		// TODO: implement hub_verifytoken checking

		$result = db_query($this->link, "SELECT feed_url FROM ttrss_feeds
			WHERE id = '$feed_id'");

		if (db_num_rows($result) != 0) {

			$check_feed_url = db_fetch_result($result, 0, "feed_url");

			if ($check_feed_url && ($check_feed_url == $feed_url || !$feed_url)) {
				if ($mode == "subscribe") {

					db_query($this->link, "UPDATE ttrss_feeds SET pubsub_state = 2
						WHERE id = '$feed_id'");

					print $_REQUEST['hub_challenge'];
					return;

				} else if ($mode == "unsubscribe") {

					db_query($this->link, "UPDATE ttrss_feeds SET pubsub_state = 0
						WHERE id = '$feed_id'");

					print $_REQUEST['hub_challenge'];
					return;

				} else if (!$mode) {

					// Received update ping, schedule feed update.
					//update_rss_feed($this->link, $feed_id, true, true);

					db_query($this->link, "UPDATE ttrss_feeds SET
						last_update_started = '1970-01-01',
						last_updated = '1970-01-01' WHERE id = '$feed_id'");

				}
			} else {
				header('HTTP/1.0 404 Not Found');
				echo "404 Not found";
			}
		} else {
			header('HTTP/1.0 404 Not Found');
			echo "404 Not found";
		}

	}

	function logout() {
		logout_user();
		header("Location: index.php");
	}

	function fbexport() {

		$access_key = db_escape_string($_POST["key"]);

		// TODO: rate limit checking using last_connected
		$result = db_query($this->link, "SELECT id FROM ttrss_linked_instances
			WHERE access_key = '$access_key'");

		if (db_num_rows($result) == 1) {

			$instance_id = db_fetch_result($result, 0, "id");

			$result = db_query($this->link, "SELECT feed_url, site_url, title, subscribers
				FROM ttrss_feedbrowser_cache ORDER BY subscribers DESC LIMIT 100");

			$feeds = array();

			while ($line = db_fetch_assoc($result)) {
				array_push($feeds, $line);
			}

			db_query($this->link, "UPDATE ttrss_linked_instances SET
				last_status_in = 1 WHERE id = '$instance_id'");

			print json_encode(array("feeds" => $feeds));
		} else {
			print json_encode(array("error" => array("code" => 6)));
		}
	}

	function share() {
		$uuid = db_escape_string($_REQUEST["key"]);

		$result = db_query($this->link, "SELECT ref_id, owner_uid FROM ttrss_user_entries WHERE
			uuid = '$uuid'");

		if (db_num_rows($result) != 0) {
			header("Content-Type: text/html");

			$id = db_fetch_result($result, 0, "ref_id");
			$owner_uid = db_fetch_result($result, 0, "owner_uid");

			$_SESSION["uid"] = $owner_uid;
			$article = format_article($this->link, $id, false, true);
			$_SESSION["uid"] = "";

			print_r($article['content']);

		} else {
			print "Article not found.";
		}

	}

	function rss() {
		header("Content-Type: text/xml; charset=utf-8");

		$feed = db_escape_string($_REQUEST["id"]);
		$key = db_escape_string($_REQUEST["key"]);
		$is_cat = $_REQUEST["is_cat"] != false;
		$limit = (int)db_escape_string($_REQUEST["limit"]);

		$search = db_escape_string($_REQUEST["q"]);
		$match_on = db_escape_string($_REQUEST["m"]);
		$search_mode = db_escape_string($_REQUEST["smode"]);
		$view_mode = db_escape_string($_REQUEST["view-mode"]);

		if (SINGLE_USER_MODE) {
			authenticate_user($this->link, "admin", null);
		}

		$owner_id = false;

		if ($key) {
			$result = db_query($this->link, "SELECT owner_uid FROM
				ttrss_access_keys WHERE access_key = '$key' AND feed_id = '$feed'");

			if (db_num_rows($result) == 1)
				$owner_id = db_fetch_result($result, 0, "owner_uid");
		}

		if ($owner_id) {
			$_SESSION['uid'] = $owner_id;

			$this->generate_syndicated_feed(0, $feed, $is_cat, $limit,
				$search, $search_mode, $match_on, $view_mode);
		} else {
			header('HTTP/1.1 403 Forbidden');
		}
	}

	function globalUpdateFeeds() {
		include "rssfuncs.php";
		// Update all feeds needing a update.
		update_daemon_common($this->link, 0, true, true);
	}
}
?>

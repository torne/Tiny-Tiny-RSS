<?php
// TODO: digest should register digest specific hotkey actions within tt-rss
class Digest extends Plugin implements IHandler {
	private $host;

	function about() {
		return array(1.0,
			"Digest mode for tt-rss (tablet friendly UI)",
			"fox",
			true);
	}

	function init($host) {
		$this->link = $host->get_link();
		$this->host = $host;

		$host->add_handler("digest", "*", $this);
	}

	function index() {
		header("Content-type: text/html; charset=utf-8");

		login_sequence();

		require_once dirname(__FILE__) . "/digest_body.php";
	}

	/* function get_js() {
		return file_get_contents(dirname(__FILE__) . "/digest.js");
	} */

	function csrf_ignore($method) {
		return in_array($method, array("index"));
	}

	function before($method) {
		return true;
	}

	function after() {

	}

	function digestgetcontents() {
		$article_id = db_escape_string($_REQUEST['article_id']);

		$result = db_query("SELECT content,title,link,marked,published
			FROM ttrss_entries, ttrss_user_entries
			WHERE id = '$article_id' AND ref_id = id AND owner_uid = ".$_SESSION['uid']);

		$content = sanitize(db_fetch_result($result, 0, "content"));
		$title = strip_tags(db_fetch_result($result, 0, "title"));
		$article_url = htmlspecialchars(db_fetch_result($result, 0, "link"));
		$marked = sql_bool_to_bool(db_fetch_result($result, 0, "marked"));
		$published = sql_bool_to_bool(db_fetch_result($result, 0, "published"));

		print json_encode(array("article" =>
			array("id" => $article_id, "url" => $article_url,
				"tags" => get_article_tags($article_id),
				"marked" => $marked, "published" => $published,
				"title" => $title, "content" => $content)));
	}

	function digestupdate() {
		$feed_id = db_escape_string($_REQUEST['feed_id']);
		$offset = db_escape_string($_REQUEST['offset']);
		$seq = db_escape_string($_REQUEST['seq']);

		if (!$feed_id) $feed_id = -4;
		if (!$offset) $offset = 0;

		$reply = array();

		$reply['seq'] = $seq;

		$headlines = API::api_get_headlines($feed_id, 30, $offset,
				'', ($feed_id == -4), true, false, "unread", "updated DESC", 0, 0);

		$reply['headlines'] = array();
		$reply['headlines']['title'] = getFeedTitle($feed_id);
		$reply['headlines']['content'] = $headlines;

		print json_encode($reply);
	}

	function digestinit() {
		$tmp_feeds = API::api_get_feeds(-4, true, false, 0);

		$params = array();
		$feeds = array();

		foreach ($tmp_feeds as $f) {
			if ($f['id'] > 0 || $f['id'] == -4) array_push($feeds, $f);
		}

		if ($_REQUEST["init"] == 1) {
			$params["hotkeys"] = get_hotkeys_map();
		}
		$params["feeds"] = $feeds;

		print json_encode($params);
	}

	function api_version() {
		return 2;
	}

}
?>

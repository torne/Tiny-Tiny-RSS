<?
class Digest extends Plugin implements IHandler {

	private $link;
	private $host;

	function __construct($host) {
		$this->link = $host->get_link();
		$this->host = $host;

		$host->add_handler("digest", "*", $this);

		//$host->add_handler("rpc", "digestinit", $this);
		//$host->add_handler("rpc", "digestupdate", $this);
		//$host->add_handler("rpc", "digestgetcontents", $this);
	}

	function index() {
		header("Content-type: text/html; charset=utf-8");

		login_sequence($this->link);

		global $link;
		$link = $this->link;

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

		$result = db_query($this->link, "SELECT content,title,link,marked,published
			FROM ttrss_entries, ttrss_user_entries
			WHERE id = '$article_id' AND ref_id = id AND owner_uid = ".$_SESSION['uid']);

		$content = sanitize($this->link, db_fetch_result($result, 0, "content"));
		$title = strip_tags(db_fetch_result($result, 0, "title"));
		$article_url = htmlspecialchars(db_fetch_result($result, 0, "link"));
		$marked = sql_bool_to_bool(db_fetch_result($result, 0, "marked"));
		$published = sql_bool_to_bool(db_fetch_result($result, 0, "published"));

		print json_encode(array("article" =>
			array("id" => $article_id, "url" => $article_url,
				"tags" => get_article_tags($this->link, $article_id),
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

		$headlines = api_get_headlines($this->link, $feed_id, 30, $offset,
				'', ($feed_id == -4), true, false, "unread", "updated DESC", 0, 0);

		$reply['headlines'] = array();
		$reply['headlines']['title'] = getFeedTitle($this->link, $feed_id);
		$reply['headlines']['content'] = $headlines;

		print json_encode($reply);
	}

	function digestinit() {
		$tmp_feeds = api_get_feeds($this->link, -4, true, false, 0);

		$feeds = array();

		foreach ($tmp_feeds as $f) {
			if ($f['id'] > 0 || $f['id'] == -4) array_push($feeds, $f);
		}

		print json_encode(array("feeds" => $feeds));
	}

}
?>

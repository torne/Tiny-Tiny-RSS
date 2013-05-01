<?php
class FeedItem_RSS {
	private $elem;

	function __construct($elem) {
		$this->elem = $elem;
	}

	function get_id() {
		return $this->get_link();
	}

	function get_date() {
		$pubDate = $this->elem->getElementsByTagName("pubDate")->item(0);

		if ($pubDate) {
			return strtotime($pubDate->nodeValue);
		}
	}

	function get_link() {
		$link = $this->elem->getElementsByTagName("link")->item(0);

		if ($link) {
			return $link->nodeValue;
		}
	}

	function get_title() {
		$title = $this->elem->getElementsByTagName("title")->item(0);

		if ($title) {
			return $title->nodeValue;
		}
	}

	function get_content() {
		$content = $this->elem->getElementsByTagName("description")->item(0);

		if ($content) {
			return $content->nodeValue;
		}
	}

	function get_description() {
		$summary = $this->elem->getElementsByTagName("description")->item(0);

		if ($summary) {
			return $summary->nodeValue;
		}
	}

	// todo
	function get_comments_url() {

	}

	// todo
	function get_comments_count() {

	}

	function get_categories() {
		$categories = $this->elem->getElementsByTagName("category");
		$cats = array();

		foreach ($categories as $cat) {
			array_push($cats, $cat->nodeValue);
		}

		return $cats;
	}

	function get_enclosures() {
		$enclosures = $this->elem->getElementsByTagName("enclosure");

		$encs = array();

		foreach ($enclosures as $enclosure) {
			$enc = new FeedEnclosure();

			$enc->type = $enclosure->getAttribute("type");
			$enc->link = $enclosure->getAttribute("url");
			$enc->length = $enclosure->getAttribute("length");

			array_push($encs, $enc);
		}

		return $encs;
	}

	function get_author() {
		$author = $this->elem->getElementsByTagName("author")->item(0);

		if ($author) {
			$name = $author->getElementsByTagName("name")->item(0);

			if ($name) return $name->nodeValue;

			$email = $author->getElementsByTagName("email")->item(0);

			if ($email) return $email->nodeValue;

		}
	}
}
?>

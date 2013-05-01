<?php
class FeedItem_Atom {
	private $elem;

	function __construct($elem) {
		$this->elem = $elem;
	}

	function get_id() {
		$id = $this->elem->getElementsByTagName("id")->item(0);

		if ($id) {
			return $id->nodeValue;
		} else {
			return $this->get_link();
		}
	}

	function get_date() {
		$updated = $this->elem->getElementsByTagName("updated")->item(0);

		if ($updated) {
			return strtotime($updated->nodeValue);
		}
	}

	function get_link() {
		$links = $this->elem->getElementsByTagName("link");

		foreach ($links as $link) {
			if ($link && $link->hasAttribute("href") && !$link->hasAttribute("rel")) {
				return $link->getAttribute("href");
			}
		}
	}

	function get_title() {
		$title = $this->elem->getElementsByTagName("title")->item(0);

		if ($title) {
			return $title->nodeValue;
		}
	}

	function get_content() {
		$content = $this->elem->getElementsByTagName("content")->item(0);

		if ($content) {
			return $content->nodeValue;
		}
	}

	function get_description() {
		$summary = $this->elem->getElementsByTagName("summary")->item(0);

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
			if ($cat->hasAttribute("term"))
				array_push($cats, $cat->getAttribute("term"));
		}


		return $cats;
	}

	function get_enclosures() {
		$links = $this->elem->getElementsByTagName("link");

		$encs = array();

		foreach ($links as $link) {
			if ($link && $link->hasAttribute("href") && $link->hasAttribute("rel")) {
				if ($link->getAttribute("rel") == "enclosure") {
					$enc = new FeedEnclosure();

					$enc->type = $link->getAttribute("type");
					$enc->link = $link->getAttribute("href");
					$enc->length = $link->getAttribute("length");

					array_push($encs, $enc);
				}
			}
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

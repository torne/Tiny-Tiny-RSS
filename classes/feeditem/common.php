<?php
abstract class FeedItem_Common extends FeedItem {
	protected $elem;
	protected $xpath;
	protected $doc;

	function __construct($elem, $doc, $xpath) {
		$this->elem = $elem;
		$this->xpath = $xpath;
		$this->doc = $doc;
	}

	function get_author() {
		$author = $this->elem->getElementsByTagName("author")->item(0);

		if ($author) {
			$name = $author->getElementsByTagName("name")->item(0);

			if ($name) return $name->nodeValue;

			$email = $author->getElementsByTagName("email")->item(0);

			if ($email) return $email->nodeValue;
		}

		$author = $this->xpath->query("dc:creator", $this->elem)->item(0);

		if ($author) {
			return $author->nodeValue;
		}
	}

	// todo
	function get_comments_url() {

	}

	function get_comments_count() {
		$comments = $this->xpath->query("slash:comments", $this->elem)->item(0);

		if ($comments) {
			return $comments->nodeValue;
		}
	}


}
?>

<?php
abstract class FeedItem_Common extends FeedItem {
	protected $elem;
	protected $xpath;
	protected $doc;

	function __construct($elem, $doc, $xpath) {
		$this->elem = $elem;
		$this->xpath = $xpath;
		$this->doc = $doc;

		try {

			$source = $elem->getElementsByTagName("source")->item(0);

			// we don't need <source> element
			if ($source)
				$elem->removeChild($source);
		} catch (DOMException $e) {
			//
		}
	}

	function get_author() {
		$author = $this->elem->getElementsByTagName("author")->item(0);

		if ($author) {
			$name = $author->getElementsByTagName("name")->item(0);

			if ($name) return $name->nodeValue;

			$email = $author->getElementsByTagName("email")->item(0);

			if ($email) return $email->nodeValue;

			if ($author->nodeValue)
				return $author->nodeValue;
		}

		$author = $this->xpath->query("dc:creator", $this->elem)->item(0);

		if ($author) {
			return $author->nodeValue;
		}
	}

	function get_comments_url() {
		//RSS only. Use a query here to avoid namespace clashes (e.g. with slash).
		//might give a wrong result if a default namespace was declared (possible with XPath 2.0)
		$com_url = $this->xpath->query("comments", $this->elem)->item(0);

		if($com_url)
			return $com_url->nodeValue;

		//Atom Threading Extension (RFC 4685) stuff. Could be used in RSS feeds, so it's in common.
		//'text/html' for type is too restrictive?
		$com_url = $this->xpath->query("atom:link[@rel='replies' and contains(@type,'text/html')]/@href", $this->elem)->item(0);

		if($com_url)
			return $com_url->nodeValue;
	}

	function get_comments_count() {
		//also query for ATE stuff here
		$query = "slash:comments|thread:total|atom:link[@rel='replies']/@thread:count";
		$comments = $this->xpath->query($query, $this->elem)->item(0);

		if ($comments) {
			return $comments->nodeValue;
		}
	}


}
?>

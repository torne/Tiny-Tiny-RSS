<?php
class FeedItem_RSS extends FeedItem_Common {
	function get_id() {
		$id = $this->elem->getElementsByTagName("guid")->item(0);

		if ($id) {
			return $id->nodeValue;
		} else {
			return $this->get_link();
		}
	}

	function get_date() {
		$pubDate = $this->elem->getElementsByTagName("pubDate")->item(0);

		if ($pubDate) {
			return strtotime($pubDate->nodeValue);
		}
	}

	function get_link() {
		$link = $this->xpath->query("atom:link", $this->elem)->item(0);

		if ($link) {
			return $link->getAttribute("href");
		}

		$link = $this->elem->getElementsByTagName("guid")->item(0);

		if ($link && $link->hasAttributes() && $link->getAttribute("isPermaLink") == "true") {
			return $link->nodeValue;
		}

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
		$content = $this->xpath->query("content:encoded", $this->elem)->item(0);

		if ($content) {
			return $content->nodeValue;
		}

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

	function get_categories() {
		$categories = $this->elem->getElementsByTagName("category");
		$cats = array();

		foreach ($categories as $cat) {
			array_push($cats, $cat->nodeValue);
		}

		$categories = $this->xpath->query("dc:subject", $this->elem);

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

		$enclosures = $this->xpath->query("media:content", $this->elem);

		foreach ($enclosures as $enclosure) {
			$enc = new FeedEnclosure();

			$enc->type = $enclosure->getAttribute("type");
			$enc->link = $enclosure->getAttribute("url");
			$enc->length = $enclosure->getAttribute("length");

			array_push($encs, $enc);
		}

		return $encs;
	}

}
?>

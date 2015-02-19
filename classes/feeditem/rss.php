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

		$date = $this->xpath->query("dc:date", $this->elem)->item(0);

		if ($date) {
			return strtotime($date->nodeValue);
		}
	}

	function get_link() {
		$links = $this->xpath->query("atom:link", $this->elem);

		foreach ($links as $link) {
			if ($link && $link->hasAttribute("href") &&
				(!$link->hasAttribute("rel")
					|| $link->getAttribute("rel") == "alternate"
					|| $link->getAttribute("rel") == "standout")) {

				return trim($link->getAttribute("href"));
			}
		}

		$link = $this->elem->getElementsByTagName("guid")->item(0);

		if ($link && $link->hasAttributes() && $link->getAttribute("isPermaLink") == "true") {
			return trim($link->nodeValue);
		}

		$link = $this->elem->getElementsByTagName("link")->item(0);

		if ($link) {
			return trim($link->nodeValue);
		}
	}

	function get_title() {
		$title = $this->xpath->query("title", $this->elem)->item(0);

		if ($title) {
			return trim($title->nodeValue);
		}

		// if the document has a default namespace then querying for
		// title would fail because of reasons so let's try the old way
		$title = $this->elem->getElementsByTagName("title")->item(0);

		if ($title) {
			return trim($title->nodeValue);
		}
	}

	function get_content() {
		$contentA = $this->xpath->query("content:encoded", $this->elem)->item(0);
		$contentB = $this->elem->getElementsByTagName("description")->item(0);

		if ($contentA && !$contentB) {
			return $contentA->nodeValue;
		}


		if ($contentB && !$contentA) {
			return $contentB->nodeValue;
		}

		if ($contentA && $contentB) {
			return mb_strlen($contentA->nodeValue) > mb_strlen($contentB->nodeValue) ?
				$contentA->nodeValue : $contentB->nodeValue;
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
			array_push($cats, trim($cat->nodeValue));
		}

		$categories = $this->xpath->query("dc:subject", $this->elem);

		foreach ($categories as $cat) {
			array_push($cats, trim($cat->nodeValue));
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
			$enc->height = $enclosure->getAttribute("height");
			$enc->width = $enclosure->getAttribute("width");

			array_push($encs, $enc);
		}

		$enclosures = $this->xpath->query("media:content", $this->elem);

		foreach ($enclosures as $enclosure) {
			$enc = new FeedEnclosure();

			$enc->type = $enclosure->getAttribute("type");
			$enc->link = $enclosure->getAttribute("url");
			$enc->length = $enclosure->getAttribute("length");
			$enc->height = $enclosure->getAttribute("height");
			$enc->width = $enclosure->getAttribute("width");

			$desc = $this->xpath->query("media:description", $enclosure)->item(0);
			if ($desc) $enc->title = strip_tags($desc->nodeValue);

			array_push($encs, $enc);
		}


		$enclosures = $this->xpath->query("media:group", $this->elem);

		foreach ($enclosures as $enclosure) {
			$enc = new FeedEnclosure();

			$content = $this->xpath->query("media:content", $enclosure)->item(0);

			if ($content) {
				$enc->type = $content->getAttribute("type");
				$enc->link = $content->getAttribute("url");
				$enc->length = $content->getAttribute("length");
				$enc->height = $content->getAttribute("height");
				$enc->width = $content->getAttribute("width");

				$desc = $this->xpath->query("media:description", $content)->item(0);
				if ($desc) {
					$enc->title = strip_tags($desc->nodeValue);
				} else {
					$desc = $this->xpath->query("media:description", $enclosure)->item(0);
					if ($desc) $enc->title = strip_tags($desc->nodeValue);
				}

				array_push($encs, $enc);
			}
		}

		$enclosures = $this->xpath->query("media:thumbnail", $this->elem);

		foreach ($enclosures as $enclosure) {
			$enc = new FeedEnclosure();

			$enc->type = "image/generic";
			$enc->link = $enclosure->getAttribute("url");
			$enc->height = $enclosure->getAttribute("height");
			$enc->width = $enclosure->getAttribute("width");

			array_push($encs, $enc);
		}

		return $encs;
	}

}
?>

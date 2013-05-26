<?php
class FeedItem_Atom extends FeedItem_Common {
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
			if ($link && $link->hasAttribute("href") &&
				(!$link->hasAttribute("rel")
					|| $link->getAttribute("rel") == "alternate"
					|| $link->getAttribute("rel") == "standout")) {

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
			if ($content->hasAttribute('type')) {
				if ($content->getAttribute('type') == 'xhtml') {
					return $this->doc->saveXML($content->firstChild->nextSibling);
				}
			}

			return $content->nodeValue;
		}
	}

	function get_description() {
		$content = $this->elem->getElementsByTagName("summary")->item(0);

		if ($content) {
			if ($content->hasAttribute('type')) {
				if ($content->getAttribute('type') == 'xhtml') {
					return $this->doc->saveXML($content->firstChild->nextSibling);
				}
			}

			return $content->nodeValue;
		}

	}

	function get_categories() {
		$categories = $this->elem->getElementsByTagName("category");
		$cats = array();

		foreach ($categories as $cat) {
			if ($cat->hasAttribute("term"))
				array_push($cats, $cat->getAttribute("term"));
		}

		$categories = $this->xpath->query("dc:subject", $this->elem);

		foreach ($categories as $cat) {
			array_push($cats, $cat->nodeValue);
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

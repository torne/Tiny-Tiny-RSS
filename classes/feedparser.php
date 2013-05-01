<?php
class FeedParser {
	private $doc;
	private $error;
	private $items;
	private $link;
	private $title;
	private $type;
	private $xpath;

	const FEED_RDF = 0;
	const FEED_RSS = 1;
	const FEED_ATOM = 2;

	function __construct($data) {
		libxml_use_internal_errors(true);
		libxml_clear_errors();
		$this->doc = new DOMDocument();
		$this->doc->loadXML($data);
		$this->error = $this->format_error(libxml_get_last_error());
		libxml_clear_errors();

		$this->items = array();
	}

	function init() {
		$root = $this->doc->firstChild;
		$xpath = new DOMXPath($this->doc);
		$xpath->registerNamespace('atom', 'http://www.w3.org/2005/Atom');
		$xpath->registerNamespace('media', 'http://search.yahoo.com/mrss/');
		$xpath->registerNamespace('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
		$xpath->registerNamespace('slash', 'http://purl.org/rss/1.0/modules/slash/');
		$xpath->registerNamespace('dc', 'http://purl.org/dc/elements/1.1/');
		$xpath->registerNamespace('content', 'http://purl.org/rss/1.0/modules/content/');

		$this->xpath = $xpath;

		$root = $xpath->query("(//atom:feed|//channel|//rdf:rdf|//rdf:RDF)")->item(0);

		if ($root) {
			switch (mb_strtolower($root->tagName)) {
			case "rdf:rdf":
				$this->type = $this::FEED_RDF;
				break;
			case "channel":
				$this->type = $this::FEED_RSS;
				break;
			case "feed":
				$this->type = $this::FEED_ATOM;
				break;
			default:
				$this->error = "Unknown/unsupported feed type";
				return;
			}

			switch ($this->type) {
			case $this::FEED_ATOM:

				$title = $xpath->query("//atom:feed/atom:title")->item(0);

				if ($title) {
					$this->title = $title->nodeValue;
				}

				$link = $xpath->query("//atom:feed/atom:link[not(@rel)]")->item(0);

				if ($link && $link->hasAttributes()) {
					$this->link = $link->getAttribute("href");
				}

				$articles = $xpath->query("//atom:entry");

				foreach ($articles as $article) {
					array_push($this->items, new FeedItem_Atom($article, $this->doc, $this->xpath));
				}

				break;
			case $this::FEED_RSS:

				$title = $xpath->query("//channel/title")->item(0);

				if ($title) {
					$this->title = $title->nodeValue;
				}

				$link = $xpath->query("//channel/link")->item(0);

				if ($link && $link->hasAttributes()) {
					$this->link = $link->getAttribute("href");
				}

				$articles = $xpath->query("//channel/item");

				foreach ($articles as $article) {
					array_push($this->items, new FeedItem_RSS($article, $this->doc, $this->xpath));
				}

				break;
			case $this::FEED_RDF:
				$xpath->registerNamespace('rssfake', 'http://purl.org/rss/1.0/');

				$title = $xpath->query("//rssfake:channel/rssfake:title")->item(0);

				if ($title) {
					$this->title = $title->nodeValue;
				}

				$link = $xpath->query("//rssfake:channel/rssfake:link")->item(0);

				if ($link) {
					$this->link = $link->nodeValue;
				}

				$articles = $xpath->query("//rssfake:item");

				foreach ($articles as $article) {
					array_push($this->items, new FeedItem_RSS($article, $this->doc, $this->xpath));
				}

				break;

			}
		} else {
			$this->error = "Unknown/unsupported feed type";
			return;
		}
	}

	function format_error($error) {
		if ($error) {
			return sprintf("LibXML error %s at line %d (column %d): %s",
				$error->code, $error->line, $error->column,
				$error->message);
		} else {
			return "";
		}
	}

	function error() {
		return $this->error;
	}

	function get_link() {
		return $this->link;
	}

	function get_title() {
		return $this->title;
	}

	function get_items() {
		return $this->items;
	}

	function get_links($rel) {
		$rv = array();

		switch ($this->type) {
		case $this::FEED_ATOM:
			$links = $this->xpath->query("//atom:feed/atom:link");

			foreach ($links as $link) {
				if (!$rel || $link->hasAttribute('rel') && $link->getAttribute('rel') == $rel) {
					array_push($rv, $link->getAttribute('href'));
				}
			}
			break;
		case $this::FEED_RSS:
			$links = $this->xpath->query("//channel/link");
			foreach ($links as $link) {
				if (!$rel || $link->hasAttribute('rel') && $link->getAttribute('rel') == $rel) {
					array_push($rv, $link->getAttribute('href'));
				}
			}
			break;
		}

		return $rv;
	}
} ?>

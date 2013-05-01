<?php
class FeedParser {
	private $doc;
	private $error;
	private $items;
	private $link;
	private $title;
	private $type;

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

		if ($root) {
			switch ($root->tagName) {
			case "rss":
				$this->type = $this::FEED_RSS;
				break;
			case "feed":
				$this->type = $this::FEED_ATOM;
				break;
			default:
				$this->error = "Unknown/unsupported feed type";
				return;
			}

			$xpath = new DOMXPath($this->doc);

			switch ($this->type) {
			case $this::FEED_ATOM:
				$xpath->registerNamespace('atom', 'http://www.w3.org/2005/Atom');

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
					array_push($this->items, new FeedItem_Atom($article));
				}

				break;
			case $this::FEED_RDF:

				break;
			case $this::FEED_RSS:
				break;
			}
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

} ?>

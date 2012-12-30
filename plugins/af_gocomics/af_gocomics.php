<?php
class Af_GoComics extends Plugin {

	private $link;
	private $host;

	function about() {
		return array(1.0,
			"Strip unnecessary stuff from gocomics feeds",
			"fox");
	}

	function init($host) {
		$this->link = $host->get_link();
		$this->host = $host;

		$host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
	}

	function hook_article_filter($article) {
		$owner_uid = $article["owner_uid"];

		if (strpos($article["guid"], "gocomics.com") !== FALSE && strpos($article["guid"], "gocomics,$owner_uid:") === FALSE) {
			$doc = new DOMDocument();
			@$doc->loadHTML(fetch_file_contents($article["link"]));

			$basenode = false;

			if ($doc) {
				$xpath = new DOMXPath($doc);
				$entries = $xpath->query('(//img[@src])'); // we might also check for img[@class='strip'] I guess...

				$matches = array();

				foreach ($entries as $entry) {

					if (preg_match("/(http:\/\/assets.amuniversal.com\/.*)/i", $entry->getAttribute("src"), $matches)) {

						$entry->setAttribute("src", $matches[0]);
						$basenode = $entry;
						break;
					}
				}

				if ($basenode) {
					$article["content"] = $doc->saveXML($basenode, LIBXML_NOEMPTYTAG);

					// we need to update guid with owner_uid because our local article is different from the one
					// other users with this plugin disabled might get
					$article["guid"] = "gocomics,$owner_uid:" . $article["guid"];
				}
			}
		}

		return $article;
	}
}
?>

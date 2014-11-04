<?php
class Af_NatGeo extends Plugin {

	private $host;

	function about() {
		return array(1.0,
			"Fetch content of National Geographic feeds",
			"fox");
	}

	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
	}

	function hook_article_filter($article) {
		$owner_uid = $article["owner_uid"];

		if (strpos($article["link"], "nationalgeographic.com") !== FALSE) {

				$doc = new DOMDocument();
				@$doc->loadHTML(fetch_file_contents($article["link"]));

				$basenode = false;

				if ($doc) {
					$xpath = new DOMXPath($doc);

					$basenode = $doc->getElementById("content_mainA");

					$trash = $xpath->query("//*[@class='aside' or @id='livefyre' or @id='powered_by_livefyre' or @class='social_buttons']");

					foreach ($trash as $t) {
						$t->parentNode->removeChild($t);
					}

					if ($basenode) {
						$article["content"] = $doc->saveXML($basenode);
					}
				}
		}

		return $article;
	}

	function api_version() {
		return 2;
	}
}
?>

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
			if (strpos($article["plugin_data"], "natgeo,$owner_uid:") === FALSE) {

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
						$article["plugin_data"] = "natgeo,$owner_uid:" . $article["plugin_data"];
					}
				}
			} else if (isset($article["stored"]["content"])) {
				$article["content"] = $article["stored"]["content"];
			}
		}

		return $article;
	}

	function api_version() {
		return 2;
	}
}
?>

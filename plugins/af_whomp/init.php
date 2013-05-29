<?php
class Af_Whomp extends Plugin {
	private $host;

	function about() {
		return array(1.0,
			"Embed images in Whomp!",
			"fox");
	}

	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
	}

	function hook_article_filter($article) {
		$owner_uid = $article["owner_uid"];

		if (strpos($article["guid"], "whompcomic.com") !== FALSE) {
			if (strpos($article["plugin_data"], "whomp,$owner_uid:") === FALSE) {
				$doc = new DOMDocument();
				@$doc->loadHTML(fetch_file_contents($article["link"]));

				$basenode = false;

				if ($doc) {
					$xpath = new DOMXPath($doc);
					$entries = $xpath->query('(//img[@src])');

					$matches = array();

					foreach ($entries as $entry) {

						if (preg_match("/(http:\/\/www\.whompcomic\.com\/comics\/.*)/i", $entry->getAttribute("src"), $matches)) {

							$entry->setAttribute("src", $matches[0]);
							$basenode = $entry;
							break;
						}
					}

					if ($basenode) {
						$article["content"] = $doc->saveXML($basenode);
						$article["plugin_data"] = "whomp,$owner_uid:" . $article["plugin_data"];
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

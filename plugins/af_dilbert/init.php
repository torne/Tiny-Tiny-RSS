<?php
class Af_Dilbert extends Plugin {
	private $host;

	function about() {
		return array(1.0,
			"Embeds Dilbert strips",
			"fox");
	}

	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
	}

	function hook_article_filter($article) {
		$owner_uid = $article["owner_uid"];

		if (strpos($article["guid"], "dilbert.com") !== FALSE) {
			if (strpos($article["plugin_data"], "dilbert2,$owner_uid:") === FALSE) {
				$doc = new DOMDocument();
				@$doc->loadHTML(fetch_file_contents($article["link"]));

				$basenode = false;

				if ($doc) {
					$xpath = new DOMXPath($doc);
					$entries = $xpath->query('(//img[@src])'); // we might also check for img[@class='strip'] I guess...

					$matches = array();

					foreach ($entries as $entry) {

						if (preg_match("/dyn\/str_strip\/.*zoom\.gif$/", $entry->getAttribute("src"), $matches)) {

							$entry->setAttribute("src",
								rewrite_relative_url("http://dilbert.com/",
								$matches[0]));

							$basenode = $entry;
							break;
						}
					}

					if ($basenode) {
						$article["content"] = $doc->saveXML($basenode);
						$article["plugin_data"] = "dilbert,$owner_uid:" . $article["plugin_data"];
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

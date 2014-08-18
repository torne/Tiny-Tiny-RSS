<?php
class Af_Habr extends Plugin {

	function about() {
		return array(1.0,
			"Fetch content of Habrahabr feeds",
			"fox");
	}

	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
	}

	function hook_article_filter($article) {
		$owner_uid = $article["owner_uid"];

		if (strpos($article["link"], "habrahabr.ru") !== FALSE) {

				$doc = new DOMDocument();
				@$doc->loadHTML(fetch_file_contents($article["link"]));

				$basenode = false;

				if ($doc) {
					$xpath = new DOMXPath($doc);

					$basenode = $xpath->query("//div[contains(@class,'content') and contains(@class, 'html_format')]")->item(0);

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

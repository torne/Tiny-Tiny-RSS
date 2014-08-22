<?php
class Af_ElReg extends Plugin {

	private $host;

	function about() {
		return array(1.0,
			"Fetch content of The Register feeds",
			"fox");
	}

	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
	}

	function hook_article_filter($article) {
		if (strpos($article["link"], "theregister.co.uk") !== FALSE) {

				$doc = new DOMDocument();
				@$doc->loadHTML(fetch_file_contents($article["link"]));

				$basenode = false;

				if ($doc) {
					$basenode = $doc->getElementById("body");

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

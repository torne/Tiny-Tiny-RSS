<?php
class Af_SciAm extends Plugin {

	private $host;

	function about() {
		return array(1.0,
			"Fetch content of Scientific American feeds",
			"fox");
	}

	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
	}

	function hook_article_filter($article) {
		$owner_uid = $article["owner_uid"];

		if (strpos($article["link"], "scientificamerican.com") !== FALSE || strpos($article["link"], "rss.sciam.com") !== FALSE) {

				$doc = new DOMDocument();
				@$doc->loadHTML(fetch_file_contents($article["link"]));

				$basenode = false;

				if ($doc) {
					$xpath = new DOMXpath($doc);

					$basenode = $xpath->query("//*[@id='singleBlogPost' or @id='articleContent']")->item(0);

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

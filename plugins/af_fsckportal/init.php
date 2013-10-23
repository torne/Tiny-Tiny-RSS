<?php
class Af_Fsckportal extends Plugin {

	private $host;

	function about() {
		return array(1.0,
			"Remove feedsportal spamlinks from article content",
			"fox");
	}

	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
	}

	function hook_article_filter($article) {
		$owner_uid = $article["owner_uid"];

		if (strpos($article["plugin_data"], "fsckportal,$owner_uid:") === FALSE) {

			$doc = new DOMDocument();

			$charset_hack = '<head>
				<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
			</head>';

			@$doc->loadHTML($charset_hack . $article["content"]);

			if ($doc) {
				$xpath = new DOMXPath($doc);
				$entries = $xpath->query('(//img[@src]|//a[@href])');

				$matches = array();

				foreach ($entries as $entry) {
					if (preg_match("/feedsportal.com/", $entry->getAttribute("src"))) {
						$entry->parentNode->removeChild($entry);
					} else if (preg_match("/feedsportal.com/", $entry->getAttribute("href"))) {
						$entry->parentNode->removeChild($entry);
					}
				}

				$article["content"] = $doc->saveXML($basenode);
				$article["plugin_data"] = "fsckportal,$owner_uid:" . $article["plugin_data"];

			}
		} else if (isset($article["stored"]["content"])) {
			$article["content"] = $article["stored"]["content"];
		}

		return $article;
	}

	function api_version() {
		return 2;
	}

}
?>

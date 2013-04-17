<?php
class Af_PennyArcade extends Plugin {

	private $host;

	function about() {
		return array(1.1,
			"Strip unnecessary stuff from PA feeds",
			"fox");
	}

	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
	}

	function hook_article_filter($article) {
		$owner_uid = $article["owner_uid"];

		if (strpos($article["link"], "penny-arcade.com") !== FALSE && strpos($article["title"], "Comic:") !== FALSE) {
			if (strpos($article["plugin_data"], "pennyarcade,$owner_uid:") === FALSE) {

				if ($debug_enabled) {
					_debug("af_pennyarcade: Processing comic");
				}

				$doc = new DOMDocument();
				$doc->loadHTML(fetch_file_contents($article["link"]));

				$basenode = false;

				if ($doc) {
					$xpath = new DOMXPath($doc);
					$entries = $xpath->query('(//div[@class="post comic"])');

					foreach ($entries as $entry) {
						$basenode = $entry;
					}

					if ($basenode) {
						$article["content"] = $doc->saveXML($basenode);
						$article["plugin_data"] = "pennyarcade,$owner_uid:" . $article["plugin_data"];
					}
				}
			} else if (isset($article["stored"]["content"])) {
				$article["content"] = $article["stored"]["content"];
			}
		}

		if (strpos($article["link"], "penny-arcade.com") !== FALSE && strpos($article["title"], "News Post:") !== FALSE) {
			if (strpos($article["plugin_data"], "pennyarcade,$owner_uid:") === FALSE) {
				if ($debug_enabled) {
					_debug("af_pennyarcade: Processing news post");
				}
				$doc = new DOMDocument();
				$doc->loadHTML(fetch_file_contents($article["link"]));

				if ($doc) {
					$xpath = new DOMXPath($doc);
					$entries = $xpath->query('(//div[@class="post"])');

					$basenode = false;

					foreach ($entries as $entry) {
						$basenode = $entry;
					}

					$uninteresting = $xpath->query('(//div[@class="heading"])');
					foreach ($uninteresting as $i) {
						$i->parentNode->removeChild($i);
					}

					if ($basenode){
						$article["content"] = $doc->saveXML($basenode);
						$article["plugin_data"] = "pennyarcade,$owner_uid:" . $article["plugin_data"];
					}
				}
			} else if (isset($article["stored"]["content"])) {
				$article["content"] = $article["stored"]["content"];
			}
		}

		return $article;
	}
}
?>

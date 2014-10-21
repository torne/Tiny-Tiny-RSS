<?php
class Af_Comics_Tfd extends Af_ComicFilter {

	function supported() {
		return array("Toothpaste For Dinner");
	}

	function process(&$article) {
		$owner_uid = $article["owner_uid"];

		if (strpos($article["link"], "toothpastefordinner.com") !== FALSE) {
			$doc = new DOMDocument();

			@$doc->loadHTML(fetch_file_contents($article["link"]));

			$basenode = false;

			if ($doc) {
				$xpath = new DOMXPath($doc);
				$basenode = $xpath->query('//img[@class="comic"]')->item(0);

				if ($basenode) {
					$article["content"] = $doc->saveXML($basenode);
					return true;
				}
			}
		}

		return false;
	}
}
?>

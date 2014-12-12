<?php
class Af_Comics_Explosm extends Af_ComicFilter {

	function supported() {
		return array("Cyanide and Happiness");
	}

	function process(&$article) {
		$owner_uid = $article["owner_uid"];

		if (strpos($article["link"], "explosm.net/comics") !== FALSE) {

				$doc = new DOMDocument();
				@$doc->loadHTML(fetch_file_contents($article["link"]));

				$basenode = false;

				if ($doc) {
					$xpath = new DOMXPath($doc);
					$basenode = $xpath->query('(//img[@id="main-comic"])')->item(0);

					if ($basenode) {
						$article["content"] = $doc->saveXML($basenode);
					}
				}

			return true;
		}

		return false;
	}
}
?>

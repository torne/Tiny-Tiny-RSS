<?php
class Af_Comics_Twp extends Af_ComicFilter {

	function supported() {
		return array("Three Word Phrase");
	}

	function process(&$article) {
		$owner_uid = $article["owner_uid"];

		if (strpos($article["link"], "threewordphrase.com") !== FALSE) {
			if (strpos($article["plugin_data"], "af_comics,$owner_uid:") === FALSE) {

				$doc = new DOMDocument();
				@$doc->loadHTML(fetch_file_contents($article["link"]));

				$basenode = false;

				if ($doc) {
					$xpath = new DOMXpath($doc);

					$basenode = $xpath->query("//td/center/img")->item(0);

					if ($basenode) {
						$article["content"] = $doc->saveXML($basenode);
						$article["plugin_data"] = "af_comics,$owner_uid:" . $article["plugin_data"];
					}
				}
			} else if (isset($article["stored"]["content"])) {
				$article["content"] = $article["stored"]["content"];
			}

			return true;
		}

		return false;
	}
}
?>

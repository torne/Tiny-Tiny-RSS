<?php
class Af_Comics_ComicPress extends Af_ComicFilter {

	function supported() {
		return array("Buni", "Buttersafe", "Whomp!", "Happy Jar", "CSection");
	}

	function process(&$article) {
		$owner_uid = $article["owner_uid"];

		if (strpos($article["guid"], "bunicomic.com") !== FALSE ||
				strpos($article["guid"], "buttersafe.com") !== FALSE ||
				strpos($article["guid"], "whompcomic.com") !== FALSE ||
				strpos($article["guid"], "happyjar.com") !== FALSE ||
				strpos($article["guid"], "csectioncomics.com") !== FALSE) {

			 if (strpos($article["plugin_data"], "af_comics,$owner_uid:") === FALSE) {

				// lol at people who block clients by user agent
				// oh noes my ad revenue Q_Q

				$res = fetch_file_contents($article["link"], false, false, false,
					 false, false, 0,
					 "Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; WOW64; Trident/6.0)");

				$doc = new DOMDocument();
				@$doc->loadHTML($res);

				$basenode = false;

				if ($doc) {
					$xpath = new DOMXPath($doc);
					$basenode = $xpath->query('//div[@id="comic"]')->item(0);

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

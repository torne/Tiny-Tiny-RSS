<?php
class Af_Comics_GoComics extends Af_ComicFilter {

	function supported() {
		return array("GoComics");
	}

	function process(&$article) {
		$owner_uid = $article["owner_uid"];

		if (strpos($article["guid"], "gocomics.com") !== FALSE) {
			if (strpos($article["plugin_data"], "af_comics,$owner_uid:") === FALSE) {
				$doc = new DOMDocument();
				@$doc->loadHTML(fetch_file_contents($article["link"]));

				$basenode = false;

				if ($doc) {
					$xpath = new DOMXPath($doc);
					$entries = $xpath->query("(//img[@class='strip'])");

					$matches = array();

					if ($entries->length > 1) { // if we have more than one match, then get the zoomed one, which is the second for gocomics
						$entry = $entries->item(1); // get the second element (items start at 0)
						if (preg_match("/(http:\/\/assets.amuniversal.com\/.*)/i", $entry->getAttribute("src"), $matches)) {
							$entry->setAttribute("src", $matches[0]);
							$basenode = $entry;
						}
					}

                    if (!$basenode) {
                        // fallback on the smaller version
                        foreach ($entries as $entry) {

                            if (preg_match("/(http:\/\/assets.amuniversal.com\/.*)/i", $entry->getAttribute("src"), $matches)) {

                                $entry->setAttribute("src", $matches[0]);
                                $basenode = $entry;
                                break;
                            }
                        }
                    }

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

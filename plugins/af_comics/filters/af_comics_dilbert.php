<?php
class Af_Comics_Dilbert extends Af_ComicFilter {

	function supported() {
		return array("Dilbert");
	}

	function process(&$article) {
		if (strpos($article["guid"], "dilbert.com") !== FALSE) {
				$res = fetch_file_contents($article["link"], false, false, false,
					 false, false, 0,
					 "Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; WOW64; Trident/6.0)");

				global $fetch_last_error_content;

				if (!$res && $fetch_last_error_content)
					$res = $fetch_last_error_content;

				$doc = new DOMDocument();
				@$doc->loadHTML($res);

				$basenode = false;

				if ($doc) {
					$xpath = new DOMXPath($doc);

					$basenode = $xpath->query('//div[@class="STR_Image"]')->item(0);

					/* $entries = $xpath->query('(//img[@src])'); // we might also check for img[@class='strip'] I guess...

					$matches = array();

					foreach ($entries as $entry) {

						if (preg_match("/dyn\/str_strip\/.*strip\.gif$/", $entry->getAttribute("src"), $matches)) {

							$entry->setAttribute("src",
								rewrite_relative_url("http://dilbert.com/",
								$matches[0]));

							$basenode = $entry;
							break;
						}
					} */

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

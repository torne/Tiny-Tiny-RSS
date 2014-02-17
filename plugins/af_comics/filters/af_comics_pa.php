<?php
class Af_Comics_Pa extends Af_ComicFilter {

	function supported() {
		return array("Penny Arcade");
	}

	function process(&$article) {
		$owner_uid = $article["owner_uid"];

		if (strpos($article["link"], "penny-arcade.com") !== FALSE && strpos($article["title"], "Comic:") !== FALSE) {
			if (strpos($article["plugin_data"], "af_comics,$owner_uid:") === FALSE) {

				if ($debug_enabled) {
					_debug("af_pennyarcade: Processing comic");
				}

				$doc = new DOMDocument();
				$doc->loadHTML(fetch_file_contents($article["link"]));

				$basenode = false;

				if ($doc) {
					$xpath = new DOMXPath($doc);
					$basenode = $xpath->query('(//div[@id="comicFrame"])')->item(0);

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

		if (strpos($article["link"], "penny-arcade.com") !== FALSE && strpos($article["title"], "News Post:") !== FALSE) {
			if (strpos($article["plugin_data"], "af_comics,$owner_uid:") === FALSE) {
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

					$meta = $xpath->query('(//div[@class="meta"])')->item(0);
					if ($meta->parentNode) { $meta->parentNode->removeChild($meta); }

					$header = $xpath->query('(//div[@class="postBody"]/h2)')->item(0);
					if ($header->parentNode) { $header->parentNode->removeChild($header); }

					$header = $xpath->query('(//div[@class="postBody"]/div[@class="comicPost"])')->item(0);
					if ($header->parentNode) { $header->parentNode->removeChild($header); }

					$avatar = $xpath->query('(//div[@class="avatar"]//img)')->item(0);
					$basenode->insertBefore($avatar, $basenode->firstChild);

					$uninteresting = $xpath->query('(//div[@class="avatar"])');
					foreach ($uninteresting as $i) {
						$i->parentNode->removeChild($i);
					}

					if ($basenode){
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

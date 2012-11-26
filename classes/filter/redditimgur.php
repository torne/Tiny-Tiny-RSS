<?php
class Filter_RedditImgur {

	function filter_article($article) {

		if (strpos($article["link"], "reddit.com/r/") !== FALSE) {
			if (strpos($article["content"], "i.imgur.com") !== FALSE) {

				$doc = new DOMDocument();
				@$doc->loadHTML($article["content"]);

				if ($doc) {
					$xpath = new DOMXPath($doc);
					$entries = $xpath->query('(//a[@href]|//img[@src])');

					foreach ($entries as $entry) {
						if ($entry->hasAttribute("href")) {
							if (preg_match("/i.imgur.com\/.*?.jpg/", $entry->getAttribute("href"))) {

							 	$img = $doc->createElement('img');
								$img->setAttribute("src", $entry->getAttribute("href"));

								$entry->parentNode->replaceChild($img, $entry);
							}
						}

						// remove tiny thumbnails
						if ($entry->hasAttribute("src")) {
							if ($entry->parentNode && $entry->parentNode->parentNode) {
								$entry->parentNode->parentNode->removeChild($entry->parentNode);
							}
						}
					}

					$node = $doc->getElementsByTagName('body')->item(0);

					if ($node) {
						$article["content"] = $doc->saveXML($node, LIBXML_NOEMPTYTAG);
					}
				}
			}
		}

		return $article;
	}
}
?>

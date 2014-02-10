<?php
class Af_Comics extends Plugin {

	private $host;

	function about() {
		return array(1.0,
			"Fixes RSS feeds of assorted comic strips",
			"fox");
	}

	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
		$host->add_hook($host::HOOK_PREFS_TAB, $this);
	}

	function hook_prefs_tab($args) {
		if ($args != "prefPrefs") return;

		print "<div dojoType=\"dijit.layout.AccordionPane\" title=\"".__('Feeds supported by af_comics')."\">";

		print_notice("This plugin supports the following comics:");

		print "<ul class=\"browseFeedList\" style=\"border-width : 1px\">";
		print "<li>Buni</li>
		<li>Buttersafe</li>
		<li>CSection</li>
		<li>Dilbert</li>
		<li>Explosm</li>
		<li>GoComics</li>
		<li>Happy Jar</li>
		<li>Penny Arcade</li>
		<li>Three word phrase</li>
		<li>Whomp</li>";
		print "</ul>";

		print "</div>";
	}

	function hook_article_filter($article) {
		$owner_uid = $article["owner_uid"];

		$found = false;

		# div#comic - comicpress?

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
		}

		if (strpos($article["guid"], "dilbert.com") !== FALSE) {
			if (strpos($article["plugin_data"], "af_comics,$owner_uid:") === FALSE) {
				$doc = new DOMDocument();
				@$doc->loadHTML(fetch_file_contents($article["link"]));

				$basenode = false;

				if ($doc) {
					$xpath = new DOMXPath($doc);
					$entries = $xpath->query('(//img[@src])'); // we might also check for img[@class='strip'] I guess...

					$matches = array();

					foreach ($entries as $entry) {

						if (preg_match("/dyn\/str_strip\/.*zoom\.gif$/", $entry->getAttribute("src"), $matches)) {

							$entry->setAttribute("src",
								rewrite_relative_url("http://dilbert.com/",
								$matches[0]));

							$basenode = $entry;
							break;
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
		}

		if (strpos($article["link"], "explosm.net/comics") !== FALSE) {
			if (strpos($article["plugin_data"], "af_comics,$owner_uid:") === FALSE) {

				$doc = new DOMDocument();
				@$doc->loadHTML(fetch_file_contents($article["link"]));

				$basenode = false;

				if ($doc) {
					$xpath = new DOMXPath($doc);
					$entries = $xpath->query('(//img[@src])'); // we might also check for img[@class='strip'] I guess...

					$matches = array();

					foreach ($entries as $entry) {

						if (preg_match("/(http:\/\/.*\/db\/files\/Comics\/.*)/i", $entry->getAttribute("src"), $matches)) {

							$basenode = $entry;
							break;
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
		}

		if (strpos($article["guid"], "gocomics.com") !== FALSE) {
			if (strpos($article["plugin_data"], "af_comics,$owner_uid:") === FALSE) {
				$doc = new DOMDocument();
				@$doc->loadHTML(fetch_file_contents($article["link"]));

				$basenode = false;

				if ($doc) {
					$xpath = new DOMXPath($doc);
					$entries = $xpath->query('(//img[@src])'); // we might also check for img[@class='strip'] I guess...

					$matches = array();

					foreach ($entries as $entry) {

						if (preg_match("/(http:\/\/assets.amuniversal.com\/.*width.*)/i", $entry->getAttribute("src"), $matches)) {

							$entry->setAttribute("src", $matches[0]);
							$basenode = $entry;
							break;
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
		}

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
					$entries = $xpath->query('(//div[@class="post comic"])');

					foreach ($entries as $entry) {
						$basenode = $entry;
					}

					if ($basenode) {
						$article["content"] = $doc->saveXML($basenode);
						$article["plugin_data"] = "af_comics,$owner_uid:" . $article["plugin_data"];
					}
				}
			} else if (isset($article["stored"]["content"])) {
				$article["content"] = $article["stored"]["content"];
			}
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

					$uninteresting = $xpath->query('(//div[@class="heading"])');
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
		}

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
		}

		return $article;
	}

	function api_version() {
		return 2;
	}

}
?>

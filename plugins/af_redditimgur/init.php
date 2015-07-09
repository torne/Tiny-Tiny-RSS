<?php
class Af_RedditImgur extends Plugin {
	private $host;

	function about() {
		return array(1.0,
			"Inline images (and other content) in Reddit RSS feeds",
			"fox");
	}

	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
		$host->add_hook($host::HOOK_PREFS_TAB, $this);
	}

	function hook_prefs_tab($args) {
		if ($args != "prefFeeds") return;

		print "<div id=\"af_redditimgur_prefs\" dojoType=\"dijit.layout.AccordionPane\" title=\"".__('af_redditimgur settings')."\">";

		$enable_readability = $this->host->get($this, "enable_readability");
		$enable_readability_checked = $enable_readability ? "checked" : "";

		print "<form dojoType=\"dijit.form.Form\">";

		print "<script type=\"dojo/method\" event=\"onSubmit\" args=\"evt\">
			evt.preventDefault();
			if (this.validate()) {
				console.log(dojo.objectToQuery(this.getValues()));
				new Ajax.Request('backend.php', {
					parameters: dojo.objectToQuery(this.getValues()),
					onComplete: function(transport) {
						notify_info(transport.responseText);
					}
				});
				//this.reset();
			}
			</script>";

		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"pluginhandler\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"method\" value=\"save\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"plugin\" value=\"af_redditimgur\">";

		print "<h3>" . __("Global settings") . "</h3>";

		print_notice("Uses Readability (full-text-rss) implementation by <a target='_blank' href='https://bitbucket.org/fivefilters/'>FiveFilters.org</a>");
		print "<p/>";

		print "<input dojoType=\"dijit.form.CheckBox\" id=\"enable_readability\"
			$enable_readability_checked name=\"enable_readability\">&nbsp;";

		print "<label for=\"enable_readability\">" . __("Extract missing content using Readability") . "</label>";

		print "<p><button dojoType=\"dijit.form.Button\" type=\"submit\">".
			__("Save")."</button>";

		print "</form>";

		print "</div>";
	}

	function save() {
		$enable_readability = checkbox_to_sql_bool($_POST["enable_readability"]) == "true";

		$this->host->set($this, "enable_readability", $enable_readability);

		echo __("Configuration saved");
	}

	private function inline_stuff($article, &$doc, $xpath) {

		$entries = $xpath->query('(//a[@href]|//img[@src])');

		$found = false;

		foreach ($entries as $entry) {
			if ($entry->hasAttribute("href")) {

				$matches = array();

				if (preg_match("/https?:\/\/gfycat.com\/([a-z]+)$/i", $entry->getAttribute("href"), $matches)) {

					$tmp = fetch_file_contents($entry->getAttribute("href"));

					if ($tmp) {
						$tmpdoc = new DOMDocument();
						@$tmpdoc->loadHTML($tmp);

						if ($tmpdoc) {
							$tmpxpath = new DOMXPath($tmpdoc);
							$source_meta = $tmpxpath->query("//meta[@property='og:video']")->item(0);

							if ($source_meta) {
								$source_stream = $source_meta->getAttribute("content");

								if ($source_stream) {
									$this->handle_as_video($doc, $entry, $source_stream);
									$found = 1;
								}
							}
						}
					}

				}

				if (preg_match("/\.(gifv)$/i", $entry->getAttribute("href"))) {

					$source_stream = str_replace(".gifv", ".mp4", $entry->getAttribute("href"));
					$this->handle_as_video($doc, $entry, $source_stream);

					$found = true;
				}

				$matches = array();
				if (preg_match("/\.youtube\.com\/v\/([\w-]+)/", $entry->getAttribute("href"), $matches) ||
					preg_match("/\.youtube\.com\/watch\?v=([\w-]+)/", $entry->getAttribute("href"), $matches) ||
					preg_match("/\/\/youtu.be\/([\w-]+)/", $entry->getAttribute("href"), $matches)) {

					$vid_id = $matches[1];

					$iframe = $doc->createElement("iframe");
					$iframe->setAttribute("class", "youtube-player");
					$iframe->setAttribute("type", "text/html");
					$iframe->setAttribute("width", "640");
					$iframe->setAttribute("height", "385");
					$iframe->setAttribute("src", "https://www.youtube.com/embed/$vid_id");
					$iframe->setAttribute("allowfullscreen", "1");
					$iframe->setAttribute("frameborder", "0");

					$br = $doc->createElement('br');
					$entry->parentNode->insertBefore($iframe, $entry);
					$entry->parentNode->insertBefore($br, $entry);

					$found = true;
				}

				if (preg_match("/\.(jpg|jpeg|gif|png)(\?[0-9][0-9]*)?$/i", $entry->getAttribute("href"))) {
					$img = $doc->createElement('img');
					$img->setAttribute("src", $entry->getAttribute("href"));

					$br = $doc->createElement('br');
					$entry->parentNode->insertBefore($img, $entry);
					$entry->parentNode->insertBefore($br, $entry);

					$found = true;
				}

				// links to imgur pages
				$matches = array();
				if (preg_match("/^https?:\/\/(m\.)?imgur.com\/([^\.\/]+$)/", $entry->getAttribute("href"), $matches)) {

					$token = $matches[2];

					$album_content = fetch_file_contents($entry->getAttribute("href"),
						false, false, false, false, 10);

					if ($album_content && $token) {
						$adoc = new DOMDocument();
						@$adoc->loadHTML($album_content);

						if ($adoc) {
							$axpath = new DOMXPath($adoc);
							$aentries = $axpath->query('(//img[@src])');

							foreach ($aentries as $aentry) {
								if (preg_match("/\/\/i.imgur.com\/$token\./", $aentry->getAttribute("src"))) {
									$img = $doc->createElement('img');
									$img->setAttribute("src", $aentry->getAttribute("src"));

									$br = $doc->createElement('br');

									$entry->parentNode->insertBefore($img, $entry);
									$entry->parentNode->insertBefore($br, $entry);

									$found = true;

									break;
								}
							}
						}
					}
				}

				// linked albums, ffs
				if (preg_match("/^https?:\/\/imgur.com\/(a|album|gallery)\/[^\.]+$/", $entry->getAttribute("href"), $matches)) {

					$album_content = fetch_file_contents($entry->getAttribute("href"),
						false, false, false, false, 10);

					if ($album_content) {
						$adoc = new DOMDocument();
						@$adoc->loadHTML($album_content);

						if ($adoc) {
							$axpath = new DOMXPath($adoc);
							$aentries = $axpath->query("//meta[@property='og:image']");
							$urls = array();

							foreach ($aentries as $aentry) {

								if (!in_array($aentry->getAttribute("content"), $urls)) {
									$img = $doc->createElement('img');
									$img->setAttribute("src", $aentry->getAttribute("content"));
									$entry->parentNode->insertBefore($doc->createElement('br'), $entry);

									$br = $doc->createElement('br');

									$entry->parentNode->insertBefore($img, $entry);
									$entry->parentNode->insertBefore($br, $entry);

									array_push($urls, $aentry->getAttribute("content"));

									$found = true;
								}
							}
						}
					}
				}
			}

			// remove tiny thumbnails
			if ($entry->hasAttribute("src")) {
				if ($entry->parentNode && $entry->parentNode->parentNode) {
					$entry->parentNode->parentNode->removeChild($entry->parentNode);
				}
			}
		}

		return $found;
	}

	function hook_article_filter($article) {

		if (strpos($article["link"], "reddit.com/r/") !== FALSE) {
			$doc = new DOMDocument();
			@$doc->loadHTML($article["content"]);
			$xpath = new DOMXPath($doc);

			$found = $this->inline_stuff($article, $doc, $xpath);

			if (function_exists("curl_init") && !$found && $this->host->get($this, "enable_readability") &&
				mb_strlen(strip_tags($article["content"])) <= 150) {

				if (!class_exists("Readability")) require_once(dirname(dirname(__DIR__)). "/lib/readability/Readability.php");

				$content_link = $xpath->query("(//a[contains(., '[link]')])")->item(0);

				if ($content_link && strpos($content_link->getAttribute("href"), "reddit.com") === FALSE) {

					/* link may lead to a huge video file or whatever, we need to check content type before trying to
					parse it which p much requires curl */

					$ch = curl_init($content_link->getAttribute("href"));
					curl_setopt($ch, CURLOPT_TIMEOUT, 5);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
					curl_setopt($ch, CURLOPT_HEADER, true);
					curl_setopt($ch, CURLOPT_NOBODY, true);
					curl_setopt($ch, CURLOPT_FOLLOWLOCATION,
						!ini_get("safe_mode") && !ini_get("open_basedir"));
					curl_setopt($ch, CURLOPT_USERAGENT, SELF_USER_AGENT);

					@$result = curl_exec($ch);
					$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

					if ($content_type && strpos($content_type, "text/html") !== FALSE) {

						$tmp = fetch_file_contents($content_link->getAttribute("href"));

						if ($tmp) {
							$r = new Readability($tmp, $content_link->getAttribute("href"));

							if ($r->init()) {

								$tmpxpath = new DOMXPath($r->dom);

								$entries = $tmpxpath->query('(//a[@href]|//img[@src])');

								foreach ($entries as $entry) {
									if ($entry->hasAttribute("href")) {
										$entry->setAttribute("href",
											rewrite_relative_url($content_link->getAttribute("href"), $entry->getAttribute("href")));

									}

									if ($entry->hasAttribute("src")) {
										$entry->setAttribute("src",
											rewrite_relative_url($content_link->getAttribute("href"), $entry->getAttribute("src")));

									}

								}

								$article["content"] = $r->articleContent->innerHTML . "<hr/>" . $article["content"];

								// prob not a very good idea (breaks wikipedia pages, etc) -
								// inliner currently is not really fit for any random web content

								//$doc = new DOMDocument();
								//@$doc->loadHTML($article["content"]);
								//$xpath = new DOMXPath($doc);
								//$found = $this->inline_stuff($article, $doc, $xpath);
							}
						}
					}
				}

			}

			$node = $doc->getElementsByTagName('body')->item(0);

			if ($node && $found) {
				$article["content"] = $doc->saveXML($node);
			}
		}

		return $article;
	}

	function api_version() {
		return 2;
	}

	private function handle_as_video($doc, $entry, $source_stream) {

		$video = $doc->createElement('video');
		$video->setAttribute("autoplay", "1");
		$video->setAttribute("controls", "1");
		$video->setAttribute("loop", "1");

		$source = $doc->createElement('source');
		$source->setAttribute("src", $source_stream);
		$source->setAttribute("type", "video/mp4");

		$video->appendChild($source);

		$br = $doc->createElement('br');
		$entry->parentNode->insertBefore($video, $entry);
		$entry->parentNode->insertBefore($br, $entry);

		$img = $doc->createElement('img');
		$img->setAttribute("src",
			"data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs%3D");

		$entry->parentNode->insertBefore($img, $entry);
	}
}
?>
